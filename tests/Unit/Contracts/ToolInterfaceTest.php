<?php

use Vizra\VizraAdk\Contracts\ToolInterface;
use Vizra\VizraAdk\System\AgentContext;

it('implements tool interface contract correctly', function () {
    $tool = new TestTool();

    // Test that the tool implements the interface correctly
    expect($tool)->toBeInstanceOf(ToolInterface::class);

    // Test definition method returns proper structure
    $definition = $tool->definition();
    expect($definition)->toBeArray()
        ->toHaveKey('name')
        ->toHaveKey('description')
        ->toHaveKey('parameters');

    // Test execute method
    $context = new AgentContext('test-session');
    $arguments = ['input' => 'test_value'];

    $result = $tool->execute($arguments, $context);
    expect($result)->toBeString()->toBeJson();
});

it('has proper tool definition structure', function () {
    $tool = new TestTool();
    $definition = $tool->definition();

    // Verify the definition follows the expected schema
    expect($definition['name'])->toBe('test_tool');
    expect($definition['description'])->toBe('A test tool for unit testing');

    expect($definition['parameters'])->toHaveKey('type');
    expect($definition['parameters']['type'])->toBe('object');

    expect($definition['parameters'])->toHaveKey('properties');
    expect($definition['parameters'])->toHaveKey('required');
});

it('executes with context correctly', function () {
    $tool = new TestTool();
    $context = new AgentContext('test-session');
    $context->setState('test_state', 'state_value');

    $arguments = ['input' => 'hello world'];
    $result = $tool->execute($arguments, $context);

    $decoded = json_decode($result, true);
    expect($decoded)->toBeArray();
    expect($decoded['processed_input'])->toBe('hello world');
    expect($decoded['session_id'])->toBe('test-session');
});

it('handles empty arguments', function () {
    $tool = new TestTool();
    $context = new AgentContext('test-session');

    $result = $tool->execute([], $context);
    $decoded = json_decode($result, true);

    expect($decoded)->toBeArray()
        ->toHaveKey('processed_input');
    expect($decoded['processed_input'])->toBeNull();
});

it('handles invalid arguments', function () {
    $tool = new TestTool();
    $context = new AgentContext('test-session');

    $arguments = ['wrong_param' => 'value'];
    $result = $tool->execute($arguments, $context);
    $decoded = json_decode($result, true);

    expect($decoded)->toBeArray()
        ->toHaveKey('error');
});

/**
 * Test implementation of ToolInterface for testing purposes
 */
class TestTool implements ToolInterface
{
    public function definition(): array
    {
        return [
            'name' => 'test_tool',
            'description' => 'A test tool for unit testing',
            'parameters' => [
                'type' => 'object',
                'properties' => [
                    'input' => [
                        'type' => 'string',
                        'description' => 'Input text to process'
                    ]
                ],
                'required' => ['input']
            ]
        ];
    }

    public function execute(array $arguments, AgentContext $context): string
    {
        if (!isset($arguments['input']) && !empty(array_diff_key($arguments, ['input' => '']))) {
            return json_encode([
                'error' => 'Invalid arguments provided',
                'expected' => ['input'],
                'received' => array_keys($arguments)
            ]);
        }

        $input = $arguments['input'] ?? null;

        return json_encode([
            'processed_input' => $input,
            'session_id' => $context->getSessionId(),
            'tool_name' => 'test_tool',
            'timestamp' => now()->toISOString()
        ]);
    }
}
