<?php

namespace AaronLumsden\LaravelAgentADK\Agents;

use AaronLumsden\LaravelAgentADK\System\AgentContext;
use AaronLumsden\LaravelAgentADK\Contracts\ToolInterface;
use AaronLumsden\LaravelAgentADK\Events\LlmCallInitiating;
use AaronLumsden\LaravelAgentADK\Events\LlmResponseReceived;
use AaronLumsden\LaravelAgentADK\Events\ToolCallCompleted;
use AaronLumsden\LaravelAgentADK\Events\ToolCallInitiating;
use AaronLumsden\LaravelAgentADK\Events\AgentResponseGenerated;
use AaronLumsden\LaravelAgentADK\Exceptions\ToolExecutionException;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Arr;
use Prism\Prism\Prism;
use Prism\Prism\Enums\Provider;
use Prism\Prism\Tools\Tool;
use Prism\Prism\ValueObjects\Usage;
use Prism\Prism\Text\Response;
use Prism\Prism\ValueObjects\Messages\UserMessage;
use Prism\Prism\ValueObjects\Messages\AssistantMessage;
use Prism\Prism\ValueObjects\Messages\SystemMessage;
use Prism\Prism\ValueObjects\Messages\ToolResultMessage;

abstract class BaseLlmAgent extends BaseAgent
{
    protected string $instructions = '';
    protected string $model = '';
    protected ?Provider $provider = null;

    /** @var array<ToolInterface> */
    protected array $loadedTools = [];

    public function __construct()
    {
        // Initialize tools right away so definition is available
        $this->loadTools();
    }

