<?php

namespace AaronLumsden\LaravelAiADK\Events;

use AaronLumsden\LaravelAiADK\System\AgentContext;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class LlmResponseReceived
{
    use Dispatchable, SerializesModels;

    public AgentContext $context;
    public string $agentName;
    public mixed $llmResponse;

    /**
     * Create a new event instance.
     *
     * @param AgentContext $context
     * @param string $agentName
     * @param mixed $llmResponse
     */
    public function __construct(AgentContext $context, string $agentName, mixed $llmResponse)
    {
        $this->context = $context;
        $this->agentName = $agentName;
        $this->llmResponse = $llmResponse;
    }
}
