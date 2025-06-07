<?php

namespace AaronLumsden\LaravelAgentADK\Services;

use AaronLumsden\LaravelAgentADK\Agents\SequentialWorkflow;
use AaronLumsden\LaravelAgentADK\Agents\ParallelWorkflow;
use AaronLumsden\LaravelAgentADK\Agents\ConditionalWorkflow;
use AaronLumsden\LaravelAgentADK\Agents\LoopWorkflow;

/**
 * Workflow Manager Service
 * 
 * Provides factory methods for creating different types of workflow agents.
 * This class powers the Workflow facade and enables fluent workflow creation.
 */
class WorkflowManager
{
    /**
     * Create a sequential workflow
     *
     * @param string ...$agentNames Optional agent names for quick setup
     * @return SequentialWorkflow
     */
    public function sequential(string ...$agentNames): SequentialWorkflow
    {
        if (empty($agentNames)) {
            return new SequentialWorkflow();
        }

        return SequentialWorkflow::create(...$agentNames);
    }

    /**
     * Create a parallel workflow
     *
     * @param array $agents Optional agent array for quick setup
     * @return ParallelWorkflow
     */
    public function parallel(array $agents = []): ParallelWorkflow
    {
        if (empty($agents)) {
            return new ParallelWorkflow();
        }

        $workflow = new ParallelWorkflow();
        $workflow->agents($agents);
        return $workflow;
    }

    /**
     * Create a conditional workflow
     *
     * @return ConditionalWorkflow
     */
    public function conditional(): ConditionalWorkflow
    {
        return new ConditionalWorkflow();
    }

    /**
     * Create a loop workflow
     *
     * @param string|null $agentName Optional agent name for quick setup
     * @return LoopWorkflow
     */
    public function loop(?string $agentName = null): LoopWorkflow
    {
        $workflow = new LoopWorkflow();
        
        if ($agentName) {
            $workflow->agent($agentName);
        }
        
        return $workflow;
    }

    /**
     * Create a while loop workflow
     *
     * @param string $agentName
     * @param string|\Closure $condition
     * @param int $maxIterations
     * @return LoopWorkflow
     */
    public function while(string $agentName, string|\Closure $condition, int $maxIterations = 100): LoopWorkflow
    {
        return LoopWorkflow::createWhile($agentName, $condition, $maxIterations);
    }

    /**
     * Create an until loop workflow
     *
     * @param string $agentName
     * @param string|\Closure $condition
     * @param int $maxIterations
     * @return LoopWorkflow
     */
    public function until(string $agentName, string|\Closure $condition, int $maxIterations = 100): LoopWorkflow
    {
        return (new LoopWorkflow())
            ->agent($agentName)
            ->until($condition)
            ->maxIterations($maxIterations);
    }

    /**
     * Create a times loop workflow
     *
     * @param string $agentName
     * @param int $times
     * @return LoopWorkflow
     */
    public function times(string $agentName, int $times): LoopWorkflow
    {
        return LoopWorkflow::createTimes($agentName, $times);
    }

    /**
     * Create a forEach loop workflow
     *
     * @param string $agentName
     * @param array|\Traversable $collection
     * @return LoopWorkflow
     */
    public function forEach(string $agentName, array|\Traversable $collection): LoopWorkflow
    {
        return LoopWorkflow::createForEach($agentName, $collection);
    }

    /**
     * Create a workflow from a definition array
     *
     * @param array $definition
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
     *
     * @param array $definition
     * @return SequentialWorkflow
     */
    private function createSequentialFromArray(array $definition): SequentialWorkflow
    {
        $workflow = new SequentialWorkflow();
        
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
     *
     * @param array $definition
     * @return ParallelWorkflow
     */
    private function createParallelFromArray(array $definition): ParallelWorkflow
    {
        $workflow = new ParallelWorkflow();
        
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
     *
     * @param array $definition
     * @return ConditionalWorkflow
     */
    private function createConditionalFromArray(array $definition): ConditionalWorkflow
    {
        $workflow = new ConditionalWorkflow();
        
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
     *
     * @param array $definition
     * @return LoopWorkflow
     */
    private function createLoopFromArray(array $definition): LoopWorkflow
    {
        $workflow = new LoopWorkflow();
        
        $workflow->agent($definition['agent']);
        
        $loopType = $definition['loop_type'] ?? 'while';
        
        switch ($loopType) {
            case 'while':
                $workflow->while($definition['condition']);
                break;
                
            case 'until':
                $workflow->until($definition['condition']);
                break;
                
            case 'times':
                $workflow->times($definition['times']);
                break;
                
            case 'forEach':
                $workflow->forEach($definition['collection']);
                break;
        }
        
        if (isset($definition['max_iterations'])) {
            $workflow->maxIterations($definition['max_iterations']);
        }
        
        return $workflow;
    }
}