    protected function getProvider(): Provider
    {
        if ($this->provider === null) {
            $defaultProvider = config('agent-adk.default_provider', 'openai');
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
        $model = $this->model ?: config('agent-adk.default_model', 'gpt-4o');

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
        return $this->instructions;
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

    /**
     * @return array<class-string<ToolInterface>>
     */
    protected function registerTools(): array
    {
        return [];
    }

    protected function loadTools(): void
    {
        if (!empty($this->loadedTools)) {
            return;
        }
        foreach ($this->registerTools() as $toolClass) {
            if (class_exists($toolClass) && is_subclass_of($toolClass, ToolInterface::class)) {
                $toolInstance = app($toolClass); // Resolve from container
                $this->loadedTools[$toolInstance->definition()['name']] = $toolInstance;
            }
        }
    }

    /**
     * @return array<Tool>
     */
    protected function getToolsForPrism(): array
    {
        $tools = [];
        foreach ($this->loadedTools as $tool) {
            $definition = $tool->definition();

            // Create Prism Tool using the correct API
            $tools[] = Tool::make(
                name: $definition['name'],
                description: $definition['description'],
                parameters: $definition['parameters']
            );
        }
        return $tools;
    }

    public function run(mixed $input, AgentContext $context): mixed
    {
        $context->setUserInput($input);
        $context->addMessage(['role' => 'user', 'content' => $input, 'timestamp' => now()]);

        // Maximum interaction cycles to prevent infinite loops
        $maxCycles = 10;
        $currentCycle = 0;

        while($currentCycle++ < $maxCycles) {
            $messages = $this->prepareMessagesForPrism($context);
            $messages = $this->beforeLlmCall($messages, $context);

            Event::dispatch(new LlmCallInitiating($context, $this->getName(), $messages));

            try {
                // Build Prism request using the correct fluent API
                $prismRequest = Prism::text()
                    ->using($this->getProvider(), $this->getModel());

                // Add system prompt if available
                if (!empty($this->getInstructions())) {
                    $prismRequest = $prismRequest->withSystemPrompt($this->getInstructions());
                }

                // Add messages for conversation history
                $prismRequest = $prismRequest->withMessages($messages);

                // Add tools if available
                if (!empty($this->loadedTools)) {
                    $prismRequest = $prismRequest->withTools($this->getToolsForPrism());
                }

                // Execute the request
                /** @var TextResponse $llmResponse */
                $llmResponse = $prismRequest->asText();

            } catch (\Throwable $e) {
                throw new \RuntimeException("LLM API call failed: " . $e->getMessage(), 0, $e);
            }

            Event::dispatch(new LlmResponseReceived($context, $this->getName(), $llmResponse));
            $processedResponse = $this->afterLlmResponse($llmResponse, $context);

            ray($processedResponse->toolCalls);

            if (!($processedResponse instanceof Response)) {
                throw new \RuntimeException("afterLlmResponse hook modified the response to an incompatible type.");
            }

            $toolCalls = $processedResponse->toolCalls ?? [];

            // Check if the response contains tool calls
            if (!empty($toolCalls)) {
                $context->addMessage([
                    'role' => 'assistant',
                    'content' => null,
                    'tool_calls' => $processedResponse->toolCalls,
                    'timestamp' => now()
                ]);

                foreach ($processedResponse->toolCalls as $toolCall) {
                    $toolName = $toolCall->name;
                    $toolArguments = $toolCall->arguments();

                    if (!isset($this->loadedTools[$toolName])) {
                        throw new ToolExecutionException("Agent '{$this->getName()}' attempted to call unregistered tool '{$toolName}'.");
                    }

                    $toolInstance = $this->loadedTools[$toolName];
                    $modifiedArgs = $this->beforeToolCall($toolName, $toolArguments, $context);

                    Event::dispatch(new ToolCallInitiating($context, $this->getName(), $toolName, $modifiedArgs));

                    try {
                        $toolResultString = $toolInstance->execute($modifiedArgs, $context);
                    } catch (\Throwable $e) {
                        throw new ToolExecutionException("Error executing tool '{$toolName}': " . $e->getMessage(), 0, $e);
                    }

                    $processedResultString = $this->afterToolResult($toolName, $toolResultString, $context);
                    Event::dispatch(new ToolCallCompleted($context, $this->getName(), $toolName, $processedResultString));

                    $context->addMessage([
                        'role' => 'tool',
                        'tool_call_id' => $toolCall->id,
                        'name' => $toolName,
                        'content' => $processedResultString,
                        'timestamp' => now()
                    ]);
                }
                // Continue loop to get final response from LLM
                continue;
            } else {
                // No tool calls, this is the final response
                $assistantResponseContent = $processedResponse->text;
                $context->addMessage([
                    'role' => 'assistant',
                    'content' => $assistantResponseContent,
                    'timestamp' => now()
                ]);

                Event::dispatch(new AgentResponseGenerated($context, $this->getName(), $assistantResponseContent));
                return $assistantResponseContent;
            }
        }

        // Reached max cycles
        throw new \RuntimeException("Agent '{$this->getName()}' exceeded maximum interaction cycles.");
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
                    if (isset($message['content']) && $message['content'] !== null) {
                        $messages[] = new UserMessage($message['content']);
                    }
                    break;

                case 'assistant':
                    // For assistant messages, we need to handle both regular content and tool calls
                    $content = $message['content'] ?? '';

                    // If there are tool calls but no content, we still need to create the message
                    // Prism will handle the tool calls through its own mechanisms
                    if (!empty($content) || (isset($message['tool_calls']) && !empty($message['tool_calls']))) {
                        $assistantMessage = new AssistantMessage($content);
                        // Note: Tool calls are handled separately by Prism's tool system
                        $messages[] = $assistantMessage;
                    }
                    break;

                case 'tool':
                    // For tool results, we need to create ToolResultMessage
                    if (isset($message['content']) && isset($message['tool_call_id'])) {
                        $messages[] = new ToolResultMessage(
                            $message['content'],
                            $message['tool_call_id'],
                            $message['name'] ?? ''
                        );
                    }
                    break;
            }
        }

        return $messages;
    }

    public function beforeLlmCall(array $inputMessages, AgentContext $context): array
    {
        return $inputMessages;
    }

    public function afterLlmResponse(Response $response, AgentContext $context): mixed
    {
        return $response;
    }

    public function beforeToolCall(string $toolName, array $arguments, AgentContext $context): array
    {
        return $arguments;
    }

    public function afterToolResult(string $toolName, string $result, AgentContext $context): string
    {
        return $result;
    }
}
