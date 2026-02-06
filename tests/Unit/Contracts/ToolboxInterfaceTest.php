<?php

use Illuminate\Support\Facades\Gate;
use Vizra\VizraADK\Agents\BaseLlmAgent;
use Vizra\VizraADK\Contracts\ToolboxInterface;
use Vizra\VizraADK\Contracts\ToolInterface;
use Vizra\VizraADK\Memory\AgentMemory;
use Vizra\VizraADK\System\AgentContext;
use Vizra\VizraADK\Toolboxes\BaseToolbox;

/**
 * ToolboxInterface Contract Tests
 *
 * These tests define the expected behavior of the Toolbox system.
 * Following TDD, these tests are written first to define the contract.
 */

afterEach(function () {
    Mockery::close();
});

describe('ToolboxInterface Contract', function () {
    it('defines the toolbox interface with required methods', function () {
        // Verify the interface exists and has the expected methods
        expect(interface_exists(ToolboxInterface::class))->toBeTrue();

        $reflection = new ReflectionClass(ToolboxInterface::class);
        $methods = array_map(fn($m) => $m->getName(), $reflection->getMethods());

        expect($methods)->toContain('name');
        expect($methods)->toContain('description');
        expect($methods)->toContain('tools');
        expect($methods)->toContain('authorize');
        expect($methods)->toContain('authorizedTools');
    });

    it('can be implemented by a concrete class', function () {
        $toolbox = new ConcreteTestToolbox();

        expect($toolbox)->toBeInstanceOf(ToolboxInterface::class);
    });

    it('returns toolbox name as string', function () {
        $toolbox = new ConcreteTestToolbox();

        expect($toolbox->name())->toBe('test_toolbox');
        expect($toolbox->name())->toBeString();
    });

    it('returns toolbox description as string', function () {
        $toolbox = new ConcreteTestToolbox();

        expect($toolbox->description())->toBe('A test toolbox for unit testing');
        expect($toolbox->description())->toBeString();
    });

    it('returns array of tool class names', function () {
        $toolbox = new ConcreteTestToolbox();

        $tools = $toolbox->tools();

        expect($tools)->toBeArray();
        expect($tools)->toContain(ToolboxTestToolA::class);
        expect($tools)->toContain(ToolboxTestToolB::class);
    });

    it('returns boolean from authorize method', function () {
        $toolbox = new ConcreteTestToolbox();
        $context = new AgentContext('test-session');

        $result = $toolbox->authorize($context);

        expect($result)->toBeBool();
    });

    it('returns array of tool instances from authorizedTools', function () {
        $toolbox = new ConcreteTestToolbox();
        $context = new AgentContext('test-session');

        $tools = $toolbox->authorizedTools($context);

        expect($tools)->toBeArray();
        foreach ($tools as $tool) {
            expect($tool)->toBeInstanceOf(ToolInterface::class);
        }
    });
});

