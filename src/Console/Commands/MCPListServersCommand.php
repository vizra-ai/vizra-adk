<?php

namespace Vizra\VizraADK\Console\Commands;

use Illuminate\Console\Command;
use Vizra\VizraADK\Services\MCP\MCPClientManager;

class MCPListServersCommand extends Command
{
    protected $signature = 'vizra:mcp:servers {--test : Test connectivity to servers}';

    protected $description = 'List configured MCP servers and their status';

    public function handle(MCPClientManager $mcpManager): int
    {
        $servers = config('vizra-adk.mcp_servers', []);

        if (empty($servers)) {
            $this->info('No MCP servers configured.');
            $this->line('');
            $this->line('To configure MCP servers, add them to config/vizra-adk.php:');
            $this->line('');
            $this->line("'mcp_servers' => [");
            $this->line("    'filesystem' => [");
            $this->line("        'command' => 'npx',");
            $this->line("        'args' => ['@modelcontextprotocol/server-filesystem', '/path/to/directory'],");
            $this->line("        'enabled' => true,");
            $this->line('    ],');
            $this->line('],');

            return 0;
        }

        $this->line('Configured MCP Servers:');
        $this->line('');

        $headers = ['Server', 'Command', 'Status'];
        $rows = [];

        foreach ($servers as $name => $config) {
            $enabled = $config['enabled'] ?? true;
            $command = $config['command'] ?? 'N/A';
            $status = $enabled ? '<fg=green>Enabled</fg=green>' : '<fg=red>Disabled</fg=red>';

            $rows[] = [$name, $command, $status];
        }

        $this->table($headers, $rows);

        if ($this->option('test')) {
            $this->line('');
            $this->line('Testing server connectivity...');
            $this->line('');

            $enabledServers = $mcpManager->getEnabledServers();

            if (empty($enabledServers)) {
                $this->warn('No enabled servers to test.');

                return 0;
            }

            $results = $mcpManager->testAllConnections();

            $testHeaders = ['Server', 'Status', 'Tools', 'Details'];
            $testRows = [];

            foreach ($results as $serverName => $result) {
                $status = $result['success']
                    ? '<fg=green>✓ Connected</fg=green>'
                    : '<fg=red>✗ Failed</fg=red>';

                $toolsCount = $result['tools_count'] ?? 0;
                $details = $result['success']
                    ? "Found {$toolsCount} tools"
                    : ($result['error'] ?? 'Unknown error');

                $testRows[] = [$serverName, $status, $toolsCount, $details];
            }

            $this->table($testHeaders, $testRows);

            // Show discovered tools for successful connections
            foreach ($results as $serverName => $result) {
                if ($result['success'] && ! empty($result['tools'])) {
                    $this->line('');
                    $this->line("<fg=cyan>Tools from {$serverName}:</fg=cyan>");
                    foreach ($result['tools'] as $tool) {
                        $this->line("  • {$tool}");
                    }
                }
            }
        }

        return 0;
    }
}
