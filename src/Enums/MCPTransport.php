<?php

namespace Vizra\VizraADK\Enums;

enum MCPTransport: string
{
    /**
     * STDIO transport - Communication via subprocess stdin/stdout
     * Used for local MCP servers running as child processes
     */
    case STDIO = 'stdio';

    /**
     * HTTP transport - Communication via HTTP/SSE
     * Used for remote MCP servers accessible over HTTP
     */
    case HTTP = 'http';

    /**
     * Check if transport is STDIO
     */
    public function isStdio(): bool
    {
        return $this === self::STDIO;
    }

    /**
     * Check if transport is HTTP
     */
    public function isHttp(): bool
    {
        return $this === self::HTTP;
    }
}
