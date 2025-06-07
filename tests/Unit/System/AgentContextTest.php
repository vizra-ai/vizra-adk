<?php

use AaronLumsden\LaravelAiADK\System\AgentContext;
use Illuminate\Support\Collection;

it('can create agent context with minimal data', function () {
    $context = new AgentContext('test-session-id');

    expect($context->getSessionId())->toBe('test-session-id');
    expect($context->getUserInput())->toBeNull();
    expect($context->getAllState())->toBe([]);
    expect($context->getConversationHistory())->toBeInstanceOf(Collection::class);
    expect($context->getConversationHistory()->isEmpty())->toBeTrue();
});

it('can create agent context with full data', function () {
    $sessionId = 'test-session-id';
    $userInput = 'Hello, agent!';
    $initialState = ['key' => 'value', 'counter' => 0];
    $history = new Collection([
        ['role' => 'user', 'content' => 'Previous message'],
        ['role' => 'assistant', 'content' => 'Previous response']
    ]);

    $context = new AgentContext($sessionId, $userInput, $initialState, $history);

    expect($context->getSessionId())->toBe($sessionId);
    expect($context->getUserInput())->toBe($userInput);
    expect($context->getAllState())->toBe($initialState);
    expect($context->getConversationHistory())->toBe($history);
    expect($context->getConversationHistory())->toHaveCount(2);
});

it('can get and set state values', function () {
    $context = new AgentContext('test-session');

    // Test setting individual state values
    $context->setState('initial', 'data');
    expect($context->getState('initial'))->toBe('data');

    // Test getting specific state value with default
    expect($context->getState('initial'))->toBe('data');
    expect($context->getState('non-existent'))->toBeNull();
    expect($context->getState('non-existent', 'default'))->toBe('default');

    // Test setting another state value
    $context->setState('new_key', 'new_value');
    expect($context->getState('new_key'))->toBe('new_value');
    expect($context->getState('initial'))->toBe('data');
});

it('can manage conversation history', function () {
    $context = new AgentContext('test-session');

    // Add messages to history
    $context->addMessage(['role' => 'user', 'content' => 'Hello']);
    $context->addMessage(['role' => 'assistant', 'content' => 'Hi there!']);
    $context->addMessage(['role' => 'user', 'content' => 'How are you?']);

    $history = $context->getConversationHistory();
    expect($history)->toHaveCount(3);
    expect($history[0]['role'])->toBe('user');
    expect($history[0]['content'])->toBe('Hello');
    expect($history[1]['role'])->toBe('assistant');
    expect($history[1]['content'])->toBe('Hi there!');
});

it('can add tool messages to history', function () {
    $context = new AgentContext('test-session');

    $context->addMessage(['role' => 'tool_call', 'content' => ['function' => 'get_weather'], 'tool_name' => 'weather_tool']);
    $context->addMessage(['role' => 'tool_result', 'content' => ['temperature' => '20°C'], 'tool_name' => 'weather_tool']);

    $history = $context->getConversationHistory();
    expect($history)->toHaveCount(2);
    expect($history[0]['role'])->toBe('tool_call');
    expect($history[0]['tool_name'])->toBe('weather_tool');
    expect($history[1]['role'])->toBe('tool_result');
    expect($history[1]['content'])->toBe(['temperature' => '20°C']);
});

it('can clear history by setting empty collection', function () {
    $context = new AgentContext('test-session');

    $context->addMessage(['role' => 'user', 'content' => 'Message 1']);
    $context->addMessage(['role' => 'assistant', 'content' => 'Response 1']);

    expect($context->getConversationHistory())->toHaveCount(2);

    $context->setConversationHistory(new Collection());
    expect($context->getConversationHistory()->isEmpty())->toBeTrue();
});

it('can update user input', function () {
    $context = new AgentContext('test-session', 'initial input');

    expect($context->getUserInput())->toBe('initial input');

    $context->setUserInput('updated input');
    expect($context->getUserInput())->toBe('updated input');
});

it('can handle array user input', function () {
    $arrayInput = ['message' => 'Hello', 'metadata' => ['user_id' => 123]];
    $context = new AgentContext('test-session', $arrayInput);

    expect($context->getUserInput())->toBe($arrayInput);
    expect($context->getUserInput()['message'])->toBe('Hello');
});

it('can merge state using loadState', function () {
    $context = new AgentContext('test-session', null, ['existing' => 'value']);

    $context->loadState(['new' => 'data', 'existing' => 'updated']);

    $state = $context->getAllState();
    expect($state['existing'])->toBe('updated');
    expect($state['new'])->toBe('data');
});

it('can check if state has key using getState with default', function () {
    $context = new AgentContext('test-session', null, ['existing' => 'value']);

    expect($context->getState('existing'))->not->toBeNull();
    expect($context->getState('non-existent'))->toBeNull();
});

it('can get latest user message from history', function () {
    $context = new AgentContext('test-session');

    $context->addMessage(['role' => 'user', 'content' => 'First message']);
    $context->addMessage(['role' => 'assistant', 'content' => 'Response']);
    $context->addMessage(['role' => 'user', 'content' => 'Second message']);

    $history = $context->getConversationHistory();
    $userMessages = $history->filter(fn($message) => $message['role'] === 'user');
    $latestUserMessage = $userMessages->last();

    expect($latestUserMessage['content'])->toBe('Second message');
    expect($latestUserMessage['role'])->toBe('user');
});

it('returns null when no user messages exist in history', function () {
    $context = new AgentContext('test-session');

    $context->addMessage(['role' => 'assistant', 'content' => 'Only assistant message']);

    $history = $context->getConversationHistory();
    $userMessages = $history->filter(fn($message) => $message['role'] === 'user');

    expect($userMessages->isEmpty())->toBeTrue();
});
