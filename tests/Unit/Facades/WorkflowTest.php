<?php

namespace Vizra\VizraADK\Tests\Unit\Facades;

use Vizra\VizraADK\Agents\BaseLlmAgent;
use Vizra\VizraADK\Agents\ConditionalWorkflow;
use Vizra\VizraADK\Agents\LoopWorkflow;
use Vizra\VizraADK\Agents\ParallelWorkflow;
use Vizra\VizraADK\Agents\SequentialWorkflow;
use Vizra\VizraADK\Facades\Workflow;
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

class TestAgent extends BaseLlmAgent
{
    protected string $name = 'test_agent';
}

class TrueAgent extends BaseLlmAgent
{
    protected string $name = 'true_agent';
}

class FalseAgent extends BaseLlmAgent
{
    protected string $name = 'false_agent';
}

class InitAgent extends BaseLlmAgent
{
    protected string $name = 'init_agent';
}

class ProcessAgent extends BaseLlmAgent
{
    protected string $name = 'process_agent';
}

class WorkflowTest extends TestCase
{
    public function test_facade_accessor_is_correct()
    {
        $reflection = new \ReflectionClass(Workflow::class);
        $method = $reflection->getMethod('getFacadeAccessor');
        $method->setAccessible(true);

        $this->assertEquals('vizra-adk.workflow', $method->invoke(new Workflow));
    }

    public function test_sequential_method_creates_sequential_workflow()
    {
        $workflow = Workflow::sequential();
        $this->assertInstanceOf(SequentialWorkflow::class, $workflow);
    }

    public function test_sequential_method_with_agents()
    {
        $workflow = Workflow::sequential(FirstAgent::class, SecondAgent::class);
        $this->assertInstanceOf(SequentialWorkflow::class, $workflow);
    }

    public function test_parallel_method_creates_parallel_workflow()
    {
        $workflow = Workflow::parallel();
        $this->assertInstanceOf(ParallelWorkflow::class, $workflow);
    }

    public function test_parallel_method_with_agents()
    {
        $workflow = Workflow::parallel([FirstAgent::class, SecondAgent::class]);
        $this->assertInstanceOf(ParallelWorkflow::class, $workflow);
    }

    public function test_conditional_method_creates_conditional_workflow()
    {
        $workflow = Workflow::conditional();
        $this->assertInstanceOf(ConditionalWorkflow::class, $workflow);
    }

    public function test_loop_method_creates_loop_workflow()
    {
        $workflow = Workflow::loop();
        $this->assertInstanceOf(LoopWorkflow::class, $workflow);
    }

    public function test_loop_method_with_agent()
    {
        $workflow = Workflow::loop(TestAgent::class);
        $this->assertInstanceOf(LoopWorkflow::class, $workflow);
    }

    public function test_while_method_creates_while_loop()
    {
        $condition = fn ($input) => $input['counter'] < 5;
        $workflow = Workflow::while(TestAgent::class, $condition);

        $this->assertInstanceOf(LoopWorkflow::class, $workflow);
    }

    public function test_until_method_creates_until_loop()
    {
        $condition = fn ($input) => $input['counter'] >= 5;
        $workflow = Workflow::until(TestAgent::class, $condition);

        $this->assertInstanceOf(LoopWorkflow::class, $workflow);
    }

    public function test_times_method_creates_times_loop()
    {
        $workflow = Workflow::times(TestAgent::class, 5);
        $this->assertInstanceOf(LoopWorkflow::class, $workflow);
    }

    public function test_foreach_method_creates_foreach_loop()
    {
        $collection = ['a', 'b', 'c'];
        $workflow = Workflow::forEach(TestAgent::class, $collection);

        $this->assertInstanceOf(LoopWorkflow::class, $workflow);
    }

    public function test_from_array_method_creates_appropriate_workflow()
    {
        $sequentialDefinition = ['type' => 'sequential'];
        $parallelDefinition = ['type' => 'parallel'];
        $conditionalDefinition = ['type' => 'conditional'];
        $loopDefinition = ['type' => 'loop', 'agent' => TestAgent::class];

        $this->assertInstanceOf(SequentialWorkflow::class, Workflow::fromArray($sequentialDefinition));
        $this->assertInstanceOf(ParallelWorkflow::class, Workflow::fromArray($parallelDefinition));
        $this->assertInstanceOf(ConditionalWorkflow::class, Workflow::fromArray($conditionalDefinition));
        $this->assertInstanceOf(LoopWorkflow::class, Workflow::fromArray($loopDefinition));
    }

    public function test_facade_methods_return_correct_workflow_types()
    {
        // Test that all facade methods return the expected workflow types
        $workflows = [
            'sequential' => Workflow::sequential(),
            'parallel' => Workflow::parallel(),
            'conditional' => Workflow::conditional(),
            'loop' => Workflow::loop(),
            'while' => Workflow::while(TestAgent::class, fn () => true),
            'until' => Workflow::until(TestAgent::class, fn () => false),
            'times' => Workflow::times(TestAgent::class, 3),
            'forEach' => Workflow::forEach(TestAgent::class, ['a', 'b']),
        ];

        $expectedTypes = [
            'sequential' => SequentialWorkflow::class,
            'parallel' => ParallelWorkflow::class,
            'conditional' => ConditionalWorkflow::class,
            'loop' => LoopWorkflow::class,
            'while' => LoopWorkflow::class,
            'until' => LoopWorkflow::class,
            'times' => LoopWorkflow::class,
            'forEach' => LoopWorkflow::class,
        ];

        foreach ($workflows as $method => $workflow) {
            $this->assertInstanceOf(
                $expectedTypes[$method],
                $workflow,
                "Method {$method} should return {$expectedTypes[$method]}"
            );
        }
    }

    public function test_can_chain_facade_methods()
    {
        // Test fluent interface works through the facade
        $sequential = Workflow::sequential()
            ->start(FirstAgent::class)
            ->then(SecondAgent::class);

        $parallel = Workflow::parallel()
            ->agents([FirstAgent::class, SecondAgent::class])
            ->waitForAll();

        $conditional = Workflow::conditional()
            ->when(fn () => true, TrueAgent::class)
            ->otherwise(FalseAgent::class);

        $loop = Workflow::loop()
            ->agent(TestAgent::class)
            ->times(3);

        $this->assertInstanceOf(SequentialWorkflow::class, $sequential);
        $this->assertInstanceOf(ParallelWorkflow::class, $parallel);
        $this->assertInstanceOf(ConditionalWorkflow::class, $conditional);
        $this->assertInstanceOf(LoopWorkflow::class, $loop);
    }

    public function test_complex_workflow_composition()
    {
        // Test creating complex nested workflows through the facade
        $mainWorkflow = Workflow::sequential()
            ->start(InitAgent::class)
            ->then(ProcessAgent::class);

        $this->assertInstanceOf(SequentialWorkflow::class, $mainWorkflow);
    }

    public function test_facade_provides_access_to_all_workflow_types()
    {
        // Ensure facade provides access to all workflow types
        $workflowTypes = [
            Workflow::sequential(),
            Workflow::parallel(),
            Workflow::conditional(),
            Workflow::loop(),
        ];

        $expectedClasses = [
            SequentialWorkflow::class,
            ParallelWorkflow::class,
            ConditionalWorkflow::class,
            LoopWorkflow::class,
        ];

        foreach ($workflowTypes as $index => $workflow) {
            $this->assertInstanceOf($expectedClasses[$index], $workflow);
        }
    }
}
