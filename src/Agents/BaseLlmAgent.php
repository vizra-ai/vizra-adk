<?php

namespace Vizra\VizraAdk\Agents;

use Vizra\VizraAdk\System\AgentContext;
use Vizra\VizraAdk\Contracts\ToolInterface;
use Vizra\VizraAdk\Events\LlmCallInitiating;
use Vizra\VizraAdk\Events\LlmResponseReceived;
use Vizra\VizraAdk\Events\ToolCallCompleted;
use Vizra\VizraAdk\Events\ToolCallInitiating;
use Vizra\VizraAdk\Events\AgentResponseGenerated;
use Vizra\VizraAdk\Exceptions\ToolExecutionException;
use Vizra\VizraAdk\Services\Tracer;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Arr;
use Prism\Prism\Prism;
use Prism\Prism\Enums\Provider;
use Prism\Prism\Facades\Tool; // Use the Tool facade instead of Tool class
use Prism\Prism\ValueObjects\Usage;
use Prism\Prism\Text\Response;
use Generator;
use Prism\Prism\Schema\StringSchema;
use Prism\Prism\ValueObjects\Messages\UserMessage;
use Prism\Prism\ValueObjects\Messages\AssistantMessage;
use Prism\Prism\ValueObjects\Messages\SystemMessage;
use Prism\Prism\ValueObjects\Messages\ToolResultMessage;
use Prism\Prism\ValueObjects\Messages\Support\Image;
use Prism\Prism\ValueObjects\Messages\Support\Document;

abstract class BaseLlmAgent extends BaseAgent
{
    protected string $instructions = '';
    protected string $model = '';
    protected ?Provider $provider = null;
    protected ?float $temperature = null;
    protected ?int $maxTokens = null;
    protected ?float $topP = null;
    protected bool $streaming = false;

    /** @var array<class-string<ToolInterface>> */
    protected array $tools = [];

    /** @var array<ToolInterface> */
    protected array $loadedTools = [];

    /** @var array<string, BaseLlmAgent> */
    protected array $loadedSubAgents = [];

    public function __construct()
    {
        // Initialize tools and sub-agents right away so definitions are available
        $this->loadTools();
        $this->loadSubAgents();
    }

    protected function getProvider(): Provider
    {
        if ($this->provider === null) {
            $defaultProvider = config('vizra-adk.default_provider', 'openai');
            $this->provider = match($defaultProvider) {
                'openai' => Provider::OpenAI,
                'anthropic' => Provider::Anthropic,
                'gemini', 'google' => Provider::Gemini,
                default => Provider::OpenAI,
            };
        }
        return $this->provider;
    }

    public function getModel(): string
    {
        $model = $this->model ?: config('vizra-adk.default_model', 'gpt-4o');

        // Auto-detect provider based on model name if not explicitly set
        if ($this->provider === null) {
            if (str_contains($model, 'gemini') || str_contains($model, 'flash')) {
                $this->provider = Provider::Gemini;
            } elseif (str_contains($model, 'claude')) {
                $this->provider = Provider::Anthropic;
            } elseif (str_contains($model, 'gpt') || str_contains($model, 'o1')) {
                $this->provider = Provider::OpenAI;
            }
        }

        return $model;
    }

    public function getInstructions(): string
    {
        $instructions = $this->instructions;

        // Add delegation information if sub-agents are available
        if (!empty($this->loadedSubAgents)) {
            $subAgentNames = array_keys($this->loadedSubAgents);
            $subAgentsList = implode(', ', $subAgentNames);

            $delegationInfo = "\n\nDELEGATION CAPABILITIES:\n" .
                "You have access to specialized sub-agents for handling specific tasks. " .
                "Available sub-agents: {$subAgentsList}. " .
                "Use the 'delegate_to_sub_agent' tool when a task would be better handled by one of your sub-agents. " .
                "This allows you to leverage specialized expertise and break down complex problems into manageable parts.";

            $instructions .= $delegationInfo;
        }

        return $instructions;
    }

