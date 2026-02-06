<?php

namespace Vizra\VizraADK\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Vizra\VizraADK\Models\TraceSpan;
use Vizra\VizraADK\Services\Tracer;

/**
 * Agent Trace Command
 *
 * Displays a hierarchical visualization of agent execution traces
 * for debugging and performance analysis.
 */
class AgentTraceCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'vizra:trace
                            {session_id : The session ID to show traces for}
                            {--trace-id= : Show a specific trace ID instead of all traces for the session}
                            {--show-input : Show input data for each span}
                            {--show-output : Show output data for each span}
                            {--show-metadata : Show metadata for each span}
                            {--errors-only : Show only spans with errors}
                            {--format=tree : Output format (tree, table, json)}';

    /**
     * The console command description.
     */
    protected $description = 'Display agent execution traces for debugging and analysis';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $sessionId = $this->argument('session_id');
        $traceId = $this->option('trace-id');
        $format = $this->option('format');

        /** @var Tracer $tracer */
        $tracer = app(Tracer::class);

        if (! $tracer->isEnabled()) {
            $this->error('Tracing is disabled. Enable it in config/vizra-adk.php');

            return self::FAILURE;
        }

        // Get spans based on criteria
        if ($traceId) {
            $spans = collect($tracer->getSpansForTrace($traceId));
            $this->info("Trace for trace ID: {$traceId}");
        } else {
            $spans = collect($tracer->getSpansForSession($sessionId));
            $this->info("Traces for session: {$sessionId}");
        }

        if ($spans->isEmpty()) {
            $this->warn('No traces found for the given criteria.');

            return self::SUCCESS;
        }

        // Filter by errors if requested
        if ($this->option('errors-only')) {
            $spans = $spans->filter(fn ($span) => $span->status === 'error');
            if ($spans->isEmpty()) {
                $this->info('No error spans found.');

                return self::SUCCESS;
            }
        }

        // Convert to TraceSpan models for better handling
        $traceSpans = $spans->map(function ($span) {
            $model = new TraceSpan;
            foreach ((array) $span as $key => $value) {
                $model->setAttribute($key, $value);
            }

            return $model;
        });

        // Display based on format
        match ($format) {
            'tree' => $this->displayTreeFormat($traceSpans),
            'table' => $this->displayTableFormat($traceSpans),
            'json' => $this->displayJsonFormat($traceSpans),
            default => $this->displayTreeFormat($traceSpans)
        };

        return self::SUCCESS;
    }

    /**
     * Display traces in hierarchical tree format.
     */
    protected function displayTreeFormat(Collection $spans): void
    {
        // Group spans by trace_id
        $traceGroups = $spans->groupBy('trace_id');

        foreach ($traceGroups as $traceId => $traceSpans) {
            $this->line('');
            $this->info("🔍 Trace ID: {$traceId}");
            $this->line(str_repeat('═', 80));

            // Build hierarchy
            $hierarchy = $this->buildHierarchy($traceSpans);

            // Display tree
            foreach ($hierarchy as $rootSpan) {
                $this->displaySpanTree($rootSpan, 0);
            }

            // Show trace summary
            $this->displayTraceSummary($traceSpans);
        }
    }

    /**
     * Display traces in table format.
     */
    protected function displayTableFormat(Collection $spans): void
    {
        $headers = ['Type', 'Name', 'Status', 'Duration', 'Start Time'];

        if ($this->option('show-input')) {
            $headers[] = 'Input';
        }
        if ($this->option('show-output')) {
            $headers[] = 'Output';
        }

        $rows = $spans->map(function (TraceSpan $span) {
            $row = [
                $span->getTypeIcon().' '.$span->type,
                $span->name,
                $span->getStatusIcon().' '.$span->status,
                $span->getFormattedDuration(),
                date('H:i:s.v', (int) $span->start_time),
            ];

            if ($this->option('show-input')) {
                $row[] = $this->formatData($span->input);
            }
            if ($this->option('show-output')) {
                $row[] = $this->formatData($span->output);
            }

            return $row;
        })->toArray();

        $this->table($headers, $rows);
    }

    /**
     * Display traces in JSON format.
     */
    protected function displayJsonFormat(Collection $spans): void
    {
        $data = $spans->map(function (TraceSpan $span) {
            return [
                'trace_id' => $span->trace_id,
                'span_id' => $span->span_id,
                'parent_span_id' => $span->parent_span_id,
                'type' => $span->type,
                'name' => $span->name,
                'status' => $span->status,
                'duration_ms' => $span->duration_ms,
                'start_time' => $span->start_time,
                'end_time' => $span->end_time,
                'input' => $span->input,
                'output' => $span->output,
                'metadata' => $span->metadata,
                'error_message' => $span->error_message,
            ];
        });

        $this->line(json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    /**
     * Build hierarchical structure from flat spans.
     */
    protected function buildHierarchy(Collection $spans): array
    {
        $spansByParent = $spans->groupBy('parent_span_id');
        $rootSpans = $spansByParent->get(null, collect());

        $hierarchy = [];
        foreach ($rootSpans as $rootSpan) {
            $hierarchy[] = $this->buildSpanTree($rootSpan, $spansByParent);
        }

        return $hierarchy;
    }

    /**
     * Recursively build span tree.
     */
    protected function buildSpanTree($span, Collection $spansByParent): object
    {
        // Create a new object to avoid modifying Eloquent model properties
        $treeNode = (object) [
            'id' => $span->id,
            'span_id' => $span->span_id,
            'parent_span_id' => $span->parent_span_id,
            'trace_id' => $span->trace_id,
            'session_id' => $span->session_id,
            'agent_name' => $span->agent_name,
            'type' => $span->type,
            'name' => $span->name,
            'status' => $span->status,
            'error_message' => $span->error_message,
            'start_time' => $span->start_time,
            'end_time' => $span->end_time,
            'duration_ms' => $span->duration_ms,
            'input' => $span->input,
            'output' => $span->output,
            'metadata' => $span->metadata,
            'children' => [],
        ];

        $children = $spansByParent->get($span->span_id, collect());

        foreach ($children as $child) {
            $treeNode->children[] = $this->buildSpanTree($child, $spansByParent);
        }

        return $treeNode;
    }

    /**
     * Display a span and its children in tree format.
     */
    protected function displaySpanTree($span, int $depth = 0): void
    {
        $indent = str_repeat('    ', $depth);
        $connector = $depth > 0 ? '└── ' : '';

        // Main span line
        $statusIcon = $this->getStatusIcon($span->status);
        $typeIcon = $this->getTypeIcon($span->type);
        $duration = $this->getFormattedDuration($span->duration_ms);

        $line = "{$indent}{$connector}{$typeIcon} {$span->type}: {$span->name} - {$duration} {$statusIcon}";

        // Color based on status
        match ($span->status) {
            'success' => $this->line("<fg=green>{$line}</fg=green>"),
            'error' => $this->line("<fg=red>{$line}</fg=red>"),
            'running' => $this->line("<fg=yellow>{$line}</fg=yellow>"),
            default => $this->line($line)
        };

        // Show error message if present
        if ($span->status === 'error' && $span->error_message) {
            $this->line("{$indent}    <fg=red>Error: {$span->error_message}</fg=red>");
        }

        // Show input/output if requested
        if ($this->option('show-input') && $span->input) {
            $this->line("{$indent}    <fg=blue>Input:</fg=blue> ".$this->formatData($span->input));
        }

        if ($this->option('show-output') && $span->output) {
            $this->line("{$indent}    <fg=cyan>Output:</fg=cyan> ".$this->formatData($span->output));
        }

        if ($this->option('show-metadata') && $span->metadata) {
            $this->line("{$indent}    <fg=gray>Metadata:</fg=gray> ".$this->formatData($span->metadata));
        }

        // Display children
        foreach ($span->children ?? [] as $child) {
            $this->displaySpanTree($child, $depth + 1);
        }
    }

    /**
     * Display trace summary statistics.
     */
    protected function displayTraceSummary(Collection $spans): void
    {
        $totalSpans = $spans->count();
        $successSpans = $spans->where('status', 'success')->count();
        $errorSpans = $spans->where('status', 'error')->count();
        $runningSpans = $spans->where('status', 'running')->count();

        $totalDuration = $spans->where('status', 'success')->sum('duration_ms');
        $avgDuration = $successSpans > 0 ? round($totalDuration / $successSpans, 2) : 0;

        $this->line('');
        $this->line('<fg=gray>📊 Trace Summary:</fg=gray>');
        $this->line("<fg=gray>   Total Spans: {$totalSpans}</fg=gray>");
        $this->line("<fg=green>   ✅ Success: {$successSpans}</fg=green>");

        if ($errorSpans > 0) {
            $this->line("<fg=red>   ❌ Errors: {$errorSpans}</fg=red>");
        }

        if ($runningSpans > 0) {
            $this->line("<fg=yellow>   ⏳ Running: {$runningSpans}</fg=yellow>");
        }

        $this->line("<fg=gray>   🕐 Total Duration: {$totalDuration}ms</fg=gray>");
        $this->line("<fg=gray>   📈 Avg Duration: {$avgDuration}ms</fg=gray>");
    }

    /**
     * Format data for display (truncate if too long).
     */
    protected function formatData($data, int $maxLength = 100): string
    {
        if (is_null($data)) {
            return 'null';
        }

        if (is_array($data)) {
            $json = json_encode($data, JSON_UNESCAPED_SLASHES);
        } else {
            $json = (string) $data;
        }

        if (strlen($json) > $maxLength) {
            return substr($json, 0, $maxLength).'...';
        }

        return $json;
    }

    /**
     * Get status icon for a span.
     */
    protected function getStatusIcon(string $status): string
    {
        return match ($status) {
            'success' => '✅',
            'error' => '❌',
            'running' => '⏳',
            default => '❓'
        };
    }

    /**
     * Get type icon for a span.
     */
    protected function getTypeIcon(string $type): string
    {
        return match ($type) {
            'agent_run' => '🤖',
            'llm_call' => '🧠',
            'tool_call' => '🔧',
            'sub_agent_delegation' => '👥',
            'chain_step' => '🔗',
            default => '📄'
        };
    }

    /**
     * Get formatted duration for a span.
     */
    protected function getFormattedDuration(?int $durationMs): string
    {
        if (is_null($durationMs)) {
            return 'N/A';
        }

        if ($durationMs < 1000) {
            return "{$durationMs}ms";
        }

        $seconds = round($durationMs / 1000, 2);

        return "{$seconds}s";
    }
}
