<?php

namespace Vizra\VizraADK\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Class AgentMemory
 * Represents the cumulative memory and knowledge of an agent across all sessions.
 * This is the long-term storage that persists beyond individual conversations.
 *
 * @property int $id
 * @property string $agent_name
 * @property int|string|null $user_id
 * @property string|null $memory_summary
 * @property array|null $memory_data
 * @property array|null $key_learnings
 * @property int $total_sessions
 * @property Carbon|null $last_session_at
 * @property Carbon|null $memory_updated_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property \Illuminate\Database\Eloquent\Collection|AgentSession[] $sessions
 */
class AgentMemory extends Model
{
    protected $table = 'agent_memories'; // Default, will be configurable

    protected $fillable = [
        'agent_name',
        'user_id',
        'memory_summary',
        'memory_data',
        'key_learnings',
        'total_sessions',
        'last_session_at',
        'memory_updated_at',
    ];

    protected $casts = [
        'memory_data' => 'array',
        'key_learnings' => 'array',
        'last_session_at' => 'datetime',
        'memory_updated_at' => 'datetime',
    ];

    protected $attributes = [
        'total_sessions' => 0,
    ];

    /**
     * Get all sessions associated with this memory.
     */
    public function sessions(): HasMany
    {
        $relationship = $this->hasMany(AgentSession::class, 'agent_name', 'agent_name');

        // If this memory is user-specific, filter sessions by user_id
        if ($this->user_id !== null) {
            $relationship->where('user_id', $this->user_id);
        }

        return $relationship;
    }

    /**
     * Get recent sessions (last 10 by default).
     */
    public function recentSessions(int $limit = 10): HasMany
    {
        return $this->sessions()
            ->orderBy('updated_at', 'desc')
            ->limit($limit);
    }

    /**
     * Add a learning to the memory.
     */
    public function addLearning(string $learning): void
    {
        $learnings = $this->key_learnings ?? [];
        $learnings[] = [
            'learning' => $learning,
            'learned_at' => now()->toISOString(),
        ];

        $this->key_learnings = $learnings;
    }

    /**
     * Update memory data with new information.
     */
    public function updateMemoryData(array $newData): void
    {
        $memoryData = $this->memory_data ?? [];
        $this->memory_data = array_merge($memoryData, $newData);
    }

    /**
     * Increment session count and update last session timestamp.
     */
    public function recordSession(): void
    {
        $this->increment('total_sessions');
        $this->last_session_at = now();
        $this->save();
    }

    /**
     * Get a summary of this memory for context purposes.
     */
    public function getContextSummary(int $maxLength = 1000): string
    {
        $summary = '';

        if ($this->memory_summary) {
            $summary .= 'Previous Knowledge: '.$this->memory_summary."\n\n";
        }

        if ($this->key_learnings && count($this->key_learnings) > 0) {
            $summary .= "Key Learnings:\n";
            $recentLearnings = array_slice($this->key_learnings, -5); // Last 5 learnings
            foreach ($recentLearnings as $learning) {
                $summary .= '- '.$learning['learning']."\n";
            }
            $summary .= "\n";
        }

        if ($this->memory_data && count($this->memory_data) > 0) {
            $summary .= "Important Facts:\n";
            foreach (array_slice($this->memory_data, 0, 10) as $key => $value) {
                if (is_string($value)) {
                    $summary .= "- {$key}: {$value}\n";
                }
            }
        }

        // Truncate if too long
        if (strlen($summary) > $maxLength) {
            $summary = substr($summary, 0, $maxLength - 3).'...';
        }

        return trim($summary);
    }

    /**
     * Override table name from config if provided.
     */
    public function getTable()
    {
        return config('vizra-adk.tables.agent_memories', parent::getTable());
    }
}
