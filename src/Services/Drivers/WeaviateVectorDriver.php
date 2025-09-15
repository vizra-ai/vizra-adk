<?php

namespace Vizra\VizraADK\Services\Drivers;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Vizra\VizraADK\Contracts\VectorDriverInterface;
use Vizra\VizraADK\Models\VectorMemory;

class WeaviateVectorDriver implements VectorDriverInterface
{
    protected string $host;

    protected ?string $apiKey;

    protected string $classPrefix;

    public function __construct()
    {
        $this->host = config('vizra-adk.vector_memory.drivers.weaviate.host', 'http://localhost:8080');
        $this->apiKey = config('vizra-adk.vector_memory.drivers.weaviate.api_key');
        $this->classPrefix = config('vizra-adk.vector_memory.drivers.weaviate.class_prefix', 'Agent');
    }

    /**
     * Store a vector memory entry in Weaviate.
     */
    public function store(VectorMemory $memory): bool
    {
        $className = $this->getClassName($memory->agent_name, $memory->namespace);

        try {
            // Ensure class exists
            $this->ensureClass($className, $memory->embedding_dimensions);

            // Prepare object for Weaviate
            $object = [
                'class' => $className,
                'properties' => [
                    'memory_id' => $memory->id,
                    'agent_name' => $memory->agent_name,
                    'namespace' => $memory->namespace,
                    'content' => $memory->content,
                    'metadata' => json_encode($memory->metadata ?? []),
                    'source' => $memory->source,
                    'source_id' => $memory->source_id,
                    'chunk_index' => $memory->chunk_index,
                    'embedding_provider' => $memory->embedding_provider,
                    'embedding_model' => $memory->embedding_model,
                    'embedding_dimensions' => $memory->embedding_dimensions,
                    'embedding_norm' => $memory->embedding_norm,
                    'content_hash' => $memory->content_hash,
                    'token_count' => $memory->token_count,
                    'created_at' => $memory->created_at?->toISOString(),
                    'updated_at' => $memory->updated_at?->toISOString(),
                ],
                'vector' => $memory->embedding_vector,
            ];

            $response = $this->makeRequest('POST', '/v1/objects', $object);

            Log::debug('Stored document in Weaviate', [
                'class' => $className,
                'object_id' => $response['id'] ?? null,
                'memory_id' => $memory->id,
            ]);

            return true;

        } catch (\Exception $e) {
            Log::error('Failed to store document in Weaviate', [
                'error' => $e->getMessage(),
                'class' => $className,
                'memory_id' => $memory->id,
            ]);
            throw new RuntimeException('Failed to store in Weaviate: '.$e->getMessage());
        }
    }

