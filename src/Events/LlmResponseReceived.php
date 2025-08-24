<?php

namespace Vizra\VizraADK\Events;

use Generator;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Prism\Prism\Text\PendingRequest;
use Prism\Prism\Text\Response;
use Vizra\VizraADK\System\AgentContext;

class LlmResponseReceived
{
    use Dispatchable, SerializesModels;

    public AgentContext $context;

    public string $agentName;

    public Generator|Response $llmResponse;

    public ?PendingRequest $request = null;

    /**
     * Create a new event instance.
     */
    public function __construct(AgentContext $context, string $agentName, Generator|Response $llmResponse, ?PendingRequest $request = null)
    {
        $this->context = $context;
        $this->agentName = $agentName;
        $this->llmResponse = $llmResponse;
        $this->request = $request;
    }
}
