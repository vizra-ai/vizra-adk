<?php

namespace Vizra\VizraADK\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Vizra\VizraADK\System\AgentContext;

class LlmResponseReceived
{
    use Dispatchable, SerializesModels;

    public AgentContext $context;

    public string $agentName;

    public mixed $llmResponse;

    /**
     * Create a new event instance.
     */
    public function __construct(AgentContext $context, string $agentName, mixed $llmResponse)
    {
        $this->context = $context;
        $this->agentName = $agentName;
        $this->llmResponse = $llmResponse;
    }
}
