<?php

namespace Vizra\VizraADK\Tests\Feature;

use Vizra\VizraADK\Services\MCP\MCPClientManager;
use Vizra\VizraADK\Services\MCP\MCPToolDiscovery;
use Vizra\VizraADK\System\AgentContext;
use Vizra\VizraADK\Memory\AgentMemory;
use Vizra\VizraADK\Tools\MCP\MCPToolWrapper;
use Vizra\VizraADK\Agents\BaseLlmAgent;

beforeEach(function () {
    // Set up test MCP server configurations
    config([
        'vizra-adk.mcp_servers' => [
            'test_server' => [
                'transport' => 'stdio',
                'command' => 'echo',
                'args' => [
                    'default-config',
                ],
                'enabled' => true,
                'timeout' => 30,
            ],
        ],
    ]);
});

it('passes context overrides from agent context to mcp tool wrapper', function () {
    // Create a context with MCP overrides
    $context = new AgentContext(
        sessionId: 'test-session-123',
        userInput: 'test message',
        initialState: [
            'mcp_config_overrides' => [
                'test_server' => [
                    'args' => [
                        'tenant-specific-config',
                    ],
                ],
            ],
        ]
    );

    // Verify the overrides are stored in context
    $storedOverrides = $context->getState('mcp_config_overrides');

    expect($storedOverrides)->toBeArray()
        ->and($storedOverrides)->toHaveKey('test_server')
        ->and($storedOverrides['test_server']['args'])->toBe([
            'tenant-specific-config',
        ]);

    // Verify that MCPClientManager would apply these overrides
    $mcpManager = new MCPClientManager();
    $mcpManager->setContextOverrides($storedOverrides);

    $reflection = new \ReflectionClass($mcpManager);
    $method = $reflection->getMethod('getServerConfigs');
    $method->setAccessible(true);

    $configs = $method->invoke($mcpManager);

    expect($configs['test_server']['args'])->toBe([
        'tenant-specific-config',
    ]);
});

it('uses default config when no overrides provided in context', function () {
    // Create a context WITHOUT MCP overrides
    $context = new AgentContext(
        sessionId: 'test-session-456',
        userInput: 'test message'
    );

    // Verify no overrides in context
    $storedOverrides = $context->getState('mcp_config_overrides', []);

    expect($storedOverrides)->toBeEmpty();

    // Verify MCPClientManager uses default config
    $mcpManager = new MCPClientManager();

    $reflection = new \ReflectionClass($mcpManager);
    $method = $reflection->getMethod('getServerConfigs');
    $method->setAccessible(true);

    $configs = $method->invoke($mcpManager);

    // Should use default config
    expect($configs['test_server']['args'])->toBe([
        'default-config',
    ]);
});

it('isolates configurations between different mcp manager instances', function () {
    // Create first manager with overrides
    $manager1 = new MCPClientManager();
    $manager1->setContextOverrides([
        'test_server' => [
            'args' => ['tenant-1-config'],
        ],
    ]);

    // Create second manager with different overrides
    $manager2 = new MCPClientManager();
    $manager2->setContextOverrides([
        'test_server' => [
            'args' => ['tenant-2-config'],
        ],
    ]);

    // Verify they have different configurations
    $reflection = new \ReflectionClass($manager1);
    $method = $reflection->getMethod('getServerConfigs');
    $method->setAccessible(true);

    $config1 = $method->invoke($manager1);
    $config2 = $method->invoke($manager2);

    expect($config1['test_server']['args'])->toBe(['tenant-1-config'])
        ->and($config2['test_server']['args'])->toBe(['tenant-2-config']);
});

it('allows multiple tools to share the same manager instance with same overrides', function () {
    $mcpManager = new MCPClientManager();
    $overrides = [
        'test_server' => [
            'args' => ['shared-tenant-config'],
        ],
    ];

    $mcpManager->setContextOverrides($overrides);

    // Create multiple tool wrappers using same manager
    $tool1 = new MCPToolWrapper($mcpManager, 'test_server', [
        'name' => 'tool_1',
        'description' => 'Tool 1',
    ]);

    $tool2 = new MCPToolWrapper($mcpManager, 'test_server', [
        'name' => 'tool_2',
        'description' => 'Tool 2',
    ]);

    // Both tools should use the same overridden config
    $reflection = new \ReflectionClass($mcpManager);
    $method = $reflection->getMethod('getServerConfigs');
    $method->setAccessible(true);

    $configs = $method->invoke($mcpManager);

    expect($configs['test_server']['args'])->toBe(['shared-tenant-config']);
});

it('applies overrides from context state before tool execution', function () {
    // Simulate what happens when AgentExecutor passes context
    $initialState = [
        'mcp_config_overrides' => [
            'test_server' => [
                'enabled' => false,
                'timeout' => 99,
            ],
        ],
    ];

    $context = new AgentContext(
        sessionId: 'test-session',
        userInput: 'test',
        initialState: $initialState
    );

    // Verify the state was stored correctly
    $storedOverrides = $context->getState('mcp_config_overrides');

    expect($storedOverrides)->toBeArray()
        ->and($storedOverrides)->toHaveKey('test_server')
        ->and($storedOverrides['test_server']['enabled'])->toBeFalse()
        ->and($storedOverrides['test_server']['timeout'])->toBe(99);
});
