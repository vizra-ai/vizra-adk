<?php

namespace AaronLumsden\LaravelAgentADK\Services;

use AaronLumsden\LaravelAgentADK\System\AgentContext;
use AaronLumsden\LaravelAgentADK\Models\AgentSession;
use AaronLumsden\LaravelAgentADK\Models\AgentMessage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Collection;

/**
 * Class StateManager
 * Manages loading and saving agent session state and conversation history.
 */
class StateManager
{
    /**
     * Load or create an AgentContext for a given session ID and agent name.
     *
     * @param string $agentName The name of the agent.
     * @param string|null $sessionId Optional session ID. If null, a new session is created.
     * @param mixed|null $userInput Optional initial user input for a new context.
     * @return AgentContext
     */
    public function loadContext(string $agentName, ?string $sessionId = null, mixed $userInput = null): AgentContext
    {
        $sessionId = $sessionId ?: (string) Str::uuid();
        $agentSession = AgentSession::firstOrCreate(
            ['session_id' => $sessionId, 'agent_name' => $agentName],
            ['state_data' => []]
        );

        $history = AgentMessage::where('agent_session_id', $agentSession->id)
            ->orderBy('created_at', 'asc')
            ->get()
            ->map(fn ($msg) => [
                'role' => $msg->role,
                'content' => $msg->content, // Laravel's JSON cast already handles the conversion
                'tool_name' => $msg->tool_name
            ]);

        return new AgentContext(
            $sessionId,
            $userInput,
            $agentSession->state_data ?: [],
            $history
        );
    }

    /**
     * Save the state and conversation history from an AgentContext.
     *
     * @param AgentContext $context
     * @param string $agentName The name of the agent (for creating session if it doesn't exist)
     */
    public function saveContext(AgentContext $context, string $agentName): void
    {
        DB::transaction(function () use ($context, $agentName) {
            $agentSession = AgentSession::updateOrCreate(
                ['session_id' => $context->getSessionId(), 'agent_name' => $agentName],
                ['state_data' => $context->getAllState()]
            );

            // Efficiently update messages: delete existing for session and re-insert current history.
            // Could be optimized further for very long histories (e.g., only insert new messages),
            // but this is robust for MVP.
            AgentMessage::where('agent_session_id', $agentSession->id)->delete();

            $messagesToInsert = $context->getConversationHistory()->map(function (array $message) use ($agentSession): array {
                $content = $message['content'] ?? '';

                // Ensure content is never null - convert arrays/objects to JSON, ensure strings are not null
                if (is_array($content) || is_object($content)) {
                    $content = json_encode($content);
                } elseif ($content === null) {
                    $content = '';
                }

                return [
                    'agent_session_id' => $agentSession->id,
                    'role' => $message['role'],
                    'content' => $content,
                    'tool_name' => $message['tool_name'] ?? null
                ];
            })->all();

            if (!empty($messagesToInsert)) {
                AgentMessage::insert($messagesToInsert);
            }
        });
    }
}
