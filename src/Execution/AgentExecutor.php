<?php

namespace Vizra\VizraADK\Execution;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Laravel\SerializableClosure\SerializableClosure;
use Prism\Prism\ValueObjects\Media\Document;
use Prism\Prism\ValueObjects\Media\Image;
use Vizra\VizraADK\Jobs\AgentJob;
use Vizra\VizraADK\Services\AgentManager;
use Vizra\VizraADK\Services\StateManager;

class AgentExecutor
{
    protected string $agentClass;

    protected mixed $input;

    protected ?Model $user = null;

    protected ?string $sessionId = null;

    protected array $context = [];

    protected bool $streaming = false;

    protected array $parameters = [];

    protected bool $async = false;

    protected ?string $queue = null;

    protected ?int $delay = null;

    protected int $tries = 3;

    protected ?int $timeout = null;

    protected ?string $promptVersion = null;

    /** @var array<Image> */
    protected array $images = [];

    /** @var array<Document> */
    protected array $documents = [];

    /**
     * Lightweight user context array (takes precedence over user model serialization)
     */
    protected ?array $userContext = null;

    /**
     * Callback to execute when the agent completes
     *
     * @var callable|null
     */
    protected $onComplete = null;

    public function __construct(string $agentClass, mixed $input)
    {
        $this->agentClass = $agentClass;
        $this->input = $input;
    }

    /**
     * Set the user context for this execution
     */
    public function forUser(?Model $user): self
    {
        $this->user = $user;

        return $this;
    }

    /**
     * Set lightweight user context (takes precedence over user model serialization)
     *
     * This allows passing minimal user data instead of serializing the entire model,
     * which is useful for preventing large relationship trees from being stored in context.
     *
     * Example:
     * ```php
     * ->withUserContext([
     *     'user_id' => $user->id,
     *     'user_name' => $user->name,
     *     'user_email' => $user->email,
     * ])
     * ```
     */
    public function withUserContext(array $userContext): self
    {
        $this->userContext = $userContext;

        return $this;
    }

    /**
     * Set a specific session ID
     */
    public function withSession(string $sessionId): self
    {
        $this->sessionId = $sessionId;

        return $this;
    }

    /**
     * Add additional context data
     */
    public function withContext(array $context): self
    {
        $this->context = array_merge($this->context, $context);

        return $this;
    }

    /**
     * Enable streaming for this execution
     */
    public function streaming(bool $enabled = true): self
    {
        $this->streaming = $enabled;

        return $this;
    }

    /**
     * Set agent parameters (temperature, max_tokens, etc.)
     */
    public function withParameters(array $parameters): self
    {
        $this->parameters = array_merge($this->parameters, $parameters);

        return $this;
    }

    /**
     * Set temperature for this execution
     */
    public function temperature(float $temperature): self
    {
        $this->parameters['temperature'] = $temperature;

        return $this;
    }

    /**
     * Set max tokens for this execution
     */
    public function maxTokens(int $maxTokens): self
    {
        $this->parameters['max_tokens'] = $maxTokens;

        return $this;
    }

    /**
     * Set prompt version for this execution
     */
    public function withPromptVersion(string $version): self
    {
        $this->promptVersion = $version;

        return $this;
    }

    /**
     * Execute the agent asynchronously using Laravel queues
     */
    public function async(bool $enabled = true): self
    {
        $this->async = $enabled;

        return $this;
    }

    /**
     * Specify which queue to use for async execution
     */
    public function onQueue(string $queue): self
    {
        $this->queue = $queue;
        $this->async = true; // Auto-enable async when queue is specified

        return $this;
    }

    /**
     * Delay the execution by specified seconds
     */
    public function delay(int $seconds): self
    {
        $this->delay = $seconds;

        return $this;
    }

    /**
     * Set number of retry attempts for failed executions
     */
    public function tries(int $tries): self
    {
        $this->tries = $tries;

        return $this;
    }

