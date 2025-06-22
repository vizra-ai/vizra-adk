<?php

namespace Vizra\VizraADK\Tests\Unit\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Vizra\VizraADK\Agents\BaseAgent;
use Vizra\VizraADK\Agents\BaseLlmAgent;
use Vizra\VizraADK\Agents\BaseWorkflowAgent;
use Vizra\VizraADK\Services\AgentDiscovery;
use Vizra\VizraADK\Tests\TestCase;

class AgentDiscoveryTest extends TestCase
{
    protected AgentDiscovery $discovery;

    protected function setUp(): void
    {
        parent::setUp();
        $this->discovery = new AgentDiscovery;

        // Clear cache before each test
        Cache::forget('vizra_adk_discovered_agents');
    }

    public function test_discovers_agents_in_configured_namespace()
    {
        // Create a test agent directory
        $testDir = app_path('Agents');
        File::makeDirectory($testDir, 0755, true, true);

        // Create test agent files
        $this->createTestAgent($testDir, 'TestDiscoveryAgent', BaseLlmAgent::class, 'test_discovery');
        $this->createTestAgent($testDir, 'AnotherTestAgent', BaseAgent::class, 'another_test');

        // Discover agents
        $agents = $this->discovery->discover();

        // Assertions
        $this->assertArrayHasKey('App\Agents\TestDiscoveryAgent', $agents);
        $this->assertEquals('test_discovery', $agents['App\Agents\TestDiscoveryAgent']);
        $this->assertArrayHasKey('App\Agents\AnotherTestAgent', $agents);
        $this->assertEquals('another_test', $agents['App\Agents\AnotherTestAgent']);

        // Cleanup
        File::deleteDirectory($testDir);
    }

