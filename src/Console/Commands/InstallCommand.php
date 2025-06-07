<?php

namespace AaronLumsden\LaravelAiADK\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class InstallCommand extends Command
{
    protected $signature = 'agent:install';
    protected $description = 'Install Laravel Ai ADK assets (config and migrations).';

    public function handle(): void
    {
        $this->info('Publishing Laravel Ai ADK configuration...');
        $this->call('vendor:publish', [
            '--provider' => "AaronLumsden\LaravelAiADK\Providers\AgentServiceProvider",
            '--tag' => "agent-adk-config"
        ]);

        $this->info('Publishing Laravel Ai ADK migrations...');
        $this->call('vendor:publish', [
            '--provider' => "AaronLumsden\LaravelAiADK\Providers\AgentServiceProvider",
            '--tag' => "agent-adk-migrations"
        ]);

        // Check if migrations were already published and ask to overwrite if necessary,
        // or simply inform the user. For simplicity, vendor:publish handles this.

        $this->info('Laravel Ai ADK installed successfully.');
        $this->comment('Please run "php artisan migrate" to create the necessary database tables.');
        $this->comment('Configure your LLM provider API keys in .env and in config/agent-adk.php if needed.');

        $this->showDashboardInfo();
    }

    protected function showDashboardInfo(): void
    {
        $prefix = config('agent-adk.routes.web.prefix', 'ai-adk');
        $url = url($prefix);

        $this->line('');
        $this->info('🤖 Web Dashboard Available:');
        $this->line("   <fg=green>{$url}</fg=green>");
        $this->line('');
        $this->comment('💡 Quick commands:');
        $this->line('   <fg=cyan>php artisan agent:dashboard</fg=cyan>          # Show dashboard URL');
        $this->line('   <fg=cyan>php artisan agent:dashboard --open</fg=cyan>   # Open dashboard in browser');
        $this->line('   <fg=cyan>php artisan agent:make:agent MyAgent</fg=cyan> # Create a new agent');
    }
}
