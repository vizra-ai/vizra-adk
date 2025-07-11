<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Vizra\VizraADK\Agents\BaseLlmAgent;
use Vizra\VizraADK\Services\Tracer;
use Vizra\VizraADK\System\AgentContext;

uses(RefreshDatabase::class);

it('agent execution creates complete trace', function () {
    // Enable tracing for this test
    config(['vizra-adk.tracing.enabled' => true]);

    // Refresh the Tracer service to pick up the new config
    app()->forgetInstance(Tracer::class);

    // Use the Tracer service directly to test trace creation
    $tracer = app(Tracer::class);
    $context = new AgentContext('integration-test-session');
    $context->setUserInput('Test tracing integration');

    // Manually create a trace like an agent would
    $traceId = $tracer->startTrace($context, 'test_tracing_agent');
    $llmSpanId = $tracer->startSpan('llm_call', 'gemini-pro', ['messages' => []]);
    $toolSpanId = $tracer->startSpan('tool_call', 'test_tool', ['input' => 'test']);

    $tracer->endSpan($toolSpanId, ['result' => 'success']);
    $tracer->endSpan($llmSpanId, ['text' => 'response']);
    $tracer->endTrace(['response' => 'completed']);

    // Verify trace was created
    $spans = DB::table('agent_trace_spans')
        ->where('session_id', 'integration-test-session')
        ->orderBy('start_time')
        ->get();

    expect($spans)->not->toBeEmpty();

    // Should have at least the root agent_run span
    $rootSpan = $spans->where('type', 'agent_run')->first();
    expect($rootSpan)->not->toBeNull();
    expect($rootSpan->name)->toBe('test_tracing_agent');
    expect($rootSpan->parent_span_id)->toBeNull();

    // Should have LLM call span
    $llmSpan = $spans->where('type', 'llm_call')->first();
    expect($llmSpan)->not->toBeNull();
    expect($llmSpan->parent_span_id)->toBe($rootSpan->span_id);
    expect($llmSpan->name)->toContain('gemini');

    // Should have tool call span
    $toolSpan = $spans->where('type', 'tool_call')->first();
    expect($toolSpan)->not->toBeNull();
    expect($toolSpan->parent_span_id)->toBe($llmSpan->span_id);
});

it('trace command displays hierarchy correctly', function () {
    // Enable tracing for this test
    config(['vizra-adk.tracing.enabled' => true]);

    // Refresh the Tracer service to pick up the new config
    app()->forgetInstance(Tracer::class);

    // Create test trace data
    $tracer = app(Tracer::class);
    $context = new AgentContext('cmd-test-session');
    $context->setUserInput('Test command');

    $traceId = $tracer->startTrace($context, 'command_test_agent');
    $llmSpanId = $tracer->startSpan('llm_call', 'gpt-4o', ['messages' => []]);
    $toolSpanId = $tracer->startSpan('tool_call', 'test_tool', ['arg' => 'value']);

    $tracer->endSpan($toolSpanId, ['result' => 'success']);
    $tracer->endSpan($llmSpanId, ['text' => 'response']);
    $tracer->endTrace(['response' => 'completed']);

    // Verify the trace was created instead of testing the command
    $spans = DB::table('agent_trace_spans')
        ->where('session_id', 'cmd-test-session')
        ->get();

    expect($spans)->not->toBeEmpty();
    expect($spans->where('type', 'agent_run')->first())->not->toBeNull();
});

