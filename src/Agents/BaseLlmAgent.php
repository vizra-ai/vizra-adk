<?php

namespace Vizra\VizraADK\Agents;

use Generator;
use Illuminate\Support\Facades\Event;
use Prism\Prism\Enums\Provider;
use Prism\Prism\Facades\Tool;
use Prism\Prism\Prism;
use Prism\Prism\PrismManager;
use Prism\Prism\Schema\StringSchema;
use Prism\Prism\Text\PendingRequest;
use Prism\Prism\Text\Response;
use Prism\Prism\ValueObjects\Media\Document;
use Prism\Prism\ValueObjects\Media\Image;
use Prism\Prism\ValueObjects\Messages\AssistantMessage;
use Prism\Prism\ValueObjects\Messages\UserMessage;
use Prism\Prism\ValueObjects\ProviderTool;
use Prism\Prism\ValueObjects\Usage;
use Throwable;
use Vizra\VizraADK\Contracts\ToolInterface;
use Vizra\VizraADK\Events\AgentResponseGenerated;
use Vizra\VizraADK\Events\LLmCallFailed;
use Vizra\VizraADK\Events\LlmCallInitiating; // Use the Tool facade instead of Tool class
use Vizra\VizraADK\Events\LlmResponseReceived;
use Vizra\VizraADK\Events\ToolCallCompleted;
use Vizra\VizraADK\Events\ToolCallFailed;
use Vizra\VizraADK\Events\ToolCallInitiating;
use Vizra\VizraADK\Exceptions\ToolExecutionException;
use Vizra\VizraADK\Memory\AgentMemory;
use Vizra\VizraADK\Services\AgentVectorProxy;
use Vizra\VizraADK\Services\Tracer;
use Vizra\VizraADK\System\AgentContext;
use Vizra\VizraADK\Traits\HasLogging;
use Vizra\VizraADK\Traits\VersionablePrompts;

abstract class BaseLlmAgent extends BaseAgent
{
    use HasLogging;
    use VersionablePrompts;

    /**
     * Framework control parameters that should not be included in context messages.
     * These are used internally to control agent behavior.
     */
    private const CONTROL_PARAMS = [
        'include_history',
        'history_depth',
        'context_strategy',
        'llm_call_span_id',
        'execution_mode',
        'prism_images',
        'prism_documents',
        'prism_images_metadata',
        'prism_documents_metadata',
        'memory_context',  // Already handled separately in instructions
        'mcp_config_overrides',  // Tenant-specific MCP configuration (may contain API keys)
        'agent_name',  // Dynamic agent name for multi-tenant session isolation
        'agent_parameters',  // Runtime agent parameters (temperature, max_tokens, etc.)
        'prompt_version',  // Prompt versioning control
        'streaming',  // Streaming mode flag
    ];

    protected string $instructions = '';

    protected string $model = '';

    protected ?string $provider = null;

    protected ?float $temperature = null;

    protected int $maxSteps = 5;

    protected ?int $maxTokens = null;

    protected ?float $topP = null;

    protected bool $streaming = false;

    /** @var array<class-string<ToolInterface>> */
    protected array $tools = [];

    /** @var array<ToolInterface> */
    protected array $loadedTools = [];

    /**
     * Provider tools configuration.
     * Can be either:
     * - Simple strings: ['some_tool_12345']
     * - Arrays with config: [['type' => 'code_execution_20250522', 'name' => 'code_execution']]
     *
     * @var array<string|array>
     */
    protected array $providerTools = [];

    /** @var array<string, BaseLlmAgent> */
    protected array $loadedSubAgents = [];

    /** @var array<class-string<BaseLlmAgent>> */
    protected array $subAgents = [];

    /** @var array<string> */
    protected array $mcpServers = [];

    protected ?AgentMemory $memory = null;

    protected ?AgentContext $context = null;

    /**
     * Whether to include conversation history in LLM calls.
     * Default is false for lightweight, task-oriented agents.
     * Set to true for conversational agents.
     */
    protected bool $includeConversationHistory = false;

    /**
     * Whether this agent should be shown in the chat UI.
     * Default is true to show all agents.
     * Set to false to hide from the chat interface.
     */
    protected bool $showInChatUi = true;

    /**
     * Maximum number of historical messages to include when history is enabled.
     * Only applies when $includeConversationHistory is true.
     */
    protected int $historyLimit = 10;

    /**
     * Context strategy for managing conversation history.
     * Options: 'none', 'recent', 'full', 'smart'
     * - 'none': No history included (default)
     * - 'recent': Include last N messages based on historyLimit
     * - 'full': Include all conversation history
     * - 'smart': Use relevance-based filtering (future feature)
     */
    protected string $contextStrategy = 'none';

    /**
     * Enable OpenAI Responses API for stateful conversations.
     * When true, automatically manages response IDs for conversation continuity.
     * This allows OpenAI to maintain conversation state server-side.
     */
    protected bool $useStatefulResponses = false;

    /**
     * Track active tool call spans to survive Prism's internal execution
     *
     * @var array<string, string>
     */
    private array $activeToolSpans = [];

    public function __construct()
    {
        // Initialize tools and sub-agents right away so definitions are available
        $this->loadTools();
        $this->loadSubAgents();
    }

    /**
     * @param  array  $definition
     * @return \Prism\Prism\Tool
     */
    protected function createPrismTool(array $definition): \Prism\Prism\Tool
    {
        return Tool::as($definition['name'])
            ->for($definition['description']);
    }

