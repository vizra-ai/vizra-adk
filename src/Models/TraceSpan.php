<?php

namespace Vizra\VizraAdk\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * TraceSpan Model
 *
 * Represents an individual span within an agent execution trace.
 * Spans can be hierarchically organized with parent-child relationships.
 */
class TraceSpan extends Model
{
    use HasUlids;

    protected $table = 'agent_trace_spans';

    protected $primaryKey = 'id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'trace_id',
        'parent_span_id',
        'span_id',
        'session_id',
        'agent_name',
        'type',
        'name',
        'input',
        'output',
        'metadata',
        'status',
        'error_message',
        'start_time',
        'end_time',
        'duration_ms',
    ];

    protected $casts = [
        'input' => 'array',
        'output' => 'array',
        'metadata' => 'array',
        'start_time' => 'decimal:6',
        'end_time' => 'decimal:6',
        'duration_ms' => 'integer',
    ];

    /**
     * Get the parent span of this span.
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_span_id', 'span_id');
    }

    /**
     * Get the child spans of this span.
     */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_span_id', 'span_id');
    }

    /**
     * Get all spans that belong to the same trace.
     */
    public function traceSpans(): HasMany
    {
        return $this->hasMany(self::class, 'trace_id', 'trace_id');
    }

    /**
     * Scope to filter spans by trace ID.
     */
    public function scopeForTrace($query, string $traceId)
    {
        return $query->where('trace_id', $traceId);
    }

    /**
     * Scope to filter spans by session ID.
     */
    public function scopeForSession($query, string $sessionId)
    {
        return $query->where('session_id', $sessionId);
    }

    /**
     * Scope to filter spans by type.
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Scope to filter spans by status.
     */
    public function scopeWithStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope to get root spans (no parent).
     */
    public function scopeRootSpans($query)
    {
        return $query->whereNull('parent_span_id');
    }

    /**
     * Scope to order spans chronologically.
     */
    public function scopeChronological($query)
    {
        return $query->orderBy('start_time');
    }

    /**
     * Check if this span has completed (has an end time).
     */
    public function isCompleted(): bool
    {
        return !is_null($this->end_time);
    }

    /**
     * Check if this span is in error status.
     */
    public function hasError(): bool
    {
        return $this->status === 'error';
    }

    /**
     * Check if this span is a root span.
     */
    public function isRoot(): bool
    {
        return is_null($this->parent_span_id);
    }

    /**
     * Get the duration in human-readable format.
     */
    public function getFormattedDuration(): string
    {
        if (is_null($this->duration_ms)) {
            return 'N/A';
        }

        if ($this->duration_ms < 1000) {
            return $this->duration_ms . 'ms';
        }

        return round($this->duration_ms / 1000, 2) . 's';
    }

    /**
     * Get the status with appropriate emoji.
     */
    public function getStatusIcon(): string
    {
        return match ($this->status) {
            'success' => '✅',
            'error' => '❌',
            'running' => '⏳',
            default => '❓'
        };
    }

    /**
     * Get the type with appropriate emoji.
     */
    public function getTypeIcon(): string
    {
        return match ($this->type) {
            'agent_run' => '🤖',
            'llm_call' => '🧠',
            'tool_call' => '🛠️',
            'sub_agent_delegation' => '👥',
            default => '📋'
        };
    }

    /**
     * Get a summary of the span for display.
     */
    public function getSummary(): string
    {
        $icon = $this->getTypeIcon();
        $status = $this->getStatusIcon();
        $duration = $this->getFormattedDuration();

        return "{$icon} {$this->type}: {$this->name} - {$duration} {$status}";
    }

    /**
     * Get the table name from configuration.
     */
    public function getTable()
    {
        return config('agent-adk.tracing.table', 'agent_trace_spans');
    }
}
