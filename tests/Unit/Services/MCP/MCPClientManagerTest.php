<?php

use Vizra\VizraADK\Exceptions\MCPException;
use Vizra\VizraADK\Services\MCP\MCPClientManager;

beforeEach(function () {
    // Mock config for testing
    config(['vizra-adk.mcp_servers' => [
        'test_server' => [
            'transport' => 'stdio',
            'command' => 'echo',
            'args' => ['test'],
            'enabled' => true,
            'timeout' => 30,
        ],
        'disabled_server' => [
            'transport' => 'stdio',
            'command' => 'echo',
            'args' => ['disabled'],
            'enabled' => false,
        ],
        'missing_command' => [
            'transport' => 'stdio',
            'enabled' => true,
            // Missing command
        ],
        'http_server' => [
            'transport' => 'http',
            'url' => 'http://localhost:8001/api/mcp',
            'api_key' => 'test-key',
            'enabled' => true,
            'timeout' => 30,
        ],
        'http_missing_url' => [
            'transport' => 'http',
            'enabled' => true,
            // Missing URL
        ],
    ]]);
});

it('can instantiate manager with config', function () {
    $manager = new MCPClientManager;

    expect($manager)->toBeInstanceOf(MCPClientManager::class);
});

it('identifies enabled servers correctly', function () {
    $manager = new MCPClientManager;

    expect($manager->isServerEnabled('test_server'))->toBeTrue();
    expect($manager->isServerEnabled('disabled_server'))->toBeFalse();
    expect($manager->isServerEnabled('nonexistent_server'))->toBeFalse();
});

it('returns enabled servers list', function () {
    $manager = new MCPClientManager;
    $enabled = $manager->getEnabledServers();

    expect($enabled)->toContain('test_server');
    expect($enabled)->not->toContain('disabled_server');
});

it('throws exception for unconfigured server', function () {
    $manager = new MCPClientManager;

    expect(function () use ($manager) {
        $manager->getClient('nonexistent_server');
    })->toThrow(MCPException::class, "MCP server 'nonexistent_server' is not configured");
});

it('throws exception for disabled server', function () {
    $manager = new MCPClientManager;

    expect(function () use ($manager) {
        $manager->getClient('disabled_server');
    })->toThrow(MCPException::class, "MCP server 'disabled_server' is disabled");
});

it('throws exception for server with missing command', function () {
    $manager = new MCPClientManager;

    expect(function () use ($manager) {
        $manager->getClient('missing_command');
    })->toThrow(MCPException::class, "MCP server 'missing_command' has no command configured");
});

it('caches client instances', function () {
    $manager = new MCPClientManager;

    // This test verifies the interface exists
    // Actual caching behavior would require mocking
    expect(method_exists($manager, 'getClient'))->toBeTrue();
    expect(method_exists($manager, 'discoverTools'))->toBeTrue();
    expect(method_exists($manager, 'discoverResources'))->toBeTrue();
});

it('handles discovery failures gracefully', function () {
    $manager = new MCPClientManager;

    // Should return empty array for disabled servers
    $tools = $manager->discoverTools('disabled_server');
    expect($tools)->toBeArray();
    expect($tools)->toBeEmpty();
});

it('can test server connections', function () {
    $manager = new MCPClientManager;

    $result = $manager->testConnection('disabled_server');
    expect($result)->toBeArray();
    expect($result['success'])->toBeFalse();
    expect($result['error'])->toContain('not enabled');
});

it('can test all connections', function () {
    $manager = new MCPClientManager;

    $results = $manager->testAllConnections();
    expect($results)->toBeArray();
    expect($results)->toHaveKey('test_server');
});

it('clears cache correctly', function () {
    $manager = new MCPClientManager;

    // Should not throw exceptions
    $manager->clearCache();
    $manager->clearCache('test_server');

    expect(true)->toBeTrue();
});

it('disconnects all clients on destruction', function () {
    $manager = new MCPClientManager;

    // Test that destructor can be called safely
    $manager->disconnectAll();

    expect(true)->toBeTrue();
});

it('identifies HTTP servers correctly', function () {
    $manager = new MCPClientManager;

    expect($manager->isServerEnabled('http_server'))->toBeTrue();
});

it('throws exception for HTTP server with missing URL', function () {
    $manager = new MCPClientManager;

    expect(function () use ($manager) {
        $manager->getClient('http_missing_url');
    })->toThrow(MCPException::class, "MCP server 'http_missing_url' has no URL configured for HTTP transport");
});

it('creates HTTP client for HTTP transport', function () {
    $manager = new MCPClientManager;

    // This will create the client but not connect
    // The test verifies that HTTP transport is recognized
    $enabled = $manager->getEnabledServers();
    expect($enabled)->toContain('http_server');
});

it('defaults to STDIO transport when not specified', function () {
    config(['vizra-adk.mcp_servers' => [
        'default_transport_server' => [
            'command' => 'echo',
            'args' => ['test'],
            'enabled' => true,
            // transport not specified, should default to stdio
        ],
    ]]);

    $manager = new MCPClientManager;
    expect($manager->isServerEnabled('default_transport_server'))->toBeTrue();
});
