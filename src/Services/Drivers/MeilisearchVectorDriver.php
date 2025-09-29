<?php

namespace Vizra\VizraADK\Services\Drivers;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Vizra\VizraADK\Models\VectorMemory;
use Vizra\VizraADK\Traits\HasLogging;

class MeilisearchVectorDriver
{
    use HasLogging;
    protected string $host;

    protected ?string $apiKey;

    protected string $indexPrefix;

    protected string $embedder;

    protected float $semanticRatio;

    public function __construct()
    {
        $this->host = config('vizra-adk.vector_memory.drivers.meilisearch.host', 'http://localhost:7700');
        $this->apiKey = config('vizra-adk.vector_memory.drivers.meilisearch.api_key');
        $this->indexPrefix = config('vizra-adk.vector_memory.drivers.meilisearch.index_prefix', 'agent_vectors_');
        $this->embedder = config('vizra-adk.vector_memory.drivers.meilisearch.embedder', 'default');
        $this->semanticRatio = config('vizra-adk.vector_memory.drivers.meilisearch.semantic_ratio', 1.0);
    }

    /**
     * Store a vector memory entry in Meilisearch.
     */
    public function store(VectorMemory $memory): bool
    {
        $indexName = $this->getIndexName($memory->agent_name, $memory->namespace);

        try {
            // Ensure index exists and is configured
            $this->ensureIndex($indexName, $memory->embedding_dimensions);

            // Prepare document for Meilisearch
            $document = [
                'id' => $memory->id,
                'agent_name' => $memory->agent_name,
                'namespace' => $memory->namespace,
                'content' => $memory->content,
                'metadata' => $memory->metadata ?? [],
                'source' => $memory->source,
                'source_id' => $memory->source_id,
                'chunk_index' => $memory->chunk_index,
                'embedding_provider' => $memory->embedding_provider,
                'embedding_model' => $memory->embedding_model,
                'embedding_dimensions' => $memory->embedding_dimensions,
                'embedding_vector' => $memory->embedding_vector, // Meilisearch native vector support
                'embedding_norm' => $memory->embedding_norm,
                'content_hash' => $memory->content_hash,
                'token_count' => $memory->token_count,
                'created_at' => $memory->created_at?->timestamp,
                'updated_at' => $memory->updated_at?->timestamp,
            ];

            $response = $this->makeRequest('POST', "/indexes/{$indexName}/documents", [$document]);

            $this->logDebug('Stored document in Meilisearch', [
                'index' => $indexName,
                'document_id' => $memory->id,
                'task_uid' => $response['taskUid'] ?? null,
            ], 'vector_memory');

            return true;

        } catch (\Exception $e) {
            $this->logError('Failed to store document in Meilisearch', [
                'error' => $e->getMessage(),
                'index' => $indexName,
                'document_id' => $memory->id,
            ], 'vector_memory');
            throw new RuntimeException('Failed to store in Meilisearch: '.$e->getMessage());
        }
    }

