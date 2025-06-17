<?php

use Vizra\VizraADK\Contracts\ToolInterface;
use Vizra\VizraADK\System\AgentContext;
use Vizra\VizraADK\Memory\AgentMemory;
use Vizra\VizraADK\Agents\BaseLlmAgent;
use Mockery;

afterEach(function () {
    Mockery::close();
});

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
    $mockAgent = Mockery::mock(BaseLlmAgent::class);
    $mockAgent->shouldReceive('getName')->andReturn('test-agent');
    $memory = new AgentMemory($mockAgent);
    $arguments = ['input' => 'test_value'];

    $result = $tool->execute($arguments, $context, $memory);
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
    $mockAgent = Mockery::mock(BaseLlmAgent::class);
    $mockAgent->shouldReceive('getName')->andReturn('test-agent');
    $memory = new AgentMemory($mockAgent);

    $arguments = ['input' => 'hello world'];
    $result = $tool->execute($arguments, $context, $memory);

    $decoded = json_decode($result, true);
    expect($decoded)->toBeArray();
    expect($decoded['processed_input'])->toBe('hello world');
    expect($decoded['session_id'])->toBe('test-session');
});

it('handles empty arguments', function () {
    $tool = new TestTool();
    $context = new AgentContext('test-session');
    $mockAgent = Mockery::mock(BaseLlmAgent::class);
    $mockAgent->shouldReceive('getName')->andReturn('test-agent');
    $memory = new AgentMemory($mockAgent);

    $result = $tool->execute([], $context, $memory);
    $decoded = json_decode($result, true);

    expect($decoded)->toBeArray()
        ->toHaveKey('processed_input');
    expect($decoded['processed_input'])->toBeNull();
});

it('handles invalid arguments', function () {
    $tool = new TestTool();
    $context = new AgentContext('test-session');
    $mockAgent = Mockery::mock(BaseLlmAgent::class);
    $mockAgent->shouldReceive('getName')->andReturn('test-agent');
    $memory = new AgentMemory($mockAgent);

    $arguments = ['wrong_param' => 'value'];
    $result = $tool->execute($arguments, $context, $memory);
    $decoded = json_decode($result, true);

    expect($decoded)->toBeArray()
        ->toHaveKey('error');
});

it('can access memory in tool execution', function () {
    $tool = new MemoryAwareTestTool();
    $context = new AgentContext('test-session');
    $mockAgent = Mockery::mock(BaseLlmAgent::class);
    $mockAgent->shouldReceive('getName')->andReturn('test-agent');
    $memory = new AgentMemory($mockAgent);
    
    // Add some memory data
    $memory->addFact('Test fact about the user');
    $memory->addLearning('test_insight');
    
    $arguments = ['action' => 'read_memory'];
    $result = $tool->execute($arguments, $context, $memory);
    $decoded = json_decode($result, true);
    
    expect($decoded)->toBeArray()
        ->toHaveKey('facts')
        ->toHaveKey('learnings');
    expect($decoded['facts'])->toBeArray();
    expect($decoded['learnings'])->toBeArray();
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

    public function execute(array $arguments, AgentContext $context, AgentMemory $memory): string
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

/**
 * Memory-aware test tool for testing memory access
 */
class MemoryAwareTestTool implements ToolInterface
{
    public function definition(): array
    {
        return [
            'name' => 'memory_aware_tool',
            'description' => 'A test tool that can access agent memory',
            'parameters' => [
                'type' => 'object',
                'properties' => [
                    'action' => [
                        'type' => 'string',
                        'description' => 'Action to perform with memory'
                    ]
                ],
                'required' => ['action']
            ]
        ];
    }

    public function execute(array $arguments, AgentContext $context, AgentMemory $memory): string
    {
        $action = $arguments['action'] ?? 'default';
        
        if ($action === 'read_memory') {
            return json_encode([
                'facts' => $memory->getFacts()->toArray(),
                'learnings' => $memory->getLearnings()->toArray(),
                'summary' => $memory->getSummary(),
                'preferences' => $memory->getPreferences()->toArray()
            ]);
        }
        
        if ($action === 'write_memory') {
            $memory->addFact('Tool fact: Written by tool');
            $memory->addLearning('Tool learned something new');
            return json_encode([
                'success' => true,
                'message' => 'Memory updated by tool'
            ]);
        }
        
        return json_encode([
            'error' => 'Unknown action: ' . $action
        ]);
    }
}
