<?php

declare(strict_types=1);

use Vizra\VizraSdk\Models\TraceSpan;
use Vizra\VizraSdk\Services\Tracer;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Enable tracing for tests
    config(['agent-adk.tracing.enabled' => true]);

    // Use fresh tracer instance
    app()->forgetInstance(Tracer::class);
    $this->tracer = app(Tracer::class);
});

it('can get count of old traces', function () {
    // Create some old traces
    $oldDate = now()->subDays(35);
    $recentDate = now()->subDays(5);

    $oldTimestamp = $oldDate->getTimestamp() + ($oldDate->micro / 1000000);
    $recentTimestamp = $recentDate->getTimestamp() + ($recentDate->micro / 1000000);

    // Create old trace - use microtime format like Tracer service
    TraceSpan::create([
        'id' => '01JBOLD001000000000000000',
        'trace_id' => '01JBOLD001000000000000000',
        'span_id' => '01JBOLD001000000000000001',
        'parent_span_id' => null,
        'session_id' => 'old-session',
        'agent_name' => 'test_agent',
        'type' => 'agent_run',
        'name' => 'test_agent',
        'status' => 'success',
        'start_time' => $oldTimestamp,
        'end_time' => $oldDate->copy()->addSeconds(1)->getTimestamp() + ($oldDate->copy()->addSeconds(1)->micro / 1000000),
        'duration_ms' => 1000,
    ]);

    // Create recent trace - use microtime format like Tracer service
    TraceSpan::create([
        'id' => '01JBNEW001000000000000000',
        'trace_id' => '01JBNEW001000000000000000',
        'span_id' => '01JBNEW001000000000000001',
        'parent_span_id' => null,
        'session_id' => 'new-session',
        'agent_name' => 'test_agent',
        'type' => 'agent_run',
        'name' => 'test_agent',
        'status' => 'success',
        'start_time' => $recentTimestamp,
        'end_time' => $recentDate->copy()->addSeconds(1)->getTimestamp() + ($recentDate->copy()->addSeconds(1)->micro / 1000000),
        'duration_ms' => 1000,
    ]);

    // Should only count the old trace
    $count = $this->tracer->getOldTracesCount(30);
    expect($count)->toBe(1);

    // Should count both if we use 1 day
    $count = $this->tracer->getOldTracesCount(1);
    expect($count)->toBe(2);

    // Should count none if we use 50 days
    $count = $this->tracer->getOldTracesCount(50);
    expect($count)->toBe(0);
});

it('can cleanup old traces', function () {
    // Create multiple old traces with multiple spans each
    $oldDate = now()->subDays(35);

    for ($i = 1; $i <= 3; $i++) {
        $traceId = sprintf('01JBOLD%03d000000000000000', $i);

        // Root span - use microtime format like Tracer service
        TraceSpan::create([
            'id' => $traceId,
            'trace_id' => $traceId,
            'span_id' => sprintf('01JBOLD%03d000000000000001', $i),
            'parent_span_id' => null,
            'session_id' => "old-session-{$i}",
            'agent_name' => 'test_agent',
            'type' => 'agent_run',
            'name' => 'test_agent',
            'status' => 'success',
            'start_time' => $oldDate->getTimestamp() + ($oldDate->micro / 1000000),
            'end_time' => $oldDate->copy()->addSeconds(1)->getTimestamp() + ($oldDate->copy()->addSeconds(1)->micro / 1000000),
            'duration_ms' => 1000,
        ]);

        // Child span - use microtime format like Tracer service
        TraceSpan::create([
            'id' => sprintf('01JBOLD%03d100000000000000', $i),
            'trace_id' => $traceId,
            'span_id' => sprintf('01JBOLD%03d100000000000001', $i),
            'parent_span_id' => $traceId,
            'session_id' => "old-session-{$i}",
            'agent_name' => 'test_agent',
            'type' => 'llm_call',
            'name' => 'chat_completion',
            'status' => 'success',
            'start_time' => $oldDate->copy()->addMilliseconds(100)->getTimestamp() + ($oldDate->copy()->addMilliseconds(100)->micro / 1000000),
            'end_time' => $oldDate->copy()->addMilliseconds(800)->getTimestamp() + ($oldDate->copy()->addMilliseconds(800)->micro / 1000000),
            'duration_ms' => 700,
        ]);
    }

    // Create a recent trace that should not be deleted - use microtime format like Tracer service
    $recentDate = now()->subDays(5);
    TraceSpan::create([
        'id' => '01JBNEW001000000000000000',
        'trace_id' => '01JBNEW001000000000000000',
        'span_id' => '01JBNEW001000000000000001',
        'parent_span_id' => null,
        'session_id' => 'new-session',
        'agent_name' => 'test_agent',
        'type' => 'agent_run',
        'name' => 'test_agent',
        'status' => 'success',
        'start_time' => $recentDate->getTimestamp() + ($recentDate->micro / 1000000),
        'end_time' => $recentDate->copy()->addSeconds(1)->getTimestamp() + ($recentDate->copy()->addSeconds(1)->micro / 1000000),
        'duration_ms' => 1000,
    ]);

    // Verify we have 7 spans total (3 traces * 2 spans + 1 recent)
    expect(TraceSpan::count())->toBe(7);

    // Track progress
    $progressCalls = 0;
    $totalProgressSpans = 0;

    $deleted = $this->tracer->cleanupOldTraces(30, function ($batchDeleted) use (&$progressCalls, &$totalProgressSpans) {
        $progressCalls++;
        $totalProgressSpans += $batchDeleted;
    });

    // Should have deleted 6 spans (3 old traces * 2 spans each)
    expect($deleted)->toBe(6);
    expect($progressCalls)->toBeGreaterThan(0);
    expect($totalProgressSpans)->toBe(6);

    // Should have 1 span remaining (the recent one)
    expect(TraceSpan::count())->toBe(1);

    // Verify the remaining span is the recent one
    $remaining = TraceSpan::first();
    expect($remaining->session_id)->toBe('new-session');
});

