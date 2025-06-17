<?php

namespace Vizra\VizraAdk\Events;

use Vizra\VizraAdk\System\AgentContext;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AgentResponseGenerated
{
    use Dispatchable, SerializesModels;

    public AgentContext $context;
    public string $agentName;
    public mixed $finalResponse;

    /**
     * Create a new event instance.
     *
     * @param AgentContext $context
     * @param string $agentName
     * @param mixed $finalResponse
     */
    public function __construct(AgentContext $context, string $agentName, mixed $finalResponse)
    {
        $this->context = $context;
        $this->agentName = $agentName;
        $this->finalResponse = $finalResponse;
    }
}
