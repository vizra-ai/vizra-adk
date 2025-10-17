<?php

namespace Vizra\VizraADK\Tools\MCP;

use Illuminate\Support\Facades\Log;
use Vizra\VizraADK\Contracts\ToolInterface;
use Vizra\VizraADK\Exceptions\MCPException;
use Vizra\VizraADK\Exceptions\ToolExecutionException;
use Vizra\VizraADK\Memory\AgentMemory;
use Vizra\VizraADK\Services\MCP\MCPClientManager;
use Vizra\VizraADK\System\AgentContext;

class MCPToolWrapper implements ToolInterface
{
    public function __construct(
        private MCPClientManager $mcpManager,
        private string $serverName,
        private array $toolDefinition
    ) {}

    /**
     * Get the tool's definition for the LLM
     */
    public function definition(): array
    {
        // Convert MCP tool definition to Vizra format
        return [
            'name' => $this->toolDefinition['name'],
            'description' => $this->toolDefinition['description'] ?? 'MCP tool',
            'parameters' => $this->convertInputSchema($this->toolDefinition['inputSchema'] ?? []),
        ];
    }

    /**
     * Execute the MCP tool
     */
    public function execute(array $arguments, AgentContext $context, AgentMemory $memory): string
    {
        // Apply tenant-specific MCP config overrides from context (for multi-tenant support)
        $overrides = $context->getState('mcp_config_overrides', []);
        if (! empty($overrides)) {
            $this->mcpManager->setContextOverrides($overrides);
        }

        try {
            Log::info('Executing MCP tool {tool} on server {server}', [
                'tool' => $this->toolDefinition['name'],
                'server' => $this->serverName,
                'arguments' => $arguments,
                'session_id' => $context->getSessionId(),
                'has_overrides' => ! empty($overrides),
            ]);

            $result = $this->mcpManager->callTool(
                $this->serverName,
                $this->toolDefinition['name'],
                $arguments
            );

            Log::info('MCP tool execution completed', [
                'tool' => $this->toolDefinition['name'],
                'server' => $this->serverName,
                'session_id' => $context->getSessionId(),
            ]);

            return $result;

        } catch (MCPException $e) {
            $errorMessage = "MCP tool '{$this->toolDefinition['name']}' failed: {$e->getMessage()}";

            Log::error('MCP tool execution failed', [
                'tool' => $this->toolDefinition['name'],
                'server' => $this->serverName,
                'error' => $e->getMessage(),
                'session_id' => $context->getSessionId(),
            ]);

            throw new ToolExecutionException($errorMessage, 0, $e);
        }
    }

    /**
     * Convert MCP input schema to Vizra tool parameter format
     */
    private function convertInputSchema(array $inputSchema): array
    {
        // MCP uses JSON Schema format, which is compatible with Vizra's parameter format
        // We may need some minor conversions

        if (empty($inputSchema)) {
            return [
                'type' => 'object',
                'properties' => [],
                'required' => [],
            ];
        }

        // If it's already a proper JSON Schema, return as-is
        if (isset($inputSchema['type']) || isset($inputSchema['properties'])) {
            return $inputSchema;
        }

        // Handle other MCP schema formats if needed
        return [
            'type' => 'object',
            'properties' => $inputSchema,
            'required' => [],
        ];
    }

    /**
     * Get the server name this tool belongs to
     */
    public function getServerName(): string
    {
        return $this->serverName;
    }

    /**
     * Get the original MCP tool definition
     */
    public function getMCPDefinition(): array
    {
        return $this->toolDefinition;
    }

    /**
     * Check if this tool is available (server is running)
     */
    public function isAvailable(): bool
    {
        try {
            return $this->mcpManager->isServerEnabled($this->serverName);
        } catch (MCPException $e) {
            return false;
        }
    }

    /**
     * Get a human-readable description of this tool's origin
     */
    public function getDescription(): string
    {
        $description = $this->toolDefinition['description'] ?? 'No description available';

        return "MCP Tool from '{$this->serverName}': {$description}";
    }
}
