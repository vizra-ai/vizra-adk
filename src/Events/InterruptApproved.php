<?php

namespace Vizra\VizraADK\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Vizra\VizraADK\Models\AgentInterrupt;

/**
 * Event dispatched when a human approves an interrupt request.
 */
class InterruptApproved
{
    use Dispatchable, SerializesModels;

    public AgentInterrupt $interrupt;

    public ?array $modifications;

    public ?string $resolvedBy;

    /**
     * Create a new event instance.
     */
    public function __construct(
        AgentInterrupt $interrupt,
        ?array $modifications = null,
        ?string $resolvedBy = null
    ) {
        $this->interrupt = $interrupt;
        $this->modifications = $modifications;
        $this->resolvedBy = $resolvedBy;
    }
}
