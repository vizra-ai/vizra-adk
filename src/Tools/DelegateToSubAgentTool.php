<?php

namespace Vizra\VizraADK\Tools;

use Illuminate\Support\Facades\Event;
use Vizra\VizraADK\Agents\BaseLlmAgent;
use Vizra\VizraADK\Contracts\ToolInterface;
use Vizra\VizraADK\Events\TaskDelegated;
use Vizra\VizraADK\Memory\AgentMemory;
use Vizra\VizraADK\System\AgentContext;

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
            ? 'No sub-agents available'
            : 'Available sub-agents: '.implode(', ', $availableSubAgents);

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
    }

    public function execute(array $arguments, AgentContext $context, AgentMemory $memory): string
    {

        // Check delegation depth to prevent excessive recursion
        $currentDepth = $context->getState('delegation_depth', 0);
        $maxDepth = config('vizra-adk.max_delegation_depth', 5);

        if ($currentDepth >= $maxDepth) {
            return json_encode([
                'error' => "Maximum delegation depth ({$maxDepth}) reached. Cannot delegate further to prevent recursion.",
                'current_depth' => $currentDepth,
                'max_depth' => $maxDepth,
                'success' => false,
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
                'success' => false,
            ]);
        }

        if (empty($taskInput)) {
            return json_encode([
                'error' => 'task_input is required',
                'success' => false,
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
            if (! $subAgent) {
                return json_encode([
                    'error' => "Sub-agent '{$subAgentName}' not found",
                    'available_sub_agents' => array_keys($this->parentAgent->getLoadedSubAgents()),
                    'success' => false,
                ]);
            }

            // Create a new context for the sub-agent
            $subAgentContext = new AgentContext($context->getSessionId().'_sub_'.$subAgentName);

            // Increment delegation depth for the sub-agent context
            $subAgentContext->setState('delegation_depth', $currentDepth + 1);

            // If context summary is provided, add it as an initial system message
            if (! empty($contextSummary)) {
                $subAgentContext->addMessage([
                    'role' => 'system',
                    'content' => "Context from parent agent: {$contextSummary}",
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

            // Store parent trace context before sub-agent execution
            $tracer = app(\Vizra\VizraADK\Services\Tracer::class);
            $parentTraceId = $tracer->getCurrentTraceId();
            
            logger()->info('Delegating to sub-agent', [
                'parent_trace_id' => $parentTraceId,
                'sub_agent' => $subAgentName,
            ]);
            
            // Run the sub-agent with the task input
            $result = $subAgent->execute($taskInput, $subAgentContext);
            
            // Restore parent trace context after sub-agent execution
            if ($parentContext = $subAgentContext->getState('_parent_trace_context')) {
                $tracer->restoreParentContext($parentContext);
                logger()->info('Restored parent trace context after sub-agent execution', [
                    'parent_trace_id' => $parentContext['trace_id'],
                    'sub_agent' => $subAgentName,
                ]);
            }
            
            logger()->info('Sub-agent execution completed', [
                'parent_trace_id' => $parentTraceId,
                'current_trace_id' => $tracer->getCurrentTraceId(),
                'sub_agent' => $subAgentName,
            ]);

            // Call the afterSubAgentDelegation hook
            $processedResult = $this->parentAgent->afterSubAgentDelegation(
                $subAgentName,
                $taskInput,
                $result,
                $context,
                $subAgentContext
            );

            // Return the sub-agent's response
            $response = [
                'sub_agent' => $subAgentName,
                'task_input' => $taskInput,
                'result' => $processedResult,
                'success' => true,
            ];

            return json_encode($response);

        } catch (\Throwable $e) {
            return json_encode([
                'error' => 'Sub-agent execution failed: '.$e->getMessage(),
                'sub_agent' => $subAgentName,
                'task_input' => $taskInput,
                'success' => false,
            ]);
        }
    }
}