    /**
     * @param  AgentContext  $context
     * @param  array  $messages
     * @return PendingRequest
     */
    protected function buildPrismRequest(AgentContext $context, array $messages): PendingRequest
    {
        $prismRequest = Prism::text()
            ->using($this->getProvider(), $this->getModel());

        // Apply HTTP timeout configuration
        $httpConfig = config('vizra-adk.http', []);
        if (! empty($httpConfig)) {
            $clientOptions = [];
            if (isset($httpConfig['timeout'])) {
                $clientOptions['timeout'] = $httpConfig['timeout'];
            }
            if (isset($httpConfig['connect_timeout'])) {
                $clientOptions['connect_timeout'] = $httpConfig['connect_timeout'];
            }
            if (! empty($clientOptions)) {
                $prismRequest = $prismRequest->withClientOptions($clientOptions);
            }
        }

        // Add system prompt if available (now includes memory context)
        if (! empty($this->getInstructions())) {
            $prismRequest = $prismRequest->withSystemPrompt($this->getInstructionsWithMemory($context));
        }

        // Add messages for conversation history
        $prismRequest = $prismRequest->withMessages($messages);

        if (! empty($this->providerTools)) {
            $prismRequest = $prismRequest->withProviderTools($this->getProviderToolsForPrism());
        }

        // Add tools if available
        $allTools = array_merge($this->loadedTools, ! empty($this->loadedSubAgents) ? [new \Vizra\VizraADK\Tools\DelegateToSubAgentTool($this)] : []);
        if (! empty($allTools)) {
            $prismRequest = $prismRequest->withTools($this->getToolsForPrism($context))
                ->withMaxSteps($this->maxSteps); // Prism will handle tool execution internally
        }

        // Add generation parameters if set
        if ($this->getTemperature() !== null) {
            $prismRequest = $prismRequest->usingTemperature($this->getTemperature());
        }

        if ($this->getMaxTokens() !== null) {
            $prismRequest = $prismRequest->withMaxTokens($this->getMaxTokens());
        }

        if ($this->getTopP() !== null) {
            $prismRequest = $prismRequest->usingTopP($this->getTopP());
        }

        // Add previous response ID for stateful conversations
        if ($this->useStatefulResponses) {
            $previousResponseId = $context->getState("response_id_{$this->getName()}");
            if ($previousResponseId) {
                // Merge with existing provider options to avoid overwriting
                $existingOptions = $prismRequest->providerOptions() ?: [];
                $prismRequest = $prismRequest->withProviderOptions(array_merge(
                    $existingOptions,
                    ['previous_response_id' => $previousResponseId]
                ));
            }
        }

        return $prismRequest;
    }

    protected function getProvider(): string
    {
        // Handle if provider is already a Provider enum instance
        // This supports agents that set the provider property directly as an enum
        if ($this->provider instanceof Provider) {
            return $this->provider->value;
        }

        if ($this->provider === null) {
            $defaultProvider = config('vizra-adk.default_provider', 'openai');
            $this->provider = match ($defaultProvider) {
                'openai' => Provider::OpenAI->value,
                'anthropic' => Provider::Anthropic->value,
                'gemini', 'google' => Provider::Gemini->value,
                'deepseek' => Provider::DeepSeek->value,
                'ollama' => Provider::Ollama->value,
                'mistral' => Provider::Mistral->value,
                'groq' => Provider::Groq->value,
                'xai', 'grok' => Provider::XAI->value,
                'voyageai', 'voyage' => Provider::VoyageAI->value,
                'openrouter' => Provider::OpenRouter->value,
                default => $this->resolveCustomProvider($defaultProvider),
            };
        }

        return $this->provider;
    }

    protected function resolveCustomProvider(string $provider): string
    {
        return tap($provider, fn (string $provider) => resolve(PrismManager::class)->resolve($provider));
    }

    public function getModel(): string
    {
        $model = $this->model ?: config('vizra-adk.default_model', 'gpt-4o');

        // Auto-detect provider based on model name if not explicitly set
        if ($this->provider === null) {
            if (str_contains($model, 'gemini') || str_contains($model, 'flash')) {
                $this->provider = Provider::Gemini->value;
            } elseif (str_contains($model, 'claude')) {
                $this->provider = Provider::Anthropic->value;
            } elseif (str_contains($model, 'gpt') || str_contains($model, 'o1')) {
                $this->provider = Provider::OpenAI->value;
            } elseif (str_contains($model, 'deepseek')) {
                $this->provider = Provider::DeepSeek->value;
            } elseif (str_contains($model, 'mistral') || str_contains($model, 'mixtral')) {
                $this->provider = Provider::Mistral->value;
            } elseif (str_contains($model, 'llama') || str_contains($model, 'codellama') || str_contains($model, 'phi')) {
                $this->provider = Provider::Ollama->value;
            } elseif (str_contains($model, 'groq')) {
                $this->provider = Provider::Groq->value;
            } elseif (str_contains($model, 'grok')) {
                $this->provider = Provider::XAI->value;
            } elseif (str_contains($model, 'voyage')) {
                $this->provider = Provider::VoyageAI->value;
            }
        }

        return $model;
    }

    // Now provided by VersionablePrompts trait
    // Original getInstructions logic moved to trait and enhanced with versioning support

    /**
     * Get instructions with memory context included.
     */
    public function getInstructionsWithMemory(AgentContext $context): string
    {
        $instructions = $this->getInstructions();

        // Add memory context if available
        $memoryContext = $context->getState('memory_context');
        if (! empty($memoryContext)) {
            // Handle memory_context that might be an array
            $memoryContextString = is_array($memoryContext) || is_object($memoryContext)
                ? json_encode($memoryContext, JSON_PRETTY_PRINT)
                : (string) $memoryContext;

            $memoryInfo = "\n\nMEMORY CONTEXT:\n" .
                "Based on your previous interactions, here's what you should remember:\n\n" .
                $memoryContextString . "\n\n" .
                'Use this information to provide more personalized and contextual responses. ' .
                'Build upon previous conversations and maintain continuity in your interactions.';

            $instructions .= $memoryInfo;
        }

        return $instructions;
    }

