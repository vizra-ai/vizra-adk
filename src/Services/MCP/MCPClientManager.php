<?php

namespace Vizra\VizraADK\Services\MCP;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Vizra\VizraADK\Contracts\MCPClientInterface;
use Vizra\VizraADK\Enums\MCPTransport;
use Vizra\VizraADK\Exceptions\MCPException;

class MCPClientManager
{
    private array $clients = [];

    private array $serverConfigs;

    private const CACHE_TTL = 300; // 5 minutes

    public function __construct()
    {
        $this->serverConfigs = config('vizra-adk.mcp_servers', []);
    }

    /**
     * Get an MCP client for the specified server
     */
    public function getClient(string $serverName): MCPClientInterface
    {
        // If we have a cached client, check if it's still connected
        if (isset($this->clients[$serverName])) {
            try {
                // Try to use the existing client
                return $this->clients[$serverName];
            } catch (\Exception $e) {
                // If the client is not working, remove it from cache
                unset($this->clients[$serverName]);
            }
        }

        // Create a new client
        $this->clients[$serverName] = $this->createClient($serverName);
        return $this->clients[$serverName];
    }

    /**
     * Check if a server is configured and enabled
     */
    public function isServerEnabled(string $serverName): bool
    {
        $config = $this->serverConfigs[$serverName] ?? null;

        return $config && ($config['enabled'] ?? true);
    }

    /**
     * Get all enabled server names
     */
    public function getEnabledServers(): array
    {
        return array_keys(array_filter($this->serverConfigs, function ($config) {
            return $config['enabled'] ?? true;
        }));
    }

