<?php

namespace Vizra\VizraADK\Tests\Unit\VectorMemory;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Collection;
use Mockery;
use RuntimeException;
use Vizra\VizraADK\Contracts\EmbeddingProviderInterface;
use Vizra\VizraADK\Models\VectorMemory;
use Vizra\VizraADK\Services\DocumentChunker;
use Vizra\VizraADK\Services\VectorMemoryManager;
use Vizra\VizraADK\Tests\TestCase;
use Vizra\VizraADK\Tests\Fixtures\TestAgent;

class VectorMemoryManagerTest extends TestCase
{
    use DatabaseTransactions;

    protected VectorMemoryManager $vectorMemoryManager;

    protected $mockEmbeddingProvider;

    protected $mockChunker;

    protected function setUp(): void
    {
        parent::setUp();

        // Mock embedding provider
        $this->mockEmbeddingProvider = Mockery::mock(EmbeddingProviderInterface::class);
        $this->mockEmbeddingProvider->shouldReceive('getProviderName')->andReturn('mock');
        $this->mockEmbeddingProvider->shouldReceive('getModel')->andReturn('mock-model');
        $this->mockEmbeddingProvider->shouldReceive('getDimensions')->andReturn(384);

        // Mock document chunker
        $this->mockChunker = Mockery::mock(DocumentChunker::class);

        $this->vectorMemoryManager = new VectorMemoryManager(
            $this->mockEmbeddingProvider,
            $this->mockChunker
        );
    }

    public function test_can_add_document_with_chunking()
    {
        // Arrange
        $agentClass = TestAgent::class;
        $agentName = 'test_agent'; // Expected agent name from class
        $content = 'This is a test document that will be chunked and stored.';
        $chunks = ['This is a test document', 'that will be chunked and stored.'];
        $mockEmbedding = array_fill(0, 384, 0.1);

        $this->mockChunker->shouldReceive('chunk')
            ->with($content)
            ->once()
            ->andReturn($chunks);

        $this->mockEmbeddingProvider->shouldReceive('embed')
            ->twice()
            ->andReturn([$mockEmbedding]);

        // Act - Test simple string format
        $result = $this->vectorMemoryManager->addDocument(
            $agentClass,
            $content,
            ['source' => 'test']
        );

        // Assert
        $this->assertInstanceOf(Collection::class, $result);
        $this->assertCount(2, $result);

        // Check database entries
        $this->assertDatabaseHas('agent_vector_memories', [
            'agent_name' => $agentName,
            'namespace' => 'default',
            'content' => $chunks[0],
        ]);

        $this->assertDatabaseHas('agent_vector_memories', [
            'agent_name' => $agentName,
            'namespace' => 'default',
            'content' => $chunks[1],
        ]);
    }

    public function test_can_add_single_chunk()
    {
        // Arrange
        $agentClass = TestAgent::class;
        $agentName = 'test_agent';
        $content = 'Single chunk content';
        $mockEmbedding = array_fill(0, 384, 0.1);

        $this->mockEmbeddingProvider->shouldReceive('embed')
            ->with($content)
            ->once()
            ->andReturn([$mockEmbedding]);

        // Act - Test with metadata
        $result = $this->vectorMemoryManager->addChunk(
            $agentClass,
            $content,
            ['type' => 'test']
        );

        // Assert
        $this->assertInstanceOf(VectorMemory::class, $result);
        $this->assertEquals($agentName, $result->agent_name);
        $this->assertEquals($content, $result->content);
        $this->assertEquals(['type' => 'test'], $result->metadata);
        $this->assertEquals('mock', $result->embedding_provider);
        $this->assertEquals('mock-model', $result->embedding_model);
        $this->assertEquals(384, $result->embedding_dimensions);
    }