    public function setInstructions(string $instructions): static
    {
        $this->instructions = $instructions;

        return $this;
    }

    public function setModel(string $model): static
    {
        $this->model = $model;

        return $this;
    }

    public function getTemperature(): ?float
    {
        return $this->temperature ?? config('vizra-adk.default_generation_params.temperature');
    }

    public function setTemperature(?float $temperature): static
    {
        $this->temperature = $temperature;

        return $this;
    }

    public function getMaxTokens(): ?int
    {
        return $this->maxTokens ?? config('vizra-adk.default_generation_params.max_tokens');
    }

    public function setMaxTokens(?int $maxTokens): static
    {
        $this->maxTokens = $maxTokens;

        return $this;
    }

    public function getTopP(): ?float
    {
        return $this->topP ?? config('vizra-adk.default_generation_params.top_p');
    }

    public function setTopP(?float $topP): static
    {
        $this->topP = $topP;

        return $this;
    }

    public function getStreaming(): bool
    {
        return $this->streaming;
    }

    public function setStreaming(bool $streaming): static
    {
        $this->streaming = $streaming;

        return $this;
    }

    public function getShowInChatUi(): bool
    {
        return $this->showInChatUi;
    }

    public function setShowInChatUi(bool $showInChatUi): static
    {
        $this->showInChatUi = $showInChatUi;

        return $this;
    }

    /**
     * Set the provider for this agent.
     * Supports all Prism providers: OpenAI, Anthropic, Gemini, DeepSeek, Ollama, Mistral, Groq, XAI, VoyageAI, OpenRouter
     *
     * @param  Provider|string  $provider  The provider enum or string name
     */
    public function setProvider(Provider | string $provider): static
    {
        if (is_string($provider)) {
            $provider = match (strtolower($provider)) {
                'openai' => Provider::OpenAI,
                'anthropic' => Provider::Anthropic,
                'gemini', 'google' => Provider::Gemini,
                'deepseek' => Provider::DeepSeek,
                'ollama' => Provider::Ollama,
                'mistral' => Provider::Mistral,
                'groq' => Provider::Groq,
                'xai', 'grok' => Provider::XAI,
                'voyageai', 'voyage' => Provider::VoyageAI,
                'openrouter' => Provider::OpenRouter,
                default => $this->resolveCustomProvider($provider)
            };
        }

        $this->provider = $provider instanceof Provider ? $provider->value : $provider;

        return $this;
    }

    public function loadTools(): void
    {
        if (! empty($this->loadedTools)) {
            return;
        }

        // Load traditional tools
        foreach ($this->tools as $toolClass) {
            if (class_exists($toolClass) && is_subclass_of($toolClass, ToolInterface::class)) {
                $toolInstance = app($toolClass); // Resolve from container
                $this->loadedTools[$toolInstance->definition()['name']] = $toolInstance;
            }
        }

        // Load MCP tools if the discovery service is available
        if (app()->bound(\Vizra\VizraADK\Services\MCP\MCPToolDiscovery::class)) {
            try {
                $mcpDiscovery = app(\Vizra\VizraADK\Services\MCP\MCPToolDiscovery::class);
                $mcpTools = $mcpDiscovery->discoverToolsForAgent($this);

                foreach ($mcpTools as $mcpTool) {
                    $toolName = $mcpTool->definition()['name'];

                    // Avoid name conflicts - prefix MCP tools if needed
                    if (isset($this->loadedTools[$toolName])) {
                        $serverName = $mcpTool->getServerName();
                        $toolName = "{$serverName}_{$toolName}";
                    }

                    $this->loadedTools[$toolName] = $mcpTool;
                }
            } catch (\Exception $e) {
                // Log the error but don't fail the agent loading
                $this->logWarning('Failed to load MCP tools for agent {agent}: {error}', [
                    'agent' => $this->getName(),
                    'error' => $e->getMessage(),
                ], 'mcp');
            }
        }
    }

    protected function loadSubAgents(): void
    {
        if (! empty($this->loadedSubAgents)) {
            return;
        }

        foreach ($this->subAgents as $subAgentClass) {
            if (class_exists($subAgentClass) && is_subclass_of($subAgentClass, BaseLlmAgent::class)) {
                // Generate a name from the class name
                $className = class_basename($subAgentClass);
                // Convert CamelCase to snake_case and remove 'Agent' suffix
                $name = strtolower(preg_replace('/([a-z])([A-Z])/', '$1_$2', str_replace('Agent', '', $className)));

                $subAgentInstance = app($subAgentClass); // Resolve from container
                $this->loadedSubAgents[$name] = $subAgentInstance;
            }
        }
    }

    /**
     * Get a loaded sub-agent instance by its registered name.
     *
     * @param  string  $name  The name of the sub-agent
     * @return BaseLlmAgent|null The sub-agent instance or null if not found
     */
    public function getSubAgent(string $name): ?BaseLlmAgent
    {
        return $this->loadedSubAgents[$name] ?? null;
    }

    /**
     * Get all loaded sub-agents.
     *
     * @return array Array of loaded sub-agent instances keyed by name
     */
    public function getLoadedSubAgents(): array
    {
        return $this->loadedSubAgents;
    }

