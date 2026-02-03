<?php

namespace Vizra\VizraADK\StructuredOutput;

use Prism\Prism\Structured\Response as StructuredResponse;

/**
 * Result of a structured output retry operation.
 */
class RetryResult
{
    /**
     * @param  array<array{data: array, errors: array<ValidationError>, repairPrompt: ?string}>  $attempts
     * @param  array<ValidationError>  $validationErrors
     */
    public function __construct(
        protected bool $valid,
        protected array $data,
        protected ?StructuredResponse $response,
        protected int $retryCount,
        protected array $attempts = [],
        protected array $validationErrors = [],
    ) {}

    public function isValid(): bool
    {
        return $this->valid;
    }

    public function getData(): array
    {
        return $this->data;
    }

    public function getResponse(): ?StructuredResponse
    {
        return $this->response;
    }

    public function getRetryCount(): int
    {
        return $this->retryCount;
    }

    /**
     * @return array<array{data: array, errors: array<ValidationError>, repairPrompt: ?string}>
     */
    public function getAttempts(): array
    {
        return $this->attempts;
    }

    /**
     * @return array<ValidationError>
     */
    public function getValidationErrors(): array
    {
        return $this->validationErrors;
    }

    public function toArray(): array
    {
        return [
            'valid' => $this->valid,
            'data' => $this->data,
            'retry_count' => $this->retryCount,
            'attempts' => count($this->attempts),
            'errors' => array_map(fn (ValidationError $e) => $e->toArray(), $this->validationErrors),
        ];
    }

    public static function success(
        array $data,
        StructuredResponse $response,
        int $retryCount,
        array $attempts
    ): static {
        return new static(
            valid: true,
            data: $data,
            response: $response,
            retryCount: $retryCount,
            attempts: $attempts,
            validationErrors: []
        );
    }

    /**
     * @param  array<ValidationError>  $errors
     */
    public static function failure(
        array $data,
        ?StructuredResponse $response,
        int $retryCount,
        array $attempts,
        array $errors
    ): static {
        return new static(
            valid: false,
            data: $data,
            response: $response,
            retryCount: $retryCount,
            attempts: $attempts,
            validationErrors: $errors
        );
    }
}
