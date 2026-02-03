<?php

use Illuminate\Support\Facades\Gate;
use Prism\Prism\PrismManager;
use Vizra\VizraADK\Agents\BaseLlmAgent;
use Vizra\VizraADK\Contracts\ToolboxInterface;
use Vizra\VizraADK\Contracts\ToolInterface;
use Vizra\VizraADK\Memory\AgentMemory;
use Vizra\VizraADK\System\AgentContext;
use Vizra\VizraADK\Toolboxes\BaseToolbox;

/**
 * Agent Toolbox Integration Tests
 *
 * Tests that verify toolboxes integrate correctly with BaseLlmAgent.
 */

beforeEach(function () {
    $this->app[PrismManager::class]->extend('mock', function () {
        return new class extends \Prism\Prism\Providers\Provider {};
    });
});

afterEach(function () {
    Mockery::close();
});

describe('BaseLlmAgent Toolbox Support', function () {
    it('has toolboxes property', function () {
        $agent = new AgentWithToolboxes();

        $reflection = new ReflectionClass($agent);
        expect($reflection->hasProperty('toolboxes'))->toBeTrue();
    });

    it('loads tools from toolboxes during loadTools', function () {
        $agent = new AgentWithToolboxes();
        $agent->loadTools();

        $loadedTools = $agent->getLoadedTools();

        // Should have both direct tool and toolbox tools
        expect($loadedTools)->toHaveKey('direct_test_tool');
        expect($loadedTools)->toHaveKey('integration_tool_a');
        expect($loadedTools)->toHaveKey('integration_tool_b');
    });

    it('respects toolbox authorization', function () {
        // Define gate to deny access
        Gate::define('restricted-tools', fn() => false);

        $agent = new AgentWithGatedToolbox();
        $context = new AgentContext('test-session');
        $agent->setContext($context);
        $agent->loadTools();

        $loadedTools = $agent->getLoadedTools();

        // Should have direct tool but not toolbox tools
        expect($loadedTools)->toHaveKey('direct_test_tool');
        expect($loadedTools)->not->toHaveKey('gated_tool_a');
    });

    it('includes toolbox tools when authorized', function () {
        // Define gate to allow access
        Gate::define('restricted-tools', fn() => true);

        $agent = new AgentWithGatedToolbox();
        $context = new AgentContext('test-session');
        $agent->setContext($context);
        $agent->loadTools();

        $loadedTools = $agent->getLoadedTools();

        expect($loadedTools)->toHaveKey('direct_test_tool');
        expect($loadedTools)->toHaveKey('gated_tool_a');
    });

    it('can combine multiple toolboxes', function () {
        $agent = new AgentWithMultipleToolboxes();
        $agent->loadTools();

        $loadedTools = $agent->getLoadedTools();

        // Should have tools from both toolboxes
        expect($loadedTools)->toHaveKey('multi_tool_a');
        expect($loadedTools)->toHaveKey('multi_tool_b');
        expect($loadedTools)->toHaveKey('multi_tool_c');
    });

    it('handles tool name conflicts between toolboxes', function () {
        $agent = new AgentWithConflictingToolboxes();
        $agent->loadTools();

        $loadedTools = $agent->getLoadedTools();

        // The first loaded tool should take precedence
        // Or tools should be prefixed with toolbox name
        expect($loadedTools)->toHaveKey('shared_tool_name');
    });

    it('handles tool name conflicts between direct tools and toolbox tools', function () {
        $agent = new AgentWithToolConflict();
        $agent->loadTools();

        $loadedTools = $agent->getLoadedTools();

        // Direct tools should take precedence over toolbox tools
        expect($loadedTools)->toHaveKey('conflict_tool');
        expect($loadedTools['conflict_tool'])->toBeInstanceOf(DirectConflictTool::class);
    });

    it('passes context to toolbox for authorization', function () {
        $agent = new AgentWithContextAwareToolbox();
        $context = new AgentContext('test-session');
        $context->setState('user_role', 'premium');
        $agent->setContext($context);
        // Force reload to apply new context (constructor loaded without context)
        $agent->forceReloadTools();
        $agent->loadTools();

        $loadedTools = $agent->getLoadedTools();

        // Premium users get the premium tool
        expect($loadedTools)->toHaveKey('premium_only_tool');
    });

    it('excludes tools when context conditions not met', function () {
        $agent = new AgentWithContextAwareToolbox();
        $context = new AgentContext('test-session');
        $context->setState('user_role', 'basic');
        $agent->setContext($context);
        $agent->loadTools();

        $loadedTools = $agent->getLoadedTools();

        // Basic users don't get the premium tool
        expect($loadedTools)->not->toHaveKey('premium_only_tool');
        expect($loadedTools)->toHaveKey('basic_tool');
    });

    it('reloads toolbox tools when forceReload is called', function () {
        Gate::define('dynamic-gate', fn() => false);

        $agent = new AgentWithDynamicToolbox();
        $context = new AgentContext('test-session');
        $agent->setContext($context);
        $agent->loadTools();

        $loadedTools1 = $agent->getLoadedTools();
        expect($loadedTools1)->not->toHaveKey('dynamic_tool');

        // Change gate to allow access
        Gate::define('dynamic-gate', fn() => true);

        // Force reload
        $agent->forceReloadTools();
        $agent->loadTools();

        $loadedTools2 = $agent->getLoadedTools();
        expect($loadedTools2)->toHaveKey('dynamic_tool');
    });

    it('includes toolbox tools in Prism tool conversion', function () {
        $agent = new AgentWithToolboxes();
        $context = new AgentContext('test-session');
        $agent->setContext($context);
        $agent->loadTools();

        $prismTools = $agent->getToolsForPrismPublic($context);

        $toolNames = array_map(fn($t) => $t->name(), $prismTools);

        expect($toolNames)->toContain('integration_tool_a');
        expect($toolNames)->toContain('integration_tool_b');
    });

    it('can add toolbox at runtime', function () {
        $agent = new AgentWithToolboxes();
        $context = new AgentContext('test-session');
        $agent->setContext($context);

        // Add toolbox at runtime and force reload to apply changes
        $agent->addToolbox(RuntimeToolbox::class);
        $agent->forceReloadTools();
        $agent->loadTools();

        $loadedTools = $agent->getLoadedTools();

        expect($loadedTools)->toHaveKey('runtime_tool');
    });

    it('can remove toolbox at runtime', function () {
        $agent = new AgentWithToolboxes();
        $context = new AgentContext('test-session');
        $agent->setContext($context);

        // Remove toolbox and force reload to apply changes
        $agent->removeToolbox(IntegrationTestToolbox::class);
        $agent->forceReloadTools();
        $agent->loadTools();

        $loadedTools = $agent->getLoadedTools();

        expect($loadedTools)->not->toHaveKey('integration_tool_a');
        expect($loadedTools)->not->toHaveKey('integration_tool_b');
        expect($loadedTools)->toHaveKey('direct_test_tool');
    });

    it('returns list of registered toolboxes', function () {
        $agent = new AgentWithMultipleToolboxes();

        $toolboxes = $agent->getToolboxes();

        expect($toolboxes)->toContain(MultiToolboxA::class);
        expect($toolboxes)->toContain(MultiToolboxB::class);
    });

    it('can check if a toolbox is registered', function () {
        $agent = new AgentWithToolboxes();

        expect($agent->hasToolbox(IntegrationTestToolbox::class))->toBeTrue();
        expect($agent->hasToolbox(RuntimeToolbox::class))->toBeFalse();
    });
});

