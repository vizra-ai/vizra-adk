<?php

namespace Vizra\VizraADK\Tools\Chaining;

use Throwable;

/**
 * Represents the result of executing a ToolChain.
 *
 * Contains the final value, step-by-step results, timing information,
 * and any errors that occurred during execution.
 */
class ToolChainResult
{
    /**
     * The final value after all steps have executed.
     */
    protected mixed $finalValue = null;

    /**
     * Results from each step.
     *
     * @var array<int, array{step: ToolChainStep, value: mixed, duration: float, skipped: bool}>
     */
    protected array $stepResults = [];

    /**
     * Errors that occurred during execution.
     *
     * @var array<int, array{step: ToolChainStep, error: Throwable, duration: float}>
     */
    protected array $errors = [];

    /**
     * Total execution time in seconds.
     */
    protected float $totalDuration = 0.0;

    /**
     * Whether the chain completed successfully (no errors).
     */
    protected bool $successful = true;

    public function __construct(
        protected ?string $chainName = null
    ) {}

    /**
     * Add a successful step result.
     */
    public function addStep(int $index, ToolChainStep $step, mixed $value, float $duration, bool $skipped = false): void
    {
        $this->stepResults[$index] = [
            'step' => $step,
            'value' => $value,
            'duration' => $duration,
            'skipped' => $skipped,
        ];
        $this->totalDuration += $duration;
    }

    /**
     * Add a skipped step.
     */
    public function addSkippedStep(int $index, ToolChainStep $step): void
    {
        $this->stepResults[$index] = [
            'step' => $step,
            'value' => null,
            'duration' => 0.0,
            'skipped' => true,
        ];
    }

    /**
     * Add an error that occurred during a step.
     */
    public function addError(int $index, ToolChainStep $step, Throwable $error, float $duration): void
    {
        $this->errors[$index] = [
            'step' => $step,
            'error' => $error,
            'duration' => $duration,
        ];
        $this->totalDuration += $duration;
        $this->successful = false;
    }

    /**
     * Set the final value of the chain.
     */
    public function setFinalValue(mixed $value): void
    {
        $this->finalValue = $value;
    }

    /**
     * Get the final value of the chain.
     */
    public function value(): mixed
    {
        return $this->finalValue;
    }

    /**
     * Get the final value (alias for value()).
     */
    public function getFinalValue(): mixed
    {
        return $this->finalValue;
    }

    /**
     * Check if the chain executed successfully.
     */
    public function successful(): bool
    {
        return $this->successful;
    }

    /**
     * Check if the chain failed.
     */
    public function failed(): bool
    {
        return ! $this->successful;
    }

    /**
     * Check if there are any errors.
     */
    public function hasErrors(): bool
    {
        return ! empty($this->errors);
    }

    /**
     * Get all errors.
     *
     * @return array<int, array{step: ToolChainStep, error: Throwable, duration: float}>
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * Get the first error (if any).
     */
    public function getFirstError(): ?Throwable
    {
        if (empty($this->errors)) {
            return null;
        }

        return reset($this->errors)['error'];
    }

    /**
     * Get all step results.
     *
     * @return array<int, array{step: ToolChainStep, value: mixed, duration: float, skipped: bool}>
     */
    public function getStepResults(): array
    {
        return $this->stepResults;
    }

    /**
     * Get the result of a specific step by index.
     */
    public function getStepResult(int $index): ?array
    {
        return $this->stepResults[$index] ?? null;
    }

    /**
     * Get the value from a specific step.
     */
    public function getStepValue(int $index): mixed
    {
        return $this->stepResults[$index]['value'] ?? null;
    }

    /**
     * Get the total execution duration in seconds.
     */
    public function getDuration(): float
    {
        return $this->totalDuration;
    }

    /**
     * Get the total execution duration in milliseconds.
     */
    public function getDurationMs(): float
    {
        return $this->totalDuration * 1000;
    }

    /**
     * Get the number of steps that were executed.
     */
    public function getExecutedStepCount(): int
    {
        return count(array_filter($this->stepResults, fn ($r) => ! $r['skipped']));
    }

    /**
     * Get the number of steps that were skipped.
     */
    public function getSkippedStepCount(): int
    {
        return count(array_filter($this->stepResults, fn ($r) => $r['skipped']));
    }

    /**
     * Get the chain name.
     */
    public function getChainName(): ?string
    {
        return $this->chainName;
    }

    /**
     * Convert the result to an array.
     */
    public function toArray(): array
    {
        return [
            'chain_name' => $this->chainName,
            'successful' => $this->successful,
            'final_value' => $this->finalValue,
            'duration_ms' => $this->getDurationMs(),
            'executed_steps' => $this->getExecutedStepCount(),
            'skipped_steps' => $this->getSkippedStepCount(),
            'errors' => array_map(fn ($e) => [
                'step' => $e['step']->describe(),
                'message' => $e['error']->getMessage(),
                'duration_ms' => $e['duration'] * 1000,
            ], $this->errors),
            'steps' => array_map(fn ($r) => [
                'step' => $r['step']->describe(),
                'skipped' => $r['skipped'],
                'duration_ms' => $r['duration'] * 1000,
            ], $this->stepResults),
        ];
    }

    /**
     * Convert the result to JSON.
     */
    public function toJson(int $options = 0): string
    {
        return json_encode($this->toArray(), $options);
    }

    /**
     * Throw the first error if the chain failed.
     *
     * @throws Throwable
     */
    public function throw(): static
    {
        if ($this->failed() && $error = $this->getFirstError()) {
            throw $error;
        }

        return $this;
    }

    /**
     * Get the value or throw if failed.
     *
     * @throws Throwable
     */
    public function valueOrThrow(): mixed
    {
        $this->throw();

        return $this->value();
    }
}
