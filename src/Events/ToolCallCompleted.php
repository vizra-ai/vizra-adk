<?php

namespace AaronLumsden\LaravelAiADK\Events;

use AaronLumsden\LaravelAiADK\System\AgentContext;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ToolCallCompleted
{
    use Dispatchable, SerializesModels;

    public AgentContext $context;
    public string $agentName;
    public string $toolName;
    public string $result; // JSON string result from the tool

    /**
     * Create a new event instance.
     *
     * @param AgentContext $context
     * @param string $agentName
     * @param string $toolName
     * @param string $result
     */
    public function __construct(AgentContext $context, string $agentName, string $toolName, string $result)
    {
        $this->context = $context;
        $this->agentName = $agentName;
        $this->toolName = $toolName;
        $this->result = $result;
    }
}
