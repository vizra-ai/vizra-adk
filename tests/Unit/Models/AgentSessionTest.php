<?php

use AaronLumsden\LaravelAgentADK\Models\AgentSession;
use AaronLumsden\LaravelAgentADK\Models\AgentMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Run migrations for testing
    $this->loadMigrationsFrom(__DIR__ . '/../../../database/migrations');
});

it('can create agent session', function () {
    $session = AgentSession::create([
        'agent_name' => 'test-agent',
        'state_data' => ['key' => 'value']
    ]);

    expect($session)->toBeInstanceOf(AgentSession::class);
    expect($session->agent_name)->toBe('test-agent');
    expect($session->state_data)->toBe(['key' => 'value']);
    expect($session->session_id)->not->toBeNull();
});

it('auto generates session id', function () {
    $session = AgentSession::create([
        'agent_name' => 'test-agent'
    ]);

    expect($session->session_id)->not->toBeNull();
    expect(Str::isUuid($session->session_id))->toBeTrue();
});

it('can set custom session id', function () {
    $customId = (string) Str::uuid();

    $session = AgentSession::create([
        'session_id' => $customId,
        'agent_name' => 'test-agent'
    ]);

    expect($session->session_id)->toBe($customId);
});

it('casts state data to array', function () {
    $session = AgentSession::create([
        'agent_name' => 'test-agent',
        'state_data' => ['nested' => ['data' => 'value']]
    ]);

    expect($session->state_data)->toBeArray();
    expect($session->state_data['nested']['data'])->toBe('value');
});

it('can associate user id', function () {
    $session = AgentSession::create([
        'user_id' => 123,
        'agent_name' => 'test-agent'
    ]);

    expect($session->user_id)->toBe(123);
});

it('has many messages relationship', function () {
    $session = AgentSession::create([
        'agent_name' => 'test-agent'
    ]);

    $message = AgentMessage::create([
        'agent_session_id' => $session->id,
        'role' => 'user',
        'content' => 'Hello'
    ]);

    expect($session->messages)->toBeInstanceOf(\Illuminate\Database\Eloquent\Collection::class);
    expect($session->messages)->toHaveCount(1);
    expect($session->messages->first()->content)->toBe('Hello');
});

it('can find session by session id and agent', function () {
    $sessionId = (string) Str::uuid();

    $session = AgentSession::create([
        'session_id' => $sessionId,
        'agent_name' => 'test-agent'
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
        'state_data' => ['initial' => 'data']
    ]);

    $session->update([
        'state_data' => ['updated' => 'data', 'new_key' => 'new_value']
    ]);

    expect($session->fresh()->state_data)->toBe(['updated' => 'data', 'new_key' => 'new_value']);
});

it('manages timestamps', function () {
    $session = AgentSession::create([
        'agent_name' => 'test-agent'
    ]);

    expect($session->created_at)->not->toBeNull();
    expect($session->updated_at)->not->toBeNull();

    $originalUpdatedAt = $session->updated_at;

    // Wait a moment and update
    sleep(1);
    $session->touch();

    expect($session->updated_at->isAfter($originalUpdatedAt))->toBeTrue();
});
