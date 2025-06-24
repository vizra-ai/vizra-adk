<?php

namespace Vizra\VizraADK\Execution;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Prism\Prism\ValueObjects\Messages\Support\Document;
use Prism\Prism\ValueObjects\Messages\Support\Image;
use Vizra\VizraADK\Jobs\AgentJob;
use Vizra\VizraADK\Services\AgentManager;
use Vizra\VizraADK\Services\StateManager;

class AgentExecutor
{
    protected string $agentClass;

    protected mixed $input;

    protected string $mode;

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

    public function __construct(string $agentClass, mixed $input, string $mode = 'ask')
    {
        $this->agentClass = $agentClass;
        $this->input = $input;
        $this->mode = $mode;
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

        // Load or create agent context
        $agentContext = $stateManager->loadContext($agentName, $sessionId, $this->input);

        // Add execution mode to context
        $agentContext->setState('execution_mode', $this->mode);

        // Add user information to context
        if ($this->user) {
            $agentContext->setState('user_id', $this->user->getKey());
            $agentContext->setState('user_model', get_class($this->user));
            $agentContext->setState('user_data', $this->user->toArray());

            // Add user-specific context helpers
            if (method_exists($this->user, 'email')) {
                $agentContext->setState('user_email', $this->user->email);
            }
            if (method_exists($this->user, 'name')) {
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
                // The Image class has public properties: image (base64 data) and mimeType
                $imageMetadata[] = [
                    'type' => 'image',
                    'data' => $image->image,  // This is the base64 encoded image data
                    'mimeType' => $image->mimeType,
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
                // Document class has: document (string|array), mimeType, dataFormat, documentTitle, documentContext
                $documentMetadata[] = [
                    'type' => 'document',
                    'data' => is_string($document->document) ? $document->document : json_encode($document->document),
                    'mimeType' => $document->mimeType,
                    'dataFormat' => $document->dataFormat,
                    'documentTitle' => $document->documentTitle,
                    'documentContext' => $document->documentContext,
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
        return $agentManager->run($agentName, $this->input, $sessionId);
    }

    /**
     * Dispatch the agent execution to a queue
     */
    protected function dispatchAsync(): mixed
    {
        $job = new AgentJob(
            $this->agentClass,
            $this->input,
            $this->mode,
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
            'mode' => $this->mode,
        ];
    }

    /**
     * Build context data for job serialization
     */
    protected function buildJobContext(): array
    {
        $context = [
            'execution_mode' => $this->mode,
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

        return $context;
    }

    /**
     * Get the agent name from the class
     */
    protected function getAgentName(): string
    {
        try {
            // Try to instantiate the agent to get its name
            $agent = app($this->agentClass);

            if (method_exists($agent, 'getName')) {
                return $agent->getName();
            }
        } catch (\Exception $e) {
            // If agent instantiation fails, fall back to class name transformation
        }

        // Fallback to class name transformation
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
