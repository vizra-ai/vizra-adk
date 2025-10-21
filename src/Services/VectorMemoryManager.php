<?php

namespace Vizra\VizraADK\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Pgvector\Laravel\Vector;
use RuntimeException;
use Vizra\VizraADK\Contracts\EmbeddingProviderInterface;
use Vizra\VizraADK\Facades\Agent;
use Vizra\VizraADK\Models\VectorMemory;
use Vizra\VizraADK\Services\Drivers\MeilisearchVectorDriver;
use Vizra\VizraADK\Traits\HasLogging;

class VectorMemoryManager
{
    use HasLogging;

    protected EmbeddingProviderInterface $embeddingProvider;

    protected DocumentChunker $chunker;

    protected string $driver;

    public function __construct(EmbeddingProviderInterface $embeddingProvider, DocumentChunker $chunker)
    {
        $this->embeddingProvider = $embeddingProvider;
        $this->chunker = $chunker;
        $this->driver = config('vizra-adk.vector_memory.driver', 'pgvector');
    }

    /**
     * Add a document to vector memory.
     *
     * @param string $agentClass The agent class (e.g., ChiefDinnerAgent::class)
     * @param string|array $contentOrArray Either a string content or array with all options
     * @param array|null $metadata Optional metadata when using string content
     * @return Collection
     */
    public function addDocument(string $agentClass, $contentOrArray, array $metadata = null): Collection
    {
        // Extract agent name from class
        $agentName = $this->getAgentName($agentClass);
        
        // Parse parameters
        if (is_string($contentOrArray)) {
            $content = $contentOrArray;
            $metadata = $metadata ?? [];
            $namespace = 'default';
            $source = null;
            $sourceId = null;
        } elseif (is_array($contentOrArray)) {
            $content = $contentOrArray['content'] ?? throw new InvalidArgumentException('content is required');
            $metadata = $contentOrArray['metadata'] ?? [];
            $namespace = $contentOrArray['namespace'] ?? 'default';
            $source = $contentOrArray['source'] ?? null;
            $sourceId = $contentOrArray['source_id'] ?? null;
        } else {
            throw new InvalidArgumentException('Second parameter must be string or array');
        }
        
        // Auto-generate source if not provided
        $source = $source ?: class_basename($agentClass);
        $sourceId = $sourceId ?: (string) Str::ulid();

        $this->logInfo('Adding document to vector memory', [
            'agent_name' => $agentName,
            'content_length' => strlen($content),
            'namespace' => $namespace,
            'source' => $source,
        ], 'vector_memory');

        // Chunk the document
        $chunks = $this->chunker->chunk($content);
        $memoryEntries = collect();

        foreach ($chunks as $index => $chunk) {
            $entry = $this->addChunk(
                agentClass: $agentClass,
                contentOrArray: [
                    'content' => $chunk,
                    'metadata' => array_merge($metadata, ['chunk_index' => $index]),
                    'namespace' => $namespace,
                    'source' => $source,
                    'source_id' => $sourceId,
                    'chunk_index' => $index
                ]
            );

            if ($entry) {
                $memoryEntries->push($entry);
            }
        }

        $this->logInfo('Document added to vector memory', [
            'agent_name' => $agentName,
            'chunks_created' => $memoryEntries->count(),
            'namespace' => $namespace,
        ], 'vector_memory');

        return $memoryEntries;
    }

