<?php

namespace Vizra\VizraADK\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Vizra\VizraADK\System\AgentContext;

class ToolCallInitiating
{
    use Dispatchable, SerializesModels;

    public AgentContext $context;

    public string $agentName;

    public string $toolName;

    public array $arguments;

    /**
     * Create a new event instance.
     */
    public function __construct(AgentContext $context, string $agentName, string $toolName, array $arguments)
    {
        $this->context = $context;
        $this->agentName = $agentName;
        $this->toolName = $toolName;
        $this->arguments = $arguments;
    }
}
