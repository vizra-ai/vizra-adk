<?php

namespace Vizra\VizraADK\Services\MCP;

use Illuminate\Support\Facades\Log;
use ReflectionClass;
use Vizra\VizraADK\Agents\BaseLlmAgent;
use Vizra\VizraADK\Attributes\UseMCPServers;
use Vizra\VizraADK\Tools\MCP\MCPToolWrapper;

class MCPToolDiscovery
{
    public function __construct(
        private MCPClientManager $mcpManager
    ) {}

    /**
     * Discover MCP tools for a specific agent based on its UseMCPServers attribute
     */
    public function discoverToolsForAgent(BaseLlmAgent $agent): array
    {
        $mcpServers = $this->getMCPServersForAgent($agent);

        if (empty($mcpServers)) {
            return [];
        }

        $tools = [];

        foreach ($mcpServers as $serverName) {
            if (! $this->mcpManager->isServerEnabled($serverName)) {
                Log::info("Skipping disabled MCP server: {$serverName}");

                continue;
            }

            try {
                $serverTools = $this->mcpManager->discoverTools($serverName);

                foreach ($serverTools as $toolDef) {
                    $tools[] = new MCPToolWrapper(
                        $this->mcpManager,
                        $serverName,
                        $toolDef
                    );
                }

                Log::info('Discovered {count} tools from MCP server {server} for agent {agent}', [
                    'count' => count($serverTools),
                    'server' => $serverName,
                    'agent' => $agent->getName(),
                    'tools' => array_column($serverTools, 'name'),
                ]);

            } catch (\Exception $e) {
                Log::warning('Failed to discover tools from MCP server {server} for agent {agent}: {error}', [
                    'server' => $serverName,
                    'agent' => $agent->getName(),
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $tools;
    }

    /**
     * Get MCP servers that an agent should use based on its attributes
     */
    public function getMCPServersForAgent(BaseLlmAgent $agent): array
    {
        $reflection = new ReflectionClass($agent);
        $attributes = $reflection->getAttributes(UseMCPServers::class);

        if (empty($attributes)) {
            return [];
        }

        $useMCPServers = $attributes[0]->newInstance();

        return $useMCPServers->getServers();
    }

    /**
     * Check if an agent has MCP servers configured
     */
    public function agentHasMCPServers(BaseLlmAgent $agent): bool
    {
        return ! empty($this->getMCPServersForAgent($agent));
    }

    /**
     * Get information about all MCP tools available to an agent
     */
    public function getAgentMCPToolsInfo(BaseLlmAgent $agent): array
    {
        $mcpServers = $this->getMCPServersForAgent($agent);
        $info = [
            'has_mcp_tools' => false,
            'servers' => [],
            'total_tools' => 0,
            'tools_by_server' => [],
        ];

        if (empty($mcpServers)) {
            return $info;
        }

        $info['has_mcp_tools'] = true;
        $info['servers'] = $mcpServers;

        foreach ($mcpServers as $serverName) {
            if (! $this->mcpManager->isServerEnabled($serverName)) {
                continue;
            }

            try {
                $tools = $this->mcpManager->discoverTools($serverName);
                $info['tools_by_server'][$serverName] = [
                    'count' => count($tools),
                    'tools' => array_column($tools, 'name'),
                    'enabled' => true,
                ];
                $info['total_tools'] += count($tools);
            } catch (\Exception $e) {
                $info['tools_by_server'][$serverName] = [
                    'count' => 0,
                    'tools' => [],
                    'enabled' => false,
                    'error' => $e->getMessage(),
                ];
            }
        }

        return $info;
    }

    /**
     * Validate that all required MCP servers for an agent are available
     */
    public function validateAgentMCPServers(BaseLlmAgent $agent): array
    {
        $mcpServers = $this->getMCPServersForAgent($agent);
        $results = [];

        foreach ($mcpServers as $serverName) {
            $results[$serverName] = $this->mcpManager->testConnection($serverName);
        }

        return $results;
    }
}
