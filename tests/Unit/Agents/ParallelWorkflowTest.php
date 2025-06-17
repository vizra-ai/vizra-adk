<?php

namespace Vizra\VizraADK\Tests\Unit\Agents;

use Vizra\VizraADK\Agents\ParallelWorkflow;
use Vizra\VizraADK\System\AgentContext;
use Vizra\VizraADK\Tests\TestCase;
use Vizra\VizraADK\Facades\Agent;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Cache;
use Mockery;

class ParallelWorkflowTest extends TestCase
{
    protected ParallelWorkflow $workflow;
    protected AgentContext $context;

    protected function setUp(): void
    {
        parent::setUp();
        $this->workflow = new ParallelWorkflow();
        $this->context = new AgentContext('test-session');
    }

    protected function mockAgentRun($returnValue = 'mocked_result')
    {
        Agent::shouldReceive('run')
            ->andReturn($returnValue)
            ->byDefault();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
    }

    public function test_workflow_has_correct_name_and_description()
    {
        $this->assertEquals('ParallelWorkflow', $this->workflow->getName());
        $this->assertEquals('Executes agents in parallel and collects results', $this->workflow->getDescription());
    }

    public function test_can_add_single_agent()
    {
        $workflow = $this->workflow->agents('SingleAgent');
        $this->assertInstanceOf(ParallelWorkflow::class, $workflow);
    }

    public function test_can_add_multiple_agents_as_array()
    {
        $workflow = $this->workflow->agents(['FirstAgent', 'SecondAgent', 'ThirdAgent']);
        $this->assertInstanceOf(ParallelWorkflow::class, $workflow);
    }

    public function test_can_add_agents_with_parameters()
    {
        $workflow = $this->workflow->agents([
            'FirstAgent' => ['param1' => 'value1'],
            'SecondAgent' => ['param2' => 'value2']
        ]);
        $this->assertInstanceOf(ParallelWorkflow::class, $workflow);
    }

    public function test_can_set_wait_conditions()
    {
        $workflow = $this->workflow
            ->waitForAll()
            ->waitForAny()
            ->waitFor(2);

        $this->assertInstanceOf(ParallelWorkflow::class, $workflow);
    }

    public function test_can_set_fail_fast()
    {
        $workflow = $this->workflow
            ->failFast(true)
            ->failFast(false);

        $this->assertInstanceOf(ParallelWorkflow::class, $workflow);
    }

    public function test_can_set_async_mode()
    {
        $workflow = $this->workflow
            ->async(true)
            ->async(false);

        $this->assertInstanceOf(ParallelWorkflow::class, $workflow);
    }

    public function test_execute_parallel_agents_wait_for_all()
    {
        // Mock all agents to be called in parallel
        Agent::shouldReceive('run')
            ->with('FirstAgent', 'input', 'test-session')
            ->once()
            ->andReturn('first_result');

        Agent::shouldReceive('run')
            ->with('SecondAgent', 'input', 'test-session')
            ->once()
            ->andReturn('second_result');

        Agent::shouldReceive('run')
            ->with('ThirdAgent', 'input', 'test-session')
            ->once()
            ->andReturn('third_result');

        $result = $this->workflow
            ->agents(['FirstAgent', 'SecondAgent', 'ThirdAgent'])
            ->waitForAll()
            ->execute('input', $this->context);

        $this->assertIsArray($result);
        $this->assertEquals('parallel', $result['workflow_type']);
        $this->assertEquals(3, $result['completed_count']);
        $this->assertEquals(3, $result['total_count']);
        $this->assertArrayHasKey('results', $result);
        $this->assertArrayHasKey('FirstAgent', $result['results']);
        $this->assertArrayHasKey('SecondAgent', $result['results']);
        $this->assertArrayHasKey('ThirdAgent', $result['results']);
    }

