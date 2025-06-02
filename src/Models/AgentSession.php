<?php

namespace AaronLumsden\LaravelAgentADK\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * Class AgentSession
 * Represents a single agent interaction session.
 *
 * @property int $id
 * @property string $session_id
 * @property int|null $user_id
 * @property string $agent_name
 * @property array $state_data
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 * @property \Illuminate\Database\Eloquent\Collection|AgentMessage[] $messages
 */
class AgentSession extends Model
{
    protected $table = 'agent_sessions'; // Default, will be configurable

    protected $fillable = [
        'session_id',
        'user_id',
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
     * Override table name from config if provided.
     */
    public function getTable()
    {
        return config('agent-adk.tables.agent_sessions', parent::getTable());
    }
}
