<?php

use AaronLumsden\LaravelAgentADK\Events\AgentResponseGenerated;
use AaronLumsden\LaravelAgentADK\Events\AgentExecutionStarting;
use AaronLumsden\LaravelAgentADK\Events\AgentExecutionFinished;
use AaronLumsden\LaravelAgentADK\Events\ToolCallCompleted;
use AaronLumsden\LaravelAgentADK\Events\LlmCallInitiating;
use AaronLumsden\LaravelAgentADK\Events\LlmResponseReceived;
use AaronLumsden\LaravelAgentADK\Events\StateUpdated;
use AaronLumsden\LaravelAgentADK\Events\ToolCallInitiating;
use AaronLumsden\LaravelAgentADK\System\AgentContext;
use Illuminate\Support\Facades\Event;

it('creates agent response generated event correctly', function () {
    $context = new AgentContext('test-session', 'test input');
    $agentName = 'test-agent';
    $response = 'Test response';

    $event = new AgentResponseGenerated($context, $agentName, $response);

    expect($event->context)->toBe($context);
    expect($event->agentName)->toBe($agentName);
    expect($event->finalResponse)->toBe($response);
});

it('creates agent execution starting event correctly', function () {
    $context = new AgentContext('test-session', 'test input');
    $agentName = 'test-agent';
    $input = 'test input';

    $event = new AgentExecutionStarting($context, $agentName, $input);

    expect($event->context)->toBe($context);
    expect($event->agentName)->toBe($agentName);
    expect($event->input)->toBe($input);
});

it('creates agent execution finished event correctly', function () {
    $context = new AgentContext('test-session', 'test input');
    $agentName = 'test-agent';

    $event = new AgentExecutionFinished($context, $agentName);

    expect($event->context)->toBe($context);
    expect($event->agentName)->toBe($agentName);
});

it('creates tool call completed event correctly', function () {
    $context = new AgentContext('test-session', 'test input');
    $agentName = 'test-agent';
    $toolName = 'test-tool';
    $result = '{"success": true}';

    $event = new ToolCallCompleted($context, $agentName, $toolName, $result);

    expect($event->context)->toBe($context);
    expect($event->agentName)->toBe($agentName);
    expect($event->toolName)->toBe($toolName);
    expect($event->result)->toBe($result);
});

it('creates LlmCallInitiating event correctly', function () {
    $context = new AgentContext('test-session', 'test input');
    $agentName = 'test-agent';
    $prompt = 'test prompt';

    $event = new LlmCallInitiating($context, $agentName, $prompt);

    expect($event->context)->toBe($context);
    expect($event->agentName)->toBe($agentName);
    expect($event->prompt)->toBe($prompt);
});

it('creates LlmResponseReceived event correctly', function () {
    $context = new AgentContext('test-session', 'test input');
    $agentName = 'test-agent';
    $response = 'test response';

    $event = new LlmResponseReceived($context, $agentName, $response);

    expect($event->context)->toBe($context);
    expect($event->agentName)->toBe($agentName);
    expect($event->response)->toBe($response);
});

it('creates StateUpdated event correctly', function () {
    $context = new AgentContext('test-session', 'test input');
    $key = 'test-key';
    $value = 'test-value';

    $event = new StateUpdated($context, $key, $value);

    expect($event->context)->toBe($context);
    expect($event->key)->toBe($key);
    expect($event->value)->toBe($value);
});

it('creates ToolCallInitiating event correctly', function () {
    $context = new AgentContext('test-session', 'test input');
    $agentName = 'test-agent';
    $toolName = 'test-tool';
    $parameters = ['param1' => 'value1'];

    $event = new ToolCallInitiating($context, $agentName, $toolName, $parameters);

    expect($event->context)->toBe($context);
    expect($event->agentName)->toBe($agentName);
    expect($event->toolName)->toBe($toolName);
    expect($event->parameters)->toBe($parameters);
});

