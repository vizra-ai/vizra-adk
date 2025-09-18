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

it('can create agent message', function () {
    $session = AgentSession::create([
        'agent_name' => 'test-agent',
    ]);

    $message = AgentMessage::create([
        'agent_session_id' => $session->id,
        'role' => 'user',
        'content' => 'Hello, world!',
        'turn_uuid' => (string) Str::uuid(),
    ]);

    expect($message)->toBeInstanceOf(AgentMessage::class)
        ->and($message->agent_session_id)->toBe($session->id)
        ->and($message->role)->toBe('user')
        ->and($message->content)->toBe('Hello, world!');
});

it('content is cast to json', function () {
    $session = AgentSession::create([
        'agent_name' => 'test-agent',
    ]);

    $complexContent = [
        'type' => 'function_call',
        'function' => ['name' => 'get_weather', 'arguments' => ['city' => 'London']],
    ];

    $message = AgentMessage::create([
        'agent_session_id' => $session->id,
        'role' => 'tool_call',
        'content' => $complexContent,
        'tool_name' => 'get_weather',
        'turn_uuid' => (string) Str::uuid(),
    ]);

    expect($message->content)->toBeArray()
        ->and($message->content['type'])->toBe('function_call')
        ->and($message->content['function']['name'])->toBe('get_weather');
});

it('can store string content', function () {
    $session = AgentSession::create([
        'agent_name' => 'test-agent',
    ]);

    $message = AgentMessage::create([
        'agent_session_id' => $session->id,
        'role' => 'assistant',
        'content' => 'This is a simple text response',
        'turn_uuid' => (string) Str::uuid(),
    ]);

    expect($message->content)->toBe('This is a simple text response');
});

it('can associate tool name', function () {
    $session = AgentSession::create([
        'agent_name' => 'test-agent',
    ]);

    $message = AgentMessage::create([
        'agent_session_id' => $session->id,
        'role' => 'tool_result',
        'content' => ['result' => 'Weather is sunny'],
        'tool_name' => 'get_weather',
        'turn_uuid' => (string) Str::uuid(),
    ]);

    expect($message->tool_name)->toBe('get_weather')
        ->and($message->role)->toBe('tool_result');
});

it('belongs to session relationship', function () {
    $session = AgentSession::create([
        'agent_name' => 'test-agent',
    ]);

    $message = AgentMessage::create([
        'agent_session_id' => $session->id,
        'role' => 'user',
        'content' => 'Test message',
        'turn_uuid' => (string) Str::uuid(),
    ]);

    expect($message->session)->toBeInstanceOf(AgentSession::class)
        ->and($message->session->id)->toBe($session->id)
        ->and($message->session->agent_name)->toBe('test-agent');
});

it('can store different role types', function () {
    $session = AgentSession::create([
        'agent_name' => 'test-agent',
    ]);

    $roles = ['user', 'assistant', 'tool_call', 'tool_result', 'system'];

    foreach ($roles as $role) {
        $message = AgentMessage::create([
            'agent_session_id' => $session->id,
            'role' => $role,
            'content' => "Content for $role",
            'turn_uuid' => (string) Str::uuid(),
        ]);

        expect($message->role)->toBe($role);
    }
});

it('timestamps are managed', function () {
    $session = AgentSession::create([
        'agent_name' => 'test-agent',
    ]);

    $message = AgentMessage::create([
        'agent_session_id' => $session->id,
        'role' => 'user',
        'content' => 'Test message',
        'turn_uuid' => (string) Str::uuid(),
    ]);

    expect($message->created_at)->not->toBeNull()
        ->and($message->updated_at)->not->toBeNull();
});

