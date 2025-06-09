<?php

use Vizra\VizraSdk\Models\AgentSession;
use Vizra\VizraSdk\Models\AgentMemory;
use Vizra\VizraSdk\Models\AgentMessage;
use Vizra\VizraSdk\Services\MemoryManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->memoryManager = new MemoryManager();
});

it('can create session with memory relationship', function () {
    $agentName = 'integration-test-agent';

    // Create memory first
    $memory = $this->memoryManager->getOrCreateMemory($agentName);

    $session = AgentSession::create([
        'agent_name' => $agentName,
        'agent_memory_id' => $memory->id,
        'state_data' => ['topic' => 'test_conversation']
    ]);

    expect($session->memory)->toBeInstanceOf(AgentMemory::class);
    expect($session->memory->id)->toBe($memory->id);
    expect($session->memory->agent_name)->toBe($agentName);
});

it('can use getOrCreateMemory from session', function () {
    $agentName = 'session-memory-test';

    $session = AgentSession::create([
        'agent_name' => $agentName,
        'state_data' => ['test' => 'data']
    ]);

    // Session should not have memory initially
    expect($session->agent_memory_id)->toBeNull();

    // Get or create memory through session
    $memory = $session->getOrCreateMemory();

    expect($memory)->toBeInstanceOf(AgentMemory::class);
    expect($memory->agent_name)->toBe($agentName);

    // Session should now be linked to memory
    $session->refresh();
    expect($session->agent_memory_id)->toBe($memory->id);

    // Calling again should return same memory
    $sameMemory = $session->getOrCreateMemory();
    expect($sameMemory->id)->toBe($memory->id);
});

it('can update memory from session', function () {
    $agentName = 'session-update-test';

    $session = AgentSession::create([
        'agent_name' => $agentName
    ]);

    $memory = $session->getOrCreateMemory();

    // Update memory with learnings and facts
    $session->updateMemory([
        'learnings' => ['User prefers detailed responses'],
        'facts' => ['user_type' => 'premium_customer'],
        'summary' => 'Handles premium customer support'
    ]);

    $memory->refresh();

    expect($memory->key_learnings)->toContain('User prefers detailed responses');
    expect($memory->memory_data['user_type'])->toBe('premium_customer');
    expect($memory->memory_summary)->toBe('Handles premium customer support');
});

it('memory persists across multiple sessions', function () {
    $agentName = 'persistence-test-agent';

    // First session
    $session1 = AgentSession::create([
        'agent_name' => $agentName
    ]);

    $memory = $session1->getOrCreateMemory();
    $session1->updateMemory([
        'learnings' => ['First session learning'],
        'facts' => ['session_count' => '1']
    ]);

    // Second session should use same memory
    $session2 = AgentSession::create([
        'agent_name' => $agentName
    ]);

    $session2Memory = $session2->getOrCreateMemory();
    expect($session2Memory->id)->toBe($memory->id);

    // Add more data from second session
    $session2->updateMemory([
        'learnings' => ['First session learning', 'Second session learning'],
        'facts' => ['session_count' => '2', 'last_topic' => 'billing']
    ]);

    // Verify accumulated memory
    $memory->refresh();
    expect($memory->key_learnings)->toContain('First session learning');
    expect($memory->key_learnings)->toContain('Second session learning');
    expect($memory->memory_data['session_count'])->toBe('2');
    expect($memory->memory_data['last_topic'])->toBe('billing');

    // Both sessions should reference same memory
    expect($session1->getOrCreateMemory()->id)->toBe($memory->id);
    expect($session2->getOrCreateMemory()->id)->toBe($memory->id);
});

it('can track conversation history across sessions', function () {
    $agentName = 'history-test-agent';

    // Create memory and multiple sessions
    $memory = $this->memoryManager->getOrCreateMemory($agentName);

    $session1 = AgentSession::create([
        'agent_name' => $agentName,
        'agent_memory_id' => $memory->id
    ]);

    $session2 = AgentSession::create([
        'agent_name' => $agentName,
        'agent_memory_id' => $memory->id
    ]);

    // Add messages to first session
    AgentMessage::create([
        'agent_session_id' => $session1->id,
        'role' => 'user',
        'content' => 'Hello from session 1'
    ]);

    AgentMessage::create([
        'agent_session_id' => $session1->id,
        'role' => 'assistant',
        'content' => 'Hi there! How can I help?'
    ]);

    // Add messages to second session
    AgentMessage::create([
        'agent_session_id' => $session2->id,
        'role' => 'user',
        'content' => 'I have a billing question'
    ]);

    AgentMessage::create([
        'agent_session_id' => $session2->id,
        'role' => 'assistant',
        'content' => 'I can help with billing questions'
    ]);

    // Get conversation history through memory
    $history = $this->memoryManager->getConversationHistory($agentName);

    expect($history)->toHaveCount(4);
    expect($history[0]['content'])->toBe('Hello from session 1');
    expect($history[1]['content'])->toBe('Hi there! How can I help?');
    expect($history[2]['content'])->toBe('I have a billing question');
    expect($history[3]['content'])->toBe('I can help with billing questions');
});

