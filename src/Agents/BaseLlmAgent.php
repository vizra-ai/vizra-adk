<?php

namespace AaronLumsden\LaravelAgentADK\Agents;

use AaronLumsden\LaravelAgentADK\System\AgentContext;
use AaronLumsden\LaravelAgentADK\Contracts\ToolInterface;
use AaronLumsden\LaravelAgentADK\Events\LlmCallInitiating;
use AaronLumsden\LaravelAgentADK\Events\LlmResponseReceived;
use AaronLumsden\LaravelAgentADK\Events\ToolCallCompleted;
use AaronLumsden\LaravelAgentADK\Events\ToolCallInitiating;
use AaronLumsden\LaravelAgentADK\Exceptions\ToolExecutionException;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Arr;
use Prism\Prism;
use Prism\DTOs\RequestData;
use Prism\DTOs\ToolData;
use Prism\Responses\Completions\CreateResponse as PrismCreateResponse;
use Prism\Responses\Completions\CreateResponseToolCall;


abstract class BaseLlmAgent extends BaseAgent
{
    protected string $instructions = '';
    protected string $model = '';
    protected ?Prism $prismClient = null;

    /** @var array<ToolInterface> */
    protected array $loadedTools = [];

    public function __construct()
    {
        // Initialize tools right away so definition is available
        $this->loadTools();
    }

    protected function getPrismClient(): Prism
    {
        if ($this->prismClient === null) {
            $config = config('agent-adk.prism', []);
            $apiKey = Arr::get($config, 'api_key', env('PRISM_API_KEY'));
            $clientOptions = Arr::get($config, 'client_options', []);

            if (empty($apiKey)) {
                // Depending on Prism's setup, this might throw an error or use a default.
                // Consider throwing a specific exception if API key is mandatory and missing.
                // For now, assume Prism handles this or user configures it properly.
            }

            // This assumes Prism's default entry point. Adjust if Prism requires more specific instantiation.
            // e.g., Prism::gemini()->withApiKey(), Prism::openAi()->withApiKey() etc.
            // For MVP, let's assume a generic client setup or user configures Prism container bindings.
            // A simple approach:
            $this->prismClient = new Prism($apiKey, $clientOptions);
        }
        return $this->prismClient;
    }

