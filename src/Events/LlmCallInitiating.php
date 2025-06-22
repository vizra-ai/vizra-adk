<?php

namespace Vizra\VizraADK\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Vizra\VizraADK\System\AgentContext;

class LlmCallInitiating
{
    use Dispatchable, SerializesModels;

    public AgentContext $context;

    public string $agentName;

    public array $promptMessages;

    /**
     * Create a new event instance.
     */
    public function __construct(AgentContext $context, string $agentName, array $promptMessages)
    {
        $this->context = $context;
        $this->agentName = $agentName;
        $this->promptMessages = $promptMessages;
    }
}
