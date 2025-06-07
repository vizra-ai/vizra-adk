<?php

namespace AaronLumsden\LaravelAiADK\Events;

use AaronLumsden\LaravelAiADK\System\AgentContext;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class LlmCallInitiating
{
    use Dispatchable, SerializesModels;

    public AgentContext $context;
    public string $agentName;
    public array $promptMessages;

    /**
     * Create a new event instance.
     *
     * @param AgentContext $context
     * @param string $agentName
     * @param array $promptMessages
     */
    public function __construct(AgentContext $context, string $agentName, array $promptMessages)
    {
        $this->context = $context;
        $this->agentName = $agentName;
        $this->promptMessages = $promptMessages;
    }
}
