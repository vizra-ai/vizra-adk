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
        $this->showDiscordInvite();
        $this->askForGitHubStar();
    }

    protected function showDiscordInvite(): void
    {
        $this->line('');
        $this->info('💬 Join Our Community!');
        $this->comment('Connect with other developers, get help, and share your projects:');
        $this->line('   <fg=cyan>https://discord.gg/CRRzmvS5MK</fg=cyan>');
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

    protected function askForGitHubStar(): void
    {
        $this->line('');
        $this->line('');
        $this->info('⭐ Want to show your support?');
        $this->comment('Star us on GitHub to help the Vizra community grow!');
        
        if ($this->confirm('Would you like to star Vizra ADK on GitHub?', false)) {
            $url = 'https://github.com/vizra-ai/vizra-adk';
            
            $this->info('Opening GitHub in your browser...');
            
            // Detect the operating system and open the URL accordingly
            if (PHP_OS_FAMILY === 'Darwin') {
                // macOS
                exec("open '{$url}'");
            } elseif (PHP_OS_FAMILY === 'Windows') {
                // Windows
                exec("start {$url}");
            } else {
                // Linux and others
                exec("xdg-open '{$url}' 2>/dev/null || sensible-browser '{$url}' 2>/dev/null || x-www-browser '{$url}' 2>/dev/null");
            }
            
            $this->line('');
            $this->info('Thank you for your support! 🎉');
        } else {
            $this->line('');
            $this->comment('No problem! You can always star us later at:');
            $this->line("   <fg=cyan>https://github.com/vizra-ai/vizra-adk</fg=cyan>");
        }
    }
}
