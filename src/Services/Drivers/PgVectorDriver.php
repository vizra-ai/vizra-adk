<?php

namespace Vizra\VizraADK\Services\Drivers;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Vizra\VizraADK\Contracts\VectorDriverInterface;
use Vizra\VizraADK\Models\VectorMemory;

class PgVectorDriver implements VectorDriverInterface
{
    /**
     * Store a vector memory entry in PostgreSQL with pgvector.
     */
    public function store(VectorMemory $memory): bool
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            throw new RuntimeException('PgVector driver requires PostgreSQL database connection');
        }

        try {
            // Store the vector in the pgvector column separately
            DB::table('agent_vector_memories')
                ->where('id', $memory->id)
                ->update(['embedding' => '['.implode(',', $memory->embedding_vector).']']);

            Log::debug('Stored document in pgvector', [
                'document_id' => $memory->id,
                'dimensions' => count($memory->embedding_vector),
            ]);

            return true;

        } catch (\Exception $e) {
            Log::error('Failed to store document in pgvector', [
                'error' => $e->getMessage(),
                'document_id' => $memory->id,
            ]);
            throw new RuntimeException('Failed to store in pgvector: '.$e->getMessage());
        }
    }

    /**
     * Search for similar vectors using PostgreSQL pgvector extension.
     */
    public function search(
        string $agentName,
        array $queryEmbedding,
        string $namespace = 'default',
        int $limit = 5,
        float $threshold = 0.7
    ): Collection {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            throw new RuntimeException('PgVector driver requires PostgreSQL database connection');
        }

        try {
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

            $collection = collect($results)->map(function ($result) {
                $result->metadata = json_decode($result->metadata, true);
                return $result;
            });

            Log::debug('PgVector search completed', [
                'agent_name' => $agentName,
                'results_count' => $collection->count(),
                'query_dimensions' => count($queryEmbedding),
            ]);

            return $collection;

        } catch (\Exception $e) {
            Log::error('PgVector search failed', [
                'error' => $e->getMessage(),
                'agent_name' => $agentName,
            ]);
            throw new RuntimeException('PgVector search failed: '.$e->getMessage());
        }
    }

    /**
     * Delete memories from PostgreSQL.
     */
    public function delete(string $agentName, string $namespace = 'default', ?string $source = null): int
    {
        try {
            $query = VectorMemory::forAgent($agentName)->inNamespace($namespace);

            if ($source) {
                $query->fromSource($source);
            }

            $count = $query->delete();

            Log::info('Deleted documents from pgvector', [
                'agent_name' => $agentName,
                'namespace' => $namespace,
                'source' => $source,
                'deleted_count' => $count,
            ]);

            return $count;

        } catch (\Exception $e) {
            Log::error('Failed to delete from pgvector', [
                'error' => $e->getMessage(),
                'agent_name' => $agentName,
            ]);
            throw new RuntimeException('PgVector deletion failed: '.$e->getMessage());
        }
    }

    /**
     * Get statistics for an agent/namespace.
     */
    public function getStatistics(string $agentName, string $namespace = 'default'): array
    {
        try {
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

        } catch (\Exception $e) {
            Log::error('Failed to get pgvector statistics', [
                'error' => $e->getMessage(),
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
     * Check if pgvector is available.
     */
    public function isAvailable(): bool
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return false;
        }

        try {
            // Check if pgvector extension is installed
            $result = DB::select('SELECT 1 FROM pg_extension WHERE extname = ?', ['vector']);
            return !empty($result);
        } catch (\Exception $e) {
            return false;
        }
    }
}