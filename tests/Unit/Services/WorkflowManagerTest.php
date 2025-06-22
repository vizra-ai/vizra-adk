<?php

namespace Vizra\VizraADK\Tests\Unit\Services;

use Vizra\VizraADK\Agents\BaseLlmAgent;
use Vizra\VizraADK\Agents\ConditionalWorkflow;
use Vizra\VizraADK\Agents\LoopWorkflow;
use Vizra\VizraADK\Agents\ParallelWorkflow;
use Vizra\VizraADK\Agents\SequentialWorkflow;
use Vizra\VizraADK\Services\WorkflowManager;
use Vizra\VizraADK\Tests\TestCase;

// Mock agent classes for testing
class FirstAgent extends BaseLlmAgent
{
    protected string $name = 'first_agent';
}

class SecondAgent extends BaseLlmAgent
{
    protected string $name = 'second_agent';
}

class ThirdAgent extends BaseLlmAgent
{
    protected string $name = 'third_agent';
}

class TestAgent extends BaseLlmAgent
{
    protected string $name = 'test_agent';
}

class DefaultAgent extends BaseLlmAgent
{
    protected string $name = 'default_agent';
}

class WorkflowManagerTest extends TestCase
{
    protected WorkflowManager $manager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->manager = new WorkflowManager;
    }

    public function test_creates_sequential_workflow()
    {
        $workflow = $this->manager->sequential();
        $this->assertInstanceOf(SequentialWorkflow::class, $workflow);
    }

    public function test_creates_sequential_workflow_with_agents()
    {
        $workflow = $this->manager->sequential(FirstAgent::class, SecondAgent::class, ThirdAgent::class);
        $this->assertInstanceOf(SequentialWorkflow::class, $workflow);
    }

    public function test_creates_parallel_workflow()
    {
        $workflow = $this->manager->parallel();
        $this->assertInstanceOf(ParallelWorkflow::class, $workflow);
    }

    public function test_creates_parallel_workflow_with_agents()
    {
        $workflow = $this->manager->parallel([FirstAgent::class, SecondAgent::class]);
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
        $workflow = $this->manager->loop(TestAgent::class);
        $this->assertInstanceOf(LoopWorkflow::class, $workflow);
    }

    public function test_creates_while_loop()
    {
        $condition = fn ($input) => $input['counter'] < 5;
        $workflow = $this->manager->while(TestAgent::class, $condition);

        $this->assertInstanceOf(LoopWorkflow::class, $workflow);
    }

    public function test_creates_until_loop()
    {
        $condition = fn ($input) => $input['counter'] >= 5;
        $workflow = $this->manager->until(TestAgent::class, $condition);

        $this->assertInstanceOf(LoopWorkflow::class, $workflow);
    }

    public function test_creates_times_loop()
    {
        $workflow = $this->manager->times(TestAgent::class, 5);
        $this->assertInstanceOf(LoopWorkflow::class, $workflow);
    }

    public function test_creates_foreach_loop()
    {
        $collection = ['a', 'b', 'c'];
        $workflow = $this->manager->forEach(TestAgent::class, $collection);

        $this->assertInstanceOf(LoopWorkflow::class, $workflow);
    }

    public function test_creates_sequential_workflow_from_array()
    {
        $definition = [
            'type' => 'sequential',
            'steps' => [
                ['agent' => FirstAgent::class, 'params' => ['param1' => 'value1']],
                ['agent' => SecondAgent::class, 'params' => ['param2' => 'value2']],
                ['agent' => ThirdAgent::class],
            ],
        ];

        $workflow = $this->manager->fromArray($definition);
        $this->assertInstanceOf(SequentialWorkflow::class, $workflow);
    }

    public function test_creates_parallel_workflow_from_array()
    {
        $definition = [
            'type' => 'parallel',
            'agents' => [
                ['name' => FirstAgent::class, 'params' => ['param1' => 'value1']],
                ['name' => SecondAgent::class, 'params' => ['param2' => 'value2']],
                ThirdAgent::class,
            ],
            'wait_for_all' => true,
        ];

        $workflow = $this->manager->fromArray($definition);
        $this->assertInstanceOf(ParallelWorkflow::class, $workflow);
    }

    public function test_creates_parallel_workflow_from_array_with_wait_count()
    {
        $definition = [
            'type' => 'parallel',
            'agents' => [FirstAgent::class, SecondAgent::class, ThirdAgent::class],
            'wait_for_all' => false,
            'wait_for_count' => 2,
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
                    'agent' => FirstAgent::class,
                    'params' => ['param1' => 'value1'],
                ],
                [
                    'condition' => 'another_condition',
                    'agent' => SecondAgent::class,
                ],
            ],
            'default' => [
                'agent' => DefaultAgent::class,
                'params' => ['default' => true],
            ],
        ];

        $workflow = $this->manager->fromArray($definition);
        $this->assertInstanceOf(ConditionalWorkflow::class, $workflow);
    }

    public function test_creates_while_loop_workflow_from_array()
    {
        $definition = [
            'type' => 'loop',
            'agent' => TestAgent::class,
            'loop_type' => 'while',
            'condition' => 'some_condition',
            'max_iterations' => 10,
        ];

        $workflow = $this->manager->fromArray($definition);
        $this->assertInstanceOf(LoopWorkflow::class, $workflow);
    }

    public function test_creates_until_loop_workflow_from_array()
    {
        $definition = [
            'type' => 'loop',
            'agent' => TestAgent::class,
            'loop_type' => 'until',
            'condition' => 'some_condition',
        ];

        $workflow = $this->manager->fromArray($definition);
        $this->assertInstanceOf(LoopWorkflow::class, $workflow);
    }

    public function test_creates_times_loop_workflow_from_array()
    {
        $definition = [
            'type' => 'loop',
            'agent' => TestAgent::class,
            'loop_type' => 'times',
            'times' => 5,
        ];

        $workflow = $this->manager->fromArray($definition);
        $this->assertInstanceOf(LoopWorkflow::class, $workflow);
    }

    public function test_creates_foreach_loop_workflow_from_array()
    {
        $definition = [
            'type' => 'loop',
            'agent' => TestAgent::class,
            'loop_type' => 'forEach',
            'collection' => ['a', 'b', 'c'],
        ];

        $workflow = $this->manager->fromArray($definition);
        $this->assertInstanceOf(LoopWorkflow::class, $workflow);
    }

    public function test_throws_exception_for_unknown_workflow_type()
    {
        $definition = [
            'type' => 'unknown_type',
            'agent' => 'TestAgent',
        ];

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown workflow type: unknown_type');

        $this->manager->fromArray($definition);
    }

    public function test_sequential_workflow_defaults_to_sequential_type()
    {
        $definition = [
            'steps' => [
                ['agent' => FirstAgent::class],
                ['agent' => SecondAgent::class],
            ],
        ];

        $workflow = $this->manager->fromArray($definition);
        $this->assertInstanceOf(SequentialWorkflow::class, $workflow);
    }

    public function test_creates_empty_workflows_from_minimal_definitions()
    {
        $definitions = [
            ['type' => 'sequential'],
            ['type' => 'parallel'],
            ['type' => 'conditional'],
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
            'agent' => TestAgent::class,
            'condition' => 'some_condition',
        ];

        $workflow = $this->manager->fromArray($definition);
        $this->assertInstanceOf(LoopWorkflow::class, $workflow);
    }

    public function test_handles_empty_agents_array_in_parallel_definition()
    {
        $definition = [
            'type' => 'parallel',
            'agents' => [],
        ];

        $workflow = $this->manager->fromArray($definition);
        $this->assertInstanceOf(ParallelWorkflow::class, $workflow);
    }

    public function test_handles_empty_conditions_array_in_conditional_definition()
    {
        $definition = [
            'type' => 'conditional',
            'conditions' => [],
        ];

        $workflow = $this->manager->fromArray($definition);
        $this->assertInstanceOf(ConditionalWorkflow::class, $workflow);
    }

    public function test_handles_empty_steps_array_in_sequential_definition()
    {
        $definition = [
            'type' => 'sequential',
            'steps' => [],
        ];

        $workflow = $this->manager->fromArray($definition);
        $this->assertInstanceOf(SequentialWorkflow::class, $workflow);
    }
}
