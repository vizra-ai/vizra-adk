<?php

declare(strict_types=1);

namespace Vizra\VizraADK\Agents\Patterns\Data;

use JsonSerializable;

/**
 * Represents a single step in an execution plan.
 *
 * A PlanStep contains the action to be performed, its dependencies on other steps,
 * and the tools required for execution. Steps can track their completion status
 * and store their execution results.
 */
class PlanStep implements JsonSerializable
{
    /**
     * Whether this step has been completed.
     */
    private bool $completed = false;

    /**
     * The result of executing this step.
     */
    private ?string $result = null;

    /**
     * Create a new PlanStep instance.
     *
     * @param int $id Unique identifier for this step within the plan
     * @param string $action Description of the action to perform
     * @param array<int> $dependencies IDs of steps that must complete before this one
     * @param array<string> $tools Names of tools required for this step
     */
    public function __construct(
        public readonly int $id,
        public readonly string $action,
        public readonly array $dependencies = [],
        public readonly array $tools = [],
    ) {}

    /**
     * Create a PlanStep from an array representation.
     *
     * @param array{id: int, action: string, dependencies?: array<int>, tools?: array<string>} $data
     * @return static
     */
    public static function fromArray(array $data): static
    {
        return new static(
            id: $data['id'],
            action: $data['action'],
            dependencies: $data['dependencies'] ?? [],
            tools: $data['tools'] ?? [],
        );
    }

    /**
     * Convert the step to an array representation.
     *
     * @return array{id: int, action: string, dependencies: array<int>, tools: array<string>, completed: bool, result: ?string}
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'action' => $this->action,
            'dependencies' => $this->dependencies,
            'tools' => $this->tools,
            'completed' => $this->completed,
            'result' => $this->result,
        ];
    }

    /**
     * Check if this step has been completed.
     */
    public function isCompleted(): bool
    {
        return $this->completed;
    }

    /**
     * Mark this step as completed or not completed.
     */
    public function setCompleted(bool $completed): void
    {
        $this->completed = $completed;
    }

    /**
     * Get the result of executing this step.
     */
    public function getResult(): ?string
    {
        return $this->result;
    }

    /**
     * Store the result of executing this step.
     */
    public function setResult(?string $result): void
    {
        $this->result = $result;
    }

    /**
     * Check if this step has dependencies.
     */
    public function hasDependencies(): bool
    {
        return !empty($this->dependencies);
    }

    /**
     * Check if all dependencies are satisfied given a list of completed step IDs.
     *
     * @param array<int> $completedStepIds List of IDs for completed steps
     * @return bool True if all dependencies are satisfied
     */
    public function areDependenciesSatisfied(array $completedStepIds): bool
    {
        if (empty($this->dependencies)) {
            return true;
        }

        foreach ($this->dependencies as $dependencyId) {
            if (!in_array($dependencyId, $completedStepIds, true)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Specify data which should be serialized to JSON.
     *
     * @return array{id: int, action: string, dependencies: array<int>, tools: array<string>, completed: bool, result: ?string}
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
