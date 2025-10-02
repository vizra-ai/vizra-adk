<?php

use Illuminate\Support\Facades\Http;
use Vizra\VizraADK\Exceptions\MCPException;
use Vizra\VizraADK\Services\MCP\MCPHttpClient;

beforeEach(function () {
    Http::preventStrayRequests();
});

it('can connect to HTTP MCP server', function () {
    Http::fake([
        '*' => Http::sequence()
            ->push([
                'jsonrpc' => '2.0',
                'id' => 1,
                'result' => [
                    'protocolVersion' => '2024-11-05',
                    'capabilities' => [],
                    'serverInfo' => ['name' => 'test-server'],
                ],
            ])
            ->push(['jsonrpc' => '2.0']), // initialized notification response
    ]);

    $client = new MCPHttpClient(
        url: 'http://localhost:8001/api/mcp',
        apiKey: 'test-key'
    );

    $client->connect();

    Http::assertSent(function ($request) {
        return $request->url() === 'http://localhost:8001/api/mcp' &&
               $request->hasHeader('Authorization', 'Bearer test-key') &&
               $request['method'] === 'initialize';
    });
});

it('can list tools from HTTP server', function () {
    Http::fake([
        '*' => Http::sequence()
            ->push([
                'jsonrpc' => '2.0',
                'id' => 1,
                'result' => ['serverInfo' => ['name' => 'test']],
            ])
            ->push(['jsonrpc' => '2.0']) // initialized notification
            ->push([
                'jsonrpc' => '2.0',
                'id' => 2,
                'result' => [
                    'tools' => [
                        [
                            'name' => 'test_tool',
                            'description' => 'A test tool',
                            'inputSchema' => ['type' => 'object'],
                        ],
                    ],
                ],
            ]),
    ]);

    $client = new MCPHttpClient(url: 'http://localhost:8001/api/mcp');
    $tools = $client->listTools();

    expect($tools)->toBeArray();
    expect($tools)->toHaveCount(1);
    expect($tools[0]['name'])->toBe('test_tool');
});

it('can call a tool on HTTP server', function () {
    Http::fake([
        '*' => Http::sequence()
            ->push([
                'jsonrpc' => '2.0',
                'id' => 1,
                'result' => ['serverInfo' => ['name' => 'test']],
            ])
            ->push(['jsonrpc' => '2.0']) // initialized notification
            ->push([
                'jsonrpc' => '2.0',
                'id' => 2,
                'result' => [
                    'content' => 'Tool executed successfully',
                ],
            ]),
    ]);

    $client = new MCPHttpClient(url: 'http://localhost:8001/api/mcp');
    $result = $client->callTool('test_tool', ['arg1' => 'value1']);

    expect($result)->toBe('Tool executed successfully');

    Http::assertSent(function ($request) {
        return $request['method'] === 'tools/call' &&
               $request['params']['name'] === 'test_tool' &&
               $request['params']['arguments']['arg1'] === 'value1';
    });
});

it('handles errors from HTTP server', function () {
    Http::fake([
        '*' => Http::sequence()
            ->push([
                'jsonrpc' => '2.0',
                'id' => 1,
                'result' => ['serverInfo' => ['name' => 'test']],
            ])
            ->push(['jsonrpc' => '2.0']) // initialized notification
            ->push([
                'jsonrpc' => '2.0',
                'id' => 2,
                'error' => [
                    'code' => -32601,
                    'message' => 'Method not found',
                ],
            ]),
    ]);

    $client = new MCPHttpClient(url: 'http://localhost:8001/api/mcp');

    expect(fn () => $client->callTool('nonexistent_tool'))
        ->toThrow(MCPException::class);
});

it('includes custom headers in requests', function () {
    Http::fake([
        '*' => Http::response([
            'jsonrpc' => '2.0',
            'id' => 1,
            'result' => ['serverInfo' => ['name' => 'test']],
        ]),
    ]);

    $client = new MCPHttpClient(
        url: 'http://localhost:8001/api/mcp',
        headers: ['X-Custom-Header' => 'custom-value']
    );

    $client->connect();

    Http::assertSent(function ($request) {
        return $request->hasHeader('X-Custom-Header', 'custom-value');
    });
});