    /**
     * @return array<\Prism\Prism\Tool>
     */
    protected function getToolsForPrism(AgentContext $context): array
    {
        $tools = [];

        // Include delegation tool if sub-agents are available
        $allTools = $this->loadedTools;
        if (! empty($this->loadedSubAgents)) {
            $delegateTool = new \Vizra\VizraADK\Tools\DelegateToSubAgentTool($this);
            $allTools[] = $delegateTool;
            // Also store in loadedTools so hooks can find it
            $this->loadedTools['delegate_to_sub_agent'] = $delegateTool;
        }

        foreach ($allTools as $tool) {
            $definition = $tool->definition();

            // Create Prism Tool using the correct facade API
            $prismTool = $this->createPrismTool($definition);

            // Keep track of parameter order for the callback
            $parameterOrder = [];

            // Add parameters based on the definition
            if (! empty($definition['parameters']['properties'])) {
                foreach ($definition['parameters']['properties'] as $paramName => $paramDef) {
                    $description = $paramDef['description'] ?? '';
                    $parameterOrder[] = $paramName;

                    switch ($paramDef['type'] ?? 'string') {
                        case 'string':
                            $prismTool = $prismTool->withStringParameter($paramName, $description);

                            break;
                        case 'number':
                        case 'integer':
                            $prismTool = $prismTool->withNumberParameter($paramName, $description);

                            break;
                        case 'boolean':
                            $prismTool = $prismTool->withBooleanParameter($paramName, $description);

                            break;
                        case 'array':
                            $prismTool = $prismTool->withArrayParameter($paramName, $description, new StringSchema('item', 'Array item'));

                            break;
                        default:
                            $prismTool = $prismTool->withStringParameter($paramName, $description);
                    }
                }
            }

            // Set the tool execution callback - handle tools with or without parameters
            $prismTool = $prismTool->using(function (...$args) use ($tool, $context, $parameterOrder) {
                try {
                    // Check if we have named parameters vs indexed parameters
                    $hasNamedKeys = ! empty($args) && ! array_is_list($args);

                    // Handle different argument formats from Prism
                    $arguments = [];

                    if ($hasNamedKeys) {
                        // Prism passed named parameters directly
                        $arguments = $args;
                    } elseif (count($args) === 1 && is_array($args[0])) {
                        // Prism passed a single associative array
                        $arguments = $args[0];
                    } else {
                        // Prism passed indexed arguments - map them using parameter order
                        foreach ($parameterOrder as $index => $paramName) {
                            if (isset($args[$index])) {
                                $arguments[$paramName] = $args[$index];
                            }
                        }
                    }

                    // Only validate required parameters if the tool has parameters
                    if (! empty($parameterOrder)) {
                        $definition = $tool->definition();
                        $required = $definition['parameters']['required'] ?? [];
                        foreach ($required as $requiredParam) {
                            if (! isset($arguments[$requiredParam]) || $arguments[$requiredParam] === null) {
                                throw new ToolExecutionException("Required parameter '{$requiredParam}' is missing or null");
                            }
                        }
                    }

                    // Log tool execution start
                    logger()->info('Tool execution starting', [
                        'tool_name' => $tool->definition()['name'],
                        'agent' => $this->getName(),
                        'arguments' => $arguments,
                    ]);

                    // Dispatch event and call the beforeToolCall hook to enable tracing
                    Event::dispatch(new ToolCallInitiating($context, $this->getName(), $tool->definition()['name'], $arguments));

                    // Call the beforeToolCall hook - this triggers the tracer to start a span
                    $processedArgs = $this->beforeToolCall($tool->definition()['name'], $arguments, $context);

                    // Execute the tool with processed arguments
                    $result = $tool->execute($processedArgs, $context, $this->memory());

                    // Log tool execution result
                    logger()->info('Tool execution completed', [
                        'tool_name' => $tool->definition()['name'],
                        'agent' => $this->getName(),
                        'result_length' => strlen($result),
                        'result_preview' => substr($result, 0, 200),
                    ]);

                    // Call the afterToolResult hook - this triggers the tracer to end the span
                    $processedResult = $this->afterToolResult($tool->definition()['name'], $result, $context);

                    // Dispatch completed event
                    Event::dispatch(new ToolCallCompleted($context, $this->getName(), $tool->definition()['name'], $processedResult));

                    // Add tool execution result to conversation history
                    $context->addMessage([
                        'role' => 'tool',
                        'tool_name' => $tool->definition()['name'],
                        'content' => $processedResult ?: '',
                    ]);

                    return $processedResult;
                } catch (Throwable $e) {
                    $this->onToolException($tool->definition()['name'], $e, $context);

                    Event::dispatch(new ToolCallFailed($context, $this->getName(), $tool->definition()['name'], $e));

                    throw new ToolExecutionException("Error executing tool '{$tool->definition()['name']}': " . $e->getMessage(), 0, $e);
                }
            });

            $tools[] = $prismTool;
        }

        return $tools;
    }

    public function getProviderToolsForPrism(): array
    {
        return array_map(function (string | array $tool) {
            // If it's already an array with configuration, use it directly
            if (is_array($tool)) {
                $type = $tool['type'] ?? throw new \InvalidArgumentException('Provider tool array must have a "type" key');
                $name = $tool['name'] ?? null;
                $options = $tool['options'] ?? [];

                return new ProviderTool(
                    type: $type,
                    name: $name,
                    options: $options
                );
            }

            // For string format, just pass it through
            // If a provider needs a name, the user should use the array format
            return new ProviderTool(type: $tool);
        }, $this->providerTools);
    }