it('automatically creates memory when needed', function () {
    $agentName = 'auto-create-test';

    // Create session without explicitly creating memory
    $session = AgentSession::create([
        'agent_name' => $agentName
    ]);

    expect($session->agent_memory_id)->toBeNull();
    expect(AgentMemory::where('agent_name', $agentName)->count())->toBe(0);

    // Memory should be created when first accessed
    $memory = $session->getOrCreateMemory();

    expect($memory)->toBeInstanceOf(AgentMemory::class);
    expect($memory->agent_name)->toBe($agentName);
    expect(AgentMemory::where('agent_name', $agentName)->count())->toBe(1);

    $session->refresh();
    expect($session->agent_memory_id)->toBe($memory->id);
});

it('can handle session cleanup while preserving memory', function () {
    $agentName = 'cleanup-test-agent';

    // Create memory and sessions
    $memory = $this->memoryManager->getOrCreateMemory($agentName);

    $oldSession = AgentSession::create([
        'agent_name' => $agentName,
        'agent_memory_id' => $memory->id
    ]);

    $recentSession = AgentSession::create([
        'agent_name' => $agentName,
        'agent_memory_id' => $memory->id
    ]);

    // Add some memory data
    $memory->update([
        'key_learnings' => ['Important learning to preserve'],
        'memory_data' => ['critical_info' => 'must_keep'],
        'total_sessions' => 2
    ]);

    // Simulate old session
    $oldDate = now()->subDays(40);
    $oldSession->created_at = $oldDate;
    $oldSession->updated_at = $oldDate;
    $oldSession->save();

    // Add messages to both sessions
    AgentMessage::create([
        'agent_session_id' => $oldSession->id,
        'role' => 'user',
        'content' => 'Old message'
    ]);

    AgentMessage::create([
        'agent_session_id' => $recentSession->id,
        'role' => 'user',
        'content' => 'Recent message'
    ]);

    expect(AgentSession::count())->toBe(2);
    expect(AgentMessage::count())->toBe(2);

    // Cleanup old sessions
    $deletedCount = $this->memoryManager->cleanupOldSessions($agentName, 30);

    expect($deletedCount)->toBe(1);
    expect(AgentSession::count())->toBe(1);
    expect(AgentMessage::count())->toBe(1);

    // Memory should still exist with all its data
    $memory->refresh();
    expect($memory)->not->toBeNull();
    expect($memory->key_learnings)->toContain('Important learning to preserve');
    expect($memory->memory_data['critical_info'])->toBe('must_keep');
    expect($memory->total_sessions)->toBe(2); // Should not be affected by cleanup
});

it('supports multiple agents with separate memories', function () {
    $agent1Name = 'customer-support-agent';
    $agent2Name = 'technical-support-agent';

    // Create sessions for different agents
    $session1 = AgentSession::create(['agent_name' => $agent1Name]);
    $session2 = AgentSession::create(['agent_name' => $agent2Name]);

    // Each should get their own memory
    $memory1 = $session1->getOrCreateMemory();
    $memory2 = $session2->getOrCreateMemory();

    expect($memory1->id)->not->toBe($memory2->id);
    expect($memory1->agent_name)->toBe($agent1Name);
    expect($memory2->agent_name)->toBe($agent2Name);

    // Update memories with different data
    $session1->updateMemory([
        'learnings' => ['Customer service specific learning'],
        'facts' => ['domain' => 'customer_service']
    ]);

    $session2->updateMemory([
        'learnings' => ['Technical support specific learning'],
        'facts' => ['domain' => 'technical_support']
    ]);

    // Verify separation
    $memory1->refresh();
    $memory2->refresh();

    expect($memory1->key_learnings)->toContain('Customer service specific learning');
    expect($memory1->key_learnings)->not->toContain('Technical support specific learning');
    expect($memory1->memory_data['domain'])->toBe('customer_service');

    expect($memory2->key_learnings)->toContain('Technical support specific learning');
    expect($memory2->key_learnings)->not->toContain('Customer service specific learning');
    expect($memory2->memory_data['domain'])->toBe('technical_support');
});

it('handles memory updates correctly during session lifecycle', function () {
    $agentName = 'lifecycle-test-agent';

    $session = AgentSession::create([
        'agent_name' => $agentName,
        'state_data' => ['initial' => 'state']
    ]);

    // Start with no memory
    expect($session->agent_memory_id)->toBeNull();

    // First memory access creates it
    $memory = $session->getOrCreateMemory();
    expect($memory->total_sessions)->toBe(0);

    // Update memory during conversation
    $session->updateMemory([
        'learnings' => ['Mid-conversation learning'],
        'facts' => ['conversation_stage' => 'middle']
    ]);

    // Simulate end of session - increment session count
    $this->memoryManager->incrementSessionCount($agentName);

    $memory->refresh();
    expect($memory->total_sessions)->toBe(1);
    expect($memory->key_learnings)->toContain('Mid-conversation learning');
    expect($memory->memory_data['conversation_stage'])->toBe('middle');

    // Create new session - should use existing memory
    $newSession = AgentSession::create([
        'agent_name' => $agentName
    ]);

    $existingMemory = $newSession->getOrCreateMemory();
    expect($existingMemory->id)->toBe($memory->id);
    expect($existingMemory->total_sessions)->toBe(1);
    expect($existingMemory->key_learnings)->toContain('Mid-conversation learning');
});