    public function getModel(): string
    {
        return $this->model ?: config('agent-adk.default_model', 'gemini-pro');
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
     * @return array<ToolData>
     */
    protected function getToolDefinitionsForPrism(): array
    {
        $definitions = [];
        foreach ($this->loadedTools as $tool) {
            // Assuming Prism's ToolData DTO constructor or a factory method
            // This might need adjustment based on actual Prism-PHP ToolData structure
             $definitions[] = new ToolData(
                name: $tool->definition()['name'],
                description: $tool->definition()['description'],
                parameters: $tool->definition()['parameters']
             );
        }
        return $definitions;
    }

    public function run(mixed $input, AgentContext $context): mixed
    {
        $context->setUserInput($input);
        $context->addMessage(['role' => 'user', 'content' => $input, 'timestamp' => now()]);

        // Maximum interaction cycles to prevent infinite loops (e.g., tool call loops)
        $maxCycles = 10;
        $currentCycle = 0;

        while($currentCycle++ < $maxCycles) {
            $messagesForLlm = $this->prepareMessagesForLlm($context);
            $messagesForLlm = $this->beforeLlmCall($messagesForLlm, $context);

            Event::dispatch(new LlmCallInitiating($context, $this->getName(), $messagesForLlm));

            $request = RequestData::create(
                model: $this->getModel(),
                messages: $messagesForLlm,
                tools: $this->getToolDefinitionsForPrism()
                // toolChoice: count($this->loadedTools) > 0 ? 'auto' : null, // Let Prism decide or set to null if no tools
            );

            // This part needs to align with how Prism-PHP client is used.
            // Example: $response = $this->getPrismClient()->completions()->create($request);
            // Adjust based on Prism's actual API for sending a request with tools.
            // The following is a generic representation.

            try {
                // This is a guess at Prism's API. Replace with actual Prism-PHP usage.
                // It's likely: $response = $this->getPrismClient()->chat()->create($request);
                // or $response = $this->getPrismClient()->completions()->create($request);
                // For this subtask, I will assume a generic $this->getPrismClient()->sendRequest($request)
                // and the response object has methods like hasToolCalls(), getToolCalls(), firstMessageContent()

                // SIMULATED PRISM CALL for structure - replace with actual Prism call
                // This part is critical and needs exact Prism-PHP v0.8 syntax.
                // Assuming Prism client has a method like `sendRequest` returning a compatible response object.
                // The actual Prism-PHP call would be something like:
                // $client = Prism::client('openai', config('agent-adk.prism.api_key'));
                // $response = $client->chat()->model($this->getModel())->messages($messagesForLlm)->tools($this->getToolDefinitionsForPrism())->request();
                // For now, I'll use a placeholder for the Prism client interaction.

                // Let's assume the actual Prism-PHP call looks something like this:
                $client = $this->getPrismClient(); // Returns a configured Prism instance
                /** @var PrismCreateResponse $llmResponse */
                $llmResponse = $client->chat()->create($request);

            } catch (\Throwable $e) {
                // Handle exceptions from Prism (API errors, connection issues etc.)
                // Log the error, potentially rethrow as a package-specific exception
                throw new \RuntimeException("LLM API call failed: " . $e->getMessage(), 0, $e);
            }

            Event::dispatch(new LlmResponseReceived($context, $this->getName(), $llmResponse));
            $processedResponse = $this->afterLlmResponse($llmResponse, $context); // Allow modification of raw response

            if (!($processedResponse instanceof PrismCreateResponse)) {
                 throw new \RuntimeException("afterLlmResponse hook modified the response to an incompatible type.");
            }


            if ($processedResponse->hasToolCalls()) {
                $context->addMessage([
                    'role' => 'assistant',
                    'content' => null, // Or some representation of tool call decision
                    'tool_calls' => $processedResponse->toolCalls(), // Assuming this returns CreateResponseToolCall[]
                    'timestamp' => now()
                ]);

                foreach ($processedResponse->toolCalls() as $toolCall) {
                     /** @var CreateResponseToolCall $toolCall */
                    $toolName = $toolCall->function->name;
                    $toolArguments = json_decode($toolCall->function->arguments, true) ?? [];

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
                        'tool_call_id' => $toolCall->id, // Prism provides a tool_call_id
                        'name' => $toolName,
                        'content' => $processedResultString, // Result from tool execution
                        'timestamp' => now()
                    ]);
                }
                // Loop back to LLM with tool results
                continue;
            } else {
                // No tool calls, this is a direct message response from the assistant
                $assistantResponseContent = $processedResponse->choices[0]->message->content ?? ''; // Adjust based on Prism DTO
                $context->addMessage([
                    'role' => 'assistant',
                    'content' => $assistantResponseContent,
                    'timestamp' => now()
                ]);
                // This is the final response for this turn
                    Event::dispatch(new AgentResponseGenerated($context, $this->getName(), $assistantResponseContent)); // Dispatch event HERE
                return $assistantResponseContent;
            }
        }
        // Reached max cycles
        throw new \RuntimeException("Agent '{$this->getName()}' exceeded maximum interaction cycles.");
    }

    protected function prepareMessagesForLlm(AgentContext $context): array
    {
        $messages = [];
        // Add system prompt (instructions) first, if any
        if (!empty($this->getInstructions())) {
            $messages[] = ['role' => 'system', 'content' => $this->getInstructions()];
        }

        // Add conversation history
        foreach ($context->getConversationHistory() as $message) {
            $formattedMessage = ['role' => $message['role']];
            if (isset($message['content']) && $message['content'] !== null) {
                $formattedMessage['content'] = $message['content'];
            }

            // Handle tool calls and results for Prism format
            if ($message['role'] === 'assistant' && isset($message['tool_calls']) && !empty($message['tool_calls'])) {
                 // This needs to be formatted as Prism expects for tool_calls from assistant
                 // $formattedMessage['tool_calls'] = $message['tool_calls']; // Assuming Prism DTOs are stored
            } elseif ($message['role'] === 'tool' && isset($message['tool_call_id'])) {
                // This needs to be formatted as Prism expects for tool responses
                // $formattedMessage['tool_call_id'] = $message['tool_call_id'];
                // $formattedMessage['name'] = $message['name']; // Or tool_name
            }
            // For MVP, keeping it simple. Complex tool message formatting might be needed.
             if (isset($formattedMessage['content']) || isset($formattedMessage['tool_calls'])) {
                $messages[] = $formattedMessage;
            }
        }
        return $messages;
    }

    public function beforeLlmCall(array $inputMessages, AgentContext $context): array
    {
        return $inputMessages;
    }

    public function afterLlmResponse(mixed $response, AgentContext $context): mixed
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
