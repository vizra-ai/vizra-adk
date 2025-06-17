<?php

namespace Vizra\VizraADK\Console\Commands;

use Illuminate\Console\Command;
use Vizra\VizraADK\Services\AgentDiscovery;
use Vizra\VizraADK\Services\AgentRegistry;

class AgentDiscoverCommand extends Command
{
    protected $signature = 'vizra:discover-agents {--clear-cache : Clear the discovery cache}';

    protected $description = 'Discover and list all available agents';

    public function handle(): void
    {
        /** @var AgentDiscovery $discovery */
        $discovery = app(AgentDiscovery::class);
        
        if ($this->option('clear-cache')) {
            $discovery->clearCache();
            $this->info('Discovery cache cleared.');
        }

        $this->info('Discovering agents...');
        
        $agents = $discovery->discover();
        
        if (empty($agents)) {
            $this->warn('No agents found in ' . config('vizra-adk.namespaces.agents', 'App\\Agents'));
            return;
        }

        $this->info('Found ' . count($agents) . ' agent(s):');
        $this->newLine();

        $headers = ['Agent Name', 'Class', 'Status'];
        $rows = [];

        /** @var AgentRegistry $registry */
        $registry = app(AgentRegistry::class);

        foreach ($agents as $className => $agentName) {
            $status = $registry->hasAgent($agentName) ? '<fg=green>Registered</>' : '<fg=yellow>Available</>';
            $rows[] = [$agentName, $className, $status];
        }

        $this->table($headers, $rows);
        
        $this->newLine();
        $this->info('All agents are automatically available for use!');
    }
}