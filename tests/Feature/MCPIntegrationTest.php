<?php

use Vizra\VizraADK\Agents\BaseLlmAgent;
use Vizra\VizraADK\Services\MCP\MCPClientManager;
use Vizra\VizraADK\Services\MCP\MCPToolDiscovery;

beforeEach(function () {
    // Set up test configuration
    config(['vizra-adk.mcp_servers' => [
        'test_filesystem' => [
            'command' => 'echo',
            'args' => ['test-filesystem'],
            'enabled' => true,
            'timeout' => 5,
        ],
        'test_disabled' => [
            'command' => 'echo',
            'args' => ['test-disabled'],
            'enabled' => false,
        ],
    ]]);
});

it('can resolve MCP services from container', function () {
    $manager = app(MCPClientManager::class);
    $discovery = app(MCPToolDiscovery::class);

    expect($manager)->toBeInstanceOf(MCPClientManager::class);
    expect($discovery)->toBeInstanceOf(MCPToolDiscovery::class);
});

it('loads MCP configuration correctly', function () {
    $manager = app(MCPClientManager::class);

    expect($manager->isServerEnabled('test_filesystem'))->toBeTrue();
    expect($manager->isServerEnabled('test_disabled'))->toBeFalse();
    expect($manager->isServerEnabled('nonexistent'))->toBeFalse();
});

it('discovers enabled servers', function () {
    $manager = app(MCPClientManager::class);
    $enabled = $manager->getEnabledServers();

    expect($enabled)->toContain('test_filesystem');
    expect($enabled)->not->toContain('test_disabled');
});

it('integrates with agent tool loading', function () {
    // Create a test agent with MCP servers
    $agent = new class extends BaseLlmAgent
    {
        protected string $name = 'test_mcp_agent';

        protected string $description = 'Test MCP agent';

        protected string $instructions = 'Test agent with MCP';
        
        protected array $mcpServers = ['test_filesystem'];
    };

    // Test that tool discovery works
    $discovery = app(MCPToolDiscovery::class);
    $servers = $discovery->getMCPServersForAgent($agent);

    expect($servers)->toContain('test_filesystem');
    expect($discovery->agentHasMCPServers($agent))->toBeTrue();
});

it('handles agent without MCP servers', function () {
    $agent = new class extends BaseLlmAgent
    {
        protected string $name = 'no_mcp_agent';

        protected string $description = 'Agent without MCP';

        protected string $instructions = 'Regular agent';
    };

    $discovery = app(MCPToolDiscovery::class);

    expect($discovery->agentHasMCPServers($agent))->toBeFalse();
    expect($discovery->getMCPServersForAgent($agent))->toBeEmpty();
});

it('provides comprehensive agent info', function () {
    $agent = new class extends BaseLlmAgent
    {
        protected string $name = 'mixed_mcp_agent';

        protected string $description = 'Agent with mixed servers';

        protected string $instructions = 'Test agent';
        
        protected array $mcpServers = ['test_filesystem', 'test_disabled'];
    };

    $discovery = app(MCPToolDiscovery::class);
    $info = $discovery->getAgentMCPToolsInfo($agent);

    expect($info['has_mcp_tools'])->toBeTrue();
    expect($info['servers'])->toContain('test_filesystem');
    expect($info['servers'])->toContain('test_disabled');
});

it('validates server configurations', function () {
    $manager = app(MCPClientManager::class);

    // Test connection validation
    $result = $manager->testConnection('test_disabled');
    expect($result['success'])->toBeFalse();
    expect($result['error'])->toContain('not enabled');
});

it('handles cache operations', function () {
    $manager = app(MCPClientManager::class);

    // Should not throw exceptions
    $manager->clearCache();
    $manager->clearCache('test_filesystem');

    expect(true)->toBeTrue();
});

it('maintains correct instance behavior', function () {
    // MCPClientManager should NOT be a singleton (for multi-tenant isolation)
    $manager1 = app(MCPClientManager::class);
    $manager2 = app(MCPClientManager::class);

    expect($manager1)->not->toBe($manager2)
        ->and($manager1)->toBeInstanceOf(MCPClientManager::class)
        ->and($manager2)->toBeInstanceOf(MCPClientManager::class);

    // MCPToolDiscovery SHOULD be a singleton
    $discovery1 = app(MCPToolDiscovery::class);
    $discovery2 = app(MCPToolDiscovery::class);

    expect($discovery1)->toBe($discovery2);
});
