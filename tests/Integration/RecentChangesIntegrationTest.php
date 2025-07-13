<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Vizra\VizraADK\Agents\BaseLlmAgent;
use Vizra\VizraADK\Contracts\ToolInterface;
use Vizra\VizraADK\Facades\Agent;
use Vizra\VizraADK\Livewire\ChatInterface;
use Vizra\VizraADK\Models\TraceSpan;
use Vizra\VizraADK\Services\Tracer;
use Vizra\VizraADK\System\AgentContext;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Enable tracing for integration tests
    config(['vizra-adk.tracing.enabled' => true]);
    
    // Create test agent and tool
    $this->testAgent = new class extends BaseLlmAgent {
        protected string $name = 'integration_test_agent';
        protected string $description = 'Test agent for integration testing';
        protected string $instructions = 'You are a test agent for integration testing.';
        protected array $tools = [IntegrationTestTool::class];
    };
    
    // Register the test agent
    Agent::build(get_class($this->testAgent))->register();
});

// Test tool for integration testing
class IntegrationTestTool implements ToolInterface
{
    public function definition(): array
    {
        return [
            'name' => 'integration_test_tool',
            'description' => 'A test tool for integration testing',
            'parameters' => [
                'type' => 'object',
                'properties' => [
                    'action' => [
                        'type' => 'string',
                        'description' => 'The action to perform'
                    ]
                ],
                'required' => ['action']
            ]
        ];
    }

    public function execute(array $arguments, AgentContext $context, \Vizra\VizraADK\Memory\AgentMemory $memory): string
    {
        return json_encode([
            'result' => 'success',
            'action' => $arguments['action'] ?? 'default',
            'timestamp' => now()->toISOString()
        ]);
    }
}

test('complete agent flow with tracing and typing indicator', function () {
    // Mock the Agent facade to avoid actual LLM calls
    Agent::shouldReceive('run')
        ->once()
        ->with('integration_test_agent', 'Test integration message', Mockery::type('string'))
        ->andReturn('This is a test response from the integration test agent.');
    
    // Test the complete chat interface flow
    $component = Livewire::test(ChatInterface::class)
        ->set('selectedAgent', 'integration_test_agent')
        ->set('message', 'Test integration message');
    
    // Send message - should set loading state
    $component->call('sendMessage')
        ->assertSet('isLoading', true)
        ->assertSet('message', '') // Message cleared
        ->assertCount('chatHistory', 1); // User message added
    
    // Verify user message was added correctly
    $component->tap(function ($comp) {
        $chatHistory = $comp->get('chatHistory');
        expect($chatHistory[0]['role'])->toBe('user');
        expect($chatHistory[0]['content'])->toBe('Test integration message');
    });
    
    // Simulate the async processing
    $component->call('processAgentResponse', 'Test integration message')
        ->assertSet('isLoading', false) // Loading state cleared
        ->assertCount('chatHistory', 2); // User + agent message
    
    // Verify agent response was added
    $component->tap(function ($comp) {
        $chatHistory = $comp->get('chatHistory');
        expect($chatHistory[1]['role'])->toBe('assistant');
        expect($chatHistory[1]['content'])->toBe('This is a test response from the integration test agent.');
    });
});

test('agent execution with proper trace span creation', function () {
    // Create a test context
    $context = new AgentContext('integration-session-123', 'Test tracing message');
    $tracer = app(Tracer::class);
    
    // Start a trace
    $traceId = $tracer->startTrace($context, 'integration_test_agent');
    
    // Simulate agent execution with spans
    $llmSpanId = $tracer->startSpan(
        'llm_call',
        'test-model',
        ['messages' => [['role' => 'user', 'content' => 'Test tracing message']]],
        ['temperature' => 0.7]
    );
    
    $toolSpanId = $tracer->startSpan(
        'tool_call',
        'integration_test_tool',
        ['action' => 'test_action']
    );
    
    // Add delays to ensure measurable durations
    usleep(1000);
    
    // End spans in proper order
    $tracer->endSpan($toolSpanId, ['result' => 'success', 'data' => 'test result']);
    usleep(1000);
    $tracer->endSpan($llmSpanId, ['response' => 'LLM response with tool result']);
    usleep(1000);
    $tracer->endTrace(['final_response' => 'Integration test completed']);
    
    // Verify trace spans were created correctly
    $spans = TraceSpan::where('trace_id', $traceId)
        ->orderBy('start_time')
        ->get();
    
    expect($spans)->toHaveCount(3);
    
    $rootSpan = $spans->where('type', 'agent_run')->first();
    $llmSpan = $spans->where('type', 'llm_call')->first();
    $toolSpan = $spans->where('type', 'tool_call')->first();
    
    // Verify hierarchy
    expect($rootSpan->parent_span_id)->toBeNull();
    expect($llmSpan->parent_span_id)->toBe($rootSpan->span_id);
    expect($toolSpan->parent_span_id)->toBe($llmSpan->span_id);
    
    // Verify all spans completed successfully
    expect($rootSpan->status)->toBe('success');
    expect($llmSpan->status)->toBe('success');
    expect($toolSpan->status)->toBe('success');
    
    // Verify outputs were preserved
    expect($rootSpan->output)->not->toBeNull();
    expect($llmSpan->output)->not->toBeNull();
    expect($toolSpan->output)->not->toBeNull();
    
    $toolOutput = json_decode($toolSpan->output, true);
    expect($toolOutput['result'])->toBe('success');
    expect($toolOutput['data'])->toBe('test result');
});

