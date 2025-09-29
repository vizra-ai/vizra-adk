<?php

namespace Vizra\VizraADK\Tools;

use Vizra\VizraADK\Contracts\ToolInterface;
use Vizra\VizraADK\Memory\AgentMemory;
use Vizra\VizraADK\Services\VectorMemoryManager;
use Vizra\VizraADK\System\AgentContext;
use Vizra\VizraADK\Traits\HasLogging;

class VectorMemoryTool implements ToolInterface
{
    use HasLogging;

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

    public function execute(array $arguments, AgentContext $context, AgentMemory $memory): string
    {
        $action = $arguments['action'];
        // Get agent class from context, fallback to a generic class if not available
        $agentClass = $context->getState('agent_class') ?? $context->getState('agent_name') ?? 'VizraADK\\GenericAgent';
        $namespace = $arguments['namespace'] ?? 'default';

        try {
            return match ($action) {
                'store' => $this->handleStore($agentClass, $arguments, $context),
                'search' => $this->handleSearch($agentClass, $arguments, $context),
                'delete' => $this->handleDelete($agentClass, $arguments, $context),
                'stats' => $this->handleStats($agentClass, $arguments, $context),
                default => json_encode([
                    'success' => false,
                    'error' => "Unknown action: {$action}. Use 'store', 'search', 'delete', or 'stats'.",
                ]),
            };
        } catch (\Exception $e) {
            $this->logError('VectorMemoryTool execution failed', [
                'action' => $action,
                'agent_class' => $agentClass,
                'error' => $e->getMessage(),
            ], 'vector_memory');

            return json_encode([
                'success' => false,
                'error' => 'Vector memory operation failed: '.$e->getMessage(),
            ]);
        }
    }

    protected function handleStore(string $agentClass, array $arguments, AgentContext $context): string
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
        $metadata['stored_by_agent'] = $agentClass;
        $metadata['stored_at'] = now()->toISOString();

        if ($sessionId = $context->getSessionId()) {
            $metadata['session_id'] = $sessionId;
        }

        // Use new API format
        $memories = $this->vectorMemory->addDocument(
            $agentClass,
            [
                'content' => $content,
                'metadata' => $metadata,
                'namespace' => $namespace,
                'source' => $source,
                'source_id' => $sourceId
            ]
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

    protected function handleSearch(string $agentClass, array $arguments, AgentContext $context): string
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
            // Generate formatted RAG context using new API
            $ragContext = $this->vectorMemory->generateRagContext(
                $agentClass,
                $query,
                [
                    'namespace' => $namespace,
                    'limit' => $limit,
                    'threshold' => $threshold
                ]
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
            // Return raw search results using new API
            $results = $this->vectorMemory->search(
                $agentClass,
                [
                    'query' => $query,
                    'namespace' => $namespace,
                    'limit' => $limit,
                    'threshold' => $threshold
                ]
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

    protected function handleDelete(string $agentClass, array $arguments, AgentContext $context): string
    {
        $namespace = $arguments['namespace'] ?? 'default';
        $source = $arguments['source'] ?? null;

        if ($source) {
            // Delete by source using new API
            $count = $this->vectorMemory->deleteMemoriesBySource(
                $agentClass,
                [
                    'source' => $source,
                    'namespace' => $namespace
                ]
            );
            $message = "Deleted {$count} memories from source '{$source}'";
        } else {
            // Delete all memories in namespace using new API
            $count = $this->vectorMemory->deleteMemories($agentClass, $namespace);
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

    protected function handleStats(string $agentClass, array $arguments, AgentContext $context): string
    {
        $namespace = $arguments['namespace'] ?? 'default';
        $stats = $this->vectorMemory->getStatistics($agentClass, $namespace);

        return json_encode([
            'success' => true,
            'action' => 'stats',
            'namespace' => $namespace,
            'statistics' => $stats,
            'message' => "Retrieved statistics for namespace '{$namespace}'",
        ]);
    }
}
