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
     * Set the content attribute, preventing double-encoding of already JSON-encoded strings.
     * This handles cases where AI providers return JSON-encoded tool results.
     *
     * @param  mixed  $value
     * @return void
     */
    public function setContentAttribute($value): void
    {
        // If value is already a JSON string (from AI provider), decode it once
        // to prevent double-encoding by Laravel's JSON cast
        if (is_string($value) && $this->isJson($value)) {
            // Decode the JSON string, then let Laravel's JSON cast re-encode it
            $decoded = json_decode($value, true);
            $this->attributes['content'] = json_encode($decoded);
        } else {
            // For everything else (plain strings, arrays, objects),
            // let Laravel's JSON cast handle it normally by encoding
            $this->attributes['content'] = json_encode($value);
        }
    }

    /**
     * Check if a string is valid JSON
     *
     * @param  string  $string
     * @return bool
     */
    protected function isJson(string $string): bool
    {
        if (empty($string)) {
            return false;
        }

        json_decode($string);

        return json_last_error() === JSON_ERROR_NONE;
    }

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