describe('Toolbox Tool Execution', function () {
    it('can execute tools loaded from toolbox', function () {
        $agent = new AgentWithToolboxes();
        $context = new AgentContext('test-session');
        $mockAgentForMemory = Mockery::mock(BaseLlmAgent::class);
        $mockAgentForMemory->shouldReceive('getName')->andReturn('test-agent');
        $memory = new AgentMemory($mockAgentForMemory);

        $agent->setContext($context);
        $agent->loadTools();

        $loadedTools = $agent->getLoadedTools();
        $tool = $loadedTools['integration_tool_a'];

        $result = $tool->execute(['input' => 'test'], $context, $memory);
        $decoded = json_decode($result, true);

        expect($decoded)->toHaveKey('source');
        expect($decoded['source'])->toBe('toolbox');
    });
});

/**
 * Test Agents
 */
class AgentWithToolboxes extends BaseLlmAgent
{
    protected string $name = 'agent-with-toolboxes';
    protected string $description = 'Test agent with toolboxes';
    protected string $instructions = 'Test instructions';
    protected string $model = 'gpt-3.5-turbo';

    protected array $tools = [
        DirectTestTool::class,
    ];

    protected array $toolboxes = [
        IntegrationTestToolbox::class,
    ];

    protected ?AgentContext $testContext = null;

