<?php

namespace Vizra\VizraSdk\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Route;

class DashboardCommand extends Command
{
    protected $signature = 'agent:dashboard {--open : Open the dashboard URL in your default browser}';

    protected $description = 'Show the Laravel Ai ADK dashboard URL';

    public function handle(): void
    {
        $prefix = config('agent-adk.routes.web.prefix', 'ai-adk');
        $url = url($prefix);

        $this->info('🤖 Laravel Ai ADK Dashboard');
        $this->line('');
        $this->line("Dashboard URL: <fg=green>{$url}</fg=green>");
        $this->line('');

        if ($this->option('open')) {
            $this->info('Opening dashboard in your default browser...');
            $this->openUrl($url);
        } else {
            $this->line('Use <fg=yellow>--open</fg=yellow> flag to open the dashboard automatically.');
        }

        $this->line('');
        $this->comment('💡 Tip: You can also run this after the install command:');
        $this->line('   <fg=cyan>php artisan agent:install</fg=cyan>');
    }

    protected function openUrl(string $url): void
    {
        $os = PHP_OS_FAMILY;

        switch ($os) {
            case 'Darwin':  // macOS
                exec("open {$url}");
                break;
            case 'Windows':
                exec("start {$url}");
                break;
            case 'Linux':
                exec("xdg-open {$url}");
                break;
            default:
                $this->warn("Unable to open URL automatically on {$os}. Please visit the URL manually.");
        }
    }
}
