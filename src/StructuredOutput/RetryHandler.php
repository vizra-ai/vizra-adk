<?php

namespace Vizra\VizraADK\StructuredOutput;

use Closure;
use Illuminate\Support\Facades\Log;
use Prism\Prism\Contracts\Schema;
use Prism\Prism\Structured\Response as StructuredResponse;

/**
 * Handles structured output validation and retry logic.
 */
class RetryHandler
{
    /**
     * Callback for when a retry occurs.
     *
     * @var Closure|null fn(int $attempt, array $errors): void
     */
    protected ?Closure $onRetryCallback = null;

    /**
     * Callback for when validation succeeds.
     *
     * @var Closure|null fn(array $data, int $retryCount): void
     */
    protected ?Closure $onSuccessCallback = null;

    /**
     * Callback for when all retries are exhausted.
     *
     * @var Closure|null fn(array $errors, int $totalAttempts): void
     */
    protected ?Closure $onFailureCallback = null;

    /**
     * @param  Schema  $schema  The schema to validate against.
     * @param  Closure  $responseGenerator  fn(?string $repairPrompt): StructuredResponse
     * @param  int  $maxRetries  Maximum number of retries (default 3).
     */
    public function __construct(
        protected Schema $schema,
        protected Closure $responseGenerator,
        protected int $maxRetries = 3,
    ) {}

    /**
     * Set callback for retry events.
     */
    public function onRetry(Closure $callback): static
    {
        $this->onRetryCallback = $callback;

        return $this;
    }

    /**
     * Set callback for success.
     */
    public function onSuccess(Closure $callback): static
    {
        $this->onSuccessCallback = $callback;

        return $this;
    }

    /**
     * Set callback for failure.
     */
    public function onFailure(Closure $callback): static
    {
        $this->onFailureCallback = $callback;

        return $this;
    }

    /**
     * Execute the structured output request with retry logic.
     */
    public function execute(): RetryResult
    {
        $attempts = [];
        $retryCount = 0;
        $repairPrompt = null;
        $lastResponse = null;
        $lastData = [];
        $lastErrors = [];

        for ($attempt = 0; $attempt <= $this->maxRetries; $attempt++) {
            // Generate response (with repair prompt on retry)
            $response = ($this->responseGenerator)($repairPrompt);
            $lastResponse = $response;
            $data = $response->structured;
            $lastData = $data;

            // Validate against schema
            $validationResult = Validator::validate($data, $this->schema);

            // Track this attempt
            $attempts[] = [
                'data' => $data,
                'errors' => $validationResult->getErrors(),
                'repairPrompt' => $repairPrompt,
            ];

            if ($validationResult->isValid()) {
                // Success!
                $this->logSuccess($attempt, $retryCount);

                if ($this->onSuccessCallback) {
                    ($this->onSuccessCallback)($data, $retryCount);
                }

                return RetryResult::success($data, $response, $retryCount, $attempts);
            }

            // Validation failed
            $lastErrors = $validationResult->getErrors();

            // If we have retries left, prepare for next attempt
            if ($attempt < $this->maxRetries) {
                $retryCount++;

                $this->logRetry($retryCount, $lastErrors);

                if ($this->onRetryCallback) {
                    ($this->onRetryCallback)($retryCount, $lastErrors);
                }

                // Build repair prompt for next attempt
                $repairPrompt = RepairPromptBuilder::build($lastErrors, $this->schema, $data);
            }
        }

        // All retries exhausted
        $totalAttempts = $this->maxRetries + 1;

        $this->logFailure($totalAttempts, $lastErrors);

        if ($this->onFailureCallback) {
            ($this->onFailureCallback)($lastErrors, $totalAttempts);
        }

        return RetryResult::failure(
            $lastData,
            $lastResponse,
            $retryCount,
            $attempts,
            $lastErrors
        );
    }

    /**
     * Log a successful validation.
     */
    protected function logSuccess(int $attempt, int $retryCount): void
    {
        $schemaName = $this->schema->name();

        if ($retryCount === 0) {
            Log::debug("Structured output validated on first attempt", [
                'schema' => $schemaName,
            ]);
        } else {
            Log::info("Structured output validated after {$retryCount} retries", [
                'schema' => $schemaName,
                'retries' => $retryCount,
            ]);
        }
    }

    /**
     * Log a retry attempt.
     */
    protected function logRetry(int $attempt, array $errors): void
    {
        $schemaName = $this->schema->name();
        $errorMessages = array_map(fn ($e) => (string) $e, $errors);

        Log::warning("Structured output validation failed, retrying", [
            'schema' => $schemaName,
            'attempt' => $attempt,
            'max_retries' => $this->maxRetries,
            'errors' => $errorMessages,
        ]);
    }

    /**
     * Log final failure.
     */
    protected function logFailure(int $totalAttempts, array $errors): void
    {
        $schemaName = $this->schema->name();
        $errorMessages = array_map(fn ($e) => (string) $e, $errors);

        Log::error("Structured output validation failed after all retries", [
            'schema' => $schemaName,
            'total_attempts' => $totalAttempts,
            'errors' => $errorMessages,
        ]);
    }
}
