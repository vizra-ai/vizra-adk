<?php

namespace Vizra\VizraADK\Tests\Unit\Agents;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Collection;
use Mockery;
use Vizra\VizraADK\Agents\BaseLlmAgent;
use Vizra\VizraADK\Contracts\EmbeddingProviderInterface;
use Vizra\VizraADK\Models\VectorMemory;
use Vizra\VizraADK\Services\DocumentChunker;
use Vizra\VizraADK\Services\VectorMemoryManager;
use Vizra\VizraADK\Tests\TestCase;

/**
 * Comprehensive tests for the vector() and rag() methods in BaseLlmAgent
 */
class AgentVectorMemoryTest extends TestCase
{
    use DatabaseTransactions;

    protected TestVectorAgent $agent;

    protected $mockVectorMemoryManager;

    protected $mockEmbeddingProvider;

    protected $mockChunker;

    protected function setUp(): void
    {
        parent::setUp();

        // Create a test agent instance
        $this->agent = new TestVectorAgent;

        // Set up mocks for VectorMemoryManager dependencies
        $this->mockEmbeddingProvider = Mockery::mock(EmbeddingProviderInterface::class);
        $this->mockEmbeddingProvider->shouldReceive('getProviderName')->andReturn('test-provider');
        $this->mockEmbeddingProvider->shouldReceive('getModel')->andReturn('test-model');
        $this->mockEmbeddingProvider->shouldReceive('getDimensions')->andReturn(384);

        $this->mockChunker = Mockery::mock(DocumentChunker::class);

        // Create a real VectorMemoryManager instance with mocked dependencies
        $this->mockVectorMemoryManager = new VectorMemoryManager(
            $this->mockEmbeddingProvider,
            $this->mockChunker
        );

        // Bind the VectorMemoryManager to the container
        $this->app->instance(VectorMemoryManager::class, $this->mockVectorMemoryManager);
    }

    /**
     * Test that vector() method returns VectorMemoryManager instance
     */
    public function test_vector_method_returns_vector_memory_manager_instance()
    {
        $vectorManager = $this->agent->testVector();

        $this->assertInstanceOf(VectorMemoryManager::class, $vectorManager);
        $this->assertSame($this->mockVectorMemoryManager, $vectorManager);
    }

    /**
     * Test that rag() method returns the same instance as vector()
     */
    public function test_rag_method_returns_same_instance_as_vector()
    {
        $vectorManager = $this->agent->testVector();
        $ragManager = $this->agent->testRag();

        $this->assertInstanceOf(VectorMemoryManager::class, $ragManager);
        $this->assertSame($vectorManager, $ragManager);
        $this->assertSame($this->mockVectorMemoryManager, $ragManager);
    }

    /**
     * Test that multiple calls to vector() return the same instance
     */
    public function test_multiple_vector_calls_return_same_instance()
    {
        $firstCall = $this->agent->testVector();
        $secondCall = $this->agent->testVector();
        $thirdCall = $this->agent->testVector();

        $this->assertSame($firstCall, $secondCall);
        $this->assertSame($secondCall, $thirdCall);
        $this->assertSame($this->mockVectorMemoryManager, $firstCall);
    }

    /**
     * Test storing a document using vector() method
     */
    public function test_can_store_document_using_vector_method()
    {
        // Arrange
        $content = 'This is important information about Laravel.';
        $chunks = ['This is important information', 'about Laravel.'];
        $mockEmbedding = array_fill(0, 384, 0.1);

        $this->mockChunker->shouldReceive('chunk')
            ->with($content)
            ->once()
            ->andReturn($chunks);

        $this->mockEmbeddingProvider->shouldReceive('embed')
            ->twice()
            ->andReturn([$mockEmbedding]);

        // Act
        $result = $this->agent->storeDocument($content, ['type' => 'documentation']);

        // Assert
        $this->assertInstanceOf(Collection::class, $result);
        $this->assertCount(2, $result);

        // Verify database entries
        $this->assertDatabaseHas('agent_vector_memories', [
            'agent_name' => $this->agent->getName(),
            'content' => $chunks[0],
        ]);

        $this->assertDatabaseHas('agent_vector_memories', [
            'agent_name' => $this->agent->getName(),
            'content' => $chunks[1],
        ]);
    }

