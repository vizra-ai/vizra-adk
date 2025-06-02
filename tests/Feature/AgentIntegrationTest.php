<?php

use AaronLumsden\LaravelAgentADK\Services\AgentRegistry;
use AaronLumsden\LaravelAgentADK\Services\AgentManager;
use AaronLumsden\LaravelAgentADK\Services\StateManager;
use AaronLumsden\LaravelAgentADK\Agents\BaseAgent;
use AaronLumsden\LaravelAgentADK\Agents\BaseLlmAgent;
use AaronLumsden\LaravelAgentADK\System\AgentContext;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Run migrations for testing
    $this->loadMigrationsFrom(__DIR__ . '/../../database/migrations');
});

it('completes agent workflow', function () {
    // Get services from container
    $registry = $this->app->make(AgentRegistry::class);
    $manager = $this->app->make(AgentManager::class);
    $stateManager = $this->app->make(StateManager::class);

    // Register a test agent
    $registry->register('integration-test-agent', IntegrationTestAgent::class);

    // Verify agent is registered
    expect($registry->hasAgent('integration-test-agent'))->toBeTrue();

    // Get the agent instance
    $agent = $registry->getAgent('integration-test-agent');
    expect($agent)->toBeInstanceOf(IntegrationTestAgent::class);

    // Create a context and execute via manager (which handles saving)
    $response = $manager->run('integration-test-agent', 'Hello from integration test');

    expect($response)->toBeString();
    expect($response)->toContain('Integration response');

    // Since the manager created a session, we need to get the session ID from the agent
    // Let's test by creating a new execution with the same agent and verify persistence works
    $sessionId = 'test-session-' . uniqid();
    $context = $stateManager->loadContext('integration-test-agent', $sessionId, 'Hello from integration test');

    // Execute the agent directly to add messages
    $agent->run('Hello from integration test', $context);

    // Save context manually when calling agent directly
    $stateManager->saveContext($context, 'integration-test-agent');

    // Reload context and verify data persisted
    $reloadedContext = $stateManager->loadContext('integration-test-agent', $context->getSessionId());
    $history = $reloadedContext->getConversationHistory();

    expect($history)->toHaveCount(2); // User message + assistant response
});

it('executes agent via manager', function () {
    $registry = $this->app->make(AgentRegistry::class);
    $manager = $this->app->make(AgentManager::class);

    // Register agent through registry
    $registry->register('manager-test-agent', IntegrationTestAgent::class);

    // Execute via manager run method
    $response = $manager->run('manager-test-agent', 'Manager test input');

    expect($response)->toBeString();
    expect($response)->toContain('Integration response');
    expect($response)->toContain('Manager test input');
});

it('persists agent state', function () {
    $registry = $this->app->make(AgentRegistry::class);
    $stateManager = $this->app->make(StateManager::class);

    $registry->register('stateful-agent', StatefulTestAgent::class);
    $agent = $registry->getAgent('stateful-agent');

    // First interaction
    $context1 = $stateManager->loadContext('stateful-agent', null, 'Set counter to 5');
    $response1 = $agent->run('Set counter to 5', $context1);
    $stateManager->saveContext($context1, 'stateful-agent');

    // Second interaction with same session
    $context2 = $stateManager->loadContext('stateful-agent', $context1->getSessionId(), 'Increment counter');
    $response2 = $agent->run('Increment counter', $context2);
    $stateManager->saveContext($context2, 'stateful-agent');

    expect($context1->getState('counter'))->toBe(5);
    expect($context2->getState('counter'))->toBe(6);
});

it('handles multiple agents with different sessions', function () {
    $registry = $this->app->make(AgentRegistry::class);
    $stateManager = $this->app->make(StateManager::class);

    // Register two different agents
    $registry->register('agent-1', IntegrationTestAgent::class);
    $registry->register('agent-2', StatefulTestAgent::class);

    $agent1 = $registry->getAgent('agent-1');
    $agent2 = $registry->getAgent('agent-2');

    // Create separate contexts
    $context1 = $stateManager->loadContext('agent-1', null, 'Agent 1 input');
    $context2 = $stateManager->loadContext('agent-2', null, 'Agent 2 input');

    $response1 = $agent1->run('Agent 1 input', $context1);
    $response2 = $agent2->run('Agent 2 input', $context2);

    expect($response1)->toContain('Integration response');
    expect($response2)->toContain('Stateful response');

    // Verify sessions are independent
    expect($context1->getSessionId())->not->toBe($context2->getSessionId());
});

it('integrates with facade', function () {
    $registry = $this->app->make(AgentRegistry::class);
    $registry->register('facade-test-agent', IntegrationTestAgent::class);

    // Test using the Agent facade if available
    if (class_exists('\AaronLumsden\LaravelAgentADK\Facades\Agent')) {
        $response = \Agent::run('facade-test-agent', 'Facade test input');
        expect($response)->toBeString();
        expect($response)->toContain('Integration response');
    } else {
        // If facade is not available, mark test as skipped
        $this->markTestSkipped('Agent facade not available in test environment');
    }
});

/**
 * Test agent for integration testing
 */
class IntegrationTestAgent extends BaseLlmAgent
{
    protected string $name = 'integration-test-agent';
    protected string $description = 'An agent for integration testing';
    protected string $instructions = 'You are a test agent for integration testing.';

    public function run($input, AgentContext $context): mixed
    {
        // For testing, simulate the LLM response behavior manually
        $context->setUserInput($input);
        $context->addMessage(['role' => 'user', 'content' => $input ?: '']);

        $response = 'Integration response for: ' . $input . ' (Session: ' . $context->getSessionId() . ')';

        $context->addMessage([
            'role' => 'assistant',
            'content' => $response
        ]);

        return $response;
    }
}

/**
 * Stateful test agent for testing state persistence
 */
class StatefulTestAgent extends BaseLlmAgent
{
    protected string $name = 'stateful-test-agent';
    protected string $description = 'A stateful agent for testing state management';
    protected string $instructions = 'You are a stateful test agent.';

    public function run($input, AgentContext $context): mixed
    {
        // Simulate adding the user message like BaseLlmAgent would
        $context->setUserInput($input);
        $context->addMessage(['role' => 'user', 'content' => $input ?: '']);

        $counter = $context->getState('counter', 0);

        if (str_contains(strtolower($input), 'set counter to')) {
            preg_match('/set counter to (\d+)/i', $input, $matches);
            if (isset($matches[1])) {
                $counter = (int) $matches[1];
                $context->setState('counter', $counter);
            }
        } elseif (str_contains(strtolower($input), 'increment')) {
            $counter++;
            $context->setState('counter', $counter);
        }

        $response = "Stateful response. Counter is now: $counter";

        // Add assistant response to conversation history
        $context->addMessage([
            'role' => 'assistant',
            'content' => $response
        ]);

        return $response;
    }
}