    /**
     * Set timeout for agent execution
     */
    public function timeout(int $seconds): self
    {
        $this->timeout = $seconds;

        return $this;
    }

    /**
     * Set a callback to execute when the agent completes
     *
     * The callback receives the result and context array:
     * fn(mixed $result, array $context) => void
     *
     * For async execution, the callback is serialized and executed
     * by the queue worker after the agent completes.
     *
     * @param  callable  $callback  The callback to execute on completion
     */
    public function onComplete(callable $callback): self
    {
        $this->onComplete = $callback;

        return $this;
    }

    /**
     * Add an image to the conversation using Prism's Image class.
     *
     * @param  string  $path  Path to the image file
     * @param  string|null  $mimeType  Optional MIME type (auto-detected if not provided)
     */
    public function withImage(string $path, ?string $mimeType = null): self
    {
        $this->images[] = Image::fromPath($path, $mimeType);

        return $this;
    }

    /**
     * Add an image from base64 data using Prism's Image class.
     *
     * @param  string  $base64Data  Base64 encoded image data
     * @param  string  $mimeType  MIME type of the image
     */
    public function withImageFromBase64(string $base64Data, string $mimeType): self
    {
        $this->images[] = Image::fromBase64($base64Data, $mimeType);

        return $this;
    }

    /**
     * Add an image from a URL using Prism's Image class.
     *
     * @param  string  $url  URL to the image
     */
    public function withImageFromUrl(string $url): self
    {
        $this->images[] = Image::fromUrl($url);

        return $this;
    }

    /**
     * Add a document to the conversation using Prism's Document class.
     *
     * @param  string  $path  Path to the document file
     * @param  string|null  $mimeType  Optional MIME type (auto-detected if not provided)
     */
    public function withDocument(string $path, ?string $mimeType = null): self
    {
        $this->documents[] = Document::fromPath($path, $mimeType);

        return $this;
    }

    /**
     * Add a document from base64 data using Prism's Document class.
     *
     * @param  string  $base64Data  Base64 encoded document data
     * @param  string  $mimeType  MIME type of the document
     */
    public function withDocumentFromBase64(string $base64Data, string $mimeType): self
    {
        $this->documents[] = Document::fromBase64($base64Data, $mimeType);

        return $this;
    }

    /**
     * Add a document from a URL using Prism's Document class.
     *
     * @param  string  $url  URL to the document
     */
    public function withDocumentFromUrl(string $url): self
    {
        $this->documents[] = Document::fromUrl($url);

        return $this;
    }

    /**
     * Execute the agent and return the response
     */
    public function go(): mixed
    {
        // If async execution is requested, dispatch to queue
        if ($this->async) {
            return $this->dispatchAsync();
        }

        return $this->executeSynchronously();
    }