    /**
     * Test searching documents using rag() method
     */
    public function test_can_search_documents_using_rag_method()
    {
        // Arrange
        $query = 'How to use Laravel?';
        $queryEmbedding = array_fill(0, 384, 0.5);

        // Create test memory
        VectorMemory::create([
            'agent_name' => $this->agent->getName(),
            'namespace' => 'default',
            'content' => 'Laravel is a PHP framework for web artisans.',
            'embedding_provider' => 'test-provider',
            'embedding_model' => 'test-model',
            'embedding_dimensions' => 384,
            'embedding_vector' => array_fill(0, 384, 0.4),
            'embedding_norm' => 1.0,
            'content_hash' => hash('sha256', $this->agent->getName().':Laravel is a PHP framework'),
            'token_count' => 10,
        ]);

        $this->mockEmbeddingProvider->shouldReceive('embed')
            ->with($query)
            ->once()
            ->andReturn([$queryEmbedding]);

        // Act
        $results = $this->agent->searchDocuments($query);

        // Assert
        $this->assertInstanceOf(Collection::class, $results);
        $this->assertGreaterThan(0, $results->count());
        $this->assertStringContainsString('Laravel', $results->first()->content);
    }

    /**
     * Test generating RAG context using rag() method
     */
    public function test_can_generate_rag_context()
    {
        // Arrange
        $query = 'What is dependency injection?';
        $queryEmbedding = array_fill(0, 384, 0.5);

        // Create relevant memories
        VectorMemory::create([
            'agent_name' => $this->agent->getName(),
            'namespace' => 'knowledge',
            'content' => 'Dependency injection is a design pattern used to implement IoC.',
            'metadata' => ['source' => 'design_patterns.pdf', 'page' => 15],
            'source' => 'design_patterns.pdf',
            'embedding_provider' => 'test-provider',
            'embedding_model' => 'test-model',
            'embedding_dimensions' => 384,
            'embedding_vector' => array_fill(0, 384, 0.6),
            'embedding_norm' => 1.0,
            'content_hash' => hash('sha256', $this->agent->getName().':Dependency injection'),
            'token_count' => 15,
        ]);

        VectorMemory::create([
            'agent_name' => $this->agent->getName(),
            'namespace' => 'knowledge',
            'content' => 'In Laravel, dependency injection is handled by the service container.',
            'metadata' => ['source' => 'laravel_docs.md', 'section' => 'container'],
            'source' => 'laravel_docs.md',
            'embedding_provider' => 'test-provider',
            'embedding_model' => 'test-model',
            'embedding_dimensions' => 384,
            'embedding_vector' => array_fill(0, 384, 0.7),
            'embedding_norm' => 1.0,
            'content_hash' => hash('sha256', $this->agent->getName().':In Laravel'),
            'token_count' => 12,
        ]);

        $this->mockEmbeddingProvider->shouldReceive('embed')
            ->with($query)
            ->once()
            ->andReturn([$queryEmbedding]);

        // Act
        $ragContext = $this->agent->generateContext($query, 'knowledge');

        // Assert
        $this->assertIsArray($ragContext);
        $this->assertArrayHasKey('context', $ragContext);
        $this->assertArrayHasKey('sources', $ragContext);
        $this->assertArrayHasKey('query', $ragContext);
        $this->assertArrayHasKey('total_results', $ragContext);

        $this->assertStringContainsString('dependency injection', $ragContext['context']);
        $this->assertStringContainsString('Laravel', $ragContext['context']);
        $this->assertEquals($query, $ragContext['query']);
        $this->assertGreaterThanOrEqual(1, $ragContext['total_results']);
    }

