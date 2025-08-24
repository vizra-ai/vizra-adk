<?php

namespace Vizra\VizraADK\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Prism\Prism\Text\PendingRequest;
use Throwable;
use Vizra\VizraADK\System\AgentContext;

class LLmCallFailed
{
    use Dispatchable, SerializesModels;

    public AgentContext $context;

    public string $agentName;

    public Throwable $exception;

    public ?PendingRequest $request = null;

    public function __construct(AgentContext $context, string $agentName, Throwable $exception, ?PendingRequest $request = null)
    {
        $this->context = $context;
        $this->agentName = $agentName;
        $this->exception = $exception;
        $this->request = $request;
    }
}