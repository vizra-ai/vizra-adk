<?php

namespace Vizra\VizraADK\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Vizra\VizraADK\Contracts\EmbeddingProviderInterface;
use Vizra\VizraADK\Models\VectorMemory;
use Vizra\VizraADK\Services\Drivers\MeilisearchVectorDriver;

class VectorMemoryManager
{
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
     */
    public function addDocument(
        string $agentName,
        string $content,
        array $metadata = [],
        string $namespace = 'default',
        ?string $source = null,
        ?string $sourceId = null
    ): Collection {
        Log::info('Adding document to vector memory', [
            'agent_name' => $agentName,
            'content_length' => strlen($content),
            'namespace' => $namespace,
            'source' => $source,
        ]);

        // Chunk the document
        $chunks = $this->chunker->chunk($content);
        $memoryEntries = collect();

        foreach ($chunks as $index => $chunk) {
            $entry = $this->addChunk(
                agentName: $agentName,
                content: $chunk,
                metadata: array_merge($metadata, ['chunk_index' => $index]),
                namespace: $namespace,
                source: $source,
                sourceId: $sourceId,
                chunkIndex: $index
            );

            if ($entry) {
                $memoryEntries->push($entry);
            }
        }

        Log::info('Document added to vector memory', [
            'agent_name' => $agentName,
            'chunks_created' => $memoryEntries->count(),
            'namespace' => $namespace,
        ]);

        return $memoryEntries;
    }

    /**
     * Add a single text chunk to vector memory.
     */
    public function addChunk(
        string $agentName,
        string $content,
        array $metadata = [],
        string $namespace = 'default',
        ?string $source = null,
        ?string $sourceId = null,
        int $chunkIndex = 0
    ): ?VectorMemory {
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
            Log::debug('Content already exists in vector memory', [
                'agent_name' => $agentName,
                'content_hash' => $contentHash,
            ]);

            return $existing;
        }

        try {
            // Generate embedding
            $embeddings = $this->embeddingProvider->embed($content);
            $embedding = $embeddings[0]; // Single embedding

            // Calculate norm
            $norm = VectorMemory::calculateNorm($embedding);

            // Create memory entry
            $memory = VectorMemory::create([
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
                'embedding_vector' => $this->driver === 'pgvector' ? null : $embedding,
                'embedding_norm' => $norm,
                'content_hash' => $contentHash,
                'token_count' => VectorMemory::estimateTokenCount($content),
            ]);

            // Handle driver-specific storage
            if ($this->driver === 'pgvector' && DB::connection()->getDriverName() === 'pgsql') {
                // For PostgreSQL with pgvector, update the vector column separately
                DB::table('agent_vector_memories')
                    ->where('id', $memory->id)
                    ->update(['embedding' => '['.implode(',', $embedding).']']);
            } elseif ($this->driver === 'meilisearch') {
                // For Meilisearch, store in the vector database
                $meilisearchDriver = new MeilisearchVectorDriver;
                $meilisearchDriver->store($memory);
            }

            Log::debug('Added chunk to vector memory', [
                'agent_name' => $agentName,
                'memory_id' => $memory->id,
                'content_length' => strlen($content),
                'dimensions' => count($embedding),
            ]);

            return $memory;

        } catch (\Exception $e) {
            Log::error('Failed to add chunk to vector memory', [
                'agent_name' => $agentName,
                'error' => $e->getMessage(),
                'content_length' => strlen($content),
            ]);
            throw new RuntimeException('Failed to add content to vector memory: '.$e->getMessage());
        }
    }

    /**
     * Search for similar content in vector memory.
     */
    public function search(
        string $agentName,
        string $query,
        string $namespace = 'default',
        int $limit = 5,
        float $threshold = 0.7
    ): Collection {
        Log::debug('Searching vector memory', [
            'agent_name' => $agentName,
            'query_length' => strlen($query),
            'namespace' => $namespace,
            'limit' => $limit,
            'threshold' => $threshold,
        ]);

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
            Log::error('Vector memory search failed', [
                'agent_name' => $agentName,
                'error' => $e->getMessage(),
                'query_length' => strlen($query),
            ]);
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
        $embeddingStr = '['.implode(',', $queryEmbedding).']';

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
        ', [$embeddingStr, $agentName, $namespace, $embeddingStr, $threshold, $embeddingStr, $limit]);

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
     */
    public function generateRagContext(
        string $agentName,
        string $query,
        string $namespace = 'default',
        int $limit = 5,
        float $threshold = 0.7
    ): array {
        $results = $this->search($agentName, $query, $namespace, $limit, $threshold);

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
     */
    public function deleteMemories(string $agentName, string $namespace = 'default'): int
    {
        $count = VectorMemory::forAgent($agentName)
            ->inNamespace($namespace)
            ->delete();

        Log::info('Deleted vector memories', [
            'agent_name' => $agentName,
            'namespace' => $namespace,
            'count' => $count,
        ]);

        return $count;
    }

    /**
     * Delete memories by source.
     */
    public function deleteMemoriesBySource(string $agentName, string $source, string $namespace = 'default'): int
    {
        $count = VectorMemory::forAgent($agentName)
            ->inNamespace($namespace)
            ->fromSource($source)
            ->delete();

        Log::info('Deleted vector memories by source', [
            'agent_name' => $agentName,
            'namespace' => $namespace,
            'source' => $source,
            'count' => $count,
        ]);

        return $count;
    }

    /**
     * Get memory statistics for an agent.
     */
    public function getStatistics(string $agentName, string $namespace = 'default'): array
    {
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
}
