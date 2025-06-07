<?php

namespace AaronLumsden\LaravelAgentADK\Tests\Unit\Agents;

use AaronLumsden\LaravelAgentADK\Agents\LoopWorkflow;
use AaronLumsden\LaravelAgentADK\System\AgentContext;
use AaronLumsden\LaravelAgentADK\Tests\TestCase;
use AaronLumsden\LaravelAgentADK\Facades\Agent;
use Mockery;

class LoopWorkflowTest extends TestCase
{
    protected LoopWorkflow $workflow;
    protected AgentContext $context;

    protected function setUp(): void
    {
        parent::setUp();
        $this->workflow = new LoopWorkflow();
        $this->context = new AgentContext();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_workflow_has_correct_name_and_description()
    {
        $this->assertEquals('LoopWorkflow', $this->workflow->getName());
        $this->assertEquals('Repeats agent execution based on conditions', $this->workflow->getDescription());
    }

    public function test_can_set_while_condition()
    {
        $condition = fn($input) => $input['counter'] < 5;
        $workflow = $this->workflow->while($condition);
        
        $this->assertInstanceOf(LoopWorkflow::class, $workflow);
    }

    public function test_can_set_until_condition()
    {
        $condition = fn($input) => $input['counter'] >= 5;
        $workflow = $this->workflow->until($condition);
        
        $this->assertInstanceOf(LoopWorkflow::class, $workflow);
    }

    public function test_can_set_times_loop()
    {
        $workflow = $this->workflow->times(3);
        $this->assertInstanceOf(LoopWorkflow::class, $workflow);
    }

    public function test_can_set_foreach_loop()
    {
        $collection = ['item1', 'item2', 'item3'];
        $workflow = $this->workflow->forEach($collection);
        
        $this->assertInstanceOf(LoopWorkflow::class, $workflow);
    }

    public function test_can_set_max_iterations()
    {
        $workflow = $this->workflow->maxIterations(10);
        $this->assertInstanceOf(LoopWorkflow::class, $workflow);
    }

    public function test_can_set_break_on_error()
    {
        $workflow = $this->workflow
            ->breakOnError(true)
            ->breakOnError(false)
            ->continueOnError();
        
        $this->assertInstanceOf(LoopWorkflow::class, $workflow);
    }

    public function test_can_add_agent()
    {
        $workflow = $this->workflow->agent('TestAgent');
        $this->assertInstanceOf(LoopWorkflow::class, $workflow);
    }

    public function test_times_loop_execution()
    {
        $counter = 0;
        
        Agent::shouldReceive('run')
            ->times(3)
            ->with('CounterAgent', Mockery::any(), null)
            ->andReturnUsing(function() use (&$counter) {
                return ['counter' => ++$counter];
            });

        $result = $this->workflow
            ->agent('CounterAgent')
            ->times(3)
            ->execute(['counter' => 0], $this->context);

        $this->assertIsArray($result);
        $this->assertEquals(3, $result['iterations']);
        $this->assertEquals('times', $result['loop_type']);
        $this->assertTrue($result['completed_normally']);
        $this->assertCount(3, $result['results']);
    }

    public function test_while_loop_execution()
    {
        Agent::shouldReceive('run')
            ->with('CounterAgent', ['counter' => 0], null)
            ->once()
            ->andReturn(['counter' => 1]);

        Agent::shouldReceive('run')
            ->with('CounterAgent', ['counter' => 1], null)
            ->once()
            ->andReturn(['counter' => 2]);

        Agent::shouldReceive('run')
            ->with('CounterAgent', ['counter' => 2], null)
            ->once()
            ->andReturn(['counter' => 3]);

        $result = $this->workflow
            ->agent('CounterAgent')
            ->while(fn($input) => $input['counter'] < 3)
            ->execute(['counter' => 0], $this->context);

        $this->assertEquals(3, $result['iterations']);
        $this->assertEquals('while', $result['loop_type']);
        $this->assertTrue($result['completed_normally']);
    }

    public function test_until_loop_execution()
    {
        Agent::shouldReceive('run')
            ->with('CounterAgent', ['counter' => 0], null)
            ->once()
            ->andReturn(['counter' => 1]);

        Agent::shouldReceive('run')
            ->with('CounterAgent', ['counter' => 1], null)
            ->once()
            ->andReturn(['counter' => 2]);

        Agent::shouldReceive('run')
            ->with('CounterAgent', ['counter' => 2], null)
            ->once()
            ->andReturn(['counter' => 3]);

        $result = $this->workflow
            ->agent('CounterAgent')
            ->until(fn($input) => $input['counter'] >= 3)
            ->execute(['counter' => 0], $this->context);

        $this->assertEquals(3, $result['iterations']);
        $this->assertEquals('until', $result['loop_type']);
        $this->assertTrue($result['completed_normally']);
    }

    public function test_foreach_loop_execution()
    {
        $collection = ['apple', 'banana', 'orange'];

        Agent::shouldReceive('run')
            ->times(3)
            ->with('ProcessItemAgent', Mockery::type('array'), null)
            ->andReturnUsing(function($agent, $params) {
                return ['processed' => $params['item']];
            });

        $result = $this->workflow
            ->agent('ProcessItemAgent')
            ->forEach($collection)
            ->execute('initial_input', $this->context);

        $this->assertEquals(3, $result['iterations']);
        $this->assertEquals('forEach', $result['loop_type']);
        $this->assertTrue($result['completed_normally']);
        
        // Check that each iteration received the correct item
        $this->assertEquals('apple', $result['results'][1]['value']);
        $this->assertEquals('banana', $result['results'][2]['value']);
        $this->assertEquals('orange', $result['results'][3]['value']);
    }

    public function test_max_iterations_safety_limit()
    {
        Agent::shouldReceive('run')
            ->times(5) // Should stop at max iterations
            ->with('InfiniteAgent', Mockery::any(), null)
            ->andReturn(['continue' => true]);

        $result = $this->workflow
            ->agent('InfiniteAgent')
            ->while(fn($input) => true) // Would loop forever
            ->maxIterations(5)
            ->execute(['continue' => true], $this->context);

        $this->assertEquals(5, $result['iterations']);
        $this->assertFalse($result['completed_normally']); // Hit max iterations
    }

    public function test_break_on_error_true()
    {
        Agent::shouldReceive('run')
            ->with('FailingAgent', Mockery::any(), null)
            ->once()
            ->andThrow(new \Exception('Agent failed'));

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Agent failed');

        $this->workflow
            ->agent('FailingAgent')
            ->times(3)
            ->breakOnError(true)
            ->execute('input', $this->context);
    }

    public function test_continue_on_error()
    {
        Agent::shouldReceive('run')
            ->with('SometimesFailingAgent', Mockery::any(), null)
            ->times(3)
            ->andReturnUsing(function($agent, $params, $session) {
                static $call = 0;
                $call++;
                if ($call === 2) {
                    throw new \Exception('Failed on second call');
                }
                return ['call' => $call];
            });

        $result = $this->workflow
            ->agent('SometimesFailingAgent')
            ->times(3)
            ->continueOnError()
            ->execute('input', $this->context);

        $this->assertEquals(3, $result['iterations']);
        $this->assertTrue($result['results'][1]['success']);
        $this->assertFalse($result['results'][2]['success']);
        $this->assertTrue($result['results'][3]['success']);
    }

    public function test_throws_error_when_no_agent_specified()
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('No agent specified for loop execution');

        $this->workflow
            ->times(3)
            ->execute('input', $this->context);
    }