    /**
     * Execute the agent synchronously
     */
    protected function executeSynchronously(): mixed
    {
        $agentManager = app(AgentManager::class);
        $stateManager = app(StateManager::class);

        // Generate session ID if not provided
        $sessionId = $this->resolveSessionId();

        // Get agent name
        $agentName = $this->getAgentName();

        // Determine user_id BEFORE creating session
        // This ensures the session is properly associated with the user
        $userId = null;
        if ($this->userContext !== null) {
            // Priority 1: Extract from explicit userContext
            $userId = $this->userContext['user_id'] ?? $this->userContext['id'] ?? null;
        } elseif ($this->user) {
            // Priority 2 & 3: Extract from User model
            $userId = $this->user->getKey();
        }

        // Load or create agent context with the correct user_id
        $agentContext = $stateManager->loadContext($agentName, $sessionId, $this->input, $userId);

        // Add user information to context
        if ($this->user || $this->userContext) {
            // Determine user data to store
            $userData = null;
            $userModel = null;

            // Priority 1: Explicit lightweight user context
            if ($this->userContext !== null) {
                $userData = $this->userContext;
                $userModel = $this->user ? get_class($this->user) : null;
            }
            // Priority 2: Model with custom toAgentContext() method
            elseif ($this->user && method_exists($this->user, 'toAgentContext')) {
                $userData = $this->user->toAgentContext();
                $userModel = get_class($this->user);
            }
            // Priority 3: Full model serialization (backward compatibility)
            elseif ($this->user) {
                $userData = $this->user->toArray();
                $userModel = get_class($this->user);
            }

            // Store in context
            if ($userId !== null) {
                $agentContext->setState('user_id', $userId);
            }
            if ($userModel !== null) {
                $agentContext->setState('user_model', $userModel);
            }
            if ($userData !== null) {
                $agentContext->setState('user_data', $userData);
            }

            // Add user-specific context helpers
            if (isset($userData['email'])) {
                $agentContext->setState('user_email', $userData['email']);
            } elseif ($this->user && method_exists($this->user, 'email')) {
                $agentContext->setState('user_email', $this->user->email);
            }

            if (isset($userData['name'])) {
                $agentContext->setState('user_name', $userData['name']);
            } elseif ($this->user && method_exists($this->user, 'name')) {
                $agentContext->setState('user_name', $this->user->name);
            }
        }

        // Add additional context
        foreach ($this->context as $key => $value) {
            $agentContext->setState($key, $value);
        }

        // Add Prism Image and Document objects to context
        if (! empty($this->images)) {
            // Store image metadata instead of actual objects for serialization
            $imageMetadata = [];
            foreach ($this->images as $image) {
                // The Image class uses methods to access data
                $imageMetadata[] = [
                    'type' => 'image',
                    'data' => $image->base64(),  // This is the base64 encoded image data
                    'mimeType' => $image->mimeType(),
                ];
            }
            $agentContext->setState('prism_images_metadata', $imageMetadata);
            // Also store the actual objects for immediate use
            $agentContext->setState('prism_images', $this->images);
        }
        if (! empty($this->documents)) {
            // Store document metadata for consistency
            $documentMetadata = [];
            foreach ($this->documents as $document) {
                // Document class uses methods to access data
                $documentMetadata[] = [
                    'type' => 'document',
                    'data' => $document->base64(),
                    'mimeType' => $document->mimeType(),
                    'dataFormat' => 'base64',
                    'documentTitle' => $document->documentTitle(),
                    'documentContext' => null,
                ];
            }
            $agentContext->setState('prism_documents_metadata', $documentMetadata);
            // Also store the actual objects for immediate use
            $agentContext->setState('prism_documents', $this->documents);
        }

        // Add agent parameters
        if (! empty($this->parameters)) {
            $agentContext->setState('agent_parameters', $this->parameters);
        }

        // Set prompt version if specified
        if ($this->promptVersion !== null) {
            $agentContext->setState('prompt_version', $this->promptVersion);
        }

        // Set streaming mode
        if ($this->streaming) {
            $agentContext->setState('streaming', true);
        }

        // Set timeout if specified
        if ($this->timeout) {
            set_time_limit($this->timeout);
        }

        // Save the context state before running so it's available when AgentManager loads context
        $stateManager->saveContext($agentContext, $agentName, false);

        // Execute the agent
        $result = $agentManager->run($agentName, $this->input, $sessionId);

        // Execute onComplete callback if provided
        if ($this->onComplete !== null) {
            call_user_func($this->onComplete, $result, [
                'agent' => $agentName,
                'session_id' => $sessionId,
                'user_id' => $userId,
            ]);
        }

        return $result;
    }

