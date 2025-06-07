<?php

use AaronLumsden\LaravelAiADK\Events\AgentResponseGenerated;
use AaronLumsden\LaravelAiADK\Events\AgentExecutionStarting;
use AaronLumsden\LaravelAiADK\Events\AgentExecutionFinished;
use AaronLumsden\LaravelAiADK\Events\TaskDelegated;
use AaronLumsden\LaravelAiADK\System\AgentContext;
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

it('creates task delegated event correctly', function () {
    $parentContext = new AgentContext('parent-session', 'parent input');
    $subAgentContext = new AgentContext('sub-session', 'sub input');
    $parentAgentName = 'parent-agent';
    $subAgentName = 'sub-agent';
    $taskInput = 'Process this data';
    $contextSummary = 'User is asking about data processing';
    $delegationDepth = 2;

    $event = new TaskDelegated(
        $parentContext,
        $subAgentContext,
        $parentAgentName,
        $subAgentName,
        $taskInput,
        $contextSummary,
        $delegationDepth
    );

    expect($event->parentContext)->toBe($parentContext);
    expect($event->subAgentContext)->toBe($subAgentContext);
    expect($event->parentAgentName)->toBe($parentAgentName);
    expect($event->subAgentName)->toBe($subAgentName);
    expect($event->taskInput)->toBe($taskInput);
    expect($event->contextSummary)->toBe($contextSummary);
    expect($event->delegationDepth)->toBe($delegationDepth);
});

it('can dispatch events', function () {
    Event::fake();

    $context = new AgentContext('test-session', 'test input');
    $subAgentContext = new AgentContext('sub-session', 'sub input');

    // Dispatch events
    AgentResponseGenerated::dispatch($context, 'test-agent', 'response');
    AgentExecutionStarting::dispatch($context, 'test-agent', 'test input');
    AgentExecutionFinished::dispatch($context, 'test-agent');
    TaskDelegated::dispatch($context, $subAgentContext, 'parent-agent', 'sub-agent', 'task input', 'context summary', 1);

    // Assert events were dispatched
    Event::assertDispatched(AgentResponseGenerated::class);
    Event::assertDispatched(AgentExecutionStarting::class);
    Event::assertDispatched(AgentExecutionFinished::class);
    Event::assertDispatched(TaskDelegated::class);
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
