<?php

namespace Vizra\VizraSdk\Tests\Unit\Tools;

use Vizra\VizraSdk\Tests\TestCase;
use Vizra\VizraSdk\Tools\VectorMemoryTool;
use Vizra\VizraSdk\Services\VectorMemoryManager;
use Vizra\VizraSdk\System\AgentContext;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Mockery;

class VectorMemoryToolTest extends TestCase
{
    protected VectorMemoryTool $tool;
    protected $mockVectorMemory;
    protected $mockContext;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->mockVectorMemory = Mockery::mock(VectorMemoryManager::class);
        $this->mockContext = Mockery::mock(AgentContext::class);
        $this->tool = new VectorMemoryTool($this->mockVectorMemory);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_definition_returns_correct_structure()
    {
        $definition = $this->tool->definition();

        $this->assertIsArray($definition);
        $this->assertEquals('vector_memory', $definition['name']);
        $this->assertStringContainsString('semantic vector search', $definition['description']);
        
        // Check required parameters
        $this->assertEquals(['action'], $definition['parameters']['required']);
        
        // Check action enum values
        $this->assertEquals(['store', 'search', 'delete', 'stats'], $definition['parameters']['properties']['action']['enum']);
        
        // Check parameter types
        $this->assertEquals('string', $definition['parameters']['properties']['content']['type']);
        $this->assertEquals('string', $definition['parameters']['properties']['query']['type']);
        $this->assertEquals('object', $definition['parameters']['properties']['metadata']['type']);
        $this->assertEquals('integer', $definition['parameters']['properties']['limit']['type']);
        $this->assertEquals('number', $definition['parameters']['properties']['threshold']['type']);
    }

    public function test_execute_with_unknown_action_returns_error()
    {
        $this->mockContext->shouldReceive('getState')->with('agent_name')->andReturn('TestAgent');

        $result = $this->tool->execute(['action' => 'invalid_action'], $this->mockContext);
        $response = json_decode($result, true);

        $this->assertFalse($response['success']);
        $this->assertStringContainsString('Unknown action', $response['error']);
    }

    public function test_execute_handles_exceptions_gracefully()
    {
        $this->mockContext->shouldReceive('getState')->with('agent_name')->andReturn('TestAgent');
        $this->mockVectorMemory->shouldReceive('addDocument')->andThrow(new \Exception('Test exception'));

        Log::shouldReceive('error')->once();

        $result = $this->tool->execute([
            'action' => 'store',
            'content' => 'Test content'
        ], $this->mockContext);
        
        $response = json_decode($result, true);

        $this->assertFalse($response['success']);
        $this->assertStringContainsString('Vector memory operation failed', $response['error']);
    }

    public function test_handle_store_success()
    {
        $this->mockContext->shouldReceive('getState')->with('agent_name')->andReturn('TestAgent');
        $this->mockContext->shouldReceive('getSessionId')->andReturn('session-123');

        $mockMemories = collect([
            (object)['id' => 'chunk-1', 'content' => 'Chunk 1'],
            (object)['id' => 'chunk-2', 'content' => 'Chunk 2'],
        ]);

        $this->mockVectorMemory->shouldReceive('addDocument')
            ->once()
            ->with(
                'TestAgent',
                'This is test content to store',
                Mockery::on(function ($metadata) {
                    return $metadata['stored_by_agent'] === 'TestAgent' &&
                           $metadata['session_id'] === 'session-123' &&
                           isset($metadata['stored_at']);
                }),
                'default',
                'test-doc.txt',
                'doc-123'
            )
            ->andReturn($mockMemories);

        $result = $this->tool->execute([
            'action' => 'store',
            'content' => 'This is test content to store',
            'namespace' => 'default',
            'source' => 'test-doc.txt',
            'source_id' => 'doc-123',
            'metadata' => ['type' => 'test']
        ], $this->mockContext);

        $response = json_decode($result, true);

        $this->assertTrue($response['success']);
        $this->assertEquals('store', $response['action']);
        $this->assertEquals(2, $response['chunks_created']);
        $this->assertEquals('default', $response['namespace']);
        $this->assertEquals('test-doc.txt', $response['source']);
        $this->assertEquals(29, $response['content_length']);
        $this->assertStringContainsString('2 chunks', $response['message']);
    }

    public function test_handle_store_missing_content_returns_error()
    {
        $this->mockContext->shouldReceive('getState')->with('agent_name')->andReturn('TestAgent');

        $result = $this->tool->execute([
            'action' => 'store'
        ], $this->mockContext);

        $response = json_decode($result, true);

        $this->assertFalse($response['success']);
        $this->assertEquals('Content is required for store action', $response['error']);
    }

