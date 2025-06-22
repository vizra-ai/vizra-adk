<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Vizra\VizraADK\Models\TraceSpan;
use Vizra\VizraADK\Services\Tracer;
use Vizra\VizraADK\System\AgentContext;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Enable tracing for these tests
    config(['vizra-adk.tracing.enabled' => true]);

    // Use fresh tracer instance to avoid cached disabled state
    app()->forgetInstance(Tracer::class);
    $this->tracer = app(Tracer::class);

    $this->context = new AgentContext(
        'test-session-123',
        'Hello, test agent!',
        ['test_key' => 'test_value']
    );
});

it('has tracing enabled', function () {
    expect($this->tracer->isEnabled())->toBeTrue();
});

it('can create and manage traces', function () {
    // Start a trace
    $traceId = $this->tracer->startTrace($this->context, 'test_agent');

    expect($traceId)->toBeString()->not->toBeEmpty();
    expect($this->tracer->getCurrentTraceId())->toBe($traceId);

    // Verify root span was created
    $rootSpan = DB::table('agent_trace_spans')
        ->where('trace_id', $traceId)
        ->whereNull('parent_span_id')
        ->first();

    expect($rootSpan)->not->toBeNull();
    expect($rootSpan->type)->toBe('agent_run');
    expect($rootSpan->name)->toBe('test_agent');
    expect($rootSpan->status)->toBe('running');
});

it('can create hierarchical spans', function () {
    // Start trace
    $traceId = $this->tracer->startTrace($this->context, 'test_agent');

    // Create LLM call span
    $llmSpanId = $this->tracer->startSpan(
        'llm_call',
        'gpt-4o',
        ['messages' => [['role' => 'user', 'content' => 'test']]],
        ['temperature' => 0.7]
    );

    // Create tool call span (child of LLM call)
    $toolSpanId = $this->tracer->startSpan(
        'tool_call',
        'weather_tool',
        ['city' => 'London']
    );

    // Add small delays to ensure duration > 0
    usleep(1000); // 1ms delay

    // End spans
    $this->tracer->endSpan($toolSpanId, ['result' => 'Sunny, 22°C']);
    usleep(1000); // 1ms delay
    $this->tracer->endSpan($llmSpanId, ['text' => 'The weather is sunny']);
    usleep(1000); // 1ms delay
    $this->tracer->endTrace(['response' => 'Weather retrieved successfully']);

    // Verify hierarchy
    $spans = DB::table('agent_trace_spans')
        ->where('trace_id', $traceId)
        ->orderBy('start_time')
        ->get();

    expect($spans)->toHaveCount(3);

    $rootSpan = $spans->where('type', 'agent_run')->first();
    $llmSpan = $spans->where('type', 'llm_call')->first();
    $toolSpan = $spans->where('type', 'tool_call')->first();

    // Check parent-child relationships
    expect($rootSpan->parent_span_id)->toBeNull();
    expect($llmSpan->parent_span_id)->toBe($rootSpan->span_id);
    expect($toolSpan->parent_span_id)->toBe($llmSpan->span_id);

    // Check all spans completed successfully
    expect($rootSpan->status)->toBe('success');
    expect($llmSpan->status)->toBe('success');
    expect($toolSpan->status)->toBe('success');

    // Check durations are calculated
    expect($rootSpan->duration_ms)->toBeGreaterThan(0);
    expect($llmSpan->duration_ms)->toBeGreaterThan(0);
    expect($toolSpan->duration_ms)->toBeGreaterThan(0);
});

it('handles span failures', function () {
    // Start trace and span
    $traceId = $this->tracer->startTrace($this->context, 'test_agent');
    $spanId = $this->tracer->startSpan('tool_call', 'failing_tool');

    // Add small delay to ensure duration > 0
    usleep(1000); // 1ms delay

    // Simulate failure
    $exception = new \Exception('Tool execution failed');
    $this->tracer->failSpan($spanId, $exception);

    // Verify error handling
    $span = DB::table('agent_trace_spans')
        ->where('span_id', $spanId)
        ->first();

    expect($span->status)->toBe('error');
    expect($span->error_message)->toBe('Tool execution failed');
    expect($span->duration_ms)->toBeGreaterThan(0);
});

it('handles trace failures', function () {
    // Start trace with some spans
    $traceId = $this->tracer->startTrace($this->context, 'test_agent');
    $spanId1 = $this->tracer->startSpan('llm_call', 'gpt-4o');
    $spanId2 = $this->tracer->startSpan('tool_call', 'weather_tool');

    // Simulate trace-level failure
    $exception = new \Exception('Agent execution failed');
    $this->tracer->failTrace($exception);

    // Verify all spans are marked as failed
    $spans = DB::table('agent_trace_spans')
        ->where('trace_id', $traceId)
        ->get();

    foreach ($spans as $span) {
        expect($span->status)->toBe('error');
    }

    // Verify trace is cleaned up
    expect($this->tracer->getCurrentTraceId())->toBeNull();
    expect($this->tracer->getCurrentSpanId())->toBeNull();
});

