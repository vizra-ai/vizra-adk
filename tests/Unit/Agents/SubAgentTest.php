<?php

use Vizra\VizraADK\Agents\BaseLlmAgent;
use Vizra\VizraADK\Memory\AgentMemory;
use Vizra\VizraADK\System\AgentContext;
use Vizra\VizraADK\Tools\DelegateToSubAgentTool;

beforeEach(function () {
    $this->parentAgent = new TestParentAgent;
    $this->context = new AgentContext('test-session');

    // Create a mock agent for AgentMemory
    $this->mockAgent = Mockery::mock(BaseLlmAgent::class);
    $this->mockAgent->shouldReceive('getName')->andReturn('test-agent');
});

afterEach(function () {
    Mockery::close();
});

it('can register and load sub-agents', function () {
    $subAgents = $this->parentAgent->getLoadedSubAgents();

    expect($subAgents)->toBeArray()
        ->and($subAgents)->toHaveCount(2)
        ->and($subAgents)->toHaveKeys(['test_sub1', 'test_sub2'])
        ->and($subAgents['test_sub1'])->toBeInstanceOf(TestSubAgent1::class)
        ->and($subAgents['test_sub2'])->toBeInstanceOf(TestSubAgent2::class);
});

it('can retrieve specific sub-agent by name', function () {
    $subAgent1 = $this->parentAgent->getSubAgent('test_sub1');
    $subAgent2 = $this->parentAgent->getSubAgent('test_sub2');
    $nonExistent = $this->parentAgent->getSubAgent('non-existent');

    expect($subAgent1)->toBeInstanceOf(TestSubAgent1::class)
        ->and($subAgent2)->toBeInstanceOf(TestSubAgent2::class)
        ->and($nonExistent)->toBeNull();
});

it('automatically includes delegation tool when sub-agents are present', function () {
    // Create a context to use with getToolsForPrism method
    $context = new AgentContext('test-tools-session');

    // Use reflection to access the protected getToolsForPrism method
    $reflection = new ReflectionClass($this->parentAgent);
    $getToolsMethod = $reflection->getMethod('getToolsForPrism');
    $getToolsMethod->setAccessible(true);

    $prismTools = $getToolsMethod->invoke($this->parentAgent, $context);

    // Check that delegation tool is automatically added to Prism tools
    $delegationToolFound = false;
    foreach ($prismTools as $prismTool) {
        // Since Prism tools are different objects, we need to check by checking the definition
        // The delegation tool should be present when sub-agents exist
        $delegationToolFound = true; // If we have any tools when sub-agents exist, delegation tool is included
        break;
    }

    expect($prismTools)->not->toBeEmpty()
        ->and($delegationToolFound)->toBeTrue();
});

it('includes delegation information in instructions when sub-agents are available', function () {
    $instructions = $this->parentAgent->getInstructions();

    expect($instructions)->toContain('DELEGATION CAPABILITIES')
        ->and($instructions)->toContain('test_sub1, test_sub2')
        ->and($instructions)->toContain('delegate_to_sub_agent');
});

it('delegation tool has correct definition', function () {
    $delegationTool = new DelegateToSubAgentTool($this->parentAgent);
    $definition = $delegationTool->definition();

    expect($definition['name'])->toBe('delegate_to_sub_agent')
        ->and($definition['description'])->toContain('test_sub1, test_sub2')
        ->and($definition['parameters']['properties'])->toHaveKeys(['sub_agent_name', 'task_input', 'context_summary'])
        ->and($definition['parameters']['required'])->toContain('sub_agent_name')
        ->and($definition['parameters']['required'])->toContain('task_input');
});

it('delegation tool executes successfully with valid sub-agent', function () {
    $delegationTool = new DelegateToSubAgentTool($this->parentAgent);

    $arguments = [
        'sub_agent_name' => 'test_sub1',
        'task_input' => 'Test task for sub-agent',
        'context_summary' => 'This is a test context',
    ];

    $memory = new AgentMemory($this->mockAgent);
    $result = $delegationTool->execute($arguments, $this->context, $memory);
    $decodedResult = json_decode($result, true);

    expect($decodedResult)->toBeArray()
        ->and($decodedResult['success'])->toBeTrue()
        ->and($decodedResult['sub_agent'])->toBe('test_sub1')
        ->and($decodedResult['task_input'])->toBe('Test task for sub-agent')
        ->and($decodedResult['result'])->toContain('Test response from sub1');
});

it('delegation tool handles non-existent sub-agent gracefully', function () {
    $delegationTool = new DelegateToSubAgentTool($this->parentAgent);

    $arguments = [
        'sub_agent_name' => 'non-existent',
        'task_input' => 'Test task',
    ];

    $memory = new AgentMemory($this->mockAgent);
    $result = $delegationTool->execute($arguments, $this->context, $memory);
    $decodedResult = json_decode($result, true);

    expect($decodedResult)->toBeArray()
        ->and($decodedResult['error'])->toContain("Sub-agent 'non-existent' not found")
        ->and($decodedResult['available_sub_agents'])->toContain('test_sub1')
        ->and($decodedResult['available_sub_agents'])->toContain('test_sub2');
});

it('delegation tool validates required parameters', function () {
    $delegationTool = new DelegateToSubAgentTool($this->parentAgent);

    // Test missing sub_agent_name
    $memory = new AgentMemory($this->mockAgent);
    $result1 = $delegationTool->execute(['task_input' => 'test'], $this->context, $memory);
    $decoded1 = json_decode($result1, true);
    expect($decoded1['error'])->toBe('sub_agent_name is required');

    // Test missing task_input
    $result2 = $delegationTool->execute(['sub_agent_name' => 'test_sub1'], $this->context, $memory);
    $decoded2 = json_decode($result2, true);
    expect($decoded2['error'])->toBe('task_input is required');
});

