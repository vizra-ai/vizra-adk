<?php

namespace AaronLumsden\LaravelAiADK\Tests\Unit\Agents;

use AaronLumsden\LaravelAiADK\Agents\ConditionalWorkflow;
use AaronLumsden\LaravelAiADK\System\AgentContext;
use AaronLumsden\LaravelAiADK\Tests\TestCase;
use AaronLumsden\LaravelAiADK\Facades\Agent;
use Mockery;

class ConditionalWorkflowTest extends TestCase
{
    protected ConditionalWorkflow $workflow;
    protected AgentContext $context;

    protected function setUp(): void
    {
        parent::setUp();
        $this->workflow = new ConditionalWorkflow();
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
        $this->assertEquals('ConditionalWorkflow', $this->workflow->getName());
        $this->assertEquals('Routes execution to different agents based on conditions', $this->workflow->getDescription());
    }

    public function test_can_add_condition_with_closure()
    {
        $condition = fn($input) => $input['type'] === 'premium';
        
        $workflow = $this->workflow->when($condition, 'PremiumAgent');
        $this->assertInstanceOf(ConditionalWorkflow::class, $workflow);
    }

    public function test_can_add_condition_with_string()
    {
        $workflow = $this->workflow->when('some_string_condition', 'TestAgent');
        $this->assertInstanceOf(ConditionalWorkflow::class, $workflow);
    }

    public function test_can_add_otherwise_clause()
    {
        $workflow = $this->workflow
            ->when(fn($input) => false, 'FirstAgent')
            ->otherwise('DefaultAgent');

        $this->assertInstanceOf(ConditionalWorkflow::class, $workflow);
    }

    public function test_can_add_else_clause()
    {
        $workflow = $this->workflow
            ->when(fn($input) => false, 'FirstAgent')
            ->else('DefaultAgent');

        $this->assertInstanceOf(ConditionalWorkflow::class, $workflow);
    }

    public function test_when_equals_condition()
    {
        $workflow = $this->workflow->whenEquals('status', 'active', 'ActiveAgent');
        $this->assertInstanceOf(ConditionalWorkflow::class, $workflow);
    }

    public function test_when_greater_than_condition()
    {
        $workflow = $this->workflow->whenGreaterThan('score', 90, 'HighScoreAgent');
        $this->assertInstanceOf(ConditionalWorkflow::class, $workflow);
    }

    public function test_when_less_than_condition()
    {
        $workflow = $this->workflow->whenLessThan('age', 18, 'MinorAgent');
        $this->assertInstanceOf(ConditionalWorkflow::class, $workflow);
    }

    public function test_when_exists_condition()
    {
        $workflow = $this->workflow->whenExists('email', 'EmailAgent');
        $this->assertInstanceOf(ConditionalWorkflow::class, $workflow);
    }

    public function test_when_empty_condition()
    {
        $workflow = $this->workflow->whenEmpty('description', 'NoDescriptionAgent');
        $this->assertInstanceOf(ConditionalWorkflow::class, $workflow);
    }

    public function test_when_matches_condition()
    {
        $workflow = $this->workflow->whenMatches('email', '/^.+@.+\..+$/', 'ValidEmailAgent');
        $this->assertInstanceOf(ConditionalWorkflow::class, $workflow);
    }

    public function test_execute_first_matching_condition()
    {
        Agent::shouldReceive('run')
            ->with('PremiumAgent', ['type' => 'premium', 'score' => 95], 'test-session')
            ->once()
            ->andReturn('premium_result');

        $result = $this->workflow
            ->when(fn($input) => $input['type'] === 'premium', 'PremiumAgent')
            ->when(fn($input) => $input['score'] > 90, 'HighScoreAgent')
            ->otherwise('DefaultAgent')
            ->execute(['type' => 'premium', 'score' => 95], $this->context);

        $this->assertIsArray($result);
        $this->assertEquals('premium_result', $result['result']);
        $this->assertEquals('PremiumAgent', $result['matched_agent']);
        $this->assertEquals('conditional', $result['workflow_type']);
        $this->assertFalse($result['was_default'] ?? false);
    }

