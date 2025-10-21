<?php

namespace Vizra\VizraADK\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * Class AgentSession
 * Represents a single agent interaction session.
 *
 * @property int $id
 * @property string $session_id
 * @property int|string|null $user_id
 * @property string $agent_name
 * @property array $state_data
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 * @property \Illuminate\Database\Eloquent\Collection|AgentMessage[] $messages
 * @property AgentMemory|null $memory
 */
class AgentSession extends Model
{
    protected $table = 'agent_sessions'; // Default, will be configurable

    protected $fillable = [
        'session_id',
        'user_id',
        'agent_memory_id',
        'agent_name',
        'state_data',
    ];

    protected $casts = [
        'state_data' => 'array',
    ];

    /**
     * The "booted" method of the model.
     */
    protected static function booted(): void
    {
        static::creating(function ($session) {
            if (empty($session->session_id)) {
                $session->session_id = (string) Str::uuid();
            }
        });
    }

    /**
     * Get the messages associated with this agent session.
     */
    public function messages(): HasMany
    {
        return $this->hasMany(AgentMessage::class, 'agent_session_id');
    }

    /**
     * Get the memory this session contributes to.
     * Returns the memory for this agent and user (if user_id is set).
     */
    public function memory(): BelongsTo
    {
        return $this->belongsTo(AgentMemory::class, 'agent_memory_id');
    }

    /**
     * Get or create the memory for this session.
     */
    public function getOrCreateMemory(): AgentMemory
    {
        $memory = AgentMemory::firstOrCreate([
            'agent_name' => $this->agent_name,
            'user_id' => $this->user_id,
        ], [
            'memory_summary' => null,
            'memory_data' => [],
            'key_learnings' => [],
            'total_sessions' => 0,
            'last_session_at' => null,
            'memory_updated_at' => null,
        ]);

        // Link session to memory if not already linked
        if ($this->agent_memory_id === null) {
            $this->agent_memory_id = $memory->id;
            $this->save();
        }

        return $memory;
    }

    /**
     * Update the associated memory when this session is completed.
     */
    public function updateMemory(array $updates = []): void
    {
        $memory = $this->getOrCreateMemory();

        // Link session to memory if not already linked
        if ($this->agent_memory_id === null) {
            $this->agent_memory_id = $memory->id;
            $this->save();
        }

        // Apply updates if provided
        if (! empty($updates)) {
            if (isset($updates['learnings'])) {
                $memory->key_learnings = $updates['learnings'];
            }

            if (isset($updates['facts'])) {
                $memoryData = $memory->memory_data ?? [];
                $memory->memory_data = array_merge($memoryData, $updates['facts']);
            }

            if (isset($updates['summary'])) {
                $memory->memory_summary = $updates['summary'];
            }

            $memory->memory_updated_at = now();
            $memory->save();
        } else {
            // Default behavior - record session completion
            $memory->recordSession();
        }
    }

    /**
     * Override table name from config if provided.
     */
    public function getTable()
    {
        return config('vizra-adk.tables.agent_sessions', parent::getTable());
    }
}
