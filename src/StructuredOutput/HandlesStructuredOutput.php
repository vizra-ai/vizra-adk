<?php

namespace Vizra\VizraADK\StructuredOutput;

use Prism\Prism\Contracts\Schema;
use Prism\Prism\Structured\PendingRequest as StructuredPendingRequest;
use Prism\Prism\Structured\Response as StructuredResponse;

/**
 * Trait for agents that need structured output with validation and retry.
 *
 * Add this trait to your agent and implement getSchema() to enable
 * automatic validation and retry of structured outputs.
 *
 * @example
 * ```php
 * class MyAgent extends BaseLlmAgent
 * {
 *     use HandlesStructuredOutput;
 *
 *     protected int $structuredOutputMaxRetries = 3;
 *
 *     public function getSchema(): ?Schema
 *     {
 *         return new ObjectSchema(
 *             name: 'response',
 *             description: 'The response',
 *             properties: [
 *                 new StringSchema('answer', 'The answer'),
 *                 new NumberSchema('confidence', 'Confidence score'),
 *             ],
 *             requiredFields: ['answer', 'confidence']
 *         );
 *     }
 * }
 * ```
 */
trait HandlesStructuredOutput
{
    /**
     * Maximum number of retries for structured output validation.
     * Override in your agent to customize.
     */
    protected int $structuredOutputMaxRetries = 3;

    /**
     * Execute a structured request with validation and retry.
     *
     * @param  StructuredPendingRequest  $request  The Prism structured request.
     * @param  Schema  $schema  The schema to validate against.
     * @param  callable|null  $onRetry  Optional callback on retry: fn(int $attempt, array $errors)
     * @return RetryResult The result with validation status and data.
     */
    protected function executeStructuredWithRetry(
        StructuredPendingRequest $request,
        Schema $schema,
        ?callable $onRetry = null
    ): RetryResult {
        $handler = new RetryHandler(
            schema: $schema,
            responseGenerator: function (?string $repairPrompt = null) use ($request): StructuredResponse {
                if ($repairPrompt !== null) {
                    // Add repair instructions to the request
                    $request = $request->withMessages([
                        ...$request->messages ?? [],
                        ['role' => 'user', 'content' => $repairPrompt],
                    ]);
                }

                return $request->asStructured();
            },
            maxRetries: $this->structuredOutputMaxRetries
        );

        if ($onRetry) {
            $handler->onRetry($onRetry);
        }

        // Log success with agent context
        $handler->onSuccess(function (array $data, int $retryCount) {
            if (method_exists($this, 'getName')) {
                $agentName = $this->getName();
                if ($retryCount > 0) {
                    \Illuminate\Support\Facades\Log::info(
                        "Agent {$agentName}: Structured output validated after {$retryCount} retries"
                    );
                }
            }
        });

        // Log failure with agent context
        $handler->onFailure(function (array $errors, int $totalAttempts) {
            if (method_exists($this, 'getName')) {
                $agentName = $this->getName();
                \Illuminate\Support\Facades\Log::error(
                    "Agent {$agentName}: Structured output validation failed after {$totalAttempts} attempts",
                    ['errors' => array_map(fn ($e) => (string) $e, $errors)]
                );
            }
        });

        return $handler->execute();
    }

    /**
     * Validate structured data against a schema without retry.
     *
     * @param  array  $data  The data to validate.
     * @param  Schema  $schema  The schema to validate against.
     * @return ValidationResult The validation result.
     */
    protected function validateStructuredOutput(array $data, Schema $schema): ValidationResult
    {
        return Validator::validate($data, $schema);
    }

    /**
     * Get the max retries for structured output.
     */
    public function getStructuredOutputMaxRetries(): int
    {
        return $this->structuredOutputMaxRetries;
    }

    /**
     * Set the max retries for structured output.
     */
    public function setStructuredOutputMaxRetries(int $maxRetries): static
    {
        $this->structuredOutputMaxRetries = $maxRetries;

        return $this;
    }
}
