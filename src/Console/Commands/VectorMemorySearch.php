<?php

namespace Vizra\VizraADK\Console\Commands;

use Illuminate\Console\Command;
use Vizra\VizraADK\Services\VectorMemoryManager;

class VectorMemorySearch extends Command
{
    protected $signature = 'vector:search
                           {agent : The agent name to search memory for}
                           {query : Search query}
                           {--namespace=default : Memory namespace}
                           {--limit=5 : Maximum number of results}
                           {--threshold=0.7 : Similarity threshold (0.0-1.0)}
                           {--rag : Generate RAG context instead of raw results}
                           {--json : Output results as JSON}';

    protected $description = 'Search vector memory for an agent';

    public function handle(VectorMemoryManager $vectorMemory): int
    {
        $agentName = $this->argument('agent');
        $query = $this->argument('query');
        $namespace = $this->option('namespace');
        $limit = (int) $this->option('limit');
        $threshold = (float) $this->option('threshold');
        $generateRag = $this->option('rag');
        $outputJson = $this->option('json');

        $this->info('Searching vector memory...');
        $this->info("Agent: {$agentName}");
        $this->info("Query: {$query}");
        $this->info("Namespace: {$namespace}");
        $this->info("Limit: {$limit}");
        $this->info("Threshold: {$threshold}");
        $this->newLine();

        try {
            if ($generateRag) {
                // Generate RAG context
                $ragContext = $vectorMemory->generateRagContext(
                    agentClass: $agentName,
                    queryOrArray: $query,
                    options: [
                        'limit' => $limit,
                        'namespace' => $namespace,
                        'threshold' => $threshold,
                    ],
                );

                if ($outputJson) {
                    $this->line(json_encode($ragContext, JSON_PRETTY_PRINT));
                } else {
                    $this->info("🔍 Found {$ragContext['total_results']} relevant results");
                    $this->newLine();

                    if (! empty($ragContext['context'])) {
                        $this->info('📄 Generated RAG Context:');
                        $this->line('=' * 80);
                        $this->line($ragContext['context']);
                        $this->line('=' * 80);
                        $this->newLine();
                    }

                    if (! empty($ragContext['sources'])) {
                        $this->info('📚 Sources:');
                        $sourceData = [];
                        foreach ($ragContext['sources'] as $index => $source) {
                            $sourceData[] = [
                                $index + 1,
                                $source['source'] ?? 'N/A',
                                $source['source_id'] ?? 'N/A',
                                isset($source['similarity']) ? number_format($source['similarity'], 3) : 'N/A',
                                $source['created_at'] ?? 'N/A',
                            ];
                        }
                        $this->table(
                            ['#', 'Source', 'Source ID', 'Similarity', 'Created'],
                            $sourceData
                        );
                    }
                }
            } else {
                // Raw search results
                $results = $vectorMemory->search(
                    agentClass: $agentName,
                    queryOrArray: [
                        'query' => $query,
                        'limit' => $limit,
                        'namespace' => $namespace,
                        'threshold' => $threshold,
                    ],
                );

                if ($outputJson) {
                    $this->line(json_encode($results->toArray(), JSON_PRETTY_PRINT));
                } else {
                    $this->info("🔍 Found {$results->count()} relevant results");
                    $this->newLine();

                    if ($results->isEmpty()) {
                        $this->warn("No results found above the similarity threshold of {$threshold}");
                    } else {
                        foreach ($results as $index => $result) {
                            $similarity = isset($result->similarity) ? number_format($result->similarity, 3) : 'N/A';
                            $this->info('Result #'.($index + 1)." (Similarity: {$similarity})");
                            $this->line('Source: '.($result->source ?? 'N/A'));
                            $this->line('Created: '.($result->created_at ?? 'N/A'));
                            $this->line('Content: '.substr($result->content, 0, 200).'...');

                            if (! empty($result->metadata)) {
                                $metadata = is_array($result->metadata) ? $result->metadata : json_decode($result->metadata, true);
                                $this->line('Metadata: '.json_encode($metadata));
                            }

                            $this->newLine();
                        }
                    }
                }
            }

            return 0;

        } catch (\Exception $e) {
            $this->error('Search failed: '.$e->getMessage());

            return 1;
        }
    }
}