    public function test_execute_otherwise_when_no_conditions_match()
    {
        $this->mockAgentRun('default_result');
        
        $result = $this->workflow
            ->when(fn($input) => $input['type'] === 'premium', 'PremiumAgent')
            ->when(fn($input) => $input['type'] === 'gold', 'GoldAgent')
            ->otherwise('DefaultAgent')
            ->execute(['type' => 'basic'], $this->context);

        $this->assertEquals('default_result', $result['result']);
        $this->assertEquals('DefaultAgent', $result['matched_agent']);
        $this->assertTrue($result['was_default']);
    }

    public function test_throws_exception_when_no_conditions_match_and_no_default()
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('No conditions matched and no default agent specified');

        $this->workflow
            ->when(fn($input) => $input['type'] === 'premium', 'PremiumAgent')
            ->execute(['type' => 'basic'], $this->context);
    }

    public function test_when_equals_with_array_input()
    {
        Agent::shouldReceive('run')
            ->with('ActiveAgent', ['status' => 'active'], null)
            ->once()
            ->andReturn('active_result');

        $result = $this->workflow
            ->whenEquals('status', 'active', 'ActiveAgent')
            ->otherwise('InactiveAgent')
            ->execute(['status' => 'active'], $this->context);

        $this->assertEquals('ActiveAgent', $result['matched_agent']);
    }

    public function test_when_greater_than_with_array_input()
    {
        Agent::shouldReceive('run')
            ->with('HighScoreAgent', ['score' => 95], null)
            ->once()
            ->andReturn('high_score_result');

        $result = $this->workflow
            ->whenGreaterThan('score', 90, 'HighScoreAgent')
            ->otherwise('LowScoreAgent')
            ->execute(['score' => 95], $this->context);

        $this->assertEquals('HighScoreAgent', $result['matched_agent']);
    }

    public function test_when_less_than_with_array_input()
    {
        Agent::shouldReceive('run')
            ->with('MinorAgent', ['age' => 16], null)
            ->once()
            ->andReturn('minor_result');

        $result = $this->workflow
            ->whenLessThan('age', 18, 'MinorAgent')
            ->otherwise('AdultAgent')
            ->execute(['age' => 16], $this->context);

        $this->assertEquals('MinorAgent', $result['matched_agent']);
    }

    public function test_when_exists_with_array_input()
    {
        Agent::shouldReceive('run')
            ->with('EmailAgent', ['email' => 'test@example.com'], null)
            ->once()
            ->andReturn('email_result');

        $result = $this->workflow
            ->whenExists('email', 'EmailAgent')
            ->otherwise('NoEmailAgent')
            ->execute(['email' => 'test@example.com'], $this->context);

        $this->assertEquals('EmailAgent', $result['matched_agent']);
    }

    public function test_when_empty_with_array_input()
    {
        Agent::shouldReceive('run')
            ->with('NoDescriptionAgent', ['description' => ''], null)
            ->once()
            ->andReturn('no_description_result');

        $result = $this->workflow
            ->whenEmpty('description', 'NoDescriptionAgent')
            ->otherwise('HasDescriptionAgent')
            ->execute(['description' => ''], $this->context);

        $this->assertEquals('NoDescriptionAgent', $result['matched_agent']);
    }

    public function test_when_matches_with_valid_email()
    {
        Agent::shouldReceive('run')
            ->with('ValidEmailAgent', ['email' => 'test@example.com'], null)
            ->once()
            ->andReturn('valid_email_result');

        $result = $this->workflow
            ->whenMatches('email', '/^.+@.+\..+$/', 'ValidEmailAgent')
            ->otherwise('InvalidEmailAgent')
            ->execute(['email' => 'test@example.com'], $this->context);

        $this->assertEquals('ValidEmailAgent', $result['matched_agent']);
    }

    public function test_when_matches_with_invalid_email()
    {
        Agent::shouldReceive('run')
            ->with('InvalidEmailAgent', ['email' => 'invalid-email'], null)
            ->once()
            ->andReturn('invalid_email_result');

        $result = $this->workflow
            ->whenMatches('email', '/^.+@.+\..+$/', 'ValidEmailAgent')
            ->otherwise('InvalidEmailAgent')
            ->execute(['email' => 'invalid-email'], $this->context);

        $this->assertEquals('InvalidEmailAgent', $result['matched_agent']);
    }

    public function test_conditions_with_parameters()
    {
        Agent::shouldReceive('run')
            ->with('PremiumAgent', ['custom' => 'params'], null)
            ->once()
            ->andReturn('premium_result');

        $result = $this->workflow
            ->when(fn($input) => $input['type'] === 'premium', 'PremiumAgent', ['custom' => 'params'])
            ->execute(['type' => 'premium'], $this->context);

        $this->assertEquals('premium_result', $result['result']);
    }

    public function test_conditions_with_closure_parameters()
    {
        Agent::shouldReceive('run')
            ->with('PremiumAgent', 'premium', null)
            ->once()
            ->andReturn('premium_result');

        $result = $this->workflow
            ->when(
                fn($input) => $input['type'] === 'premium', 
                'PremiumAgent', 
                fn($input) => $input['type']
            )
            ->execute(['type' => 'premium'], $this->context);

        $this->assertEquals('premium_result', $result['result']);
    }

    public function test_dot_notation_for_nested_arrays()
    {
        Agent::shouldReceive('run')
            ->with('PremiumAgent', ['user' => ['membership' => ['type' => 'premium']]], null)
            ->once()
            ->andReturn('premium_result');

        $result = $this->workflow
            ->whenEquals('user.membership.type', 'premium', 'PremiumAgent')
            ->otherwise('DefaultAgent')
            ->execute(['user' => ['membership' => ['type' => 'premium']]], $this->context);

        $this->assertEquals('PremiumAgent', $result['matched_agent']);
    }

    public function test_scalar_input_with_dot_accessor()
    {
        Agent::shouldReceive('run')
            ->with('StringAgent', 'test_string', null)
            ->once()
            ->andReturn('string_result');

        $result = $this->workflow
            ->whenEquals('.', 'test_string', 'StringAgent')
            ->otherwise('DefaultAgent')
            ->execute('test_string', $this->context);

        $this->assertEquals('StringAgent', $result['matched_agent']);
    }

    public function test_static_create_method()
    {
        $condition = fn($input) => $input['type'] === 'premium';
        $workflow = ConditionalWorkflow::create($condition, 'PremiumAgent', 'DefaultAgent');
        
        $this->assertInstanceOf(ConditionalWorkflow::class, $workflow);
    }

    public function test_static_create_without_else_agent()
    {
        $condition = fn($input) => $input['type'] === 'premium';
        $workflow = ConditionalWorkflow::create($condition, 'PremiumAgent');
        
        $this->assertInstanceOf(ConditionalWorkflow::class, $workflow);
    }

    public function test_callbacks_work_with_conditional_execution()
    {
        $successCallbackCalled = false;
        $completeCallbackCalled = false;

        Agent::shouldReceive('run')
            ->with('PremiumAgent', ['type' => 'premium'], null)
            ->once()
            ->andReturn('premium_result');

        $this->workflow
            ->when(fn($input) => $input['type'] === 'premium', 'PremiumAgent')
            ->onSuccess(function($result) use (&$successCallbackCalled) {
                $successCallbackCalled = true;
            })
            ->onComplete(function($result, $success) use (&$completeCallbackCalled) {
                $completeCallbackCalled = true;
            })
            ->execute(['type' => 'premium'], $this->context);

        $this->assertTrue($successCallbackCalled);
        $this->assertTrue($completeCallbackCalled);
    }

    public function test_can_reset_workflow()
    {
        $workflow = $this->workflow
            ->when(fn($input) => true, 'TestAgent')
            ->otherwise('DefaultAgent');

        $resetWorkflow = $workflow->reset();

        $this->assertInstanceOf(ConditionalWorkflow::class, $resetWorkflow);
        $this->assertEmpty($resetWorkflow->getResults());
    }
}