    /**
     * Add a single text chunk to vector memory.
     *
     * @param string $agentClass The agent class
     * @param string|array $contentOrArray Either a string content or array with all options
     * @param array|null $metadata Optional metadata when using string content
     * @return VectorMemory|null
     */
    public function addChunk(string $agentClass, $contentOrArray, array $metadata = null): ?VectorMemory
    {
        // Extract agent name from class
        $agentName = $this->getAgentName($agentClass);
        
        // Parse parameters
        if (is_string($contentOrArray)) {
            $content = $contentOrArray;
            $metadata = $metadata ?? [];
            $namespace = 'default';
            $source = null;
            $sourceId = null;
            $chunkIndex = 0;
        } elseif (is_array($contentOrArray)) {
            $content = $contentOrArray['content'] ?? throw new InvalidArgumentException('content is required');
            $metadata = $contentOrArray['metadata'] ?? [];
            $namespace = $contentOrArray['namespace'] ?? 'default';
            $source = $contentOrArray['source'] ?? null;
            $sourceId = $contentOrArray['source_id'] ?? null;
            $chunkIndex = $contentOrArray['chunk_index'] ?? 0;
        } else {
            throw new InvalidArgumentException('Second parameter must be string or array');
        }
        
        // Auto-generate source if not provided
        $source = $source ?: class_basename($agentClass);
        $sourceId = $sourceId ?: (string) Str::ulid();
        $content = trim($content);

        if (empty($content)) {
            return null;
        }

        // Generate content hash for deduplication
        $contentHash = VectorMemory::generateContentHash($content, $agentName);

        // Check if this content already exists
        $existing = VectorMemory::where('agent_name', $agentName)
            ->where('content_hash', $contentHash)
            ->first();

        if ($existing) {
            $this->logDebug('Content already exists in vector memory', [
                'agent_name' => $agentName,
                'content_hash' => $contentHash,
            ], 'vector_memory');

            return $existing;
        }

        try {
            // Generate embedding
            $embeddings = $this->embeddingProvider->embed($content);

            if (empty($embeddings) || ! isset($embeddings[0]) || ! is_array($embeddings[0]) || empty($embeddings[0])) {
                throw new RuntimeException('Embedding provider returned empty embedding data.');
            }

            $embedding = $embeddings[0]; // Single embedding

            // Calculate norm
            $norm = VectorMemory::calculateNorm($embedding);

            // Prepare data for the new memory entry
            $memoryData = [
                'agent_name' => $agentName,
                'namespace' => $namespace,
                'content' => $content,
                'metadata' => $metadata,
                'source' => $source,
                'source_id' => $sourceId,
                'chunk_index' => $chunkIndex,
                'embedding_provider' => $this->embeddingProvider->getProviderName(),
                'embedding_model' => $this->embeddingProvider->getModel(),
                'embedding_dimensions' => $this->embeddingProvider->getDimensions(),
                'embedding_norm' => $norm,
                'content_hash' => $contentHash,
                'token_count' => VectorMemory::estimateTokenCount($content),
            ];

            if ($this->driver === 'pgvector' && DB::connection()->getDriverName() === 'pgsql') {
                $memoryData['embedding'] = new Vector($embedding);
            } else {
                $memoryData['embedding_vector'] = $embedding;
            }

            // Create memory entry
            $memory = VectorMemory::create($memoryData);

            // Handle driver-specific storage
            if ($this->driver === 'meilisearch') {
                // For Meilisearch, store in the vector database
                $meilisearchDriver = new MeilisearchVectorDriver;
                $meilisearchDriver->store($memory);
            }

            $this->logDebug('Added chunk to vector memory', [
                'agent_name' => $agentName,
                'memory_id' => $memory->id,
                'content_length' => strlen($content),
                'dimensions' => count($embedding),
            ], 'vector_memory');

            return $memory;

        } catch (\Exception $e) {
            $this->logError('Failed to add chunk to vector memory', [
                'agent_name' => $agentName,
                'error' => $e->getMessage(),
                'content_length' => strlen($content),
            ], 'vector_memory');
            throw new RuntimeException('Failed to add content to vector memory: '.$e->getMessage());
        }
    }

    /**
     * Search for similar content in vector memory.
     *
     * @param string $agentClass The agent class
     * @param string|array $queryOrArray Either a search query string or array with options
     * @param int|null $limit Optional limit when using string query
     * @return Collection
     */
    public function search(string $agentClass, $queryOrArray, int $limit = null): Collection
    {
        // Extract agent name from class
        $agentName = $this->getAgentName($agentClass);
        
        // Parse parameters
        if (is_string($queryOrArray)) {
            $query = $queryOrArray;
            $limit = $limit ?? 5;
            $namespace = 'default';
            $threshold = 0.7;
        } elseif (is_array($queryOrArray)) {
            $query = $queryOrArray['query'] ?? throw new InvalidArgumentException('query is required');
            $limit = $queryOrArray['limit'] ?? 5;
            $namespace = $queryOrArray['namespace'] ?? 'default';
            $threshold = $queryOrArray['threshold'] ?? 0.7;
        } else {
            throw new InvalidArgumentException('Second parameter must be string or array');
        }
        $this->logDebug('Searching vector memory', [
            'agent_name' => $agentName,
            'query_length' => strlen($query),
            'namespace' => $namespace,
            'limit' => $limit,
            'threshold' => $threshold,
        ], 'vector_memory');

        try {
            // Generate embedding for the query
            $queryEmbeddings = $this->embeddingProvider->embed($query);
            $queryEmbedding = $queryEmbeddings[0];

            if ($this->driver === 'pgvector' && DB::connection()->getDriverName() === 'pgsql') {
                return $this->searchWithPgVector($agentName, $queryEmbedding, $namespace, $limit, $threshold);
            } elseif ($this->driver === 'meilisearch') {
                return $this->searchWithMeilisearch($agentName, $queryEmbedding, $namespace, $limit, $threshold);
            } else {
                return $this->searchWithCosineSimilarity($agentName, $queryEmbedding, $namespace, $limit, $threshold);
            }

        } catch (\Exception $e) {
            $this->logError('Vector memory search failed', [
                'agent_name' => $agentName,
                'error' => $e->getMessage(),
                'query_length' => strlen($query),
            ], 'vector_memory');
            throw new RuntimeException('Vector memory search failed: '.$e->getMessage());
        }
    }

