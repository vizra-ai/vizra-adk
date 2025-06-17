<?php

namespace Vizra\VizraAdk\Tests\Unit\VectorMemory;

use Vizra\VizraAdk\Tests\TestCase;
use Vizra\VizraAdk\Services\VectorMemoryManager;
use Vizra\VizraAdk\Services\DocumentChunker;
use Vizra\VizraAdk\Contracts\EmbeddingProviderInterface;
use Vizra\VizraAdk\Models\VectorMemory;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Collection;
use Mockery;

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
        $agentName = 'test_agent';
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

        // Act
        $result = $this->vectorMemoryManager->addDocument(
            agentName: $agentName,
            content: $content,
            metadata: ['source' => 'test'],
            namespace: 'test_namespace'
        );

        // Assert
        $this->assertInstanceOf(Collection::class, $result);
        $this->assertCount(2, $result);

        // Check database entries
        $this->assertDatabaseHas('agent_vector_memories', [
            'agent_name' => $agentName,
            'namespace' => 'test_namespace',
            'content' => $chunks[0],
        ]);

        $this->assertDatabaseHas('agent_vector_memories', [
            'agent_name' => $agentName,
            'namespace' => 'test_namespace',
            'content' => $chunks[1],
        ]);
    }

    public function test_can_add_single_chunk()
    {
        // Arrange
        $agentName = 'test_agent';
        $content = 'Single chunk content';
        $mockEmbedding = array_fill(0, 384, 0.1);

        $this->mockEmbeddingProvider->shouldReceive('embed')
            ->with($content)
            ->once()
            ->andReturn([$mockEmbedding]);

        // Act
        $result = $this->vectorMemoryManager->addChunk(
            agentName: $agentName,
            content: $content,
            metadata: ['type' => 'test'],
            namespace: 'default'
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

    public function test_prevents_duplicate_content()
    {
        // Arrange
        $agentName = 'test_agent';
        $content = 'Duplicate content';
        $mockEmbedding = array_fill(0, 384, 0.1);

        $this->mockEmbeddingProvider->shouldReceive('embed')
            ->once()
            ->andReturn([$mockEmbedding]);

        // Act - Add content first time
        $firstResult = $this->vectorMemoryManager->addChunk(
            agentName: $agentName,
            content: $content
        );

        // Act - Add same content second time
        $secondResult = $this->vectorMemoryManager->addChunk(
            agentName: $agentName,
            content: $content
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
            'content_hash' => hash('sha256', $agentName . ':Similar content one'),
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
            'content_hash' => hash('sha256', $agentName . ':Different content'),
            'token_count' => 8,
        ]);

        $this->mockEmbeddingProvider->shouldReceive('embed')
            ->with($query)
            ->once()
            ->andReturn([$queryEmbedding]);

        // Act
        $results = $this->vectorMemoryManager->search(
            agentName: $agentName,
            query: $query,
            namespace: $namespace,
            limit: 5,
            threshold: 0.5
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
            'content_hash' => hash('sha256', $agentName . ':Paris is the capital'),
            'token_count' => 10,
        ]);

        $this->mockEmbeddingProvider->shouldReceive('embed')
            ->with($query)
            ->once()
            ->andReturn([$queryEmbedding]);

        // Act
        $ragContext = $this->vectorMemoryManager->generateRagContext(
            agentName: $agentName,
            query: $query,
            namespace: $namespace
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
            'content_hash' => hash('sha256', $agentName . ':Content to delete'),
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
            'content_hash' => hash('sha256', $agentName . ':Content to keep'),
            'token_count' => 5,
        ]);

        // Act
        $deletedCount = $this->vectorMemoryManager->deleteMemories($agentName, $namespace);

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
            'content_hash' => hash('sha256', $agentName . ':Content from document'),
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
            'content_hash' => hash('sha256', $agentName . ':Content from other document'),
            'token_count' => 5,
        ]);

        // Act
        $deletedCount = $this->vectorMemoryManager->deleteMemoriesBySource($agentName, $source, $namespace);

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
            'content_hash' => hash('sha256', $agentName . ':First memory'),
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
            'content_hash' => hash('sha256', $agentName . ':Second memory'),
            'token_count' => 15,
        ]);

        // Act
        $stats = $this->vectorMemoryManager->getStatistics($agentName, $namespace);

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
        $result = $this->vectorMemoryManager->addChunk($agentName, $emptyContent);

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
}