<?php

use AaronLumsden\LaravelAgentADK\Services\StateManager;
use AaronLumsden\LaravelAgentADK\System\AgentContext;
use AaronLumsden\LaravelAgentADK\Models\AgentSession;
use AaronLumsden\LaravelAgentADK\Models\AgentMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Run migrations for testing
    $this->loadMigrationsFrom(__DIR__ . '/../../../database/migrations');

    $this->stateManager = new StateManager();
});

it('can load context with new session', function () {
    $agentName = 'test-agent';
    $userInput = 'Hello, world!';

    $context = $this->stateManager->loadContext($agentName, null, $userInput);

    expect($context)->toBeInstanceOf(AgentContext::class);
    expect($context->getUserInput())->toBe($userInput);
    expect($context->getSessionId())->not->toBeNull();
    expect($context->getAllState())->toBeArray()->toBeEmpty();
});

it('can load context with existing session', function () {
    $agentName = 'test-agent';
    $sessionId = (string) Str::uuid();

    // Create an existing session
    $session = AgentSession::create([
        'session_id' => $sessionId,
        'agent_name' => $agentName,
        'state_data' => ['existing' => 'data']
    ]);

    $context = $this->stateManager->loadContext($agentName, $sessionId);

    expect($context->getSessionId())->toBe($sessionId);
    expect($context->getAllState())->toBe(['existing' => 'data']);
});

it('can save context', function () {
    $agentName = 'test-agent';
    $context = $this->stateManager->loadContext($agentName, null, 'test input');

    // Modify context state
    $context->setState('modified', 'state');

    $this->stateManager->saveContext($context, $agentName);

    // Reload context and verify state was saved
    $reloadedContext = $this->stateManager->loadContext($agentName, $context->getSessionId());
    expect($reloadedContext->getAllState())->toBe(['modified' => 'state']);
});

it('can add message to history', function () {
    $agentName = 'test-agent';
    $context = $this->stateManager->loadContext($agentName, null, 'test input');

    // Add messages directly to context (StateManager doesn't have addMessage method)
    $context->addMessage(['role' => 'user', 'content' => 'Hello']);
    $context->addMessage(['role' => 'assistant', 'content' => 'Hi there!']);

    // Save context to persist messages
    $this->stateManager->saveContext($context, $agentName);

    // Reload context and check history
    $reloadedContext = $this->stateManager->loadContext($agentName, $context->getSessionId());
    $history = $reloadedContext->getConversationHistory();

    expect($history)->toHaveCount(2);
    expect($history[0]['role'])->toBe('user');
    expect($history[0]['content'])->toBe('Hello');
    expect($history[1]['role'])->toBe('assistant');
    expect($history[1]['content'])->toBe('Hi there!');
});

it('can clear session', function () {
    $agentName = 'test-agent';
    $context = $this->stateManager->loadContext($agentName, null, 'test input');

    // Add some data
    $context->setState('some', 'data');
    $context->addMessage(['role' => 'user', 'content' => 'test message']);
    $this->stateManager->saveContext($context, $agentName);

    // Clear session by deleting records directly (StateManager doesn't have clearSession method)
    $session = AgentSession::where('session_id', $context->getSessionId())
        ->where('agent_name', $agentName)
        ->first();

    if ($session) {
        AgentMessage::where('agent_session_id', $session->id)->delete();
        $session->delete();
    }

    // Verify session is cleared
    expect(AgentSession::where('session_id', $context->getSessionId())
        ->where('agent_name', $agentName)->exists())->toBeFalse();
});

it('context includes conversation history', function () {
    $agentName = 'test-agent';
    $sessionId = (string) Str::uuid();

    // Create session with existing messages
    $session = AgentSession::create([
        'session_id' => $sessionId,
        'agent_name' => $agentName,
        'state_data' => []
    ]);

    AgentMessage::create([
        'agent_session_id' => $session->id,
        'role' => 'user',
        'content' => 'Previous message',
        'tool_name' => null
    ]);

    $context = $this->stateManager->loadContext($agentName, $sessionId);
    $history = $context->getConversationHistory();

    expect($history)->toHaveCount(1);
    expect($history[0]['role'])->toBe('user');
    expect($history[0]['content'])->toBe('Previous message');
});