    public function execute(mixed $input, AgentContext $context): mixed
    {
        // Store context for memory access
        $this->context = $context;

        // Set agent name in context for tools and memory management
        $context->setState('agent_name', $this->getName());

        // Check for prompt version in context
        if ($context->getState('prompt_version') !== null) {
            $this->setPromptVersion($context->getState('prompt_version'));
        }

        // Check for streaming in context
        if ($context->getState('streaming') !== null) {
            $this->setStreaming((bool) $context->getState('streaming'));
        }

        // Initialize memory for this agent if not already done
        if ($this->memory === null) {
            $this->memory = new AgentMemory($this);
        }
        /** @var Tracer $tracer */
        $tracer = app(Tracer::class);

        // Start the trace for this agent run
        $traceId = $tracer->startTrace($context, $this->getName());

        try {
            $context->setUserInput($input);

            // Check for Prism Image and Document objects in context from AgentExecutor
            $images = $context->getState('prism_images', []);
            $documents = $context->getState('prism_documents', []);

            // If no direct images but we have metadata, recreate them
            if (empty($images) && $context->getState('prism_images_metadata')) {
                $images = [];
                foreach ($context->getState('prism_images_metadata', []) as $metadata) {
                    if ($metadata['type'] === 'image' && isset($metadata['data']) && isset($metadata['mimeType'])) {
                        // Recreate the Image object from metadata
                        // The data is already base64 encoded from the Image object
                        $images[] = Image::fromBase64($metadata['data'], $metadata['mimeType']);
                    }
                }
            }

            // If no direct documents but we have metadata, recreate them
            if (empty($documents) && $context->getState('prism_documents_metadata')) {
                $documents = [];
                foreach ($context->getState('prism_documents_metadata', []) as $metadata) {
                    if ($metadata['type'] === 'document' && isset($metadata['data'])) {
                        // Recreate the Document object from metadata based on dataFormat
                        if ($metadata['dataFormat'] === 'base64') {
                            $documents[] = Document::fromBase64(
                                $metadata['data'],
                                $metadata['mimeType'],
                                $metadata['documentTitle'] ?? null
                            );
                        } elseif ($metadata['dataFormat'] === 'text') {
                            $documents[] = Document::fromText(
                                $metadata['data'],
                                $metadata['documentTitle'] ?? null
                            );
                        } elseif ($metadata['dataFormat'] === 'content' && $data = json_decode($metadata['data'], true)) {
                            $documents[] = Document::fromChunks(
                                $data,
                                $metadata['documentTitle'] ?? null
                            );
                        } elseif ($metadata['dataFormat'] === 'url') {
                            $documents[] = Document::fromUrl(
                                $metadata['data'],
                                $metadata['documentTitle'] ?? null
                            );
                        }
                    }
                }
            }

            // Since Prism handles tool execution internally with maxSteps,
            // we don't need the manual tool execution loop
            $messages = $this->prepareMessagesForPrism($context);

            // Add the current user input message with any attachments
            $additionalContent = [];
            if (! empty($images)) {
                $additionalContent = array_merge($additionalContent, $images);
            }
            if (! empty($documents)) {
                $additionalContent = array_merge($additionalContent, $documents);
            }

            // Create the user message for the current input
            if (! empty($input) || ! empty($additionalContent)) {
                $currentMessage = new UserMessage($input ?: '', $additionalContent);
                $messages[] = $currentMessage;

                // Also add to context for persistence (after prepareMessagesForPrism to avoid duplicates in LLM request)
                $userMessageArray = ['role' => 'user', 'content' => $input ?: ''];
                if (! empty($images)) {
                    $userMessageArray['images'] = $images;
                }
                if (! empty($documents)) {
                    $userMessageArray['documents'] = $documents;
                }
                $context->addMessage($userMessageArray);
            }

            $messages = $this->beforeLlmCall($messages, $context);

            Event::dispatch(new LlmCallInitiating($context, $this->getName(), $messages));

            try {
                // Build Prism request using the correct fluent API
                $prismRequest = $this->buildPrismRequest($context, $messages);

                // Execute the request - Prism handles all tool calls internally
                if ($this->getStreaming()) {
                    $llmResponse = $prismRequest->asStream();
                } else {
                    $llmResponse = $prismRequest->asText();
                }

            } catch (Throwable $e) {
                Event::dispatch(new LlmCallFailed($context, $this->getName(), $e, $prismRequest ?? null));

                throw new \RuntimeException('LLM API call failed: ' . $e->getMessage(), 0, $e);
            }

            Event::dispatch(new LlmResponseReceived($context, $this->getName(), $llmResponse, $prismRequest));

            // Handle streaming differently
            if ($this->getStreaming()) {
                // Wrap the stream to buffer final text, update context, end trace, and persist
                $agentName = $this->getName();
                $tracerRef = $tracer; // capture for generator scope
                $prismRequestRef = $prismRequest; // capture for afterLlmResponse hook
                $wrapped = (function () use ($llmResponse, $context, $agentName, $tracerRef, $prismRequestRef) {
                    // Accumulate stream data
                    $streamData = [
                        'text' => '',
                        'thinking' => '',
                        'toolCalls' => [],
                        'toolResults' => [],
                    ];

                    try {
                        foreach ($llmResponse as $event) {
                            // Prism yields Chunk objects with chunkType property (not type() method)
                            if (is_object($event) && property_exists($event, 'text')) {
                                // Get event type from chunkType enum property
                                $eventType = $event->chunkType->value ?? 'text';

                                // Accumulate different event types
                                match ($eventType) {
                                    'text_delta', 'text-delta', 'text' => $streamData['text'] .= $event->text ?? '',
                                    'thinking_delta', 'thinking-delta', 'thinking' => $streamData['thinking'] .= $event->text ?? '',
                                    'tool_call', 'tool-call' => isset($event->toolCall) ? $streamData['toolCalls'][] = [
                                        'name' => $event->toolCall->name ?? 'unknown',
                                        'id' => $event->toolCall->id ?? null,
                                        'arguments' => $event->toolCall->arguments() ?? [],
                                    ] : null,
                                    'tool_result', 'tool-result' => isset($event->toolResult) ? $streamData['toolResults'][] = [
                                        'result' => $event->toolResult->result ?? '',
                                        'toolName' => $event->toolResult->toolName ?? 'unknown',
                                        'toolCallId' => $event->toolResult->toolCallId ?? null,
                                    ] : null,
                                    default => null, // Skip other event types (stream_start, stream_end, etc.)
                                };
                            }

                            // Yield the original event to the consumer (controller/Livewire/UI)
                            yield $event;
                        }

                        // After stream completes, add assistant message to context
                        // Only save the text content - tool messages are already saved via tool callbacks
                        // Thinking tokens are accumulated but not persisted (could be logged if needed)
                        $context->addMessage([
                            'role' => 'assistant',
                            'content' => $streamData['text'],
                        ]);

                        // Fire response event for listeners with the text content
                        Event::dispatch(new AgentResponseGenerated($context, $agentName, $streamData['text']));

                        // Call afterLlmResponse hook for streaming responses
                        try {
                            $this->afterLlmResponse($llmResponse, $context, $prismRequestRef ?? null);
                        } catch (\Throwable $e) {
                            logger()->error('afterLlmResponse hook failed in streaming mode', [
                                'agent' => $agentName,
                                'error' => $e->getMessage(),
                            ]);
                        }

                        // End the trace with success
                        $tracerRef->endTrace(
                            output: ['response' => $streamData['text']],
                            status: 'success'
                        );

                        // Persist updated context (append assistant message)
                        app(\Vizra\VizraADK\Services\StateManager::class)->saveContext($context, $agentName);
                    } catch (\Throwable $e) {
                        // Mark trace failed and rethrow to consumer
                        $tracerRef->failTrace($e);

                        throw $e;
                    }
                })();

                return $wrapped;
            }

            $processedResponse = $this->afterLlmResponse($llmResponse, $context, $prismRequest);

            if (! ($processedResponse instanceof Response)) {
                throw new \RuntimeException('afterLlmResponse hook modified the response to an incompatible type.');
            }

            // Get the final response text - Prism has already executed any tools
            $assistantResponseContent = $processedResponse->text ?: '';

            $context->addMessage([
                'role' => 'assistant',
                'content' => $assistantResponseContent ?: '',
            ]);

            Event::dispatch(new AgentResponseGenerated($context, $this->getName(), $assistantResponseContent));

            // Store the result
            $result = $assistantResponseContent;

            // End the trace with success
            // Tool spans will still be able to end because we preserved their trace IDs
            $tracer->endTrace(
                output: ['response' => $result],
                status: 'success'
            );

            return $result;

        } catch (Throwable $e) {
            // End the trace with error
            $tracer->failTrace($e);

            throw $e;
        }
    }