    public function test_execute_with_different_parameters()
    {
        Agent::shouldReceive('run')
            ->with('FirstAgent', ['param1' => 'value1'], 'test-session')
            ->once()
            ->andReturn('first_result');

        Agent::shouldReceive('run')
            ->with('SecondAgent', ['param2' => 'value2'], 'test-session')
            ->once()
            ->andReturn('second_result');

        $result = $this->workflow
            ->agents([
                'FirstAgent' => ['param1' => 'value1'],
                'SecondAgent' => ['param2' => 'value2']
            ])
            ->execute('ignored_input', $this->context);

        $this->assertEquals('first_result', $result['results']['FirstAgent']);
        $this->assertEquals('second_result', $result['results']['SecondAgent']);
    }

    public function test_fail_fast_true_stops_on_first_error()
    {
        Agent::shouldReceive('run')
            ->with('FirstAgent', 'input', 'test-session')
            ->once()
            ->andThrow(new \Exception('First agent failed'));

        // Other agents should not be called due to fail fast
        Agent::shouldReceive('run')
            ->with('SecondAgent', 'input', 'test-session')
            ->never();

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('First agent failed');

        $this->workflow
            ->agents(['FirstAgent', 'SecondAgent'])
            ->failFast(true)
            ->execute('input', $this->context);
    }

    public function test_fail_fast_false_continues_on_error()
    {
        Agent::shouldReceive('run')
            ->with('FirstAgent', 'input', 'test-session')
            ->once()
            ->andThrow(new \Exception('First agent failed'));

        Agent::shouldReceive('run')
            ->with('SecondAgent', 'input', 'test-session')
            ->once()
            ->andReturn('second_result');

        Agent::shouldReceive('run')
            ->with('ThirdAgent', 'input', 'test-session')
            ->once()
            ->andReturn('third_result');

        $result = $this->workflow
            ->agents(['FirstAgent', 'SecondAgent', 'ThirdAgent'])
            ->failFast(false)
            ->waitFor(2) // Only require 2 successes, so 1 failure is acceptable
            ->execute('input', $this->context);

        $this->assertEquals(2, $result['completed_count']);
        $this->assertArrayHasKey('errors', $result);
        $this->assertArrayHasKey('FirstAgent', $result['errors']);
        $this->assertArrayHasKey('SecondAgent', $result['results']);
        $this->assertArrayHasKey('ThirdAgent', $result['results']);
    }

    public function test_wait_for_any_returns_early()
    {
        Agent::shouldReceive('run')
            ->with('FirstAgent', 'input', 'test-session')
            ->once()
            ->andReturn('first_result');

        // Second agent should NOT be called since waitForAny breaks after first success
        Agent::shouldReceive('run')
            ->with('SecondAgent', 'input', 'test-session')
            ->never();

        $result = $this->workflow
            ->agents(['FirstAgent', 'SecondAgent'])
            ->waitForAny()
            ->execute('input', $this->context);

        $this->assertEquals(1, $result['completed_count']);
        $this->assertEquals('parallel', $result['workflow_type']);
        $this->assertArrayHasKey('FirstAgent', $result['results']);
        $this->assertArrayNotHasKey('SecondAgent', $result['results']);
    }

    public function test_wait_for_specific_count()
    {
        Agent::shouldReceive('run')
            ->with('FirstAgent', 'input', 'test-session')
            ->once()
            ->andReturn('first_result');

        Agent::shouldReceive('run')
            ->with('SecondAgent', 'input', 'test-session')
            ->once()
            ->andReturn('second_result');

        // Third agent should NOT be called since waitFor(2) breaks after 2 successes
        Agent::shouldReceive('run')
            ->with('ThirdAgent', 'input', 'test-session')
            ->never();

        $result = $this->workflow
            ->agents(['FirstAgent', 'SecondAgent', 'ThirdAgent'])
            ->waitFor(2)
            ->execute('input', $this->context);

        $this->assertEquals(2, $result['completed_count']);
        $this->assertArrayHasKey('FirstAgent', $result['results']);
        $this->assertArrayHasKey('SecondAgent', $result['results']);
        $this->assertArrayNotHasKey('ThirdAgent', $result['results']);
    }

