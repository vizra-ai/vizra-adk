<?php

declare(strict_types=1);

namespace Vizra\VizraADK\Console\Commands;

use Illuminate\Console\Command;
use Vizra\VizraADK\Services\Tracer;

class AgentTraceCleanupCommand extends Command
{
    protected $signature = 'vizra:trace:cleanup
                            {--days= : Number of days to keep traces (defaults to config value)}
                            {--dry-run : Show what would be deleted without actually deleting}
                            {--force : Skip confirmation prompt}';

    protected $description = 'Clean up old agent trace data';

    public function __construct(
        private readonly Tracer $tracer
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        if (! config('vizra-adk.tracing.enabled', false)) {
            $this->error('Agent tracing is not enabled in configuration.');

            return self::FAILURE;
        }

        $days = $this->option('days') !== null 
            ? (int) $this->option('days') 
            : config('vizra-adk.tracing.cleanup_days', 30);
        $isDryRun = $this->option('dry-run');
        $force = $this->option('force');

        $this->info("Cleaning up agent traces older than {$days} days...");

        if ($isDryRun) {
            $this->warn('Running in dry-run mode - no data will be deleted');
        }

        // Get count of traces to be deleted
        $count = $this->tracer->getOldTracesCount($days);

        if ($count === 0) {
            $this->info('No old traces found to clean up.');

            return self::SUCCESS;
        }

        $this->line("Found {$count} traces older than {$days} days.");

        if ($isDryRun) {
            $this->info("Would delete {$count} traces (dry run).");

            return self::SUCCESS;
        }

        if (! $force && ! $this->confirm("Are you sure you want to delete {$count} traces?")) {
            $this->info('Cleanup cancelled.');

            return self::SUCCESS;
        }

        $this->info('Deleting old traces...');

        $bar = $this->output->createProgressBar($count);
        $bar->start();

        $deleted = $this->tracer->cleanupOldTraces($days, function ($batchDeleted) use ($bar) {
            $bar->advance($batchDeleted);
        });

        $bar->finish();
        $this->newLine();

        $this->info("Successfully deleted {$deleted} traces.");

        return self::SUCCESS;
    }
}
