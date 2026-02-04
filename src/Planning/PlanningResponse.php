<?php

declare(strict_types=1);

namespace Vizra\VizraADK\Planning;

use JsonSerializable;

/**
 * Response wrapper for planning agent execution.
 *
 * Provides fluent access to planning results including the final result,
 * executed plan, reflection, and execution metadata.
 */
class PlanningResponse implements JsonSerializable
{
    public function __construct(
        protected string $result,
        protected ?Plan $plan,
        protected ?Reflection $reflection,
        protected int $attempts,
        protected bool $success,
        protected mixed $input,
    ) {}

    /**
     * Get the final result.
     */
    public function result(): string
    {
        return $this->result;
    }

    /**
     * Get the executed plan.
     */
    public function plan(): ?Plan
    {
        return $this->plan;
    }

    /**
     * Get the final reflection.
     */
    public function reflection(): ?Reflection
    {
        return $this->reflection;
    }

    /**
     * Get the number of attempts made.
     */
    public function attempts(): int
    {
        return $this->attempts;
    }

    /**
     * Check if execution was successful (met threshold).
     */
    public function isSuccess(): bool
    {
        return $this->success;
    }

    /**
     * Check if execution failed (didn't meet threshold).
     */
    public function isFailed(): bool
    {
        return !$this->success;
    }

    /**
     * Get the original input.
     */
    public function input(): mixed
    {
        return $this->input;
    }

    /**
     * Get the satisfaction score (if reflection available).
     */
    public function score(): ?float
    {
        return $this->reflection?->score;
    }

    /**
     * Get the goal from the plan (if available).
     */
    public function goal(): ?string
    {
        return $this->plan?->goal;
    }

    /**
     * Get the steps from the plan (if available).
     */
    public function steps(): array
    {
        return $this->plan?->steps ?? [];
    }

    /**
     * Get step results from the plan.
     */
    public function stepResults(): array
    {
        $results = [];
        foreach ($this->steps() as $step) {
            if ($step->isCompleted()) {
                $results[$step->id] = $step->getResult();
            }
        }
        return $results;
    }

    /**
     * Get strengths from reflection (if available).
     */
    public function strengths(): array
    {
        return $this->reflection?->strengths ?? [];
    }

    /**
     * Get weaknesses from reflection (if available).
     */
    public function weaknesses(): array
    {
        return $this->reflection?->weaknesses ?? [];
    }

    /**
     * Get suggestions from reflection (if available).
     */
    public function suggestions(): array
    {
        return $this->reflection?->suggestions ?? [];
    }

    /**
     * Get execution metadata.
     */
    public function metadata(): array
    {
        return [
            'input' => $this->input,
            'success' => $this->success,
            'attempts' => $this->attempts,
            'goal' => $this->goal(),
            'score' => $this->score(),
            'step_count' => count($this->steps()),
            'completed_steps' => count($this->stepResults()),
        ];
    }

    /**
     * Convert to array.
     */
    public function toArray(): array
    {
        return [
            'result' => $this->result,
            'success' => $this->success,
            'attempts' => $this->attempts,
            'input' => $this->input,
            'plan' => $this->plan?->jsonSerialize(),
            'reflection' => $this->reflection?->jsonSerialize(),
        ];
    }

    /**
     * Convert to JSON string.
     */
    public function toJson(): string
    {
        return json_encode($this->toArray(), JSON_THROW_ON_ERROR);
    }

    /**
     * JSON serialization.
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /**
     * String representation returns the result.
     */
    public function __toString(): string
    {
        return $this->result;
    }
}