    public function setContext(AgentContext $context): void
    {
        $this->testContext = $context;
    }

    protected function getContextForToolboxAuthorization(): ?AgentContext
    {
        return $this->testContext;
    }

    public function getLoadedTools(): array
    {
        return $this->loadedTools;
    }

    public function getToolsForPrismPublic(AgentContext $context): array
    {
        return $this->getToolsForPrism($context);
    }

    public function execute(mixed $input, AgentContext $context): mixed
    {
        return 'test response';
    }

    // Methods addToolbox, removeToolbox, getToolboxes, hasToolbox, forceReloadTools
    // are now inherited from BaseLlmAgent
}

class AgentWithGatedToolbox extends AgentWithToolboxes
{
    protected array $toolboxes = [
        GatedIntegrationToolbox::class,
    ];
}

class AgentWithMultipleToolboxes extends AgentWithToolboxes
{
    protected array $tools = [];
    protected array $toolboxes = [
        MultiToolboxA::class,
        MultiToolboxB::class,
    ];
}

class AgentWithConflictingToolboxes extends AgentWithToolboxes
{
    protected array $tools = [];
    protected array $toolboxes = [
        ConflictToolboxA::class,
        ConflictToolboxB::class,
    ];
}

class AgentWithToolConflict extends AgentWithToolboxes
{
    protected array $tools = [
        DirectConflictTool::class,
    ];
    protected array $toolboxes = [
        ConflictToolboxC::class,
    ];
}

class AgentWithContextAwareToolbox extends AgentWithToolboxes
{
    protected array $tools = [];
    protected array $toolboxes = [
        ContextAwareToolbox::class,
    ];
}

class AgentWithDynamicToolbox extends AgentWithToolboxes
{
    protected array $tools = [];
    protected array $toolboxes = [
        DynamicGateToolbox::class,
    ];
}

/**
 * Test Toolboxes
 */
class IntegrationTestToolbox extends BaseToolbox
{
    protected string $name = 'integration_test_toolbox';
    protected string $description = 'Integration test toolbox';
    protected array $tools = [
        IntegrationToolA::class,
        IntegrationToolB::class,
    ];
}

class GatedIntegrationToolbox extends BaseToolbox
{
    protected string $name = 'gated_integration_toolbox';
    protected string $description = 'Gated integration test toolbox';
    protected ?string $gate = 'restricted-tools';
    protected array $tools = [
        GatedToolA::class,
    ];
}

class MultiToolboxA extends BaseToolbox
{
    protected string $name = 'multi_toolbox_a';
    protected string $description = 'Multi toolbox A';
    protected array $tools = [
        MultiToolA::class,
        MultiToolB::class,
    ];
}

class MultiToolboxB extends BaseToolbox
{
    protected string $name = 'multi_toolbox_b';
    protected string $description = 'Multi toolbox B';
    protected array $tools = [
        MultiToolC::class,
    ];
}

class ConflictToolboxA extends BaseToolbox
{
    protected string $name = 'conflict_toolbox_a';
    protected string $description = 'Conflict toolbox A';
    protected array $tools = [
        SharedNameToolA::class,
    ];
}

