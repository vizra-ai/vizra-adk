<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Vizra\VizraADK\Models\AgentMemory;
use Vizra\VizraADK\Models\AgentMessage;
use Vizra\VizraADK\Models\AgentSession;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Run migrations for testing
    $this->loadMigrationsFrom(__DIR__.'/../../../database/migrations');
});

it('can create agent memory', function () {
    $memory = AgentMemory::create([
        'agent_name' => 'test-agent',
        'memory_summary' => 'This agent helps with customer support',
        'memory_data' => ['domain' => 'customer_service', 'expertise' => 'billing'],
        'key_learnings' => ['Users often ask about refunds', 'Payment issues are common'],
        'total_sessions' => 0,
    ]);

    expect($memory)->toBeInstanceOf(AgentMemory::class);
    expect($memory->agent_name)->toBe('test-agent');
    expect($memory->memory_summary)->toBe('This agent helps with customer support');
    expect($memory->memory_data)->toBe(['domain' => 'customer_service', 'expertise' => 'billing']);
    expect($memory->key_learnings)->toBe(['Users often ask about refunds', 'Payment issues are common']);
    expect($memory->total_sessions)->toBe(0);
});

it('casts memory data to array', function () {
    $memory = AgentMemory::create([
        'agent_name' => 'test-agent',
        'memory_data' => ['nested' => ['data' => 'value']],
    ]);

    expect($memory->memory_data)->toBeArray();
    expect($memory->memory_data['nested']['data'])->toBe('value');
});

it('casts key learnings to array', function () {
    $memory = AgentMemory::create([
        'agent_name' => 'test-agent',
        'key_learnings' => ['learning 1', 'learning 2', 'learning 3'],
    ]);

    expect($memory->key_learnings)->toBeArray();
    expect($memory->key_learnings)->toHaveCount(3);
    expect($memory->key_learnings[0])->toBe('learning 1');
});

it('has many sessions relationship', function () {
    $memory = AgentMemory::create([
        'agent_name' => 'test-agent',
    ]);

    // Create sessions linked to this memory
    $session1 = AgentSession::create([
        'agent_name' => 'test-agent',
        'agent_memory_id' => $memory->id,
    ]);

    $session2 = AgentSession::create([
        'agent_name' => 'test-agent',
        'agent_memory_id' => $memory->id,
    ]);

    expect($memory->sessions)->toBeInstanceOf(\Illuminate\Database\Eloquent\Collection::class);
    expect($memory->sessions)->toHaveCount(2);
    expect($memory->sessions->pluck('id')->toArray())->toBe([$session1->id, $session2->id]);
});

it('filters sessions by string user identifier', function () {
    $memory = AgentMemory::create([
        'agent_name' => 'test-agent',
        'user_id' => 'user-abc',
    ]);

    $matchingSession = AgentSession::create([
        'agent_name' => 'test-agent',
        'user_id' => 'user-abc',
    ]);

    AgentSession::create([
        'agent_name' => 'test-agent',
        'user_id' => 'user-other',
    ]);

    expect($memory->sessions)->toHaveCount(1);
    expect($memory->sessions->first()->id)->toBe($matchingSession->id);
});

it('can find memory by agent name', function () {
    $memory = AgentMemory::create([
        'agent_name' => 'unique-agent',
    ]);

    $found = AgentMemory::where('agent_name', 'unique-agent')->first();

    expect($found)->not->toBeNull();
    expect($found->id)->toBe($memory->id);
});

it('can update memory data', function () {
    $memory = AgentMemory::create([
        'agent_name' => 'test-agent',
        'memory_data' => ['initial' => 'data'],
    ]);

    $memory->update([
        'memory_data' => ['updated' => 'data', 'new_key' => 'new_value'],
    ]);

    expect($memory->fresh()->memory_data)->toBe(['updated' => 'data', 'new_key' => 'new_value']);
});

it('can update key learnings', function () {
    $memory = AgentMemory::create([
        'agent_name' => 'test-agent',
        'key_learnings' => ['initial learning'],
    ]);

    $memory->update([
        'key_learnings' => ['initial learning', 'new learning', 'another insight'],
    ]);

    $fresh = $memory->fresh();
    expect($fresh->key_learnings)->toHaveCount(3);
    expect($fresh->key_learnings)->toContain('new learning');
    expect($fresh->key_learnings)->toContain('another insight');
});

it('can increment total sessions', function () {
    $memory = AgentMemory::create([
        'agent_name' => 'test-agent',
        'total_sessions' => 5,
    ]);

    $memory->increment('total_sessions');

    expect($memory->fresh()->total_sessions)->toBe(6);

    $memory->increment('total_sessions', 3);

    expect($memory->fresh()->total_sessions)->toBe(9);
});

it('manages timestamps', function () {
    $memory = AgentMemory::create([
        'agent_name' => 'test-agent',
    ]);

    expect($memory->created_at)->not->toBeNull();
    expect($memory->updated_at)->not->toBeNull();

    $originalUpdatedAt = $memory->updated_at;

    // Wait a moment and update
    sleep(1);
    $memory->touch();

    expect($memory->updated_at->isAfter($originalUpdatedAt))->toBeTrue();
});

it('can have null memory summary', function () {
    $memory = AgentMemory::create([
        'agent_name' => 'test-agent',
        'memory_summary' => null,
    ]);

    expect($memory->memory_summary)->toBeNull();
});

it('can have empty memory data', function () {
    $memory = AgentMemory::create([
        'agent_name' => 'test-agent',
        'memory_data' => [],
    ]);

    expect($memory->memory_data)->toBe([]);
});

it('can have empty key learnings', function () {
    $memory = AgentMemory::create([
        'agent_name' => 'test-agent',
        'key_learnings' => [],
    ]);

    expect($memory->key_learnings)->toBe([]);
});

it('defaults total sessions to 0', function () {
    $memory = AgentMemory::create([
        'agent_name' => 'test-agent',
    ]);

    expect($memory->total_sessions)->toBe(0);
});

it('can have associated sessions with messages', function () {
    $memory = AgentMemory::create([
        'agent_name' => 'test-agent',
    ]);

    $session = AgentSession::create([
        'agent_name' => 'test-agent',
        'agent_memory_id' => $memory->id,
    ]);

    $message = AgentMessage::create([
        'agent_session_id' => $session->id,
        'role' => 'user',
        'content' => 'Hello memory!',
    ]);

    expect($memory->sessions->first()->messages->first()->content)->toBe('Hello memory!');
});