    /**
     * Dispatch the agent execution to a queue
     */
    protected function dispatchAsync(): mixed
    {
        $job = new AgentJob(
            $this->agentClass,
            $this->input,
            $this->resolveSessionId(),
            $this->buildJobContext()
        );

        // Configure job settings
        if ($this->queue) {
            $job->onQueue($this->queue);
        }

        if ($this->delay) {
            $job->delay($this->delay);
        }

        if ($this->tries) {
            $job->tries = $this->tries;
        }

        if ($this->timeout) {
            $job->timeout = $this->timeout;
        }

        // Dispatch the job
        dispatch($job);

        // Return job ID for tracking
        return [
            'job_dispatched' => true,
            'job_id' => $job->getJobId(),
            'queue' => $this->queue ?: 'default',
            'agent' => $this->getAgentName(),
        ];
    }

    /**
     * Build context data for job serialization
     */
    protected function buildJobContext(): array
    {
        $context = [
            'context_data' => $this->context,
            'parameters' => $this->parameters,
            'streaming' => $this->streaming,
            // Note: Prism Image/Document objects need special serialization for queue jobs
            'images' => array_map(fn ($img) => ['type' => 'serialized', 'data' => serialize($img)], $this->images),
            'documents' => array_map(fn ($doc) => ['type' => 'serialized', 'data' => serialize($doc)], $this->documents),
        ];

        if ($this->user) {
            $context['user'] = [
                'id' => $this->user->getKey(),
                'model' => get_class($this->user),
                'data' => $this->user->toArray(),
            ];

            // Add user-specific helpers
            if (method_exists($this->user, 'email')) {
                $context['user']['email'] = $this->user->email;
            }
            if (method_exists($this->user, 'name')) {
                $context['user']['name'] = $this->user->name;
            }
        }

        // Serialize the onComplete callback using Laravel's SerializableClosure
        if ($this->onComplete !== null) {
            $context['on_complete'] = serialize(new SerializableClosure($this->onComplete));
        }

        return $context;
    }

    /**
     * Get the agent name from the class
     *
     * For dynamic agents (multi-tenancy, runtime registration), we resolve from the registry
     * to ensure proper instantiation with required context before calling getName().
     */
    protected function getAgentName(): string
    {
        // If agent_name was explicitly provided in context, use it (highest priority)
        if (isset($this->context['agent_name']) && !empty($this->context['agent_name'])) {
            return $this->context['agent_name'];
        }

        try {
            // Try to resolve from agent registry first (handles dynamic agents)
            $agentManager = app(AgentManager::class);
            $allAgents = $agentManager->getAllRegisteredAgents();

            foreach ($allAgents as $registeredName => $registeredClass) {
                if ($registeredClass === $this->agentClass) {
                    try {
                        $agent = \Vizra\VizraADK\Facades\Agent::named($registeredName);
                        if (method_exists($agent, 'getName')) {
                            return $agent->getName();
                        }
                    } catch (\Exception $e) {
                        // Continue to fallback if registry resolution fails
                    }
                }
            }

            // Fallback: Direct instantiation for simple agents
            $agent = app($this->agentClass);

            if (method_exists($agent, 'getName')) {
                return $agent->getName();
            }
        } catch (\Exception $e) {
            // If all else fails, use class name transformation
        }

        // Final fallback to class name transformation
        $className = class_basename($this->agentClass);

        return Str::snake(str_replace('Agent', '', $className));
    }

    /**
     * Resolve the session ID
     */
    protected function resolveSessionId(): string
    {
        if ($this->sessionId) {
            return $this->sessionId;
        }

        if ($this->user) {
            return 'user_'.$this->user->getKey().'_'.Str::random(8);
        }

        return 'session_'.Str::random(12);
    }

    /**
     * Magic method to auto-execute when used as string
     */
    public function __toString(): string
    {
        try {
            $result = $this->go();

            return is_string($result) ? $result : (string) $result;
        } catch (\Exception $e) {
            return 'Error executing agent: '.$e->getMessage();
        }
    }

    /**
     * Magic method to execute when called
     */
    public function __invoke(): mixed
    {
        return $this->go();
    }
}
