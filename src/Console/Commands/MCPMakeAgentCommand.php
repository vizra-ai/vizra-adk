<?php

namespace Vizra\VizraADK\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;
use Vizra\VizraADK\Services\MCP\MCPClientManager;

class MCPMakeAgentCommand extends Command
{
    protected $signature = 'vizra:make:agent-with-mcp {name} {--servers= : Comma-separated list of MCP servers}';

    protected $description = 'Create a new agent with MCP server integration';

    public function handle(Filesystem $files, MCPClientManager $mcpManager): int
    {
        $name = $this->argument('name');
        $servers = $this->option('servers');

        // Parse servers or prompt for selection
        if ($servers) {
            $selectedServers = array_map('trim', explode(',', $servers));
        } else {
            $availableServers = $mcpManager->getEnabledServers();

            if (empty($availableServers)) {
                $this->error('No enabled MCP servers found. Configure servers in config/vizra-adk.php first.');

                return 1;
            }

            $this->line('Available MCP servers:');
            foreach ($availableServers as $server) {
                $this->line("  • {$server}");
            }

            $serversInput = $this->ask('Which MCP servers should this agent use? (comma-separated)', implode(',', $availableServers));
            $selectedServers = array_map('trim', explode(',', $serversInput));
        }

        // Validate selected servers
        $enabledServers = $mcpManager->getEnabledServers();
        $invalidServers = array_diff($selectedServers, $enabledServers);

        if (! empty($invalidServers)) {
            $this->error('Invalid or disabled servers: '.implode(', ', $invalidServers));
            $this->line('Available servers: '.implode(', ', $enabledServers));

            return 1;
        }

        // Generate agent class
        $className = Str::studly($name);
        $agentName = Str::snake($name);
        $namespace = config('vizra-adk.namespaces.agents', 'App\\Agents');

        $directory = $this->namespaceToPath($namespace);
        $path = "{$directory}/{$className}.php";

        // Create directory if it doesn't exist
        if (! $files->isDirectory($directory)) {
            $files->makeDirectory($directory, 0755, true);
        }

        // Check if file already exists
        if ($files->exists($path)) {
            $this->error("Agent {$className} already exists!");

            return 1;
        }

        // Generate the agent class content
        $stub = $this->getStub($className, $agentName, $namespace, $selectedServers);

        $files->put($path, $stub);

        $this->info("Agent {$className} created successfully!");
        $this->line("Location: {$path}");
        $this->line("Agent name: {$agentName}");
        $this->line('MCP servers: '.implode(', ', $selectedServers));
        $this->line('');
        $this->line('Next steps:');
        $this->line('1. Customize the agent instructions in the generated class');
        $this->line('2. Register the agent in a service provider');
        $this->line("3. Test with: php artisan vizra:chat {$agentName}");

        return 0;
    }

    private function getStub(string $className, string $agentName, string $namespace, array $servers): string
    {
        $serversArray = "'".implode("', '", $servers)."'";
        $description = 'AI agent with access to '.implode(', ', $servers).' via MCP';

        return <<<PHP
<?php

namespace {$namespace};

use Vizra\\VizraADK\\Agents\\BaseLlmAgent;

class {$className} extends BaseLlmAgent
{
    protected string \$name = '{$agentName}';
    
    protected string \$description = '{$description}';
    
    protected string \$instructions = 'You are a helpful AI assistant with access to additional tools and resources via MCP servers.
    
Available MCP servers: {$this->formatServersForInstructions($servers)}

Use the available tools to help users with their requests. Always be helpful and provide accurate information.';

    protected string \$model = 'gemini-1.5-flash'; // Change to your preferred model
    
    protected array \$mcpServers = [{$serversArray}];
}
PHP;
    }

    private function formatServersForInstructions(array $servers): string
    {
        return implode(', ', array_map(function ($server) {
            return match ($server) {
                'filesystem' => 'file system access',
                'github' => 'GitHub integration',
                'postgres' => 'database queries',
                'brave_search' => 'web search',
                'slack' => 'Slack integration',
                default => $server
            };
        }, $servers));
    }

    private function namespaceToPath(string $namespace): string
    {
        $relativePath = str_replace('\\', '/', $namespace);

        if (str_starts_with($namespace, 'App\\')) {
            return app_path(str_replace('App/', '', $relativePath));
        }

        return base_path($relativePath);
    }
}