it('handles cleanup when tracing is disabled', function () {
    config(['agent-adk.tracing.enabled' => false]);

    $count = $this->tracer->getOldTracesCount(30);
    expect($count)->toBe(0);

    $deleted = $this->tracer->cleanupOldTraces(30);
    expect($deleted)->toBe(0);
});

it('can run cleanup command', function () {
    // Create old traces
    $oldDate = now()->subDays(35);

    TraceSpan::create([
        'id' => '01JBOLD001000000000000000',
        'trace_id' => '01JBOLD001000000000000000',
        'span_id' => '01JBOLD001000000000000001',
        'parent_span_id' => null,
        'session_id' => 'old-session',
        'agent_name' => 'test_agent',
        'type' => 'agent_run',
        'name' => 'test_agent',
        'status' => 'success',
        'start_time' => $oldDate->getTimestamp() + ($oldDate->micro / 1000000),
        'end_time' => $oldDate->copy()->addSeconds(1)->getTimestamp() + ($oldDate->copy()->addSeconds(1)->micro / 1000000),
        'duration_ms' => 1000,
    ]);

    // Run dry-run cleanup
    $this->artisan('agent:trace:cleanup --dry-run')
        ->expectsOutput('Running in dry-run mode - no data will be deleted')
        ->expectsOutput('Found 1 traces older than 30 days.')
        ->expectsOutput('Would delete 1 traces (dry run).')
        ->assertExitCode(0);

    // Verify nothing was deleted
    expect(TraceSpan::count())->toBe(1);

    // Run actual cleanup with force flag
    $this->artisan('agent:trace:cleanup --force')
        ->expectsOutput('Found 1 traces older than 30 days.')
        ->expectsOutput('Successfully deleted 1 traces.')
        ->assertExitCode(0);

    // Verify trace was deleted
    expect(TraceSpan::count())->toBe(0);
});

it('handles cleanup command with custom days', function () {
    // Create trace that's 15 days old
    $oldDate = now()->subDays(15);

    TraceSpan::create([
        'id' => '01JBOLD001000000000000000',
        'trace_id' => '01JBOLD001000000000000000',
        'span_id' => '01JBOLD001000000000000001',
        'parent_span_id' => null,
        'session_id' => 'old-session',
        'agent_name' => 'test_agent',
        'type' => 'agent_run',
        'name' => 'test_agent',
        'status' => 'success',
        'start_time' => $oldDate->getTimestamp() + ($oldDate->micro / 1000000),
        'end_time' => $oldDate->copy()->addSeconds(1)->getTimestamp() + ($oldDate->copy()->addSeconds(1)->micro / 1000000),
        'duration_ms' => 1000,
    ]);

    // Should not find anything with default 30 days
    $this->artisan('agent:trace:cleanup --dry-run')
        ->expectsOutput('No old traces found to clean up.')
        ->assertExitCode(0);

    // Should find the trace with 10 days
    $this->artisan('agent:trace:cleanup --days=10 --dry-run')
        ->expectsOutput('Found 1 traces older than 10 days.')
        ->expectsOutput('Would delete 1 traces (dry run).')
        ->assertExitCode(0);
});

it('handles cleanup command when tracing is disabled', function () {
    config(['agent-adk.tracing.enabled' => false]);

    $this->artisan('agent:trace:cleanup')
        ->expectsOutput('Agent tracing is not enabled in configuration.')
        ->assertExitCode(1);
});

it('handles cleanup cancellation', function () {
    // Create old trace
    $oldDate = now()->subDays(35);

    TraceSpan::create([
        'id' => '01JBOLD001000000000000000',
        'trace_id' => '01JBOLD001000000000000000',
        'span_id' => '01JBOLD001000000000000001',
        'parent_span_id' => null,
        'session_id' => 'old-session',
        'agent_name' => 'test_agent',
        'type' => 'agent_run',
        'name' => 'test_agent',
        'status' => 'success',
        'start_time' => $oldDate->getTimestamp() + ($oldDate->micro / 1000000),
        'end_time' => $oldDate->copy()->addSeconds(1)->getTimestamp() + ($oldDate->copy()->addSeconds(1)->micro / 1000000),
        'duration_ms' => 1000,
    ]);

    // Simulate user saying "no" to confirmation
    $this->artisan('agent:trace:cleanup')
        ->expectsQuestion('Are you sure you want to delete 1 traces?', false)
        ->expectsOutput('Cleanup cancelled.')
        ->assertExitCode(0);

    // Verify nothing was deleted
    expect(TraceSpan::count())->toBe(1);
});
