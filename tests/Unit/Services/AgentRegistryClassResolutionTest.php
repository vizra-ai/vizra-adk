<?php

namespace Vizra\VizraADK\Tests\Unit\Services;

use Vizra\VizraADK\Services\AgentRegistry;
use Vizra\VizraADK\Services\AgentBuilder;
use Vizra\VizraADK\Agents\BaseLlmAgent;
use Vizra\VizraADK\Exceptions\AgentNotFoundException;
use Vizra\VizraADK\Tests\TestCase;
use Illuminate\Support\Facades\App;

// Test agent classes
class TestRegistryAgent extends BaseLlmAgent {
    protected string $name = 'test_registry_agent';
    protected string $description = 'Test agent for registry';
}

class AnotherTestAgent extends BaseLlmAgent {
    protected string $name = 'another_test_agent';
    protected string $description = 'Another test agent';
}

class UnregisteredAgent extends BaseLlmAgent {
    protected string $name = 'unregistered_agent';
    protected string $description = 'This agent is not registered';
}

class AgentRegistryClassResolutionTest extends TestCase
{
    protected AgentRegistry $registry;
    protected AgentBuilder $builder;

    protected function setUp(): void
    {
        parent::setUp();
        $this->registry = app(AgentRegistry::class);
        $this->builder = app(AgentBuilder::class);
    }

    public function test_registers_class_to_name_mapping()
    {
        // Register an agent
        $this->registry->register('test_registry_agent', TestRegistryAgent::class);

        // Check that the mapping was created
        $this->assertEquals('test_registry_agent', $this->registry->getAgentNameByClass(TestRegistryAgent::class));
    }

    public function test_resolves_agent_name_from_class()
    {
        // Register an agent
        $this->registry->register('test_registry_agent', TestRegistryAgent::class);

        // Test resolution
        $resolvedName = $this->registry->resolveAgentName(TestRegistryAgent::class);
        $this->assertEquals('test_registry_agent', $resolvedName);
    }

    public function test_resolves_existing_agent_name()
    {
        // Register an agent
        $this->registry->register('test_registry_agent', TestRegistryAgent::class);

        // Test that existing names still work
        $resolvedName = $this->registry->resolveAgentName('test_registry_agent');
        $this->assertEquals('test_registry_agent', $resolvedName);
    }

    public function test_throws_exception_for_unresolvable_agent()
    {
        $this->expectException(AgentNotFoundException::class);
        $this->expectExceptionMessage("Cannot resolve agent 'NonExistentAgent' as either a name or class.");

        $this->registry->resolveAgentName('NonExistentAgent');
    }

    public function test_resolves_unregistered_agent_by_instantiation()
    {
        // Don't register the agent, let it be resolved by instantiation
        $resolvedName = $this->registry->getAgentNameByClass(UnregisteredAgent::class);
        
        $this->assertEquals('unregistered_agent', $resolvedName);
        
        // Verify it was registered after resolution
        $this->assertTrue($this->registry->hasAgent('unregistered_agent'));
    }

    public function test_builder_registers_class_mapping()
    {
        // Use the builder to register an agent
        $this->builder->build(AnotherTestAgent::class)->register();

        // Check that the mapping exists
        $resolvedName = $this->registry->getAgentNameByClass(AnotherTestAgent::class);
        $this->assertEquals('another_test_agent', $resolvedName);
    }

    public function test_multiple_agents_with_different_classes()
    {
        // Register multiple agents
        $this->registry->register('test_registry_agent', TestRegistryAgent::class);
        $this->registry->register('another_test_agent', AnotherTestAgent::class);

        // Test resolution for both
        $this->assertEquals('test_registry_agent', $this->registry->resolveAgentName(TestRegistryAgent::class));
        $this->assertEquals('another_test_agent', $this->registry->resolveAgentName(AnotherTestAgent::class));
    }

    public function test_agent_discovery_updates_class_mappings()
    {
        // Clear any existing registrations
        $this->registry = new AgentRegistry(App::getFacadeRoot());

        // Trigger discovery (this would normally discover agents in configured paths)
        // For this test, we'll manually register after simulating discovery
        $this->registry->register('discovered_agent', TestRegistryAgent::class);

        // Check that class mapping works after discovery
        $resolvedName = $this->registry->getAgentNameByClass(TestRegistryAgent::class);
        $this->assertEquals('discovered_agent', $resolvedName);
    }

    public function test_class_resolution_with_workflows()
    {
        // Register agents that would be used in workflows
        $this->registry->register('first_workflow_agent', TestRegistryAgent::class);
        $this->registry->register('second_workflow_agent', AnotherTestAgent::class);

        // Simulate workflow usage - resolve class names
        $firstName = $this->registry->resolveAgentName(TestRegistryAgent::class);
        $secondName = $this->registry->resolveAgentName(AnotherTestAgent::class);

        $this->assertEquals('first_workflow_agent', $firstName);
        $this->assertEquals('second_workflow_agent', $secondName);
    }
}