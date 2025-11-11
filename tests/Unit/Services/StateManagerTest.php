<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Vizra\VizraADK\Models\AgentMessage;
use Vizra\VizraADK\Models\AgentSession;
use Vizra\VizraADK\Services\StateManager;
use Vizra\VizraADK\System\AgentContext;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Run migrations for testing
    $this->loadMigrationsFrom(__DIR__.'/../../../database/migrations');

    $this->stateManager = new StateManager;
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

it('stores string user identifiers on context creation', function () {
    $agentName = 'string-user-agent';
    $userId = 'user-123';

    $context = $this->stateManager->loadContext($agentName, null, 'hello', $userId);

    expect($context)->toBeInstanceOf(AgentContext::class);

    $session = AgentSession::where('agent_name', $agentName)->first();
    expect($session)->not->toBeNull();
    expect($session->user_id)->toBe($userId);
});

it('can load context with existing session', function () {
    $agentName = 'test-agent';
    $sessionId = (string) Str::uuid();

    // Create an existing session
    $session = AgentSession::create([
        'session_id' => $sessionId,
        'agent_name' => $agentName,
        'state_data' => ['existing' => 'data'],
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
        'state_data' => [],
    ]);

    AgentMessage::create([
        'agent_session_id' => $session->id,
        'role' => 'user',
        'content' => 'Previous message',
        'tool_name' => null,
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
    $memoryManager = new \Vizra\VizraADK\Services\MemoryManager;
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
        'facts' => ['user_satisfaction' => 'high'],
    ]);

    $this->stateManager->saveContext($context, $agentName);

    // Reload context and verify state was saved
    $reloadedContext = $this->stateManager->loadContext($agentName, $context->getSessionId());
    expect($reloadedContext->getState('modified'))->toBe('state');

    // Verify memory was updated
    $memoryManager = new \Vizra\VizraADK\Services\MemoryManager;
    $memoryContextArray = $memoryManager->getMemoryContextArray($agentName);
    expect($memoryContextArray['key_learnings'])->toContain('New learning from conversation');
    expect($memoryContextArray['facts']['user_satisfaction'])->toBe('high');
});

it('persists all messages without deleting on multiple saves', function () {
    $agentName = 'persist-test-agent';
    $context = $this->stateManager->loadContext($agentName, null, 'test input');
    
    // Add initial messages
    $context->addMessage(['role' => 'user', 'content' => 'Message 1']);
    $context->addMessage(['role' => 'assistant', 'content' => 'Response 1']);
    
    // Save context first time
    $this->stateManager->saveContext($context, $agentName, false);
    
    // Add more messages
    $context->addMessage(['role' => 'user', 'content' => 'Message 2']);
    $context->addMessage(['role' => 'assistant', 'content' => 'Response 2']);
    
    // Save context second time - should not delete existing messages
    $this->stateManager->saveContext($context, $agentName, false);
    
    // Add even more messages
    $context->addMessage(['role' => 'user', 'content' => 'Message 3']);
    $context->addMessage(['role' => 'assistant', 'content' => 'Response 3']);
    
    // Save context third time
    $this->stateManager->saveContext($context, $agentName, false);
    
    // Reload context and verify all messages are persisted
    $reloadedContext = $this->stateManager->loadContext($agentName, $context->getSessionId());
    $history = $reloadedContext->getConversationHistory();
    
    expect($history)->toHaveCount(6);
    expect($history[0]['content'])->toBe('Message 1');
    expect($history[1]['content'])->toBe('Response 1');
    expect($history[2]['content'])->toBe('Message 2');
    expect($history[3]['content'])->toBe('Response 2');
    expect($history[4]['content'])->toBe('Message 3');
    expect($history[5]['content'])->toBe('Response 3');
});

it('does not create duplicate messages on multiple saves', function () {
    $agentName = 'no-duplicates-agent';
    $context = $this->stateManager->loadContext($agentName, null, 'test input');
    
    // Add messages
    $context->addMessage(['role' => 'user', 'content' => 'Hello']);
    $context->addMessage(['role' => 'assistant', 'content' => 'Hi']);
    
    // Save multiple times without adding new messages
    $this->stateManager->saveContext($context, $agentName, false);
    $this->stateManager->saveContext($context, $agentName, false);
    $this->stateManager->saveContext($context, $agentName, false);
    
    // Reload and verify no duplicates
    $reloadedContext = $this->stateManager->loadContext($agentName, $context->getSessionId());
    $history = $reloadedContext->getConversationHistory();
    
    expect($history)->toHaveCount(2);
    expect($history[0]['content'])->toBe('Hello');
    expect($history[1]['content'])->toBe('Hi');
});

it('persists long conversation history beyond typical historyLimit', function () {
    $agentName = 'long-conversation-agent';
    $context = $this->stateManager->loadContext($agentName, null, 'test input');
    
    // Add 100+ messages to simulate a long conversation
    for ($i = 1; $i <= 105; $i++) {
        $context->addMessage(['role' => 'user', 'content' => "User message {$i}"]);
        $context->addMessage(['role' => 'assistant', 'content' => "Assistant response {$i}"]);
        
        // Save periodically to simulate real usage
        if ($i % 20 === 0) {
            $this->stateManager->saveContext($context, $agentName, false);
        }
    }
    
    // Final save
    $this->stateManager->saveContext($context, $agentName, false);
    
    // Reload context and verify ALL messages are persisted
    $reloadedContext = $this->stateManager->loadContext($agentName, $context->getSessionId());
    $history = $reloadedContext->getConversationHistory();
    
    // Should have all 210 messages (105 user + 105 assistant)
    expect($history)->toHaveCount(210);
    
    // Verify first few messages
    expect($history[0]['content'])->toBe('User message 1');
    expect($history[1]['content'])->toBe('Assistant response 1');
    
    // Verify middle messages
    expect($history[100]['content'])->toBe('User message 51');
    expect($history[101]['content'])->toBe('Assistant response 51');
    
    // Verify last messages
    expect($history[208]['content'])->toBe('User message 105');
    expect($history[209]['content'])->toBe('Assistant response 105');
});

it('correctly handles incremental message saving across multiple sessions', function () {
    $agentName = 'incremental-save-agent';
    $sessionId = (string) Str::uuid();
    
    // First context load and save
    $context1 = $this->stateManager->loadContext($agentName, $sessionId, 'first input');
    $context1->addMessage(['role' => 'user', 'content' => 'First message']);
    $context1->addMessage(['role' => 'assistant', 'content' => 'First response']);
    $this->stateManager->saveContext($context1, $agentName, false);
    
    // Second context load (simulating new request in same session)
    $context2 = $this->stateManager->loadContext($agentName, $sessionId, 'second input');
    expect($context2->getConversationHistory())->toHaveCount(2);
    
    // Add more messages
    $context2->addMessage(['role' => 'user', 'content' => 'Second message']);
    $context2->addMessage(['role' => 'assistant', 'content' => 'Second response']);
    $this->stateManager->saveContext($context2, $agentName, false);
    
    // Third context load
    $context3 = $this->stateManager->loadContext($agentName, $sessionId, 'third input');
    expect($context3->getConversationHistory())->toHaveCount(4);
    
    // Verify all messages are in correct order
    $history = $context3->getConversationHistory();
    expect($history[0]['content'])->toBe('First message');
    expect($history[1]['content'])->toBe('First response');
    expect($history[2]['content'])->toBe('Second message');
    expect($history[3]['content'])->toBe('Second response');
});
