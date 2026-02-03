<?php

namespace Vizra\VizraADK\Tests\Unit\Jobs;

use Illuminate\Support\Facades\Cache;
use Laravel\SerializableClosure\SerializableClosure;
use Mockery;
use Vizra\VizraADK\Jobs\AgentJob;
use Vizra\VizraADK\Services\AgentManager;
use Vizra\VizraADK\Services\StateManager;
use Vizra\VizraADK\System\AgentContext;
use Vizra\VizraADK\Tests\TestCase;

class AgentJobTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_job_executes_agent_successfully()
    {
        // Mock the AgentManager
        $mockAgentManager = Mockery::mock(AgentManager::class);
        $mockAgentManager->shouldReceive('run')
            ->once()
            ->with('test', 'test input', 'test-session')
            ->andReturn('Test response');

        // Mock the StateManager
        $mockStateManager = Mockery::mock(StateManager::class);
        $mockContext = Mockery::mock(AgentContext::class);
        $mockContext->shouldReceive('setState')->zeroOrMoreTimes();
        $mockStateManager->shouldReceive('loadContext')
            ->once()
            ->andReturn($mockContext);

        // Mock an agent
        $mockAgent = new class {
            public function getName(): string
            {
                return 'test';
            }
        };

        $this->app->instance('TestAgent', $mockAgent);

        $job = new AgentJob('TestAgent', 'test input', 'test-session', []);

        $job->handle($mockAgentManager, $mockStateManager);

        // Check that result was cached
        $this->assertNotNull(Cache::get("agent_job_result:{$job->getJobId()}"));
    }

    public function test_job_executes_on_complete_callback()
    {
        // Use cache to verify callback execution since closure references don't survive serialization
        $cacheKey = 'test_callback_executed_'.uniqid();

        // Create a serialized callback that stores to cache
        $callback = new SerializableClosure(function ($result, $context) use ($cacheKey) {
            Cache::put($cacheKey, [
                'executed' => true,
                'result' => $result,
                'context' => $context,
            ], 60);
        });

        $context = [
            'on_complete' => serialize($callback),
        ];

        // Mock the AgentManager
        $mockAgentManager = Mockery::mock(AgentManager::class);
        $mockAgentManager->shouldReceive('run')
            ->once()
            ->andReturn('Test response');

        // Mock the StateManager
        $mockStateManager = Mockery::mock(StateManager::class);
        $mockContext = Mockery::mock(AgentContext::class);
        $mockContext->shouldReceive('setState')->zeroOrMoreTimes();
        $mockStateManager->shouldReceive('loadContext')
            ->once()
            ->andReturn($mockContext);

        // Mock an agent
        $mockAgent = new class {
            public function getName(): string
            {
                return 'test';
            }
        };

        $this->app->instance('TestAgent', $mockAgent);

        $job = new AgentJob('TestAgent', 'test input', 'test-session', $context);

        $job->handle($mockAgentManager, $mockStateManager);

        // Verify callback was executed by checking cache
        $callbackData = Cache::get($cacheKey);
        $this->assertNotNull($callbackData);
        $this->assertTrue($callbackData['executed']);
        $this->assertEquals('Test response', $callbackData['result']);
        $this->assertIsArray($callbackData['context']);
        $this->assertArrayHasKey('agent', $callbackData['context']);
        $this->assertArrayHasKey('session_id', $callbackData['context']);
        $this->assertArrayHasKey('job_id', $callbackData['context']);
        $this->assertEquals('test-session', $callbackData['context']['session_id']);
    }

    public function test_job_does_not_fail_when_callback_throws_exception()
    {
        // Create a callback that throws an exception
        $callback = new SerializableClosure(function ($result, $context) {
            throw new \Exception('Callback error');
        });

        $context = [
            'on_complete' => serialize($callback),
        ];

        // Mock the AgentManager
        $mockAgentManager = Mockery::mock(AgentManager::class);
        $mockAgentManager->shouldReceive('run')
            ->once()
            ->andReturn('Test response');

        // Mock the StateManager
        $mockStateManager = Mockery::mock(StateManager::class);
        $mockContext = Mockery::mock(AgentContext::class);
        $mockContext->shouldReceive('setState')->zeroOrMoreTimes();
        $mockStateManager->shouldReceive('loadContext')
            ->once()
            ->andReturn($mockContext);

        // Mock an agent
        $mockAgent = new class {
            public function getName(): string
            {
                return 'test';
            }
        };

        $this->app->instance('TestAgent', $mockAgent);

        $job = new AgentJob('TestAgent', 'test input', 'test-session', $context);

        // This should not throw - callback errors are caught and logged
        $job->handle($mockAgentManager, $mockStateManager);

        // Job should still complete and cache the result
        $this->assertNotNull(Cache::get("agent_job_result:{$job->getJobId()}"));
    }

    public function test_job_works_without_callback()
    {
        // Mock the AgentManager
        $mockAgentManager = Mockery::mock(AgentManager::class);
        $mockAgentManager->shouldReceive('run')
            ->once()
            ->andReturn('Test response');

        // Mock the StateManager
        $mockStateManager = Mockery::mock(StateManager::class);
        $mockContext = Mockery::mock(AgentContext::class);
        $mockContext->shouldReceive('setState')->zeroOrMoreTimes();
        $mockStateManager->shouldReceive('loadContext')
            ->once()
            ->andReturn($mockContext);

        // Mock an agent
        $mockAgent = new class {
            public function getName(): string
            {
                return 'test';
            }
        };

        $this->app->instance('TestAgent', $mockAgent);

        // No on_complete in context
        $job = new AgentJob('TestAgent', 'test input', 'test-session', []);

        // Should complete without errors
        $job->handle($mockAgentManager, $mockStateManager);

        $this->assertNotNull(Cache::get("agent_job_result:{$job->getJobId()}"));
    }

    public function test_callback_receives_user_id_from_context()
    {
        // Use cache to verify callback context since closure references don't survive serialization
        $cacheKey = 'test_callback_user_context_'.uniqid();

        $callback = new SerializableClosure(function ($result, $context) use ($cacheKey) {
            Cache::put($cacheKey, $context, 60);
        });

        $context = [
            'on_complete' => serialize($callback),
            'user' => [
                'id' => 123,
                'name' => 'Test User',
                'model' => 'App\\Models\\User',
                'data' => ['id' => 123, 'name' => 'Test User'],
            ],
        ];

        // Mock the AgentManager
        $mockAgentManager = Mockery::mock(AgentManager::class);
        $mockAgentManager->shouldReceive('run')
            ->once()
            ->andReturn('Test response');

        // Mock the StateManager
        $mockStateManager = Mockery::mock(StateManager::class);
        $mockContext = Mockery::mock(AgentContext::class);
        $mockContext->shouldReceive('setState')->zeroOrMoreTimes();
        $mockStateManager->shouldReceive('loadContext')
            ->once()
            ->andReturn($mockContext);

        // Mock an agent
        $mockAgent = new class {
            public function getName(): string
            {
                return 'test';
            }
        };

        $this->app->instance('TestAgent', $mockAgent);

        $job = new AgentJob('TestAgent', 'test input', 'test-session', $context);

        $job->handle($mockAgentManager, $mockStateManager);

        $receivedContext = Cache::get($cacheKey);
        $this->assertNotNull($receivedContext);
        $this->assertEquals(123, $receivedContext['user_id']);
    }
}