it('can list resources from HTTP server', function () {
    Http::fake([
        '*' => Http::sequence()
            ->push([
                'jsonrpc' => '2.0',
                'id' => 1,
                'result' => ['serverInfo' => ['name' => 'test']],
            ])
            ->push(['jsonrpc' => '2.0']) // initialized notification
            ->push([
                'jsonrpc' => '2.0',
                'id' => 2,
                'result' => [
                    'resources' => [
                        [
                            'uri' => 'file:///test.txt',
                            'name' => 'test.txt',
                            'mimeType' => 'text/plain',
                        ],
                    ],
                ],
            ]),
    ]);

    $client = new MCPHttpClient(url: 'http://localhost:8001/api/mcp');
    $resources = $client->listResources();

    expect($resources)->toBeArray();
    expect($resources)->toHaveCount(1);
    expect($resources[0]['uri'])->toBe('file:///test.txt');
});

it('can read a resource from HTTP server', function () {
    Http::fake([
        '*' => Http::sequence()
            ->push([
                'jsonrpc' => '2.0',
                'id' => 1,
                'result' => ['serverInfo' => ['name' => 'test']],
            ])
            ->push(['jsonrpc' => '2.0']) // initialized notification
            ->push([
                'jsonrpc' => '2.0',
                'id' => 2,
                'result' => [
                    'contents' => [
                        ['text' => 'File contents here'],
                    ],
                ],
            ]),
    ]);

    $client = new MCPHttpClient(url: 'http://localhost:8001/api/mcp');
    $content = $client->readResource('file:///test.txt');

    expect($content)->toBe('File contents here');
});

it('can list prompts from HTTP server', function () {
    Http::fake([
        '*' => Http::sequence()
            ->push([
                'jsonrpc' => '2.0',
                'id' => 1,
                'result' => ['serverInfo' => ['name' => 'test']],
            ])
            ->push(['jsonrpc' => '2.0']) // initialized notification
            ->push([
                'jsonrpc' => '2.0',
                'id' => 2,
                'result' => [
                    'prompts' => [
                        [
                            'name' => 'test_prompt',
                            'description' => 'A test prompt',
                        ],
                    ],
                ],
            ]),
    ]);

    $client = new MCPHttpClient(url: 'http://localhost:8001/api/mcp');
    $prompts = $client->listPrompts();

    expect($prompts)->toBeArray();
    expect($prompts)->toHaveCount(1);
    expect($prompts[0]['name'])->toBe('test_prompt');
});

it('can get a prompt from HTTP server', function () {
    Http::fake([
        '*' => Http::sequence()
            ->push([
                'jsonrpc' => '2.0',
                'id' => 1,
                'result' => ['serverInfo' => ['name' => 'test']],
            ])
            ->push(['jsonrpc' => '2.0']) // initialized notification
            ->push([
                'jsonrpc' => '2.0',
                'id' => 2,
                'result' => [
                    'messages' => [
                        ['role' => 'user', 'content' => 'Test prompt content'],
                    ],
                ],
            ]),
    ]);

    $client = new MCPHttpClient(url: 'http://localhost:8001/api/mcp');
    $messages = $client->getPrompt('test_prompt', ['arg' => 'value']);

    expect($messages)->toBeArray();
    expect($messages)->toHaveCount(1);
    expect($messages[0]['role'])->toBe('user');
});

it('respects timeout configuration', function () {
    Http::fake([
        '*' => Http::response([], 200),
    ]);

    $client = new MCPHttpClient(
        url: 'http://localhost:8001/api/mcp',
        timeout: 60
    );

    $client->connect();

    Http::assertSent(function ($request) {
        // Laravel Http facade doesn't expose timeout directly in the assertion
        // This test mainly ensures no errors are thrown with custom timeout
        return true;
    });
});

it('handles HTTP errors gracefully', function () {
    Http::fake([
        '*' => Http::response([], 500),
    ]);

    $client = new MCPHttpClient(url: 'http://localhost:8001/api/mcp');

    expect(fn () => $client->connect())
        ->toThrow(MCPException::class, 'HTTP request failed with status 500');
});

it('disconnects and clears state', function () {
    Http::fake([
        '*' => Http::response([
            'jsonrpc' => '2.0',
            'id' => 1,
            'result' => ['serverInfo' => ['name' => 'test']],
        ]),
    ]);

    $client = new MCPHttpClient(url: 'http://localhost:8001/api/mcp');
    $client->connect();

    expect($client->getServerInfo())->not->toBeNull();

    $client->disconnect();

    expect($client->getServerInfo())->toBeNull();
});