it('can retrieve spans by session', function () {
    // Create multiple traces for the same session
    $traceId1 = $this->tracer->startTrace($this->context, 'agent1');
    $this->tracer->endTrace();

    $traceId2 = $this->tracer->startTrace($this->context, 'agent2');
    $this->tracer->endTrace();

    // Debug: check what's actually in the database
    $allSpans = DB::table('agent_trace_spans')->get();
    expect($allSpans)->toHaveCount(2); // Two root spans should be created

    // Retrieve all spans for session
    $spans = $this->tracer->getSpansForSession('test-session-123');

    expect($spans)->toHaveCount(2); // Two root spans
    expect(collect($spans)->pluck('trace_id')->unique())->toHaveCount(2);
});

it('can retrieve spans by trace', function () {
    // Create trace with multiple spans
    $traceId = $this->tracer->startTrace($this->context, 'test_agent');
    $llmSpanId = $this->tracer->startSpan('llm_call', 'gpt-4o');
    $toolSpanId = $this->tracer->startSpan('tool_call', 'weather_tool');

    $this->tracer->endSpan($toolSpanId);
    $this->tracer->endSpan($llmSpanId);
    $this->tracer->endTrace();

    // Retrieve spans for this specific trace
    $spans = $this->tracer->getSpansForTrace($traceId);

    expect($spans)->toHaveCount(3);
    expect(collect($spans)->pluck('trace_id')->unique())->toHaveCount(1);
    expect(collect($spans)->first()->trace_id)->toBe($traceId);
});

it('respects tracing enabled configuration', function () {
    // Disable tracing
    config(['vizra-adk.tracing.enabled' => false]);
    $tracer = new Tracer;

    expect($tracer->isEnabled())->toBeFalse();

    // Operations should return empty/null without errors
    $traceId = $tracer->startTrace($this->context, 'test_agent');
    expect($traceId)->toBe('');

    $spanId = $tracer->startSpan('test', 'test');
    expect($spanId)->toBe('');

    $spans = $tracer->getSpansForSession('test-session');
    expect($spans)->toBeArray()->toBeEmpty();
});

it('handles database errors gracefully', function () {
    // Temporarily break the database table name to simulate error
    config(['vizra-adk.tables.agent_trace_spans' => 'non_existent_table']);

    // Operations should not throw exceptions
    $traceId = $this->tracer->startTrace($this->context, 'test_agent');
    expect($traceId)->toBeString(); // Still generates trace ID

    $spanId = $this->tracer->startSpan('test', 'test');
    expect($spanId)->toBe(''); // But span creation fails gracefully

    // Should not throw exceptions
    $this->tracer->endSpan('fake-span-id');
    $this->tracer->failSpan('fake-span-id', new \Exception('test'));
    $this->tracer->endTrace();
});

it('trace span model relationships work', function () {
    // Create test data
    $traceId = $this->tracer->startTrace($this->context, 'test_agent');
    $llmSpanId = $this->tracer->startSpan('llm_call', 'gpt-4o');
    $toolSpanId = $this->tracer->startSpan('tool_call', 'weather_tool');

    $this->tracer->endSpan($toolSpanId);
    $this->tracer->endSpan($llmSpanId);
    $this->tracer->endTrace();

    // Test model relationships
    $rootSpan = TraceSpan::where('trace_id', $traceId)
        ->whereNull('parent_span_id')
        ->first();

    $llmSpan = TraceSpan::where('trace_id', $traceId)
        ->where('type', 'llm_call')
        ->first();

    $toolSpan = TraceSpan::where('trace_id', $traceId)
        ->where('type', 'tool_call')
        ->first();

    // Test parent-child relationships
    expect($rootSpan->children)->toHaveCount(1);
    expect($rootSpan->children->first()->span_id)->toBe($llmSpan->span_id);

    expect($llmSpan->parent->span_id)->toBe($rootSpan->span_id);
    expect($llmSpan->children)->toHaveCount(1);
    expect($llmSpan->children->first()->span_id)->toBe($toolSpan->span_id);

    expect($toolSpan->parent->span_id)->toBe($llmSpan->span_id);
    expect($toolSpan->children)->toHaveCount(0);

    // Test utility methods
    expect($rootSpan->isRoot())->toBeTrue();
    expect($llmSpan->isRoot())->toBeFalse();
    expect($toolSpan->isCompleted())->toBeTrue();
    expect($rootSpan->hasError())->toBeFalse();

    // Test formatted methods
    expect($rootSpan->getFormattedDuration())->toContain('ms');
    expect($rootSpan->getStatusIcon())->toBe('✅');
    expect($rootSpan->getTypeIcon())->toBe('🤖');
    expect($rootSpan->getSummary())->toContain('🤖 agent_run: test_agent');
});

