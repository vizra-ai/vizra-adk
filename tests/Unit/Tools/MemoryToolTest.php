<?php

use Vizra\VizraADK\Tools\MemoryTool;
use Vizra\VizraADK\System\AgentContext;
use Vizra\VizraADK\Models\AgentMemory;
use Vizra\VizraADK\Models\AgentSession;
use Vizra\VizraADK\Models\AgentMessage;
use Vizra\VizraADK\Services\MemoryManager;
use Vizra\VizraADK\Memory\AgentMemory as AgentMemoryClass;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Run migrations for testing
    $this->loadMigrationsFrom(__DIR__ . '/../../../database/migrations');

    $this->memoryManager = new MemoryManager();
    $this->memoryTool = new MemoryTool($this->memoryManager);
    
    // Create a mock agent for AgentMemory
    $this->mockAgent = Mockery::mock(\Vizra\VizraADK\Agents\BaseLlmAgent::class);
    $this->mockAgent->shouldReceive('getName')->andReturn('test-agent');
});

afterEach(function () {
    Mockery::close();
});

// Helper function to create context with agent name
function createContextWithAgent(string $agentName, string $input = 'test input'): AgentContext {
    $sessionId = (string) Str::uuid();
    $context = new AgentContext($sessionId, $input);
    $context->setState('agent_name', $agentName);
    return $context;
}

it('has correct tool definition', function () {
    $definition = $this->memoryTool->definition();

    expect($definition)->toBeArray();
    expect($definition)->toHaveKey('name');
    expect($definition)->toHaveKey('description');
    expect($definition)->toHaveKey('parameters');

    expect($definition['name'])->toBe('manage_memory');
    expect($definition['description'])->toContain('Manage long-term memory');

    $parameters = $definition['parameters'];
    expect($parameters)->toHaveKey('type');
    expect($parameters)->toHaveKey('properties');
    expect($parameters)->toHaveKey('required');

    expect($parameters['type'])->toBe('object');
    expect($parameters['required'])->toContain('action');

    $properties = $parameters['properties'];
    expect($properties)->toHaveKey('action');
    expect($properties['action']['enum'])->toContain('add_learning');
    expect($properties['action']['enum'])->toContain('add_fact');
    expect($properties['action']['enum'])->toContain('get_history');
    expect($properties['action']['enum'])->toContain('get_context');
});

it('can add learning via tool', function () {
    $agentName = 'test-agent';
    $context = createContextWithAgent($agentName);

    $arguments = [
        'action' => 'add_learning',
        'content' => 'Users prefer detailed explanations'
    ];

    $memory = new AgentMemoryClass($this->mockAgent);
    $result = $this->memoryTool->execute($arguments, $context, $memory);

    expect($result)->toBeString();
    expect($result)->toContain('Added learning to memory');

    // Verify learning was actually added
    $memory = AgentMemory::where('agent_name', $agentName)->first();
    expect($memory)->not->toBeNull();
    expect($memory->key_learnings)->toContain('Users prefer detailed explanations');
});

it('can add fact via tool', function () {
    $agentName = 'test-agent';
    $context = createContextWithAgent($agentName);

    $arguments = [
        'action' => 'add_fact',
        'key' => 'user_preference',
        'value' => 'concise responses'
    ];

    $memory = new AgentMemoryClass($this->mockAgent);
    $result = $this->memoryTool->execute($arguments, $context, $memory);

    expect($result)->toBeString();
    expect($result)->toContain('Added fact to memory');

    // Verify fact was actually added
    $memory = AgentMemory::where('agent_name', $agentName)->first();
    expect($memory)->not->toBeNull();
    expect($memory->memory_data)->toHaveKey('user_preference');
    expect($memory->memory_data['user_preference'])->toBe('concise responses');
});

it('can get memory context via tool', function () {
    $agentName = 'test-agent';
    $sessionId = (string) Str::uuid();

    // Setup some memory data
    $this->memoryManager->addLearning($agentName, 'Users like quick responses');
    $this->memoryManager->addFact($agentName, 'domain', 'customer_support');
    $this->memoryManager->updateSummary($agentName, 'Customer support specialist');
    $this->memoryManager->incrementSessionCount($agentName);

    $context = createContextWithAgent($agentName);

    $arguments = [
        'action' => 'get_context'
    ];

    $memory = new AgentMemoryClass($this->mockAgent);
    $result = $this->memoryTool->execute($arguments, $context, $memory);

    expect($result)->toBeString();
    expect($result)->toContain('Memory Summary: Customer support specialist');
    expect($result)->toContain('Users like quick responses');
    expect($result)->toContain('domain: customer_support');
    expect($result)->toContain('Total Sessions: 1');
});

it('can get conversation history via tool', function () {
    $agentName = 'test-agent';
    $context = createContextWithAgent($agentName);

    // Add conversation history to context
    $context->addMessage(['role' => 'user', 'content' => 'Hello, I need help']);
    $context->addMessage(['role' => 'assistant', 'content' => 'I can help you with that']);

    $arguments = [
        'action' => 'get_history'
    ];

    $memory = new AgentMemoryClass($this->mockAgent);
    $result = $this->memoryTool->execute($arguments, $context, $memory);

    expect($result)->toBeString();
    expect($result)->toContain('Recent conversation history');
    expect($result)->toContain('Hello, I need help');
    expect($result)->toContain('I can help you with that');
});

