<?php

use AaronLumsden\LaravelAgentADK\Events\AgentResponseGenerated;
use AaronLumsden\LaravelAgentADK\Events\AgentExecutionStarting;
use AaronLumsden\LaravelAgentADK\Events\AgentExecutionFinished;
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

it('can dispatch events', function () {
    Event::fake();

    $context = new AgentContext('test-session', 'test input');

    // Dispatch events
    AgentResponseGenerated::dispatch($context, 'test-agent', 'response');
    AgentExecutionStarting::dispatch($context, 'test-agent', 'test input');
    AgentExecutionFinished::dispatch($context, 'test-agent');

    // Assert events were dispatched
    Event::assertDispatched(AgentResponseGenerated::class);
    Event::assertDispatched(AgentExecutionStarting::class);
    Event::assertDispatched(AgentExecutionFinished::class);
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