it('can clean up old traces', function () {
    // Create some test traces
    $traceId = $this->tracer->startTrace($this->context, 'test_agent');
    $this->tracer->endTrace();

    // Manually update start_time to simulate old data (use same format as Tracer service)
    $oldDate = now()->subDays(35);
    $oldTimestamp = $oldDate->getTimestamp() + ($oldDate->micro / 1000000);

    DB::table('agent_trace_spans')
        ->where('trace_id', $traceId)
        ->update(['start_time' => $oldTimestamp]);

    // Clean up traces older than 30 days
    $deletedCount = $this->tracer->cleanupOldTraces(30);

    expect($deletedCount)->toBe(1);

    // Verify trace was deleted
    $remainingSpans = DB::table('agent_trace_spans')
        ->where('trace_id', $traceId)
        ->count();

    expect($remainingSpans)->toBe(0);
});

it('tracks execution mode in trace metadata', function () {
    // Test default execution mode (ask)
    $context1 = new AgentContext('session-1', 'Hello');
    $traceId1 = $this->tracer->startTrace($context1, 'test_agent');

    $rootSpan1 = DB::table('agent_trace_spans')
        ->where('trace_id', $traceId1)
        ->whereNull('parent_span_id')
        ->first();

    $metadata1 = json_decode($rootSpan1->metadata, true);
    expect($metadata1['execution_mode'])->toBe('ask');

    $this->tracer->endTrace();

    // Test with custom execution mode
    $context2 = new AgentContext('session-2', 'Process this');
    $context2->setState('execution_mode', 'process');
    $traceId2 = $this->tracer->startTrace($context2, 'test_agent');

    $rootSpan2 = DB::table('agent_trace_spans')
        ->where('trace_id', $traceId2)
        ->whereNull('parent_span_id')
        ->first();

    $metadata2 = json_decode($rootSpan2->metadata, true);
    expect($metadata2['execution_mode'])->toBe('process');

    $this->tracer->endTrace();

    // Test with different execution modes
    $modes = ['trigger', 'analyze', 'report', 'delegate'];
    foreach ($modes as $mode) {
        $context = new AgentContext("session-$mode", "Test $mode");
        $context->setState('execution_mode', $mode);
        $traceId = $this->tracer->startTrace($context, 'test_agent');

        $rootSpan = DB::table('agent_trace_spans')
            ->where('trace_id', $traceId)
            ->whereNull('parent_span_id')
            ->first();

        $metadata = json_decode($rootSpan->metadata, true);
        expect($metadata['execution_mode'])->toBe($mode);

        $this->tracer->endTrace();
    }
});

it('preserves execution mode when set by AgentExecutor', function () {
    // Simulate AgentExecutor setting execution mode
    $context = new AgentContext('executor-session', 'Execute task');
    $context->setState('execution_mode', 'trigger');
    $context->setState('user_id', 123);
    $context->setState('user_email', 'test@example.com');

    $traceId = $this->tracer->startTrace($context, 'automated_agent');

    // Create some child spans to ensure mode is preserved
    $llmSpanId = $this->tracer->startSpan('llm_call', 'gpt-4o', ['prompt' => 'automated task']);
    $toolSpanId = $this->tracer->startSpan('tool_call', 'automation_tool', ['action' => 'execute']);

    $this->tracer->endSpan($toolSpanId, ['result' => 'success']);
    $this->tracer->endSpan($llmSpanId, ['response' => 'Task completed']);
    $this->tracer->endTrace(['status' => 'completed']);

    // Verify root span has the correct execution mode
    $rootSpan = DB::table('agent_trace_spans')
        ->where('trace_id', $traceId)
        ->whereNull('parent_span_id')
        ->first();

    $metadata = json_decode($rootSpan->metadata, true);
    expect($metadata['execution_mode'])->toBe('trigger');
    expect($metadata['session_id'])->toBe('executor-session');
});

it('handles missing execution mode gracefully', function () {
    // Create context without setting execution mode
    $context = new AgentContext('no-mode-session', 'Test');
    // Explicitly set execution_mode to null to test the default fallback
    $context->setState('execution_mode', null);

    $traceId = $this->tracer->startTrace($context, 'test_agent');

    $rootSpan = DB::table('agent_trace_spans')
        ->where('trace_id', $traceId)
        ->whereNull('parent_span_id')
        ->first();

    $metadata = json_decode($rootSpan->metadata, true);
    // Should default to 'ask' when not set
    expect($metadata['execution_mode'])->toBe('ask');

    $this->tracer->endTrace();
});
