<?php

namespace Vizra\VizraADK\Facades;

use Illuminate\Support\Facades\Facade;
use Vizra\VizraADK\Agents\ConditionalWorkflow;
use Vizra\VizraADK\Agents\LoopWorkflow;
use Vizra\VizraADK\Agents\ParallelWorkflow;
use Vizra\VizraADK\Agents\SequentialWorkflow;

/**
 * @method static SequentialWorkflow sequential(string ...$agentClasses)
 * @method static ParallelWorkflow parallel(array $agentClasses = [])
 * @method static ConditionalWorkflow conditional()
 * @method static LoopWorkflow loop(string $agentClass = null)
 * @method static LoopWorkflow while(string $agentClass, string|\Closure $condition, int $maxIterations = 100)
 * @method static LoopWorkflow until(string $agentClass, string|\Closure $condition, int $maxIterations = 100)
 * @method static LoopWorkflow times(string $agentClass, int $times)
 * @method static LoopWorkflow forEach(string $agentClass, array|\Traversable $collection)
 * @method static SequentialWorkflow|ParallelWorkflow|ConditionalWorkflow|LoopWorkflow fromArray(array $definition)
 *
 * @see \Vizra\VizraADK\Services\WorkflowManager
 */
class Workflow extends Facade
{
    /**
     * Get the registered name of the component.
     */
    protected static function getFacadeAccessor(): string
    {
        return 'vizra-adk.workflow';
    }
}