describe('BaseToolbox Abstract Class', function () {
    it('provides default implementation of ToolboxInterface', function () {
        $toolbox = new ConcreteTestToolbox();

        expect($toolbox)->toBeInstanceOf(BaseToolbox::class);
        expect($toolbox)->toBeInstanceOf(ToolboxInterface::class);
    });

    it('authorizes all tools when no gate is defined', function () {
        $toolbox = new ConcreteTestToolbox();
        $context = new AgentContext('test-session');

        expect($toolbox->authorize($context))->toBeTrue();

        $tools = $toolbox->authorizedTools($context);
        expect($tools)->toHaveCount(2);
    });

    it('uses gate for toolbox-level authorization', function () {
        $toolbox = new GatedTestToolbox();
        $context = new AgentContext('test-session');

        // Define a gate that denies access
        Gate::define('admin-tools', fn() => false);

        expect($toolbox->authorize($context))->toBeFalse();
        expect($toolbox->authorizedTools($context))->toBeEmpty();
    });

    it('allows access when gate returns true', function () {
        $toolbox = new GatedTestToolbox();
        $context = new AgentContext('test-session');

        // Define a gate that allows access
        Gate::define('admin-tools', fn() => true);

        expect($toolbox->authorize($context))->toBeTrue();
        expect($toolbox->authorizedTools($context))->toHaveCount(2);
    });

    it('uses user from context for gate authorization', function () {
        $toolbox = new GatedTestToolbox();
        $context = new AgentContext('test-session');
        $context->setState('user_id', 123);
        $context->setState('user_data', ['id' => 123, 'role' => 'admin']);

        // Define a gate that checks user role
        Gate::define('admin-tools', function ($user) {
            return ($user['role'] ?? null) === 'admin';
        });

        expect($toolbox->authorize($context))->toBeTrue();
    });

    it('denies access when user lacks permission', function () {
        $toolbox = new GatedTestToolbox();
        $context = new AgentContext('test-session');
        $context->setState('user_id', 456);
        $context->setState('user_data', ['id' => 456, 'role' => 'user']);

        // Define a gate that checks user role
        Gate::define('admin-tools', function ($user) {
            return ($user['role'] ?? null) === 'admin';
        });

        expect($toolbox->authorize($context))->toBeFalse();
    });

    it('supports per-tool gates', function () {
        $toolbox = new PerToolGatedToolbox();
        $context = new AgentContext('test-session');

        // Allow toolbox access but deny specific tool
        Gate::define('support-tools', fn() => true);
        Gate::define('refund-permission', fn() => false);

        $tools = $toolbox->authorizedTools($context);

        // Should only have ToolA, not ToolB (which requires refund-permission)
        expect($tools)->toHaveCount(1);
        expect(array_keys($tools))->toContain('toolbox_test_tool_a');
    });

    it('allows all tools when per-tool gates pass', function () {
        $toolbox = new PerToolGatedToolbox();
        $context = new AgentContext('test-session');

        // Allow both toolbox and tool-specific gates
        Gate::define('support-tools', fn() => true);
        Gate::define('refund-permission', fn() => true);

        $tools = $toolbox->authorizedTools($context);

        expect($tools)->toHaveCount(2);
    });

    it('supports policy-based authorization', function () {
        $toolbox = new PolicyBasedToolbox();
        $context = new AgentContext('test-session');
        $context->setState('user_data', ['id' => 1, 'role' => 'admin']);

        // Register the policy
        Gate::policy(PolicyBasedToolbox::class, TestToolboxPolicy::class);

        expect($toolbox->authorize($context))->toBeTrue();
    });

    it('denies access when policy returns false', function () {
        $toolbox = new PolicyBasedToolbox();
        $context = new AgentContext('test-session');
        $context->setState('user_data', ['id' => 1, 'role' => 'guest']);

        // Register the policy
        Gate::policy(PolicyBasedToolbox::class, TestToolboxPolicy::class);

        expect($toolbox->authorize($context))->toBeFalse();
    });

    it('can instantiate tools from class names', function () {
        $toolbox = new ConcreteTestToolbox();
        $context = new AgentContext('test-session');

        $tools = $toolbox->authorizedTools($context);

        expect($tools['toolbox_test_tool_a'])->toBeInstanceOf(ToolboxTestToolA::class);
        expect($tools['toolbox_test_tool_b'])->toBeInstanceOf(ToolboxTestToolB::class);
    });

    it('resolves tools from the container', function () {
        // Bind a custom implementation
        app()->bind(ToolboxTestToolA::class, function () {
            return new ToolboxTestToolA();
        });

        $toolbox = new ConcreteTestToolbox();
        $context = new AgentContext('test-session');

        $tools = $toolbox->authorizedTools($context);

        expect($tools['toolbox_test_tool_a'])->toBeInstanceOf(ToolboxTestToolA::class);
    });

    it('supports conditional tool inclusion via shouldIncludeTool method', function () {
        $toolbox = new ConditionalToolbox();
        $context = new AgentContext('test-session');
        $context->setState('feature_enabled', false);

        $tools = $toolbox->authorizedTools($context);

        // ToolB should be excluded because feature_enabled is false
        expect($tools)->toHaveCount(1);
        expect(array_keys($tools))->toContain('toolbox_test_tool_a');
    });

    it('includes conditional tools when condition passes', function () {
        $toolbox = new ConditionalToolbox();
        $context = new AgentContext('test-session');
        $context->setState('feature_enabled', true);

        $tools = $toolbox->authorizedTools($context);

        expect($tools)->toHaveCount(2);
    });

    it('caches authorized tools for same context', function () {
        $toolbox = new ConcreteTestToolbox();
        $context = new AgentContext('test-session');

        $tools1 = $toolbox->authorizedTools($context);
        $tools2 = $toolbox->authorizedTools($context);

        // Should return same instances
        expect($tools1)->toBe($tools2);
    });

    it('clears cache when context changes', function () {
        $toolbox = new ConcreteTestToolbox();
        $context1 = new AgentContext('session-1');
        $context2 = new AgentContext('session-2');

        $tools1 = $toolbox->authorizedTools($context1);
        $tools2 = $toolbox->authorizedTools($context2);

        // Should be different instances (different sessions)
        expect($tools1)->not->toBe($tools2);
    });
});

