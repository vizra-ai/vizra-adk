<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Vizra\VizraADK\Agents\BaseAgent;
use Vizra\VizraADK\Agents\BaseLlmAgent;
use Vizra\VizraADK\Models\AgentMessage;
use Vizra\VizraADK\Models\AgentSession;
use Vizra\VizraADK\Services\AgentBuilder;
use Vizra\VizraADK\Services\AgentManager;
use Vizra\VizraADK\Services\AgentRegistry;
use Vizra\VizraADK\Services\StateManager;
use Vizra\VizraADK\System\AgentContext;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->loadMigrationsFrom(__DIR__.'/../../../database/migrations');

    $this->mockRegistry = Mockery::mock(AgentRegistry::class);
    $this->mockBuilder = Mockery::mock(AgentBuilder::class);
    $this->mockStateManager = Mockery::mock(StateManager::class);

    $this->manager = new AgentManager(
        app(),
        $this->mockRegistry,
        $this->mockBuilder,
        $this->mockStateManager
    );
});

afterEach(function () {
    Mockery::close();
});

it('can build agent from class', function () {
    $agentClass = TestManagerAgent::class;

    $this->mockBuilder
        ->shouldReceive('build')
        ->with($agentClass)
        ->once()
        ->andReturnSelf();

    $result = $this->manager->build($agentClass);

    expect($result)->toBeInstanceOf(AgentBuilder::class);
});

it('can define ad hoc agent', function () {
    $agentName = 'test-agent';

    $this->mockBuilder
        ->shouldReceive('define')
        ->with($agentName)
        ->once()
        ->andReturnSelf();

    $result = $this->manager->define($agentName);

    expect($result)->toBeInstanceOf(AgentBuilder::class);
});

it('can get agent from registry', function () {
    $agentName = 'test-agent';
    $mockAgent = Mockery::mock(BaseAgent::class);

    $this->mockRegistry
        ->shouldReceive('getAgent')
        ->with($agentName)
        ->once()
        ->andReturn($mockAgent);

    $result = $this->manager->named($agentName);

    expect($result)->toBe($mockAgent);
});

it('can run agent', function () {
    $agentName = 'test-agent';
    $input = 'test input';
    $sessionId = 'test-session';

    $mockAgent = Mockery::mock(BaseLlmAgent::class);
    $mockContext = Mockery::mock(AgentContext::class);

    $mockAgent->shouldReceive('execute')
        ->with($input, $mockContext)
        ->once()
        ->andReturn('test response');

    $this->mockRegistry
        ->shouldReceive('getAgent')
        ->with($agentName)
        ->once()
        ->andReturn($mockAgent);

    $this->mockRegistry
        ->shouldReceive('resolveAgentName')
        ->with($agentName)
        ->once()
        ->andReturn($agentName);

    $this->mockStateManager
        ->shouldReceive('loadContext')
        ->with($agentName, $sessionId, $input)
        ->once()
        ->andReturn($mockContext);

    $this->mockStateManager
        ->shouldReceive('saveContext')
        ->with($mockContext, $agentName)
        ->once();

    $result = $this->manager->run($agentName, $input, $sessionId);

    expect($result)->toBe('test response');
});

it('can check if agent is registered', function () {
    $agentName = 'test-agent';

    $this->mockRegistry
        ->shouldReceive('hasAgent')
        ->with($agentName)
        ->once()
        ->andReturn(true);

    $result = $this->manager->hasAgent($agentName);

    expect($result)->toBeTrue();
});

it('can get all registered agents', function () {
    $expectedAgents = ['agent1' => 'config1', 'agent2' => 'config2'];

    $this->mockRegistry
        ->shouldReceive('getAllRegisteredAgents')
        ->once()
        ->andReturn($expectedAgents);

    $result = $this->manager->getAllRegisteredAgents();

    expect($result)->toBe($expectedAgents);
});

it('can set feedback on assistant message', function () {
    $session = AgentSession::create([
        'agent_name' => 'test-agent-feedback',
    ]);

    $message = AgentMessage::create([
        'agent_session_id' => $session->id,
        'role' => 'assistant',
        'content' => 'Assistant reply',
    ]);

    $updated = $this->manager->setMessageFeedback($message->id, 'like');

    expect($updated->feedback)->toBe('like');

    $message->refresh();
    expect($message->feedback)->toBe('like');

    $cleared = $this->manager->setMessageFeedback($message->id, null);
    expect($cleared->feedback)->toBeNull();
});

it('throws when setting feedback on non-assistant message', function () {
    $session = AgentSession::create([
        'agent_name' => 'test-agent-feedback',
    ]);

    $message = AgentMessage::create([
        'agent_session_id' => $session->id,
        'role' => 'user',
        'content' => 'User input',
    ]);

    $this->manager->setMessageFeedback($message->id, 'like');
})->throws(InvalidArgumentException::class, 'Feedback can only be applied to assistant messages.');

it('throws when feedback value is invalid', function () {
    $session = AgentSession::create([
        'agent_name' => 'test-agent-feedback',
    ]);

    $message = AgentMessage::create([
        'agent_session_id' => $session->id,
        'role' => 'assistant',
        'content' => 'Assistant reply',
    ]);

    $this->manager->setMessageFeedback($message->id, 'unsupported');
})->throws(InvalidArgumentException::class, "Feedback must be null, 'like', or 'dislike'. 'unsupported' given.");

/**
 * Test agent for manager testing
 */
class TestManagerAgent extends BaseAgent
{
    protected string $name = 'test-manager-agent';

    protected string $description = 'A test agent for manager testing';

    public function execute(mixed $input, AgentContext $context): mixed
    {
        return 'Manager test response: '.$input;
    }
}