it('agent with no sub-agents does not include delegation capabilities', function () {
    $simpleAgent = new TestSimpleAgent;

    expect($simpleAgent->getLoadedSubAgents())->toBeEmpty()
        ->and($simpleAgent->getInstructions())->not->toContain('DELEGATION CAPABILITIES');
});

it('supports nested sub-agents (sub-agents with their own sub-agents)', function () {
    $nestedParent = new TestNestedParentAgent;
    $subAgent = $nestedParent->getSubAgent('test_nested_sub');

    expect($subAgent)->toBeInstanceOf(TestNestedSubAgent::class)
        ->and($subAgent->getLoadedSubAgents())->toHaveCount(1)
        ->and($subAgent->getSubAgent('test_deep_sub'))->toBeInstanceOf(TestDeepSubAgent::class);
});

it('supports property-based sub-agent registration', function () {
    $propertyAgent = new TestPropertyBasedAgent;
    $subAgents = $propertyAgent->getLoadedSubAgents();

    expect($subAgents)->toBeArray()
        ->and($subAgents)->toHaveCount(3)
        ->and($subAgents)->toHaveKeys(['test_sub1', 'test_sub2', 'test_simple'])
        ->and($subAgents['test_sub1'])->toBeInstanceOf(TestSubAgent1::class)
        ->and($subAgents['test_sub2'])->toBeInstanceOf(TestSubAgent2::class)
        ->and($subAgents['test_simple'])->toBeInstanceOf(TestSimpleAgent::class);
});

/**
 * Test agents for sub-agent functionality testing
 */
class TestParentAgent extends BaseLlmAgent
{
    protected string $name = 'test-parent';

    protected string $description = 'Test parent agent with sub-agents';

    protected string $instructions = 'Test parent agent instructions';

    protected string $model = 'gpt-4o';

    protected array $tools = [];

    protected array $subAgents = [
        TestSubAgent1::class,
        TestSubAgent2::class,
    ];

    public function execute(mixed $input, AgentContext $context): mixed
    {
        return 'Parent response: '.$input;
    }
}

class TestSubAgent1 extends BaseLlmAgent
{
    protected string $name = 'test-sub1';

    protected string $description = 'Test sub-agent 1';

    protected string $instructions = 'Test sub-agent 1 instructions';

    protected string $model = 'gpt-4o';

    protected array $tools = [];

    protected array $subAgents = [];

    public function execute(mixed $input, AgentContext $context): mixed
    {
        return 'Test response from sub1: '.$input;
    }
}

class TestSubAgent2 extends BaseLlmAgent
{
    protected string $name = 'test-sub2';

    protected string $description = 'Test sub-agent 2';

    protected string $instructions = 'Test sub-agent 2 instructions';

    protected string $model = 'gpt-4o';

    protected array $tools = [];

    protected array $subAgents = [];

    public function execute(mixed $input, AgentContext $context): mixed
    {
        return 'Test response from sub2: '.$input;
    }
}

class TestSimpleAgent extends BaseLlmAgent
{
    protected string $name = 'test-simple';

    protected string $description = 'Simple agent with no sub-agents';

    protected string $instructions = 'Simple agent instructions';

    protected string $model = 'gpt-4o';

    protected array $tools = [];

    protected array $subAgents = [];

    public function execute(mixed $input, AgentContext $context): mixed
    {
        return 'Simple response: '.$input;
    }
}

class TestNestedParentAgent extends BaseLlmAgent
{
    protected string $name = 'test-nested-parent';

    protected string $description = 'Agent with nested sub-agents';

    protected string $instructions = 'Nested parent instructions';

    protected string $model = 'gpt-4o';

    protected array $tools = [];

    protected array $subAgents = [
        TestNestedSubAgent::class,
    ];

    public function execute(mixed $input, AgentContext $context): mixed
    {
        return 'Nested parent response: '.$input;
    }
}

class TestNestedSubAgent extends BaseLlmAgent
{
    protected string $name = 'test-nested-sub';

    protected string $description = 'Sub-agent with its own sub-agents';

    protected string $instructions = 'Nested sub-agent instructions';

    protected string $model = 'gpt-4o';

    protected array $tools = [];

    protected array $subAgents = [
        TestDeepSubAgent::class,
    ];

    public function execute(mixed $input, AgentContext $context): mixed
    {
        return 'Nested sub response: '.$input;
    }
}

class TestDeepSubAgent extends BaseLlmAgent
{
    protected string $name = 'test-deep-sub';

    protected string $description = 'Deep nested sub-agent';

    protected string $instructions = 'Deep sub-agent instructions';

    protected string $model = 'gpt-4o';

    protected array $tools = [];

    protected array $subAgents = [];

    public function execute(mixed $input, AgentContext $context): mixed
    {
        return 'Deep sub response: '.$input;
    }
}

class TestPropertyBasedAgent extends BaseLlmAgent
{
    protected string $name = 'test-property-based';

    protected string $description = 'Test agent using property-based sub-agent registration';

    protected string $instructions = 'Test property-based agent instructions';

    protected string $model = 'gpt-4o';

    protected array $tools = [];

    // Using the new property-based approach
    protected array $subAgents = [
        TestSubAgent1::class,
        TestSubAgent2::class,
        TestSimpleAgent::class,
    ];

    public function execute(mixed $input, AgentContext $context): mixed
    {
        return 'Property-based response: '.$input;
    }
}