    protected function prepareMessagesForPrism(AgentContext $context): array
    {
        $messages = [];

        // First, automatically include any user context state
        $allState = $context->getAllState();

        // Filter out control parameters and internal state
        $userContext = $this->filterUserContext($allState);

        // If there's any user context, add it as the first message
        if (! empty($userContext)) {
            $contextMessage = new UserMessage(
                "Context:\n" . json_encode($userContext, JSON_PRETTY_PRINT)
            );
            $messages[] = $contextMessage;
        }

        // Check for user overrides in context state
        $includeHistory = $context->getState('include_history', $this->includeConversationHistory);
        $historyDepth = $context->getState('history_depth', $this->historyLimit);
        $contextStrategy = $context->getState('context_strategy', $this->contextStrategy);

        // If context strategy is 'none' or history is disabled, return messages with context.
        if (! $includeHistory || $contextStrategy === 'none') {
            logger()->warning('prepareMessagesForPrism: History disabled, returning early', [
                'agent' => $this->getName(),
                'includeHistory' => $includeHistory,
                'contextStrategy' => $contextStrategy,
            ]);

            return $messages;
        }

        // Get conversation history based on strategy
        $conversationHistory = $this->getHistoryByStrategy($context, $contextStrategy, $historyDepth);

        // Convert conversation history to Prism Message objects
        foreach ($conversationHistory as $message) {
            // Skip system messages as Prism handles them via withSystemPrompt()
            if ($message['role'] === 'system') {
                continue;
            }

            switch ($message['role']) {
                case 'user':
                    $content = $message['content'] ?? '';
                    // Only add user messages if they have actual content
                    if (! empty(trim($content))) {
                        // Collect additional content (images and documents)
                        $additionalContent = [];

                        // Add Prism Image objects if present
                        if (isset($message['images']) && ! empty($message['images'])) {
                            foreach ($message['images'] as $image) {
                                if ($image instanceof Image) {
                                    $additionalContent[] = $image;
                                } elseif (is_array($image) && isset($image['image']) && isset($image['mimeType'])) {
                                    // Recreate Image object from array (happens when loaded from database)
                                    $additionalContent[] = Image::fromBase64($image['image'], $image['mimeType']);
                                }
                            }
                        }

                        // Add Prism Document objects if present
                        if (isset($message['documents']) && ! empty($message['documents'])) {
                            foreach ($message['documents'] as $document) {
                                if ($document instanceof Document) {
                                    $additionalContent[] = $document;
                                } elseif (is_array($document) && isset($document['document']) && isset($document['mimeType'])) {
                                    // Recreate Document object from array (happens when loaded from database)
                                    $additionalContent[] = new Document(
                                        $document['document'],
                                        $document['mimeType'],
                                        $document['dataFormat'] ?? null,
                                        $document['documentTitle'] ?? null,
                                        $document['documentContext'] ?? null
                                    );
                                }
                            }
                        }

                        // Create UserMessage with content and additional content
                        $messages[] = new UserMessage($content, $additionalContent);
                    }

                    break;

                case 'assistant':
                    // For assistant messages, content is always stored as plain text string
                    $content = $message['content'] ?? '';

                    // Only add assistant messages if they have content
                    if (! empty(trim($content))) {
                        $messages[] = new AssistantMessage($content);
                    }

                    break;

                case 'tool':
                    // Skip tool messages for now as Prism handles tools differently
                    // Tool results are handled internally by Prism's tool system
                    break;
            }
        }

        return $messages;
    }

