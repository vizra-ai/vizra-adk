<?php

namespace Vizra\VizraADK\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Class AgentInterrupt
 * Represents a human-in-the-loop interrupt/approval request.
 *
 * @property string $id
 * @property string $session_id
 * @property string|null $workflow_id
 * @property string|null $step_name
 * @property string $agent_name
 * @property string $type
 * @property string $reason
 * @property array|null $data
 * @property string $status
 * @property array|null $modifications
 * @property string|null $rejection_reason
 * @property string|null $user_response
 * @property string|null $resolved_by
 * @property \Illuminate\Support\Carbon|null $resolved_at
 * @property \Illuminate\Support\Carbon|null $expires_at
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 */
class AgentInterrupt extends Model
{
    use HasUlids;

    protected $table = 'agent_interrupts';

    protected $fillable = [
        'session_id',
        'workflow_id',
        'step_name',
        'agent_name',
        'type',
        'reason',
        'data',
        'status',
        'modifications',
        'rejection_reason',
        'user_response',
        'resolved_by',
        'resolved_at',
        'expires_at',
    ];

    protected $casts = [
        'data' => 'array',
        'modifications' => 'array',
        'resolved_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    /**
     * Interrupt types.
     */
    public const TYPE_APPROVAL = 'approval';
    public const TYPE_CONFIRMATION = 'confirmation';
    public const TYPE_INPUT = 'input';
    public const TYPE_FEEDBACK = 'feedback';

    /**
     * Interrupt statuses.
     */
    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_EXPIRED = 'expired';
    public const STATUS_CANCELLED = 'cancelled';

    /**
     * Get the session associated with this interrupt.
     */
    public function session(): BelongsTo
    {
        return $this->belongsTo(AgentSession::class, 'session_id', 'session_id');
    }

    /**
     * Check if the interrupt is pending.
     */
    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    /**
     * Check if the interrupt is approved.
     */
    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    /**
     * Check if the interrupt is rejected.
     */
    public function isRejected(): bool
    {
        return $this->status === self::STATUS_REJECTED;
    }

    /**
     * Check if the interrupt is expired.
     */
    public function isExpired(): bool
    {
        if ($this->status === self::STATUS_EXPIRED) {
            return true;
        }

        if ($this->expires_at && $this->expires_at->isPast()) {
            return true;
        }

        return false;
    }

    /**
     * Check if the interrupt is resolved (approved, rejected, or expired).
     */
    public function isResolved(): bool
    {
        return in_array($this->status, [
            self::STATUS_APPROVED,
            self::STATUS_REJECTED,
            self::STATUS_EXPIRED,
            self::STATUS_CANCELLED,
        ]);
    }

    /**
     * Scope to filter pending interrupts.
     */
    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    /**
     * Scope to filter non-expired interrupts.
     */
    public function scopeNotExpired($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('expires_at')
              ->orWhere('expires_at', '>', now());
        });
    }

    /**
     * Scope to filter by session.
     */
    public function scopeForSession($query, string $sessionId)
    {
        return $query->where('session_id', $sessionId);
    }

    /**
     * Scope to filter by agent.
     */
    public function scopeForAgent($query, string $agentName)
    {
        return $query->where('agent_name', $agentName);
    }

    /**
     * Scope to get active (pending and not expired) interrupts.
     */
    public function scopeActive($query)
    {
        return $query->pending()->notExpired();
    }

    /**
     * Override table name from config if provided.
     */
    public function getTable()
    {
        return config('vizra-adk.tables.agent_interrupts', parent::getTable());
    }
}