    /**
     * Search using PostgreSQL pgvector extension.
     */
    protected function searchWithPgVector(
        string $agentName,
        array $queryEmbedding,
        string $namespace,
        int $limit,
        float $threshold
    ): Collection {
        $embeddingVector = new Vector($queryEmbedding);

        $results = DB::select('
            SELECT
                id, agent_name, namespace, content, metadata, source, source_id,
                embedding_provider, embedding_model, created_at,
                1 - (embedding <=> ?) as similarity
            FROM agent_vector_memories
            WHERE agent_name = ?
                AND namespace = ?
                AND 1 - (embedding <=> ?) >= ?
            ORDER BY embedding <=> ?
            LIMIT ?
        ', [
            $embeddingVector,
            $agentName,
            $namespace,
            clone $embeddingVector,
            $threshold,
            clone $embeddingVector,
            $limit,
        ]);

        return collect($results)->map(function ($result) {
            $result->metadata = json_decode($result->metadata, true);

            return $result;
        });
    }

    /**
     * Search using in-memory cosine similarity calculation.
     */
    protected function searchWithCosineSimilarity(
        string $agentName,
        array $queryEmbedding,
        string $namespace,
        int $limit,
        float $threshold
    ): Collection {
        $memories = VectorMemory::forAgent($agentName)
            ->inNamespace($namespace)
            ->get();

        $results = $memories->map(function (VectorMemory $memory) use ($queryEmbedding) {
            $similarity = $memory->cosineSimilarity($queryEmbedding);

            return (object) [
                'id' => $memory->id,
                'agent_name' => $memory->agent_name,
                'namespace' => $memory->namespace,
                'content' => $memory->content,
                'metadata' => $memory->metadata,
                'source' => $memory->source,
                'source_id' => $memory->source_id,
                'embedding_provider' => $memory->embedding_provider,
                'embedding_model' => $memory->embedding_model,
                'created_at' => $memory->created_at,
                'similarity' => $similarity,
            ];
        })
            ->filter(fn ($result) => $result->similarity >= $threshold)
            ->sortByDesc('similarity')
            ->take($limit)
            ->values();

        return $results;
    }

    /**
     * Search using Meilisearch vector database.
     */
    protected function searchWithMeilisearch(
        string $agentName,
        array $queryEmbedding,
        string $namespace,
        int $limit,
        float $threshold
    ): Collection {
        $meilisearchDriver = new MeilisearchVectorDriver;

        return $meilisearchDriver->search(
            agentName: $agentName,
            queryEmbedding: $queryEmbedding,
            namespace: $namespace,
            limit: $limit,
            threshold: $threshold
        );
    }

    /**
     * Generate RAG context from search results.
     *
     * @param string $agentClass The agent class
     * @param string|array $queryOrArray Either a query string or array with options
     * @param array|null $options Optional options when using string query
     * @return array
     */
    public function generateRagContext(string $agentClass, $queryOrArray, array $options = null): array
    {
        // Parse parameters
        if (is_string($queryOrArray)) {
            $searchParams = ['query' => $queryOrArray];
            if ($options) {
                $searchParams = array_merge($searchParams, $options);
            }
        } else {
            $searchParams = $queryOrArray;
        }
        
        $results = $this->search($agentClass, $searchParams);

        // Extract query from params
        $query = is_string($queryOrArray) ? $queryOrArray : ($searchParams['query'] ?? '');
        
        if ($results->isEmpty()) {
            return [
                'context' => '',
                'sources' => [],
                'query' => $query,
            ];
        }

        $template = config('vizra-adk.vector_memory.rag.context_template');
        $maxLength = config('vizra-adk.vector_memory.rag.max_context_length', 4000);
        $includeMetadata = config('vizra-adk.vector_memory.rag.include_metadata', true);

        $contextParts = [];
        $sources = [];
        $currentLength = 0;

        foreach ($results as $result) {
            $content = $result->content;

            if ($includeMetadata && ! empty($result->metadata)) {
                $metadata = is_array($result->metadata) ? $result->metadata : json_decode($result->metadata, true);
                if (! empty($metadata)) {
                    $content .= "\n[Metadata: ".json_encode($metadata).']';
                }
            }

            if ($currentLength + strlen($content) > $maxLength) {
                break;
            }

            $contextParts[] = $content;
            $sources[] = [
                'id' => $result->id,
                'source' => $result->source,
                'source_id' => $result->source_id,
                'similarity' => $result->similarity ?? null,
                'created_at' => $result->created_at,
            ];

            $currentLength += strlen($content);
        }

        $context = implode("\n\n---\n\n", $contextParts);

        // Apply template if provided
        if ($template) {
            $context = str_replace(['{context}', '{query}'], [$context, $query], $template);
        }

        return [
            'context' => $context,
            'sources' => $sources,
            'query' => $query,
            'total_results' => $results->count(),
        ];
    }

    /**
     * Delete memories by agent and namespace.
     *
     * @param string $agentClass The agent class
     * @param string|array|null $namespaceOrArray Namespace string or array with options
     * @return int Number of memories deleted
     */
    public function deleteMemories(string $agentClass, $namespaceOrArray = null): int
    {
        // Extract agent name from class
        $agentName = $this->getAgentName($agentClass);
        
        // Parse parameters
        if (is_null($namespaceOrArray)) {
            $namespace = 'default';
        } elseif (is_string($namespaceOrArray)) {
            $namespace = $namespaceOrArray;
        } elseif (is_array($namespaceOrArray)) {
            $namespace = $namespaceOrArray['namespace'] ?? 'default';
        } else {
            throw new InvalidArgumentException('Second parameter must be string, array, or null');
        }
        
        $count = VectorMemory::forAgent($agentName)
            ->inNamespace($namespace)
            ->delete();

        $this->logInfo('Deleted vector memories', [
            'agent_name' => $agentName,
            'namespace' => $namespace,
            'count' => $count,
        ], 'vector_memory');

        return $count;
    }

    /**
     * Delete memories by source.
     *
     * @param string $agentClass The agent class
     * @param string|array $sourceOrArray Source string or array with options
     * @param string|null $namespace Optional namespace when using string source
     * @return int Number of memories deleted
     */
    public function deleteMemoriesBySource(string $agentClass, $sourceOrArray, string $namespace = null): int
    {
        // Extract agent name from class
        $agentName = $this->getAgentName($agentClass);
        
        // Parse parameters
        if (is_string($sourceOrArray)) {
            $source = $sourceOrArray;
            $namespace = $namespace ?? 'default';
        } elseif (is_array($sourceOrArray)) {
            $source = $sourceOrArray['source'] ?? throw new InvalidArgumentException('source is required');
            $namespace = $sourceOrArray['namespace'] ?? 'default';
        } else {
            throw new InvalidArgumentException('Second parameter must be string or array');
        }
        
        $count = VectorMemory::forAgent($agentName)
            ->inNamespace($namespace)
            ->fromSource($source)
            ->delete();

        $this->logInfo('Deleted vector memories by source', [
            'agent_name' => $agentName,
            'namespace' => $namespace,
            'source' => $source,
            'count' => $count,
        ], 'vector_memory');

        return $count;
    }

    /**
     * Get memory statistics for an agent.
     *
     * @param string $agentClass The agent class
     * @param string|array|null $namespaceOrArray Namespace string or array with options
     * @return array
     */
    public function getStatistics(string $agentClass, $namespaceOrArray = null): array
    {
        // Extract agent name from class
        $agentName = $this->getAgentName($agentClass);
        
        // Parse parameters
        if (is_null($namespaceOrArray)) {
            $namespace = 'default';
        } elseif (is_string($namespaceOrArray)) {
            $namespace = $namespaceOrArray;
        } elseif (is_array($namespaceOrArray)) {
            $namespace = $namespaceOrArray['namespace'] ?? 'default';
        } else {
            throw new InvalidArgumentException('Second parameter must be string, array, or null');
        }
        
        $query = VectorMemory::forAgent($agentName)->inNamespace($namespace);

        return [
            'total_memories' => $query->count(),
            'total_tokens' => $query->sum('token_count'),
            'providers' => $query->select('embedding_provider', DB::raw('count(*) as count'))
                ->groupBy('embedding_provider')
                ->pluck('count', 'embedding_provider')
                ->toArray(),
            'sources' => $query->whereNotNull('source')
                ->select('source', DB::raw('count(*) as count'))
                ->groupBy('source')
                ->pluck('count', 'source')
                ->toArray(),
        ];
    }

    /**
     * Extract agent name from agent class.
     *
     * @param string $agentClass
     * @return string
     */
    protected function getAgentName(string $agentClass): string
    {
        // Try to instantiate the agent class and get its name
        if (class_exists($agentClass) && is_subclass_of($agentClass, \Vizra\VizraADK\Agents\BaseAgent::class)) {
            $agent = new $agentClass();
            return $agent->getName();
        }
        
        // Fallback to extracting from class name
        $className = class_basename($agentClass);
        // Convert ChiefDinnerAgent to chief_dinner_agent
        return Str::snake(str_replace('Agent', '', $className)) . '_agent';
    }
}
