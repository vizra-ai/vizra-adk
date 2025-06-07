<?php

namespace AaronLumsden\LaravelAiADK\Tests\Unit\Facades;

use AaronLumsden\LaravelAiADK\Facades\Workflow;
use AaronLumsden\LaravelAiADK\Agents\SequentialWorkflow;
use AaronLumsden\LaravelAiADK\Agents\ParallelWorkflow;
use AaronLumsden\LaravelAiADK\Agents\ConditionalWorkflow;
use AaronLumsden\LaravelAiADK\Agents\LoopWorkflow;
use AaronLumsden\LaravelAiADK\Tests\TestCase;

class WorkflowTest extends TestCase
{
    public function test_facade_accessor_is_correct()
    {
        $reflection = new \ReflectionClass(Workflow::class);
        $method = $reflection->getMethod('getFacadeAccessor');
        $method->setAccessible(true);
        
        $this->assertEquals('laravel-ai-adk.workflow', $method->invoke(new Workflow()));
    }

    public function test_sequential_method_creates_sequential_workflow()
    {
        $workflow = Workflow::sequential();
        $this->assertInstanceOf(SequentialWorkflow::class, $workflow);
    }

    public function test_sequential_method_with_agents()
    {
        $workflow = Workflow::sequential('FirstAgent', 'SecondAgent');
        $this->assertInstanceOf(SequentialWorkflow::class, $workflow);
    }

    public function test_parallel_method_creates_parallel_workflow()
    {
        $workflow = Workflow::parallel();
        $this->assertInstanceOf(ParallelWorkflow::class, $workflow);
    }

    public function test_parallel_method_with_agents()
    {
        $workflow = Workflow::parallel(['FirstAgent', 'SecondAgent']);
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
        $workflow = Workflow::loop('TestAgent');
        $this->assertInstanceOf(LoopWorkflow::class, $workflow);
    }

    public function test_while_method_creates_while_loop()
    {
        $condition = fn($input) => $input['counter'] < 5;
        $workflow = Workflow::while('TestAgent', $condition);
        
        $this->assertInstanceOf(LoopWorkflow::class, $workflow);
    }

    public function test_until_method_creates_until_loop()
    {
        $condition = fn($input) => $input['counter'] >= 5;
        $workflow = Workflow::until('TestAgent', $condition);
        
        $this->assertInstanceOf(LoopWorkflow::class, $workflow);
    }

    public function test_times_method_creates_times_loop()
    {
        $workflow = Workflow::times('TestAgent', 5);
        $this->assertInstanceOf(LoopWorkflow::class, $workflow);
    }

    public function test_foreach_method_creates_foreach_loop()
    {
        $collection = ['a', 'b', 'c'];
        $workflow = Workflow::forEach('TestAgent', $collection);
        
        $this->assertInstanceOf(LoopWorkflow::class, $workflow);
    }

    public function test_from_array_method_creates_appropriate_workflow()
    {
        $sequentialDefinition = ['type' => 'sequential'];
        $parallelDefinition = ['type' => 'parallel'];
        $conditionalDefinition = ['type' => 'conditional'];
        $loopDefinition = ['type' => 'loop', 'agent' => 'TestAgent'];

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
            'while' => Workflow::while('TestAgent', fn() => true),
            'until' => Workflow::until('TestAgent', fn() => false),
            'times' => Workflow::times('TestAgent', 3),
            'forEach' => Workflow::forEach('TestAgent', ['a', 'b']),
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
            ->start('FirstAgent')
            ->then('SecondAgent');

        $parallel = Workflow::parallel()
            ->agents(['FirstAgent', 'SecondAgent'])
            ->waitForAll();

        $conditional = Workflow::conditional()
            ->when(fn() => true, 'TrueAgent')
            ->otherwise('FalseAgent');

        $loop = Workflow::loop()
            ->agent('TestAgent')
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
            ->start('InitAgent')
            ->then('ProcessAgent');

        $this->assertInstanceOf(SequentialWorkflow::class, $mainWorkflow);
    }

    public function test_facade_provides_access_to_all_workflow_types()
    {
        // Ensure facade provides access to all workflow types
        $workflowTypes = [
            Workflow::sequential(),
            Workflow::parallel(),
            Workflow::conditional(),
            Workflow::loop()
        ];

        $expectedClasses = [
            SequentialWorkflow::class,
            ParallelWorkflow::class,
            ConditionalWorkflow::class,
            LoopWorkflow::class
        ];

        foreach ($workflowTypes as $index => $workflow) {
            $this->assertInstanceOf($expectedClasses[$index], $workflow);
        }
    }
}