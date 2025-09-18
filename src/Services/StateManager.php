<?php

namespace Vizra\VizraADK\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Vizra\VizraADK\Models\AgentMessage;
use Vizra\VizraADK\Models\AgentSession;
use Vizra\VizraADK\System\AgentContext;

/**
 * Class StateManager
 * Manages loading and saving agent session state and conversation history.
 */
class StateManager
{
    protected MemoryManager $memoryManager;

    public function __construct(?MemoryManager $memoryManager = null)
    {
        $this->memoryManager = $memoryManager ?? app(MemoryManager::class);
    }

    /**
     * Load or create an AgentContext for a given session ID and agent name.
     * Now also includes memory context from previous sessions.
     *
     * @param  string  $agentName  The name of the agent.
     * @param  string|null  $sessionId  Optional session ID. If null, a new session is created.
     * @param  mixed|null  $userInput  Optional initial user input for a new context.
     * @param  int|null  $userId  Optional user ID for user-specific memory.
     */
    public function loadContext(string $agentName, ?string $sessionId = null, mixed $userInput = null, ?int $userId = null): AgentContext
    {
        $sessionId = $sessionId ?: (string) Str::uuid();
        $agentSession = AgentSession::firstOrCreate(
            ['session_id' => $sessionId, 'agent_name' => $agentName],
            ['state_data' => [], 'user_id' => $userId]
        );

        $history = AgentMessage::where('agent_session_id', $agentSession->id)
            ->orderBy('created_at', 'asc')
            ->orderBy('id', 'asc')
            ->get()
            ->map(fn ($msg) => [
                'id' => $msg->id,
                'role' => $msg->role,
                'content' => $msg->content, // Laravel's JSON cast already handles the conversion
                'tool_name' => $msg->tool_name,
                'turn_uuid' => $msg->turn_uuid,
                'user_message_id' => $msg->user_message_id,
                'variant_index' => $msg->variant_index,
                'hidden_from_prompt' => false,
            ]);

        $variantMax = [];
        foreach ($history as $message) {
            $turnUuid = $message['turn_uuid'] ?? null;
            if (! $turnUuid) {
                continue;
            }

            if (($message['role'] ?? null) === 'assistant') {
                $variantMax[$turnUuid] = max($variantMax[$turnUuid] ?? 0, $message['variant_index'] ?? 0);
            }
        }

        $history = $history->map(function ($message) use ($variantMax) {
            $turnUuid = $message['turn_uuid'] ?? null;
            $variantIndex = $message['variant_index'] ?? 0;

            if ($turnUuid && isset($variantMax[$turnUuid]) && $variantIndex < $variantMax[$turnUuid]) {
                if (($message['role'] ?? null) !== 'user') {
                    $message['hidden_from_prompt'] = true;
                }
            }

            return $message;
        });

        $context = new AgentContext(
            $sessionId,
            $userInput,
            $agentSession->state_data ?: [],
            $history
        );

        // Add memory context to the context state
        $memoryContext = $this->memoryManager->getMemoryContextArray($agentName, $userId);
        if (! empty($memoryContext['summary']) || ! empty($memoryContext['key_learnings']) || ! empty($memoryContext['facts'])) {
            $context->setState('memory_context', $memoryContext);
        }

        return $context;
    }

