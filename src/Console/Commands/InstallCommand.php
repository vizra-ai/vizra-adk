<?php

namespace Vizra\VizraADK\Console\Commands;

use Illuminate\Console\Command;

class InstallCommand extends Command
{
    protected $signature = 'vizra:install';

    protected $description = 'Install Vizra SDK assets (config and migrations).';

    public function handle(): void
    {
        $this->info('Publishing Vizra SDK configuration...');
        $this->call('vendor:publish', [
            '--provider' => "Vizra\VizraADK\Providers\AgentServiceProvider",
            '--tag' => 'vizra-adk-config',
        ]);

        $this->info('Publishing Vizra SDK migrations...');
        $this->call('vendor:publish', [
            '--provider' => "Vizra\VizraADK\Providers\AgentServiceProvider",
            '--tag' => 'vizra-adk-migrations',
        ]);

        // Check if migrations were already published and ask to overwrite if necessary,
        // or simply inform the user. For simplicity, vendor:publish handles this.

        $this->info('Vizra SDK installed successfully.');
        $this->comment('Please run "php artisan migrate" to create the necessary database tables.');
        $this->comment('Configure your LLM provider API keys in .env and in config/vizra-adk.php if needed.');

        $this->showDashboardInfo();
    }

    protected function showDashboardInfo(): void
    {
        $prefix = config('vizra-adk.routes.web.prefix', 'ai-adk');
        $url = url($prefix);

        $this->line('');
        $this->info('🤖 Web Dashboard Available:');
        $this->line("   <fg=green>{$url}</fg=green>");
        $this->line('');
        $this->comment('💡 Quick commands:');
        $this->line('   <fg=cyan>php artisan vizra:dashboard</fg=cyan>          # Show dashboard URL');
        $this->line('   <fg=cyan>php artisan vizra:dashboard --open</fg=cyan>   # Open dashboard in browser');
        $this->line('   <fg=cyan>php artisan vizra:make:agent MyAgent</fg=cyan> # Create a new agent');
    }
}
