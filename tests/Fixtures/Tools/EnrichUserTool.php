<?php

namespace Vizra\VizraADK\Tests\Fixtures\Tools;

use Vizra\VizraADK\Contracts\ToolInterface;
use Vizra\VizraADK\Memory\AgentMemory;
use Vizra\VizraADK\System\AgentContext;

/**
 * Test fixture: Enriches user data with additional information.
 */
class EnrichUserTool implements ToolInterface
{
    public function definition(): array
    {
        return [
            'name' => 'enrich_user',
            'description' => 'Enrich user data with additional information',
            'parameters' => [
                'type' => 'object',
                'properties' => [
                    'user_id' => [
                        'type' => 'integer',
                        'description' => 'The user ID to enrich',
                    ],
                    'name' => [
                        'type' => 'string',
                        'description' => 'The user name',
                    ],
                ],
                'required' => ['user_id'],
            ],
        ];
    }

    public function execute(array $arguments, AgentContext $context, AgentMemory $memory): string
    {
        $userId = $arguments['user_id'] ?? 0;
        $name = $arguments['name'] ?? 'Unknown';

        return json_encode([
            'user_id' => $userId,
            'name' => $name,
            'enriched' => true,
            'preferences' => [
                'theme' => 'dark',
                'notifications' => true,
            ],
            'metadata' => [
                'enriched_at' => '2024-01-01T00:00:00Z',
                'version' => '1.0',
            ],
        ]);
    }
}
