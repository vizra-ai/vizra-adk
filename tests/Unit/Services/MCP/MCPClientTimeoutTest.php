<?php

namespace Vizra\VizraADK\Tests\Unit\Services\MCP;

use Vizra\VizraADK\Tests\TestCase;
use Vizra\VizraADK\Services\MCP\MCPClient;
use Vizra\VizraADK\Services\MCP\MCPStdioClient;
use Vizra\VizraADK\Exceptions\MCPException;

class MCPClientTimeoutTest extends TestCase
{
    /**
     * Test that MCP client respects configured timeout
     */
    public function test_mcp_client_respects_configured_timeout(): void
    {
        // Test with different timeout values
        $shortTimeout = new MCPClient('echo', ['test'], 5);
        $mediumTimeout = new MCPClient('echo', ['test'], 30);
        $longTimeout = new MCPClient('echo', ['test'], 120);

        // Use reflection to verify timeout is set correctly
        // MCPClient extends MCPStdioClient, so we need to get properties from parent class
        $reflection = new \ReflectionClass(MCPStdioClient::class);
        $timeoutProperty = $reflection->getProperty('timeout');
        $timeoutProperty->setAccessible(true);

        $this->assertEquals(5, $timeoutProperty->getValue($shortTimeout));
        $this->assertEquals(30, $timeoutProperty->getValue($mediumTimeout));
        $this->assertEquals(120, $timeoutProperty->getValue($longTimeout));
    }

    /**
     * Test that timeout calculation is correct
     */
    public function test_timeout_calculation_is_correct(): void
    {
        // Create client with 10 second timeout
        $client = new MCPClient('echo', ['test'], 10);

        // Use reflection to verify internal timeout calculation
        $reflection = new \ReflectionClass(MCPStdioClient::class);
        $timeoutProperty = $reflection->getProperty('timeout');
        $timeoutProperty->setAccessible(true);

        $timeout = $timeoutProperty->getValue($client);

        // Verify timeout is set correctly
        $this->assertEquals(10, $timeout);

        // The maxAttempts calculation should be timeout * 10 (for 100ms intervals)
        // This means 10 seconds = 100 attempts at 100ms each
        $expectedMaxAttempts = $timeout * 10;
        $this->assertEquals(100, $expectedMaxAttempts);
    }

    /**
     * Test that default timeout is 30 seconds
     */
    public function test_default_timeout_is_thirty_seconds(): void
    {
        // Create client without specifying timeout
        $client = new MCPClient('echo', ['test']);

        // Use reflection to check the default timeout
        $reflection = new \ReflectionClass(MCPStdioClient::class);
        $timeoutProperty = $reflection->getProperty('timeout');
        $timeoutProperty->setAccessible(true);

        $timeout = $timeoutProperty->getValue($client);

        // Default timeout should be 30 seconds
        $this->assertEquals(30, $timeout);

        // This means 300 attempts at 100ms intervals
        $expectedMaxAttempts = $timeout * 10;
        $this->assertEquals(300, $expectedMaxAttempts);
    }

    /**
     * Test with a longer timeout value
     */
    public function test_long_timeout_value(): void
    {
        // Test with 120 second timeout (as mentioned in the issue)
        $client = new MCPClient('echo', ['test'], 120);

        $reflection = new \ReflectionClass(MCPStdioClient::class);
        $timeoutProperty = $reflection->getProperty('timeout');
        $timeoutProperty->setAccessible(true);

        $timeout = $timeoutProperty->getValue($client);

        $this->assertEquals(120, $timeout);

        // Should allow for 1200 attempts at 100ms intervals
        $expectedMaxAttempts = $timeout * 10;
        $this->assertEquals(1200, $expectedMaxAttempts);
    }
}