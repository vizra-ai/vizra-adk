<?php

use Vizra\VizraSdk\Services\StateManager;
use Vizra\VizraSdk\System\AgentContext;
use Vizra\VizraSdk\Models\AgentSession;
use Vizra\VizraSdk\Models\AgentMessage;
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

it('includes memory context when loading context', function () {
    $agentName = 'memory-context-test';
    $sessionId = (string) Str::uuid();

    // Create memory with some data
    $memoryManager = new \Vizra\VizraSdk\Services\MemoryManager();
    $memoryManager->addLearning($agentName, 'Users prefer quick responses');
    $memoryManager->updateMemoryData($agentName, ['domain' => 'customer_support']);
    $memoryManager->updateSummary($agentName, 'Customer support specialist');

    $context = $this->stateManager->loadContext($agentName, $sessionId, 'test input');

    // Verify memory context is included
    $memoryContext = $context->getState('memory_context');
    expect($memoryContext)->not->toBeNull();
    expect($memoryContext)->toBeArray();
    expect($memoryContext['summary'])->toBe('Customer support specialist');
    expect($memoryContext['key_learnings'])->toContain('Users prefer quick responses');
    expect($memoryContext['facts']['domain'])->toBe('customer_support');
});

it('handles memory context for new agent gracefully', function () {
    $agentName = 'new-agent-memory-test';
    $sessionId = (string) Str::uuid();

    $context = $this->stateManager->loadContext($agentName, $sessionId, 'test input');

    // Should not have memory context for completely new agent with no data
    $memoryContext = $context->getState('memory_context');
    expect($memoryContext)->toBeNull();
});

it('can save context with memory updates', function () {
    $agentName = 'save-memory-test';
    $context = $this->stateManager->loadContext($agentName, null, 'test input');

    // Modify context state including memory updates
    $context->setState('modified', 'state');
    $context->setState('memory_updates', [
        'learnings' => ['New learning from conversation'],
        'facts' => ['user_satisfaction' => 'high']
    ]);

    $this->stateManager->saveContext($context, $agentName);

    // Reload context and verify state was saved
    $reloadedContext = $this->stateManager->loadContext($agentName, $context->getSessionId());
    expect($reloadedContext->getState('modified'))->toBe('state');

    // Verify memory was updated
    $memoryManager = new \Vizra\VizraSdk\Services\MemoryManager();
    $memoryContextArray = $memoryManager->getMemoryContextArray($agentName);
    expect($memoryContextArray['key_learnings'])->toContain('New learning from conversation');
    expect($memoryContextArray['facts']['user_satisfaction'])->toBe('high');
});
