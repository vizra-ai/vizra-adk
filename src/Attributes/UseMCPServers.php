<?php

namespace Vizra\VizraADK\Attributes;

use Attribute;

/**
 * Attribute to specify which MCP servers an agent should use
 *
 * Usage:
 * #[UseMCPServers(['filesystem', 'github'])]
 * class MyAgent extends BaseLlmAgent { ... }
 */
#[Attribute(Attribute::TARGET_CLASS)]
class UseMCPServers
{
    /**
     * @param  array<string>  $servers  List of MCP server names to use
     */
    public function __construct(
        public readonly array $servers = []
    ) {}

    /**
     * Get the list of MCP servers
     */
    public function getServers(): array
    {
        return $this->servers;
    }

    /**
     * Check if a specific server is enabled for this agent
     */
    public function hasServer(string $serverName): bool
    {
        return in_array($serverName, $this->servers, true);
    }

    /**
     * Get servers as a comma-separated string
     */
    public function getServersString(): string
    {
        return implode(', ', $this->servers);
    }
}
