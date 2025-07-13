<?php

use Vizra\VizraADK\Agents\BaseLlmAgent;
use Vizra\VizraADK\Services\MCP\MCPClientManager;
use Vizra\VizraADK\Services\MCP\MCPToolDiscovery;
use Vizra\VizraADK\Tools\MCP\MCPToolWrapper;

beforeEach(function () {
    $this->mockManager = Mockery::mock(MCPClientManager::class);
    $this->discovery = new MCPToolDiscovery($this->mockManager);

    // Create a test agent class
    $this->testAgent = new class extends BaseLlmAgent
    {
        protected string $name = 'test_agent';

        protected string $description = 'Test agent';

        protected string $instructions = 'Test instructions';
    };
});

afterEach(function () {
    Mockery::close();
});

it('can instantiate discovery service', function () {
    expect($this->discovery)->toBeInstanceOf(MCPToolDiscovery::class);
});

it('returns empty array for agent without MCP servers', function () {
    $servers = $this->discovery->getMCPServersForAgent($this->testAgent);

    expect($servers)->toBeArray();
    expect($servers)->toBeEmpty();
});

it('detects agents without MCP servers', function () {
    $hasMCP = $this->discovery->agentHasMCPServers($this->testAgent);

    expect($hasMCP)->toBeFalse();
});

it('discovers tools for agent with MCP servers', function () {
    // Create agent with MCP servers
    $agentWithMCP = new class extends BaseLlmAgent
    {
        protected string $name = 'mcp_agent';

        protected string $description = 'MCP test agent';

        protected string $instructions = 'Test instructions';
        
        protected array $mcpServers = ['test_server'];
    };

    $this->mockManager->shouldReceive('isServerEnabled')
        ->with('test_server')
        ->andReturn(true);

    $this->mockManager->shouldReceive('discoverTools')
        ->with('test_server')
        ->andReturn([
            ['name' => 'tool1', 'description' => 'Test tool 1'],
            ['name' => 'tool2', 'description' => 'Test tool 2'],
        ]);

    $tools = $this->discovery->discoverToolsForAgent($agentWithMCP);

    expect($tools)->toBeArray();
    expect($tools)->toHaveCount(2);
    expect($tools[0])->toBeInstanceOf(MCPToolWrapper::class);
});

it('skips disabled servers', function () {
    $agentWithMCP = new class extends BaseLlmAgent
    {
        protected string $name = 'mcp_agent';

        protected string $description = 'MCP test agent';

        protected string $instructions = 'Test instructions';
        
        protected array $mcpServers = ['disabled_server'];
    };

    $this->mockManager->shouldReceive('isServerEnabled')
        ->with('disabled_server')
        ->andReturn(false);

    $tools = $this->discovery->discoverToolsForAgent($agentWithMCP);

    expect($tools)->toBeArray();
    expect($tools)->toBeEmpty();
});

it('handles discovery errors gracefully', function () {
    $agentWithMCP = new class extends BaseLlmAgent
    {
        protected string $name = 'mcp_agent';

        protected string $description = 'MCP test agent';

        protected string $instructions = 'Test instructions';
        
        protected array $mcpServers = ['error_server'];
    };

    $this->mockManager->shouldReceive('isServerEnabled')
        ->with('error_server')
        ->andReturn(true);

    $this->mockManager->shouldReceive('discoverTools')
        ->with('error_server')
        ->andThrow(new Exception('Discovery failed'));

    $tools = $this->discovery->discoverToolsForAgent($agentWithMCP);

    expect($tools)->toBeArray();
    expect($tools)->toBeEmpty();
});

it('provides agent MCP tools info', function () {
    $info = $this->discovery->getAgentMCPToolsInfo($this->testAgent);

    expect($info)->toBeArray();
    expect($info['has_mcp_tools'])->toBeFalse();
    expect($info['servers'])->toBeEmpty();
    expect($info['total_tools'])->toBe(0);
});

it('validates agent MCP servers', function () {
    $agentWithMCP = new class extends BaseLlmAgent
    {
        protected string $name = 'mcp_agent';

        protected string $description = 'MCP test agent';

        protected string $instructions = 'Test instructions';
        
        protected array $mcpServers = ['test_server'];
    };

    $this->mockManager->shouldReceive('testConnection')
        ->with('test_server')
        ->andReturn(['success' => true, 'tools_count' => 2]);

    $results = $this->discovery->validateAgentMCPServers($agentWithMCP);

    expect($results)->toBeArray();
    expect($results)->toHaveKey('test_server');
    expect($results['test_server']['success'])->toBeTrue();
});

it('extracts MCP servers from property correctly', function () {
    $agentWithMCP = new class extends BaseLlmAgent
    {
        protected string $name = 'mcp_agent';

        protected string $description = 'MCP test agent';

        protected string $instructions = 'Test instructions';
        
        protected array $mcpServers = ['server1', 'server2'];
    };

    $servers = $this->discovery->getMCPServersForAgent($agentWithMCP);

    expect($servers)->toContain('server1');
    expect($servers)->toContain('server2');
    expect($servers)->toHaveCount(2);
});
