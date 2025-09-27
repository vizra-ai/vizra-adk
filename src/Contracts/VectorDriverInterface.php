<?php

namespace Vizra\VizraADK\Contracts;

use Illuminate\Support\Collection;
use Vizra\VizraADK\Models\VectorMemory;

interface VectorDriverInterface
{
    /**
     * Store a vector memory entry in the vector database.
     *
     * @param VectorMemory $memory The vector memory to store
     * @return bool True if stored successfully
     */
    public function store(VectorMemory $memory): bool;

    /**
     * Search for similar vectors in the vector database.
     *
     * @param string $agentName Agent name to search within
     * @param array $queryEmbedding The query embedding vector
     * @param string $namespace Namespace to search within
     * @param int $limit Maximum number of results to return
     * @param float $threshold Similarity threshold (0.0 - 1.0)
     * @return Collection Collection of search results
     */
    public function search(
        string $agentName,
        array $queryEmbedding,
        string $namespace = 'default',
        int $limit = 5,
        float $threshold = 0.7
    ): Collection;

    /**
     * Delete memories from the vector database.
     *
     * @param string $agentName Agent name
     * @param string $namespace Namespace to delete from
     * @param string|null $source Optional source filter
     * @return int Number of memories deleted
     */
    public function delete(string $agentName, string $namespace = 'default', ?string $source = null): int;

    /**
     * Get statistics for stored memories.
     *
     * @param string $agentName Agent name
     * @param string $namespace Namespace
     * @return array Statistics array
     */
    public function getStatistics(string $agentName, string $namespace = 'default'): array;

    /**
     * Check if the vector database is available and ready.
     *
     * @return bool True if available
     */
    public function isAvailable(): bool;
}