class ConflictToolboxB extends BaseToolbox
{
    protected string $name = 'conflict_toolbox_b';
    protected string $description = 'Conflict toolbox B';
    protected array $tools = [
        SharedNameToolB::class,
    ];
}

class ConflictToolboxC extends BaseToolbox
{
    protected string $name = 'conflict_toolbox_c';
    protected string $description = 'Conflict toolbox C';
    protected array $tools = [
        ToolboxConflictTool::class,
    ];
}

class ContextAwareToolbox extends BaseToolbox
{
    protected string $name = 'context_aware_toolbox';
    protected string $description = 'Context aware toolbox';
    protected array $tools = [
        BasicTool::class,
        PremiumOnlyTool::class,
    ];

    protected function shouldIncludeTool(string $toolClass, AgentContext $context): bool
    {
        if ($toolClass === PremiumOnlyTool::class) {
            return $context->getState('user_role') === 'premium';
        }
        return true;
    }
}

class DynamicGateToolbox extends BaseToolbox
{
    protected string $name = 'dynamic_gate_toolbox';
    protected string $description = 'Dynamic gate toolbox';
    protected ?string $gate = 'dynamic-gate';
    protected array $tools = [
        DynamicTool::class,
    ];
}

class RuntimeToolbox extends BaseToolbox
{
    protected string $name = 'runtime_toolbox';
    protected string $description = 'Runtime added toolbox';
    protected array $tools = [
        RuntimeTool::class,
    ];
}

/**
 * Test Tools
 */
class DirectTestTool implements ToolInterface
{
    public function definition(): array
    {
        return [
            'name' => 'direct_test_tool',
            'description' => 'Direct test tool',
            'parameters' => ['type' => 'object', 'properties' => [], 'required' => []],
        ];
    }

    public function execute(array $arguments, AgentContext $context, AgentMemory $memory): string
    {
        return json_encode(['source' => 'direct']);
    }
}

class IntegrationToolA implements ToolInterface
{
    public function definition(): array
    {
        return [
            'name' => 'integration_tool_a',
            'description' => 'Integration tool A',
            'parameters' => [
                'type' => 'object',
                'properties' => ['input' => ['type' => 'string']],
                'required' => ['input'],
            ],
        ];
    }

    public function execute(array $arguments, AgentContext $context, AgentMemory $memory): string
    {
        return json_encode(['source' => 'toolbox', 'tool' => 'A', 'input' => $arguments['input'] ?? null]);
    }
}

class IntegrationToolB implements ToolInterface
{
    public function definition(): array
    {
        return [
            'name' => 'integration_tool_b',
            'description' => 'Integration tool B',
            'parameters' => ['type' => 'object', 'properties' => [], 'required' => []],
        ];
    }

    public function execute(array $arguments, AgentContext $context, AgentMemory $memory): string
    {
        return json_encode(['source' => 'toolbox', 'tool' => 'B']);
    }
}

class GatedToolA implements ToolInterface
{
    public function definition(): array
    {
        return [
            'name' => 'gated_tool_a',
            'description' => 'Gated tool A',
            'parameters' => ['type' => 'object', 'properties' => [], 'required' => []],
        ];
    }

    public function execute(array $arguments, AgentContext $context, AgentMemory $memory): string
    {
        return json_encode(['source' => 'gated_toolbox']);
    }
}

class MultiToolA implements ToolInterface
{
    public function definition(): array
    {
        return [
            'name' => 'multi_tool_a',
            'description' => 'Multi tool A',
            'parameters' => ['type' => 'object', 'properties' => [], 'required' => []],
        ];
    }

    public function execute(array $arguments, AgentContext $context, AgentMemory $memory): string
    {
        return json_encode(['tool' => 'multi_a']);
    }
}

class MultiToolB implements ToolInterface
{
    public function definition(): array
    {
        return [
            'name' => 'multi_tool_b',
            'description' => 'Multi tool B',
            'parameters' => ['type' => 'object', 'properties' => [], 'required' => []],
        ];
    }

