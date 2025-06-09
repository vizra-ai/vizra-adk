<?php

namespace Vizra\VizraSdk\Events;

use Vizra\VizraSdk\System\AgentContext;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TaskDelegated
{
    use Dispatchable, SerializesModels;

    public AgentContext $parentContext;
    public AgentContext $subAgentContext;
    public string $parentAgentName;
    public string $subAgentName;
    public string $taskInput;
    public string $contextSummary;
    public int $delegationDepth;

    /**
     * Create a new event instance.
     *
     * @param AgentContext $parentContext The context of the parent agent
     * @param AgentContext $subAgentContext The context created for the sub-agent
     * @param string $parentAgentName The name of the parent agent delegating the task
     * @param string $subAgentName The name of the sub-agent receiving the task
     * @param string $taskInput The task input being delegated
     * @param string $contextSummary The context summary provided to the sub-agent
     * @param int $delegationDepth The current delegation depth
     */
    public function __construct(
        AgentContext $parentContext,
        AgentContext $subAgentContext,
        string $parentAgentName,
        string $subAgentName,
        string $taskInput,
        string $contextSummary,
        int $delegationDepth
    ) {
        $this->parentContext = $parentContext;
        $this->subAgentContext = $subAgentContext;
        $this->parentAgentName = $parentAgentName;
        $this->subAgentName = $subAgentName;
        $this->taskInput = $taskInput;
        $this->contextSummary = $contextSummary;
        $this->delegationDepth = $delegationDepth;
    }
}