    /**
     * Save the state and conversation history from an AgentContext.
     *
     * @param  string  $agentName  The name of the agent (for creating session if it doesn't exist)
     * @param  bool  $updateMemory  Whether to update long-term memory after saving
     */
    public function saveContext(AgentContext $context, string $agentName, bool $updateMemory = true): void
    {
        $agentSession = null;

        DB::transaction(function () use ($context, $agentName, &$agentSession) {
            $agentSession = AgentSession::updateOrCreate(
                ['session_id' => $context->getSessionId(), 'agent_name' => $agentName],
                ['state_data' => $context->getAllState()]
            );

            // Get existing message count for this session to determine starting index
            $existingMessageCount = AgentMessage::where('agent_session_id', $agentSession->id)->count();

            // Get all messages from the context
            $allMessages = $context->getConversationHistory();

            if ($allMessages->count() > $existingMessageCount) {
                $newMessages = $allMessages->slice($existingMessageCount)->values();

                $historyCollection = $context->getConversationHistory();

                $turnUuids = $newMessages
                    ->pluck('turn_uuid')
                    ->filter()
                    ->unique()
                    ->values();

                $turnUserMap = AgentMessage::where('agent_session_id', $agentSession->id)
                    ->whereIn('turn_uuid', $turnUuids)
                    ->where('role', 'user')
                    ->get()
                    ->pluck('id', 'turn_uuid')
                    ->toArray();

                foreach ($newMessages as $index => $message) {
                    $turnUuid = $message['turn_uuid'] ?? (string) Str::uuid();
                    $role = $message['role'] ?? 'assistant';
                    $variantIndex = $message['variant_index'] ?? 0;

                    $payload = [
                        'agent_session_id' => $agentSession->id,
                        'role' => $role,
                        'content' => $message['content'] ?? '',
                        'tool_name' => $message['tool_name'] ?? null,
                        'turn_uuid' => $turnUuid,
                        'variant_index' => $variantIndex,
                    ];

                    if ($role === 'user') {
                        $payload['user_message_id'] = null;
                    } else {
                        $userMessageId = $message['user_message_id'] ?? null;

                        if (! $userMessageId && isset($turnUserMap[$turnUuid])) {
                            $userMessageId = $turnUserMap[$turnUuid];
                        }

                        $payload['user_message_id'] = $userMessageId;
                    }

                    $created = AgentMessage::create($payload);

                    if ($role === 'user') {
                        $turnUserMap[$turnUuid] = $created->id;
                    } elseif (! $payload['user_message_id'] && isset($turnUserMap[$turnUuid])) {
                        $created->user_message_id = $turnUserMap[$turnUuid];
                        $created->save();
                        $payload['user_message_id'] = $turnUserMap[$turnUuid];
                    }

                    $historyIndex = $existingMessageCount + $index;
                    $historyCollection->put($historyIndex, array_merge($message, [
                        'id' => $created->id,
                        'turn_uuid' => $turnUuid,
                        'variant_index' => $variantIndex,
                        'user_message_id' => $payload['user_message_id'],
                        'hidden_from_prompt' => $message['hidden_from_prompt'] ?? false,
                    ]));
                }
            }
        });

        // Update memory after the transaction completes
        if ($updateMemory && $agentSession) {
            // Check for memory updates in context state
            $memoryUpdates = $context->getState('memory_updates');
            if ($memoryUpdates) {
                // Apply memory updates
                if (isset($memoryUpdates['learnings'])) {
                    foreach ($memoryUpdates['learnings'] as $learning) {
                        $this->memoryManager->addLearning($agentName, $learning, $agentSession->user_id);
                    }
                }

                if (isset($memoryUpdates['facts'])) {
                    $this->memoryManager->updateMemoryData($agentName, $memoryUpdates['facts'], $agentSession->user_id);
                }

                if (isset($memoryUpdates['summary'])) {
                    $this->memoryManager->updateSummary($agentName, $memoryUpdates['summary'], $agentSession->user_id);
                }
            } else {
                // Default memory update from session
                $this->memoryManager->updateMemoryFromSession($agentSession);
            }
        }
    }

    /**
     * Prepare a context for regenerating a response for a specific turn.
     *
     * @return array{context: AgentContext, user_message: AgentMessage, next_variant_index: int}
     */
    public function prepareRegeneration(
        string $agentName,
        string $sessionId,
        string $turnUuid,
        ?int $userId = null
    ): array {
        $context = $this->loadContext($agentName, $sessionId, null, $userId);

        $agentSession = AgentSession::where('session_id', $context->getSessionId())
            ->where('agent_name', $agentName)
            ->firstOrFail();

        $userMessage = AgentMessage::where('agent_session_id', $agentSession->id)
            ->where('turn_uuid', $turnUuid)
            ->where('role', 'user')
            ->firstOrFail();

        $maxVariant = AgentMessage::where('agent_session_id', $agentSession->id)
            ->where('turn_uuid', $turnUuid)
            ->where('role', 'assistant')
            ->max('variant_index');

        $nextVariantIndex = is_null($maxVariant) ? 0 : $maxVariant + 1;

        $history = $context->getConversationHistory();
        $history->transform(function ($message) use ($turnUuid) {
            if (($message['turn_uuid'] ?? null) === $turnUuid && ($message['role'] ?? null) !== 'user') {
                $message['hidden_from_prompt'] = true;
            }

            return $message;
        });

        $context->useTurn($turnUuid, $nextVariantIndex);
        $context->setState('regenerating_turn_uuid', $turnUuid);
        $context->setState('regenerating_variant_index', $nextVariantIndex);

        return [
            'context' => $context,
            'user_message' => $userMessage,
            'next_variant_index' => $nextVariantIndex,
        ];
    }

    /**
     * Get memory context for an agent.
     */
    public function getMemoryContext(string $agentName, ?int $userId = null): string
    {
        return $this->memoryManager->getMemoryContext($agentName, $userId);
    }

    /**
     * Add a learning to memory.
     */
    public function addLearning(string $agentName, string $learning, ?int $userId = null): void
    {
        $this->memoryManager->addLearning($agentName, $learning, $userId);
    }

    /**
     * Update memory data.
     */
    public function updateMemoryData(string $agentName, array $data, ?int $userId = null): void
    {
        $this->memoryManager->updateMemoryData($agentName, $data, $userId);
    }
}
