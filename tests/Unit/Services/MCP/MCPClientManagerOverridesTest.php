<?php

namespace Vizra\VizraADK\Tests\Unit\Services\MCP;

use Vizra\VizraADK\Services\MCP\MCPClientManager;

beforeEach(function () {
    // Set up test MCP server configurations
    config([
        'vizra-adk.mcp_servers' => [
            'test_server' => [
                'transport' => 'stdio',
                'command' => 'npx',
                'args' => [
                    '@test/server',
                    '--token',
                    'default-token',
                ],
                'enabled' => true,
                'timeout' => 30,
            ],
            'http_server' => [
                'transport' => 'http',
                'url' => 'http://localhost:8000/mcp',
                'api_key' => 'default-api-key',
                'enabled' => true,
                'timeout' => 30,
                'headers' => [
                    'X-Default-Header' => 'default-value',
                ],
            ],
        ],
    ]);

    $this->manager = new MCPClientManager();
});

it('loads base configuration without overrides', function () {
    expect($this->manager->isServerEnabled('test_server'))->toBeTrue()
        ->and($this->manager->isServerEnabled('http_server'))->toBeTrue();
});

it('applies context overrides to stdio server configuration', function () {
    $overrides = [
        'test_server' => [
            'args' => [
                '@test/server',
                '--token',
                'tenant-specific-token-123',
            ],
        ],
    ];

    $this->manager->setContextOverrides($overrides);

    // Use reflection to access protected method and verify merged config
    $reflection = new \ReflectionClass($this->manager);
    $method = $reflection->getMethod('getServerConfigs');
    $method->setAccessible(true);

    $configs = $method->invoke($this->manager);

    expect($configs['test_server']['args'])->toBe([
        '@test/server',
        '--token',
        'tenant-specific-token-123',
    ]);
});

it('applies context overrides to http server configuration', function () {
    $overrides = [
        'http_server' => [
            'api_key' => 'tenant-api-key-xyz',
            'headers' => [
                'X-Tenant-ID' => '123',
            ],
        ],
    ];

    $this->manager->setContextOverrides($overrides);

    $reflection = new \ReflectionClass($this->manager);
    $method = $reflection->getMethod('getServerConfigs');
    $method->setAccessible(true);

    $configs = $method->invoke($this->manager);

    expect($configs['http_server']['api_key'])->toBe('tenant-api-key-xyz')
        ->and($configs['http_server']['headers'])->toHaveKey('X-Tenant-ID')
        ->and($configs['http_server']['headers']['X-Tenant-ID'])->toBe('123');
});

it('deep merges nested configuration arrays', function () {
    $overrides = [
        'http_server' => [
            'headers' => [
                'X-Tenant-ID' => '456',
            ],
        ],
    ];

    $this->manager->setContextOverrides($overrides);

    $reflection = new \ReflectionClass($this->manager);
    $method = $reflection->getMethod('getServerConfigs');
    $method->setAccessible(true);

    $configs = $method->invoke($this->manager);

    // Should have both default header and new header
    expect($configs['http_server']['headers'])->toHaveKey('X-Default-Header')
        ->and($configs['http_server']['headers']['X-Default-Header'])->toBe('default-value')
        ->and($configs['http_server']['headers'])->toHaveKey('X-Tenant-ID')
        ->and($configs['http_server']['headers']['X-Tenant-ID'])->toBe('456');
});

it('allows disabling servers via context override', function () {
    $overrides = [
        'test_server' => [
            'enabled' => false,
        ],
    ];

    $this->manager->setContextOverrides($overrides);

    expect($this->manager->isServerEnabled('test_server'))->toBeFalse();
});

it('only overrides specified servers, leaves others unchanged', function () {
    $overrides = [
        'test_server' => [
            'timeout' => 60,
        ],
    ];

    $this->manager->setContextOverrides($overrides);

    $reflection = new \ReflectionClass($this->manager);
    $method = $reflection->getMethod('getServerConfigs');
    $method->setAccessible(true);

    $configs = $method->invoke($this->manager);

    // test_server should have new timeout
    expect($configs['test_server']['timeout'])->toBe(60);

    // http_server should retain default values
    expect($configs['http_server']['api_key'])->toBe('default-api-key');
});

it('clears cached config when setting new overrides', function () {
    // First call to load config
    $this->manager->isServerEnabled('test_server');

    // Set overrides
    $this->manager->setContextOverrides([
        'test_server' => [
            'enabled' => false,
        ],
    ]);

    // Should reflect new override
    expect($this->manager->isServerEnabled('test_server'))->toBeFalse();
});

it('handles empty overrides array', function () {
    $this->manager->setContextOverrides([]);

    expect($this->manager->isServerEnabled('test_server'))->toBeTrue();
});

it('ignores overrides for non-existent servers', function () {
    $overrides = [
        'non_existent_server' => [
            'api_key' => 'some-key',
        ],
        'test_server' => [
            'timeout' => 90,
        ],
    ];

    $this->manager->setContextOverrides($overrides);

    $reflection = new \ReflectionClass($this->manager);
    $method = $reflection->getMethod('getServerConfigs');
    $method->setAccessible(true);

    $configs = $method->invoke($this->manager);

    // Should apply valid override
    expect($configs['test_server']['timeout'])->toBe(90);

    // Should not create new server
    expect($configs)->not->toHaveKey('non_existent_server');
});

it('returns enabled servers list with overrides applied', function () {
    $overrides = [
        'test_server' => [
            'enabled' => false,
        ],
    ];

    $this->manager->setContextOverrides($overrides);

    $enabledServers = $this->manager->getEnabledServers();

    expect($enabledServers)->not->toContain('test_server')
        ->and($enabledServers)->toContain('http_server');
});

it('supports multiple override calls on same instance', function () {
    // First override
    $this->manager->setContextOverrides([
        'test_server' => [
            'timeout' => 60,
        ],
    ]);

    $reflection = new \ReflectionClass($this->manager);
    $method = $reflection->getMethod('getServerConfigs');
    $method->setAccessible(true);

    $configs = $method->invoke($this->manager);
    expect($configs['test_server']['timeout'])->toBe(60);

    // Second override (should replace first)
    $this->manager->setContextOverrides([
        'test_server' => [
            'timeout' => 120,
        ],
    ]);

    // Force reload by accessing method again
    $reflection = new \ReflectionClass($this->manager);
    $method = $reflection->getMethod('getServerConfigs');
    $method->setAccessible(true);

    $configs = $method->invoke($this->manager);
    expect($configs['test_server']['timeout'])->toBe(120);
});