describe('Toolbox Tool Keys', function () {
    it('keys tools by their definition name', function () {
        $toolbox = new ConcreteTestToolbox();
        $context = new AgentContext('test-session');

        $tools = $toolbox->authorizedTools($context);

        expect($tools)->toHaveKey('toolbox_test_tool_a');
        expect($tools)->toHaveKey('toolbox_test_tool_b');
    });
});

/**
 * Test Toolbox Implementations
 */
class ConcreteTestToolbox extends BaseToolbox
{
    protected string $name = 'test_toolbox';
    protected string $description = 'A test toolbox for unit testing';
    protected array $tools = [
        ToolboxTestToolA::class,
        ToolboxTestToolB::class,
    ];
}

class GatedTestToolbox extends BaseToolbox
{
    protected string $name = 'gated_toolbox';
    protected string $description = 'A toolbox with gate-based authorization';
    protected ?string $gate = 'admin-tools';
    protected array $tools = [
        ToolboxTestToolA::class,
        ToolboxTestToolB::class,
    ];
}

class PerToolGatedToolbox extends BaseToolbox
{
    protected string $name = 'per_tool_gated_toolbox';
    protected string $description = 'A toolbox with per-tool gates';
    protected ?string $gate = 'support-tools';
    protected array $tools = [
        ToolboxTestToolA::class,
        ToolboxTestToolB::class,
    ];
    protected array $toolGates = [
        ToolboxTestToolB::class => 'refund-permission',
    ];
}

class PolicyBasedToolbox extends BaseToolbox
{
    protected string $name = 'policy_toolbox';
    protected string $description = 'A toolbox with policy-based authorization';
    protected ?string $policy = TestToolboxPolicy::class;
    protected ?string $policyAbility = 'use';
    protected array $tools = [
        ToolboxTestToolA::class,
        ToolboxTestToolB::class,
    ];
}

class ConditionalToolbox extends BaseToolbox
{
    protected string $name = 'conditional_toolbox';
    protected string $description = 'A toolbox with conditional tool inclusion';
    protected array $tools = [
        ToolboxTestToolA::class,
        ToolboxTestToolB::class,
    ];

    protected function shouldIncludeTool(string $toolClass, AgentContext $context): bool
    {
        // Exclude ToolB when feature is not enabled
        if ($toolClass === ToolboxTestToolB::class) {
            return $context->getState('feature_enabled', false) === true;
        }
        return true;
    }
}

/**
 * Test Tools
 */
class ToolboxTestToolA implements ToolInterface
{
    public function definition(): array
    {
        return [
            'name' => 'toolbox_test_tool_a',
            'description' => 'Test tool A for toolbox testing',
            'parameters' => [
                'type' => 'object',
                'properties' => [
                    'input' => ['type' => 'string', 'description' => 'Input value'],
                ],
                'required' => ['input'],
            ],
        ];
    }

    public function execute(array $arguments, AgentContext $context, AgentMemory $memory): string
    {
        return json_encode(['tool' => 'A', 'input' => $arguments['input'] ?? null]);
    }
}

class ToolboxTestToolB implements ToolInterface
{
    public function definition(): array
    {
        return [
            'name' => 'toolbox_test_tool_b',
            'description' => 'Test tool B for toolbox testing',
            'parameters' => [
                'type' => 'object',
                'properties' => [
                    'value' => ['type' => 'integer', 'description' => 'Numeric value'],
                ],
                'required' => ['value'],
            ],
        ];
    }

    public function execute(array $arguments, AgentContext $context, AgentMemory $memory): string
    {
        return json_encode(['tool' => 'B', 'value' => $arguments['value'] ?? 0]);
    }
}

/**
 * Test Policy
 */
class TestToolboxPolicy
{
    public function use($user, $toolbox): bool
    {
        // Allow if user has admin role
        return ($user['role'] ?? null) === 'admin';
    }
}
