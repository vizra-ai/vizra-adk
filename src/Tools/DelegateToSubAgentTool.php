<?php

namespace Vizra\VizraADK\Tools;

use Vizra\VizraADK\Contracts\ToolInterface;
use Vizra\VizraADK\System\AgentContext;
use Vizra\VizraADK\Agents\BaseLlmAgent;
use Vizra\VizraADK\Events\TaskDelegated;
use Illuminate\Support\Facades\Event;

/**
 * Internal tool for delegating tasks to sub-agents.
 * This tool is automatically added to agents that have sub-agents registered.
 */
class DelegateToSubAgentTool implements ToolInterface
{
    protected BaseLlmAgent $parentAgent;

    public function __construct(BaseLlmAgent $parentAgent)
    {
        $this->parentAgent = $parentAgent;
    }

    public function definition(): array
    {
        // Get available sub-agent names for the description
        $availableSubAgents = array_keys($this->parentAgent->getLoadedSubAgents());
        $subAgentsList = empty($availableSubAgents)
            ? "No sub-agents available"
            : "Available sub-agents: " . implode(', ', $availableSubAgents);

        return [
            'name' => 'delegate_to_sub_agent',
            'description' => "Delegates a specific task or question to a specialized sub-agent. Use this when a sub-task requires expertise that one of your available sub-agents possesses. {$subAgentsList}",
            'parameters' => [
                'type' => 'object',
                'properties' => [
                    'sub_agent_name' => [
                        'type' => 'string',
                        'description' => "The registered name of the sub-agent to delegate to. {$subAgentsList}",
                    ],
                    'task_input' => [
                        'type' => 'string',
                        'description' => 'The specific question, instruction, or data to pass to the sub-agent.',
                    ],
                    'context_summary' => [
                        'type' => 'string',
                        'description' => 'A brief summary of the current relevant conversation context from the parent agent that might be useful for the sub-agent.',
                    ],
                ],
                'required' => ['sub_agent_name', 'task_input'],
            ],
        ];
    }    public function execute(array $arguments, AgentContext $context): string
    {
        // Check delegation depth to prevent excessive recursion
        $currentDepth = $context->getState('delegation_depth', 0);
        $maxDepth = config('vizra-adk.max_delegation_depth', 5);

        if ($currentDepth >= $maxDepth) {
            return json_encode([
                'error' => "Maximum delegation depth ({$maxDepth}) reached. Cannot delegate further to prevent recursion.",
                'current_depth' => $currentDepth,
                'max_depth' => $maxDepth,
                'success' => false
            ]);
        }

        // Handle parameter conversion gracefully
        $subAgentName = is_array($arguments['sub_agent_name'] ?? '')
            ? (string) json_encode($arguments['sub_agent_name'])
            : (string) ($arguments['sub_agent_name'] ?? '');
        $taskInput = (string) ($arguments['task_input'] ?? '');
        $contextSummary = (string) ($arguments['context_summary'] ?? '');

        // Validate required parameters
        if (empty($subAgentName)) {
            return json_encode([
                'error' => 'sub_agent_name is required',
                'available_sub_agents' => array_keys($this->parentAgent->getLoadedSubAgents()),
                'success' => false
            ]);
        }

        if (empty($taskInput)) {
            return json_encode([
                'error' => 'task_input is required',
                'success' => false
            ]);
        }

        try {
            // Call the beforeSubAgentDelegation hook first
            [$modifiedSubAgentName, $modifiedTaskInput, $modifiedContextSummary] = $this->parentAgent->beforeSubAgentDelegation(
                $subAgentName,
                $taskInput,
                $contextSummary,
                $context
            );

            // Use the modified values from the hook
            $subAgentName = $modifiedSubAgentName;
            $taskInput = $modifiedTaskInput;
            $contextSummary = $modifiedContextSummary;

            // Get the sub-agent instance (after hook may have modified the name)
            $subAgent = $this->parentAgent->getSubAgent($subAgentName);
            if (!$subAgent) {
                return json_encode([
                    'error' => "Sub-agent '{$subAgentName}' not found",
                    'available_sub_agents' => array_keys($this->parentAgent->getLoadedSubAgents()),
                    'success' => false
                ]);
            }

            // Create a new context for the sub-agent
            $subAgentContext = new AgentContext($context->getSessionId() . '_sub_' . $subAgentName);

            // Increment delegation depth for the sub-agent context
            $subAgentContext->setState('delegation_depth', $currentDepth + 1);

            // If context summary is provided, add it as an initial system message
            if (!empty($contextSummary)) {
                $subAgentContext->addMessage([
                    'role' => 'system',
                    'content' => "Context from parent agent: {$contextSummary}"
                ]);
            }

            // Dispatch the task delegation event
            Event::dispatch(new TaskDelegated(
                $context,
                $subAgentContext,
                $this->parentAgent->getName(),
                $subAgentName,
                $taskInput,
                $contextSummary,
                $currentDepth + 1
            ));

            // Run the sub-agent with the task input
            $result = $subAgent->run($taskInput, $subAgentContext);

            // Call the afterSubAgentDelegation hook
            $processedResult = $this->parentAgent->afterSubAgentDelegation(
                $subAgentName,
                $taskInput,
                $result,
                $context,
                $subAgentContext
            );

            // Return the sub-agent's response
            return json_encode([
                'sub_agent' => $subAgentName,
                'task_input' => $taskInput,
                'result' => $processedResult,
                'success' => true
            ]);

        } catch (\Throwable $e) {
            return json_encode([
                'error' => "Sub-agent execution failed: " . $e->getMessage(),
                'sub_agent' => $subAgentName,
                'task_input' => $taskInput,
                'success' => false
            ]);
        }
    }
}
