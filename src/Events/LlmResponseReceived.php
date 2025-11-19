<?php

namespace Vizra\VizraADK\Events;

use Generator;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Prism\Prism\Structured\PendingRequest as StructuredPendingRequest;
use Prism\Prism\Text\PendingRequest as TextPendingRequest;
use Prism\Prism\Text\Response;
use Vizra\VizraADK\System\AgentContext;

class LlmResponseReceived
{
    use Dispatchable, SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(public AgentContext $context, public string $agentName, public Generator|Response|\Prism\Prism\Structured\Response $llmResponse, public TextPendingRequest|StructuredPendingRequest $request)
    {
    }
}
