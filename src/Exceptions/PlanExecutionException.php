<?php

declare(strict_types=1);

namespace Vizra\VizraADK\Exceptions;

use Exception;
use Throwable;
use Vizra\VizraADK\Agents\Patterns\Data\PlanStep;

/**
 * Exception thrown when a plan execution fails.
 *
 * This exception captures details about the failed step and can be used
 * to determine whether replanning is necessary.
 */
class PlanExecutionException extends Exception
{
    /**
     * The step that caused the failure.
     */
    private ?PlanStep $failedStep = null;

    /**
     * Create a new PlanExecutionException.
     *
     * @param string $message The error message
     * @param int $code The error code
     * @param Throwable|null $previous The previous exception
     */
    public function __construct(string $message = '', int $code = 0, ?Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }

    /**
     * Create an exception for a failed step.
     *
     * @param PlanStep $step The step that failed
     * @param string $reason The reason for failure
     * @param Throwable|null $previous The previous exception
     * @return static
     */
    public static function forStep(PlanStep $step, string $reason, ?Throwable $previous = null): static
    {
        $exception = new static(
            message: "Plan step {$step->id} ({$step->action}) failed: {$reason}",
            previous: $previous
        );
        $exception->failedStep = $step;

        return $exception;
    }

    /**
     * Create an exception for unsatisfied dependencies.
     *
     * @param PlanStep $step The step with unsatisfied dependencies
     * @param array<int> $missingDependencies The IDs of missing dependencies
     * @return static
     */
    public static function unsatisfiedDependencies(PlanStep $step, array $missingDependencies): static
    {
        $missing = implode(', ', $missingDependencies);
        $exception = new static(
            message: "Cannot execute step {$step->id}: dependencies not satisfied (missing: {$missing})"
        );
        $exception->failedStep = $step;

        return $exception;
    }

    /**
     * Get the step that caused the failure.
     *
     * @return PlanStep|null
     */
    public function getFailedStep(): ?PlanStep
    {
        return $this->failedStep;
    }
}