it('can query messages by role', function () {
    $session = AgentSession::create([
        'agent_name' => 'test-agent',
    ]);

    AgentMessage::create([
        'agent_session_id' => $session->id,
        'role' => 'user',
        'content' => 'User message 1',
        'turn_uuid' => (string) Str::uuid(),
    ]);

    AgentMessage::create([
        'agent_session_id' => $session->id,
        'role' => 'assistant',
        'content' => 'Assistant response',
        'turn_uuid' => (string) Str::uuid(),
    ]);

    AgentMessage::create([
        'agent_session_id' => $session->id,
        'role' => 'user',
        'content' => 'User message 2',
        'turn_uuid' => (string) Str::uuid(),
    ]);

    $userMessages = AgentMessage::where('role', 'user')->get();
    $assistantMessages = AgentMessage::where('role', 'assistant')->get();

    expect($userMessages)->toHaveCount(2)
        ->and($assistantMessages)->toHaveCount(1);
});

it('can handle null tool name', function () {
    $session = AgentSession::create([
        'agent_name' => 'test-agent',
    ]);

    $message = AgentMessage::create([
        'agent_session_id' => $session->id,
        'role' => 'user',
        'content' => 'Regular message without tool',
        'turn_uuid' => (string) Str::uuid(),
    ]);

    expect($message->tool_name)->toBeNull();
});

it('links assistant variants back to the originating user message', function () {
    $session = AgentSession::create([
        'agent_name' => 'test-agent',
    ]);

    $turnUuid = (string) Str::uuid();

    $userMessage = AgentMessage::create([
        'agent_session_id' => $session->id,
        'role' => 'user',
        'content' => 'User prompt',
        'turn_uuid' => $turnUuid,
    ]);

    $firstVariant = AgentMessage::create([
        'agent_session_id' => $session->id,
        'role' => 'assistant',
        'content' => 'First reply',
        'turn_uuid' => $turnUuid,
        'user_message_id' => $userMessage->id,
        'variant_index' => 0,
    ]);

    $secondVariant = AgentMessage::create([
        'agent_session_id' => $session->id,
        'role' => 'assistant',
        'content' => 'Second reply',
        'turn_uuid' => $turnUuid,
        'user_message_id' => $userMessage->id,
        'variant_index' => 1,
    ]);

    expect($firstVariant->userMessage->id)->toBe($userMessage->id)
        ->and($userMessage->assistantVariants)->toHaveCount(2)
        ->and($userMessage->assistantVariants->first()->id)->toBe($firstVariant->id)
        ->and($userMessage->assistantVariants->last()->id)->toBe($secondVariant->id);
});

it('scopes messages by turn ordered by variant', function () {
    $session = AgentSession::create([
        'agent_name' => 'test-agent',
    ]);

    $turnUuid = (string) Str::uuid();

    $userMessage = AgentMessage::create([
        'agent_session_id' => $session->id,
        'role' => 'user',
        'content' => 'Prompt',
        'turn_uuid' => $turnUuid,
    ]);

    AgentMessage::create([
        'agent_session_id' => $session->id,
        'role' => 'assistant',
        'content' => 'Variant A',
        'turn_uuid' => $turnUuid,
        'user_message_id' => $userMessage->id,
        'variant_index' => 0,
    ]);

    AgentMessage::create([
        'agent_session_id' => $session->id,
        'role' => 'assistant',
        'content' => 'Variant B',
        'turn_uuid' => $turnUuid,
        'user_message_id' => $userMessage->id,
        'variant_index' => 2,
    ]);

    AgentMessage::create([
        'agent_session_id' => $session->id,
        'role' => 'assistant',
        'content' => 'Variant C',
        'turn_uuid' => $turnUuid,
        'user_message_id' => $userMessage->id,
        'variant_index' => 1,
    ]);

    $variants = AgentMessage::forTurn($turnUuid)->get();

    expect($variants)->toHaveCount(4)
        ->and($variants->first()['role'])->toBe('user');

    $assistantVariants = $variants->where('role', 'assistant')->values();

    expect($assistantVariants->pluck('variant_index')->toArray())->toBe([0, 1, 2]);
});