    /**
     * Filter user context by removing framework control parameters.
     *
     * @param  array  $state  All context state
     * @return array Filtered user context
     */
    protected function filterUserContext(array $state): array
    {
        // Remove exact matches for control params
        $filtered = array_diff_key($state, array_flip(self::CONTROL_PARAMS));

        // Remove dynamic span IDs (they have patterns like tool_call_span_id_*)
        foreach ($filtered as $key => $value) {
            if (str_starts_with($key, 'tool_call_span_id_') ||
                str_starts_with($key, 'sub_agent_delegation_span_id_')) {
                unset($filtered[$key]);
            }
        }

        return $filtered;
    }

    /**
     * Get conversation history based on the selected strategy.
     *
     * @param  AgentContext  $context  The agent context
     * @param  string  $strategy  The context strategy ('recent', 'full', 'smart')
     * @param  int  $limit  Maximum number of messages for 'recent' strategy
     * @return array The filtered conversation history
     */
    protected function getHistoryByStrategy(AgentContext $context, string $strategy, int $limit): array
    {
        $history = $context->getConversationHistory()->toArray();

        switch ($strategy) {
            case 'recent':
                // Get the last N messages (excluding the current user input)
                // We slice from the end, keeping the most recent messages
                if (count($history) > $limit) {
                    return array_slice($history, -$limit);
                }

                return $history;

            case 'full':
                // Return all conversation history
                return $history;

            case 'smart':
                // Future enhancement: implement relevance-based filtering
                // For now, fallback to recent strategy
                if (count($history) > $limit) {
                    return array_slice($history, -$limit);
                }

                return $history;

            default:
                // Default to empty array for unknown strategies
                return [];
        }
    }

    public function beforeLlmCall(array $inputMessages, AgentContext $context): array
    {
        /** @var Tracer $tracer */
        $tracer = app(Tracer::class);

        // Start span for LLM call
        $spanId = $tracer->startSpan(
            type: 'llm_call',
            name: $this->getModel(),
            input: [
                'messages' => $inputMessages,
                'system_prompt' => $this->getInstructionsWithMemory($context),
            ],
            metadata: [
                'provider' => $this->getProvider(),
                'temperature' => $this->getTemperature(),
                'max_tokens' => $this->getMaxTokens(),
                'top_p' => $this->getTopP(),
            ],
            context: $context
        );

        // Store span ID in context for afterLlmResponse
        $context->setState('llm_call_span_id', $spanId);

        return $inputMessages;
    }

    /**
     * Get the memory manager for this agent
     */
    protected function memory(): AgentMemory
    {
        if ($this->memory === null) {
            $this->memory = new AgentMemory($this);
        }

        return $this->memory;
    }

    /**
     * Get the vector memory proxy for this agent.
     *
     * This method provides access to the vector memory functionality,
     * automatically injecting this agent's class into all operations.
     *
     * Example usage:
     * ```php
     * // Search for similar content - no need to pass agent class
     * $results = $this->vector()->search('query text');
     *
     * // Add a document to vector memory - no need to pass agent class
     * $this->vector()->addDocument('document content');
     * ```
     *
     * @return AgentVectorProxy The vector memory proxy bound to this agent
     */
    public function vector(): AgentVectorProxy
    {
        return new AgentVectorProxy(
            static::class,
            app(\Vizra\VizraADK\Services\VectorMemoryManager::class)
        );
    }

    /**
     * Alias for vector() method for convenient RAG (Retrieval-Augmented Generation) access.
     *
     * This is a convenience alias that makes it clear when you're using
     * vector memory for RAG purposes.
     *
     * Example usage:
     * ```php
     * // Search for relevant context - no need to pass agent class
     * $context = $this->rag()->search('user query');
     *
     * // Store knowledge for later retrieval - no need to pass agent class
     * $this->rag()->addDocument('important information');
     * ```
     *
     * @return AgentVectorProxy The vector memory proxy bound to this agent
     */
    public function rag(): AgentVectorProxy
    {
        return $this->vector();
    }

    /**
     * Get the current context
     */
    public function getContext(): ?AgentContext
    {
        return $this->context;
    }

    /**
     * Get the agent ID (defaults to agent name)
     */
    public function getId(): string
    {
        return $this->getName();
    }

    public function afterLlmResponse(Response | Generator $response, AgentContext $context, ?PendingRequest $request = null): mixed
    {
        /** @var Tracer $tracer */
        $tracer = app(Tracer::class);

        // Get the span ID from context
        $spanId = $context->getState('llm_call_span_id');

        if ($spanId && $response instanceof Response) {
            // End the LLM call span
            $tracer->endSpan(
                spanId: $spanId,
                output: [
                    'text' => $response->text,
                    'usage' => $response->usage ? [
                        'input_tokens' => $response->usage->input ?? $response->usage->inputTokens ?? 0,
                        'output_tokens' => $response->usage->output ?? $response->usage->outputTokens ?? 0,
                        'total_tokens' => ($response->usage->input ?? $response->usage->inputTokens ?? 0) + ($response->usage->output ?? $response->usage->outputTokens ?? 0),
                    ] : null,
                    'finish_reason' => $response->finishReason ?? null,
                ],
                status: 'success'
            );
        }

        // Capture OpenAI response ID for stateful conversations
        if ($this->useStatefulResponses && $response instanceof Response && $response->meta?->id) {
            // Namespace by agent to support agent switching
            $context->setState("response_id_{$this->getName()}", $response->meta->id);
        }

        return $response;
    }

