<?php

namespace Vizra\VizraADK\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Vizra\VizraADK\Models\AgentInterrupt;

/**
 * Event dispatched when a human rejects an interrupt request.
 */
class InterruptRejected
{
    use Dispatchable, SerializesModels;

    public AgentInterrupt $interrupt;

    public ?string $rejectionReason;

    public ?string $resolvedBy;

    /**
     * Create a new event instance.
     */
    public function __construct(
        AgentInterrupt $interrupt,
        ?string $rejectionReason = null,
        ?string $resolvedBy = null
    ) {
        $this->interrupt = $interrupt;
        $this->rejectionReason = $rejectionReason;
        $this->resolvedBy = $resolvedBy;
    }
}
