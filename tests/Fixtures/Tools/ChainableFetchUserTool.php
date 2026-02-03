<?php

namespace Vizra\VizraADK\Tests\Fixtures\Tools;

use Vizra\VizraADK\Contracts\ChainableToolInterface;
use Vizra\VizraADK\Memory\AgentMemory;
use Vizra\VizraADK\System\AgentContext;
use Vizra\VizraADK\Tools\Chaining\ChainableTool;

/**
 * Test fixture: A chainable version of the fetch user tool.
 */
class ChainableFetchUserTool implements ChainableToolInterface
{
    use ChainableTool;

    public function definition(): array
    {
        return [
            'name' => 'chainable_fetch_user',
            'description' => 'Fetch a user by ID (chainable)',
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

        return json_encode([
            'id' => $userId,
            'name' => 'User '.$userId,
            'email' => "user{$userId}@example.com",
        ]);
    }

    public function getInputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'user_id' => ['type' => 'integer'],
            ],
            'required' => ['user_id'],
        ];
    }

    public function getOutputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'id' => ['type' => 'integer'],
                'name' => ['type' => 'string'],
                'email' => ['type' => 'string'],
            ],
        ];
    }
}