    /**
     * Test that agent can use different namespaces for organization
     */
    public function test_can_use_namespaces_for_organization()
    {
        // Arrange
        $mockEmbedding = array_fill(0, 384, 0.1);

        $this->mockEmbeddingProvider->shouldReceive('embed')
            ->times(3)
            ->andReturn([$mockEmbedding]);

        // Act - Store in different namespaces
        $this->agent->storeInNamespace('facts', 'PHP is a programming language.');
        $this->agent->storeInNamespace('opinions', 'PHP is the best language.');
        $this->agent->storeInNamespace('facts', 'Laravel is a PHP framework.');

        // Assert - Check database entries
        $facts = VectorMemory::where('agent_name', $this->agent->getName())
            ->where('namespace', 'facts')
            ->get();

        $opinions = VectorMemory::where('agent_name', $this->agent->getName())
            ->where('namespace', 'opinions')
            ->get();

        $this->assertCount(2, $facts);
        $this->assertCount(1, $opinions);
    }

    /**
     * Test deleting memories by namespace
     */
    public function test_can_delete_memories_by_namespace()
    {
        // Arrange
        $mockEmbedding = array_fill(0, 384, 0.1);

        $this->mockEmbeddingProvider->shouldReceive('embed')
            ->times(3)
            ->andReturn([$mockEmbedding]);

        // Store memories in different namespaces
        $this->agent->storeInNamespace('temp', 'Temporary data 1');
        $this->agent->storeInNamespace('temp', 'Temporary data 2');
        $this->agent->storeInNamespace('permanent', 'Important data');

        // Act - Delete temporary namespace
        $deletedCount = $this->agent->clearNamespace('temp');

        // Assert
        $this->assertEquals(2, $deletedCount);

        $this->assertDatabaseMissing('agent_vector_memories', [
            'agent_name' => $this->agent->getName(),
            'namespace' => 'temp',
        ]);

        $this->assertDatabaseHas('agent_vector_memories', [
            'agent_name' => $this->agent->getName(),
            'namespace' => 'permanent',
        ]);
    }

    /**
     * Test getting statistics about stored vectors
     */
    public function test_can_get_vector_statistics()
    {
        // Arrange
        VectorMemory::create([
            'agent_name' => $this->agent->getName(),
            'namespace' => 'default',
            'content' => 'First memory',
            'source' => 'doc1.pdf',
            'embedding_provider' => 'openai',
            'embedding_model' => 'text-embedding-3-small',
            'embedding_dimensions' => 1536,
            'embedding_vector' => [],
            'content_hash' => hash('sha256', $this->agent->getName().':First memory'),
            'token_count' => 10,
        ]);

        VectorMemory::create([
            'agent_name' => $this->agent->getName(),
            'namespace' => 'default',
            'content' => 'Second memory',
            'source' => 'doc2.pdf',
            'embedding_provider' => 'openai',
            'embedding_model' => 'text-embedding-3-small',
            'embedding_dimensions' => 1536,
            'embedding_vector' => [],
            'content_hash' => hash('sha256', $this->agent->getName().':Second memory'),
            'token_count' => 15,
        ]);

        // Act
        $stats = $this->agent->getVectorStats();

        // Assert
        $this->assertIsArray($stats);
        $this->assertEquals(2, $stats['total_memories']);
        $this->assertEquals(25, $stats['total_tokens']);
        $this->assertArrayHasKey('providers', $stats);
        $this->assertArrayHasKey('sources', $stats);
    }

    /**
     * Test that vector methods are protected and not accessible from outside
     */
    public function test_vector_methods_are_protected()
    {
        $reflection = new \ReflectionClass(BaseLlmAgent::class);

        $vectorMethod = $reflection->getMethod('vector');
        $this->assertTrue($vectorMethod->isProtected());

        $ragMethod = $reflection->getMethod('rag');
        $this->assertTrue($ragMethod->isProtected());
    }

