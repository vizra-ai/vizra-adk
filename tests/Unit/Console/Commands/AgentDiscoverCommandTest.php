<?php

namespace Vizra\VizraADK\Tests\Unit\Console\Commands;

use Vizra\VizraADK\Tests\TestCase;
use Vizra\VizraADK\Services\AgentDiscovery;
use Vizra\VizraADK\Services\AgentRegistry;
use Illuminate\Support\Facades\Artisan;
use Mockery;

class AgentDiscoverCommandTest extends TestCase
{
    public function test_discover_command_lists_agents()
    {
        // Mock discovery service
        $mockDiscovery = Mockery::mock(AgentDiscovery::class);
        $mockDiscovery->shouldReceive('discover')->once()->andReturn([
            'App\Agents\TestAgent' => 'test_agent',
            'App\Agents\AnotherAgent' => 'another_agent'
        ]);
        
        // Mock registry service
        $mockRegistry = Mockery::mock(AgentRegistry::class);
        $mockRegistry->shouldReceive('hasAgent')->with('test_agent')->andReturn(true);
        $mockRegistry->shouldReceive('hasAgent')->with('another_agent')->andReturn(false);
        
        $this->app->instance(AgentDiscovery::class, $mockDiscovery);
        $this->app->instance(AgentRegistry::class, $mockRegistry);
        
        // Run command
        Artisan::call('vizra:discover-agents');
        $output = Artisan::output();
        
        // Check output
        $this->assertStringContainsString('Found 2 agent(s)', $output);
        $this->assertStringContainsString('test_agent', $output);
        $this->assertStringContainsString('App\Agents\TestAgent', $output);
        $this->assertStringContainsString('another_agent', $output);
        $this->assertStringContainsString('App\Agents\AnotherAgent', $output);
        $this->assertStringContainsString('Registered', $output); // test_agent is registered
        $this->assertStringContainsString('Available', $output);  // another_agent is available
    }

    public function test_discover_command_with_clear_cache_option()
    {
        // Mock discovery service
        $mockDiscovery = Mockery::mock(AgentDiscovery::class);
        $mockDiscovery->shouldReceive('clearCache')->once();
        $mockDiscovery->shouldReceive('discover')->once()->andReturn([]);
        
        $this->app->instance(AgentDiscovery::class, $mockDiscovery);
        
        // Run command with --clear-cache
        Artisan::call('vizra:discover-agents', ['--clear-cache' => true]);
        $output = Artisan::output();
        
        $this->assertStringContainsString('Discovery cache cleared', $output);
    }

    public function test_discover_command_with_no_agents_found()
    {
        // Mock discovery service returning empty
        $mockDiscovery = Mockery::mock(AgentDiscovery::class);
        $mockDiscovery->shouldReceive('discover')->once()->andReturn([]);
        
        $this->app->instance(AgentDiscovery::class, $mockDiscovery);
        
        // Run command
        Artisan::call('vizra:discover-agents');
        $output = Artisan::output();
        
        $this->assertStringContainsString('No agents found', $output);
        $this->assertStringContainsString(config('vizra-adk.namespaces.agents', 'App\\Agents'), $output);
    }
}