    public function test_get_iteration_result()
    {
        Agent::shouldReceive('run')
            ->times(2)
            ->with('TestAgent', Mockery::any(), null)
            ->andReturn('result_1', 'result_2');

        $this->workflow
            ->agent('TestAgent')
            ->times(2)
            ->execute('input', $this->context);

        $firstResult = $this->workflow->getIterationResult(1);
        $secondResult = $this->workflow->getIterationResult(2);
        $nonExistentResult = $this->workflow->getIterationResult(5);

        $this->assertNotNull($firstResult);
        $this->assertNotNull($secondResult);
        $this->assertNull($nonExistentResult);
        $this->assertEquals(1, $firstResult['iteration']);
        $this->assertEquals(2, $secondResult['iteration']);
    }

    public function test_get_successful_results()
    {
        Agent::shouldReceive('run')
            ->with('SometimesFailingAgent', Mockery::any(), null)
            ->times(3)
            ->andReturnUsing(function($agent, $params, $session) {
                static $call = 0;
                $call++;
                if ($call === 2) {
                    throw new \Exception('Failed on second call');
                }
                return ['call' => $call];
            });

        $this->workflow
            ->agent('SometimesFailingAgent')
            ->times(3)
            ->continueOnError()
            ->execute('input', $this->context);

        $successfulResults = $this->workflow->getSuccessfulResults();
        $failedResults = $this->workflow->getFailedResults();

        $this->assertCount(2, $successfulResults);
        $this->assertCount(1, $failedResults);
    }

