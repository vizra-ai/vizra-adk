<?php

namespace Vizra\VizraADK\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Vizra\VizraADK\System\AgentContext;

class AgentExecutionFinished
{
    use Dispatchable, SerializesModels;

    public AgentContext $context;

    public string $agentName;

    /**
     * Create a new event instance.
     */
    public function __construct(AgentContext $context, string $agentName)
    {
        $this->context = $context;
        $this->agentName = $agentName;
    }
}
