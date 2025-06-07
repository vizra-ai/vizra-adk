<?php

namespace AaronLumsden\LaravelAiADK\Tools;

use AaronLumsden\LaravelAiADK\Contracts\ToolInterface;
use AaronLumsden\LaravelAiADK\System\AgentContext;
use AaronLumsden\LaravelAiADK\Services\MemoryManager;

/**
 * Memory Management Tool
 * Allows agents to interact with their long-term memory system.
 */
class MemoryTool implements ToolInterface
{
    protected MemoryManager $memoryManager;

    public function __construct(MemoryManager $memoryManager)
    {
        $this->memoryManager = $memoryManager;
    }

    public function definition(): array
    {
        return [
            'name' => 'manage_memory',
            'description' => 'Manage long-term memory by adding learnings, facts, or retrieving past knowledge. Use this to remember important information across conversations.',
            'parameters' => [
                'type' => 'object',
                'properties' => [
                    'action' => [
                        'type' => 'string',
                        'enum' => ['add_learning', 'add_fact', 'get_context', 'get_history'],
                        'description' => 'The memory action to perform'
                    ],
                    'content' => [
                        'type' => 'string',
                        'description' => 'The learning content (required for add_learning)'
                    ],
                    'key' => [
                        'type' => 'string',
                        'description' => 'Key for storing facts (required for add_fact action)'
                    ],
                    'value' => [
                        'type' => 'string',
                        'description' => 'Value for storing facts (required for add_fact action)'
                    ],
                    'limit' => [
                        'type' => 'integer',
                        'description' => 'Number of recent messages to retrieve (for get_history, default: 10)',
                        'minimum' => 1,
                        'maximum' => 50
                    ]
                ],
                'required' => ['action']
            ]
        ];
    }

    public function execute(array $arguments, AgentContext $context): string
    {
        if (!isset($arguments['action'])) {
            return "Error: action parameter is required";
        }

        $action = $arguments['action'];
        $agentName = $context->getState('agent_name') ?? 'unknown';
        $userId = $context->getState('user_id') ?? null;

        try {
            switch ($action) {
                case 'add_learning':
                    if (empty($arguments['content'])) {
                        return "Error: learning parameter is required";
                    }

                    $this->memoryManager->addLearning($agentName, $arguments['content'], $userId);
                    return "Added learning to memory: " . $arguments['content'];

                case 'add_fact':
                    if (empty($arguments['key']) || empty($arguments['value'])) {
                        return "Error: key and value parameters are required";
                    }

                    $this->memoryManager->addFact($agentName, $arguments['key'], $arguments['value'], $userId);
                    return "Added fact to memory: {$arguments['key']} = {$arguments['value']}";

                case 'get_context':
                    $memoryContext = $this->memoryManager->getMemoryContextArray($agentName, $userId);

                    $summary = $memoryContext['summary'] ?? 'None';
                    $learnings = empty($memoryContext['key_learnings']) ? 'None' : implode('; ', $memoryContext['key_learnings']);
                    $facts = empty($memoryContext['facts']) ? 'None' : implode('; ', array_map(
                        fn($k, $v) => "$k: $v",
                        array_keys($memoryContext['facts']),
                        array_values($memoryContext['facts'])
                    ));
                    $totalSessions = $memoryContext['total_sessions'] ?? 0;

                    return "Memory Summary: {$summary}\nKey Learnings: {$learnings}\nKnown Facts: {$facts}\nTotal Sessions: {$totalSessions}";

                case 'get_history':
                    $limit = $arguments['limit'] ?? 10;
                    $history = $context->getConversationHistory();

                    if ($history->isEmpty()) {
                        return "No conversation history found for this session.";
                    }

                    $limitedHistory = $history->slice(-$limit);
                    $historyText = "Recent conversation history";
                    if ($limit < $history->count()) {
                        $historyText .= " (last {$limit} messages)";
                    }
                    $historyText .= ":\n";

                    foreach ($limitedHistory as $message) {
                        $role = ucfirst($message['role']);
                        $content = is_array($message['content']) ? json_encode($message['content']) : $message['content'];
                        $historyText .= "{$role}: {$content}\n";
                    }

                    return $historyText;

                default:
                    return "Error: Unknown action: {$action}";
            }
        } catch (\Exception $e) {
            return json_encode([
                'success' => false,
                'message' => 'Memory operation failed: ' . $e->getMessage()
            ]);
        }
    }
}
