<?php

namespace Vizra\VizraADK\Contracts;

interface MCPClientInterface
{
    /**
     * Connect to the MCP server
     */
    public function connect(): void;

    /**
     * Disconnect from the MCP server
     */
    public function disconnect(): void;

    /**
     * List available tools from the MCP server
     *
     * @return array<int, array{name: string, description?: string, inputSchema?: array}>
     */
    public function listTools(): array;

    /**
     * Call a tool on the MCP server
     *
     * @param  array<string, mixed>  $arguments
     */
    public function callTool(string $toolName, array $arguments = []): string;

    /**
     * List available resources from the MCP server
     *
     * @return array<int, array{uri: string, name?: string, description?: string, mimeType?: string}>
     */
    public function listResources(): array;

    /**
     * Read a resource from the MCP server
     */
    public function readResource(string $uri): string;

    /**
     * List available prompts from the MCP server
     *
     * @return array<int, array{name: string, description?: string, arguments?: array}>
     */
    public function listPrompts(): array;

    /**
     * Get a prompt from the MCP server
     *
     * @param  array<string, mixed>  $arguments
     * @return array<int, array{role: string, content: string}>
     */
    public function getPrompt(string $name, array $arguments = []): array;
}
