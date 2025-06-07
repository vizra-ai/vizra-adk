<?php

namespace AaronLumsden\LaravelAiADK\Console\Commands;

use AaronLumsden\LaravelAiADK\Services\VectorMemoryManager;
use AaronLumsden\LaravelAiADK\Models\VectorMemory;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class VectorMemoryStats extends Command
{
    protected $signature = 'vector:stats 
                           {agent? : The agent name to get stats for (optional)}
                           {--namespace=default : Memory namespace}
                           {--detailed : Show detailed statistics}
                           {--json : Output as JSON}';

    protected $description = 'Show vector memory statistics';

    public function handle(VectorMemoryManager $vectorMemory): int
    {
        $agentName = $this->argument('agent');
        $namespace = $this->option('namespace');
        $detailed = $this->option('detailed');
        $outputJson = $this->option('json');

        try {
            if ($agentName) {
                // Stats for specific agent
                $this->showAgentStats($vectorMemory, $agentName, $namespace, $detailed, $outputJson);
            } else {
                // Global stats
                $this->showGlobalStats($detailed, $outputJson);
            }

            return 0;

        } catch (\Exception $e) {
            $this->error("Failed to retrieve statistics: " . $e->getMessage());
            return 1;
        }
    }

    protected function showAgentStats(
        VectorMemoryManager $vectorMemory,
        string $agentName,
        string $namespace,
        bool $detailed,
        bool $outputJson
    ): void {
        $stats = $vectorMemory->getStatistics($agentName, $namespace);

        if ($outputJson) {
            $this->line(json_encode($stats, JSON_PRETTY_PRINT));
            return;
        }

        $this->info("📊 Vector Memory Statistics for Agent: {$agentName}");
        $this->info("Namespace: {$namespace}");
        $this->newLine();

        $this->table(
            ['Metric', 'Value'],
            [
                ['Total Memories', number_format($stats['total_memories'])],
                ['Total Tokens', number_format($stats['total_tokens'])],
            ]
        );

        if (!empty($stats['providers'])) {
            $this->newLine();
            $this->info("🤖 Embedding Providers:");
            $providerData = [];
            foreach ($stats['providers'] as $provider => $count) {
                $providerData[] = [$provider, number_format($count)];
            }
            $this->table(['Provider', 'Count'], $providerData);
        }

        if (!empty($stats['sources'])) {
            $this->newLine();
            $this->info("📁 Sources:");
            $sourceData = [];
            $topSources = array_slice($stats['sources'], 0, 10, true);
            foreach ($topSources as $source => $count) {
                $sourceData[] = [$source, number_format($count)];
            }
            $this->table(['Source', 'Count'], $sourceData);
            
            if (count($stats['sources']) > 10) {
                $this->info("... and " . (count($stats['sources']) - 10) . " more sources");
            }
        }

        if ($detailed) {
            $this->showDetailedAgentStats($agentName, $namespace);
        }
    }

    protected function showGlobalStats(bool $detailed, bool $outputJson): void
    {
        $globalStats = [
            'total_memories' => VectorMemory::count(),
            'total_agents' => VectorMemory::distinct('agent_name')->count(),
            'total_namespaces' => VectorMemory::distinct('namespace')->count(),
            'total_tokens' => VectorMemory::sum('token_count'),
        ];

        // Top agents by memory count
        $topAgents = VectorMemory::select('agent_name', DB::raw('count(*) as count'))
            ->groupBy('agent_name')
            ->orderByDesc('count')
            ->limit(10)
            ->get()
            ->pluck('count', 'agent_name')
            ->toArray();

        // Embedding providers
        $providers = VectorMemory::select('embedding_provider', DB::raw('count(*) as count'))
            ->groupBy('embedding_provider')
            ->orderByDesc('count')
            ->get()
            ->pluck('count', 'embedding_provider')
            ->toArray();

        $globalStats['top_agents'] = $topAgents;
        $globalStats['providers'] = $providers;

        if ($outputJson) {
            $this->line(json_encode($globalStats, JSON_PRETTY_PRINT));
            return;
        }

        $this->info("📊 Global Vector Memory Statistics");
        $this->newLine();

        $this->table(
            ['Metric', 'Value'],
            [
                ['Total Memories', number_format($globalStats['total_memories'])],
                ['Total Agents', number_format($globalStats['total_agents'])],
                ['Total Namespaces', number_format($globalStats['total_namespaces'])],
                ['Total Tokens', number_format($globalStats['total_tokens'])],
            ]
        );

        if (!empty($topAgents)) {
            $this->newLine();
            $this->info("🤖 Top Agents by Memory Count:");
            $agentData = [];
            foreach ($topAgents as $agent => $count) {
                $agentData[] = [$agent, number_format($count)];
            }
            $this->table(['Agent', 'Memories'], $agentData);
        }

        if (!empty($providers)) {
            $this->newLine();
            $this->info("🔧 Embedding Providers:");
            $providerData = [];
            foreach ($providers as $provider => $count) {
                $providerData[] = [$provider, number_format($count)];
            }
            $this->table(['Provider', 'Count'], $providerData);
        }

        if ($detailed) {
            $this->showDetailedGlobalStats();
        }
    }

    protected function showDetailedAgentStats(string $agentName, string $namespace): void
    {
        $this->newLine();
        $this->info("🔍 Detailed Statistics:");

        // Memory age distribution
        $ageStats = VectorMemory::forAgent($agentName)
            ->inNamespace($namespace)
            ->select(
                DB::raw('COUNT(*) as count'),
                DB::raw('DATE(created_at) as date')
            )
            ->groupBy(DB::raw('DATE(created_at)'))
            ->orderByDesc('date')
            ->limit(7)
            ->get();

        if ($ageStats->isNotEmpty()) {
            $this->info("📅 Recent Activity (Last 7 Days):");
            $ageData = [];
            foreach ($ageStats as $stat) {
                $ageData[] = [$stat->date, number_format($stat->count)];
            }
            $this->table(['Date', 'Memories Added'], $ageData);
        }

        // Average chunk sizes
        $avgStats = VectorMemory::forAgent($agentName)
            ->inNamespace($namespace)
            ->select(
                DB::raw('AVG(token_count) as avg_tokens'),
                DB::raw('AVG(CHAR_LENGTH(content)) as avg_characters'),
                DB::raw('MIN(CHAR_LENGTH(content)) as min_characters'),
                DB::raw('MAX(CHAR_LENGTH(content)) as max_characters')
            )
            ->first();

        if ($avgStats) {
            $this->newLine();
            $this->info("📏 Content Size Statistics:");
            $this->table(
                ['Metric', 'Value'],
                [
                    ['Avg Tokens', number_format($avgStats->avg_tokens, 0)],
                    ['Avg Characters', number_format($avgStats->avg_characters, 0)],
                    ['Min Characters', number_format($avgStats->min_characters, 0)],
                    ['Max Characters', number_format($avgStats->max_characters, 0)],
                ]
            );
        }
    }

    protected function showDetailedGlobalStats(): void
    {
        $this->newLine();
        $this->info("🔍 Detailed Global Statistics:");

        // Namespace distribution
        $namespaces = VectorMemory::select('namespace', DB::raw('count(*) as count'))
            ->groupBy('namespace')
            ->orderByDesc('count')
            ->limit(10)
            ->get();

        if ($namespaces->isNotEmpty()) {
            $this->info("📂 Namespaces:");
            $namespaceData = [];
            foreach ($namespaces as $ns) {
                $namespaceData[] = [$ns->namespace, number_format($ns->count)];
            }
            $this->table(['Namespace', 'Count'], $namespaceData);
        }

        // Model distribution
        $models = VectorMemory::select('embedding_model', DB::raw('count(*) as count'))
            ->groupBy('embedding_model')
            ->orderByDesc('count')
            ->get();

        if ($models->isNotEmpty()) {
            $this->newLine();
            $this->info("🎯 Embedding Models:");
            $modelData = [];
            foreach ($models as $model) {
                $modelData[] = [$model->embedding_model, number_format($model->count)];
            }
            $this->table(['Model', 'Count'], $modelData);
        }
    }
}