    public function test_ignores_non_agent_classes()
    {
        $testDir = app_path('Agents');
        File::makeDirectory($testDir, 0755, true, true);

        // Create a non-agent class
        File::put($testDir.'/NotAnAgent.php', '<?php
namespace App\Agents;

class NotAnAgent
{
    protected string $name = "not_an_agent";
}');

        // Create a valid agent
        $this->createTestAgent($testDir, 'ValidAgent', BaseLlmAgent::class, 'valid_agent');

        $agents = $this->discovery->discover();

        // Should only find the valid agent
        $this->assertCount(1, $agents);
        $this->assertArrayHasKey('App\Agents\ValidAgent', $agents);
        $this->assertArrayNotHasKey('App\Agents\NotAnAgent', $agents);

        // Cleanup
        File::deleteDirectory($testDir);
    }

    public function test_ignores_abstract_classes()
    {
        $testDir = app_path('Agents');
        File::makeDirectory($testDir, 0755, true, true);

        // Create an abstract agent class
        File::put($testDir.'/AbstractTestAgent.php', '<?php
namespace App\Agents;

use Vizra\VizraADK\Agents\BaseLlmAgent;

abstract class AbstractTestAgent extends BaseLlmAgent
{
    protected string $name = "abstract_agent";
}');

        $agents = $this->discovery->discover();

        // Should not include abstract classes
        $this->assertArrayNotHasKey('App\Agents\AbstractTestAgent', $agents);

        // Cleanup
        File::deleteDirectory($testDir);
    }

    public function test_discovers_workflow_agents()
    {
        $testDir = app_path('Agents');
        File::makeDirectory($testDir, 0755, true, true);

        // Create a workflow agent
        $this->createTestAgent($testDir, 'TestWorkflowAgent', BaseWorkflowAgent::class, 'test_workflow');

        $agents = $this->discovery->discover();

        $this->assertArrayHasKey('App\Agents\TestWorkflowAgent', $agents);
        $this->assertEquals('test_workflow', $agents['App\Agents\TestWorkflowAgent']);

        // Cleanup
        File::deleteDirectory($testDir);
    }

    public function test_handles_missing_agent_directory()
    {
        // Ensure directory doesn't exist
        $testDir = app_path('Agents');
        if (File::exists($testDir)) {
            File::deleteDirectory($testDir);
        }

        $agents = $this->discovery->discover();

        $this->assertIsArray($agents);
        $this->assertEmpty($agents);
    }

    public function test_handles_agents_without_name_property()
    {
        $testDir = app_path('Agents');
        File::makeDirectory($testDir, 0755, true, true);

        // Create agent without name property
        File::put($testDir.'/NoNameAgent.php', '<?php
namespace App\Agents;

use Vizra\VizraADK\Agents\BaseLlmAgent;

class NoNameAgent extends BaseLlmAgent
{
    // No name property defined
    protected string $description = "An agent without a name";
}');

        $agents = $this->discovery->discover();

        // Should not include agents without names
        $this->assertArrayNotHasKey('App\Agents\NoNameAgent', $agents);

        // Cleanup
        File::deleteDirectory($testDir);
    }

    public function test_cache_is_used_in_production()
    {
        // Set environment to production
        config(['app.env' => 'production']);

        $testDir = app_path('Agents');
        File::makeDirectory($testDir, 0755, true, true);
        $this->createTestAgent($testDir, 'CachedAgent', BaseLlmAgent::class, 'cached_agent');

        // First discovery - should cache
        $agents1 = $this->discovery->discover();
        $this->assertArrayHasKey('App\Agents\CachedAgent', $agents1);

        // Add another agent
        $this->createTestAgent($testDir, 'NewAgent', BaseLlmAgent::class, 'new_agent');

        // Second discovery - should return cached result
        $agents2 = $this->discovery->discover();
        $this->assertEquals($agents1, $agents2);
        $this->assertArrayNotHasKey('App\Agents\NewAgent', $agents2);

        // Clear cache and rediscover
        $this->discovery->clearCache();
        $agents3 = $this->discovery->discover();
        $this->assertArrayHasKey('App\Agents\NewAgent', $agents3);

        // Cleanup
        File::deleteDirectory($testDir);
        config(['app.env' => 'testing']); // Reset to testing
    }

    public function test_cache_is_not_used_in_development()
    {
        // Set environment to local
        config(['app.env' => 'local']);

        $testDir = app_path('Agents');
        File::makeDirectory($testDir, 0755, true, true);
        $this->createTestAgent($testDir, 'DevAgent', BaseLlmAgent::class, 'dev_agent');

        // First discovery
        $agents1 = $this->discovery->discover();
        $this->assertArrayHasKey('App\Agents\DevAgent', $agents1);

        // Add another agent
        $this->createTestAgent($testDir, 'AnotherDevAgent', BaseLlmAgent::class, 'another_dev');

        // Second discovery - should include new agent immediately
        $agents2 = $this->discovery->discover();
        $this->assertArrayHasKey('App\Agents\AnotherDevAgent', $agents2);

        // Cleanup
        File::deleteDirectory($testDir);
        config(['app.env' => 'testing']); // Reset to testing
    }

    public function test_discovers_agents_in_subdirectories()
    {
        $testDir = app_path('Agents');
        $subDir = $testDir.'/SubFolder';
        File::makeDirectory($subDir, 0755, true, true);

        // Create agent in subdirectory
        File::put($subDir.'/SubAgent.php', '<?php
namespace App\Agents\SubFolder;

use Vizra\VizraADK\Agents\BaseLlmAgent;
use Vizra\VizraADK\System\AgentContext;

class SubAgent extends BaseLlmAgent
{
    protected string $name = "sub_agent";
    protected string $description = "Agent in subdirectory";
    
    public function run(mixed $input, AgentContext $context): mixed
    {
        return "Response from sub agent";
    }
}');

        $agents = $this->discovery->discover();

        $this->assertArrayHasKey('App\Agents\SubFolder\SubAgent', $agents);
        $this->assertEquals('sub_agent', $agents['App\Agents\SubFolder\SubAgent']);

        // Cleanup
        File::deleteDirectory($testDir);
    }

    protected function createTestAgent(string $dir, string $className, string $baseClass, string $agentName): void
    {
        $baseClassName = class_basename($baseClass);

        // BaseWorkflowAgent has a final run method and abstract executeWorkflow method
        if ($baseClassName === 'BaseWorkflowAgent') {
            $content = '<?php
namespace App\Agents;

use '.$baseClass.';
use Vizra\VizraADK\System\AgentContext;

class '.$className.' extends '.$baseClassName.'
{
    protected string $name = "'.$agentName.'";
    protected string $description = "Test workflow agent for discovery";
    
    protected function executeWorkflow(mixed $input, AgentContext $context): mixed
    {
        return "Response from workflow '.$agentName.'";
    }
}';
        } else {
            $content = '<?php
namespace App\Agents;

use '.$baseClass.';
use Vizra\VizraADK\System\AgentContext;

class '.$className.' extends '.$baseClassName.'
{
    protected string $name = "'.$agentName.'";
    protected string $description = "Test agent for discovery";
    
    public function run(mixed $input, AgentContext $context): mixed
    {
        return "Response from '.$agentName.'";
    }
}';
        }

        File::put($dir.'/'.$className.'.php', $content);
    }
}
