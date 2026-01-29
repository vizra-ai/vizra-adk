<?php

namespace Vizra\VizraADK\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Prism\Prism\Structured\PendingRequest as StructuredPendingRequest;
use Prism\Prism\Text\PendingRequest as TextPendingRequest;
use Throwable;
use Vizra\VizraADK\System\AgentContext;

class LLmCallFailed
{
    use Dispatchable, SerializesModels;

    public function __construct(public AgentContext $context, public string $agentName, public Throwable $exception, public TextPendingRequest|StructuredPendingRequest|null $request = null)
    {
    }
}
