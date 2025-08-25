<?php

namespace Vizra\VizraADK\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Throwable;
use Vizra\VizraADK\System\AgentContext;

class ToolCallFailed
{
    use Dispatchable, SerializesModels;

    public AgentContext $context;

    public string $agentName;

    public string $toolName;

    public Throwable $exception;

    public function __construct(AgentContext $context, string $agentName, string $toolName, Throwable $exception)
    {
        $this->context = $context;
        $this->agentName = $agentName;
        $this->toolName = $toolName;
        $this->exception = $exception;
    }
}