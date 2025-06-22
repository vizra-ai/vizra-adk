<?php

namespace Vizra\VizraADK\Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Vizra\VizraADK\Agents\BaseLlmAgent;
use Vizra\VizraADK\Exceptions\AgentNotFoundException;
use Vizra\VizraADK\Facades\Agent;
use Vizra\VizraADK\Services\AgentDiscovery;
use Vizra\VizraADK\Services\AgentRegistry;
use Vizra\VizraADK\Tests\TestCase;

class AgentAutoDiscoveryTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Clear any cached discoveries
        app(AgentDiscovery::class)->clearCache();

        // Create test agents directory
        $this->createTestAgentsDirectory();
    }

    protected function tearDown(): void
    {
        // Clean up test agents directory
        $testDir = app_path('TestAgents');
        if (File::exists($testDir)) {
            File::deleteDirectory($testDir);
        }

        parent::tearDown();
    }

    public function test_agents_are_auto_discovered_on_boot()
    {
        // Create a test agent
        $this->createTestAgent('AutoDiscoveredAgent', 'auto_discovered');

        // Create a new instance of the service provider and boot it
        $provider = new \Vizra\VizraADK\Providers\AgentServiceProvider($this->app);
        $provider->register();
        $provider->boot();

        // Check if agent is available
        $this->assertTrue(Agent::hasAgent('auto_discovered'));

        // Use the agent
        $agent = Agent::named('auto_discovered');
        $this->assertInstanceOf(BaseLlmAgent::class, $agent);
        $this->assertEquals('auto_discovered', $agent->getName());
    }

    public function test_agent_can_be_used_directly_without_registration()
    {
        // Create a test agent
        $this->createTestAgent('DirectUseAgent', 'direct_use');

        // Clear the registry to ensure it's not pre-registered
        $registry = app(AgentRegistry::class);

        // Initially, agent should not be registered
        $this->assertFalse($registry->hasAgent('direct_use'));

        // Try to use the agent - should trigger lazy discovery
        $agent = Agent::named('direct_use');

        // Verify it works
        $this->assertInstanceOf(BaseLlmAgent::class, $agent);
        $this->assertEquals('direct_use', $agent->getName());

        // Now it should be registered
        $this->assertTrue($registry->hasAgent('direct_use'));
    }

    public function test_ask_method_works_with_auto_discovery()
    {
        // Create a test agent
        $agentContent = '<?php
namespace App\TestAgents;

use Vizra\VizraADK\Agents\BaseLlmAgent;
use Vizra\VizraADK\System\AgentContext;

class AskMethodAgent extends BaseLlmAgent
{
    protected string $name = "ask_method_agent";
    protected string $description = "Test agent for ask method";
    protected string $model = "gemini-1.5-flash";
    
    public function run(mixed $input, AgentContext $context): mixed
    {
        return "Received: " . $input;
    }
}';

        File::put(app_path('TestAgents/AskMethodAgent.php'), $agentContent);

        // Include the file so the class is available
        require_once app_path('TestAgents/AskMethodAgent.php');

        // Use the ask method directly
        $executor = \App\TestAgents\AskMethodAgent::ask('Hello');
        $this->assertInstanceOf(\Vizra\VizraADK\Execution\AgentExecutor::class, $executor);
    }

    public function test_lazy_discovery_only_runs_when_needed()
    {
        // Manually register an agent
        Agent::build(\Vizra\VizraADK\Tests\Fixtures\TestAgent::class)->register();

        // Create a test agent that would be discovered
        $this->createTestAgent('LazyTestAgent', 'lazy_test');

        // Access the manually registered agent - should not trigger discovery
        $agent = Agent::named('test_agent');
        $this->assertInstanceOf(\Vizra\VizraADK\Tests\Fixtures\TestAgent::class, $agent);

        // Verify lazy agent is not yet registered
        $this->assertFalse(Agent::hasAgent('lazy_test'));

        // Now access the lazy agent - should trigger discovery
        $lazyAgent = Agent::named('lazy_test');
        $this->assertInstanceOf(BaseLlmAgent::class, $lazyAgent);
        $this->assertTrue(Agent::hasAgent('lazy_test'));
    }

    public function test_non_existent_agent_throws_exception()
    {
        $this->expectException(AgentNotFoundException::class);
        $this->expectExceptionMessage("Agent 'non_existent' is not registered");

        Agent::named('non_existent');
    }

    public function test_discover_agents_command()
    {
        // Create test agents
        $this->createTestAgent('CommandTestAgent1', 'command_test_1');
        $this->createTestAgent('CommandTestAgent2', 'command_test_2');

        // Run the discover command
        Artisan::call('vizra:discover-agents');
        $output = Artisan::output();

        // Check output contains discovered agents
        $this->assertStringContainsString('command_test_1', $output);
        $this->assertStringContainsString('command_test_2', $output);
        $this->assertStringContainsString('App\TestAgents\CommandTestAgent1', $output);
        $this->assertStringContainsString('App\TestAgents\CommandTestAgent2', $output);
    }

    public function test_discover_agents_command_with_clear_cache()
    {
        // Create and discover an agent
        $this->createTestAgent('CachedCommandAgent', 'cached_command');
        Agent::named('cached_command'); // This will cache it

        // Add a new agent
        $this->createTestAgent('NewCommandAgent', 'new_command');

        // Run command with clear-cache option
        Artisan::call('vizra:discover-agents', ['--clear-cache' => true]);
        $output = Artisan::output();

        $this->assertStringContainsString('Discovery cache cleared', $output);
        $this->assertStringContainsString('new_command', $output);
    }

    public function test_mixed_manual_and_auto_discovery()
    {
        // Manually register an agent
        Agent::define('manual_agent')
            ->instructions('A manually defined agent')
            ->model('gpt-4')
            ->register();

        // Create an auto-discovered agent
        $this->createTestAgent('AutoAgent', 'auto_agent');

        // Both should be available
        $this->assertTrue(Agent::hasAgent('manual_agent'));

        $autoAgent = Agent::named('auto_agent');
        $this->assertInstanceOf(BaseLlmAgent::class, $autoAgent);

        // Get all agents
        $allAgents = Agent::getAllRegisteredAgents();
        $this->assertArrayHasKey('manual_agent', $allAgents);
        $this->assertArrayHasKey('auto_agent', $allAgents);
    }

    public function test_agent_in_subdirectory_is_discovered()
    {
        // Create subdirectory
        $subDir = app_path('TestAgents/SubAgents');
        File::makeDirectory($subDir, 0755, true, true);

        // Create agent in subdirectory
        $content = '<?php
namespace App\TestAgents\SubAgents;

use Vizra\VizraADK\Agents\BaseLlmAgent;
use Vizra\VizraADK\System\AgentContext;

class NestedAgent extends BaseLlmAgent
{
    protected string $name = "nested_agent";
    protected string $description = "Agent in subdirectory";
    
    public function run(mixed $input, AgentContext $context): mixed
    {
        return "Nested response";
    }
}';

        File::put($subDir.'/NestedAgent.php', $content);

        // Discover and use the nested agent
        $agent = Agent::named('nested_agent');
        $this->assertInstanceOf(BaseLlmAgent::class, $agent);
        $this->assertEquals('nested_agent', $agent->getName());
    }

    protected function createTestAgentsDirectory(): void
    {
        $testDir = app_path('TestAgents');
        if (! File::exists($testDir)) {
            File::makeDirectory($testDir, 0755, true, true);
        }

        // Update config to use test directory
        config(['vizra-adk.namespaces.agents' => 'App\\TestAgents']);
    }

    protected function createTestAgent(string $className, string $agentName): void
    {
        $content = '<?php
namespace App\TestAgents;

use Vizra\VizraADK\Agents\BaseLlmAgent;
use Vizra\VizraADK\System\AgentContext;

class '.$className.' extends BaseLlmAgent
{
    protected string $name = "'.$agentName.'";
    protected string $description = "Test agent for auto-discovery";
    protected string $model = "gemini-1.5-flash";
    
    public function run(mixed $input, AgentContext $context): mixed
    {
        return "Response from '.$agentName.': " . $input;
    }
}';

        File::put(app_path('TestAgents/'.$className.'.php'), $content);
    }
}
