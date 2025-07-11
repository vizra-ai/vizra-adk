<?php

namespace Vizra\VizraADK\Tests\Unit\Agents;

use Vizra\VizraADK\Agents\ConditionalWorkflow;
use Vizra\VizraADK\Facades\Agent;
use Vizra\VizraADK\System\AgentContext;
use Vizra\VizraADK\Tests\TestCase;

class ConditionalWorkflowTest extends TestCase
{
    protected ConditionalWorkflow $workflow;

    protected AgentContext $context;

    protected function setUp(): void
    {
        parent::setUp();
        $this->workflow = new ConditionalWorkflow;
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
        $condition = fn ($input) => $input['type'] === 'premium';

        $workflow = $this->workflow->when($condition, PremiumAgent::class);
        $this->assertInstanceOf(ConditionalWorkflow::class, $workflow);
    }

    public function test_can_add_condition_with_string()
    {
        $workflow = $this->workflow->when('some_string_condition', TestAgent::class);
        $this->assertInstanceOf(ConditionalWorkflow::class, $workflow);
    }

    public function test_can_add_otherwise_clause()
    {
        $workflow = $this->workflow
            ->when(fn ($input) => false, FirstAgent::class)
            ->otherwise(DefaultAgent::class);

        $this->assertInstanceOf(ConditionalWorkflow::class, $workflow);
    }

    public function test_can_add_else_clause()
    {
        $workflow = $this->workflow
            ->when(fn ($input) => false, FirstAgent::class)
            ->else(DefaultAgent::class);

        $this->assertInstanceOf(ConditionalWorkflow::class, $workflow);
    }

    public function test_when_equals_condition()
    {
        $workflow = $this->workflow->whenEquals('status', 'active', ActiveAgent::class);
        $this->assertInstanceOf(ConditionalWorkflow::class, $workflow);
    }

    public function test_when_greater_than_condition()
    {
        $workflow = $this->workflow->whenGreaterThan('score', 90, HighScoreAgent::class);
        $this->assertInstanceOf(ConditionalWorkflow::class, $workflow);
    }

    public function test_when_less_than_condition()
    {
        $workflow = $this->workflow->whenLessThan('age', 18, MinorAgent::class);
        $this->assertInstanceOf(ConditionalWorkflow::class, $workflow);
    }

    public function test_when_exists_condition()
    {
        $workflow = $this->workflow->whenExists('email', EmailAgent::class);
        $this->assertInstanceOf(ConditionalWorkflow::class, $workflow);
    }

    public function test_when_empty_condition()
    {
        $workflow = $this->workflow->whenEmpty('description', NoDescriptionAgent::class);
        $this->assertInstanceOf(ConditionalWorkflow::class, $workflow);
    }

    public function test_when_matches_condition()
    {
        $workflow = $this->workflow->whenMatches('email', '/^.+@.+\..+$/', ValidEmailAgent::class);
        $this->assertInstanceOf(ConditionalWorkflow::class, $workflow);
    }

    public function test_execute_first_matching_condition()
    {
        Agent::shouldReceive('run')
            ->with('premium_agent', ['type' => 'premium', 'score' => 95], 'test-session')
            ->once()
            ->andReturn('premium_result');

        $result = $this->workflow
            ->when(fn ($input) => $input['type'] === 'premium', PremiumAgent::class)
            ->when(fn ($input) => $input['score'] > 90, HighScoreAgent::class)
            ->otherwise(DefaultAgent::class)
            ->execute(['type' => 'premium', 'score' => 95], $this->context);

        $this->assertIsArray($result);
        $this->assertEquals('premium_result', $result['result']);
        $this->assertEquals(PremiumAgent::class, $result['matched_agent']);
        $this->assertEquals('conditional', $result['workflow_type']);
        $this->assertFalse($result['was_default'] ?? false);
    }