    /**
     * Search for similar vectors in Meilisearch.
     */
    public function search(
        string $agentName,
        array $queryEmbedding,
        string $namespace = 'default',
        int $limit = 5,
        float $threshold = 0.7
    ): Collection {
        $indexName = $this->getIndexName($agentName, $namespace);

        try {
            // First, try to use native vector search if available
            try {
                $searchParams = [
                    'vector' => $queryEmbedding,
                    'hybrid' => [
                        'embedder' => $this->embedder,
                        'semanticRatio' => $this->semanticRatio,
                    ],
                    'limit' => $limit,
                    'filter' => "agent_name = '{$agentName}' AND namespace = '{$namespace}'",
                    'showRankingScore' => true,
                ];

                $response = $this->makeRequest('POST', "/indexes/{$indexName}/search", $searchParams);
            } catch (\Exception $e) {
                // Fallback to retrieving all documents and calculating similarity in PHP
                $this->logInfo('Meilisearch native vector search not available, using fallback', [
                    'error' => $e->getMessage()
                ], 'vector_memory');
                
                // Get all documents for this agent/namespace
                $searchParams = [
                    'filter' => "agent_name = '{$agentName}' AND namespace = '{$namespace}'",
                    'limit' => 1000, // Get more documents to filter by similarity
                ];
                
                $response = $this->makeRequest('POST', "/indexes/{$indexName}/search", $searchParams);
                
                // Calculate similarity for each document
                $response['hits'] = collect($response['hits'] ?? [])
                    ->map(function ($hit) use ($queryEmbedding) {
                        if (isset($hit['embedding_vector']) && is_array($hit['embedding_vector'])) {
                            $similarity = $this->cosineSimilarity($queryEmbedding, $hit['embedding_vector']);
                            $hit['_rankingScore'] = $similarity;
                        } else {
                            $hit['_rankingScore'] = 0;
                        }
                        return $hit;
                    })
                    ->sortByDesc('_rankingScore')
                    ->values()
                    ->toArray();
            }

            $results = collect($response['hits'] ?? [])
                ->filter(function ($hit) use ($threshold) {
                    // Convert Meilisearch ranking score to similarity (0-1)
                    $similarity = $hit['_rankingScore'] ?? 0;

                    return $similarity >= $threshold;
                })
                ->map(function ($hit) {
                    return (object) [
                        'id' => $hit['id'],
                        'agent_name' => $hit['agent_name'],
                        'namespace' => $hit['namespace'],
                        'content' => $hit['content'],
                        'metadata' => $hit['metadata'] ?? [],
                        'source' => $hit['source'],
                        'source_id' => $hit['source_id'],
                        'embedding_provider' => $hit['embedding_provider'],
                        'embedding_model' => $hit['embedding_model'],
                        'created_at' => isset($hit['created_at']) ?
                            \Carbon\Carbon::createFromTimestamp($hit['created_at']) : null,
                        'similarity' => $hit['_rankingScore'] ?? null,
                    ];
                })
                ->take($limit);

            $this->logDebug('Meilisearch vector search completed', [
                'index' => $indexName,
                'results_count' => $results->count(),
                'query_dimensions' => count($queryEmbedding),
            ], 'vector_memory');

            return $results;

        } catch (\Exception $e) {
            $this->logError('Meilisearch vector search failed', [
                'error' => $e->getMessage(),
                'index' => $indexName,
                'agent_name' => $agentName,
            ], 'vector_memory');
            throw new RuntimeException('Meilisearch search failed: '.$e->getMessage());
        }
    }

    /**
     * Delete memories from Meilisearch.
     */
    public function delete(string $agentName, string $namespace = 'default', ?string $source = null): int
    {
        $indexName = $this->getIndexName($agentName, $namespace);

        try {
            if ($source) {
                // Delete by source
                $filter = "agent_name = '{$agentName}' AND namespace = '{$namespace}' AND source = '{$source}'";
            } else {
                // Delete all in namespace
                $filter = "agent_name = '{$agentName}' AND namespace = '{$namespace}'";
            }

            $response = $this->makeRequest('POST', "/indexes/{$indexName}/documents/delete", [
                'filter' => $filter,
            ]);

            // Get the task to track deletion
            $taskUid = $response['taskUid'] ?? null;

            // Wait for task completion to get actual count
            $deletedCount = $this->waitForTask($taskUid);

            $this->logInfo('Deleted documents from Meilisearch', [
                'index' => $indexName,
                'filter' => $filter,
                'deleted_count' => $deletedCount,
            ], 'vector_memory');

            return $deletedCount;

        } catch (\Exception $e) {
            $this->logError('Failed to delete from Meilisearch', [
                'error' => $e->getMessage(),
                'index' => $indexName,
                'agent_name' => $agentName,
            ], 'vector_memory');
            throw new RuntimeException('Meilisearch deletion failed: '.$e->getMessage());
        }
    }

