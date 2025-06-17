<?php

namespace Vizra\VizraAdk\Facades;

use Illuminate\Support\Facades\Facade;
use Vizra\VizraAdk\Agents\SequentialWorkflow;
use Vizra\VizraAdk\Agents\ParallelWorkflow;
use Vizra\VizraAdk\Agents\ConditionalWorkflow;
use Vizra\VizraAdk\Agents\LoopWorkflow;

/**
 * @method static SequentialWorkflow sequential(string ...$agentNames)
 * @method static ParallelWorkflow parallel(array $agents = [])
 * @method static ConditionalWorkflow conditional()
 * @method static LoopWorkflow loop(string $agentName = null)
 * @method static LoopWorkflow while(string $agentName, string|\Closure $condition, int $maxIterations = 100)
 * @method static LoopWorkflow until(string $agentName, string|\Closure $condition, int $maxIterations = 100)
 * @method static LoopWorkflow times(string $agentName, int $times)
 * @method static LoopWorkflow forEach(string $agentName, array|\Traversable $collection)
 * @method static SequentialWorkflow|ParallelWorkflow|ConditionalWorkflow|LoopWorkflow fromArray(array $definition)
 *
 * @see \Vizra\VizraAdk\Services\WorkflowManager
 */
class Workflow extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor(): string
    {
        return 'laravel-ai-adk.workflow';
    }
}