<?php

namespace AaronLumsden\LaravelAgentADK\Services;

use AaronLumsden\LaravelAgentADK\System\AgentContext;
use AaronLumsden\LaravelAgentADK\Models\TraceSpan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

/**
 * Tracer Service
 *
 * Manages the complete lifecycle of agent execution tracing.
 * Handles trace initialization, span creation, hierarchical relationships,
 * and timing measurements for debugging and performance analysis.
 */
class Tracer
{
    /** @var string|null Current trace ID for the active agent run */
    protected ?string $currentTraceId = null;

    /** @var string|null Current session ID for the active trace */
    protected ?string $currentSessionId = null;

    /** @var array Stack of active span IDs to track parent-child relationships */
    protected array $spanStack = [];

    /** @var array Cache of span start times for duration calculation */
    protected array $spanStartTimes = [];

    /** @var bool Whether tracing is currently enabled */
    protected bool $enabled;

    public function __construct()
    {
        $this->enabled = config('agent-adk.tracing.enabled', true);
    }

    /**
     * Check if tracing is enabled in configuration.
     */
    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    /**
     * Initialize a new trace for an agent run.
     * Creates the root span and sets up the trace context.
     */
    public function startTrace(AgentContext $context, string $agentName): string
    {
        if (!$this->isEnabled()) {
            return '';
        }

        $this->currentTraceId = Str::ulid()->toString();
        $this->currentSessionId = $context->getSessionId();
        $this->spanStack = [];
        $this->spanStartTimes = [];

        // Create the root span for the entire agent run
        $rootSpanId = $this->startSpan(
            type: 'agent_run',
            name: $agentName,
            input: ['user_input' => $context->getUserInput()],
            metadata: [
                'session_id' => $context->getSessionId(),
                'initial_state_keys' => array_keys($context->getAllState())
            ]
        );

        return $this->currentTraceId;
    }

