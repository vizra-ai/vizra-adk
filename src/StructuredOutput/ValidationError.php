<?php

namespace Vizra\VizraADK\StructuredOutput;

/**
 * Represents a single validation error.
 */
class ValidationError
{
    public function __construct(
        public readonly string $field,
        public readonly string $type,
        public readonly string $message,
        public readonly mixed $expected = null,
        public readonly mixed $actual = null,
    ) {}

    public function toArray(): array
    {
        return [
            'field' => $this->field,
            'type' => $this->type,
            'message' => $this->message,
            'expected' => $this->expected,
            'actual' => $this->actual,
        ];
    }

    public function __toString(): string
    {
        return "{$this->field}: {$this->message}";
    }
}
