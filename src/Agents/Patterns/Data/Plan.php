<?php

declare(strict_types=1);

namespace Vizra\VizraADK\Agents\Patterns\Data;

use JsonException;
use JsonSerializable;

/**
 * Represents an execution plan with a goal, steps, and success criteria.
 *
 * A Plan is the result of the planning phase in a PlanningAgent. It contains
 * the overall goal to achieve, a sequence of steps with their dependencies,
 * and criteria for determining if the plan was successfully executed.
 */
class Plan implements JsonSerializable
{
    /**
     * Create a new Plan instance.
     *
     * @param string $goal The main objective of this plan
     * @param array<PlanStep> $steps The steps to execute
     * @param array<string> $successCriteria Criteria for determining success
     */
    public function __construct(
        public readonly string $goal,
        public array $steps = [],
        public readonly array $successCriteria = [],
    ) {}

    /**
     * Create a Plan from a JSON string.
     *
     * @param string $json JSON string representation of the plan
     * @return static
     * @throws JsonException If the JSON is invalid
     */
    public static function fromJson(string $json): static
    {
        $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

        $steps = [];
        foreach ($data['steps'] ?? [] as $stepData) {
            $steps[] = PlanStep::fromArray($stepData);
        }

        return new static(
            goal: $data['goal'] ?? '',
            steps: $steps,
            successCriteria: $data['success_criteria'] ?? [],
        );
    }

    /**
     * Convert the plan to a JSON string.
     *
     * @return string
     */
    public function toJson(): string
    {
        return json_encode($this, JSON_THROW_ON_ERROR);
    }

    /**
     * Get a step by its ID.
     *
     * @param int $id The step ID to find
     * @return PlanStep|null The step if found, null otherwise
     */
    public function getStepById(int $id): ?PlanStep
    {
        foreach ($this->steps as $step) {
            if ($step->id === $id) {
                return $step;
            }
        }

        return null;
    }

    /**
     * Check if all steps in the plan have been completed.
     *
     * @return bool True if all steps are completed
     */
    public function isCompleted(): bool
    {
        if (empty($this->steps)) {
            return true;
        }

        foreach ($this->steps as $step) {
            if (!$step->isCompleted()) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get the IDs of all completed steps.
     *
     * @return array<int>
     */
    public function getCompletedStepIds(): array
    {
        $ids = [];
        foreach ($this->steps as $step) {
            if ($step->isCompleted()) {
                $ids[] = $step->id;
            }
        }
        return $ids;
    }

    /**
     * Get the next steps that can be executed (all dependencies satisfied).
     *
     * @return array<PlanStep>
     */
    public function getExecutableSteps(): array
    {
        $completedIds = $this->getCompletedStepIds();
        $executable = [];

        foreach ($this->steps as $step) {
            if (!$step->isCompleted() && $step->areDependenciesSatisfied($completedIds)) {
                $executable[] = $step;
            }
        }

        return $executable;
    }

    /**
     * Specify data which should be serialized to JSON.
     *
     * @return array{goal: string, steps: array<PlanStep>, success_criteria: array<string>}
     */
    public function jsonSerialize(): array
    {
        return [
            'goal' => $this->goal,
            'steps' => $this->steps,
            'success_criteria' => $this->successCriteria,
        ];
    }
}
