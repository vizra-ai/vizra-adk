<?php

namespace Vizra\VizraSdk\Tests\Unit\EventDriven;

use Vizra\VizraSdk\Tests\TestCase;
use Vizra\VizraSdk\Agents\BaseAgent;
use Vizra\VizraSdk\Execution\AgentExecutor;
use Vizra\VizraSdk\System\AgentContext;
use Illuminate\Database\Eloquent\Model;
use Mockery;

class TestEventDrivenAgent extends BaseAgent
{
    protected string $name = 'test_event_driven_agent';
    protected string $description = 'Test agent for event-driven functionality';

    public function run(mixed $input, AgentContext $context): mixed
    {
        $mode = $context->getState('execution_mode', 'ask');
        
        return match($mode) {
            'trigger' => "Triggered with: " . json_encode($input),
            'analyze' => "Analyzed: " . json_encode($input),
            'process' => "Processed: " . json_encode($input),
            'monitor' => "Monitoring: " . json_encode($input),
            'generate' => "Generated: " . json_encode($input),
            default => "Asked: " . (is_array($input) ? json_encode($input) : $input),
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
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_ask_mode_returns_agent_executor()
    {
        $executor = TestEventDrivenAgent::ask('test input');
        
        $this->assertInstanceOf(AgentExecutor::class, $executor);
    }

    public function test_trigger_mode_returns_agent_executor()
    {
        $executor = TestEventDrivenAgent::trigger(['event' => 'test']);
        
        $this->assertInstanceOf(AgentExecutor::class, $executor);
    }

    public function test_analyze_mode_returns_agent_executor()
    {
        $executor = TestEventDrivenAgent::analyze(['data' => 'test']);
        
        $this->assertInstanceOf(AgentExecutor::class, $executor);
    }

    public function test_process_mode_returns_agent_executor()
    {
        $executor = TestEventDrivenAgent::process(['batch' => 'test']);
        
        $this->assertInstanceOf(AgentExecutor::class, $executor);
    }

    public function test_monitor_mode_returns_agent_executor()
    {
        $executor = TestEventDrivenAgent::monitor(['metrics' => 'test']);
        
        $this->assertInstanceOf(AgentExecutor::class, $executor);
    }

    public function test_generate_mode_returns_agent_executor()
    {
        $executor = TestEventDrivenAgent::generate(['report' => 'test']);
        
        $this->assertInstanceOf(AgentExecutor::class, $executor);
    }

    public function test_fluent_api_chaining_with_modes()
    {
        $user = Mockery::mock(Model::class);
        $user->shouldReceive('getKey')->andReturn(123);
        $user->shouldReceive('toArray')->andReturn(['id' => 123]);

        // Test that all modes support fluent chaining
        $executor = TestEventDrivenAgent::trigger(['event' => 'test'])
            ->forUser($user)
            ->withContext(['test' => 'context'])
            ->async()
            ->onQueue('test-queue')
            ->temperature(0.8);

        $this->assertInstanceOf(AgentExecutor::class, $executor);
    }

    public function test_async_execution_configuration()
    {
        $executor = TestEventDrivenAgent::process(['data' => 'test'])
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
        // Mock the dependencies to test mode passing
        $mockAgentManager = Mockery::mock(\Vizra\VizraSdk\Services\AgentManager::class);
        $mockStateManager = Mockery::mock(\Vizra\VizraSdk\Services\StateManager::class);
        
        $mockContext = Mockery::mock(\Vizra\VizraSdk\System\AgentContext::class);
        $mockContext->shouldReceive('setState')->with('execution_mode', 'trigger')->once();
        $mockContext->shouldReceive('setState')->withAnyArgs()->zeroOrMoreTimes();
        
        $mockStateManager->shouldReceive('loadContext')
            ->once()
            ->andReturn($mockContext);
            
        $mockAgentManager->shouldReceive('run')
            ->once()
            ->andReturn('Test response');

        // Bind mocks to container
        $this->app->instance(\Vizra\VizraSdk\Services\AgentManager::class, $mockAgentManager);
        $this->app->instance(\Vizra\VizraSdk\Services\StateManager::class, $mockStateManager);
        $this->app->instance(TestEventDrivenAgent::class, new TestEventDrivenAgent());

        // Execute and verify mode is set correctly
        $result = TestEventDrivenAgent::trigger(['test' => 'data'])->execute();
        
        $this->assertEquals('Test response', $result);
    }

    public function test_different_modes_create_different_executors()
    {
        $askExecutor = TestEventDrivenAgent::ask('test');
        $triggerExecutor = TestEventDrivenAgent::trigger(['event']);
        $analyzeExecutor = TestEventDrivenAgent::analyze(['data']);
        
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
        $executor = TestEventDrivenAgent::process(['large_dataset'])
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

        $executor = TestEventDrivenAgent::analyze(['important_data'])
            ->forUser($user)
            ->withContext(['analysis_type' => 'deep', 'priority' => 'high'])
            ->temperature(0.9)
            ->maxTokens(2000);

        $this->assertInstanceOf(AgentExecutor::class, $executor);
    }

    public function test_mode_specific_static_methods_exist()
    {
        $this->assertTrue(method_exists(TestEventDrivenAgent::class, 'ask'));
        $this->assertTrue(method_exists(TestEventDrivenAgent::class, 'trigger'));
        $this->assertTrue(method_exists(TestEventDrivenAgent::class, 'analyze'));
        $this->assertTrue(method_exists(TestEventDrivenAgent::class, 'process'));
        $this->assertTrue(method_exists(TestEventDrivenAgent::class, 'monitor'));
        $this->assertTrue(method_exists(TestEventDrivenAgent::class, 'generate'));
    }

    public function test_mode_execution_with_different_inputs()
    {
        $agent = new TestEventDrivenAgent();
        
        // Test ask mode
        $askContext = Mockery::mock(\Vizra\VizraSdk\System\AgentContext::class);
        $askContext->shouldReceive('getState')->with('execution_mode', 'ask')->andReturn('ask');
        $result = $agent->run('test input', $askContext);
        $this->assertEquals('Asked: test input', $result);
        
        // Test trigger mode
        $triggerContext = Mockery::mock(\Vizra\VizraSdk\System\AgentContext::class);
        $triggerContext->shouldReceive('getState')->with('execution_mode', 'ask')->andReturn('trigger');
        $result = $agent->run(['event' => 'data'], $triggerContext);
        $this->assertEquals('Triggered with: {"event":"data"}', $result);
        
        // Test analyze mode
        $analyzeContext = Mockery::mock(\Vizra\VizraSdk\System\AgentContext::class);
        $analyzeContext->shouldReceive('getState')->with('execution_mode', 'ask')->andReturn('analyze');
        $result = $agent->run(['data' => 'analyze'], $analyzeContext);
        $this->assertEquals('Analyzed: {"data":"analyze"}', $result);
    }
}