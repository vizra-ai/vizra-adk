<?php

namespace AaronLumsden\LaravelAiADK\Tests\Unit\VectorMemory\Drivers;

use AaronLumsden\LaravelAiADK\Tests\TestCase;
use AaronLumsden\LaravelAiADK\Services\Drivers\MeilisearchVectorDriver;
use AaronLumsden\LaravelAiADK\Models\VectorMemory;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Collection;
use RuntimeException;

class MeilisearchVectorDriverTest extends TestCase
{
    protected MeilisearchVectorDriver $driver;
    protected string $testHost = 'http://test-meilisearch:7700';
    protected string $testApiKey = 'test-api-key';
    protected string $testIndexPrefix = 'test_vectors_';

    protected function setUp(): void
    {
        parent::setUp();
        
        // Mock configuration
        Config::set('agent-adk.vector_memory.drivers.meilisearch.host', $this->testHost);
        Config::set('agent-adk.vector_memory.drivers.meilisearch.api_key', $this->testApiKey);
        Config::set('agent-adk.vector_memory.drivers.meilisearch.index_prefix', $this->testIndexPrefix);
        
        $this->driver = new MeilisearchVectorDriver();
    }

    public function test_constructor_sets_configuration_correctly()
    {
        $this->assertInstanceOf(MeilisearchVectorDriver::class, $this->driver);
    }

