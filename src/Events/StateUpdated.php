<?php

namespace Vizra\VizraSdk\Events;

use Vizra\VizraSdk\System\AgentContext;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class StateUpdated
{
    use Dispatchable, SerializesModels;

    public AgentContext $context;
    public string $key;
    public mixed $value;

    /**
     * Create a new event instance.
     *
     * @param AgentContext $context The context where state was updated (contains all state)
     * @param string $key The specific key that was updated.
     * @param mixed $value The new value for the key.
     */
    public function __construct(AgentContext $context, string $key, mixed $value)
    {
        $this->context = $context;
        $this->key = $key;
        $this->value = $value;
    }
}
