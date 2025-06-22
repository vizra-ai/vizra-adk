<?php

namespace Vizra\VizraADK\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Vizra\VizraADK\System\AgentContext;

class ToolCallCompleted
{
    use Dispatchable, SerializesModels;

    public AgentContext $context;

    public string $agentName;

    public string $toolName;

    public string $result; // JSON string result from the tool

    /**
     * Create a new event instance.
     */
    public function __construct(AgentContext $context, string $agentName, string $toolName, string $result)
    {
        $this->context = $context;
        $this->agentName = $agentName;
        $this->toolName = $toolName;
        $this->result = $result;
    }
}
