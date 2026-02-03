<?php

namespace Vizra\VizraADK\Tests\Fixtures\Tools;

use RuntimeException;
use Vizra\VizraADK\Contracts\ToolInterface;
use Vizra\VizraADK\Memory\AgentMemory;
use Vizra\VizraADK\System\AgentContext;

/**
 * Test fixture: A tool that always fails.
 */
class FailingTool implements ToolInterface
{
    public function definition(): array
    {
        return [
            'name' => 'failing_tool',
            'description' => 'A tool that always fails',
            'parameters' => [
                'type' => 'object',
                'properties' => [],
            ],
        ];
    }

    public function execute(array $arguments, AgentContext $context, AgentMemory $memory): string
    {
        throw new RuntimeException('Tool execution failed intentionally');
    }
}