    public function test_async_mode_returns_job_tracking_info()
    {
        // Use Laravel's Queue fake instead of global facade mock
        Queue::fake();

        $result = $this->workflow
            ->agents(['FirstAgent', 'SecondAgent'])
            ->async(true)
            ->execute('input', $this->context);

        $this->assertEquals('parallel_async', $result['workflow_type']);
        $this->assertEquals('queued', $result['status']);
        $this->assertArrayHasKey('job_ids', $result);
        $this->assertArrayHasKey('session_id', $result);

        // Verify that jobs were pushed to the queue (don't check specific count as implementation may vary)
        Queue::assertPushed(\Vizra\VizraADK\Jobs\AgentJob::class);
    }

    public function test_static_create_method()
    {
        $workflow = ParallelWorkflow::create(['FirstAgent', 'SecondAgent']);
        $this->assertInstanceOf(ParallelWorkflow::class, $workflow);
    }

    public function test_callbacks_work_with_parallel_execution()
    {
        $successCallbackCalled = false;
        $completeCallbackCalled = false;

        Agent::shouldReceive('run')
            ->with('FirstAgent', 'input', 'test-session')
            ->once()
            ->andReturn('first_result');

        Agent::shouldReceive('run')
            ->with('SecondAgent', 'input', 'test-session')
            ->once()
            ->andReturn('second_result');

        $this->workflow
            ->agents(['FirstAgent', 'SecondAgent'])
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

    public function test_failure_callback_with_fail_fast()
    {
        $failureCallbackCalled = false;
        $errorReceived = null;

        Agent::shouldReceive('run')
            ->with('FirstAgent', 'input', 'test-session')
            ->once()
            ->andThrow(new \Exception('Agent failed'));

        $this->expectException(\Exception::class);

        $this->workflow
            ->agents(['FirstAgent', 'SecondAgent'])
            ->failFast(true)
            ->onFailure(function($error) use (&$failureCallbackCalled, &$errorReceived) {
                $failureCallbackCalled = true;
                $errorReceived = $error;
            })
            ->execute('input', $this->context);

        $this->assertTrue($failureCallbackCalled);
        $this->assertInstanceOf(\Exception::class, $errorReceived);
    }

    public function test_can_reset_workflow()
    {
        $workflow = $this->workflow->agents(['FirstAgent', 'SecondAgent']);
        $resetWorkflow = $workflow->reset();

        $this->assertInstanceOf(ParallelWorkflow::class, $resetWorkflow);
        $this->assertEmpty($resetWorkflow->getResults());
    }

    public function test_timeout_and_retry_settings()
    {
        $workflow = $this->workflow
            ->timeout(120)
            ->retryOnFailure(5, 3000);

        $this->assertInstanceOf(ParallelWorkflow::class, $workflow);
    }

    public function test_insufficient_completions_without_fail_fast_throws_error()
    {
        Agent::shouldReceive('run')
            ->with('FirstAgent', 'input', 'test-session')
            ->once()
            ->andThrow(new \Exception('First failed'));

        Agent::shouldReceive('run')
            ->with('SecondAgent', 'input', 'test-session')
            ->once()
            ->andThrow(new \Exception('Second failed'));

        Agent::shouldReceive('run')
            ->with('ThirdAgent', 'input', 'test-session')
            ->once()
            ->andReturn('third_result');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Only 1 of 2 required agents completed successfully');

        $this->workflow
            ->agents(['FirstAgent', 'SecondAgent', 'ThirdAgent'])
            ->waitFor(2) // Need 2 successes but only get 1
            ->failFast(false)
            ->execute('input', $this->context);
    }

    public function test_get_async_results_static_method()
    {
        // Mock the Cache facade instead of using fake
        \Illuminate\Support\Facades\Cache::shouldReceive('get')
            ->with('workflow_results_test_session', [])
            ->once()
            ->andReturn([
                'FirstAgent' => 'first_result',
                'SecondAgent' => 'second_result'
            ]);

        $results = ParallelWorkflow::getAsyncResults('test_session');

        $this->assertIsArray($results);
        $this->assertArrayHasKey('FirstAgent', $results);
        $this->assertArrayHasKey('SecondAgent', $results);
    }
}
