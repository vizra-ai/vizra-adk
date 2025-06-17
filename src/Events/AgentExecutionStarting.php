<?php

namespace Vizra\VizraAdk\Events;

use Vizra\VizraAdk\System\AgentContext;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AgentExecutionStarting
{
    use Dispatchable, SerializesModels;

    public AgentContext $context;
    public string $agentName;
    public mixed $input;

    /**
     * Create a new event instance.
     *
     * @param AgentContext $context
     * @param string $agentName
     * @param mixed $input
     */
    public function __construct(AgentContext $context, string $agentName, mixed $input)
    {
        $this->context = $context;
        $this->agentName = $agentName;
        $this->input = $input;
    }
}