    /**
     * Get instructions with memory context included.
     */
    public function getInstructionsWithMemory(AgentContext $context): string
    {
        $instructions = $this->getInstructions();

        // Add memory context if available
        $memoryContext = $context->getState('memory_context');
        if (!empty($memoryContext)) {
            // Handle memory_context that might be an array
            $memoryContextString = is_array($memoryContext) || is_object($memoryContext)
                ? json_encode($memoryContext, JSON_PRETTY_PRINT)
                : (string)$memoryContext;

            $memoryInfo = "\n\nMEMORY CONTEXT:\n" .
                "Based on your previous interactions, here's what you should remember:\n\n" .
                $memoryContextString . "\n\n" .
                "Use this information to provide more personalized and contextual responses. " .
                "Build upon previous conversations and maintain continuity in your interactions.";

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

    public function setProvider(Provider $provider): static
    {
        $this->provider = $provider;
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

    /**
     * Register sub-agents for this agent.
     * Return an associative array where keys are unique names and values are class names.
     *
     * @return array<string, class-string<BaseLlmAgent>>
     */
    protected function registerSubAgents(): array
    {
        return [];
    }

    public function loadTools(): void
    {
        if (!empty($this->loadedTools)) {
            return;
        }
        foreach ($this->tools as $toolClass) {
            if (class_exists($toolClass) && is_subclass_of($toolClass, ToolInterface::class)) {
                $toolInstance = app($toolClass); // Resolve from container
                $this->loadedTools[$toolInstance->definition()['name']] = $toolInstance;
            }
        }
    }

    protected function loadSubAgents(): void
    {
        if (!empty($this->loadedSubAgents)) {
            return;
        }
        foreach ($this->registerSubAgents() as $subAgentName => $subAgentClass) {
            if (class_exists($subAgentClass) && is_subclass_of($subAgentClass, BaseLlmAgent::class)) {
                $subAgentInstance = app($subAgentClass); // Resolve from container
                $this->loadedSubAgents[$subAgentName] = $subAgentInstance;
            }
        }
    }

    /**
     * Get a loaded sub-agent instance by its registered name.
     *
     * @param string $name The name of the sub-agent
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
        if (!empty($this->loadedSubAgents)) {
            $allTools[] = new \Vizra\VizraAdk\Tools\DelegateToSubAgentTool($this);
        }

        foreach ($allTools as $tool) {
            $definition = $tool->definition();

            // Create Prism Tool using the correct facade API
            $prismTool = Tool::as($definition['name'])
                ->for($definition['description']);

            // Keep track of parameter order for the callback
            $parameterOrder = [];

            // Add parameters based on the definition
            if (isset($definition['parameters']['properties']) && !empty($definition['parameters']['properties'])) {
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
                    $hasNamedKeys = !empty($args) && !array_is_list($args);

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
                    if (!empty($parameterOrder)) {
                        $definition = $tool->definition();
                        $required = $definition['parameters']['required'] ?? [];
                        foreach ($required as $requiredParam) {
                            if (!isset($arguments[$requiredParam]) || $arguments[$requiredParam] === null) {
                                throw new ToolExecutionException("Required parameter '{$requiredParam}' is missing or null");
                            }
                        }
                    }

                    // Dispatch event and call the beforeToolCall hook to enable tracing
                    Event::dispatch(new ToolCallInitiating($context, $this->getName(), $tool->definition()['name'], $arguments));

                    // Call the beforeToolCall hook - this triggers the tracer to start a span
                    $processedArgs = $this->beforeToolCall($tool->definition()['name'], $arguments, $context);

                    // Execute the tool with processed arguments
                    $result = $tool->execute($processedArgs, $context);

                    // Call the afterToolResult hook - this triggers the tracer to end the span
                    $processedResult = $this->afterToolResult($tool->definition()['name'], $result, $context);

                    // Dispatch completed event
                    Event::dispatch(new ToolCallCompleted($context, $this->getName(), $tool->definition()['name'], $processedResult));

                    // Add tool execution to conversation history
                    $context->addMessage([
                        'role' => 'tool',
                        'tool_name' => $tool->definition()['name'],
                        'content' => $processedResult ?: ''
                    ]);

                    return $processedResult;
                } catch (\Throwable $e) {
                    // Get the span ID for this tool call to mark it as failed
                    $spanId = $context->getState("tool_call_span_id_{$tool->definition()['name']}");
                    if ($spanId) {
                        app(Tracer::class)->failSpan($spanId, $e);
                    }

                    throw new ToolExecutionException("Error executing tool '{$tool->definition()['name']}': " . $e->getMessage(), 0, $e);
                }
            });

            $tools[] = $prismTool;
        }
        return $tools;
    }

    public function run(mixed $input, AgentContext $context): mixed
    {
        /** @var Tracer $tracer */
        $tracer = app(Tracer::class);

        // Start the trace for this agent run
        $traceId = $tracer->startTrace($context, $this->getName());

        try {
            $context->setUserInput($input);
            
            // Check for Prism Image and Document objects in context from AgentExecutor
            $images = $context->getState('prism_images', []);
            $documents = $context->getState('prism_documents', []);
            
            // Add user message with attachments if present
            $userMessage = ['role' => 'user', 'content' => $input ?: ''];
            if (!empty($images)) {
                $userMessage['images'] = $images;
            }
            if (!empty($documents)) {
                $userMessage['documents'] = $documents;
            }
            
            $context->addMessage($userMessage);

            // Since Prism handles tool execution internally with maxSteps,
            // we don't need the manual tool execution loop
            $messages = $this->prepareMessagesForPrism($context);
            $messages = $this->beforeLlmCall($messages, $context);

            Event::dispatch(new LlmCallInitiating($context, $this->getName(), $messages));

            try {
                // Build Prism request using the correct fluent API
                $prismRequest = Prism::text()
                    ->using($this->getProvider(), $this->getModel());

                // Add system prompt if available (now includes memory context)
                if (!empty($this->getInstructions())) {
                    $prismRequest = $prismRequest->withSystemPrompt($this->getInstructionsWithMemory($context));
                }

                // Add messages for conversation history
                $prismRequest = $prismRequest->withMessages($messages);

                // Add tools if available
                $allTools = array_merge($this->loadedTools, !empty($this->loadedSubAgents) ? [new \Vizra\VizraAdk\Tools\DelegateToSubAgentTool($this)] : []);
                if (!empty($allTools)) {
                    $prismRequest = $prismRequest->withTools($this->getToolsForPrism($context))
                        ->withMaxSteps(5); // Prism will handle tool execution internally
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

                // Execute the request - Prism handles all tool calls internally
                if ($this->getStreaming()) {
                    /** @var \Prism\Prism\Text\Stream $llmResponse */
                    $llmResponse = $prismRequest->asStream();
                } else {
                    /** @var Response $llmResponse */
                    $llmResponse = $prismRequest->asText();
                }

            } catch (\Throwable $e) {
                throw new \RuntimeException("LLM API call failed: " . $e->getMessage(), 0, $e);
            }

            Event::dispatch(new LlmResponseReceived($context, $this->getName(), $llmResponse));

            // Handle streaming differently
            if ($this->getStreaming()) {
                // For streaming, return the stream directly
                // The consumer is responsible for handling the stream
                return $llmResponse;
            }

            $processedResponse = $this->afterLlmResponse($llmResponse, $context);

            if (!($processedResponse instanceof Response)) {
                throw new \RuntimeException("afterLlmResponse hook modified the response to an incompatible type.");
            }

            // Get the final response text - Prism has already executed any tools
            $assistantResponseContent = $processedResponse->text ?: '';

            $context->addMessage([
                'role' => 'assistant',
                'content' => $assistantResponseContent ?: ''
            ]);

            Event::dispatch(new AgentResponseGenerated($context, $this->getName(), $assistantResponseContent));

            // End the trace with success
            $tracer->endTrace(
                output: ['response' => $assistantResponseContent],
                status: 'success'
            );

            return $assistantResponseContent;

        } catch (\Throwable $e) {
            // End the trace with error
            $tracer->failTrace($e);
            throw $e;
        }
    }

    protected function prepareMessagesForPrism(AgentContext $context): array
    {
        $messages = [];

        // Convert conversation history to Prism Message objects
        foreach ($context->getConversationHistory() as $message) {
            // Skip system messages as Prism handles them via withSystemPrompt()
            if ($message['role'] === 'system') {
                continue;
            }

            switch ($message['role']) {
                case 'user':
                    $content = $message['content'] ?? '';
                    // Only add user messages if they have actual content
                    if (!empty(trim($content))) {
                        // Collect additional content (images and documents)
                        $additionalContent = [];
                        
                        // Add Prism Image objects if present
                        if (isset($message['images']) && !empty($message['images'])) {
                            foreach ($message['images'] as $image) {
                                if ($image instanceof Image) {
                                    $additionalContent[] = $image;
                                }
                            }
                        }
                        
                        // Add Prism Document objects if present
                        if (isset($message['documents']) && !empty($message['documents'])) {
                            foreach ($message['documents'] as $document) {
                                if ($document instanceof Document) {
                                    $additionalContent[] = $document;
                                }
                            }
                        }
                        
                        // Create UserMessage with content and additional content
                        $messages[] = new UserMessage($content, $additionalContent);
                    }
                    break;

                case 'assistant':
                    // For assistant messages, we need to handle both regular content and tool calls
                    $content = $message['content'] ?? '';

                    // Only add assistant messages if they have content
                    if (!empty(trim($content))) {
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
                'system_prompt' => $this->getInstructionsWithMemory($context)
            ],
            metadata: [
                'provider' => $this->getProvider()->value,
                'temperature' => $this->getTemperature(),
                'max_tokens' => $this->getMaxTokens(),
                'top_p' => $this->getTopP()
            ]
        );

        // Store span ID in context for afterLlmResponse
        $context->setState('llm_call_span_id', $spanId);

        return $inputMessages;
    }

    public function afterLlmResponse(Response|Generator $response, AgentContext $context): mixed
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
                        'total_tokens' => ($response->usage->input ?? $response->usage->inputTokens ?? 0) + ($response->usage->output ?? $response->usage->outputTokens ?? 0)
                    ] : null,
                    'finish_reason' => $response->finishReason ?? null
                ],
                status: 'success'
            );
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
                'tool_class' => get_class($this->loadedTools[$toolName] ?? null)
            ]
        );

        // Store span ID in context for afterToolResult
        $context->setState("tool_call_span_id_{$toolName}", $spanId);

        // Log debugging information
        logger()->info('beforeToolCall hook executed', [
            'tool_name' => $toolName,
            'span_id' => $spanId,
            'arguments' => $arguments,
            'agent' => $this->getName()
        ]);

        return $arguments;
    }

    public function afterToolResult(string $toolName, string $result, AgentContext $context): string
    {
        /** @var Tracer $tracer */
        $tracer = app(Tracer::class);

        // Get the span ID from context
        $spanId = $context->getState("tool_call_span_id_{$toolName}");

        // Log debugging information
        logger()->info('afterToolResult hook executed', [
            'tool_name' => $toolName,
            'span_id' => $spanId,
            'result_length' => strlen($result),
            'agent' => $this->getName()
        ]);

        if ($spanId) {
            // End the tool call span
            $tracer->endSpan(
                spanId: $spanId,
                output: ['result' => $result],
                status: 'success'
            );
        } else {
            logger()->warning('Tool call span ID not found in context', [
                'tool_name' => $toolName,
                'agent' => $this->getName()
            ]);
        }

        return $result;
    }

    /**
     * Called before delegating a task to a sub-agent.
     * Use this to modify delegation parameters, add authorization checks, or log delegation attempts.
     *
     * @param string $subAgentName The name of the sub-agent receiving the task
     * @param string $taskInput The task input being delegated
     * @param string $contextSummary The context summary for the sub-agent
     * @param AgentContext $parentContext The parent agent's context
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
                'context_summary' => $contextSummary
            ],
            metadata: [
                'parent_agent' => $this->getName(),
                'sub_agent_name' => $subAgentName,
                'delegation_depth' => $parentContext->getState('delegation_depth', 0) + 1
            ]
        );

        // Store span ID in context for afterSubAgentDelegation
        $parentContext->setState("sub_agent_delegation_span_id_{$subAgentName}", $spanId);

        return [$subAgentName, $taskInput, $contextSummary];
    }

    /**
     * Called after a sub-agent completes a delegated task.
     * Use this to process results, validate responses, or perform cleanup.
     *
     * @param string $subAgentName The name of the sub-agent that handled the task
     * @param string $taskInput The original task input
     * @param string $subAgentResult The result from the sub-agent
     * @param AgentContext $parentContext The parent agent's context
     * @param AgentContext $subAgentContext The sub-agent's context
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
                    'sub_agent_session_id' => $subAgentContext->getSessionId()
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
}