    public function test_store_creates_index_and_stores_document()
    {
        // Mock Meilisearch responses
        Http::fake([
            // Index doesn't exist initially
            $this->testHost . '/indexes/test_vectors_testagent_default' => Http::sequence()
                ->push(['error' => 'Index not found'], 404)
                ->push(['uid' => 'test_vectors_testagent_default', 'primaryKey' => 'id'], 200),
            
            // Create index
            $this->testHost . '/indexes' => Http::response([
                'taskUid' => 123,
                'indexUid' => 'test_vectors_testagent_default',
            ], 201),
            
            // Update settings
            $this->testHost . '/indexes/test_vectors_testagent_default/settings' => Http::response([
                'taskUid' => 124
            ], 202),
            
            // Store document
            $this->testHost . '/indexes/test_vectors_testagent_default/documents' => Http::response([
                'taskUid' => 125,
                'indexUid' => 'test_vectors_testagent_default',
            ], 202),
        ]);

        $memory = new VectorMemory([
            'id' => 'test-id-123',
            'agent_name' => 'TestAgent',
            'namespace' => 'default',
            'content' => 'This is test content for vector storage.',
            'metadata' => ['type' => 'test'],
            'source' => 'test-document.txt',
            'source_id' => 'doc-123',
            'chunk_index' => 0,
            'embedding_provider' => 'openai',
            'embedding_model' => 'text-embedding-ada-002',
            'embedding_dimensions' => 1536,
            'embedding_vector' => array_fill(0, 1536, 0.1),
            'embedding_norm' => 1.0,
            'content_hash' => 'abc123hash',
            'token_count' => 15,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $result = $this->driver->store($memory);

        $this->assertTrue($result);
        
        // Verify the HTTP requests were made
        Http::assertSent(function ($request) {
            return $request->url() === $this->testHost . '/indexes/test_vectors_testagent_default/documents' &&
                   $request->method() === 'POST' &&
                   $request->hasHeader('Authorization', 'Bearer ' . $this->testApiKey);
        });
    }

    public function test_store_throws_exception_on_api_error()
    {
        Http::fake([
            $this->testHost . '/indexes/*' => Http::response(['error' => 'Server error'], 500),
        ]);

        $memory = new VectorMemory([
            'id' => 'test-id-123',
            'agent_name' => 'TestAgent',
            'namespace' => 'default',
            'content' => 'Test content',
            'embedding_dimensions' => 1536,
            'embedding_vector' => array_fill(0, 1536, 0.1),
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Failed to store in Meilisearch');
        
        $this->driver->store($memory);
    }

    public function test_search_returns_filtered_results()
    {
        $mockHits = [
            [
                'id' => 'result-1',
                'agent_name' => 'TestAgent',
                'namespace' => 'default',
                'content' => 'First test result',
                'metadata' => ['type' => 'test'],
                'source' => 'doc1.txt',
                'source_id' => 'doc-1',
                'embedding_provider' => 'openai',
                'embedding_model' => 'text-embedding-ada-002',
                'created_at' => now()->timestamp,
                '_rankingScore' => 0.95,
            ],
            [
                'id' => 'result-2',
                'agent_name' => 'TestAgent',
                'namespace' => 'default',
                'content' => 'Second test result',
                'metadata' => ['type' => 'test'],
                'source' => 'doc2.txt',
                'source_id' => 'doc-2',
                'embedding_provider' => 'openai',
                'embedding_model' => 'text-embedding-ada-002',
                'created_at' => now()->timestamp,
                '_rankingScore' => 0.6, // Below threshold
            ],
        ];

        Http::fake([
            $this->testHost . '/indexes/test_vectors_testagent_default/search' => Http::response([
                'hits' => $mockHits,
                'processingTimeMs' => 5,
                'query' => 'test query',
            ], 200),
        ]);

        $queryEmbedding = array_fill(0, 1536, 0.2);
        $results = $this->driver->search('TestAgent', $queryEmbedding, 'default', 5, 0.7);

        $this->assertInstanceOf(Collection::class, $results);
        $this->assertCount(1, $results); // Only one result above threshold
        
        $firstResult = $results->first();
        $this->assertEquals('result-1', $firstResult->id);
        $this->assertEquals('First test result', $firstResult->content);
        $this->assertEquals(0.95, $firstResult->similarity);
        
        Http::assertSent(function ($request) {
            $body = $request->data();
            return $request->url() === $this->testHost . '/indexes/test_vectors_testagent_default/search' &&
                   $request->method() === 'POST' &&
                   isset($body['vector']) &&
                   $body['limit'] === 5 &&
                   $body['showRankingScore'] === true;
        });
    }

    public function test_search_throws_exception_on_api_error()
    {
        Http::fake([
            $this->testHost . '/indexes/*/search' => Http::response(['error' => 'Search failed'], 500),
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Meilisearch search failed');
        
        $this->driver->search('TestAgent', [0.1, 0.2, 0.3], 'default', 5, 0.7);
    }

    public function test_delete_with_source_filter()
    {
        Http::fake([
            $this->testHost . '/indexes/test_vectors_testagent_default/documents/delete' => Http::response([
                'taskUid' => 456,
                'indexUid' => 'test_vectors_testagent_default',
            ], 202),
            
            $this->testHost . '/tasks/456' => Http::response([
                'uid' => 456,
                'status' => 'succeeded',
                'details' => ['deletedDocuments' => 3],
            ], 200),
        ]);

        $deletedCount = $this->driver->delete('TestAgent', 'default', 'test-document.txt');

        $this->assertEquals(3, $deletedCount);
        
        Http::assertSent(function ($request) {
            $body = $request->data();
            return $request->url() === $this->testHost . '/indexes/test_vectors_testagent_default/documents/delete' &&
                   $request->method() === 'POST' &&
                   str_contains($body['filter'], "source = 'test-document.txt'");
        });
    }

    public function test_delete_all_in_namespace()
    {
        Http::fake([
            $this->testHost . '/indexes/test_vectors_testagent_default/documents/delete' => Http::response([
                'taskUid' => 789,
                'indexUid' => 'test_vectors_testagent_default',
            ], 202),
            
            $this->testHost . '/tasks/789' => Http::response([
                'uid' => 789,
                'status' => 'succeeded',
                'details' => ['deletedDocuments' => 10],
            ], 200),
        ]);

        $deletedCount = $this->driver->delete('TestAgent', 'default');

        $this->assertEquals(10, $deletedCount);
        
        Http::assertSent(function ($request) {
            $body = $request->data();
            return $request->url() === $this->testHost . '/indexes/test_vectors_testagent_default/documents/delete' &&
                   !str_contains($body['filter'], 'source =');
        });
    }

    public function test_get_statistics_returns_formatted_data()
    {
        $mockIndexStats = [
            'numberOfDocuments' => 100,
            'isIndexing' => false,
            'fieldDistribution' => ['content' => 100, 'metadata' => 100],
        ];

        $mockSearchResults = [
            'hits' => [
                [
                    'embedding_provider' => 'openai',
                    'source' => 'doc1.txt',
                    'token_count' => 50,
                ],
                [
                    'embedding_provider' => 'openai',
                    'source' => 'doc2.txt',
                    'token_count' => 75,
                ],
                [
                    'embedding_provider' => 'cohere',
                    'source' => 'doc1.txt',
                    'token_count' => 30,
                ],
            ],
        ];

        Http::fake([
            $this->testHost . '/indexes/test_vectors_testagent_default/stats' => Http::response($mockIndexStats, 200),
            $this->testHost . '/indexes/test_vectors_testagent_default/search' => Http::response($mockSearchResults, 200),
        ]);

        $stats = $this->driver->getStatistics('TestAgent', 'default');

        $this->assertIsArray($stats);
        $this->assertEquals(3, $stats['total_memories']);
        $this->assertEquals(155, $stats['total_tokens']); // 50 + 75 + 30
        $this->assertEquals(['openai' => 2, 'cohere' => 1], $stats['providers']);
        $this->assertEquals(['doc1.txt' => 2, 'doc2.txt' => 1], $stats['sources']);
        $this->assertEquals($mockIndexStats, $stats['index_stats']);
    }

    public function test_get_statistics_handles_errors_gracefully()
    {
        Http::fake([
            $this->testHost . '/indexes/*/stats' => Http::response(['error' => 'Index not found'], 404),
        ]);

        $stats = $this->driver->getStatistics('TestAgent', 'default');

        $this->assertIsArray($stats);
        $this->assertEquals(0, $stats['total_memories']);
        $this->assertEquals(0, $stats['total_tokens']);
        $this->assertEquals([], $stats['providers']);
        $this->assertEquals([], $stats['sources']);
        $this->assertArrayHasKey('error', $stats);
    }

    public function test_is_available_returns_true_when_healthy()
    {
        Http::fake([
            $this->testHost . '/health' => Http::response(['status' => 'available'], 200),
        ]);

        $this->assertTrue($this->driver->isAvailable());
    }

    public function test_is_available_returns_false_when_unhealthy()
    {
        Http::fake([
            $this->testHost . '/health' => Http::response(['error' => 'Service unavailable'], 503),
        ]);

        $this->assertFalse($this->driver->isAvailable());
    }

    public function test_wait_for_task_handles_timeout()
    {
        Http::fake([
            $this->testHost . '/tasks/123' => Http::response([
                'uid' => 123,
                'status' => 'enqueued',
            ], 200),
        ]);

        $result = $this->invokeMethod($this->driver, 'waitForTask', ['123', 1]); // 1 second timeout

        $this->assertEquals(0, $result);
    }

    public function test_get_index_name_formats_correctly()
    {
        $indexName = $this->invokeMethod($this->driver, 'getIndexName', ['MyAgent', 'TestNamespace']);
        
        $this->assertEquals('test_vectors_myagent_testnamespace', $indexName);
    }

    public function test_make_request_includes_authorization_header()
    {
        Http::fake([
            $this->testHost . '/test-endpoint' => Http::response(['success' => true], 200),
        ]);

        $this->invokeMethod($this->driver, 'makeRequest', ['GET', '/test-endpoint']);

        Http::assertSent(function ($request) {
            return $request->hasHeader('Authorization', 'Bearer ' . $this->testApiKey) &&
                   $request->hasHeader('Content-Type', 'application/json');
        });
    }

    public function test_make_request_without_api_key()
    {
        Config::set('agent-adk.vector_memory.drivers.meilisearch.api_key', null);
        $driver = new MeilisearchVectorDriver();

        Http::fake([
            $this->testHost . '/test-endpoint' => Http::response(['success' => true], 200),
        ]);

        $this->invokeMethod($driver, 'makeRequest', ['GET', '/test-endpoint']);

        Http::assertSent(function ($request) {
            return !$request->hasHeader('Authorization') &&
                   $request->hasHeader('Content-Type', 'application/json');
        });
    }

    /**
     * Helper method to invoke protected methods for testing
     */
    protected function invokeMethod(object $object, string $methodName, array $parameters = [])
    {
        $reflection = new \ReflectionClass(get_class($object));
        $method = $reflection->getMethod($methodName);
        $method->setAccessible(true);

        return $method->invokeArgs($object, $parameters);
    }
}