it('can dispatch events', function () {
    Event::fake();

    $context = new AgentContext('test-session', 'test input');

    // Dispatch events
    AgentResponseGenerated::dispatch($context, 'test-agent', 'response');
    AgentExecutionStarting::dispatch($context, 'test-agent', 'test input');
    AgentExecutionFinished::dispatch($context, 'test-agent');
    ToolCallCompleted::dispatch($context, 'test-agent', 'test-tool', '{"success": true}');
    LlmCallInitiating::dispatch($context, 'test-agent', 'test prompt');
    LlmResponseReceived::dispatch($context, 'test-agent', 'test response');
    StateUpdated::dispatch($context, 'test-key', 'test-value');
    ToolCallInitiating::dispatch($context, 'test-agent', 'test-tool', ['param1' => 'value1']);

    // Assert events were dispatched
    Event::assertDispatched(AgentResponseGenerated::class);
    Event::assertDispatched(AgentExecutionStarting::class);
    Event::assertDispatched(AgentExecutionFinished::class);
    Event::assertDispatched(ToolCallCompleted::class);
    Event::assertDispatched(LlmCallInitiating::class);
    Event::assertDispatched(LlmResponseReceived::class);
    Event::assertDispatched(StateUpdated::class);
    Event::assertDispatched(ToolCallInitiating::class);
});

it('contains correct data when dispatched', function () {
    Event::fake();

    $context = new AgentContext('test-session', 'test input');

    AgentResponseGenerated::dispatch($context, 'test-agent', 'test-response');

    Event::assertDispatched(AgentResponseGenerated::class, function ($event) use ($context) {
        return $event->context === $context &&
               $event->agentName === 'test-agent' &&
               $event->finalResponse === 'test-response';
    });
});

it('handles complex response data', function () {
    $context = new AgentContext('test-session', 'complex input');
    $complexResponse = [
        'text' => 'Response text',
        'metadata' => ['tokens' => 150, 'model' => 'gpt-4'],
        'tools_used' => ['weather_tool', 'calculator']
    ];

    $event = new AgentResponseGenerated($context, 'complex-agent', $complexResponse);

    expect($event->finalResponse)->toBeArray();
    expect($event->finalResponse['text'])->toBe('Response text');
    expect($event->finalResponse['metadata']['tokens'])->toBe(150);
    expect($event->finalResponse['tools_used'])->toContain('weather_tool');
});

it('can be serialized', function () {
    $context = new AgentContext('test-session', 'serialization test');
    $event = new AgentResponseGenerated($context, 'serializable-agent', 'serializable response');

    // Test that event can be serialized (important for queued listeners)
    $serialized = serialize($event);
    $unserialized = unserialize($serialized);

    expect($unserialized->agentName)->toBe($event->agentName);
    expect($unserialized->finalResponse)->toBe($event->finalResponse);
    expect($unserialized->context->getSessionId())->toBe($event->context->getSessionId());
});

it('can serialize tool call completed event', function () {
    $context = new AgentContext('test-session', 'serialization test');
    $event = new ToolCallCompleted($context, 'serializable-agent', 'serializable-tool', '{"success": true}');

    $serialized = serialize($event);
    $unserialized = unserialize($serialized);

    expect($unserialized->agentName)->toBe($event->agentName);
    expect($unserialized->toolName)->toBe($event->toolName);
    expect($unserialized->result)->toBe($event->result);
    expect($unserialized->context->getSessionId())->toBe($event->context->getSessionId());
});

it('can dispatch LlmCallInitiating event', function () {
    Event::fake();

    $context = new AgentContext('test-session', 'test input');
    LlmCallInitiating::dispatch($context, 'test-agent', 'test prompt');

    Event::assertDispatched(LlmCallInitiating::class, function ($event) use ($context) {
        return $event->context === $context &&
               $event->agentName === 'test-agent' &&
               $event->prompt === 'test prompt';
    });
});

it('can dispatch LlmResponseReceived event', function () {
    Event::fake();

    $context = new AgentContext('test-session', 'test input');
    LlmResponseReceived::dispatch($context, 'test-agent', 'test response');

    Event::assertDispatched(LlmResponseReceived::class, function ($event) use ($context) {
        return $event->context === $context &&
               $event->agentName === 'test-agent' &&
               $event->response === 'test response';
    });
});

it('can dispatch StateUpdated event', function () {
    Event::fake();

    $context = new AgentContext('test-session', 'test input');
    StateUpdated::dispatch($context, 'test-key', 'test-value');

    Event::assertDispatched(StateUpdated::class, function ($event) use ($context) {
        return $event->context === $context &&
               $event->key === 'test-key' &&
               $event->value === 'test-value';
    });
});

it('can dispatch ToolCallInitiating event', function () {
    Event::fake();

    $context = new AgentContext('test-session', 'test input');
    ToolCallInitiating::dispatch($context, 'test-agent', 'test-tool', ['param1' => 'value1']);

    Event::assertDispatched(ToolCallInitiating::class, function ($event) use ($context) {
        return $event->context === $context &&
               $event->agentName === 'test-agent' &&
               $event->toolName === 'test-tool' &&
               $event->parameters === ['param1' => 'value1'];
    });
});
