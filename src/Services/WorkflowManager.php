<?php

namespace Vizra\VizraADK\Services;

use Illuminate\Support\Traits\Macroable;
use Vizra\VizraADK\Agents\ConditionalWorkflow;
use Vizra\VizraADK\Agents\LoopWorkflow;
use Vizra\VizraADK\Agents\ParallelWorkflow;
use Vizra\VizraADK\Agents\SequentialWorkflow;

/**
 * Workflow Manager Service
 *
 * Provides factory methods for creating different types of workflow agents.
 * This class powers the Workflow facade and enables fluent workflow creation.
 *
 * This class supports Laravel macros, allowing you to extend workflow functionality
 * with custom workflow types or configuration methods.
 *
 * Example usage:
 * ```php
 * use Vizra\VizraADK\Facades\Workflow;
 *
 * // Register a custom workflow type macro
 * Workflow::macro('retryable', function (string $agentClass, int $maxRetries = 3) {
 *     return Workflow::loop($agentClass)
 *         ->until(fn($result) => $result->success || $maxRetries-- <= 0);
 * });
 * ```
 */
class WorkflowManager
{
    use Macroable;
    /**
     * Create a sequential workflow
     *
     * @param  string  ...$agentClasses  Optional agent class names for quick setup
     */
    public function sequential(string ...$agentClasses): SequentialWorkflow
    {
        if (empty($agentClasses)) {
            return new SequentialWorkflow;
        }

        return SequentialWorkflow::create(...$agentClasses);
    }

    /**
     * Create a parallel workflow
     *
     * @param  array  $agents  Optional agent array for quick setup
     */
    public function parallel(array $agents = []): ParallelWorkflow
    {
        if (empty($agents)) {
            return new ParallelWorkflow;
        }

        $workflow = new ParallelWorkflow;
        $workflow->agents($agents);

        return $workflow;
    }

    /**
     * Create a conditional workflow
     */
    public function conditional(): ConditionalWorkflow
    {
        return new ConditionalWorkflow;
    }

    /**
     * Create a loop workflow
     *
     * @param  string|null  $agentClass  Optional agent class name for quick setup
     */
    public function loop(?string $agentClass = null): LoopWorkflow
    {
        $workflow = new LoopWorkflow;

        if ($agentClass) {
            $workflow->agent($agentClass);
        }

        return $workflow;
    }

    /**
     * Create a while loop workflow
     */
    public function while(string $agentClass, string|\Closure $condition, int $maxIterations = 100): LoopWorkflow
    {
        return LoopWorkflow::createWhile($agentClass, $condition, $maxIterations);
    }

    /**
     * Create an until loop workflow
     */
    public function until(string $agentClass, string|\Closure $condition, int $maxIterations = 100): LoopWorkflow
    {
        return (new LoopWorkflow)
            ->agent($agentClass)
            ->until($condition)
            ->maxIterations($maxIterations);
    }

    /**
     * Create a times loop workflow
     */
    public function times(string $agentClass, int $times): LoopWorkflow
    {
        return LoopWorkflow::createTimes($agentClass, $times);
    }

    /**
     * Create a forEach loop workflow
     */
    public function forEach(string $agentClass, array|\Traversable $collection): LoopWorkflow
    {
        return LoopWorkflow::createForEach($agentClass, $collection);
    }

    /**
     * Create a workflow from a definition array
     *
     * @return SequentialWorkflow|ParallelWorkflow|ConditionalWorkflow|LoopWorkflow
     */
    public function fromArray(array $definition)
    {
        $type = $definition['type'] ?? 'sequential';

        switch ($type) {
            case 'sequential':
                return $this->createSequentialFromArray($definition);

            case 'parallel':
                return $this->createParallelFromArray($definition);

            case 'conditional':
                return $this->createConditionalFromArray($definition);

            case 'loop':
                return $this->createLoopFromArray($definition);

            default:
                throw new \InvalidArgumentException("Unknown workflow type: {$type}");
        }
    }

    /**
     * Create sequential workflow from array definition
     */
    private function createSequentialFromArray(array $definition): SequentialWorkflow
    {
        $workflow = new SequentialWorkflow;

        foreach ($definition['steps'] ?? [] as $step) {
            $workflow->then(
                $step['agent'],
                $step['params'] ?? null,
                $step['options'] ?? []
            );
        }

        return $workflow;
    }

    /**
     * Create parallel workflow from array definition
     */
    private function createParallelFromArray(array $definition): ParallelWorkflow
    {
        $workflow = new ParallelWorkflow;

        $agents = [];
        foreach ($definition['agents'] ?? [] as $agent) {
            if (is_string($agent)) {
                $agents[] = $agent;
            } else {
                $agents[$agent['name']] = $agent['params'] ?? null;
            }
        }

        $workflow->agents($agents);

        if ($definition['wait_for_all'] ?? true) {
            $workflow->waitForAll();
        } elseif (isset($definition['wait_for_count'])) {
            $workflow->waitFor($definition['wait_for_count']);
        }

        return $workflow;
    }

    /**
     * Create conditional workflow from array definition
     */
    private function createConditionalFromArray(array $definition): ConditionalWorkflow
    {
        $workflow = new ConditionalWorkflow;

        foreach ($definition['conditions'] ?? [] as $condition) {
            $workflow->when(
                $condition['condition'],
                $condition['agent'],
                $condition['params'] ?? null,
                $condition['options'] ?? []
            );
        }

        if (isset($definition['default'])) {
            $workflow->otherwise(
                $definition['default']['agent'],
                $definition['default']['params'] ?? null,
                $definition['default']['options'] ?? []
            );
        }

        return $workflow;
    }

    /**
     * Create loop workflow from array definition
     */
    private function createLoopFromArray(array $definition): LoopWorkflow
    {
        $workflow = new LoopWorkflow;

        $workflow->agent($definition['agent']);

        $loopType = $definition['loop_type'] ?? 'while';

        switch ($loopType) {
            case 'while':
                if (isset($definition['condition'])) {
                    $workflow->while($definition['condition']);
                }
                break;

            case 'until':
                if (isset($definition['condition'])) {
                    $workflow->until($definition['condition']);
                }
                break;

            case 'times':
                if (isset($definition['times'])) {
                    $workflow->times($definition['times']);
                }
                break;

            case 'forEach':
                if (isset($definition['collection'])) {
                    $workflow->forEach($definition['collection']);
                }
                break;
        }

        if (isset($definition['max_iterations'])) {
            $workflow->maxIterations($definition['max_iterations']);
        }

        return $workflow;
    }
}
