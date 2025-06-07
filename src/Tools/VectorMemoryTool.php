<?php

namespace AaronLumsden\LaravelAiADK\Tools;

use AaronLumsden\LaravelAiADK\Contracts\ToolInterface;
use AaronLumsden\LaravelAiADK\System\AgentContext;
use AaronLumsden\LaravelAiADK\Services\VectorMemoryManager;
use Illuminate\Support\Facades\Log;

class VectorMemoryTool implements ToolInterface
{
    protected VectorMemoryManager $vectorMemory;

    public function __construct(VectorMemoryManager $vectorMemory)
    {
        $this->vectorMemory = $vectorMemory;
    }

    public function definition(): array
    {
        return [
            'name' => 'vector_memory',
            'description' => 'Store, search, and retrieve information using semantic vector search. Perfect for RAG (Retrieval-Augmented Generation) workflows.',
            'parameters' => [
                'type' => 'object',
                'properties' => [
                    'action' => [
                        'type' => 'string',
                        'enum' => ['store', 'search', 'delete', 'stats'],
                        'description' => 'Action to perform: store content, search for relevant information, delete memories, or get statistics',
                    ],
                    'content' => [
                        'type' => 'string',
                        'description' => 'Text content to store (required for store action)',
                    ],
                    'query' => [
                        'type' => 'string',
                        'description' => 'Search query to find relevant information (required for search action)',
                    ],
                    'namespace' => [
                        'type' => 'string',
                        'description' => 'Memory namespace for organization (default: "default")',
                        'default' => 'default',
                    ],
                    'metadata' => [
                        'type' => 'object',
                        'description' => 'Additional metadata to store with content (optional)',
                    ],
                    'source' => [
                        'type' => 'string',
                        'description' => 'Source identifier (e.g., filename, URL, document title)',
                    ],
                    'source_id' => [
                        'type' => 'string',
                        'description' => 'External source ID for reference',
                    ],
                    'limit' => [
                        'type' => 'integer',
                        'description' => 'Maximum number of search results (default: 5)',
                        'default' => 5,
                        'minimum' => 1,
                        'maximum' => 20,
                    ],
                    'threshold' => [
                        'type' => 'number',
                        'description' => 'Similarity threshold for search results (default: 0.7)',
                        'default' => 0.7,
                        'minimum' => 0.0,
                        'maximum' => 1.0,
                    ],
                    'generate_rag_context' => [
                        'type' => 'boolean',
                        'description' => 'Whether to generate formatted RAG context for search results (default: false)',
                        'default' => false,
                    ],
                ],
                'required' => ['action'],
            ],
        ];
    }

    public function execute(array $arguments, AgentContext $context): string
    {
        $action = $arguments['action'];
        $agentName = $context->getState('agent_name') ?? 'unknown';
        $namespace = $arguments['namespace'] ?? 'default';

        try {
            return match ($action) {
                'store' => $this->handleStore($agentName, $arguments, $context),
                'search' => $this->handleSearch($agentName, $arguments, $context),
                'delete' => $this->handleDelete($agentName, $arguments, $context),
                'stats' => $this->handleStats($agentName, $arguments, $context),
                default => json_encode([
                    'success' => false,
                    'error' => "Unknown action: {$action}. Use 'store', 'search', 'delete', or 'stats'.",
                ]),
            };
        } catch (\Exception $e) {
            Log::error('VectorMemoryTool execution failed', [
                'action' => $action,
                'agent_name' => $agentName,
                'error' => $e->getMessage(),
            ]);

            return json_encode([
                'success' => false,
                'error' => 'Vector memory operation failed: ' . $e->getMessage(),
            ]);
        }
    }

