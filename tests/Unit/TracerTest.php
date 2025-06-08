<?php

use AaronLumsden\LaravelAiADK\Services\Tracer;
use AaronLumsden\LaravelAiADK\System\AgentContext;
use AaronLumsden\LaravelAiADK\Models\TraceSpan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Enable tracing for these tests
    config(['agent-adk.tracing.enabled' => true]);

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

it('can create hierarchical spans', function ()
    {
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
    config(['agent-adk.tracing.enabled' => false]);
    $tracer = new Tracer();

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
    config(['agent-adk.tracing.table' => 'non_existent_table']);

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
