<?php

use AaronLumsden\LaravelAiADK\Models\AgentMessage;
use AaronLumsden\LaravelAiADK\Models\AgentSession;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Run migrations for testing
    $this->loadMigrationsFrom(__DIR__ . '/../../../database/migrations');
});

it('can create agent message', function () {
    $session = AgentSession::create([
        'agent_name' => 'test-agent'
    ]);

    $message = AgentMessage::create([
        'agent_session_id' => $session->id,
        'role' => 'user',
        'content' => 'Hello, world!'
    ]);

    expect($message)->toBeInstanceOf(AgentMessage::class)
        ->and($message->agent_session_id)->toBe($session->id)
        ->and($message->role)->toBe('user')
        ->and($message->content)->toBe('Hello, world!');
});

it('content is cast to json', function () {
    $session = AgentSession::create([
        'agent_name' => 'test-agent'
    ]);

    $complexContent = [
        'type' => 'function_call',
        'function' => ['name' => 'get_weather', 'arguments' => ['city' => 'London']]
    ];

    $message = AgentMessage::create([
        'agent_session_id' => $session->id,
        'role' => 'tool_call',
        'content' => $complexContent,
        'tool_name' => 'get_weather'
    ]);

    expect($message->content)->toBeArray()
        ->and($message->content['type'])->toBe('function_call')
        ->and($message->content['function']['name'])->toBe('get_weather');
});

it('can store string content', function () {
    $session = AgentSession::create([
        'agent_name' => 'test-agent'
    ]);

    $message = AgentMessage::create([
        'agent_session_id' => $session->id,
        'role' => 'assistant',
        'content' => 'This is a simple text response'
    ]);

    expect($message->content)->toBe('This is a simple text response');
});

it('can associate tool name', function () {
    $session = AgentSession::create([
        'agent_name' => 'test-agent'
    ]);

    $message = AgentMessage::create([
        'agent_session_id' => $session->id,
        'role' => 'tool_result',
        'content' => ['result' => 'Weather is sunny'],
        'tool_name' => 'get_weather'
    ]);

    expect($message->tool_name)->toBe('get_weather')
        ->and($message->role)->toBe('tool_result');
});

it('belongs to session relationship', function () {
    $session = AgentSession::create([
        'agent_name' => 'test-agent'
    ]);

    $message = AgentMessage::create([
        'agent_session_id' => $session->id,
        'role' => 'user',
        'content' => 'Test message'
    ]);

    expect($message->session)->toBeInstanceOf(AgentSession::class)
        ->and($message->session->id)->toBe($session->id)
        ->and($message->session->agent_name)->toBe('test-agent');
});

it('can store different role types', function () {
    $session = AgentSession::create([
        'agent_name' => 'test-agent'
    ]);

    $roles = ['user', 'assistant', 'tool_call', 'tool_result', 'system'];

    foreach ($roles as $role) {
        $message = AgentMessage::create([
            'agent_session_id' => $session->id,
            'role' => $role,
            'content' => "Content for $role"
        ]);

        expect($message->role)->toBe($role);
    }
});

it('timestamps are managed', function () {
    $session = AgentSession::create([
        'agent_name' => 'test-agent'
    ]);

    $message = AgentMessage::create([
        'agent_session_id' => $session->id,
        'role' => 'user',
        'content' => 'Test message'
    ]);

    expect($message->created_at)->not->toBeNull()
        ->and($message->updated_at)->not->toBeNull();
});

it('can query messages by role', function () {
    $session = AgentSession::create([
        'agent_name' => 'test-agent'
    ]);

    AgentMessage::create([
        'agent_session_id' => $session->id,
        'role' => 'user',
        'content' => 'User message 1'
    ]);

    AgentMessage::create([
        'agent_session_id' => $session->id,
        'role' => 'assistant',
        'content' => 'Assistant response'
    ]);

    AgentMessage::create([
        'agent_session_id' => $session->id,
        'role' => 'user',
        'content' => 'User message 2'
    ]);

    $userMessages = AgentMessage::where('role', 'user')->get();
    $assistantMessages = AgentMessage::where('role', 'assistant')->get();

    expect($userMessages)->toHaveCount(2)
        ->and($assistantMessages)->toHaveCount(1);
});

it('can handle null tool name', function () {
    $session = AgentSession::create([
        'agent_name' => 'test-agent'
    ]);

    $message = AgentMessage::create([
        'agent_session_id' => $session->id,
        'role' => 'user',
        'content' => 'Regular message without tool'
    ]);

    expect($message->tool_name)->toBeNull();
});
