<?php

namespace Vizra\VizraADK\Tests\Fixtures\Tools;

use Vizra\VizraADK\Contracts\ToolInterface;
use Vizra\VizraADK\Memory\AgentMemory;
use Vizra\VizraADK\System\AgentContext;

/**
 * Test fixture: Simulates fetching a user by ID.
 */
class FetchUserTool implements ToolInterface
{
    public function definition(): array
    {
        return [
            'name' => 'fetch_user',
            'description' => 'Fetch a user by ID',
            'parameters' => [
                'type' => 'object',
                'properties' => [
                    'user_id' => [
                        'type' => 'integer',
                        'description' => 'The user ID to fetch',
                    ],
                ],
                'required' => ['user_id'],
            ],
        ];
    }

    public function execute(array $arguments, AgentContext $context, AgentMemory $memory): string
    {
        $userId = $arguments['user_id'] ?? 0;

        if ($userId <= 0) {
            return json_encode(['error' => 'Invalid user ID']);
        }

        return json_encode([
            'id' => $userId,
            'name' => 'User '.$userId,
            'email' => "user{$userId}@example.com",
            'status' => 'active',
        ]);
    }
}
