<?php

namespace Vizra\VizraADK\Tests\Unit\Agents;

use Vizra\VizraADK\Agents\SequentialWorkflow;
use Vizra\VizraADK\Agents\BaseLlmAgent;
use Vizra\VizraADK\System\AgentContext;
use Vizra\VizraADK\Services\AgentRegistry;
use Vizra\VizraADK\Tests\TestCase;
use Vizra\VizraADK\Facades\Agent;
use Mockery;

class ConditionalAgent extends BaseLlmAgent {
    protected string $name = 'conditional_agent';
}

class SequentialWorkflowTest extends TestCase
{
    protected SequentialWorkflow $workflow;
    protected AgentContext $context;

    protected function setUp(): void
    {
        parent::setUp();
        $this->workflow = new SequentialWorkflow();
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
        $this->assertEquals('SequentialWorkflow', $this->workflow->getName());
        $this->assertEquals('Executes agents sequentially, passing results between steps', $this->workflow->getDescription());
    }

    public function test_can_add_agents_with_start_and_then()
    {
        $workflow = $this->workflow
            ->start(FirstAgent::class)
            ->then(SecondAgent::class)
            ->then(ThirdAgent::class);

        $this->assertInstanceOf(SequentialWorkflow::class, $workflow);
        $this->assertCount(0, $workflow->getResults()); // This will be empty until executed
    }

    public function test_can_add_finally_step()
    {
        $workflow = $this->workflow
            ->start(FirstAgent::class)
            ->then(SecondAgent::class)
            ->finally(CleanupAgent::class);

        $this->assertInstanceOf(SequentialWorkflow::class, $workflow);
    }

    public function test_can_add_conditional_step()
    {
        $condition = fn($input) => $input['proceed'] === true;

        $workflow = $this->workflow
            ->start(FirstAgent::class)
            ->when(ConditionalAgent::class, $condition);

        $this->assertInstanceOf(SequentialWorkflow::class, $workflow);
    }

    public function test_can_set_callbacks()
    {
        $successCallback = fn($result) => $result;
        $failureCallback = fn($error) => $error;
        $completeCallback = fn($result, $success) => $success;

        $workflow = $this->workflow
            ->onSuccess($successCallback)
            ->onFailure($failureCallback)
            ->onComplete($completeCallback);

        $this->assertInstanceOf(SequentialWorkflow::class, $workflow);
    }

    public function test_can_set_timeout_and_retry()
    {
        $workflow = $this->workflow
            ->timeout(60)
            ->retryOnFailure(3, 2000);

        $this->assertInstanceOf(SequentialWorkflow::class, $workflow);
    }

    public function test_execute_sequential_workflow()
    {
        // Mock the Agent facade
        Agent::shouldReceive('run')
            ->with('first_agent', 'initial_input', 'test-session')
            ->once()
            ->andReturn('first_result');

        Agent::shouldReceive('run')
            ->with('second_agent', 'first_result', 'test-session')
            ->once()
            ->andReturn('second_result');

        Agent::shouldReceive('run')
            ->with('third_agent', 'second_result', 'test-session')
            ->once()
            ->andReturn('final_result');

        $result = $this->workflow
            ->start(FirstAgent::class)
            ->then(SecondAgent::class)
            ->then(ThirdAgent::class)
            ->execute('initial_input', $this->context);

        $this->assertIsArray($result);
        $this->assertEquals('final_result', $result['final_result']);
        $this->assertEquals('sequential', $result['workflow_type']);
        $this->assertArrayHasKey('step_results', $result);
    }

    public function test_execute_with_parameters()
    {
        Agent::shouldReceive('run')
            ->with('first_agent', ['custom' => 'params'], 'test-session')
            ->once()
            ->andReturn('first_result');

        Agent::shouldReceive('run')
            ->with('second_agent', 'first_result', 'test-session')
            ->once()
            ->andReturn('second_result');

        $result = $this->workflow
            ->start(FirstAgent::class, ['custom' => 'params'])
            ->then(SecondAgent::class)
            ->execute('ignored_input', $this->context);

        $this->assertEquals('second_result', $result['final_result']);
    }

    public function test_execute_with_closure_parameters()
    {
        Agent::shouldReceive('run')
            ->with('first_agent', 'initial_input', 'test-session')
            ->once()
            ->andReturn(['data' => 'first_result']);

        Agent::shouldReceive('run')
            ->with('second_agent', 'first_result', 'test-session')
            ->once()
            ->andReturn('final_result');

        $result = $this->workflow
            ->start(FirstAgent::class)
            ->then(SecondAgent::class, fn($input, $results) => $results[FirstAgent::class]['data'])
            ->execute('initial_input', $this->context);

        $this->assertEquals('final_result', $result['final_result']);
    }

    public function test_finally_steps_execute_on_success()
    {
        Agent::shouldReceive('run')
            ->with('first_agent', 'input', 'test-session')
            ->once()
            ->andReturn('result');

        Agent::shouldReceive('run')
            ->with('cleanup_agent', 'result', 'test-session')
            ->once()
            ->andReturn('cleanup_result');

        $result = $this->workflow
            ->start(FirstAgent::class)
            ->finally(CleanupAgent::class)
            ->execute('input', $this->context);

        $this->assertEquals('result', $result['final_result']);
    }

