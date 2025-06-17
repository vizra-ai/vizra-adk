<?php

namespace Vizra\VizraADK\Facades;

use Illuminate\Support\Facades\Facade;
use Vizra\VizraADK\Agents\SequentialWorkflow;
use Vizra\VizraADK\Agents\ParallelWorkflow;
use Vizra\VizraADK\Agents\ConditionalWorkflow;
use Vizra\VizraADK\Agents\LoopWorkflow;

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
 * @see \Vizra\VizraADK\Services\WorkflowManager
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
