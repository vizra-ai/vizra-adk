<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Vizra\VizraADK\Events\MemoryUpdated;
use Vizra\VizraADK\Models\AgentMemory;
use Vizra\VizraADK\Services\MemoryManager;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Run migrations for testing
    $this->loadMigrationsFrom(__DIR__.'/../../../database/migrations');

    $this->memoryManager = new MemoryManager;

    // Fake events for testing
    Event::fake();
});

it('can get or create memory for agent', function () {
    $agentName = 'test-agent';

    // First call should create new memory
    $memory = $this->memoryManager->getOrCreateMemory($agentName);

    expect($memory)->toBeInstanceOf(AgentMemory::class);
    expect($memory->agent_name)->toBe($agentName);
    expect($memory->total_sessions)->toBe(0);
    expect($memory->memory_data)->toBe([]);
    expect($memory->key_learnings)->toBe([]);

    // Second call should return existing memory
    $existingMemory = $this->memoryManager->getOrCreateMemory($agentName);

    expect($existingMemory->id)->toBe($memory->id);
});

it('can add learning to memory', function () {
    $agentName = 'test-agent';
    $learning = 'Users prefer quick responses';

    $this->memoryManager->addLearning($agentName, $learning);

    $memory = AgentMemory::where('agent_name', $agentName)->first();
    expect($memory)->not->toBeNull();
    expect($memory->key_learnings)->toContain($learning);

    // Add another learning
    $secondLearning = 'Complex queries need more context';
    $this->memoryManager->addLearning($agentName, $secondLearning);

    $memory->refresh();
    expect($memory->key_learnings)->toHaveCount(2);
    expect($memory->key_learnings)->toContain($learning);
    expect($memory->key_learnings)->toContain($secondLearning);

    // Check that MemoryUpdated event was fired
    Event::assertDispatched(MemoryUpdated::class, 2);
});

it('can add fact to memory data', function () {
    $agentName = 'test-agent';
    $key = 'user_preference';
    $value = 'email notifications';

    $this->memoryManager->updateMemoryData($agentName, [$key => $value]);

    $memory = AgentMemory::where('agent_name', $agentName)->first();
    expect($memory)->not->toBeNull();
    expect($memory->memory_data)->toHaveKey($key);
    expect($memory->memory_data[$key])->toBe($value);

    // Add another fact
    $this->memoryManager->updateMemoryData($agentName, ['domain' => 'customer_support']);

    $memory->refresh();
    expect($memory->memory_data)->toHaveCount(2);
    expect($memory->memory_data['domain'])->toBe('customer_support');

    // Check that MemoryUpdated event was fired
    Event::assertDispatched(MemoryUpdated::class, 2);
});

it('can update memory summary', function () {
    $agentName = 'test-agent';
    $summary = 'This agent specializes in billing support and has learned that users prefer quick, direct answers.';

    $this->memoryManager->updateSummary($agentName, $summary);

    $memory = AgentMemory::where('agent_name', $agentName)->first();
    expect($memory)->not->toBeNull();
    expect($memory->memory_summary)->toBe($summary);

    // Update with new summary
    $newSummary = 'Updated: This agent now handles both billing and technical support queries.';
    $this->memoryManager->updateSummary($agentName, $newSummary);

    $memory->refresh();
    expect($memory->memory_summary)->toBe($newSummary);

    // Check that MemoryUpdated event was fired
    Event::assertDispatched(MemoryUpdated::class, 2);
});

it('can get memory context for agent', function () {
    $agentName = 'test-agent';

    // Setup memory with data
    $this->memoryManager->addLearning($agentName, 'Users like quick responses');
    $this->memoryManager->updateMemoryData($agentName, ['domain' => 'support']);
    $this->memoryManager->updateSummary($agentName, 'Customer support specialist');

    $context = $this->memoryManager->getMemoryContextArray($agentName);

    expect($context)->toBeArray();
    expect($context)->toHaveKey('summary');
    expect($context)->toHaveKey('key_learnings');
    expect($context)->toHaveKey('facts');
    expect($context)->toHaveKey('total_sessions');

    expect($context['summary'])->toBe('Customer support specialist');
    expect($context['key_learnings'])->toContain('Users like quick responses');
    expect($context['facts']['domain'])->toBe('support');
    expect($context['total_sessions'])->toBeInt();
});

it('returns empty context for non-existent agent', function () {
    $context = $this->memoryManager->getMemoryContextArray('non-existent-agent');

    expect($context)->toBeArray();
    expect($context['summary'])->toBeNull();
    expect($context['key_learnings'])->toBe([]);
    expect($context['facts'])->toBe([]);
    expect($context['total_sessions'])->toBe(0);
});

it('handles memory operations for non-existent agent gracefully', function () {
    $agentName = 'non-existent-agent';

    // These operations should create memory as needed
    $this->memoryManager->addLearning($agentName, 'New learning');
    $this->memoryManager->updateMemoryData($agentName, ['key' => 'value']);
    $this->memoryManager->updateSummary($agentName, 'Summary');

    $memory = AgentMemory::where('agent_name', $agentName)->first();
    expect($memory)->not->toBeNull();
    expect($memory->key_learnings)->toContain('New learning');
    expect($memory->memory_data['key'])->toBe('value');
    expect($memory->memory_summary)->toBe('Summary');
});

it('supports string user identifiers across operations', function () {
    $agentName = 'string-user-agent';
    $userId = 'user-xyz';

    $this->memoryManager->addLearning($agentName, 'Learning for string user', $userId);
    $this->memoryManager->updateMemoryData($agentName, ['preference' => 'dark-mode'], $userId);
    $this->memoryManager->updateSummary($agentName, 'Summary for string user', $userId);

    $memory = AgentMemory::where('agent_name', $agentName)
        ->where('user_id', $userId)
        ->first();

    expect($memory)->not->toBeNull();
    expect($memory->user_id)->toBe($userId);
    expect($memory->key_learnings)->toContain('Learning for string user');
    expect($memory->memory_data['preference'])->toBe('dark-mode');
    expect($memory->memory_summary)->toBe('Summary for string user');

    $context = $this->memoryManager->getMemoryContextArray($agentName, $userId);
    expect($context['key_learnings'])->toContain('Learning for string user');
    expect($context['facts']['preference'])->toBe('dark-mode');
});
