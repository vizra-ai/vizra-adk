<?php

namespace Vizra\VizraADK\Services\MCP;

use Illuminate\Support\Facades\Http;
use Vizra\VizraADK\Contracts\MCPClientInterface;
use Vizra\VizraADK\Exceptions\MCPException;

/**
 * HTTP/SSE transport implementation for MCP protocol
 * Communicates with remote MCP servers via HTTP endpoints
 */
class MCPHttpClient implements MCPClientInterface
{
    private bool $connected = false;

    private int $messageId = 0;

    private ?array $serverInfo = null;

    public function __construct(
        private string $url,
        private ?string $apiKey = null,
        private int $timeout = 30,
        private array $headers = []
    ) {}

    /**
     * Connect to the MCP server (initialize)
     */
    public function connect(): void
    {
        if ($this->connected) {
            return;
        }

        try {
            // Send initialize request
            $response = $this->sendRequest('initialize', [
                'protocolVersion' => '2024-11-05',
                'capabilities' => [
                    'tools' => [],
                    'resources' => [],
                    'prompts' => [],
                ],
                'clientInfo' => [
                    'name' => 'vizra-adk',
                    'version' => '1.0.0',
                ],
            ]);

            $this->serverInfo = $response;
            $this->connected = true;

            // Send initialized notification (fire and forget)
            $this->sendNotification('notifications/initialized');

        } catch (\Exception $e) {
            throw new MCPException("Failed to connect to MCP HTTP server: {$e->getMessage()}");
        }
    }

    /**
     * Disconnect from the MCP server
     */
    public function disconnect(): void
    {
        $this->connected = false;
        $this->serverInfo = null;
    }

    /**
     * List available tools from the MCP server
     */
    public function listTools(): array
    {
        $this->ensureConnected();

        $response = $this->sendRequest('tools/list');

        return $response['tools'] ?? [];
    }

    /**
     * Call a tool on the MCP server
     */
    public function callTool(string $toolName, array $arguments = []): string
    {
        $this->ensureConnected();

        $response = $this->sendRequest('tools/call', [
            'name' => $toolName,
            'arguments' => empty($arguments) ? (object) [] : $arguments,
        ]);

        if (isset($response['content'])) {
            if (is_array($response['content'])) {
                return json_encode($response['content']);
            }

            return (string) $response['content'];
        }

        return json_encode($response);
    }

    /**
     * List available resources from the MCP server
     */
    public function listResources(): array
    {
        $this->ensureConnected();

        $response = $this->sendRequest('resources/list');

        return $response['resources'] ?? [];
    }

    /**
     * Read a resource from the MCP server
     */
    public function readResource(string $uri): string
    {
        $this->ensureConnected();

        $response = $this->sendRequest('resources/read', [
            'uri' => $uri,
        ]);

        if (isset($response['contents'])) {
            $contents = $response['contents'];
            if (is_array($contents) && isset($contents[0]['text'])) {
                return $contents[0]['text'];
            }

            return json_encode($contents);
        }

        return json_encode($response);
    }

    /**
     * List available prompts from the MCP server
     */
    public function listPrompts(): array
    {
        $this->ensureConnected();

        $response = $this->sendRequest('prompts/list');

        return $response['prompts'] ?? [];
    }

    /**
     * Get a prompt from the MCP server
     */
    public function getPrompt(string $name, array $arguments = []): array
    {
        $this->ensureConnected();

        $response = $this->sendRequest('prompts/get', [
            'name' => $name,
            'arguments' => $arguments,
        ]);

        return $response['messages'] ?? [];
    }

    /**
     * Send a JSON-RPC request to the HTTP server
     */
    private function sendRequest(string $method, array $params = []): array
    {
        $messageId = ++$this->messageId;

        $request = [
            'jsonrpc' => '2.0',
            'id' => $messageId,
            'method' => $method,
        ];

        if (! empty($params)) {
            $request['params'] = $params;
        }

        try {
            $httpClient = Http::timeout($this->timeout)
                ->withHeaders($this->buildHeaders());

            $response = $httpClient->post($this->url, $request);

            if (! $response->successful()) {
                throw new MCPException(
                    "HTTP request failed with status {$response->status()}: {$response->body()}"
                );
            }

            $data = $response->json();

            if (! is_array($data)) {
                throw new MCPException('Invalid JSON-RPC response format');
            }

            if (isset($data['error'])) {
                throw new MCPException('MCP Error: '.json_encode($data['error']));
            }

            return $data['result'] ?? [];

        } catch (MCPException $e) {
            throw $e;
        } catch (\Exception $e) {
            throw new MCPException("HTTP request failed: {$e->getMessage()}");
        }
    }

    /**
     * Send a JSON-RPC notification (no response expected)
     */
    private function sendNotification(string $method, array $params = []): void
    {
        $notification = [
            'jsonrpc' => '2.0',
            'method' => $method,
        ];

        if (! empty($params)) {
            $notification['params'] = $params;
        }

        try {
            Http::timeout($this->timeout)
                ->withHeaders($this->buildHeaders())
                ->post($this->url, $notification);
        } catch (\Exception $e) {
            // Notifications are fire-and-forget, so we don't throw on errors
            // but we could log this if needed
        }
    }

    /**
     * Build HTTP headers for the request
     */
    private function buildHeaders(): array
    {
        $headers = array_merge([
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ], $this->headers);

        if ($this->apiKey) {
            $headers['Authorization'] = "Bearer {$this->apiKey}";
        }

        return $headers;
    }

    /**
     * Ensure the connection is established
     */
    private function ensureConnected(): void
    {
        if (! $this->connected) {
            $this->connect();
        }
    }

    /**
     * Get server information from initialization
     */
    public function getServerInfo(): ?array
    {
        return $this->serverInfo;
    }

    /**
     * Destructor to clean up
     */
    public function __destruct()
    {
        $this->disconnect();
    }
}
