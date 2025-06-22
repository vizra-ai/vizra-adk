<?php

namespace Vizra\VizraADK\Services\MCP;

use Illuminate\Support\Facades\Process;
use Symfony\Component\Process\Process as SymfonyProcess;
use Vizra\VizraADK\Exceptions\MCPException;

class MCPClient
{
    private ?SymfonyProcess $process = null;

    private array $messageId = ['counter' => 0];

    public function __construct(
        private string $command,
        private array $args = [],
        private int $timeout = 30
    ) {}

    /**
     * Start the MCP server process
     */
    public function connect(): void
    {
        if ($this->process && $this->process->isRunning()) {
            return;
        }

        $fullCommand = array_merge([$this->command], $this->args);
        $this->process = new SymfonyProcess($fullCommand);
        $this->process->setTimeout($this->timeout);
        $this->process->start();

        // Wait a moment for the process to initialize
        usleep(100000); // 100ms

        if (! $this->process->isRunning()) {
            throw new MCPException("Failed to start MCP server: {$this->command}");
        }

        // Initialize the connection with MCP protocol
        $this->initialize();
    }

    /**
     * Disconnect from the MCP server
     */
    public function disconnect(): void
    {
        if ($this->process && $this->process->isRunning()) {
            $this->process->stop();
        }
        $this->process = null;
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
            'arguments' => $arguments,
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
     * Send a request to the MCP server
     */
    private function sendRequest(string $method, array $params = []): array
    {
        $messageId = ++$this->messageId['counter'];

        $request = [
            'jsonrpc' => '2.0',
            'id' => $messageId,
            'method' => $method,
        ];

        if (! empty($params)) {
            $request['params'] = $params;
        }

        $requestJson = json_encode($request)."\n";

        $this->process->getInput()->write($requestJson);

        // Read response
        $response = $this->readResponse();

        if (isset($response['error'])) {
            throw new MCPException('MCP Error: '.json_encode($response['error']));
        }

        return $response['result'] ?? [];
    }

    /**
     * Initialize the MCP connection
     */
    private function initialize(): void
    {
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

        // Send initialized notification
        $notification = [
            'jsonrpc' => '2.0',
            'method' => 'notifications/initialized',
        ];

        $this->process->getInput()->write(json_encode($notification)."\n");
    }

    /**
     * Read a JSON-RPC response from the process
     */
    private function readResponse(): array
    {
        $output = '';
        $attempts = 0;
        $maxAttempts = 50; // 5 seconds max wait

        while ($attempts < $maxAttempts) {
            $output .= $this->process->getIncrementalOutput();

            if (str_contains($output, "\n")) {
                $lines = explode("\n", $output);
                foreach ($lines as $line) {
                    $line = trim($line);
                    if (empty($line)) {
                        continue;
                    }

                    $response = json_decode($line, true);
                    if ($response !== null) {
                        return $response;
                    }
                }
            }

            usleep(100000); // 100ms
            $attempts++;
        }

        throw new MCPException('Timeout waiting for MCP response');
    }

    /**
     * Ensure the connection is established
     */
    private function ensureConnected(): void
    {
        if (! $this->process || ! $this->process->isRunning()) {
            $this->connect();
        }
    }

    /**
     * Destructor to clean up the process
     */
    public function __destruct()
    {
        $this->disconnect();
    }
}
