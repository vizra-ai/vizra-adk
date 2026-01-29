<?php

namespace Vizra\VizraADK\Services;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Vizra\VizraADK\Events\MemoryUpdated;
use Vizra\VizraADK\Models\AgentMemory;
use Vizra\VizraADK\Models\AgentSession;

/**
 * Class MemoryManager
 * Manages agent memory operations including retrieval, updating, and summarization.
 */
class MemoryManager
{
    /**
     * Get or create memory for an agent and user.
     */
    public function getOrCreateMemory(string $agentName, int|string|null $userId = null): AgentMemory
    {
        return AgentMemory::firstOrCreate([
            'agent_name' => $agentName,
            'user_id' => $userId,
        ], [
            'memory_summary' => null,
            'memory_data' => [],
            'key_learnings' => [],
            'total_sessions' => 0,
            'last_session_at' => null,
            'memory_updated_at' => null,
        ]);
    }

    /**
     * Update memory after a session completes.
     */
    public function updateMemoryFromSession(AgentSession $session): void
    {
        $memory = $this->getOrCreateMemory($session->agent_name, $session->user_id);

        // Record the session
        $memory->recordSession();

        // Extract insights from the session
        $this->extractSessionInsights($memory, $session);

        // Update memory timestamp
        $memory->memory_updated_at = now();
        $memory->save();
    }

    /**
     * Get memory context for an agent to include in their instructions.
     */
    public function getMemoryContext(string $agentName, int|string|null $userId = null, int $maxLength = 1000): string
    {
        $memoryQuery = AgentMemory::where('agent_name', $agentName);
        $this->applyUserScope($memoryQuery, $userId);

        $memory = $memoryQuery->first();

        if (! $memory) {
            return '';
        }

        return $memory->getContextSummary($maxLength);
    }

    /**
     * Get memory context as an array for testing and programmatic access.
     */
    public function getMemoryContextArray(string $agentName, int|string|null $userId = null): array
    {
        $memoryQuery = AgentMemory::where('agent_name', $agentName);
        $this->applyUserScope($memoryQuery, $userId);

        $memory = $memoryQuery->first();

        if (! $memory) {
            return [
                'summary' => null,
                'key_learnings' => [],
                'facts' => [],
                'total_sessions' => 0,
            ];
        }

        return [
            'summary' => $memory->memory_summary,
            'key_learnings' => $memory->key_learnings ?? [],
            'facts' => $memory->memory_data ?? [],
            'total_sessions' => $memory->total_sessions,
        ];
    }

    /**
     * Summarize recent sessions and update memory summary.
     */
    public function summarizeMemory(AgentMemory $memory, int $recentSessionsCount = 10): void
    {
        $recentSessions = $memory->sessions()
            ->with('messages')
            ->orderBy('updated_at', 'desc')
            ->limit($recentSessionsCount)
            ->get();

        if ($recentSessions->isEmpty()) {
            return;
        }

        // Build a summary from recent conversations
        $conversationTexts = [];

        foreach ($recentSessions as $session) {
            $sessionText = $this->extractSessionSummary($session);
            if (! empty($sessionText)) {
                $conversationTexts[] = $sessionText;
            }
        }

        if (! empty($conversationTexts)) {
            // For now, we'll create a simple concatenated summary
            // In a real implementation, you might use an LLM to create a better summary
            $summary = implode("\n\n", array_slice($conversationTexts, 0, 5));

            // Truncate if too long
            if (strlen($summary) > 2000) {
                $summary = substr($summary, 0, 1997).'...';
            }

            $memory->memory_summary = $summary;
            $memory->memory_updated_at = now();
            $memory->save();
        }
    }

    /**
     * Add a learning to memory.
     */
    public function addLearning(string $agentName, string $learning, int|string|null $userId = null): void
    {
        $memory = $this->getOrCreateMemory($agentName, $userId);

        $learnings = $memory->key_learnings ?? [];
        if (! in_array($learning, $learnings)) {
            $learnings[] = $learning;
            $memory->key_learnings = $learnings;
            $memory->memory_updated_at = now();
            $memory->save();

            // Dispatch event
            event(new MemoryUpdated($memory, null, 'learning_added'));
        }
    }

    /**
     * Update memory data with facts or preferences.
     */
    public function updateMemoryData(string $agentName, array $data, int|string|null $userId = null): void
    {
        $memory = $this->getOrCreateMemory($agentName, $userId);

        $memoryData = $memory->memory_data ?? [];
        $memoryData = array_merge($memoryData, $data);

        $memory->memory_data = $memoryData;
        $memory->memory_updated_at = now();
        $memory->save();

        // Dispatch event
        event(new MemoryUpdated($memory, null, 'data_updated'));
    }

    /**
     * Add a fact to memory data.
     */
    public function addFact(string $agentName, string $key, $value, int|string|null $userId = null): void
    {
        $memory = $this->getOrCreateMemory($agentName, $userId);

        $memoryData = $memory->memory_data ?? [];
        $memoryData[$key] = $value;

        $memory->memory_data = $memoryData;
        $memory->memory_updated_at = now();
        $memory->save();

        // Dispatch event
        event(new MemoryUpdated($memory, null, 'fact_added'));
    }

    /**
     * Update memory summary.
     */
    public function updateSummary(string $agentName, string $summary, int|string|null $userId = null): void
    {
        $memory = $this->getOrCreateMemory($agentName, $userId);

        $memory->memory_summary = $summary;
        $memory->memory_updated_at = now();
        $memory->save();

        // Dispatch event
        event(new MemoryUpdated($memory, null, 'summary_updated'));
    }