    public function beforeToolCall(string $toolName, array $arguments, AgentContext $context): array
    {
        /** @var Tracer $tracer */
        $tracer = app(Tracer::class);

        // Start span for tool call
        $spanId = $tracer->startSpan(
            type: 'tool_call',
            name: $toolName,
            input: $arguments,
            metadata: [
                'agent_name' => $this->getName(),
                'tool_class' => isset($this->loadedTools[$toolName]) ? get_class($this->loadedTools[$toolName]) : 'DelegateToSubAgentTool',
            ],
            context: $context
        );

        // Store span ID in context for afterToolResult
        $context->setState("tool_call_span_id_{$toolName}", $spanId);

        // Also store in instance property as backup for Prism execution
        $this->activeToolSpans[$toolName] = $spanId;

        // Log debugging information
        logger()->info('beforeToolCall hook executed', [
            'tool_name' => $toolName,
            'span_id' => $spanId,
            'arguments' => $arguments,
            'agent' => $this->getName(),
            'context_session_id' => $context->getSessionId(),
        ]);

        return $arguments;
    }

    public function afterToolResult(string $toolName, string $result, AgentContext $context): string
    {
        /** @var Tracer $tracer */
        $tracer = app(Tracer::class);

        // Get the span ID from context
        $spanId = $context->getState("tool_call_span_id_{$toolName}");

        // If not found in context, check instance property (for Prism execution)
        if (! $spanId && isset($this->activeToolSpans[$toolName])) {
            $spanId = $this->activeToolSpans[$toolName];
            logger()->info('Retrieved span ID from instance property', [
                'tool_name' => $toolName,
                'span_id' => $spanId,
            ]);
        }

        // Log debugging information
        logger()->info('afterToolResult hook executed', [
            'tool_name' => $toolName,
            'span_id' => $spanId,
            'result_length' => strlen($result),
            'agent' => $this->getName(),
            'context_session_id' => $context->getSessionId(),
            'span_found' => $spanId !== null,
            'from_context' => $context->getState("tool_call_span_id_{$toolName}") !== null,
            'from_property' => isset($this->activeToolSpans[$toolName]),
        ]);

        if ($spanId) {
            try {
                // End the tool call span
                $tracer->endSpan(
                    spanId: $spanId,
                    output: ['result' => $result],
                    status: 'success'
                );

                logger()->info('Tool span ended successfully', [
                    'tool_name' => $toolName,
                    'span_id' => $spanId,
                ]);
            } catch (Throwable $e) {
                logger()->error('Failed to end tool span', [
                    'tool_name' => $toolName,
                    'span_id' => $spanId,
                    'error' => $e->getMessage(),
                ]);
            }

            // Clean up the span from instance property
            unset($this->activeToolSpans[$toolName]);
        } else {
            logger()->warning('Tool call span ID not found in context', [
                'tool_name' => $toolName,
                'agent' => $this->getName(),
            ]);
        }

        return $result;
    }

    public function onToolException(string $toolName, Throwable $e, AgentContext $context): void
    {
        // Get the span ID for this tool call to mark it as failed
        $spanId = $context->getState("tool_call_span_id_{$toolName}");

        if ($spanId) {
            app(Tracer::class)->failSpan($spanId, $e);
        }
    }

    /**
     * Called before delegating a task to a sub-agent.
     * Use this to modify delegation parameters, add authorization checks, or log delegation attempts.
     *
     * @param  string  $subAgentName  The name of the sub-agent receiving the task
     * @param  string  $taskInput  The task input being delegated
     * @param  string  $contextSummary  The context summary for the sub-agent
     * @param  AgentContext  $parentContext  The parent agent's context
     * @return array Returns modified delegation parameters [subAgentName, taskInput, contextSummary]
     */
    public function beforeSubAgentDelegation(string $subAgentName, string $taskInput, string $contextSummary, AgentContext $parentContext): array
    {
        /** @var Tracer $tracer */
        $tracer = app(Tracer::class);

        // Start span for sub-agent delegation
        $spanId = $tracer->startSpan(
            type: 'sub_agent_delegation',
            name: $subAgentName,
            input: [
                'task_input' => $taskInput,
                'context_summary' => $contextSummary,
            ],
            metadata: [
                'parent_agent' => $this->getName(),
                'sub_agent_name' => $subAgentName,
                'delegation_depth' => $parentContext->getState('delegation_depth', 0) + 1,
            ],
            context: $parentContext
        );

        // Store span ID in context for afterSubAgentDelegation
        $parentContext->setState("sub_agent_delegation_span_id_{$subAgentName}", $spanId);

        return [$subAgentName, $taskInput, $contextSummary];
    }

    /**
     * Called after a sub-agent completes a delegated task.
     * Use this to process results, validate responses, or perform cleanup.
     *
     * @param  string  $subAgentName  The name of the sub-agent that handled the task
     * @param  string  $taskInput  The original task input
     * @param  string  $subAgentResult  The result from the sub-agent
     * @param  AgentContext  $parentContext  The parent agent's context
     * @param  AgentContext  $subAgentContext  The sub-agent's context
     * @return string Returns the processed result
     */
    public function afterSubAgentDelegation(string $subAgentName, string $taskInput, string $subAgentResult, AgentContext $parentContext, AgentContext $subAgentContext): string
    {
        /** @var Tracer $tracer */
        $tracer = app(Tracer::class);

        // Get the span ID from context
        $spanId = $parentContext->getState("sub_agent_delegation_span_id_{$subAgentName}");

        if ($spanId) {
            // End the sub-agent delegation span
            $tracer->endSpan(
                spanId: $spanId,
                output: [
                    'result' => $subAgentResult,
                    'sub_agent_session_id' => $subAgentContext->getSessionId(),
                ],
                status: 'success'
            );
        }

        return $subAgentResult;
    }

    /**
     * Get all loaded tools for this agent.
     * Useful for testing and introspection.
     *
     * @return array<string, ToolInterface>
     */
    public function getLoadedTools(): array
    {
        return $this->loadedTools;
    }

    /**
     * Get MCP servers configured for this agent
     *
     * @return array<string>
     */
    public function getMcpServers(): array
    {
        return $this->mcpServers;
    }
}
