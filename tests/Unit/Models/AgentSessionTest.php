<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Vizra\VizraADK\Models\AgentMessage;
use Vizra\VizraADK\Models\AgentSession;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Run migrations for testing
    $this->loadMigrationsFrom(__DIR__.'/../../../database/migrations');
});

it('can create agent session', function () {
    $session = AgentSession::create([
        'agent_name' => 'test-agent',
        'state_data' => ['key' => 'value'],
    ]);

    expect($session)->toBeInstanceOf(AgentSession::class);
    expect($session->agent_name)->toBe('test-agent');
    expect($session->state_data)->toBe(['key' => 'value']);
    expect($session->session_id)->not->toBeNull();
});

it('auto generates session id', function () {
    $session = AgentSession::create([
        'agent_name' => 'test-agent',
    ]);

    expect($session->session_id)->not->toBeNull();
    expect(Str::isUuid($session->session_id))->toBeTrue();
});

it('can set custom session id', function () {
    $customId = (string) Str::uuid();

    $session = AgentSession::create([
        'session_id' => $customId,
        'agent_name' => 'test-agent',
    ]);

    expect($session->session_id)->toBe($customId);
});

it('casts state data to array', function () {
    $session = AgentSession::create([
        'agent_name' => 'test-agent',
        'state_data' => ['nested' => ['data' => 'value']],
    ]);

    expect($session->state_data)->toBeArray();
    expect($session->state_data['nested']['data'])->toBe('value');
});

it('can associate user identifier', function () {
    $numericSession = AgentSession::create([
        'user_id' => 123,
        'agent_name' => 'test-agent',
    ]);

    expect($numericSession->user_id)->toBe(123);

    $stringSession = AgentSession::create([
        'user_id' => 'user-123',
        'agent_name' => 'test-agent',
    ]);

    expect($stringSession->user_id)->toBe('user-123');
});

it('has many messages relationship', function () {
    $session = AgentSession::create([
        'agent_name' => 'test-agent',
    ]);

    $message = AgentMessage::create([
        'agent_session_id' => $session->id,
        'role' => 'user',
        'content' => 'Hello',
    ]);

    expect($session->messages)->toBeInstanceOf(\Illuminate\Database\Eloquent\Collection::class);
    expect($session->messages)->toHaveCount(1);
    expect($session->messages->first()->content)->toBe('Hello');
});

it('can find session by session id and agent', function () {
    $sessionId = (string) Str::uuid();

    $session = AgentSession::create([
        'session_id' => $sessionId,
        'agent_name' => 'test-agent',
    ]);

    $found = AgentSession::where('session_id', $sessionId)
        ->where('agent_name', 'test-agent')
        ->first();

    expect($found)->not->toBeNull();
    expect($found->id)->toBe($session->id);
});

it('can update state data', function () {
    $session = AgentSession::create([
        'agent_name' => 'test-agent',
        'state_data' => ['initial' => 'data'],
    ]);

    $session->update([
        'state_data' => ['updated' => 'data', 'new_key' => 'new_value'],
    ]);

    expect($session->fresh()->state_data)->toBe(['updated' => 'data', 'new_key' => 'new_value']);
});

it('manages timestamps', function () {
    $session = AgentSession::create([
        'agent_name' => 'test-agent',
    ]);

    expect($session->created_at)->not->toBeNull();
    expect($session->updated_at)->not->toBeNull();

    $originalUpdatedAt = $session->updated_at;

    // Wait a moment and update
    sleep(1);
    $session->touch();

    expect($session->updated_at->isAfter($originalUpdatedAt))->toBeTrue();
});

it('can have memory relationship', function () {
    $memory = \Vizra\VizraADK\Models\AgentMemory::create([
        'agent_name' => 'test-agent',
    ]);

    $session = AgentSession::create([
        'agent_name' => 'test-agent',
        'agent_memory_id' => $memory->id,
    ]);

    expect($session->memory)->toBeInstanceOf(\Vizra\VizraADK\Models\AgentMemory::class);
    expect($session->memory->id)->toBe($memory->id);
    expect($session->memory->agent_name)->toBe('test-agent');
});

it('can have null memory relationship', function () {
    $session = AgentSession::create([
        'agent_name' => 'test-agent',
    ]);

    expect($session->agent_memory_id)->toBeNull();
    expect($session->memory)->toBeNull();
});

it('can get or create memory', function () {
    $session = AgentSession::create([
        'agent_name' => 'memory-test-agent',
    ]);

    // Initially no memory
    expect($session->agent_memory_id)->toBeNull();

    // Should create new memory
    $memory = $session->getOrCreateMemory();

    expect($memory)->toBeInstanceOf(\Vizra\VizraADK\Models\AgentMemory::class);
    expect($memory->agent_name)->toBe('memory-test-agent');

    // Session should now be linked to memory
    $session->refresh();
    expect($session->agent_memory_id)->toBe($memory->id);

    // Calling again should return same memory
    $sameMemory = $session->getOrCreateMemory();
    expect($sameMemory->id)->toBe($memory->id);
});

it('can update memory through session', function () {
    $session = AgentSession::create([
        'agent_name' => 'update-test-agent',
    ]);

    $memory = $session->getOrCreateMemory();

    $session->updateMemory([
        'learnings' => ['User prefers concise answers'],
        'facts' => ['user_type' => 'power_user'],
        'summary' => 'Handles power user queries',
    ]);

    $memory->refresh();

    expect($memory->key_learnings)->toContain('User prefers concise answers');
    expect($memory->memory_data['user_type'])->toBe('power_user');
    expect($memory->memory_summary)->toBe('Handles power user queries');
});

it('can update memory with only learnings', function () {
    $session = AgentSession::create([
        'agent_name' => 'learnings-test-agent',
    ]);

    $memory = $session->getOrCreateMemory();

    $session->updateMemory([
        'learnings' => ['First learning', 'Second learning'],
    ]);

    $memory->refresh();

    expect($memory->key_learnings)->toHaveCount(2);
    expect($memory->key_learnings)->toContain('First learning');
    expect($memory->key_learnings)->toContain('Second learning');
});

it('can update memory with only facts', function () {
    $session = AgentSession::create([
        'agent_name' => 'facts-test-agent',
    ]);

    $memory = $session->getOrCreateMemory();

    $session->updateMemory([
        'facts' => ['preference' => 'email', 'timezone' => 'UTC'],
    ]);

    $memory->refresh();

    expect($memory->memory_data['preference'])->toBe('email');
    expect($memory->memory_data['timezone'])->toBe('UTC');
});

it('can update memory with only summary', function () {
    $session = AgentSession::create([
        'agent_name' => 'summary-test-agent',
    ]);

    $memory = $session->getOrCreateMemory();

    $session->updateMemory([
        'summary' => 'Specialized in technical support',
    ]);

    $memory->refresh();

    expect($memory->memory_summary)->toBe('Specialized in technical support');
});
