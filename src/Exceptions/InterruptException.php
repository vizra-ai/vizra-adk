<?php

namespace Vizra\VizraADK\Exceptions;

use Exception;
use Vizra\VizraADK\Models\AgentInterrupt;

/**
 * Exception thrown when agent execution is interrupted for human approval.
 * This exception is used to pause execution and signal that human input is required.
 */
class InterruptException extends Exception
{
    /**
     * Create a new InterruptException.
     *
     * @param string $reason The reason for the interrupt
     * @param array $data Additional data related to the interrupt
     * @param AgentInterrupt|null $interrupt The interrupt model if already created
     */
    public function __construct(
        public string $reason,
        public array $data = [],
        public ?AgentInterrupt $interrupt = null,
    ) {
        parent::__construct("Execution interrupted: {$reason}");
    }

    /**
     * Get the interrupt ID if available.
     */
    public function getInterruptId(): ?string
    {
        return $this->interrupt?->id;
    }

    /**
     * Get the reason for the interrupt.
     */
    public function getReason(): string
    {
        return $this->reason;
    }

    /**
     * Get the data associated with the interrupt.
     */
    public function getData(): array
    {
        return $this->data;
    }

    /**
     * Get the interrupt model.
     */
    public function getInterrupt(): ?AgentInterrupt
    {
        return $this->interrupt;
    }

    /**
     * Convert the exception to an array for API responses.
     */
    public function toArray(): array
    {
        return [
            'interrupted' => true,
            'interrupt_id' => $this->getInterruptId(),
            'reason' => $this->reason,
            'data' => $this->data,
            'message' => $this->getMessage(),
        ];
    }
}
