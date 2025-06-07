<?php

namespace AaronLumsden\LaravelAgentADK\Tests\Unit\Execution;

use AaronLumsden\LaravelAgentADK\Tests\TestCase;
use AaronLumsden\LaravelAgentADK\Execution\AgentExecutor;
use AaronLumsden\LaravelAgentADK\Services\AgentManager;
use AaronLumsden\LaravelAgentADK\Services\StateManager;
use AaronLumsden\LaravelAgentADK\System\AgentContext;
use Illuminate\Database\Eloquent\Model;
use Mockery;

class AgentExecutorTest extends TestCase
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

    public function test_constructor_sets_properties_correctly()
    {
        $executor = new AgentExecutor('TestAgent', 'test input');

        $this->assertInstanceOf(AgentExecutor::class, $executor);
    }

    public function test_for_user_sets_user_context()
    {
        $user = Mockery::mock(Model::class);
        $user->shouldReceive('getKey')->andReturn(123);
        $user->shouldReceive('toArray')->andReturn(['id' => 123, 'name' => 'John Doe']);

        $executor = new AgentExecutor('TestAgent', 'test input');
        $result = $executor->forUser($user);

        $this->assertSame($executor, $result); // Should return self for chaining
    }

    public function test_with_session_sets_session_id()
    {
        $executor = new AgentExecutor('TestAgent', 'test input');
        $result = $executor->withSession('custom-session-123');

        $this->assertSame($executor, $result); // Should return self for chaining
    }

    public function test_with_context_adds_context_data()
    {
        $executor = new AgentExecutor('TestAgent', 'test input');
        $result = $executor->withContext(['key1' => 'value1', 'key2' => 'value2']);

        $this->assertSame($executor, $result); // Should return self for chaining
    }

    public function test_streaming_enables_streaming()
    {
        $executor = new AgentExecutor('TestAgent', 'test input');
        $result = $executor->streaming(true);

        $this->assertSame($executor, $result); // Should return self for chaining
    }

    public function test_with_parameters_sets_agent_parameters()
    {
        $executor = new AgentExecutor('TestAgent', 'test input');
        $result = $executor->withParameters(['temperature' => 0.8, 'max_tokens' => 1000]);

        $this->assertSame($executor, $result); // Should return self for chaining
    }

    public function test_temperature_sets_temperature_parameter()
    {
        $executor = new AgentExecutor('TestAgent', 'test input');
        $result = $executor->temperature(0.9);

        $this->assertSame($executor, $result); // Should return self for chaining
    }

    public function test_max_tokens_sets_max_tokens_parameter()
    {
        $executor = new AgentExecutor('TestAgent', 'test input');
        $result = $executor->maxTokens(1500);

        $this->assertSame($executor, $result); // Should return self for chaining
    }

    public function test_method_chaining_works()
    {
        $user = Mockery::mock(Model::class);
        $user->shouldReceive('getKey')->andReturn(123);
        $user->shouldReceive('toArray')->andReturn(['id' => 123]);

        $executor = new AgentExecutor('TestAgent', 'test input');
        
        $result = $executor
            ->forUser($user)
            ->withSession('test-session')
            ->withContext(['test' => 'data'])
            ->temperature(0.8)
            ->maxTokens(1000)
            ->streaming(true);

        $this->assertSame($executor, $result);
    }

    public function test_resolve_session_id_with_user()
    {
        $user = Mockery::mock(Model::class);
        $user->shouldReceive('getKey')->andReturn(123);
        $user->shouldReceive('toArray')->andReturn(['id' => 123]);

        $executor = new AgentExecutor('TestAgent', 'test input');
        $executor->forUser($user);

        // Use reflection to test private method
        $reflection = new \ReflectionClass($executor);
        $method = $reflection->getMethod('resolveSessionId');
        $method->setAccessible(true);

        $sessionId = $method->invoke($executor);
        
        $this->assertStringStartsWith('user_123_', $sessionId);
        $this->assertEquals(17, strlen($sessionId)); // user_123_ + 8 random chars
    }

    public function test_resolve_session_id_with_custom_session()
    {
        $executor = new AgentExecutor('TestAgent', 'test input');
        $executor->withSession('custom-session-id');

        // Use reflection to test private method
        $reflection = new \ReflectionClass($executor);
        $method = $reflection->getMethod('resolveSessionId');
        $method->setAccessible(true);

        $sessionId = $method->invoke($executor);
        
        $this->assertEquals('custom-session-id', $sessionId);
    }

    public function test_resolve_session_id_without_user_or_session()
    {
        $executor = new AgentExecutor('TestAgent', 'test input');

        // Use reflection to test private method
        $reflection = new \ReflectionClass($executor);
        $method = $reflection->getMethod('resolveSessionId');
        $method->setAccessible(true);

        $sessionId = $method->invoke($executor);
        
        $this->assertStringStartsWith('session_', $sessionId);
        $this->assertEquals(20, strlen($sessionId)); // session_ + 12 random chars
    }

    public function test_get_agent_name_with_mock_agent()
    {
        // Create a real test agent class with getName method
        $mockAgent = new class {
            public function getName(): string
            {
                return 'test_agent_name';
            }
        };

        // Mock the app container to return our mock agent
        $this->app->instance('TestAgent', $mockAgent);

        $executor = new AgentExecutor('TestAgent', 'test input');

        // Use reflection to test private method
        $reflection = new \ReflectionClass($executor);
        $method = $reflection->getMethod('getAgentName');
        $method->setAccessible(true);

        $agentName = $method->invoke($executor);
        
        $this->assertEquals('test_agent_name', $agentName);
    }

    public function test_execute_calls_agent_manager()
    {
        // Mock the AgentManager
        $mockAgentManager = Mockery::mock(AgentManager::class);
        $mockAgentManager->shouldReceive('run')
            ->once()
            ->with('test', 'test input', Mockery::type('string'))
            ->andReturn('Test response');

        // Mock the StateManager
        $mockStateManager = Mockery::mock(StateManager::class);
        $mockContext = Mockery::mock(AgentContext::class);
        $mockContext->shouldReceive('setState')->zeroOrMoreTimes();
        $mockStateManager->shouldReceive('loadContext')
            ->once()
            ->andReturn($mockContext);

        // Mock an agent
        $mockAgent = Mockery::mock();
        $mockAgent->shouldReceive('getName')->andReturn('test_agent');

        // Bind mocks to the container
        $this->app->instance(AgentManager::class, $mockAgentManager);
        $this->app->instance(StateManager::class, $mockStateManager);
        $this->app->instance('TestAgent', $mockAgent);

        $executor = new AgentExecutor('TestAgent', 'test input');
        $response = $executor->execute();

        $this->assertEquals('Test response', $response);
    }

    public function test_execute_with_user_context()
    {
        $user = new class extends Model {
            public function getKey() { return 123; }
            public function toArray() { return ['id' => 123, 'name' => 'John Doe']; }
            public function email() { return 'john@example.com'; }
            public function name() { return 'John Doe'; }
            public $email = 'john@example.com';
            public $name = 'John Doe';
        };

        // Mock the context
        $mockContext = Mockery::mock(AgentContext::class);
        $mockContext->shouldReceive('setState')->with('execution_mode', 'ask')->once();
        $mockContext->shouldReceive('setState')->with('user_id', 123)->once();
        $mockContext->shouldReceive('setState')->with('user_model', get_class($user))->once();
        $mockContext->shouldReceive('setState')->with('user_data', ['id' => 123, 'name' => 'John Doe'])->once();
        $mockContext->shouldReceive('setState')->with('user_email', 'john@example.com')->once();
        $mockContext->shouldReceive('setState')->with('user_name', 'John Doe')->once();

        // Mock the StateManager
        $mockStateManager = Mockery::mock(StateManager::class);
        $mockStateManager->shouldReceive('loadContext')
            ->once()
            ->andReturn($mockContext);

        // Mock the AgentManager
        $mockAgentManager = Mockery::mock(AgentManager::class);
        $mockAgentManager->shouldReceive('run')
            ->once()
            ->with('test', 'test input', Mockery::type('string'))  // agent name, input, session_id
            ->andReturn('Test response with user context');

        // Mock an agent
        $mockAgent = Mockery::mock();
        $mockAgent->shouldReceive('getName')->andReturn('test_agent');

        // Bind mocks to the container
        $this->app->instance(AgentManager::class, $mockAgentManager);
        $this->app->instance(StateManager::class, $mockStateManager);
        $this->app->instance('TestAgent', $mockAgent);

        $executor = new AgentExecutor('TestAgent', 'test input');
        $response = $executor->forUser($user)->execute();

        $this->assertEquals('Test response with user context', $response);
    }

    public function test_to_string_magic_method()
    {
        // Mock dependencies
        $mockAgentManager = Mockery::mock(AgentManager::class);
        $mockAgentManager->shouldReceive('run')->andReturn('String response');

        $mockStateManager = Mockery::mock(StateManager::class);
        $mockContext = Mockery::mock(AgentContext::class);
        $mockContext->shouldReceive('setState')->zeroOrMoreTimes();
        $mockStateManager->shouldReceive('loadContext')->andReturn($mockContext);

        $mockAgent = Mockery::mock();
        $mockAgent->shouldReceive('getName')->andReturn('test_agent');

        // Bind mocks
        $this->app->instance(AgentManager::class, $mockAgentManager);
        $this->app->instance(StateManager::class, $mockStateManager);
        $this->app->instance('TestAgent', $mockAgent);

        $executor = new AgentExecutor('TestAgent', 'test input');
        $result = (string) $executor;

        $this->assertEquals('String response', $result);
    }

    public function test_invoke_magic_method()
    {
        // Mock dependencies
        $mockAgentManager = Mockery::mock(AgentManager::class);
        $mockAgentManager->shouldReceive('run')->andReturn('Invoked response');

        $mockStateManager = Mockery::mock(StateManager::class);
        $mockContext = Mockery::mock(AgentContext::class);
        $mockContext->shouldReceive('setState')->zeroOrMoreTimes();
        $mockStateManager->shouldReceive('loadContext')->andReturn($mockContext);

        $mockAgent = Mockery::mock();
        $mockAgent->shouldReceive('getName')->andReturn('test_agent');

        // Bind mocks
        $this->app->instance(AgentManager::class, $mockAgentManager);
        $this->app->instance(StateManager::class, $mockStateManager);
        $this->app->instance('TestAgent', $mockAgent);

        $executor = new AgentExecutor('TestAgent', 'test input');
        $result = $executor(); // Invoke as callable

        $this->assertEquals('Invoked response', $result);
    }

    public function test_to_string_handles_exceptions()
    {
        // Mock an agent that throws an exception
        $mockAgent = Mockery::mock();
        $mockAgent->shouldReceive('getName')->andThrow(new \Exception('Test exception'));

        $this->app->instance('TestAgent', $mockAgent);

        $executor = new AgentExecutor('TestAgent', 'test input');
        $result = (string) $executor;

        $this->assertStringContainsString('Error executing agent:', $result);
    }
}