    /**
     * Start a new span within the current trace.
     * Automatically manages parent-child relationships using the span stack.
     */
    public function startSpan(
        string $type,
        string $name,
        ?array $input = null,
        ?array $metadata = null
    ): string {
        if (!$this->isEnabled() || !$this->currentTraceId) {
            return '';
        }

        $spanId = Str::ulid()->toString();
        $parentSpanId = empty($this->spanStack) ? null : end($this->spanStack);
        $startTime = microtime(true);

        // Store start time for duration calculation
        $this->spanStartTimes[$spanId] = $startTime;

        // Push span onto stack to track hierarchy
        $this->spanStack[] = $spanId;

        try {
            // Create the database record
            DB::table(config('agent-adk.tracing.table', 'agent_trace_spans'))->insert([
                'id' => Str::ulid()->toString(),
                'trace_id' => $this->currentTraceId,
                'parent_span_id' => $parentSpanId,
                'span_id' => $spanId,
                'session_id' => $this->getCurrentSessionId(),
                'agent_name' => $this->getCurrentAgentName($name, $type),
                'type' => $type,
                'name' => $name,
                'input' => $input ? json_encode($input) : null,
                'output' => null,
                'metadata' => $metadata ? json_encode($metadata) : null,
                'status' => 'running',
                'error_message' => null,
                'start_time' => $startTime,
                'end_time' => null,
                'duration_ms' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (Throwable $e) {
            // If database insert fails, don't break the agent execution
            // but remove from our tracking
            if (($key = array_search($spanId, $this->spanStack)) !== false) {
                unset($this->spanStack[$key]);
                $this->spanStack = array_values($this->spanStack);
            }
            unset($this->spanStartTimes[$spanId]);

            // Log the error but continue execution
            logger()->warning('Tracer failed to create span', [
                'span_id' => $spanId,
                'error' => $e->getMessage(),
                'trace_id' => $this->currentTraceId
            ]);

            return '';
        }

        return $spanId;
    }

    /**
     * End an active span with success status.
     * Calculates duration and updates the database record.
     */
    public function endSpan(
        string $spanId,
        ?array $output = null,
        string $status = 'success'
    ): void {
        if (!$this->isEnabled() || empty($spanId) || !isset($this->spanStartTimes[$spanId])) {
            return;
        }

        $endTime = microtime(true);
        $startTime = $this->spanStartTimes[$spanId];
        $durationMs = round(($endTime - $startTime) * 1000);

        // Remove from tracking
        $this->removeSpanFromStack($spanId);
        unset($this->spanStartTimes[$spanId]);

        try {
            // Update the database record
            DB::table(config('agent-adk.tracing.table', 'agent_trace_spans'))
                ->where('span_id', $spanId)
                ->update([
                    'output' => $output ? json_encode($output) : null,
                    'status' => $status,
                    'end_time' => $endTime,
                    'duration_ms' => $durationMs,
                    'updated_at' => now(),
                ]);
        } catch (Throwable $e) {
            logger()->warning('Tracer failed to end span', [
                'span_id' => $spanId,
                'error' => $e->getMessage(),
                'trace_id' => $this->currentTraceId
            ]);
        }
    }

    /**
     * End a span with error status.
     * Convenience method for handling failures.
     */
    public function failSpan(string $spanId, Throwable $exception): void
    {
        if (!$this->isEnabled() || empty($spanId)) {
            return;
        }

        $endTime = microtime(true);
        $startTime = $this->spanStartTimes[$spanId] ?? $endTime;
        $durationMs = round(($endTime - $startTime) * 1000);

        // Remove from tracking
        $this->removeSpanFromStack($spanId);
        unset($this->spanStartTimes[$spanId]);

        try {
            // Update the database record with error information
            DB::table(config('agent-adk.tracing.table', 'agent_trace_spans'))
                ->where('span_id', $spanId)
                ->update([
                    'status' => 'error',
                    'error_message' => $exception->getMessage(),
                    'end_time' => $endTime,
                    'duration_ms' => $durationMs,
                    'updated_at' => now(),
                ]);
        } catch (Throwable $e) {
            logger()->warning('Tracer failed to mark span as failed', [
                'span_id' => $spanId,
                'error' => $e->getMessage(),
                'original_exception' => $exception->getMessage(),
                'trace_id' => $this->currentTraceId
            ]);
        }
    }

    /**
     * End the entire trace.
     * Updates the root span and cleans up trace state.
     */
    public function endTrace(?array $output = null, string $status = 'success'): void
    {
        if (!$this->isEnabled() || !$this->currentTraceId) {
            return;
        }

        // Find and end the root span (first span in the trace)
        try {
            $rootSpan = DB::table(config('agent-adk.tracing.table', 'agent_trace_spans'))
                ->where('trace_id', $this->currentTraceId)
                ->whereNull('parent_span_id')
                ->first();

            if ($rootSpan) {
                $this->endSpan($rootSpan->span_id, $output, $status);
            }
        } catch (Throwable $e) {
            logger()->warning('Tracer failed to end trace', [
                'trace_id' => $this->currentTraceId,
                'error' => $e->getMessage()
            ]);
        }

        // Clean up trace state
        $this->currentTraceId = null;
        $this->currentSessionId = null;
        $this->spanStack = [];
        $this->spanStartTimes = [];
    }

    /**
     * End the trace with error status.
     * Convenience method for handling trace-level failures.
     */
    public function failTrace(Throwable $exception): void
    {
        if (!$this->isEnabled() || !$this->currentTraceId) {
            return;
        }

        // End any remaining spans in the stack with error status
        while (!empty($this->spanStack)) {
            $spanId = array_pop($this->spanStack);
            $this->failSpan($spanId, $exception);
        }

        $this->endTrace(
            output: ['error' => $exception->getMessage()],
            status: 'error'
        );
    }

    /**
     * Get the current trace ID.
     */
    public function getCurrentTraceId(): ?string
    {
        return $this->currentTraceId;
    }

    /**
     * Get the current active span ID (top of stack).
     */
    public function getCurrentSpanId(): ?string
    {
        return empty($this->spanStack) ? null : end($this->spanStack);
    }

    /**
     * Get all spans for a given session ID.
     * Useful for retrieving complete execution traces.
     */
    public function getSpansForSession(string $sessionId): array
    {
        if (!$this->isEnabled()) {
            return [];
        }

        try {
            return DB::table(config('agent-adk.tracing.table', 'agent_trace_spans'))
                ->where('session_id', $sessionId)
                ->orderBy('start_time')
                ->get()
                ->toArray();
        } catch (Throwable $e) {
            logger()->warning('Tracer failed to retrieve spans for session', [
                'session_id' => $sessionId,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Get all spans for a given trace ID.
     * Returns spans in chronological order.
     */
    public function getSpansForTrace(string $traceId): array
    {
        if (!$this->isEnabled()) {
            return [];
        }

        try {
            return DB::table(config('agent-adk.tracing.table', 'agent_trace_spans'))
                ->where('trace_id', $traceId)
                ->orderBy('start_time')
                ->get()
                ->toArray();
        } catch (Throwable $e) {
            logger()->warning('Tracer failed to retrieve spans for trace', [
                'trace_id' => $traceId,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Clean up old trace data.
     * Removes traces older than the specified number of days.
     */
        /**
     * Clean up old trace data with optional progress callback.
     */
    public function cleanupOldTraces(int $days = 30, ?callable $progressCallback = null): int
    {
        if (!$this->isEnabled()) {
            return 0;
        }

        try {
            $cutoffDate = now()->subDays($days);
            $tableName = config('agent-adk.tracing.table', 'agent_trace_spans');

            // Get distinct trace IDs to delete
            $traceIds = DB::table($tableName)
                ->where('start_time', '<', $cutoffDate)
                ->distinct()
                ->pluck('trace_id')
                ->toArray();

            if (empty($traceIds)) {
                return 0;
            }

            $totalDeleted = 0;
            $batchSize = 1000;

            // Delete in batches to avoid memory issues
            foreach (array_chunk($traceIds, $batchSize) as $batch) {
                $deleted = DB::table($tableName)
                    ->whereIn('trace_id', $batch)
                    ->delete();

                $totalDeleted += $deleted;

                if ($progressCallback) {
                    $progressCallback($deleted);
                }
            }

            return $totalDeleted;
        } catch (Throwable $e) {
            logger()->warning('Tracer failed to cleanup old traces', [
                'days' => $days,
                'error' => $e->getMessage()
            ]);
            return 0;
        }
    }

    /**
     * Remove a span from the span stack.
     * Handles both exact position removal and cleanup.
     */
    protected function removeSpanFromStack(string $spanId): void
    {
        if (($key = array_search($spanId, $this->spanStack)) !== false) {
            unset($this->spanStack[$key]);
            $this->spanStack = array_values($this->spanStack); // Re-index array
        }
    }

    /**
     * Get the current session ID from the active trace context.
     * Fallback to extracting from the most recent span.
     */
    protected function getCurrentSessionId(): string
    {
        // Use the stored session ID from the current trace
        if ($this->currentSessionId) {
            return $this->currentSessionId;
        }

        // Try to get from the most recent span if available
        if (!empty($this->currentTraceId)) {
            try {
                $recentSpan = DB::table(config('agent-adk.tracing.table', 'agent_trace_spans'))
                    ->where('trace_id', $this->currentTraceId)
                    ->orderBy('start_time', 'desc')
                    ->first();

                if ($recentSpan && $recentSpan->session_id) {
                    return $recentSpan->session_id;
                }
            } catch (Throwable $e) {
                // Fall through to default
            }
        }

        return 'unknown-session';
    }

    /**
     * Determine the agent name for a span.
     * For sub-agent delegations, this might be different from the root agent.
     */
    protected function getCurrentAgentName(string $name, string $type): string
    {
        if ($type === 'sub_agent_delegation') {
            return $name; // Use the sub-agent name
        }

        // Try to get from the root span
        if (!empty($this->currentTraceId)) {
            try {
                $rootSpan = DB::table(config('agent-adk.tracing.table', 'agent_trace_spans'))
                    ->where('trace_id', $this->currentTraceId)
                    ->whereNull('parent_span_id')
                    ->first();

                if ($rootSpan && $rootSpan->agent_name) {
                    return $rootSpan->agent_name;
                }
            } catch (Throwable $e) {
                // Fall through to default
            }
        }

        return $name;
    }

    /**
     * Get count of traces older than specified days.
     */
    public function getOldTracesCount(int $days): int
    {
        if (!$this->isEnabled()) {
            return 0;
        }

        try {
            $cutoffDate = now()->subDays($days);

            return DB::table(config('agent-adk.tracing.table', 'agent_trace_spans'))
                ->where('start_time', '<', $cutoffDate)
                ->distinct('trace_id')
                ->count('trace_id');
        } catch (Throwable $e) {
            return 0;
        }
}
}