    protected function handleStore(string $agentName, array $arguments, AgentContext $context): string
    {
        if (empty($arguments['content'])) {
            return json_encode([
                'success' => false,
                'error' => 'Content is required for store action',
            ]);
        }

        $content = $arguments['content'];
        $namespace = $arguments['namespace'] ?? 'default';
        $metadata = $arguments['metadata'] ?? [];
        $source = $arguments['source'] ?? null;
        $sourceId = $arguments['source_id'] ?? null;

        // Add context metadata
        $metadata['stored_by_agent'] = $agentName;
        $metadata['stored_at'] = now()->toISOString();
        
        if ($sessionId = $context->getSessionId()) {
            $metadata['session_id'] = $sessionId;
        }

        $memories = $this->vectorMemory->addDocument(
            agentName: $agentName,
            content: $content,
            metadata: $metadata,
            namespace: $namespace,
            source: $source,
            sourceId: $sourceId
        );

        return json_encode([
            'success' => true,
            'action' => 'store',
            'chunks_created' => $memories->count(),
            'namespace' => $namespace,
            'source' => $source,
            'content_length' => strlen($content),
            'message' => "Successfully stored {$memories->count()} chunks in vector memory",
        ]);
    }

    protected function handleSearch(string $agentName, array $arguments, AgentContext $context): string
    {
        if (empty($arguments['query'])) {
            return json_encode([
                'success' => false,
                'error' => 'Query is required for search action',
            ]);
        }


        $query = $arguments['query'];
        $namespace = $arguments['namespace'] ?? 'default';
        $limit = $arguments['limit'] ?? 5;
        $threshold = $arguments['threshold'] ?? 0.7;
        $generateRagContext = $arguments['generate_rag_context'] ?? false;

        if ($generateRagContext) {
            // Generate formatted RAG context
            $ragContext = $this->vectorMemory->generateRagContext(
                agentName: $agentName,
                query: $query,
                namespace: $namespace,
                limit: $limit,
                threshold: $threshold
            );

            return json_encode([
                'success' => true,
                'action' => 'search',
                'query' => $query,
                'namespace' => $namespace,
                'rag_context' => $ragContext['context'],
                'sources' => $ragContext['sources'],
                'total_results' => $ragContext['total_results'],
                'message' => "Found {$ragContext['total_results']} relevant results",
            ]);
        } else {
            // Return raw search results
            $results = $this->vectorMemory->search(
                agentName: $agentName,
                query: $query,
                namespace: $namespace,
                limit: $limit,
                threshold: $threshold
            );

            return json_encode([
                'success' => true,
                'action' => 'search',
                'query' => $query,
                'namespace' => $namespace,
                'results' => $results->map(function ($result) {
                    return [
                        'id' => $result->id,
                        'content' => $result->content,
                        'similarity' => $result->similarity ?? null,
                        'metadata' => $result->metadata,
                        'source' => $result->source,
                        'source_id' => $result->source_id,
                        'created_at' => $result->created_at,
                    ];
                })->toArray(),
                'total_results' => $results->count(),
                'message' => "Found {$results->count()} relevant results",
            ]);
        }
    }

    protected function handleDelete(string $agentName, array $arguments, AgentContext $context): string
    {
        $namespace = $arguments['namespace'] ?? 'default';
        $source = $arguments['source'] ?? null;

        if ($source) {
            // Delete by source
            $count = $this->vectorMemory->deleteMemoriesBySource($agentName, $source, $namespace);
            $message = "Deleted {$count} memories from source '{$source}'";
        } else {
            // Delete all memories in namespace
            $count = $this->vectorMemory->deleteMemories($agentName, $namespace);
            $message = "Deleted {$count} memories from namespace '{$namespace}'";
        }

        return json_encode([
            'success' => true,
            'action' => 'delete',
            'namespace' => $namespace,
            'source' => $source,
            'deleted_count' => $count,
            'message' => $message,
        ]);
    }

    protected function handleStats(string $agentName, array $arguments, AgentContext $context): string
    {
        $namespace = $arguments['namespace'] ?? 'default';
        $stats = $this->vectorMemory->getStatistics($agentName, $namespace);

        return json_encode([
            'success' => true,
            'action' => 'stats',
            'namespace' => $namespace,
            'statistics' => $stats,
            'message' => "Retrieved statistics for namespace '{$namespace}'",
        ]);
    }
}