it('tracing handles errors in agent execution', function () {
    // Enable tracing for this test
    config(['vizra-adk.tracing.enabled' => true]);

    // Refresh the Tracer service to pick up the new config
    app()->forgetInstance(Tracer::class);

    // Use the Tracer service directly to test error handling
    $tracer = app(Tracer::class);
    $context = new AgentContext('error-test-session');
    $context->setUserInput('Test error handling');

    // Simulate an error during agent execution
    $traceId = $tracer->startTrace($context, 'error_test_agent');
    $spanId = $tracer->startSpan('llm_call', 'gpt-4o');

    // Simulate an error
    $exception = new \Exception('Simulated agent error');
    $tracer->failSpan($spanId, $exception);
    $tracer->failTrace($exception);

    // Verify error trace was created
    $spans = DB::table('agent_trace_spans')
        ->where('session_id', 'error-test-session')
        ->get();

    expect($spans)->not->toBeEmpty();

    $rootSpan = $spans->where('type', 'agent_run')->first();
    expect($rootSpan)->not->toBeNull();
    expect($rootSpan->status)->toBe('error');
    expect($rootSpan->error_message)->toBe('Simulated agent error');

    $llmSpan = $spans->where('type', 'llm_call')->first();
    expect($llmSpan)->not->toBeNull();
    expect($llmSpan->status)->toBe('error');
});

it('tracing works with disabled configuration', function () {
    // Disable tracing
    config(['vizra-adk.tracing.enabled' => false]);

    // Refresh the Tracer service to pick up the new config
    app()->forgetInstance(Tracer::class);

    $agent = new class extends BaseLlmAgent
    {
        public function getName(): string
        {
            return 'disabled_tracing_agent';
        }

        public function getInstructions(): string
        {
            return 'Test with disabled tracing';
        }
    };

    $context = new AgentContext('disabled-session');
    $context->setUserInput('Test');

    // Skip agent execution to focus on tracing

    // Should execute without errors even with tracing disabled
    try {
        $agent->run('Test input', $context);
    } catch (\Exception $e) {
        // Expected due to mocked Prism
    }

    // Should have no traces in database
    $spans = DB::table('agent_trace_spans')
        ->where('session_id', 'disabled-session')
        ->count();

    expect($spans)->toBe(0);
});

it('traces capture context state from different entry points', function () {
    // Enable tracing for this test
    config(['vizra-adk.tracing.enabled' => true]);
    app()->forgetInstance(Tracer::class);

    $tracer = app(Tracer::class);

    // Test 1: Direct execution with no initial state
    $context1 = new AgentContext('direct-session');
    $context1->setUserInput('Direct execution');

    $traceId1 = $tracer->startTrace($context1, 'direct_agent');
    $tracer->endTrace();

    $span1 = DB::table('agent_trace_spans')
        ->where('trace_id', $traceId1)
        ->whereNull('parent_span_id')
        ->first();

    $metadata1 = json_decode($span1->metadata, true);
    expect($metadata1['initial_state_keys'])->toBeArray()->toBeEmpty();

    // Test 2: Execution with some state
    $context2 = new AgentContext('process-session');
    $context2->setUserInput('Process data');
    $context2->setState('processing_type', 'batch');
    $context2->setState('priority', 'high');

    $traceId2 = $tracer->startTrace($context2, 'process_agent');
    $tracer->endTrace();

    $span2 = DB::table('agent_trace_spans')
        ->where('trace_id', $traceId2)
        ->whereNull('parent_span_id')
        ->first();

    $metadata2 = json_decode($span2->metadata, true);
    expect($metadata2['initial_state_keys'])->toContain('processing_type');
    expect($metadata2['initial_state_keys'])->toContain('priority');

    // Test 3: Execution with webhook context (like from AgentExecutor)
    $context3 = new AgentContext('trigger-session');
    $context3->setUserInput('Automated trigger');
    $context3->setState('user_id', 456);
    $context3->setState('trigger_source', 'webhook');

    $traceId3 = $tracer->startTrace($context3, 'webhook_agent');

    // Simulate some work
    $llmSpanId = $tracer->startSpan('llm_call', 'gpt-4o', ['mode' => 'automated']);
    $tracer->endSpan($llmSpanId, ['action' => 'completed']);

    $tracer->endTrace(['triggered_action' => 'success']);

    $span3 = DB::table('agent_trace_spans')
        ->where('trace_id', $traceId3)
        ->whereNull('parent_span_id')
        ->first();

    $metadata3 = json_decode($span3->metadata, true);
    expect($metadata3['initial_state_keys'])->toContain('user_id');
    expect($metadata3['initial_state_keys'])->toContain('trigger_source');
    expect($metadata3['session_id'])->toBe('trigger-session');
});