    public function test_static_create_while_method()
    {
        $workflow = LoopWorkflow::createWhile(
            'TestAgent',
            fn($input) => $input['counter'] < 3,
            10
        );

        $this->assertInstanceOf(LoopWorkflow::class, $workflow);
    }

    public function test_static_create_times_method()
    {
        $workflow = LoopWorkflow::createTimes('TestAgent', 5);
        $this->assertInstanceOf(LoopWorkflow::class, $workflow);
    }

    public function test_static_create_foreach_method()
    {
        $collection = ['a', 'b', 'c'];
        $workflow = LoopWorkflow::createForEach('TestAgent', $collection);
        
        $this->assertInstanceOf(LoopWorkflow::class, $workflow);
    }

    public function test_callbacks_work_with_loop_execution()
    {
        $successCallbackCalled = false;
        $completeCallbackCalled = false;

        Agent::shouldReceive('run')
            ->times(2)
            ->with('TestAgent', Mockery::any(), null)
            ->andReturn('result');

        $this->workflow
            ->agent('TestAgent')
            ->times(2)
            ->onSuccess(function($result) use (&$successCallbackCalled) {
                $successCallbackCalled = true;
            })
            ->onComplete(function($result, $success) use (&$completeCallbackCalled) {
                $completeCallbackCalled = true;
            })
            ->execute('input', $this->context);

        $this->assertTrue($successCallbackCalled);
        $this->assertTrue($completeCallbackCalled);
    }

    public function test_can_reset_workflow()
    {
        $workflow = $this->workflow
            ->agent('TestAgent')
            ->times(3);

        $resetWorkflow = $workflow->reset();

        $this->assertInstanceOf(LoopWorkflow::class, $resetWorkflow);
        $this->assertEmpty($resetWorkflow->getResults());
    }

    public function test_foreach_with_associative_array()
    {
        $collection = ['key1' => 'value1', 'key2' => 'value2'];

        Agent::shouldReceive('run')
            ->times(2)
            ->with('ProcessItemAgent', Mockery::type('array'), null)
            ->andReturnUsing(function($agent, $params) {
                return ['processed' => $params['item'], 'key' => $params['key']];
            });

        $result = $this->workflow
            ->agent('ProcessItemAgent')
            ->forEach($collection)
            ->execute('initial_input', $this->context);

        $this->assertEquals(2, $result['iterations']);
        $this->assertEquals('key1', $result['results'][1]['key']);
        $this->assertEquals('value1', $result['results'][1]['value']);
        $this->assertEquals('key2', $result['results'][2]['key']);
        $this->assertEquals('value2', $result['results'][2]['value']);
    }

    public function test_closure_parameters_in_loop()
    {
        Agent::shouldReceive('run')
            ->times(2)
            ->with('TestAgent', Mockery::type('array'), null)
            ->andReturnUsing(function($agent, $params) {
                return ['iteration' => $params['iteration']];
            });

        $result = $this->workflow
            ->agent('TestAgent', fn($input, $results, $context) => [
                'iteration' => count($results) + 1,
                'original_input' => $input
            ])
            ->times(2)
            ->execute('original_input', $this->context);

        $this->assertEquals(2, $result['iterations']);
    }
}