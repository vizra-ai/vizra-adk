<?php

use Vizra\VizraSdk\Events\TaskDelegated;
use Vizra\VizraSdk\System\AgentContext;
use Vizra\VizraSdk\Tools\DelegateToSubAgentTool;
use Vizra\VizraSdk\Agents\BaseLlmAgent;
use Illuminate\Support\Facades\Event;

describe('TaskDelegated Event', function () {
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

    it('can be serialized for queued listeners', function () {
        $parentContext = new AgentContext('parent-session', 'parent input');
        $subAgentContext = new AgentContext('sub-session', 'sub input');

        $event = new TaskDelegated(
            $parentContext,
            $subAgentContext,
            'parent-agent',
            'sub-agent',
            'task input',
            'context summary',
            1
        );

        // Test that event can be serialized (important for queued listeners)
        $serialized = serialize($event);
        $unserialized = unserialize($serialized);

        expect($unserialized->parentAgentName)->toBe($event->parentAgentName);
        expect($unserialized->subAgentName)->toBe($event->subAgentName);
        expect($unserialized->taskInput)->toBe($event->taskInput);
        expect($unserialized->contextSummary)->toBe($event->contextSummary);
        expect($unserialized->delegationDepth)->toBe($event->delegationDepth);
    });
});

describe('TaskDelegated Event Integration', function () {
    beforeEach(function () {
        Event::fake();
    });

    it('dispatches task delegated event when delegation occurs', function () {
        // Create a mock sub-agent
        $subAgent = $this->mock(BaseLlmAgent::class, function ($mock) {
            $mock->shouldReceive('run')->andReturn('Sub-agent response');
        });

        // Create a mock parent agent
        $parentAgent = $this->mock(BaseLlmAgent::class, function ($mock) use ($subAgent) {
            $mock->shouldReceive('getName')->andReturn('parent-agent');
            $mock->shouldReceive('getLoadedSubAgents')->andReturn(['sub-agent' => $subAgent]);
            $mock->shouldReceive('getSubAgent')->with('sub-agent')->andReturn($subAgent);
            $mock->shouldReceive('beforeSubAgentDelegation')->andReturnUsing(function ($subAgentName, $taskInput, $contextSummary, $context) {
                return [$subAgentName, $taskInput, $contextSummary];
            });
            $mock->shouldReceive('afterSubAgentDelegation')->andReturnUsing(function ($subAgentName, $taskInput, $result, $context, $subAgentContext) {
                return $result;
            });
        });

        $tool = new DelegateToSubAgentTool($parentAgent);
        $context = new AgentContext('test-session', 'test input');

        $arguments = [
            'sub_agent_name' => 'sub-agent',
            'task_input' => 'Process this data',
            'context_summary' => 'User is asking about data processing'
        ];

        // Execute the tool
        $result = $tool->execute($arguments, $context);

        // Verify the event was dispatched
        Event::assertDispatched(TaskDelegated::class, function ($event) use ($context) {
            return $event->parentContext === $context &&
                   $event->parentAgentName === 'parent-agent' &&
                   $event->subAgentName === 'sub-agent' &&
                   $event->taskInput === 'Process this data' &&
                   $event->contextSummary === 'User is asking about data processing' &&
                   $event->delegationDepth === 1;
        });

        // Verify the result is successful
        $decodedResult = json_decode($result, true);
        expect($decodedResult['success'])->toBe(true);
        expect($decodedResult['sub_agent'])->toBe('sub-agent');
    });

    it('includes correct delegation depth in the event', function () {
        // Create a mock sub-agent
        $subAgent = $this->mock(BaseLlmAgent::class, function ($mock) {
            $mock->shouldReceive('run')->andReturn('Response');
        });

        // Create a mock parent agent
        $parentAgent = $this->mock(BaseLlmAgent::class, function ($mock) use ($subAgent) {
            $mock->shouldReceive('getName')->andReturn('parent-agent');
            $mock->shouldReceive('getLoadedSubAgents')->andReturn(['sub-agent' => $subAgent]);
            $mock->shouldReceive('getSubAgent')->with('sub-agent')->andReturn($subAgent);
            $mock->shouldReceive('beforeSubAgentDelegation')->andReturnUsing(function ($subAgentName, $taskInput, $contextSummary, $context) {
                return [$subAgentName, $taskInput, $contextSummary];
            });
            $mock->shouldReceive('afterSubAgentDelegation')->andReturnUsing(function ($subAgentName, $taskInput, $result, $context, $subAgentContext) {
                return $result;
            });
        });

        $tool = new DelegateToSubAgentTool($parentAgent);
        $context = new AgentContext('test-session', 'test input');

        // Set initial delegation depth
        $context->setState('delegation_depth', 2);

        $arguments = [
            'sub_agent_name' => 'sub-agent',
            'task_input' => 'Task input',
        ];

        $tool->execute($arguments, $context);

        // Verify the event was dispatched with correct depth
        Event::assertDispatched(TaskDelegated::class, function ($event) {
            return $event->delegationDepth === 3; // Should be incremented from 2 to 3
        });
    });

    it('does not dispatch event when delegation fails', function () {
        $parentAgent = $this->mock(BaseLlmAgent::class, function ($mock) {
            $mock->shouldReceive('getName')->andReturn('parent-agent');
            $mock->shouldReceive('getLoadedSubAgents')->andReturn([]);
            $mock->shouldReceive('getSubAgent')->with('non-existent-agent')->andReturn(null);
            $mock->shouldReceive('beforeSubAgentDelegation')->andReturnUsing(function ($subAgentName, $taskInput, $contextSummary, $context) {
                return [$subAgentName, $taskInput, $contextSummary];
            });
        });

        $tool = new DelegateToSubAgentTool($parentAgent);
        $context = new AgentContext('test-session', 'test input');

        $arguments = [
            'sub_agent_name' => 'non-existent-agent',
            'task_input' => 'Task input',
        ];

        $result = $tool->execute($arguments, $context);

        // Verify the event was NOT dispatched for failed delegation
        Event::assertNotDispatched(TaskDelegated::class);

        // Verify the result shows error
        $decodedResult = json_decode($result, true);
        expect($decodedResult)->toHaveKey('success');
        expect($decodedResult['success'])->toBe(false);
        expect($decodedResult['error'])->toContain('not found');
    });

    it('does not dispatch event when delegation depth limit is reached', function () {
        $parentAgent = $this->mock(BaseLlmAgent::class, function ($mock) {
            $mock->shouldReceive('getName')->andReturn('parent-agent');
        });

        $tool = new DelegateToSubAgentTool($parentAgent);
        $context = new AgentContext('test-session', 'test input');

        // Set delegation depth at the limit
        $context->setState('delegation_depth', 5);

        $arguments = [
            'sub_agent_name' => 'sub-agent',
            'task_input' => 'Task input',
        ];

        $result = $tool->execute($arguments, $context);

        // Verify the event was NOT dispatched when depth limit is reached
        Event::assertNotDispatched(TaskDelegated::class);

        // Verify the result shows depth limit error
        $decodedResult = json_decode($result, true);
        expect($decodedResult['success'])->toBe(false);
        expect($decodedResult['error'])->toContain('Maximum delegation depth');
    });

    it('creates correct sub-agent context in the event', function () {
        // Create a mock sub-agent
        $subAgent = $this->mock(BaseLlmAgent::class, function ($mock) {
            $mock->shouldReceive('run')->andReturn('Response');
        });

        $parentAgent = $this->mock(BaseLlmAgent::class, function ($mock) use ($subAgent) {
            $mock->shouldReceive('getName')->andReturn('parent-agent');
            $mock->shouldReceive('getLoadedSubAgents')->andReturn(['sub-agent' => $subAgent]);
            $mock->shouldReceive('getSubAgent')->with('sub-agent')->andReturn($subAgent);
            $mock->shouldReceive('beforeSubAgentDelegation')->andReturnUsing(function ($subAgentName, $taskInput, $contextSummary, $context) {
                return [$subAgentName, $taskInput, $contextSummary];
            });
            $mock->shouldReceive('afterSubAgentDelegation')->andReturnUsing(function ($subAgentName, $taskInput, $result, $context, $subAgentContext) {
                return $result;
            });
        });

        $tool = new DelegateToSubAgentTool($parentAgent);
        $parentContext = new AgentContext('parent-session', 'parent input');

        $arguments = [
            'sub_agent_name' => 'sub-agent',
            'task_input' => 'Task input',
            'context_summary' => 'Context summary'
        ];

        $tool->execute($arguments, $parentContext);

        Event::assertDispatched(TaskDelegated::class, function ($event) use ($parentContext) {
            // Verify parent context is preserved
            expect($event->parentContext)->toBe($parentContext);

            // Verify sub-agent context is different and properly created
            expect($event->subAgentContext)->not->toBe($parentContext);
            expect($event->subAgentContext->getSessionId())->toContain('parent-session_sub_sub-agent');

            // Verify delegation depth is set in sub-agent context
            expect($event->subAgentContext->getState('delegation_depth'))->toBe(1);

            return true;
        });
    });
});
