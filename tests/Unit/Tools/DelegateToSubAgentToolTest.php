<?php

use Vizra\VizraADK\Tools\DelegateToSubAgentTool;
use Vizra\VizraADK\Agents\BaseLlmAgent;
use Vizra\VizraADK\System\AgentContext;
use Vizra\VizraADK\Memory\AgentMemory;

beforeEach(function () {
    $this->parentAgent = new TestDelegationParentAgent();
    $this->delegationTool = new DelegateToSubAgentTool($this->parentAgent);
    $this->context = new AgentContext('test-delegation-session');
    
    // Create a mock agent for AgentMemory
    $this->mockAgent = Mockery::mock(BaseLlmAgent::class);
    $this->mockAgent->shouldReceive('getName')->andReturn('test-agent');
});

afterEach(function () {
    Mockery::close();
});

it('has correct tool definition structure', function () {
    $definition = $this->delegationTool->definition();

    expect($definition)->toBeArray()
        ->and($definition['name'])->toBe('delegate_to_sub_agent')
        ->and($definition['description'])->toBeString()
        ->and($definition['parameters'])->toBeArray()
        ->and($definition['parameters']['type'])->toBe('object')
        ->and($definition['parameters']['properties'])->toBeArray()
        ->and($definition['parameters']['required'])->toBeArray();
});

it('includes available sub-agents in description', function () {
    $definition = $this->delegationTool->definition();

    expect($definition['description'])->toContain('specialist_a, specialist_b');
});

it('executes delegation with complete context transfer', function () {
    $arguments = [
        'sub_agent_name' => 'specialist_a',
        'task_input' => 'Complex technical problem',
        'context_summary' => 'Customer has been experiencing issues for 3 days'
    ];

    $memory = new AgentMemory($this->mockAgent);
    $result = $this->delegationTool->execute($arguments, $this->context, $memory);
    $decodedResult = json_decode($result, true);

    expect($decodedResult['success'])->toBeTrue()
        ->and($decodedResult['sub_agent'])->toBe('specialist_a')
        ->and($decodedResult['task_input'])->toBe('Complex technical problem')
        ->and($decodedResult['result'])->toContain('Complex technical problem');
});

it('creates separate context for sub-agent execution', function () {
    $arguments = [
        'sub_agent_name' => 'specialist_a',
        'task_input' => 'Test isolation',
        'context_summary' => 'Parent context info'
    ];

    // Add a message to parent context
    $this->context->addMessage(['role' => 'user', 'content' => 'Parent message']);

    $memory = new AgentMemory($this->mockAgent);
    $result = $this->delegationTool->execute($arguments, $this->context, $memory);
    $decodedResult = json_decode($result, true);

    expect($decodedResult['success'])->toBeTrue();

    // Parent context should remain unchanged (sub-agent uses separate context)
    $parentMessages = $this->context->getConversationHistory();
    expect($parentMessages)->toHaveCount(1)
        ->and($parentMessages[0]['content'])->toBe('Parent message');
});

it('handles sub-agent execution errors gracefully', function () {
    $parentAgentWithError = new TestDelegationParentAgentWithError();
    $delegationTool = new DelegateToSubAgentTool($parentAgentWithError);

    $arguments = [
        'sub_agent_name' => 'error_agent',
        'task_input' => 'This will cause an error'
    ];

    $memory = new AgentMemory($this->mockAgent);
    $result = $delegationTool->execute($arguments, $this->context, $memory);
    $decodedResult = json_decode($result, true);

    expect($decodedResult['success'])->toBeFalse()
        ->and($decodedResult['error'])->toContain('Sub-agent execution failed');
});

it('validates parameter types and formats', function () {
    // Test with array instead of string for sub_agent_name
    $arguments = [
        'sub_agent_name' => ['invalid'],
        'task_input' => 'Valid input'
    ];

    $memory = new AgentMemory($this->mockAgent);
    $result = $this->delegationTool->execute($arguments, $this->context, $memory);
    $decodedResult = json_decode($result, true);

    // Should handle gracefully, converting array to string
    expect($decodedResult)->toBeArray();
});

it('preserves context summary in sub-agent execution', function () {
    $contextSummary = 'Important background: Customer is VIP, issue affects production system';

    $arguments = [
        'sub_agent_name' => 'specialist_a',
        'task_input' => 'Urgent production issue',
        'context_summary' => $contextSummary
    ];

    $memory = new AgentMemory($this->mockAgent);
    $result = $this->delegationTool->execute($arguments, $this->context, $memory);
    $decodedResult = json_decode($result, true);

    expect($decodedResult['success'])->toBeTrue();
    // The TestSpecialistAgent should include context info in its response
    // This is a simplified test - in real implementation, we'd verify the sub-agent received the context
});