it('can get limited conversation history via tool', function () {
    $agentName = 'test-agent';
    $context = createContextWithAgent($agentName);

    // Add multiple messages to context
    for ($i = 1; $i <= 10; $i++) {
        $context->addMessage([
            'role' => $i % 2 === 1 ? 'user' : 'assistant',
            'content' => "Message number $i"
        ]);
    }

    $arguments = [
        'action' => 'get_history',
        'limit' => 3
    ];

    $memory = new AgentMemoryClass($this->mockAgent);
    $result = $this->memoryTool->execute($arguments, $context, $memory);

    expect($result)->toBeString();
    expect($result)->toContain('Recent conversation history (last 3 messages)');
    expect($result)->toContain('Message number 8');
    expect($result)->toContain('Message number 9');
    expect($result)->toContain('Message number 10');
    expect($result)->not->toContain('Message number 7');
});

it('handles missing required parameters gracefully', function () {
    $agentName = 'test-agent';
    $context = createContextWithAgent($agentName);

    // Missing action parameter
    $arguments = [];

    $memory = new AgentMemoryClass($this->mockAgent);
    $result = $this->memoryTool->execute($arguments, $context, $memory);

    expect($result)->toBeString();
    expect($result)->toContain('Error: action parameter is required');
});

it('handles invalid action gracefully', function () {
    $agentName = 'test-agent';
    $context = createContextWithAgent($agentName);

    $arguments = [
        'action' => 'invalid_action'
    ];

    $memory = new AgentMemoryClass($this->mockAgent);
    $result = $this->memoryTool->execute($arguments, $context, $memory);

    expect($result)->toBeString();
    expect($result)->toContain('Error: Unknown action');
});

it('handles add_learning with missing learning parameter', function () {
    $agentName = 'test-agent';
    $context = createContextWithAgent($agentName);

    $arguments = [
        'action' => 'add_learning'
        // Missing 'content' parameter
    ];

    $memory = new AgentMemoryClass($this->mockAgent);
    $result = $this->memoryTool->execute($arguments, $context, $memory);

    expect($result)->toBeString();
    expect($result)->toContain('Error: learning parameter is required');
});

it('handles add_fact with missing parameters', function () {
    $agentName = 'test-agent';
    $context = createContextWithAgent($agentName);

    // Missing 'key' parameter
    $arguments = [
        'action' => 'add_fact',
        'value' => 'some value'
    ];

    $memory = new AgentMemoryClass($this->mockAgent);
    $result = $this->memoryTool->execute($arguments, $context, $memory);

    expect($result)->toBeString();
    expect($result)->toContain('Error: key and value parameters are required');

    // Missing 'value' parameter
    $arguments = [
        'action' => 'add_fact',
        'key' => 'some_key'
    ];

    $memory = new AgentMemoryClass($this->mockAgent);
    $result = $this->memoryTool->execute($arguments, $context, $memory);

    expect($result)->toBeString();
    expect($result)->toContain('Error: key and value parameters are required');
});

it('handles empty memory context gracefully', function () {
    $agentName = 'non-existent-agent';
    $context = createContextWithAgent($agentName);

    $arguments = [
        'action' => 'get_context'
    ];

    $memory = new AgentMemoryClass($this->mockAgent);
    $result = $this->memoryTool->execute($arguments, $context, $memory);

    expect($result)->toBeString();
    expect($result)->toContain('Memory Summary: None');
    expect($result)->toContain('Key Learnings: None');
    expect($result)->toContain('Known Facts: None');
    expect($result)->toContain('Total Sessions: 0');
});

it('handles empty conversation history gracefully', function () {
    $agentName = 'new-agent';
    $context = createContextWithAgent($agentName);

    $arguments = [
        'action' => 'get_history'
    ];

    $memory = new AgentMemoryClass($this->mockAgent);
    $result = $this->memoryTool->execute($arguments, $context, $memory);

    expect($result)->toBeString();
    expect($result)->toContain('No conversation history found');
});

it('can handle different data types for fact values', function () {
    $agentName = 'test-agent';
    $context = createContextWithAgent($agentName);

    // Test with string value
    $arguments = [
        'action' => 'add_fact',
        'key' => 'string_fact',
        'value' => 'string value'
    ];

    $memory = new AgentMemoryClass($this->mockAgent);
    $result = $this->memoryTool->execute($arguments, $context, $memory);
    expect($result)->toContain('Added fact to memory');

    // Test with numeric value (as string since tool parameters are strings)
    $arguments = [
        'action' => 'add_fact',
        'key' => 'numeric_fact',
        'value' => '42'
    ];

    $memory = new AgentMemoryClass($this->mockAgent);
    $result = $this->memoryTool->execute($arguments, $context, $memory);
    expect($result)->toContain('Added fact to memory');

    // Verify both facts were stored
    $memory = AgentMemory::where('agent_name', $agentName)->first();
    expect($memory->memory_data['string_fact'])->toBe('string value');
    expect($memory->memory_data['numeric_fact'])->toBe('42');
});