    /**
     * Get statistics for an agent/namespace.
     */
    public function getStatistics(string $agentName, string $namespace = 'default'): array
    {
        $indexName = $this->getIndexName($agentName, $namespace);

        try {
            // Get index stats
            $indexStats = $this->makeRequest('GET', "/indexes/{$indexName}/stats");

            // Get sample documents to analyze
            $searchResponse = $this->makeRequest('POST', "/indexes/{$indexName}/search", [
                'q' => '',
                'filter' => "agent_name = '{$agentName}' AND namespace = '{$namespace}'",
                'limit' => 1000,
            ]);

            $documents = $searchResponse['hits'] ?? [];

            $providers = [];
            $sources = [];
            $totalTokens = 0;

            foreach ($documents as $doc) {
                $provider = $doc['embedding_provider'] ?? 'unknown';
                $providers[$provider] = ($providers[$provider] ?? 0) + 1;

                if (! empty($doc['source'])) {
                    $source = $doc['source'];
                    $sources[$source] = ($sources[$source] ?? 0) + 1;
                }

                $totalTokens += $doc['token_count'] ?? 0;
            }

            return [
                'total_memories' => count($documents),
                'total_tokens' => $totalTokens,
                'providers' => $providers,
                'sources' => $sources,
                'index_stats' => $indexStats,
            ];

        } catch (\Exception $e) {
            $this->logError('Failed to get Meilisearch statistics', [
                'error' => $e->getMessage(),
                'index' => $indexName,
                'agent_name' => $agentName,
            ], 'vector_memory');

            return [
                'total_memories' => 0,
                'total_tokens' => 0,
                'providers' => [],
                'sources' => [],
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Ensure the index exists and is properly configured.
     */
    protected function ensureIndex(string $indexName, int $dimensions): void
    {
        try {
            // Check if index exists
            $this->makeRequest('GET', "/indexes/{$indexName}");
        } catch (\Exception $e) {
            // Index doesn't exist, create it
            $this->makeRequest('POST', '/indexes', [
                'uid' => $indexName,
                'primaryKey' => 'id',
            ]);

            // Wait for index creation
            sleep(1);

            // Configure vector search settings
            $this->makeRequest('PATCH', "/indexes/{$indexName}/settings", [
                'searchableAttributes' => ['content', 'metadata'],
                'filterableAttributes' => [
                    'agent_name', 'namespace', 'source', 'source_id',
                    'embedding_provider', 'embedding_model', 'created_at',
                ],
                'sortableAttributes' => ['created_at', 'token_count'],
                'distinctAttribute' => 'content_hash',
                'rankingRules' => [
                    'words',
                    'typo',
                    'proximity',
                    'attribute',
                    'sort',
                    'exactness',
                ],
            ]);

            $this->logInfo('Created and configured Meilisearch index', [
                'index' => $indexName,
                'dimensions' => $dimensions,
            ], 'vector_memory');
        }
    }

    /**
     * Generate index name for agent and namespace.
     */
    protected function getIndexName(string $agentName, string $namespace): string
    {
        return $this->indexPrefix.strtolower($agentName).'_'.strtolower($namespace);
    }

    /**
     * Calculate cosine similarity between two vectors.
     */
    protected function cosineSimilarity(array $a, array $b): float
    {
        if (count($a) !== count($b)) {
            return 0.0;
        }

        $dotProduct = 0.0;
        $normA = 0.0;
        $normB = 0.0;

        for ($i = 0; $i < count($a); $i++) {
            $dotProduct += $a[$i] * $b[$i];
            $normA += $a[$i] * $a[$i];
            $normB += $b[$i] * $b[$i];
        }

        $normA = sqrt($normA);
        $normB = sqrt($normB);

        if ($normA == 0.0 || $normB == 0.0) {
            return 0.0;
        }

        return $dotProduct / ($normA * $normB);
    }

    /**
     * Make HTTP request to Meilisearch API.
     */
    protected function makeRequest(string $method, string $endpoint, array $data = []): array
    {
        $headers = ['Content-Type' => 'application/json'];

        if ($this->apiKey) {
            $headers['Authorization'] = 'Bearer '.$this->apiKey;
        }

        $url = rtrim($this->host, '/').$endpoint;

        $response = Http::withHeaders($headers)
            ->timeout(30)
            ->send($method, $url, $method === 'GET' ? ['query' => $data] : ['json' => $data]);

        if (! $response->successful()) {
            throw new RuntimeException(
                "Meilisearch API error: {$response->status()} - ".$response->body()
            );
        }

        return $response->json();
    }

    /**
     * Wait for a Meilisearch task to complete.
     */
    protected function waitForTask(?string $taskUid, int $maxWaitSeconds = 10): int
    {
        if (! $taskUid) {
            return 0;
        }

        $startTime = time();

        while (time() - $startTime < $maxWaitSeconds) {
            try {
                $task = $this->makeRequest('GET', "/tasks/{$taskUid}");

                if ($task['status'] === 'succeeded') {
                    return $task['details']['deletedDocuments'] ??
                           $task['details']['indexedDocuments'] ?? 1;
                }

                if ($task['status'] === 'failed') {
                    throw new RuntimeException('Meilisearch task failed: '.($task['error'] ?? 'Unknown error'));
                }

                sleep(1);
            } catch (\Exception $e) {
                break;
            }
        }

        return 0; // Timeout or error
    }

    /**
     * Check if Meilisearch is available.
     */
    public function isAvailable(): bool
    {
        try {
            $this->makeRequest('GET', '/health');

            return true;
        } catch (\Exception $e) {
            return false;
        }
    }
}
