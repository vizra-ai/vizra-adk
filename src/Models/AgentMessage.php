<?php

namespace Vizra\VizraADK\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Class AgentMessage
 * Represents a single message within an agent session's conversation history.
 *
 * @property int $id
 * @property int $agent_session_id
 * @property string $role (user, assistant, tool_call, tool_result)
 * @property string|array $content (text, or JSON for tool call/result)
 * @property string $turn_uuid
 * @property int|null $user_message_id
 * @property int $variant_index
 * @property string|null $tool_name
 * @property AgentSession $session
 */
class AgentMessage extends Model
{
    protected $table = 'agent_messages'; // Default, will be configurable

    protected $fillable = [
        'agent_session_id',
        'role',
        'content',
        'tool_name',
        'turn_uuid',
        'user_message_id',
        'variant_index',
    ];

    protected $casts = [
        'content' => 'json', // Cast to json, handles string or array/object
        'variant_index' => 'integer',
    ];

    /**
     * Get the session this message belongs to.
     */
    public function session(): BelongsTo
    {
        return $this->belongsTo(AgentSession::class, 'agent_session_id');
    }

    /**
     * Get the originating user message for this variant.
     */
    public function userMessage(): BelongsTo
    {
        return $this->belongsTo(self::class, 'user_message_id');
    }

    /**
     * Get assistant variants that stem from this user message.
     */
    public function assistantVariants(): HasMany
    {
        return $this->hasMany(self::class, 'user_message_id')
            ->where('role', 'assistant')
            ->orderBy('variant_index');
    }

    /**
     * Scope a query to messages belonging to a specific turn.
     */
    public function scopeForTurn($query, string $turnUuid)
    {
        return $query->where('turn_uuid', $turnUuid)->orderBy('variant_index');
    }

    /**
     * Override table name from config if provided.
     */
    public function getTable()
    {
        return config('vizra-adk.tables.agent_messages', parent::getTable());
    }
}