    public function test_finally_steps_execute_on_failure()
    {
        Agent::shouldReceive('run')
            ->with('first_agent', 'input', 'test-session')
            ->once()
            ->andThrow(new \Exception('Agent failed'));

        Agent::shouldReceive('run')
            ->with('cleanup_agent', 'input', 'test-session')
            ->once()
            ->andReturn('cleanup_result');

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Agent failed');

        $this->workflow
            ->start(FirstAgent::class)
            ->finally(CleanupAgent::class)
            ->execute('input', $this->context);
    }

    public function test_conditional_step_executes_when_condition_true()
    {
        Agent::shouldReceive('run')
            ->with('first_agent', ['proceed' => true], 'test-session')
            ->once()
            ->andReturn(['proceed' => true]);

        Agent::shouldReceive('run')
            ->with('conditional_agent', ['proceed' => true], 'test-session')
            ->once()
            ->andReturn('conditional_result');

        $result = $this->workflow
            ->start(FirstAgent::class)
            ->when(ConditionalAgent::class, fn($input) => $input['proceed'] === true)
            ->execute(['proceed' => true], $this->context);

        $this->assertEquals('conditional_result', $result['final_result']);
    }

    public function test_conditional_step_skips_when_condition_false()
    {
        Agent::shouldReceive('run')
            ->with('first_agent', ['proceed' => false], 'test-session')
            ->once()
            ->andReturn(['proceed' => false]);

        // ConditionalAgent should not be called

        $result = $this->workflow
            ->start(FirstAgent::class)
            ->when(ConditionalAgent::class, fn($input) => $input['proceed'] === true)
            ->execute(['proceed' => false], $this->context);

        $this->assertEquals(['proceed' => false], $result['final_result']);
    }

    public function test_can_reset_workflow()
    {
        $workflow = $this->workflow
            ->start(FirstAgent::class)
            ->then(SecondAgent::class);

        $resetWorkflow = $workflow->reset();

        $this->assertInstanceOf(SequentialWorkflow::class, $resetWorkflow);
        $this->assertEmpty($resetWorkflow->getResults());
    }

    public function test_static_create_method()
    {
        $workflow = SequentialWorkflow::create(FirstAgent::class, SecondAgent::class, ThirdAgent::class);

        $this->assertInstanceOf(SequentialWorkflow::class, $workflow);
    }

    public function test_success_callback_is_called()
    {
        $callbackCalled = false;
        $callbackResult = null;

        Agent::shouldReceive('run')
            ->with('test_agent', 'input', 'test-session')
            ->once()
            ->andReturn('result');

        $this->workflow
            ->start(TestAgent::class)
            ->onSuccess(function($result) use (&$callbackCalled, &$callbackResult) {
                $callbackCalled = true;
                $callbackResult = $result;
            })
            ->execute('input', $this->context);

        $this->assertTrue($callbackCalled);
        $this->assertNotNull($callbackResult);
    }

    public function test_failure_callback_is_called()
    {
        $callbackCalled = false;
        $callbackError = null;

        Agent::shouldReceive('run')
            ->with('test_agent', 'input', 'test-session')
            ->once()
            ->andThrow(new \Exception('Test error'));

        $this->expectException(\Exception::class);

        $this->workflow
            ->start(TestAgent::class)
            ->onFailure(function($error) use (&$callbackCalled, &$callbackError) {
                $callbackCalled = true;
                $callbackError = $error;
            })
            ->execute('input', $this->context);

        $this->assertTrue($callbackCalled);
        $this->assertInstanceOf(\Exception::class, $callbackError);
    }

    public function test_complete_callback_is_called_on_success()
    {
        $callbackCalled = false;
        $callbackSuccess = null;

        Agent::shouldReceive('run')
            ->with('test_agent', 'input', 'test-session')
            ->once()
            ->andReturn('result');

        $this->workflow
            ->start(TestAgent::class)
            ->onComplete(function($result, $success) use (&$callbackCalled, &$callbackSuccess) {
                $callbackCalled = true;
                $callbackSuccess = $success;
            })
            ->execute('input', $this->context);

        $this->assertTrue($callbackCalled);
        $this->assertTrue($callbackSuccess);
    }

    public function test_complete_callback_is_called_on_failure()
    {
        $callbackCalled = false;
        $callbackSuccess = null;

        Agent::shouldReceive('run')
            ->with('test_agent', 'input', 'test-session')
            ->once()
            ->andThrow(new \Exception('Test error'));

        $this->expectException(\Exception::class);

        $this->workflow
            ->start(TestAgent::class)
            ->onComplete(function($result, $success) use (&$callbackCalled, &$callbackSuccess) {
                $callbackCalled = true;
                $callbackSuccess = $success;
            })
            ->execute('input', $this->context);

        $this->assertTrue($callbackCalled);
        $this->assertFalse($callbackSuccess);
    }

    public function test_can_get_step_results()
    {
        Agent::shouldReceive('run')
            ->with('first_agent', 'input', 'test-session')
            ->once()
            ->andReturn('first_result');

        Agent::shouldReceive('run')
            ->with('second_agent', 'first_result', 'test-session')
            ->once()
            ->andReturn('second_result');

        $this->workflow
            ->start(FirstAgent::class)
            ->then(SecondAgent::class)
            ->execute('input', $this->context);

        $this->assertEquals('first_result', $this->workflow->getStepResult(FirstAgent::class));
        $this->assertEquals('second_result', $this->workflow->getStepResult(SecondAgent::class));
        $this->assertNull($this->workflow->getStepResult('NonExistentAgent'));
    }
}