    /**
     * Search for similar vectors in Weaviate.
     */
    public function search(
        string $agentName,
        array $queryEmbedding,
        string $namespace = 'default',
        int $limit = 5,
        float $threshold = 0.7
    ): Collection {
        $className = $this->getClassName($agentName, $namespace);

        try {
            $query = [
                'query' => sprintf(
                    '{
                        Get {
                            %s(
                                nearVector: {
                                    vector: %s
                                    distance: %f
                                }
                                limit: %d
                                where: {
                                    operator: And
                                    operands: [
                                        {
                                            path: ["agent_name"]
                                            operator: Equal
                                            valueText: "%s"
                                        },
                                        {
                                            path: ["namespace"]
                                            operator: Equal
                                            valueText: "%s"
                                        }
                                    ]
                                }
                            ) {
                                memory_id
                                agent_name
                                namespace
                                content
                                metadata
                                source
                                source_id
                                embedding_provider
                                embedding_model
                                created_at
                                _additional {
                                    distance
                                }
                            }
                        }
                    }',
                    $className,
                    json_encode($queryEmbedding),
                    1.0 - $threshold, // Weaviate uses distance, not similarity
                    $limit,
                    $agentName,
                    $namespace
                )
            ];

            $response = $this->makeRequest('POST', '/v1/graphql', $query);

            $results = collect($response['data']['Get'][$className] ?? [])
                ->map(function ($hit) {
                    $distance = $hit['_additional']['distance'] ?? 1.0;
                    $similarity = 1.0 - $distance; // Convert distance back to similarity

                    return (object) [
                        'id' => $hit['memory_id'],
                        'agent_name' => $hit['agent_name'],
                        'namespace' => $hit['namespace'],
                        'content' => $hit['content'],
                        'metadata' => json_decode($hit['metadata'] ?? '[]', true),
                        'source' => $hit['source'],
                        'source_id' => $hit['source_id'],
                        'embedding_provider' => $hit['embedding_provider'],
                        'embedding_model' => $hit['embedding_model'],
                        'created_at' => $hit['created_at'] ? \Carbon\Carbon::parse($hit['created_at']) : null,
                        'similarity' => $similarity,
                    ];
                })
                ->filter(fn ($result) => $result->similarity >= $threshold);

            Log::debug('Weaviate vector search completed', [
                'class' => $className,
                'results_count' => $results->count(),
                'query_dimensions' => count($queryEmbedding),
            ]);

            return $results;

        } catch (\Exception $e) {
            Log::error('Weaviate vector search failed', [
                'error' => $e->getMessage(),
                'class' => $className,
                'agent_name' => $agentName,
            ]);
            throw new RuntimeException('Weaviate search failed: '.$e->getMessage());
        }
    }

    /**
     * Delete memories from Weaviate.
     */
    public function delete(string $agentName, string $namespace = 'default', ?string $source = null): int
    {
        $className = $this->getClassName($agentName, $namespace);

        try {
            // Build where condition
            $whereCondition = [
                'operator' => 'And',
                'operands' => [
                    [
                        'path' => ['agent_name'],
                        'operator' => 'Equal',
                        'valueText' => $agentName,
                    ],
                    [
                        'path' => ['namespace'],
                        'operator' => 'Equal',
                        'valueText' => $namespace,
                    ],
                ]
            ];

            if ($source) {
                $whereCondition['operands'][] = [
                    'path' => ['source'],
                    'operator' => 'Equal',
                    'valueText' => $source,
                ];
            }

            $mutation = [
                'query' => sprintf(
                    'mutation {
                        Delete {
                            %s(
                                where: %s
                            ) {
                                successful
                                failed
                            }
                        }
                    }',
                    $className,
                    json_encode($whereCondition)
                )
            ];

            $response = $this->makeRequest('POST', '/v1/graphql', $mutation);

            $successful = $response['data']['Delete'][$className]['successful'] ?? 0;
            $failed = $response['data']['Delete'][$className]['failed'] ?? 0;

            Log::info('Deleted documents from Weaviate', [
                'class' => $className,
                'successful' => $successful,
                'failed' => $failed,
                'source' => $source,
            ]);

            return $successful;

        } catch (\Exception $e) {
            Log::error('Failed to delete from Weaviate', [
                'error' => $e->getMessage(),
                'class' => $className,
                'agent_name' => $agentName,
            ]);
            throw new RuntimeException('Weaviate deletion failed: '.$e->getMessage());
        }
    }

    /**
     * Get statistics for an agent/namespace.
     */
    public function getStatistics(string $agentName, string $namespace = 'default'): array
    {
        $className = $this->getClassName($agentName, $namespace);

        try {
            $query = [
                'query' => sprintf(
                    '{
                        Aggregate {
                            %s(
                                where: {
                                    operator: And
                                    operands: [
                                        {
                                            path: ["agent_name"]
                                            operator: Equal
                                            valueText: "%s"
                                        },
                                        {
                                            path: ["namespace"]
                                            operator: Equal
                                            valueText: "%s"
                                        }
                                    ]
                                }
                            ) {
                                meta {
                                    count
                                }
                                token_count {
                                    sum
                                }
                                embedding_provider {
                                    topOccurrences(limit: 10) {
                                        value
                                        occurs
                                    }
                                }
                                source {
                                    topOccurrences(limit: 10) {
                                        value
                                        occurs
                                    }
                                }
                            }
                        }
                    }',
                    $className,
                    $agentName,
                    $namespace
                )
            ];

            $response = $this->makeRequest('POST', '/v1/graphql', $query);
            $aggregateData = $response['data']['Aggregate'][$className][0] ?? [];

            $providers = [];
            $sources = [];

            foreach ($aggregateData['embedding_provider']['topOccurrences'] ?? [] as $occurrence) {
                $providers[$occurrence['value']] = $occurrence['occurs'];
            }

            foreach ($aggregateData['source']['topOccurrences'] ?? [] as $occurrence) {
                if ($occurrence['value']) {
                    $sources[$occurrence['value']] = $occurrence['occurs'];
                }
            }

            return [
                'total_memories' => $aggregateData['meta']['count'] ?? 0,
                'total_tokens' => $aggregateData['token_count']['sum'] ?? 0,
                'providers' => $providers,
                'sources' => $sources,
            ];

        } catch (\Exception $e) {
            Log::error('Failed to get Weaviate statistics', [
                'error' => $e->getMessage(),
                'class' => $className,
                'agent_name' => $agentName,
            ]);

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
     * Check if Weaviate is available.
     */
    public function isAvailable(): bool
    {
        try {
            $this->makeRequest('GET', '/v1/meta');
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Ensure the class exists in Weaviate.
     */
    protected function ensureClass(string $className, int $dimensions): void
    {
        try {
            // Check if class exists
            $this->makeRequest('GET', "/v1/schema/{$className}");
        } catch (\Exception $e) {
            // Class doesn't exist, create it
            $classDefinition = [
                'class' => $className,
                'description' => "Vector memories for {$className}",
                'vectorizer' => 'none', // We provide our own vectors
                'properties' => [
                    // Use string for ULID-based memory IDs
                    ['name' => 'memory_id', 'dataType' => ['string'], 'description' => 'Memory ID'],
                    ['name' => 'agent_name', 'dataType' => ['string'], 'description' => 'Agent name'],
                    ['name' => 'namespace', 'dataType' => ['string'], 'description' => 'Namespace'],
                    ['name' => 'content', 'dataType' => ['text'], 'description' => 'Content text'],
                    ['name' => 'metadata', 'dataType' => ['string'], 'description' => 'JSON metadata'],
                    ['name' => 'source', 'dataType' => ['string'], 'description' => 'Content source'],
                    ['name' => 'source_id', 'dataType' => ['string'], 'description' => 'Source ID'],
                    ['name' => 'chunk_index', 'dataType' => ['int'], 'description' => 'Chunk index'],
                    ['name' => 'embedding_provider', 'dataType' => ['string'], 'description' => 'Embedding provider'],
                    ['name' => 'embedding_model', 'dataType' => ['string'], 'description' => 'Embedding model'],
                    ['name' => 'embedding_dimensions', 'dataType' => ['int'], 'description' => 'Embedding dimensions'],
                    ['name' => 'embedding_norm', 'dataType' => ['number'], 'description' => 'Embedding norm'],
                    ['name' => 'content_hash', 'dataType' => ['string'], 'description' => 'Content hash'],
                    ['name' => 'token_count', 'dataType' => ['int'], 'description' => 'Token count'],
                    ['name' => 'created_at', 'dataType' => ['string'], 'description' => 'Created timestamp'],
                    ['name' => 'updated_at', 'dataType' => ['string'], 'description' => 'Updated timestamp'],
                ],
                'vectorIndexType' => 'hnsw',
                'vectorIndexConfig' => [
                    'distance' => 'cosine',
                    'efConstruction' => 128,
                    'maxConnections' => 64,
                ]
            ];

            $this->makeRequest('POST', '/v1/schema', $classDefinition);

            Log::info('Created Weaviate class', [
                'class' => $className,
                'dimensions' => $dimensions,
            ]);
        }
    }

    /**
     * Generate class name for agent and namespace.
     */
    protected function getClassName(string $agentName, string $namespace): string
    {
        $agentPart = str_replace('_', '', ucwords($agentName, '_'));
        $namespacePart = str_replace('_', '', ucwords($namespace, '_'));
        return $this->classPrefix . $agentPart . $namespacePart;
    }

    /**
     * Make HTTP request to Weaviate API.
     */
    protected function makeRequest(string $method, string $endpoint, array $data = []): array
    {
        $headers = ['Content-Type' => 'application/json'];

        if ($this->apiKey) {
            $headers['Authorization'] = 'Bearer ' . $this->apiKey;
        }

        $url = rtrim($this->host, '/') . $endpoint;

        $response = Http::withHeaders($headers)
            ->timeout(30)
            ->send($method, $url, $method === 'GET' ? ['query' => $data] : ['json' => $data]);

        if (!$response->successful()) {
            throw new RuntimeException(
                "Weaviate API error: {$response->status()} - " . $response->body()
            );
        }

        return $response->json();
    }
}
