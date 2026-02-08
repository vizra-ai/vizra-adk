<?php

namespace Vizra\VizraADK\Tests\Fixtures\Tools;

use Vizra\VizraADK\Contracts\ToolInterface;
use Vizra\VizraADK\Memory\AgentMemory;
use Vizra\VizraADK\System\AgentContext;

/**
 * Test fixture: Validates user data.
 */
class ValidateUserTool implements ToolInterface
{
    public function definition(): array
    {
        return [
            'name' => 'validate_user',
            'description' => 'Validate user data',
            'parameters' => [
                'type' => 'object',
                'properties' => [
                    'user_id' => [
                        'type' => 'integer',
                        'description' => 'The user ID to validate',
                    ],
                    'email' => [
                        'type' => 'string',
                        'description' => 'The email to validate',
                    ],
                ],
                'required' => ['user_id'],
            ],
        ];
    }

    public function execute(array $arguments, AgentContext $context, AgentMemory $memory): string
    {
        $userId = $arguments['user_id'] ?? 0;
        $email = $arguments['email'] ?? '';

        $errors = [];

        if ($userId <= 0) {
            $errors[] = 'Invalid user ID';
        }

        if (! empty($email) && ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Invalid email format';
        }

        return json_encode([
            'valid' => empty($errors),
            'user_id' => $userId,
            'errors' => $errors,
        ]);
    }
}
