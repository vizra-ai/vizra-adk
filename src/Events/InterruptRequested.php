<?php

namespace Vizra\VizraADK\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Vizra\VizraADK\Models\AgentInterrupt;
use Vizra\VizraADK\System\AgentContext;

/**
 * Event dispatched when an agent requests human approval/input.
 */
class InterruptRequested
{
    use Dispatchable, SerializesModels;

    public AgentContext $context;

    public string $agentName;

    public AgentInterrupt $interrupt;

    public string $reason;

    public array $data;

    /**
     * Create a new event instance.
     */
    public function __construct(
        AgentContext $context,
        string $agentName,
        AgentInterrupt $interrupt,
        string $reason,
        array $data = []
    ) {
        $this->context = $context;
        $this->agentName = $agentName;
        $this->interrupt = $interrupt;
        $this->reason = $reason;
        $this->data = $data;
    }
}
