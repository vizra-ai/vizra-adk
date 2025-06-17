<?php

namespace Vizra\VizraADK\Tests\Unit\Services;

use Vizra\VizraADK\Services\WorkflowManager;
use Vizra\VizraADK\Agents\SequentialWorkflow;
use Vizra\VizraADK\Agents\ParallelWorkflow;
use Vizra\VizraADK\Agents\ConditionalWorkflow;
use Vizra\VizraADK\Agents\LoopWorkflow;
use Vizra\VizraADK\Tests\TestCase;

class WorkflowManagerTest extends TestCase
{
    protected WorkflowManager $manager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->manager = new WorkflowManager();
    }

    public function test_creates_sequential_workflow()
    {
        $workflow = $this->manager->sequential();
        $this->assertInstanceOf(SequentialWorkflow::class, $workflow);
    }

    public function test_creates_sequential_workflow_with_agents()
    {
        $workflow = $this->manager->sequential('FirstAgent', 'SecondAgent', 'ThirdAgent');
        $this->assertInstanceOf(SequentialWorkflow::class, $workflow);
    }

    public function test_creates_parallel_workflow()
    {
        $workflow = $this->manager->parallel();
        $this->assertInstanceOf(ParallelWorkflow::class, $workflow);
    }

    public function test_creates_parallel_workflow_with_agents()
    {
        $workflow = $this->manager->parallel(['FirstAgent', 'SecondAgent']);
        $this->assertInstanceOf(ParallelWorkflow::class, $workflow);
    }

    public function test_creates_conditional_workflow()
    {
        $workflow = $this->manager->conditional();
        $this->assertInstanceOf(ConditionalWorkflow::class, $workflow);
    }

    public function test_creates_loop_workflow()
    {
        $workflow = $this->manager->loop();
        $this->assertInstanceOf(LoopWorkflow::class, $workflow);
    }

    public function test_creates_loop_workflow_with_agent()
    {
        $workflow = $this->manager->loop('TestAgent');
        $this->assertInstanceOf(LoopWorkflow::class, $workflow);
    }

    public function test_creates_while_loop()
    {
        $condition = fn($input) => $input['counter'] < 5;
        $workflow = $this->manager->while('TestAgent', $condition);

        $this->assertInstanceOf(LoopWorkflow::class, $workflow);
    }

    public function test_creates_until_loop()
    {
        $condition = fn($input) => $input['counter'] >= 5;
        $workflow = $this->manager->until('TestAgent', $condition);

        $this->assertInstanceOf(LoopWorkflow::class, $workflow);
    }

    public function test_creates_times_loop()
    {
        $workflow = $this->manager->times('TestAgent', 5);
        $this->assertInstanceOf(LoopWorkflow::class, $workflow);
    }

    public function test_creates_foreach_loop()
    {
        $collection = ['a', 'b', 'c'];
        $workflow = $this->manager->forEach('TestAgent', $collection);

        $this->assertInstanceOf(LoopWorkflow::class, $workflow);
    }

    public function test_creates_sequential_workflow_from_array()
    {
        $definition = [
            'type' => 'sequential',
            'steps' => [
                ['agent' => 'FirstAgent', 'params' => ['param1' => 'value1']],
                ['agent' => 'SecondAgent', 'params' => ['param2' => 'value2']],
                ['agent' => 'ThirdAgent']
            ]
        ];

        $workflow = $this->manager->fromArray($definition);
        $this->assertInstanceOf(SequentialWorkflow::class, $workflow);
    }

    public function test_creates_parallel_workflow_from_array()
    {
        $definition = [
            'type' => 'parallel',
            'agents' => [
                ['name' => 'FirstAgent', 'params' => ['param1' => 'value1']],
                ['name' => 'SecondAgent', 'params' => ['param2' => 'value2']],
                'ThirdAgent'
            ],
            'wait_for_all' => true
        ];

        $workflow = $this->manager->fromArray($definition);
        $this->assertInstanceOf(ParallelWorkflow::class, $workflow);
    }

    public function test_creates_parallel_workflow_from_array_with_wait_count()
    {
        $definition = [
            'type' => 'parallel',
            'agents' => ['FirstAgent', 'SecondAgent', 'ThirdAgent'],
            'wait_for_all' => false,
            'wait_for_count' => 2
        ];

        $workflow = $this->manager->fromArray($definition);
        $this->assertInstanceOf(ParallelWorkflow::class, $workflow);
    }

    public function test_creates_conditional_workflow_from_array()
    {
        $definition = [
            'type' => 'conditional',
            'conditions' => [
                [
                    'condition' => 'some_condition',
                    'agent' => 'FirstAgent',
                    'params' => ['param1' => 'value1']
                ],
                [
                    'condition' => 'another_condition',
                    'agent' => 'SecondAgent'
                ]
            ],
            'default' => [
                'agent' => 'DefaultAgent',
                'params' => ['default' => true]
            ]
        ];

        $workflow = $this->manager->fromArray($definition);
        $this->assertInstanceOf(ConditionalWorkflow::class, $workflow);
    }

    public function test_creates_while_loop_workflow_from_array()
    {
        $definition = [
            'type' => 'loop',
            'agent' => 'TestAgent',
            'loop_type' => 'while',
            'condition' => 'some_condition',
            'max_iterations' => 10
        ];

        $workflow = $this->manager->fromArray($definition);
        $this->assertInstanceOf(LoopWorkflow::class, $workflow);
    }

    public function test_creates_until_loop_workflow_from_array()
    {
        $definition = [
            'type' => 'loop',
            'agent' => 'TestAgent',
            'loop_type' => 'until',
            'condition' => 'some_condition'
        ];

        $workflow = $this->manager->fromArray($definition);
        $this->assertInstanceOf(LoopWorkflow::class, $workflow);
    }

    public function test_creates_times_loop_workflow_from_array()
    {
        $definition = [
            'type' => 'loop',
            'agent' => 'TestAgent',
            'loop_type' => 'times',
            'times' => 5
        ];

        $workflow = $this->manager->fromArray($definition);
        $this->assertInstanceOf(LoopWorkflow::class, $workflow);
    }

    public function test_creates_foreach_loop_workflow_from_array()
    {
        $definition = [
            'type' => 'loop',
            'agent' => 'TestAgent',
            'loop_type' => 'forEach',
            'collection' => ['a', 'b', 'c']
        ];

        $workflow = $this->manager->fromArray($definition);
        $this->assertInstanceOf(LoopWorkflow::class, $workflow);
    }

    public function test_throws_exception_for_unknown_workflow_type()
    {
        $definition = [
            'type' => 'unknown_type',
            'agent' => 'TestAgent'
        ];

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown workflow type: unknown_type');

        $this->manager->fromArray($definition);
    }

    public function test_sequential_workflow_defaults_to_sequential_type()
    {
        $definition = [
            'steps' => [
                ['agent' => 'FirstAgent'],
                ['agent' => 'SecondAgent']
            ]
        ];

        $workflow = $this->manager->fromArray($definition);
        $this->assertInstanceOf(SequentialWorkflow::class, $workflow);
    }

    public function test_creates_empty_workflows_from_minimal_definitions()
    {
        $definitions = [
            ['type' => 'sequential'],
            ['type' => 'parallel'],
            ['type' => 'conditional']
        ];

        foreach ($definitions as $definition) {
            $workflow = $this->manager->fromArray($definition);
            $this->assertNotNull($workflow);
        }
    }

    public function test_loop_workflow_defaults_to_while_type()
    {
        $definition = [
            'type' => 'loop',
            'agent' => 'TestAgent',
            'condition' => 'some_condition'
        ];

        $workflow = $this->manager->fromArray($definition);
        $this->assertInstanceOf(LoopWorkflow::class, $workflow);
    }

    public function test_handles_empty_agents_array_in_parallel_definition()
    {
        $definition = [
            'type' => 'parallel',
            'agents' => []
        ];

        $workflow = $this->manager->fromArray($definition);
        $this->assertInstanceOf(ParallelWorkflow::class, $workflow);
    }

    public function test_handles_empty_conditions_array_in_conditional_definition()
    {
        $definition = [
            'type' => 'conditional',
            'conditions' => []
        ];

        $workflow = $this->manager->fromArray($definition);
        $this->assertInstanceOf(ConditionalWorkflow::class, $workflow);
    }

    public function test_handles_empty_steps_array_in_sequential_definition()
    {
        $definition = [
            'type' => 'sequential',
            'steps' => []
        ];

        $workflow = $this->manager->fromArray($definition);
        $this->assertInstanceOf(SequentialWorkflow::class, $workflow);
    }
}