    public function test_execute_otherwise_when_no_conditions_match()
    {
        $this->mockAgentRun('default_result');

        $result = $this->workflow
            ->when(fn ($input) => $input['type'] === 'premium', PremiumAgent::class)
            ->when(fn ($input) => $input['type'] === 'gold', GoldAgent::class)
            ->otherwise(DefaultAgent::class)
            ->execute(['type' => 'basic'], $this->context);

        $this->assertEquals('default_result', $result['result']);
        $this->assertEquals(DefaultAgent::class, $result['matched_agent']);
        $this->assertTrue($result['was_default']);
    }

    public function test_throws_exception_when_no_conditions_match_and_no_default()
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('No conditions matched and no default agent specified');

        $this->workflow
            ->when(fn ($input) => $input['type'] === 'premium', PremiumAgent::class)
            ->execute(['type' => 'basic'], $this->context);
    }

    public function test_when_equals_with_array_input()
    {
        Agent::shouldReceive('run')
            ->with('active_agent', ['status' => 'active'], 'test-session')
            ->once()
            ->andReturn('active_result');

        $result = $this->workflow
            ->whenEquals('status', 'active', ActiveAgent::class)
            ->otherwise(InactiveAgent::class)
            ->execute(['status' => 'active'], $this->context);

        $this->assertEquals(ActiveAgent::class, $result['matched_agent']);
    }

    public function test_when_greater_than_with_array_input()
    {
        Agent::shouldReceive('run')
            ->with('high_score_agent', ['score' => 95], 'test-session')
            ->once()
            ->andReturn('high_score_result');

        $result = $this->workflow
            ->whenGreaterThan('score', 90, HighScoreAgent::class)
            ->otherwise(LowScoreAgent::class)
            ->execute(['score' => 95], $this->context);

        $this->assertEquals(HighScoreAgent::class, $result['matched_agent']);
    }

    public function test_when_less_than_with_array_input()
    {
        Agent::shouldReceive('run')
            ->with('minor_agent', ['age' => 16], 'test-session')
            ->once()
            ->andReturn('minor_result');

        $result = $this->workflow
            ->whenLessThan('age', 18, MinorAgent::class)
            ->otherwise(AdultAgent::class)
            ->execute(['age' => 16], $this->context);

        $this->assertEquals(MinorAgent::class, $result['matched_agent']);
    }

    public function test_when_exists_with_array_input()
    {
        Agent::shouldReceive('run')
            ->with('email_agent', ['email' => 'test@example.com'], 'test-session')
            ->once()
            ->andReturn('email_result');

        $result = $this->workflow
            ->whenExists('email', EmailAgent::class)
            ->otherwise(NoEmailAgent::class)
            ->execute(['email' => 'test@example.com'], $this->context);

        $this->assertEquals(EmailAgent::class, $result['matched_agent']);
    }

    public function test_when_empty_with_array_input()
    {
        Agent::shouldReceive('run')
            ->with('no_description_agent', ['description' => ''], 'test-session')
            ->once()
            ->andReturn('no_description_result');

        $result = $this->workflow
            ->whenEmpty('description', NoDescriptionAgent::class)
            ->otherwise(HasDescriptionAgent::class)
            ->execute(['description' => ''], $this->context);

        $this->assertEquals(NoDescriptionAgent::class, $result['matched_agent']);
    }

    public function test_when_matches_with_valid_email()
    {
        Agent::shouldReceive('run')
            ->with('valid_email_agent', ['email' => 'test@example.com'], 'test-session')
            ->once()
            ->andReturn('valid_email_result');

        $result = $this->workflow
            ->whenMatches('email', '/^.+@.+\..+$/', ValidEmailAgent::class)
            ->otherwise(InvalidEmailAgent::class)
            ->execute(['email' => 'test@example.com'], $this->context);

        $this->assertEquals(ValidEmailAgent::class, $result['matched_agent']);
    }

    public function test_when_matches_with_invalid_email()
    {
        Agent::shouldReceive('run')
            ->with('invalid_email_agent', ['email' => 'invalid-email'], 'test-session')
            ->once()
            ->andReturn('invalid_email_result');

        $result = $this->workflow
            ->whenMatches('email', '/^.+@.+\..+$/', ValidEmailAgent::class)
            ->otherwise(InvalidEmailAgent::class)
            ->execute(['email' => 'invalid-email'], $this->context);

        $this->assertEquals(InvalidEmailAgent::class, $result['matched_agent']);
    }

    public function test_conditions_with_parameters()
    {
        Agent::shouldReceive('run')
            ->with('premium_agent', ['custom' => 'params'], 'test-session')
            ->once()
            ->andReturn('premium_result');

        $result = $this->workflow
            ->when(fn ($input) => $input['type'] === 'premium', PremiumAgent::class, ['custom' => 'params'])
            ->execute(['type' => 'premium'], $this->context);

        $this->assertEquals('premium_result', $result['result']);
    }

    public function test_conditions_with_closure_parameters()
    {
        Agent::shouldReceive('run')
            ->with('premium_agent', 'premium', 'test-session')
            ->once()
            ->andReturn('premium_result');

        $result = $this->workflow
            ->when(
                fn ($input) => $input['type'] === 'premium',
                PremiumAgent::class,
                fn ($input) => $input['type']
            )
            ->execute(['type' => 'premium'], $this->context);

        $this->assertEquals('premium_result', $result['result']);
    }

    public function test_dot_notation_for_nested_arrays()
    {
        Agent::shouldReceive('run')
            ->with('premium_agent', ['user' => ['membership' => ['type' => 'premium']]], 'test-session')
            ->once()
            ->andReturn('premium_result');

        $result = $this->workflow
            ->whenEquals('user.membership.type', 'premium', PremiumAgent::class)
            ->otherwise(DefaultAgent::class)
            ->execute(['user' => ['membership' => ['type' => 'premium']]], $this->context);

        $this->assertEquals(PremiumAgent::class, $result['matched_agent']);
    }

    public function test_scalar_input_with_dot_accessor()
    {
        Agent::shouldReceive('run')
            ->with('string_agent', 'test_string', 'test-session')
            ->once()
            ->andReturn('string_result');

        $result = $this->workflow
            ->whenEquals('.', 'test_string', StringAgent::class)
            ->otherwise(DefaultAgent::class)
            ->execute('test_string', $this->context);

        $this->assertEquals(StringAgent::class, $result['matched_agent']);
    }

    public function test_static_create_method()
    {
        $condition = fn ($input) => $input['type'] === 'premium';
        $workflow = ConditionalWorkflow::create($condition, PremiumAgent::class, DefaultAgent::class);

        $this->assertInstanceOf(ConditionalWorkflow::class, $workflow);
    }

    public function test_static_create_without_else_agent()
    {
        $condition = fn ($input) => $input['type'] === 'premium';
        $workflow = ConditionalWorkflow::create($condition, PremiumAgent::class);

        $this->assertInstanceOf(ConditionalWorkflow::class, $workflow);
    }

    public function test_callbacks_work_with_conditional_execution()
    {
        $successCallbackCalled = false;
        $completeCallbackCalled = false;

        Agent::shouldReceive('run')
            ->with('premium_agent', ['type' => 'premium'], 'test-session')
            ->once()
            ->andReturn('premium_result');

        $this->workflow
            ->when(fn ($input) => $input['type'] === 'premium', PremiumAgent::class)
            ->onSuccess(function ($result) use (&$successCallbackCalled) {
                $successCallbackCalled = true;
            })
            ->onComplete(function ($result, $success) use (&$completeCallbackCalled) {
                $completeCallbackCalled = true;
            })
            ->execute(['type' => 'premium'], $this->context);

        $this->assertTrue($successCallbackCalled);
        $this->assertTrue($completeCallbackCalled);
    }

    public function test_can_reset_workflow()
    {
        $workflow = $this->workflow
            ->when(fn ($input) => true, TestAgent::class)
            ->otherwise(DefaultAgent::class);

        $resetWorkflow = $workflow->reset();

        $this->assertInstanceOf(ConditionalWorkflow::class, $resetWorkflow);
        $this->assertEmpty($resetWorkflow->getResults());
    }
}