    public function test_throws_exception_when_embedding_provider_returns_empty_embedding()
    {
        // Arrange
        $agentClass = TestAgent::class;
        $content = 'Problematic content';

        $this->mockEmbeddingProvider->shouldReceive('embed')
            ->with($content)
            ->once()
            ->andReturn([]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Failed to add content to vector memory: Embedding provider returned empty embedding data.');

        // Act
        $this->vectorMemoryManager->addChunk($agentClass, $content);
    }

    public function test_prevents_duplicate_content()
    {
        // Arrange
        $agentClass = TestAgent::class;
        $content = 'Duplicate content';
        $mockEmbedding = array_fill(0, 384, 0.1);

        $this->mockEmbeddingProvider->shouldReceive('embed')
            ->once()
            ->andReturn([$mockEmbedding]);

        // Act - Add content first time
        $firstResult = $this->vectorMemoryManager->addChunk(
            $agentClass,
            $content
        );

        // Act - Add same content second time
        $secondResult = $this->vectorMemoryManager->addChunk(
            $agentClass,
            $content
        );

        // Assert
        $this->assertEquals($firstResult->id, $secondResult->id);
        $this->assertDatabaseCount('agent_vector_memories', 1);
    }

    public function test_can_search_with_cosine_similarity()
    {
        // Arrange
        $agentName = 'test_agent';
        $namespace = 'test_namespace';
        $query = 'test query';
        $queryEmbedding = array_fill(0, 384, 0.5);

        // Create test memories with similar embeddings
        $memory1 = VectorMemory::create([
            'agent_name' => $agentName,
            'namespace' => $namespace,
            'content' => 'Similar content one',
            'embedding_provider' => 'mock',
            'embedding_model' => 'mock-model',
            'embedding_dimensions' => 384,
            'embedding_vector' => array_fill(0, 384, 0.4), // Similar to query
            'embedding_norm' => 1.0,
            'content_hash' => hash('sha256', $agentName.':Similar content one'),
            'token_count' => 10,
        ]);

        $memory2 = VectorMemory::create([
            'agent_name' => $agentName,
            'namespace' => $namespace,
            'content' => 'Different content',
            'embedding_provider' => 'mock',
            'embedding_model' => 'mock-model',
            'embedding_dimensions' => 384,
            'embedding_vector' => array_fill(0, 384, 0.1), // Less similar to query
            'embedding_norm' => 1.0,
            'content_hash' => hash('sha256', $agentName.':Different content'),
            'token_count' => 8,
        ]);

        $this->mockEmbeddingProvider->shouldReceive('embed')
            ->with($query)
            ->once()
            ->andReturn([$queryEmbedding]);

        // Act - Test with array format
        $results = $this->vectorMemoryManager->search(
            TestAgent::class,
            [
                'query' => $query,
                'namespace' => $namespace,
                'limit' => 5,
                'threshold' => 0.5
            ]
        );

        // Assert
        $this->assertInstanceOf(Collection::class, $results);
        $this->assertGreaterThan(0, $results->count());

        // First result should be more similar
        $firstResult = $results->first();
        $this->assertEquals('Similar content one', $firstResult->content);
        $this->assertGreaterThan(0.5, $firstResult->similarity);
    }

    public function test_can_generate_rag_context()
    {
        // Arrange
        $agentName = 'test_agent';
        $namespace = 'test_namespace';
        $query = 'What is the capital of France?';
        $queryEmbedding = array_fill(0, 384, 0.5);

        // Create relevant memory
        VectorMemory::create([
            'agent_name' => $agentName,
            'namespace' => $namespace,
            'content' => 'Paris is the capital and most populous city of France.',
            'metadata' => ['source' => 'geography_book.pdf', 'page' => 42],
            'source' => 'geography_book.pdf',
            'embedding_provider' => 'mock',
            'embedding_model' => 'mock-model',
            'embedding_dimensions' => 384,
            'embedding_vector' => array_fill(0, 384, 0.6),
            'embedding_norm' => 1.0,
            'content_hash' => hash('sha256', $agentName.':Paris is the capital'),
            'token_count' => 10,
        ]);

        $this->mockEmbeddingProvider->shouldReceive('embed')
            ->with($query)
            ->once()
            ->andReturn([$queryEmbedding]);

        // Act - Test simple string format
        $ragContext = $this->vectorMemoryManager->generateRagContext(
            TestAgent::class,
            $query,
            ['namespace' => $namespace]
        );

        // Assert
        $this->assertIsArray($ragContext);
        $this->assertArrayHasKey('context', $ragContext);
        $this->assertArrayHasKey('sources', $ragContext);
        $this->assertArrayHasKey('query', $ragContext);
        $this->assertArrayHasKey('total_results', $ragContext);

        $this->assertStringContainsString('Paris is the capital', $ragContext['context']);
        $this->assertEquals($query, $ragContext['query']);
        $this->assertGreaterThan(0, $ragContext['total_results']);
        $this->assertCount(1, $ragContext['sources']);
    }

    public function test_can_delete_memories_by_namespace()
    {
        // Arrange
        $agentName = 'test_agent';
        $namespace = 'test_namespace';

        VectorMemory::create([
            'agent_name' => $agentName,
            'namespace' => $namespace,
            'content' => 'Content to delete',
            'embedding_provider' => 'mock',
            'embedding_model' => 'mock-model',
            'embedding_dimensions' => 384,
            'embedding_vector' => [],
            'content_hash' => hash('sha256', $agentName.':Content to delete'),
            'token_count' => 5,
        ]);

        VectorMemory::create([
            'agent_name' => $agentName,
            'namespace' => 'other_namespace',
            'content' => 'Content to keep',
            'embedding_provider' => 'mock',
            'embedding_model' => 'mock-model',
            'embedding_dimensions' => 384,
            'embedding_vector' => [],
            'content_hash' => hash('sha256', $agentName.':Content to keep'),
            'token_count' => 5,
        ]);

        // Act
        $deletedCount = $this->vectorMemoryManager->deleteMemories(TestAgent::class, $namespace);

        // Assert
        $this->assertEquals(1, $deletedCount);
        $this->assertDatabaseMissing('agent_vector_memories', [
            'agent_name' => $agentName,
            'namespace' => $namespace,
        ]);
        $this->assertDatabaseHas('agent_vector_memories', [
            'agent_name' => $agentName,
            'namespace' => 'other_namespace',
        ]);
    }

    public function test_can_delete_memories_by_source()
    {
        // Arrange
        $agentName = 'test_agent';
        $namespace = 'test_namespace';
        $source = 'document.pdf';

        VectorMemory::create([
            'agent_name' => $agentName,
            'namespace' => $namespace,
            'source' => $source,
            'content' => 'Content from document',
            'embedding_provider' => 'mock',
            'embedding_model' => 'mock-model',
            'embedding_dimensions' => 384,
            'embedding_vector' => [],
            'content_hash' => hash('sha256', $agentName.':Content from document'),
            'token_count' => 5,
        ]);

        VectorMemory::create([
            'agent_name' => $agentName,
            'namespace' => $namespace,
            'source' => 'other_document.pdf',
            'content' => 'Content from other document',
            'embedding_provider' => 'mock',
            'embedding_model' => 'mock-model',
            'embedding_dimensions' => 384,
            'embedding_vector' => [],
            'content_hash' => hash('sha256', $agentName.':Content from other document'),
            'token_count' => 5,
        ]);

        // Act
        $deletedCount = $this->vectorMemoryManager->deleteMemoriesBySource(TestAgent::class, $source, $namespace);

        // Assert
        $this->assertEquals(1, $deletedCount);
        $this->assertDatabaseMissing('agent_vector_memories', [
            'agent_name' => $agentName,
            'source' => $source,
        ]);
        $this->assertDatabaseHas('agent_vector_memories', [
            'agent_name' => $agentName,
            'source' => 'other_document.pdf',
        ]);
    }

    public function test_can_get_statistics()
    {
        // Arrange
        $agentName = 'test_agent';
        $namespace = 'test_namespace';

        VectorMemory::create([
            'agent_name' => $agentName,
            'namespace' => $namespace,
            'content' => 'First memory',
            'source' => 'doc1.pdf',
            'embedding_provider' => 'openai',
            'embedding_model' => 'text-embedding-3-small',
            'embedding_dimensions' => 1536,
            'embedding_vector' => [],
            'content_hash' => hash('sha256', $agentName.':First memory'),
            'token_count' => 10,
        ]);

        VectorMemory::create([
            'agent_name' => $agentName,
            'namespace' => $namespace,
            'content' => 'Second memory',
            'source' => 'doc2.pdf',
            'embedding_provider' => 'cohere',
            'embedding_model' => 'embed-english-v3.0',
            'embedding_dimensions' => 1024,
            'embedding_vector' => [],
            'content_hash' => hash('sha256', $agentName.':Second memory'),
            'token_count' => 15,
        ]);

        // Act
        $stats = $this->vectorMemoryManager->getStatistics(TestAgent::class, $namespace);

        // Assert
        $this->assertIsArray($stats);
        $this->assertEquals(2, $stats['total_memories']);
        $this->assertEquals(25, $stats['total_tokens']);
        $this->assertArrayHasKey('providers', $stats);
        $this->assertArrayHasKey('sources', $stats);
        $this->assertEquals(1, $stats['providers']['openai']);
        $this->assertEquals(1, $stats['providers']['cohere']);
        $this->assertEquals(1, $stats['sources']['doc1.pdf']);
        $this->assertEquals(1, $stats['sources']['doc2.pdf']);
    }

    public function test_handles_empty_content()
    {
        // Arrange
        $agentName = 'test_agent';
        $emptyContent = '';

        // Act
        $result = $this->vectorMemoryManager->addChunk(TestAgent::class, $emptyContent);

        // Assert
        $this->assertNull($result);
        $this->assertDatabaseCount('agent_vector_memories', 0);
    }

    public function test_handles_whitespace_only_content()
    {
        // Arrange
        $agentName = 'test_agent';
        $whitespaceContent = "   \n\t   ";

        // Act
        $result = $this->vectorMemoryManager->addChunk($agentName, $whitespaceContent);

        // Assert
        $this->assertNull($result);
        $this->assertDatabaseCount('agent_vector_memories', 0);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_simplified_api_patterns()
    {
        // Arrange
        $agentClass = TestAgent::class;
        $agentName = 'test_agent';
        $content = 'Test content for simplified API';
        $mockEmbedding = array_fill(0, 384, 0.1);

        $this->mockEmbeddingProvider->shouldReceive('embed')
            ->andReturn([$mockEmbedding]);

        // Test 1: Simple string for addChunk
        $result1 = $this->vectorMemoryManager->addChunk($agentClass, $content);
        $this->assertInstanceOf(VectorMemory::class, $result1);
        $this->assertEquals($content, $result1->content);
        $this->assertEquals('default', $result1->namespace);

        // Test 2: Array format for addChunk
        $result2 = $this->vectorMemoryManager->addChunk($agentClass, [
            'content' => 'Array format content',
            'metadata' => ['type' => 'test'],
            'namespace' => 'custom'
        ]);
        $this->assertInstanceOf(VectorMemory::class, $result2);
        $this->assertEquals('Array format content', $result2->content);
        $this->assertEquals('custom', $result2->namespace);
        $this->assertEquals(['type' => 'test'], $result2->metadata);

        // Test 3: Simple search with limit
        $searchResults = $this->vectorMemoryManager->search($agentClass, 'test query', 3);
        $this->assertInstanceOf(Collection::class, $searchResults);

        // Test 4: DeleteMemories with null (default namespace)
        $deleteCount = $this->vectorMemoryManager->deleteMemories($agentClass);
        $this->assertIsInt($deleteCount);

        // Test 5: GetStatistics with array format
        $stats = $this->vectorMemoryManager->getStatistics($agentClass, ['namespace' => 'custom']);
        $this->assertIsArray($stats);
        $this->assertArrayHasKey('total_memories', $stats);
    }
}
