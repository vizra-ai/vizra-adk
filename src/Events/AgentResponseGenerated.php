<?php

namespace Vizra\VizraADK\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Vizra\VizraADK\System\AgentContext;

class AgentResponseGenerated
{
    use Dispatchable, SerializesModels;

    public AgentContext $context;

    public string $agentName;

    public mixed $finalResponse;

    /**
     * Create a new event instance.
     */
    public function __construct(AgentContext $context, string $agentName, mixed $finalResponse)
    {
        $this->context = $context;
        $this->agentName = $agentName;
        $this->finalResponse = $finalResponse;
    }
}