test('sub-agent delegation with proper trace context', function () {
    $context = new AgentContext('delegation-session-456', 'Delegate this task');
    $tracer = app(Tracer::class);
    
    // Start parent agent trace
    $parentTraceId = $tracer->startTrace($context, 'parent_agent');
    $delegationSpanId = $tracer->startSpan(
        'sub_agent_delegation',
        'delegate_to_specialist',
        ['task' => 'specialized_task']
    );
    
    usleep(1000);
    
    // Start sub-agent trace (simulating delegation)
    $subContext = new AgentContext(
        $context->getSessionId(),
        'Specialized task execution',
        ['delegated_from' => 'parent_agent', 'task_id' => 'spec_123']
    );
    
    $subTraceId = $tracer->startTrace($subContext, 'specialist_agent');
    $subSpanId = $tracer->startSpan(
        'tool_call',
        'specialist_tool',
        ['specialized_param' => 'value']
    );
    
    usleep(1000);
    
    // Complete sub-agent work
    $tracer->endSpan($subSpanId, ['specialist_result' => 'completed successfully']);
    $tracer->endTrace(['delegation_result' => 'specialist work done']);
    
    // Complete parent agent work
    $tracer->endSpan($delegationSpanId, ['delegation_status' => 'completed']);
    $tracer->endTrace(['parent_result' => 'delegation successful']);
    
    // Verify both traces exist and are separate
    $parentSpans = TraceSpan::where('trace_id', $parentTraceId)->get();
    $subSpans = TraceSpan::where('trace_id', $subTraceId)->get();
    
    expect($parentSpans)->toHaveCount(2); // parent + delegation span
    expect($subSpans)->toHaveCount(2); // specialist + tool span
    
    // Verify all spans completed successfully
    foreach ($parentSpans as $span) {
        expect($span->status)->toBe('success');
    }
    
    foreach ($subSpans as $span) {
        expect($span->status)->toBe('success');
    }
    
    // Verify session IDs are preserved
    foreach ($parentSpans as $span) {
        expect($span->session_id)->toBe('delegation-session-456');
    }
    
    foreach ($subSpans as $span) {
        expect($span->session_id)->toBe('delegation-session-456');
    }
});

test('chat interface state management during errors', function () {
    // Mock Agent facade to throw an exception
    Agent::shouldReceive('run')
        ->once()
        ->andThrow(new \Exception('Test agent error'));
    
    $component = Livewire::test(ChatInterface::class)
        ->set('selectedAgent', 'integration_test_agent')
        ->set('message', 'This will cause an error');
    
    // Send message
    $component->call('sendMessage')
        ->assertSet('isLoading', true)
        ->assertCount('chatHistory', 1); // User message added
    
    // Process agent response (which will fail)
    $component->call('processAgentResponse', 'This will cause an error')
        ->assertSet('isLoading', false) // Loading state should be cleared even on error
        ->assertCount('chatHistory', 2); // User message + error message
    
    // Verify error message was added
    $component->tap(function ($comp) {
        $chatHistory = $comp->get('chatHistory');
        expect($chatHistory[1]['role'])->toBe('error');
        expect($chatHistory[1]['content'])->toContain('Error: Test agent error');
    });
});

test('async chat processing with trace spans display', function () {
    // Mock successful agent execution
    Agent::shouldReceive('run')
        ->once()
        ->andReturn('Traced response');
    
    $component = Livewire::test(ChatInterface::class)
        ->set('selectedAgent', 'integration_test_agent')
        ->set('message', 'Trace this message');
    
    // Send message and process
    $component->call('sendMessage')
        ->call('processAgentResponse', 'Trace this message');
    
    // Check that trace data is loaded
    $component->tap(function ($comp) {
        $traceData = $comp->get('traceData');
        // Note: Without actual agent execution, traces won't be created
        // This test validates the loading mechanism exists
        expect($traceData)->toBeArray();
    });
});

test('prompt versioning with agent execution', function () {
    // Create test agent with versioned prompts
    $versionedAgent = new class extends BaseLlmAgent {
        protected string $name = 'versioned_test_agent';
        protected string $instructions = 'Default instructions';
        protected ?string $promptVersion = null;
    };
    
    // Test default instructions
    expect($versionedAgent->getInstructions())->toBe('Default instructions');
    
    // Test runtime prompt override
    $versionedAgent->setPromptOverride('Override instructions for testing');
    expect($versionedAgent->getInstructions())->toBe('Override instructions for testing');
    
    // Test prompt version setting
    $versionedAgent->setPromptVersion('v2');
    expect($versionedAgent->getPromptVersion())->toBe('v2');
    
    // Test that override takes precedence over version
    expect($versionedAgent->getInstructions())->toBe('Override instructions for testing');
});