    public function test_handle_search_with_raw_results()
    {
        $this->mockContext->shouldReceive('getState')->with('agent_name')->andReturn('TestAgent');

        $result1 = new \stdClass();
        $result1->id = 'result-1';
        $result1->content = 'First result content';
        $result1->similarity = 0.95;
        $result1->metadata = ['type' => 'test'];
        $result1->source = 'doc1.txt';
        $result1->source_id = 'doc-1';
        $result1->created_at = now();

        $result2 = new \stdClass();
        $result2->id = 'result-2';
        $result2->content = 'Second result content';
        $result2->similarity = 0.87;
        $result2->metadata = ['type' => 'test'];
        $result2->source = 'doc2.txt';
        $result2->source_id = 'doc-2';
        $result2->created_at = now();

        $mockResults = collect([$result1, $result2]);

        $this->mockVectorMemory->shouldReceive('search')
            ->once()
            ->with('TestAgent', 'test search query', 'default', 5, 0.7)
            ->andReturn($mockResults);

        $result = $this->tool->execute([
            'action' => 'search',
            'query' => 'test search query',
            'namespace' => 'default',
            'limit' => 5,
            'threshold' => 0.7,
            'generate_rag_context' => false
        ], $this->mockContext);

        $response = json_decode($result, true);

        $this->assertTrue($response['success']);
        $this->assertEquals('search', $response['action']);
        $this->assertEquals('test search query', $response['query']);
        $this->assertEquals('default', $response['namespace']);
        $this->assertCount(2, $response['results']);
        $this->assertEquals(2, $response['total_results']);
        
        // Check first result structure
        $firstResult = $response['results'][0];
        $this->assertEquals('result-1', $firstResult['id']);
        $this->assertEquals('First result content', $firstResult['content']);
        $this->assertEquals(0.95, $firstResult['similarity']);
        $this->assertEquals(['type' => 'test'], $firstResult['metadata']);
    }

    public function test_handle_search_with_rag_context()
    {
        $this->mockContext->shouldReceive('getState')->with('agent_name')->andReturn('TestAgent');

        $mockRagContext = [
            'context' => 'Formatted RAG context with relevant information...',
            'sources' => ['doc1.txt', 'doc2.txt'],
            'total_results' => 2,
        ];

        $this->mockVectorMemory->shouldReceive('generateRagContext')
            ->once()
            ->with('TestAgent', 'test search query', 'default', 3, 0.8)
            ->andReturn($mockRagContext);

        $result = $this->tool->execute([
            'action' => 'search',
            'query' => 'test search query',
            'namespace' => 'default',
            'limit' => 3,
            'threshold' => 0.8,
            'generate_rag_context' => true
        ], $this->mockContext);

        $response = json_decode($result, true);

        $this->assertTrue($response['success']);
        $this->assertEquals('search', $response['action']);
        $this->assertEquals('test search query', $response['query']);
        $this->assertEquals('default', $response['namespace']);
        $this->assertEquals('Formatted RAG context with relevant information...', $response['rag_context']);
        $this->assertEquals(['doc1.txt', 'doc2.txt'], $response['sources']);
        $this->assertEquals(2, $response['total_results']);
        $this->assertStringContainsString('2 relevant results', $response['message']);
    }

    public function test_handle_search_missing_query_returns_error()
    {
        $this->mockContext->shouldReceive('getState')->with('agent_name')->andReturn('TestAgent');

        $result = $this->tool->execute([
            'action' => 'search'
        ], $this->mockContext);

        $response = json_decode($result, true);

        $this->assertFalse($response['success']);
        $this->assertEquals('Query is required for search action', $response['error']);
    }

    public function test_handle_delete_by_source()
    {
        $this->mockContext->shouldReceive('getState')->with('agent_name')->andReturn('TestAgent');

        $this->mockVectorMemory->shouldReceive('deleteMemoriesBySource')
            ->once()
            ->with('TestAgent', 'test-doc.txt', 'default')
            ->andReturn(5);

        $result = $this->tool->execute([
            'action' => 'delete',
            'namespace' => 'default',
            'source' => 'test-doc.txt'
        ], $this->mockContext);

        $response = json_decode($result, true);

        $this->assertTrue($response['success']);
        $this->assertEquals('delete', $response['action']);
        $this->assertEquals('default', $response['namespace']);
        $this->assertEquals('test-doc.txt', $response['source']);
        $this->assertEquals(5, $response['deleted_count']);
        $this->assertStringContainsString("5 memories from source 'test-doc.txt'", $response['message']);
    }

