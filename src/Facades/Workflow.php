<?php

namespace AaronLumsden\LaravelAiADK\Facades;

use Illuminate\Support\Facades\Facade;
use AaronLumsden\LaravelAiADK\Agents\SequentialWorkflow;
use AaronLumsden\LaravelAiADK\Agents\ParallelWorkflow;
use AaronLumsden\LaravelAiADK\Agents\ConditionalWorkflow;
use AaronLumsden\LaravelAiADK\Agents\LoopWorkflow;

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
 * @see \AaronLumsden\LaravelAiADK\Services\WorkflowManager
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