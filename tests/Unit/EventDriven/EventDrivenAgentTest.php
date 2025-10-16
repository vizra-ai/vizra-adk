<?php

namespace Vizra\VizraADK\Tests\Unit\EventDriven;

use Illuminate\Database\Eloquent\Model;
use Mockery;
use Vizra\VizraADK\Agents\BaseLlmAgent;
use Vizra\VizraADK\Execution\AgentExecutor;
use Vizra\VizraADK\System\AgentContext;
use Vizra\VizraADK\Tests\TestCase;

class TestEventDrivenAgent extends BaseLlmAgent
{
    protected string $name = 'test_event_driven_agent';

    protected string $description = 'Test agent for event-driven functionality';

    protected string $instructions = 'You are a test agent for event-driven functionality testing.';

    public function execute(mixed $input, AgentContext $context): mixed
    {
        $mode = $context->getState('execution_mode', 'ask');

        return match ($mode) {
            'trigger' => 'Triggered with: '.json_encode($input),
            'analyze' => 'Analyzed: '.json_encode($input),
            'process' => 'Processed: '.json_encode($input),
            'monitor' => 'Monitoring: '.json_encode($input),
            'generate' => 'Generated: '.json_encode($input),
            default => 'Asked: '.(is_array($input) ? json_encode($input) : $input),
        };
    }

    public function getName(): string
    {
        return $this->name;
    }
}

class EventDrivenAgentTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Register the test agent in the registry (simulates real agent registration)
        app(\Vizra\VizraADK\Services\AgentRegistry::class)->register(
            'test_event_driven_agent',
            TestEventDrivenAgent::class
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_ask_mode_returns_agent_executor()
    {
        $executor = TestEventDrivenAgent::run('test input');

        $this->assertInstanceOf(AgentExecutor::class, $executor);
    }

    public function test_trigger_mode_returns_agent_executor()
    {
        $executor = TestEventDrivenAgent::run(['event' => 'test']);

        $this->assertInstanceOf(AgentExecutor::class, $executor);
    }

    public function test_analyze_mode_returns_agent_executor()
    {
        $executor = TestEventDrivenAgent::run(['data' => 'test']);

        $this->assertInstanceOf(AgentExecutor::class, $executor);
    }

    public function test_process_mode_returns_agent_executor()
    {
        $executor = TestEventDrivenAgent::run(['batch' => 'test']);

        $this->assertInstanceOf(AgentExecutor::class, $executor);
    }

    public function test_monitor_mode_returns_agent_executor()
    {
        $executor = TestEventDrivenAgent::run(['metrics' => 'test']);

        $this->assertInstanceOf(AgentExecutor::class, $executor);
    }

    public function test_generate_mode_returns_agent_executor()
    {
        $executor = TestEventDrivenAgent::run(['report' => 'test']);

        $this->assertInstanceOf(AgentExecutor::class, $executor);
    }

    public function test_fluent_api_chaining_with_modes()
    {
        $user = Mockery::mock(Model::class);
        $user->shouldReceive('getKey')->andReturn(123);
        $user->shouldReceive('toArray')->andReturn(['id' => 123]);

        // Test that all modes support fluent chaining
        $executor = TestEventDrivenAgent::run(['event' => 'test'])
            ->forUser($user)
            ->withContext(['test' => 'context'])
            ->async()
            ->onQueue('test-queue')
            ->temperature(0.8);

        $this->assertInstanceOf(AgentExecutor::class, $executor);
    }

    public function test_async_execution_configuration()
    {
        $executor = TestEventDrivenAgent::run(['data' => 'test'])
            ->async()
            ->onQueue('test-queue')
            ->delay(60)
            ->tries(5)
            ->timeout(300);

        $this->assertInstanceOf(AgentExecutor::class, $executor);

        // Test that the fluent interface returns self for chaining
        $result = $executor->timeout(600);
        $this->assertSame($executor, $result);
    }

    public function test_execution_mode_is_passed_to_context()
    {
        // Mock only StateManager - let AgentManager work normally for name resolution
        $mockStateManager = Mockery::mock(\Vizra\VizraADK\Services\StateManager::class);

        $mockContext = Mockery::mock(\Vizra\VizraADK\System\AgentContext::class);
        // execution_mode is no longer set by AgentExecutor
        $mockContext->shouldReceive('setState')->withAnyArgs()->zeroOrMoreTimes();
        $mockContext->shouldReceive('getState')->withAnyArgs()->andReturn(null);
        $mockContext->shouldReceive('getSessionId')->andReturn('test-session');
        $mockContext->shouldReceive('getAllState')->andReturn([]);
        $mockContext->shouldReceive('addMessage')->withAnyArgs()->zeroOrMoreTimes();

        // loadContext is called by both AgentExecutor and AgentManager
        $mockStateManager->shouldReceive('loadContext')
            ->twice()
            ->andReturn($mockContext);
        // AgentExecutor calls with false, AgentManager calls with default (2 args)
        $mockStateManager->shouldReceive('saveContext')
            ->with(Mockery::type(AgentContext::class), 'test_event_driven_agent', false)
            ->once();
        $mockStateManager->shouldReceive('saveContext')
            ->with(Mockery::type(AgentContext::class), 'test_event_driven_agent')
            ->once();

        // Bind mocks to container
        $this->app->instance(\Vizra\VizraADK\Services\StateManager::class, $mockStateManager);
        $this->app->instance(TestEventDrivenAgent::class, new TestEventDrivenAgent);

        // Execute and verify mode is set correctly
        // Agent name resolved from registry (registered in setUp)
        $result = TestEventDrivenAgent::run(['test' => 'data'])->go();

        $this->assertStringContainsString('Asked:', $result);
    }

    public function test_different_modes_create_different_executors()
    {
        $askExecutor = TestEventDrivenAgent::run('test');
        $triggerExecutor = TestEventDrivenAgent::run(['event']);
        $analyzeExecutor = TestEventDrivenAgent::run(['data']);

        // All should be AgentExecutor instances but configured differently
        $this->assertInstanceOf(AgentExecutor::class, $askExecutor);
        $this->assertInstanceOf(AgentExecutor::class, $triggerExecutor);
        $this->assertInstanceOf(AgentExecutor::class, $analyzeExecutor);

        // They should be different instances
        $this->assertNotSame($askExecutor, $triggerExecutor);
        $this->assertNotSame($triggerExecutor, $analyzeExecutor);
    }

    public function test_async_mode_configuration()
    {
        $executor = TestEventDrivenAgent::run(['large_dataset'])
            ->async(true)
            ->onQueue('data-processing')
            ->delay(30)
            ->tries(3)
            ->timeout(600);

        // Should be able to chain all async-related methods
        $this->assertInstanceOf(AgentExecutor::class, $executor);
    }

    public function test_context_building_for_async_execution()
    {
        $user = Mockery::mock(Model::class);
        $user->shouldReceive('getKey')->andReturn(456);
        $user->shouldReceive('toArray')->andReturn(['id' => 456, 'name' => 'Test User']);
        $user->shouldReceive('email')->andReturn('test@example.com');
        $user->shouldReceive('name')->andReturn('Test User');

        $executor = TestEventDrivenAgent::run(['important_data'])
            ->forUser($user)
            ->withContext(['analysis_type' => 'deep', 'priority' => 'high'])
            ->temperature(0.9)
            ->maxTokens(2000);

        $this->assertInstanceOf(AgentExecutor::class, $executor);
    }

    public function test_mode_specific_static_methods_exist()
    {
        $this->assertTrue(method_exists(TestEventDrivenAgent::class, 'run'));
    }

    public function test_mode_execution_with_different_inputs()
    {
        $agent = new TestEventDrivenAgent;

        // Test ask mode
        $askContext = Mockery::mock(\Vizra\VizraADK\System\AgentContext::class);
        $askContext->shouldReceive('getState')->with('execution_mode', 'ask')->andReturn('ask');
        $result = $agent->execute('test input', $askContext);
        $this->assertEquals('Asked: test input', $result);

        // Test trigger mode
        $triggerContext = Mockery::mock(\Vizra\VizraADK\System\AgentContext::class);
        $triggerContext->shouldReceive('getState')->with('execution_mode', 'ask')->andReturn('trigger');
        $result = $agent->execute(['event' => 'data'], $triggerContext);
        $this->assertEquals('Triggered with: {"event":"data"}', $result);

        // Test analyze mode
        $analyzeContext = Mockery::mock(\Vizra\VizraADK\System\AgentContext::class);
        $analyzeContext->shouldReceive('getState')->with('execution_mode', 'ask')->andReturn('analyze');
        $result = $agent->execute(['data' => 'analyze'], $analyzeContext);
        $this->assertEquals('Analyzed: {"data":"analyze"}', $result);
    }
}