    public function test_handle_delete_all_in_namespace()
    {
        $this->mockContext->shouldReceive('getState')->with('agent_name')->andReturn('TestAgent');

        $this->mockVectorMemory->shouldReceive('deleteMemories')
            ->once()
            ->with('TestAgent', 'default')
            ->andReturn(10);

        $result = $this->tool->execute([
            'action' => 'delete',
            'namespace' => 'default'
        ], $this->mockContext);

        $response = json_decode($result, true);

        $this->assertTrue($response['success']);
        $this->assertEquals('delete', $response['action']);
        $this->assertEquals('default', $response['namespace']);
        $this->assertNull($response['source']);
        $this->assertEquals(10, $response['deleted_count']);
        $this->assertStringContainsString("10 memories from namespace 'default'", $response['message']);
    }

    public function test_handle_stats_returns_statistics()
    {
        $this->mockContext->shouldReceive('getState')->with('agent_name')->andReturn('TestAgent');

        $mockStats = [
            'total_memories' => 50,
            'total_tokens' => 1250,
            'providers' => ['openai' => 30, 'cohere' => 20],
            'sources' => ['doc1.txt' => 25, 'doc2.txt' => 25],
            'avg_similarity' => 0.85,
        ];

        $this->mockVectorMemory->shouldReceive('getStatistics')
            ->once()
            ->with('TestAgent', 'default')
            ->andReturn($mockStats);

        $result = $this->tool->execute([
            'action' => 'stats',
            'namespace' => 'default'
        ], $this->mockContext);

        $response = json_decode($result, true);

        $this->assertTrue($response['success']);
        $this->assertEquals('stats', $response['action']);
        $this->assertEquals('default', $response['namespace']);
        $this->assertEquals($mockStats, $response['statistics']);
        $this->assertStringContainsString("namespace 'default'", $response['message']);
    }

    public function test_execute_uses_default_namespace_when_not_provided()
    {
        $this->mockContext->shouldReceive('getState')->with('agent_name')->andReturn('TestAgent');

        $this->mockVectorMemory->shouldReceive('getStatistics')
            ->once()
            ->with('TestAgent', 'default')
            ->andReturn([]);

        $result = $this->tool->execute([
            'action' => 'stats'
        ], $this->mockContext);

        $response = json_decode($result, true);

        $this->assertTrue($response['success']);
        $this->assertEquals('default', $response['namespace']);
    }

    public function test_execute_handles_unknown_agent_name()
    {
        $this->mockContext->shouldReceive('getState')->with('agent_name')->andReturn(null);

        $this->mockVectorMemory->shouldReceive('getStatistics')
            ->once()
            ->with('unknown', 'default')
            ->andReturn([]);

        $result = $this->tool->execute([
            'action' => 'stats'
        ], $this->mockContext);

        $response = json_decode($result, true);

        $this->assertTrue($response['success']);
    }

    public function test_store_with_default_parameters()
    {
        $this->mockContext->shouldReceive('getState')->with('agent_name')->andReturn('TestAgent');
        $this->mockContext->shouldReceive('getSessionId')->andReturn(null);

        $mockMemories = collect([(object)['id' => 'chunk-1']]);

        $this->mockVectorMemory->shouldReceive('addDocument')
            ->once()
            ->with(
                'TestAgent',
                'Simple content',
                Mockery::on(function ($metadata) {
                    return $metadata['stored_by_agent'] === 'TestAgent' &&
                           !isset($metadata['session_id']);
                }),
                'default',
                null,
                null
            )
            ->andReturn($mockMemories);

        $result = $this->tool->execute([
            'action' => 'store',
            'content' => 'Simple content'
        ], $this->mockContext);

        $response = json_decode($result, true);

        $this->assertTrue($response['success']);
        $this->assertEquals('default', $response['namespace']);
        $this->assertNull($response['source']);
    }

    public function test_search_uses_default_parameters()
    {
        $this->mockContext->shouldReceive('getState')->with('agent_name')->andReturn('TestAgent');

        $this->mockVectorMemory->shouldReceive('search')
            ->once()
            ->with('TestAgent', 'simple query', 'default', 5, 0.7)
            ->andReturn(collect());

        $result = $this->tool->execute([
            'action' => 'search',
            'query' => 'simple query'
        ], $this->mockContext);

        $response = json_decode($result, true);

        $this->assertTrue($response['success']);
        $this->assertEquals('default', $response['namespace']);
        $this->assertEquals(0, $response['total_results']);
    }
}