    /**
     * Get recent conversations from memory.
     */
    public function getRecentConversations(string $agentName, int|string|null $userId = null, int $limit = 5): Collection
    {
        $memoryQuery = AgentMemory::where('agent_name', $agentName);
        $this->applyUserScope($memoryQuery, $userId);

        $memory = $memoryQuery->first();

        if (! $memory) {
            return collect();
        }

        return $memory->sessions()
            ->with('messages')
            ->orderBy('updated_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Clean up old memories based on age or session count.
     */
    public function cleanupOldMemories(int $daysOld = 90, int $maxSessions = 1000): int
    {
        $cutoffDate = Carbon::now()->subDays($daysOld);

        $deleted = AgentMemory::where(function ($query) use ($cutoffDate, $maxSessions) {
            $query->where('last_session_at', '<', $cutoffDate)
                ->orWhere('total_sessions', '>', $maxSessions);
        })->delete();

        return $deleted;
    }

    /**
     * Increment the session count for an agent's memory.
     */
    public function incrementSessionCount(string $agentName, int|string|null $userId = null): void
    {
        $memory = $this->getOrCreateMemory($agentName, $userId);

        $memory->total_sessions = ($memory->total_sessions ?? 0) + 1;
        $memory->last_session_at = now();
        $memory->memory_updated_at = now();
        $memory->save();

        // Dispatch event
        event(new MemoryUpdated($memory, null, 'session_incremented'));
    }

    /**
     * Extract insights and learnings from a completed session.
     */
    protected function extractSessionInsights(AgentMemory $memory, AgentSession $session): void
    {
        // Simple implementation - extract user preferences or important facts
        // In a real implementation, you might use NLP or LLM to extract insights

        $messages = $session->messages()->get();

        foreach ($messages as $message) {
            if ($message->role === 'user' && is_string($message->content)) {
                // Look for preference patterns
                if (preg_match('/I (like|prefer|love|hate|dislike) (.+)/i', $message->content, $matches)) {
                    $preference = trim($matches[2]);
                    $sentiment = in_array(strtolower($matches[1]), ['like', 'prefer', 'love']) ? 'positive' : 'negative';

                    $memory->updateMemoryData([
                        'preferences.'.md5($preference) => [
                            'item' => $preference,
                            'sentiment' => $sentiment,
                            'learned_at' => $session->updated_at->toISOString(),
                        ],
                    ]);
                }

                // Look for personal information
                if (preg_match('/my name is (.+)/i', $message->content, $matches)) {
                    $name = trim($matches[1]);
                    $memory->updateMemoryData([
                        'personal_info.name' => $name,
                    ]);
                }
            }
        }
    }

    /**
     * Extract a summary from a session.
     */
    protected function extractSessionSummary(AgentSession $session): string
    {
        $messages = $session->messages()->orderBy('created_at', 'asc')->get();

        if ($messages->isEmpty()) {
            return '';
        }

        // Get first user message and last assistant message for context
        $firstUserMessage = $messages->where('role', 'user')->first();
        $lastAssistantMessage = $messages->where('role', 'assistant')->last();

        $summary = "Session {$session->session_id} ({$session->updated_at->format('Y-m-d')}):\n";

        if ($firstUserMessage) {
            $userContent = is_string($firstUserMessage->content) ? $firstUserMessage->content : 'Complex interaction';
            $summary .= 'User: '.substr($userContent, 0, 100).(strlen($userContent) > 100 ? '...' : '')."\n";
        }

        if ($lastAssistantMessage) {
            $assistantContent = is_string($lastAssistantMessage->content) ? $lastAssistantMessage->content : 'Complex response';
            $summary .= 'Agent: '.substr($assistantContent, 0, 100).(strlen($assistantContent) > 100 ? '...' : '');
        }

        return $summary;
    }

    /**
     * Get conversation history for an agent across all sessions.
     */
    public function getConversationHistory(string $agentName, int $limit = 50): array
    {
        $sessions = AgentSession::where('agent_name', $agentName)
            ->with('messages')
            ->orderBy('updated_at', 'desc')
            ->take(10) // Get recent sessions
            ->get();

        $messages = collect();

        foreach ($sessions as $session) {
            $sessionMessages = $session->messages()
                ->orderBy('created_at', 'asc')
                ->get();

            $messages = $messages->concat($sessionMessages);
        }

        return $messages
            ->sortBy('created_at')
            ->take($limit)
            ->map(function ($message) {
                return [
                    'role' => $message->role,
                    'content' => $message->content,
                    'created_at' => $message->created_at->toISOString(),
                ];
            })
            ->values()
            ->toArray();
    }

    /**
     * Clean up old sessions while preserving memory.
     */
    public function cleanupOldSessions(string $agentName, int $daysOld = 30): int
    {
        $cutoffDate = now()->subDays($daysOld);

        $oldSessions = AgentSession::where('agent_name', $agentName)
            ->where('updated_at', '<', $cutoffDate)
            ->get();

        $deletedCount = 0;

        foreach ($oldSessions as $session) {
            // Delete associated messages first
            $session->messages()->delete();

            // Delete the session
            $session->delete();

            $deletedCount++;
        }

        return $deletedCount;
    }

    /**
     * Apply a user identifier scope to the given query builder.
     */
    protected function applyUserScope(Builder $query, int|string|null $userId): void
    {
        if ($userId === null) {
            $query->whereNull('user_id');

            return;
        }

        $query->where('user_id', $userId);
    }
}
