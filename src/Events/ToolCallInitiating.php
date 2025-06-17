<?php

namespace Vizra\VizraADK\Events;

use Vizra\VizraADK\System\AgentContext;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ToolCallInitiating
{
    use Dispatchable, SerializesModels;

    public AgentContext $context;
    public string $agentName;
    public string $toolName;
    public array $arguments;

    /**
     * Create a new event instance.
     *
     * @param AgentContext $context
     * @param string $agentName
     * @param string $toolName
     * @param array $arguments
     */
    public function __construct(AgentContext $context, string $agentName, string $toolName, array $arguments)
    {
        $this->context = $context;
        $this->agentName = $agentName;
        $this->toolName = $toolName;
        $this->arguments = $arguments;
    }
}