    /**
     * Test real-world RAG usage scenario
     */
    public function test_real_world_rag_usage_scenario()
    {
        // Arrange - Store knowledge base
        $knowledgeBase = [
            'Laravel uses the MVC architectural pattern.',
            'Controllers handle HTTP requests in Laravel.',
            'Models represent database tables and business logic.',
            'Views contain the presentation layer.',
            'Blade is the templating engine for Laravel.',
        ];

        $mockEmbedding = array_fill(0, 384, 0.1);
        $this->mockEmbeddingProvider->shouldReceive('embed')
            ->times(6) // 5 for storing + 1 for searching
            ->andReturn([$mockEmbedding]);

        foreach ($knowledgeBase as $knowledge) {
            $this->agent->getVector()->addChunk(
                $this->agent->getName(),
                $knowledge,
                ['type' => 'framework_knowledge'],
                'knowledge_base'
            );
        }

        // Act - Search for relevant information
        $query = 'How does Laravel handle requests?';
        $ragContext = $this->agent->getRag()->generateRagContext(
            $this->agent->getName(),
            $query,
            'knowledge_base',
            3 // Top 3 results
        );

        // Assert
        $this->assertArrayHasKey('context', $ragContext);
        $this->assertArrayHasKey('total_results', $ragContext);
        $this->assertGreaterThanOrEqual(1, $ragContext['total_results']);

        // The context should mention controllers since that's most relevant
        $this->assertStringContainsString('Controllers', $ragContext['context']);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}

/**
 * Test implementation of BaseLlmAgent with public methods to test protected vector/rag methods
 */
class TestVectorAgent extends BaseLlmAgent
{
    protected string $name = 'test-vector-agent';

    protected string $description = 'An agent for testing vector memory functionality';

    protected string $instructions = 'Test agent for vector memory operations';

    protected string $model = 'gpt-3.5-turbo';

    /**
     * Public wrapper to test protected vector() method
     */
    public function testVector(): VectorMemoryManager
    {
        return $this->vector();
    }

    /**
     * Public wrapper to test protected rag() method
     */
    public function testRag(): VectorMemoryManager
    {
        return $this->rag();
    }

    /**
     * Public wrapper to test protected vector() method - alternative name
     */
    public function getVector(): VectorMemoryManager
    {
        return $this->vector();
    }

    /**
     * Public wrapper to test protected rag() method - alternative name
     */
    public function getRag(): VectorMemoryManager
    {
        return $this->rag();
    }

    /**
     * Store a document using the vector() method
     */
    public function storeDocument(string $content, array $metadata = []): Collection
    {
        return $this->vector()->addDocument(
            $this->getName(),
            $content,
            $metadata
        );
    }

    /**
     * Search documents using the rag() method
     */
    public function searchDocuments(string $query, int $limit = 5): Collection
    {
        return $this->rag()->search(
            $this->getName(),
            $query,
            'default',
            $limit
        );
    }

    /**
     * Generate RAG context
     */
    public function generateContext(string $query, string $namespace = 'default'): array
    {
        return $this->rag()->generateRagContext(
            $this->getName(),
            $query,
            $namespace
        );
    }

    /**
     * Store content in a specific namespace
     */
    public function storeInNamespace(string $namespace, string $content): ?VectorMemory
    {
        return $this->vector()->addChunk(
            $this->getName(),
            $content,
            [],
            $namespace
        );
    }

    /**
     * Clear a namespace
     */
    public function clearNamespace(string $namespace): int
    {
        return $this->vector()->deleteMemories($this->getName(), $namespace);
    }

    /**
     * Get vector statistics
     */
    public function getVectorStats(string $namespace = 'default'): array
    {
        return $this->vector()->getStatistics($this->getName(), $namespace);
    }
}
