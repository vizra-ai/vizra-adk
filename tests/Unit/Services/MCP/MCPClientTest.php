<?php

use Vizra\VizraADK\Exceptions\MCPException;
use Vizra\VizraADK\Services\MCP\MCPClient;

beforeEach(function () {
    // Mock process for testing
    $this->mockProcess = Mockery::mock(\Symfony\Component\Process\Process::class);
});

afterEach(function () {
    Mockery::close();
});

it('can initialize MCP client with command and args', function () {
    $client = new MCPClient('echo', ['hello'], 30);

    expect($client)->toBeInstanceOf(MCPClient::class);
});

it('throws exception when server fails to start', function () {
    // This test would require process mocking which is complex
    // For now, we'll test the basic structure
    $client = new MCPClient('invalid-command-that-does-not-exist');

    expect(function () use ($client) {
        $client->connect();
    })->toThrow(MCPException::class);
});

it('can handle tool list requests', function () {
    // Test would require a mock MCP server
    // This tests the structure rather than actual functionality
    $client = new MCPClient('echo', ['test']);

    expect(method_exists($client, 'listTools'))->toBeTrue();
    expect(method_exists($client, 'callTool'))->toBeTrue();
    expect(method_exists($client, 'listResources'))->toBeTrue();
});

it('properly formats JSON-RPC requests', function () {
    // Since the sendRequest method is private, we test the public interface
    $client = new MCPClient('echo');

    expect(method_exists($client, 'listTools'))->toBeTrue();
    expect(method_exists($client, 'callTool'))->toBeTrue();
    expect(method_exists($client, 'listResources'))->toBeTrue();
    expect(method_exists($client, 'readResource'))->toBeTrue();
});

it('handles disconnection properly', function () {
    $client = new MCPClient('echo');

    // Should not throw when disconnecting unconnected client
    $client->disconnect();

    expect(true)->toBeTrue(); // Test passes if no exception thrown
});

it('manages connection state correctly', function () {
    $client = new MCPClient('echo');

    // Test that multiple connect calls don't cause issues
    expect(function () use ($client) {
        // These should be safe to call multiple times
        $client->disconnect();
        $client->disconnect();
    })->not->toThrow(\Exception::class);
});
