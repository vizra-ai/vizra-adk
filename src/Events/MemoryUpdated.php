<?php

namespace Vizra\VizraADK\Events;

use Vizra\VizraADK\Models\AgentMemory;
use Vizra\VizraADK\Models\AgentSession;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MemoryUpdated
{
    use Dispatchable, SerializesModels;

    public AgentMemory $memory;
    public ?AgentSession $session;
    public string $updateType;

    /**
     * Create a new event instance.
     *
     * @param AgentMemory $memory
     * @param AgentSession|null $session
     * @param string $updateType Type of update: 'session_completed', 'learning_added', 'fact_added', etc.
     */
    public function __construct(AgentMemory $memory, ?AgentSession $session, string $updateType)
    {
        $this->memory = $memory;
        $this->session = $session;
        $this->updateType = $updateType;
    }
}