    public function execute(array $arguments, AgentContext $context, AgentMemory $memory): string
    {
        return json_encode(['tool' => 'multi_b']);
    }
}

class MultiToolC implements ToolInterface
{
    public function definition(): array
    {
        return [
            'name' => 'multi_tool_c',
            'description' => 'Multi tool C',
            'parameters' => ['type' => 'object', 'properties' => [], 'required' => []],
        ];
    }

    public function execute(array $arguments, AgentContext $context, AgentMemory $memory): string
    {
        return json_encode(['tool' => 'multi_c']);
    }
}

class SharedNameToolA implements ToolInterface
{
    public function definition(): array
    {
        return [
            'name' => 'shared_tool_name',
            'description' => 'Shared name tool A',
            'parameters' => ['type' => 'object', 'properties' => [], 'required' => []],
        ];
    }

    public function execute(array $arguments, AgentContext $context, AgentMemory $memory): string
    {
        return json_encode(['variant' => 'A']);
    }
}

class SharedNameToolB implements ToolInterface
{
    public function definition(): array
    {
        return [
            'name' => 'shared_tool_name',
            'description' => 'Shared name tool B',
            'parameters' => ['type' => 'object', 'properties' => [], 'required' => []],
        ];
    }

    public function execute(array $arguments, AgentContext $context, AgentMemory $memory): string
    {
        return json_encode(['variant' => 'B']);
    }
}

class DirectConflictTool implements ToolInterface
{
    public function definition(): array
    {
        return [
            'name' => 'conflict_tool',
            'description' => 'Direct conflict tool',
            'parameters' => ['type' => 'object', 'properties' => [], 'required' => []],
        ];
    }

    public function execute(array $arguments, AgentContext $context, AgentMemory $memory): string
    {
        return json_encode(['source' => 'direct']);
    }
}

class ToolboxConflictTool implements ToolInterface
{
    public function definition(): array
    {
        return [
            'name' => 'conflict_tool',
            'description' => 'Toolbox conflict tool',
            'parameters' => ['type' => 'object', 'properties' => [], 'required' => []],
        ];
    }

    public function execute(array $arguments, AgentContext $context, AgentMemory $memory): string
    {
        return json_encode(['source' => 'toolbox']);
    }
}

class BasicTool implements ToolInterface
{
    public function definition(): array
    {
        return [
            'name' => 'basic_tool',
            'description' => 'Basic tool for all users',
            'parameters' => ['type' => 'object', 'properties' => [], 'required' => []],
        ];
    }

    public function execute(array $arguments, AgentContext $context, AgentMemory $memory): string
    {
        return json_encode(['tier' => 'basic']);
    }
}

class PremiumOnlyTool implements ToolInterface
{
    public function definition(): array
    {
        return [
            'name' => 'premium_only_tool',
            'description' => 'Premium only tool',
            'parameters' => ['type' => 'object', 'properties' => [], 'required' => []],
        ];
    }

    public function execute(array $arguments, AgentContext $context, AgentMemory $memory): string
    {
        return json_encode(['tier' => 'premium']);
    }
}

class DynamicTool implements ToolInterface
{
    public function definition(): array
    {
        return [
            'name' => 'dynamic_tool',
            'description' => 'Dynamic tool',
            'parameters' => ['type' => 'object', 'properties' => [], 'required' => []],
        ];
    }

    public function execute(array $arguments, AgentContext $context, AgentMemory $memory): string
    {
        return json_encode(['dynamic' => true]);
    }
}

class RuntimeTool implements ToolInterface
{
    public function definition(): array
    {
        return [
            'name' => 'runtime_tool',
            'description' => 'Runtime added tool',
            'parameters' => ['type' => 'object', 'properties' => [], 'required' => []],
        ];
    }

    public function execute(array $arguments, AgentContext $context, AgentMemory $memory): string
    {
        return json_encode(['runtime' => true]);
    }
}
