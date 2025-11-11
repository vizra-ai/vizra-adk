<?php

namespace Vizra\VizraADK\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Class AgentMessage
 * Represents a single message within an agent session's conversation history.
 *
 * @property int $id
 * @property int $agent_session_id
 * @property string $role (user, assistant, tool_call, tool_result)
 * @property string|array $content (text, or JSON for tool call/result)
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
    ];

    protected $casts = [
        'content' => 'json', // Cast to json, handles string or array/object
    ];

    /**
     * Get the session this message belongs to.
     */
    public function session(): BelongsTo
    {
        return $this->belongsTo(AgentSession::class, 'agent_session_id');
    }

    /**
     * Override table name from config if provided.
     */
    public function getTable()
    {
        return config('vizra-adk.tables.agent_messages', parent::getTable());
    }
}
