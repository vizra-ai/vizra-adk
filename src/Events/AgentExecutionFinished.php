<?php

namespace AaronLumsden\LaravelAgentADK\Events;

use AaronLumsden\LaravelAgentADK\System\AgentContext;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AgentExecutionFinished
{
    use Dispatchable, SerializesModels;

    public AgentContext $context;
    public string $agentName;

    /**
     * Create a new event instance.
     *
     * @param AgentContext $context
     * @param string $agentName
     */
    public function __construct(AgentContext $context, string $agentName)
    {
        $this->context = $context;
        $this->agentName = $agentName;
    }
}
