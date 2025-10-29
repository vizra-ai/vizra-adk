<?php

use Vizra\VizraADK\Exceptions\MCPException;
use Vizra\VizraADK\Exceptions\ToolExecutionException;
use Vizra\VizraADK\Memory\AgentMemory;
use Vizra\VizraADK\Services\MCP\MCPClientManager;
use Vizra\VizraADK\System\AgentContext;
use Vizra\VizraADK\Tools\MCP\MCPToolWrapper;

beforeEach(function () {
    $this->mockManager = Mockery::mock(MCPClientManager::class);
    $this->mockContext = Mockery::mock(AgentContext::class);
    $this->mockMemory = Mockery::mock(AgentMemory::class);

    $this->toolDefinition = [
        'name' => 'test_tool',
        'description' => 'A test tool',
        'inputSchema' => [
            'type' => 'object',
            'properties' => [
                'input' => ['type' => 'string', 'description' => 'Test input'],
            ],
            'required' => ['input'],
        ],
    ];

    $this->wrapper = new MCPToolWrapper(
        $this->mockManager,
        'test_server',
        $this->toolDefinition
    );
});

afterEach(function () {
    Mockery::close();
});

it('implements tool interface correctly', function () {
    expect($this->wrapper)->toBeInstanceOf(\Vizra\VizraADK\Contracts\ToolInterface::class);
});

it('returns correct tool definition', function () {
    $definition = $this->wrapper->definition();

    expect($definition)->toBeArray();
    expect($definition['name'])->toBe('test_tool');
    expect($definition['description'])->toBe('A test tool');
    expect($definition['parameters'])->toBeArray();
});

it('converts MCP input schema to Vizra format', function () {
    $definition = $this->wrapper->definition();

    expect($definition['parameters'])->toHaveKey('type');
    expect($definition['parameters'])->toHaveKey('properties');
});

it('handles empty input schema', function () {
    $emptyToolDef = [
        'name' => 'empty_tool',
        'description' => 'Tool with no schema',
    ];

    $wrapper = new MCPToolWrapper($this->mockManager, 'test_server', $emptyToolDef);
    $definition = $wrapper->definition();

    expect($definition['parameters']['type'])->toBe('object');
    expect($definition['parameters']['properties'])->toBeArray();
    expect($definition['parameters']['required'])->toBeArray();
});

it('executes MCP tool successfully', function () {
    $this->mockContext->shouldReceive('getState')
        ->with('mcp_config_overrides', [])
        ->andReturn([]);
    $this->mockContext->shouldReceive('getSessionId')->andReturn('test-session');
    $this->mockManager->shouldReceive('callTool')
        ->with('test_server', 'test_tool', ['input' => 'test'])
        ->andReturn('{"result": "success"}');

    $result = $this->wrapper->execute(['input' => 'test'], $this->mockContext, $this->mockMemory);

    expect($result)->toBe('{"result": "success"}');
});

it('handles MCP execution errors', function () {
    $this->mockContext->shouldReceive('getState')
        ->with('mcp_config_overrides', [])
        ->andReturn([]);
    $this->mockContext->shouldReceive('getSessionId')->andReturn('test-session');
    $this->mockManager->shouldReceive('callTool')
        ->andThrow(new MCPException('MCP server error'));

    expect(function () {
        $this->wrapper->execute(['input' => 'test'], $this->mockContext, $this->mockMemory);
    })->toThrow(ToolExecutionException::class);
});

it('returns server name correctly', function () {
    expect($this->wrapper->getServerName())->toBe('test_server');
});

it('returns MCP definition correctly', function () {
    $mcpDef = $this->wrapper->getMCPDefinition();

    expect($mcpDef)->toBe($this->toolDefinition);
});

it('checks availability correctly', function () {
    $this->mockManager->shouldReceive('isServerEnabled')
        ->with('test_server')
        ->andReturn(true);

    expect($this->wrapper->isAvailable())->toBeTrue();
});

it('handles availability check errors', function () {
    $this->mockManager->shouldReceive('isServerEnabled')
        ->with('test_server')
        ->andThrow(new MCPException('Server error'));

    expect($this->wrapper->isAvailable())->toBeFalse();
});

it('provides human readable description', function () {
    $description = $this->wrapper->getDescription();

    expect($description)->toContain('test_server');
    expect($description)->toContain('A test tool');
});
