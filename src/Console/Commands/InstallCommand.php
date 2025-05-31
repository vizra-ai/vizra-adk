<?php

namespace AaronLumsden\LaravelAgentADK\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class InstallCommand extends Command
{
    protected $signature = 'agent:install';
    protected $description = 'Install Laravel Agent ADK assets (config and migrations).';

    public function handle(): void
    {
        $this->info('Publishing Laravel Agent ADK configuration...');
        $this->call('vendor:publish', [
            '--provider' => "AaronLumsden\LaravelAgentADK\Providers\AgentServiceProvider",
            '--tag' => "agent-adk-config"
        ]);

        $this->info('Publishing Laravel Agent ADK migrations...');
        $this->call('vendor:publish', [
            '--provider' => "AaronLumsden\LaravelAgentADK\Providers\AgentServiceProvider",
            '--tag' => "agent-adk-migrations"
        ]);

        // Check if migrations were already published and ask to overwrite if necessary,
        // or simply inform the user. For simplicity, vendor:publish handles this.

        $this->info('Laravel Agent ADK installed successfully.');
        $this->comment('Please run "php artisan migrate" to create the necessary database tables.');
        $this->comment('Configure your LLM provider API keys in .env and in config/agent-adk.php if needed.');
    }
}