    /**
     * Discover tools from a specific server
     */
    public function discoverTools(string $serverName): array
    {
        if (! $this->isServerEnabled($serverName)) {
            return [];
        }

        $cacheKey = "mcp_tools_{$serverName}";

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($serverName) {
            try {
                $client = $this->getClient($serverName);
                $tools = $client->listTools();

                Log::info('Discovered {count} tools from MCP server {server}', [
                    'count' => count($tools),
                    'server' => $serverName,
                    'tools' => array_column($tools, 'name'),
                ]);

                return $tools;
            } catch (MCPException $e) {
                Log::warning('Failed to discover tools from MCP server {server}: {error}', [
                    'server' => $serverName,
                    'error' => $e->getMessage(),
                ]);

                return [];
            }
        });
    }

    /**
     * Discover resources from a specific server
     */
    public function discoverResources(string $serverName): array
    {
        if (! $this->isServerEnabled($serverName)) {
            return [];
        }

        $cacheKey = "mcp_resources_{$serverName}";

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($serverName) {
            try {
                $client = $this->getClient($serverName);

                return $client->listResources();
            } catch (MCPException $e) {
                Log::warning('Failed to discover resources from MCP server {server}: {error}', [
                    'server' => $serverName,
                    'error' => $e->getMessage(),
                ]);

                return [];
            }
        });
    }

    /**
     * Discover prompts from a specific server
     */
    public function discoverPrompts(string $serverName): array
    {
        if (! $this->isServerEnabled($serverName)) {
            return [];
        }

        $cacheKey = "mcp_prompts_{$serverName}";

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($serverName) {
            try {
                $client = $this->getClient($serverName);

                return $client->listPrompts();
            } catch (MCPException $e) {
                Log::warning('Failed to discover prompts from MCP server {server}: {error}', [
                    'server' => $serverName,
                    'error' => $e->getMessage(),
                ]);

                return [];
            }
        });
    }

    /**
     * Discover all tools from all enabled servers
     */
    public function discoverAllTools(): array
    {
        $allTools = [];

        foreach ($this->getEnabledServers() as $serverName) {
            $tools = $this->discoverTools($serverName);

            foreach ($tools as $tool) {
                // Add server context to tool definition
                $tool['_mcp_server'] = $serverName;
                $allTools[] = $tool;
            }
        }

        return $allTools;
    }

    /**
     * Call a tool on a specific server
     */
    public function callTool(string $serverName, string $toolName, array $arguments = []): string
    {
        if (! $this->isServerEnabled($serverName)) {
            throw new MCPException("MCP server '{$serverName}' is not enabled");
        }

        try {
            $client = $this->getClient($serverName);

            Log::info('Calling MCP tool {tool} on server {server}', [
                'tool' => $toolName,
                'server' => $serverName,
                'arguments' => $arguments,
            ]);

            $result = $client->callTool($toolName, $arguments);

            Log::info('MCP tool call completed successfully', [
                'tool' => $toolName,
                'server' => $serverName,
            ]);

            return $result;
        } catch (MCPException $e) {
            Log::error('MCP tool call failed: {error}', [
                'tool' => $toolName,
                'server' => $serverName,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Read a resource from a specific server
     */
    public function readResource(string $serverName, string $uri): string
    {
        if (! $this->isServerEnabled($serverName)) {
            throw new MCPException("MCP server '{$serverName}' is not enabled");
        }

        try {
            $client = $this->getClient($serverName);

            return $client->readResource($uri);
        } catch (MCPException $e) {
            Log::error('Failed to read MCP resource: {error}', [
                'server' => $serverName,
                'uri' => $uri,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Clear discovery cache for a server
     */
    public function clearCache(?string $serverName = null): void
    {
        if ($serverName) {
            Cache::forget("mcp_tools_{$serverName}");
            Cache::forget("mcp_resources_{$serverName}");
            Cache::forget("mcp_prompts_{$serverName}");
        } else {
            // Clear all MCP caches
            foreach ($this->getEnabledServers() as $server) {
                $this->clearCache($server);
            }
        }
    }

    /**
     * Test connectivity to a server
     */
    public function testConnection(string $serverName): array
    {
        if (! $this->isServerEnabled($serverName)) {
            return [
                'success' => false,
                'error' => "Server '{$serverName}' is not enabled",
            ];
        }

        try {
            $client = $this->getClient($serverName);
            $tools = $client->listTools();

            return [
                'success' => true,
                'server' => $serverName,
                'tools_count' => count($tools),
                'tools' => array_column($tools, 'name'),
            ];
        } catch (MCPException $e) {
            return [
                'success' => false,
                'server' => $serverName,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Test connectivity to all enabled servers
     */
    public function testAllConnections(): array
    {
        $results = [];

        foreach ($this->getEnabledServers() as $serverName) {
            $results[$serverName] = $this->testConnection($serverName);
        }

        return $results;
    }

    /**
     * Disconnect all clients
     */
    public function disconnectAll(): void
    {
        foreach ($this->clients as $client) {
            $client->disconnect();
        }

        $this->clients = [];
    }

    /**
     * Create a new MCP client for the specified server
     */
    private function createClient(string $serverName): MCPClientInterface
    {
        if (! isset($this->serverConfigs[$serverName])) {
            throw new MCPException("MCP server '{$serverName}' is not configured");
        }

        $config = $this->serverConfigs[$serverName];

        if (! ($config['enabled'] ?? true)) {
            throw new MCPException("MCP server '{$serverName}' is disabled");
        }

        // Determine transport type (default to STDIO for backwards compatibility)
        $transport = MCPTransport::from($config['transport'] ?? 'stdio');

        return match ($transport) {
            MCPTransport::STDIO => $this->createStdioClient($config, $serverName),
            MCPTransport::HTTP => $this->createHttpClient($config, $serverName),
        };
    }

    /**
     * Create a STDIO transport client
     */
    private function createStdioClient(array $config, string $serverName): MCPStdioClient
    {
        if (empty($config['command'])) {
            throw new MCPException("MCP server '{$serverName}' has no command configured");
        }

        return new MCPStdioClient(
            command: $config['command'],
            args: $config['args'] ?? [],
            timeout: $config['timeout'] ?? 30,
            usePty: $config['use_pty'] ?? false
        );
    }

    /**
     * Create an HTTP transport client
     */
    private function createHttpClient(array $config, string $serverName): MCPHttpClient
    {
        if (empty($config['url'])) {
            throw new MCPException("MCP server '{$serverName}' has no URL configured for HTTP transport");
        }

        return new MCPHttpClient(
            url: $config['url'],
            apiKey: $config['api_key'] ?? null,
            timeout: $config['timeout'] ?? 30,
            headers: $config['headers'] ?? []
        );
    }

    /**
     * Destructor to clean up all connections
     */
    public function __destruct()
    {
        $this->disconnectAll();
    }
}
