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
     * @param  int|string|null  $userId  Optional user identifier for user-specific memory.
     */
    public function loadContext(string $agentName, ?string $sessionId = null, mixed $userInput = null, int|string|null $userId = null): AgentContext
    {
        $sessionId = $sessionId ?: (string) Str::uuid();
        $agentSession = AgentSession::firstOrCreate(
            ['session_id' => $sessionId, 'agent_name' => $agentName],
            ['state_data' => [], 'user_id' => $userId]
        );

        $history = AgentMessage::where('agent_session_id', $agentSession->id)
            ->orderBy('created_at', 'asc')
            ->get()
            ->map(fn ($msg) => [
                'role' => $msg->role,
                'content' => $msg->content, // Laravel's JSON cast already handles the conversion
                'tool_name' => $msg->tool_name,
            ]);

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
                ['state_data' => $this->filterSerializableState($context->getAllState())]
            );

            // Get existing message count for this session to determine starting index
            $existingMessageCount = AgentMessage::where('agent_session_id', $agentSession->id)->count();
            
            // Get all messages from the context
            $allMessages = $context->getConversationHistory();
            
            // Only insert new messages (those beyond the existing count)
            if ($allMessages->count() > $existingMessageCount) {
                $newMessages = $allMessages->slice($existingMessageCount);
                
                $messagesToInsert = $newMessages->map(function ($message) use ($agentSession) {
                    $content = $message['content'] ?? '';

                    // Don't pre-process content - let the model's JSON cast handle it
                    return [
                        'agent_session_id' => $agentSession->id,
                        'role' => $message['role'],
                        'content' => $content, // Let the model cast handle JSON encoding
                        'tool_name' => $message['tool_name'] ?? null,
                    ];
                })->all();

                // Use model creation instead of insert to ensure casting is applied
                foreach ($messagesToInsert as $messageData) {
                    AgentMessage::create($messageData);
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
     * Get memory context for an agent.
     */
    public function getMemoryContext(string $agentName, int|string|null $userId = null): string
    {
        return $this->memoryManager->getMemoryContext($agentName, $userId);
    }

    /**
     * Add a learning to memory.
     */
    public function addLearning(string $agentName, string $learning, int|string|null $userId = null): void
    {
        $this->memoryManager->addLearning($agentName, $learning, $userId);
    }

    /**
     * Update memory data.
     */
    public function updateMemoryData(string $agentName, array $data, int|string|null $userId = null): void
    {
        $this->memoryManager->updateMemoryData($agentName, $data, $userId);
    }

    /**
     * Filter out non-serializable objects from state before database persistence.
     * Prism Image/Document objects cannot be JSON serialized and are stored separately as metadata.
     *
     * @param  array  $state  The full state array
     * @return array The filtered state safe for JSON serialization
     */
    protected function filterSerializableState(array $state): array
    {
        // Remove non-serializable Prism objects
        // These are kept in memory for immediate use during the request,
        // but should not be persisted to the database.
        // The metadata versions (prism_images_metadata, prism_documents_metadata)
        // contain the data needed to reconstruct these objects.
        unset(
            $state['prism_images'],
            $state['prism_documents']
        );

        return $state;
    }
}