it('prevents excessive delegation depth to avoid recursion', function () {
    // Set delegation depth to maximum allowed
    $this->context->setState('delegation_depth', 5);

    $arguments = [
        'sub_agent_name' => 'test_specialist',
        'task_input' => 'Some task that would normally delegate',
    ];

    $memory = new AgentMemory($this->mockAgent);
    $result = $this->delegationTool->execute($arguments, $this->context, $memory);
    $decoded = json_decode($result, true);

    expect($decoded)->toBeArray()
        ->and($decoded['success'])->toBeFalse()
        ->and($decoded['error'])->toContain('Maximum delegation depth')
        ->and($decoded['current_depth'])->toBe(5)
        ->and($decoded['max_depth'])->toBe(5);
});

it('allows delegation within depth limits', function () {
    // Set delegation depth below maximum
    $this->context->setState('delegation_depth', 3);

    $arguments = [
        'sub_agent_name' => 'specialist_a',
        'task_input' => 'Task that should succeed',
    ];

    $memory = new AgentMemory($this->mockAgent);
    $result = $this->delegationTool->execute($arguments, $this->context, $memory);
    $decoded = json_decode($result, true);

    expect($decoded)->toBeArray()
        ->and($decoded['success'])->toBeTrue()
        ->and($decoded['sub_agent'])->toBe('specialist_a');
});

it('increments delegation depth for sub-agent context', function () {
    // Start with delegation depth of 2
    $this->context->setState('delegation_depth', 2);

    $arguments = [
        'sub_agent_name' => 'specialist_a',
        'task_input' => 'Task to test depth increment',
    ];

    // Create a mock sub-agent that can tell us about its context
    $mockSubAgent = $this->createMock(BaseLlmAgent::class);
    $mockSubAgent->method('run')->willReturnCallback(function ($input, $subContext) {
        // Verify the sub-agent context has incremented depth
        expect($subContext->getState('delegation_depth'))->toBe(3);
        return 'Sub-agent completed task with depth 3';
    });

    // Replace the specialist_a with our mock
    $reflection = new ReflectionClass($this->parentAgent);
    $property = $reflection->getProperty('loadedSubAgents');
    $property->setAccessible(true);
    $loadedSubAgents = $property->getValue($this->parentAgent);
    $loadedSubAgents['specialist_a'] = $mockSubAgent;
    $property->setValue($this->parentAgent, $loadedSubAgents);

    $memory = new AgentMemory($this->mockAgent);
    $result = $this->delegationTool->execute($arguments, $this->context, $memory);
    $decoded = json_decode($result, true);

    expect($decoded)->toBeArray()
        ->and($decoded['success'])->toBeTrue();
});

/**
 * Test agents for delegation tool testing
 */
class TestDelegationParentAgent extends BaseLlmAgent
{
    protected string $name = 'delegation-parent';
    protected string $description = 'Parent agent for delegation testing';
    protected string $instructions = 'Parent agent with delegation capabilities';
    protected string $model = 'gpt-4o';

    protected array $tools = [];

    protected function registerSubAgents(): array
    {
        return [
            'specialist_a' => TestSpecialistAgentA::class,
            'specialist_b' => TestSpecialistAgentB::class,
        ];
    }

    public function run(mixed $input, AgentContext $context): mixed
    {
        return "Parent processed: " . $input;
    }
}

class TestSpecialistAgentA extends BaseLlmAgent
{
    protected string $name = 'specialist-a';
    protected string $description = 'Specialist A for testing';
    protected string $instructions = 'Specialist A instructions';
    protected string $model = 'gpt-4o';

    protected array $tools = [];

    protected function registerSubAgents(): array
    {
        return [];
    }

    public function run(mixed $input, AgentContext $context): mixed
    {
        return "Specialist A handled: " . $input;
    }
}

class TestSpecialistAgentB extends BaseLlmAgent
{
    protected string $name = 'specialist-b';
    protected string $description = 'Specialist B for testing';
    protected string $instructions = 'Specialist B instructions';
    protected string $model = 'gpt-4o';

    protected array $tools = [];

    protected function registerSubAgents(): array
    {
        return [];
    }

    public function run(mixed $input, AgentContext $context): mixed
    {
        return "Specialist B handled: " . $input;
    }
}

class TestDelegationParentAgentWithError extends BaseLlmAgent
{
    protected string $name = 'error-parent';
    protected string $description = 'Parent agent that has error-prone sub-agents';
    protected string $instructions = 'Parent with error sub-agents';
    protected string $model = 'gpt-4o';

    protected array $tools = [];

    protected function registerSubAgents(): array
    {
        return [
            'error_agent' => TestErrorSubAgent::class,
        ];
    }

    public function run(mixed $input, AgentContext $context): mixed
    {
        return "Error parent: " . $input;
    }
}

class TestErrorSubAgent extends BaseLlmAgent
{
    protected string $name = 'error-sub';
    protected string $description = 'Sub-agent that throws errors';
    protected string $instructions = 'Error sub-agent';
    protected string $model = 'gpt-4o';

    protected array $tools = [];

    protected function registerSubAgents(): array
    {
        return [];
    }

    public function run(mixed $input, AgentContext $context): mixed
    {
        throw new \Exception('Simulated sub-agent error');
    }
}
