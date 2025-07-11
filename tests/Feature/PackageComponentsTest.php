<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Vizra\VizraADK\Agents\BaseAgent;
use Vizra\VizraADK\Models\AgentMessage;
use Vizra\VizraADK\Models\AgentSession;
use Vizra\VizraADK\Services\AgentBuilder;
use Vizra\VizraADK\Services\AgentManager;
use Vizra\VizraADK\Services\AgentRegistry;
use Vizra\VizraADK\Services\StateManager;
use Vizra\VizraADK\System\AgentContext;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Run migrations for testing
    $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');
});

it('can resolve all core services from container', function () {
    // Test that all core services can be resolved from the container
    $registry = app(AgentRegistry::class);
    $manager = app(AgentManager::class);
    $builder = app(AgentBuilder::class);
    $stateManager = app(StateManager::class);

    expect($registry)->toBeInstanceOf(AgentRegistry::class);
    expect($manager)->toBeInstanceOf(AgentManager::class);
    expect($builder)->toBeInstanceOf(AgentBuilder::class);
    expect($stateManager)->toBeInstanceOf(StateManager::class);
});

it('can create and persist models', function () {
    // Test AgentSession model
    $session = AgentSession::create([
        'agent_name' => 'test-agent',
        'state_data' => ['test' => 'data'],
    ]);

    expect($session)->toBeInstanceOf(AgentSession::class);
    $this->assertDatabaseHas('agent_sessions', [
        'agent_name' => 'test-agent',
    ]);

    // Test AgentMessage model
    $message = AgentMessage::create([
        'agent_session_id' => $session->id,
        'role' => 'user',
        'content' => 'Test message',
    ]);

    expect($message)->toBeInstanceOf(AgentMessage::class);
    $this->assertDatabaseHas('agent_messages', [
        'role' => 'user',
        'content' => json_encode('Test message'),
    ]);

    // Test relationships
    expect($session->messages)->toHaveCount(1);
    expect($message->session->id)->toBe($session->id);
});

it('has working agent context system', function () {
    $context = new AgentContext('test-session', 'test input', ['initial' => 'state']);

    // Test basic functionality
    expect($context->getSessionId())->toBe('test-session');
    expect($context->getUserInput())->toBe('test input');
    expect($context->getAllState())->toBe(['initial' => 'state']);

    // Test state management
    $context->setState('new_key', 'new_value');
    expect($context->getState('new_key'))->toBe('new_value');

    // Test history management (using correct method names)
    $context->addMessage(['role' => 'user', 'content' => 'Hello']);
    $context->addMessage(['role' => 'assistant', 'content' => 'Hi there!']);

    expect($context->getConversationHistory())->toHaveCount(2);
    expect($context->getConversationHistory()->first()['role'])->toBe('user');
    expect($context->getConversationHistory()->last()['role'])->toBe('assistant');
});

it('supports complete agent lifecycle', function () {
    $registry = app(AgentRegistry::class);
    $builder = app(AgentBuilder::class);
    $stateManager = app(StateManager::class);

    // 1. Define and register an agent using builder
    $builder
        ->define('lifecycle-test-agent')
        ->description('Agent for testing complete lifecycle')
        ->instructions('You are a test agent.')
        ->register();

    // 2. Verify agent is registered
    expect($registry->hasAgent('lifecycle-test-agent'))->toBeTrue();

    // 3. Create context with state manager
    $context = $stateManager->loadContext('lifecycle-test-agent', null, 'Hello');

    // 4. Save context and add messages
    $stateManager->saveContext($context, 'lifecycle-test-agent');
    $context->addMessage(['role' => 'user', 'content' => 'Hello']);
    $context->addMessage(['role' => 'assistant', 'content' => 'Hi there!']);
    $stateManager->saveContext($context, 'lifecycle-test-agent');

    // 5. Reload and verify persistence
    $reloadedContext = $stateManager->loadContext('lifecycle-test-agent', $context->getSessionId());

    expect($reloadedContext->getSessionId())->toBe($context->getSessionId());
    expect($reloadedContext->getConversationHistory())->toHaveCount(2);
});

it('loads package configuration', function () {
    // Test that package configuration is available
    $config = config('vizra-adk');

    expect($config)->toBeArray();
    expect($config)->toHaveKey('default_provider');
});

it('has facade alias available', function () {
    // Test that the Agent facade alias is properly registered
    expect(class_exists('\Vizra\VizraADK\Facades\Agent'))->toBeTrue();
});

it('registers services properly via service provider', function () {
    // Test that the service provider properly registers all services
    expect(app()->bound(AgentRegistry::class))->toBeTrue();
    expect(app()->bound(AgentManager::class))->toBeTrue();
    expect(app()->bound(AgentBuilder::class))->toBeTrue();
    expect(app()->bound(StateManager::class))->toBeTrue();
});

it('supports multiple agents coexisting', function () {
    $registry = app(AgentRegistry::class);
    $stateManager = app(StateManager::class);

    // Register multiple agents
    $registry->register('agent-1', TestCoexistenceAgent1::class);
    $registry->register('agent-2', TestCoexistenceAgent2::class);

    // Create separate contexts
    $context1 = $stateManager->loadContext('agent-1', null, 'Agent 1 input');
    $context2 = $stateManager->loadContext('agent-2', null, 'Agent 2 input');

    // Execute both agents
    $agent1 = $registry->getAgent('agent-1');
    $agent2 = $registry->getAgent('agent-2');

    $response1 = $agent1->execute('Test input 1', $context1);
    $response2 = $agent2->execute('Test input 2', $context2);

    // Verify independent operation
    expect($response1)->toContain('Agent 1');
    expect($response2)->toContain('Agent 2');
    expect($context1->getSessionId())->not()->toBe($context2->getSessionId());
});

/**
 * Test agents for coexistence testing
 */
class TestCoexistenceAgent1 extends BaseAgent
{
    protected string $name = 'test-coexistence-agent-1';

    protected string $description = 'First test agent for coexistence testing';

    public function execute(mixed $input, AgentContext $context): mixed
    {
        return 'Agent 1 response: '.$input;
    }
}

class TestCoexistenceAgent2 extends BaseAgent
{
    protected string $name = 'test-coexistence-agent-2';

    protected string $description = 'Second test agent for coexistence testing';

    public function execute(mixed $input, AgentContext $context): mixed
    {
        return 'Agent 2 response: '.$input;
    }
}
