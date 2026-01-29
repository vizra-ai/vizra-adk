<?php

namespace Vizra\VizraADK\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;
use Vizra\VizraADK\System\AgentContext;
use Vizra\VizraADK\Traits\HasLogging;

/**
 * Tracer Service
 *
 * Manages the complete lifecycle of agent execution tracing.
 * Handles trace initialization, span creation, hierarchical relationships,
 * and timing measurements for debugging and performance analysis.
 */
class Tracer
{
    use HasLogging;
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
        $this->enabled = config('vizra-adk.tracing.enabled', true);
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
        if (! $this->isEnabled()) {
            return '';
        }

        // Store the current trace context if we're starting a sub-agent trace
        $parentTraceId = null;
        $parentSessionId = null;
        $parentSpanStack = [];
        $parentSpanStartTimes = [];
        
        if ($this->currentTraceId !== null) {
            // We're in a sub-agent execution, preserve parent context
            $parentTraceId = $this->currentTraceId;
            $parentSessionId = $this->currentSessionId;
            $parentSpanStack = $this->spanStack;
            $parentSpanStartTimes = $this->spanStartTimes;

            $this->logInfo('Preserving parent trace context', [
                'parent_trace_id' => $parentTraceId,
                'parent_session_id' => $parentSessionId,
                'parent_span_count' => count($parentSpanStack),
            ], 'traces');
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
                'initial_state_keys' => array_keys($context->getAllState()),
                'parent_trace_id' => $parentTraceId,
            ],
            context: $context
        );
        
        // Store parent context for restoration later
        if ($parentTraceId) {
            $context->setState('_parent_trace_context', [
                'trace_id' => $parentTraceId,
                'session_id' => $parentSessionId,
                'span_stack' => $parentSpanStack,
                'span_start_times' => $parentSpanStartTimes,
            ]);
        }

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
        ?array $metadata = null,
        ?AgentContext $context = null
    ): string {
        if (! $this->isEnabled() || ! $this->currentTraceId) {
            return '';
        }

        $spanId = Str::ulid()->toString();
        $parentSpanId = empty($this->spanStack) ? null : end($this->spanStack);
        $startTime = microtime(true);

        // Capture context state if provided
        if ($context !== null) {
            $metadata = $metadata ?? [];
            $contextState = $context->getAllState();
            
            // Remove internal tracking keys from captured state
            unset($contextState['execution_mode']);
            unset($contextState['llm_call_span_id']);
            
            // Remove tool call span IDs
            foreach ($contextState as $key => $value) {
                if (str_starts_with($key, 'tool_call_span_id_') || 
                    str_starts_with($key, 'sub_agent_delegation_span_id_')) {
                    unset($contextState[$key]);
                }
            }
            
            $metadata['context_state'] = $contextState;
        }

        // Add debugging information for tool calls
        if ($type === 'tool_call') {
            $this->logInfo('Starting tool call span', [
                'tool_name' => $name,
                'span_id' => $spanId,
                'input' => $input,
                'trace_id' => $this->currentTraceId,
            ], 'traces');
        }

        // Store start time and trace ID for duration calculation
        $this->spanStartTimes[$spanId] = [
            'start_time' => $startTime,
            'trace_id' => $this->currentTraceId
        ];

        // Push span onto stack to track hierarchy
        $this->spanStack[] = $spanId;

        try {
            // Create the database record
            DB::table(config('vizra-adk.tables.agent_trace_spans', 'agent_trace_spans'))->insert([
                'id' => Str::ulid()->toString(),
                'trace_id' => $this->currentTraceId,
                'parent_span_id' => $parentSpanId,
                'span_id' => $spanId,
                'session_id' => $this->getCurrentSessionId(),
                'agent_name' => $this->getCurrentAgentName($name, $type),
                'type' => $type,
                'name' => $name,
                'input' => $this->safeJsonEncode($input),
                'output' => null,
                'metadata' => $this->safeJsonEncode($metadata),
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
            $this->logWarning('Tracer failed to create span', [
                'span_id' => $spanId,
                'error' => $e->getMessage(),
                'trace_id' => $this->currentTraceId,
            ], 'traces');

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
        if (! $this->isEnabled() || empty($spanId) || ! isset($this->spanStartTimes[$spanId])) {
            // Add debugging information when failing to end a span
            $this->logWarning('Attempted to end span but failed condition check', [
                'span_id' => $spanId,
                'is_enabled' => $this->isEnabled(),
                'is_span_in_times' => isset($this->spanStartTimes[$spanId]),
                'trace_id' => $this->currentTraceId ?? 'no_trace',
            ], 'traces');

            return;
        }
        
        // Get span data
        $spanData = $this->spanStartTimes[$spanId];
        $startTime = is_array($spanData) ? $spanData['start_time'] : $spanData; // Handle both old and new format
        $traceId = is_array($spanData) ? $spanData['trace_id'] : $this->currentTraceId;
        
        // Debug log the output being saved
        $this->logInfo('Ending span with output', [
            'span_id' => $spanId,
            'output' => $output,
            'status' => $status,
            'trace_id' => $traceId,
            'current_trace_id' => $this->currentTraceId,
        ], 'traces');

        $endTime = microtime(true);
        $durationMs = round(($endTime - $startTime) * 1000);

        // Log tool call span completion
        $spanDetails = DB::table(config('vizra-adk.tables.agent_trace_spans', 'agent_trace_spans'))
            ->where('span_id', $spanId)
            ->first();

        if ($spanDetails && $spanDetails->type === 'tool_call') {
            $this->logInfo('Ending tool call span', [
                'tool_name' => $spanDetails->name,
                'span_id' => $spanId,
                'output' => $output,
                'duration_ms' => $durationMs,
                'trace_id' => $this->currentTraceId,
            ], 'traces');
        }

        try {
            $encodedOutput = $this->safeJsonEncode($output);

            // Log what we're about to save
            $this->logInfo('Updating span in database', [
                'span_id' => $spanId,
                'encoded_output' => $encodedOutput,
                'output_length' => strlen($encodedOutput ?? ''),
            ], 'traces');
            
            // Update the database record
            // Use the trace_id from when the span was created, not the current trace
            $updateData = [
                'output' => $encodedOutput,
                'status' => $status,
                'end_time' => $endTime,
                'duration_ms' => $durationMs,
                'updated_at' => now(),
            ];
            
            // Log the exact data being sent to database
            $this->logInfo('Database update data', [
                'span_id' => $spanId,
                'trace_id' => $traceId,
                'output_is_null' => $encodedOutput === null,
                'output_value' => $encodedOutput,
                'status' => $status,
            ], 'traces');
            
            $affected = DB::table(config('vizra-adk.tables.agent_trace_spans', 'agent_trace_spans'))
                ->where('span_id', $spanId)
                ->where('trace_id', $traceId)
                ->update($updateData);

            $this->logInfo('Span update result', [
                'span_id' => $spanId,
                'affected_rows' => $affected,
            ], 'traces');
            
            // Remove from tracking after successful update
            $this->removeSpanFromStack($spanId);
            unset($this->spanStartTimes[$spanId]);
        } catch (Throwable $e) {
            $this->logWarning('Tracer failed to end span', [
                'span_id' => $spanId,
                'error' => $e->getMessage(),
                'trace_id' => $this->currentTraceId,
            ], 'traces');
        }
    }

    /**
     * End a span with error status.
     * Convenience method for handling failures.
     */
    public function failSpan(string $spanId, Throwable $exception): void
    {
        if (! $this->isEnabled() || empty($spanId)) {
            return;
        }

        $endTime = microtime(true);
        $spanData = $this->spanStartTimes[$spanId] ?? null;
        $startTime = is_array($spanData) ? $spanData['start_time'] : ($spanData ?? $endTime);
        $durationMs = round(($endTime - $startTime) * 1000);

        try {
            // Update the database record with error information
            DB::table(config('vizra-adk.tables.agent_trace_spans', 'agent_trace_spans'))
                ->where('span_id', $spanId)
                ->update([
                    'status' => 'error',
                    'error_message' => $exception->getMessage(),
                    'end_time' => $endTime,
                    'duration_ms' => $durationMs,
                    'updated_at' => now(),
                ]);
                
            // Remove from tracking after update
            $this->removeSpanFromStack($spanId);
            unset($this->spanStartTimes[$spanId]);
        } catch (Throwable $e) {
            $this->logWarning('Tracer failed to mark span as failed', [
                'span_id' => $spanId,
                'error' => $e->getMessage(),
                'original_exception' => $exception->getMessage(),
                'trace_id' => $this->currentTraceId,
            ], 'traces');
        }
    }

    /**
     * End the entire trace.
     * Updates the root span and cleans up trace state.
     */
    public function endTrace(?array $output = null, string $status = 'success'): void
    {
        if (! $this->isEnabled() || ! $this->currentTraceId) {
            return;
        }

        // Find and end the root span (first span in the trace)
        try {
            $rootSpan = DB::table(config('vizra-adk.tables.agent_trace_spans', 'agent_trace_spans'))
                ->where('trace_id', $this->currentTraceId)
                ->whereNull('parent_span_id')
                ->first();

            if ($rootSpan) {
                $this->endSpan($rootSpan->span_id, $output, $status);
            }
        } catch (Throwable $e) {
            $this->logWarning('Tracer failed to end trace', [
                'trace_id' => $this->currentTraceId,
                'error' => $e->getMessage(),
            ], 'traces');
        }

        // Clean up trace state
        $this->currentTraceId = null;
        $this->currentSessionId = null;
        $this->spanStack = [];
        $this->spanStartTimes = [];
    }
    
    /**
     * Restore parent trace context after sub-agent execution
     */
    public function restoreParentContext(array $parentContext): void
    {
        if (! $this->isEnabled()) {
            return;
        }
        
        $this->currentTraceId = $parentContext['trace_id'];
        $this->currentSessionId = $parentContext['session_id'];
        $this->spanStack = $parentContext['span_stack'];
        $this->spanStartTimes = $parentContext['span_start_times'];

        $this->logInfo('Restored parent trace context', [
            'trace_id' => $this->currentTraceId,
            'session_id' => $this->currentSessionId,
            'span_count' => count($this->spanStack),
        ], 'traces');
    }

    /**
     * End the trace with error status.
     * Convenience method for handling trace-level failures.
     */
    public function failTrace(Throwable $exception): void
    {
        if (! $this->isEnabled() || ! $this->currentTraceId) {
            return;
        }

        // End any remaining spans in the stack with error status
        while (! empty($this->spanStack)) {
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
        if (! $this->isEnabled()) {
            return [];
        }

        try {
            return DB::table(config('vizra-adk.tables.agent_trace_spans', 'agent_trace_spans'))
                ->where('session_id', $sessionId)
                ->orderBy('start_time')
                ->get()
                ->toArray();
        } catch (Throwable $e) {
            $this->logWarning('Tracer failed to retrieve spans for session', [
                'session_id' => $sessionId,
                'error' => $e->getMessage(),
            ], 'traces');

            return [];
        }
    }

    /**
     * Get all spans for a given trace ID.
     * Returns spans in chronological order.
     */
    public function getSpansForTrace(string $traceId): array
    {
        if (! $this->isEnabled()) {
            return [];
        }

        try {
            return DB::table(config('vizra-adk.tables.agent_trace_spans', 'agent_trace_spans'))
                ->where('trace_id', $traceId)
                ->orderBy('start_time')
                ->get()
                ->toArray();
        } catch (Throwable $e) {
            $this->logWarning('Tracer failed to retrieve spans for trace', [
                'trace_id' => $traceId,
                'error' => $e->getMessage(),
            ], 'traces');

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
        if (! $this->isEnabled()) {
            return 0;
        }

        try {
            $cutoffDate = now()->subDays($days);
            $cutoffTimestamp = $cutoffDate->getTimestamp() + ($cutoffDate->micro / 1000000);
            $tableName = config('vizra-adk.tables.agent_trace_spans', 'agent_trace_spans');

            // Get distinct trace IDs to delete
            $traceIds = DB::table($tableName)
                ->where('start_time', '<', $cutoffTimestamp)
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
            $this->logWarning('Tracer failed to cleanup old traces', [
                'days' => $days,
                'error' => $e->getMessage(),
            ], 'traces');

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
        if (! empty($this->currentTraceId)) {
            try {
                $recentSpan = DB::table(config('vizra-adk.tables.agent_trace_spans', 'agent_trace_spans'))
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
        if (! empty($this->currentTraceId)) {
            try {
                $rootSpan = DB::table(config('vizra-adk.tables.agent_trace_spans', 'agent_trace_spans'))
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
        if (! $this->isEnabled()) {
            return 0;
        }

        try {
            $cutoffDate = now()->subDays($days);
            $cutoffTimestamp = $cutoffDate->getTimestamp() + ($cutoffDate->micro / 1000000);

            return DB::table(config('vizra-adk.tables.agent_trace_spans', 'agent_trace_spans'))
                ->where('start_time', '<', $cutoffTimestamp)
                ->distinct('trace_id')
                ->count('trace_id');
        } catch (Throwable $e) {
            return 0;
        }
    }

    /**
     * Safely encode data to JSON, handling edge cases
     * Ensures all types are properly converted to valid JSON
     */
    protected function safeJsonEncode($data): ?string
    {
        // Log what we're trying to encode
        $this->logInfo('safeJsonEncode called', [
            'data_type' => gettype($data),
            'data_is_null' => $data === null,
            'data_preview' => is_string($data) ? substr($data, 0, 100) : json_encode($data, JSON_PARTIAL_OUTPUT_ON_ERROR),
        ], 'traces');
        
        if ($data === null) {
            return json_encode(null);
        }

        try {
            // Special handling for numeric/boolean zero values that might cause issues
            if ($data === 0 || $data === 0.0 || $data === false) {
                return json_encode(['value' => $data, 'type' => gettype($data)]);
            }

            // If it's already a string and not JSON, wrap it in an object
            if (is_string($data) && ! $this->isJsonString($data)) {
                return json_encode(['value' => $data, 'type' => 'string']);
            }

            // Handle other scalar values
            if (is_scalar($data)) {
                return json_encode(['value' => $data, 'type' => gettype($data)]);
            }

            // Handle arrays and objects that might contain enums or complex structures
            if (is_array($data) || is_object($data)) {
                $preparedData = $this->prepareDataForJson($data);

                // Ensure the result is an object (for DB compatibility)
                if (is_array($preparedData) && empty($preparedData)) {
                    return json_encode((object) $preparedData);
                }

                $data = $preparedData;
            }

            // Try to encode the data
            $encoded = json_encode($data, JSON_INVALID_UTF8_SUBSTITUTE);

            // Check if encoding succeeded and returned a valid JSON string
            if ($encoded === false || $encoded === null) {
                // Log the encoding error
                $this->logWarning('JSON encoding failed in trace span', [
                    'error' => json_last_error_msg(),
                    'data_type' => gettype($data),
                    'data_value' => is_scalar($data) ? $data : '[complex_data]',
                ], 'traces');

                // Provide a fallback that's guaranteed to be valid JSON
                return json_encode([
                    'error' => 'Could not encode data',
                    'dataType' => gettype($data),
                    'errorCode' => json_last_error(),
                ]);
            }

            $this->logInfo('safeJsonEncode result', [
                'encoded_length' => strlen($encoded),
                'encoded_preview' => substr($encoded, 0, 100),
            ], 'traces');

            return $encoded;

        } catch (\Throwable $e) {
            $this->logWarning('Exception during JSON encoding in trace span', [
                'error' => $e->getMessage(),
                'data_type' => gettype($data),
            ], 'traces');

            return json_encode(['error' => 'Exception encoding data: '.$e->getMessage()]);
        }
    }

    /**
     * Recursively prepare data for JSON encoding by handling enums and other problematic types
     *
     * @param  mixed  $data  The data to prepare for JSON encoding
     * @return mixed The prepared data, safe for JSON encoding
     */
    protected function prepareDataForJson($data)
    {
        // Handle null values
        if ($data === null) {
            return null;
        }

        // Handle special zero/false values that might cause issues
        if ($data === 0 || $data === 0.0 || $data === false) {
            return $data;
        }

        // Handle PHP enums (PHP 8.1+)
        if ($data instanceof \BackedEnum) {
            return $data->value;
        }

        if ($data instanceof \UnitEnum) {
            return $data->name;
        }

        // Handle Generator objects (streaming responses)
        if ($data instanceof \Generator) {
            return '[Generator - streaming data]';
        }

        // Handle generic objects
        if (is_object($data)) {
            // Special handling for stringable objects
            if (method_exists($data, '__toString')) {
                return (string) $data;
            }

            // Handle DateTime objects
            if ($data instanceof \DateTime || $data instanceof \DateTimeInterface) {
                return $data->format('Y-m-d H:i:s');
            }

            // Handle generic objects by converting properties
            $result = [];
            foreach (get_object_vars($data) as $key => $value) {
                $result[$key] = $this->prepareDataForJson($value);
            }

            return (object) $result; // Return as object to maintain JSON object format
        }

        // Handle arrays recursively
        if (is_array($data)) {
            $result = [];
            foreach ($data as $key => $value) {
                $result[$key] = $this->prepareDataForJson($value);
            }

            return $result;
        }

        // Return scalar values as is
        return $data;
    }

    /**
     * Check if a string is already a valid JSON string
     */
    protected function isJsonString(string $str): bool
    {
        if (empty($str)) {
            return false;
        }

        $firstChar = substr($str, 0, 1);
        $lastChar = substr($str, -1);

        // Quick check for JSON object or array format
        if (($firstChar === '{' && $lastChar === '}') ||
            ($firstChar === '[' && $lastChar === ']')) {

            // Verify it's actually valid JSON
            json_decode($str);

            return json_last_error() === JSON_ERROR_NONE;
        }

        return false;
    }
}
