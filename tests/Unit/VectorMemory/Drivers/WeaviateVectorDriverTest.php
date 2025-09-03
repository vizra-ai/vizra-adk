<?php

namespace Vizra\VizraADK\Tests\Unit\VectorMemory\Drivers;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Vizra\VizraADK\Contracts\VectorDriverInterface;
use Vizra\VizraADK\Models\VectorMemory;
use Vizra\VizraADK\Services\Drivers\WeaviateVectorDriver;

class WeaviateVectorDriverTest extends VectorDriverContractTest
{
    protected string $testHost = 'http://test-weaviate:8080';
    protected string $testApiKey = 'test-weaviate-key';
    protected string $testClassPrefix = 'TestAgent';

    protected function setUpDriver(): void
    {
        // Mock configuration
        Config::set('vizra-adk.vector_memory.drivers.weaviate.host', $this->testHost);
        Config::set('vizra-adk.vector_memory.drivers.weaviate.api_key', $this->testApiKey);
        Config::set('vizra-adk.vector_memory.drivers.weaviate.class_prefix', $this->testClassPrefix);
    }

    protected function createDriver(): VectorDriverInterface
    {
        return new WeaviateVectorDriver();
    }

    public function test_constructor_sets_configuration_correctly()
    {
        $this->assertInstanceOf(WeaviateVectorDriver::class, $this->driver);
    }

    public function test_store_creates_class_and_stores_object()
    {
        // Mock Weaviate responses
        Http::fake([
            // Class doesn't exist initially
            $this->testHost.'/v1/schema/TestAgentTestAgentDefault' => Http::sequence()
                ->push(['error' => 'Class not found'], 404)
                ->push(['class' => 'TestAgentTestAgentDefault'], 200),

            // Create class
            $this->testHost.'/v1/schema' => Http::response([
                'class' => 'TestAgentTestAgentDefault',
                'properties' => [],
            ], 201),

            // Store object
            $this->testHost.'/v1/objects' => Http::response([
                'id' => 'weaviate-object-id',
                'class' => 'TestAgentTestAgentDefault',
            ], 201),
        ]);

        $memory = $this->createTestVectorMemory([
            'agent_name' => 'test_agent',
            'namespace' => 'default',
        ]);

        $result = $this->driver->store($memory);

        $this->assertTrue($result);

        // Verify the HTTP requests were made
        Http::assertSent(function ($request) {
            return $request->url() === $this->testHost.'/v1/objects' &&
                   $request->method() === 'POST' &&
                   $request->hasHeader('Authorization', 'Bearer '.$this->testApiKey);
        });
    }

    public function test_store_throws_exception_on_api_error()
    {
        Http::fake([
            $this->testHost.'/v1/schema/*' => Http::response(['error' => 'Server error'], 500),
        ]);

        $memory = $this->createTestVectorMemory();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Failed to store in Weaviate');

        $this->driver->store($memory);
    }

    public function test_search_returns_filtered_results()
    {
        $mockResults = [
            'data' => [
                'Get' => [
                    'TestAgentTestAgentDefault' => [
                        [
                            'memory_id' => 'result-1',
                            'agent_name' => 'test_agent',
                            'namespace' => 'default',
                            'content' => 'First test result',
                            'metadata' => '{"type":"test"}',
                            'source' => 'doc1.txt',
                            'source_id' => 'doc-1',
                            'embedding_provider' => 'openai',
                            'embedding_model' => 'text-embedding-3-small',
                            'created_at' => now()->toISOString(),
                            '_additional' => ['distance' => 0.05], // High similarity
                        ],
                        [
                            'memory_id' => 'result-2',
                            'agent_name' => 'test_agent',
                            'namespace' => 'default',
                            'content' => 'Second test result',
                            'metadata' => '{"type":"test"}',
                            'source' => 'doc2.txt',
                            'source_id' => 'doc-2',
                            'embedding_provider' => 'openai',
                            'embedding_model' => 'text-embedding-3-small',
                            'created_at' => now()->toISOString(),
                            '_additional' => ['distance' => 0.4], // Lower similarity (above threshold)
                        ],
                    ]
                ]
            ]
        ];

        Http::fake([
            $this->testHost.'/v1/graphql' => Http::response($mockResults, 200),
        ]);

        $queryEmbedding = array_fill(0, 1536, 0.2);
        $results = $this->driver->search('test_agent', $queryEmbedding, 'default', 5, 0.5);

        $this->assertInstanceOf(Collection::class, $results);
        $this->assertCount(2, $results);

        $firstResult = $results->first();
        $this->assertEquals('result-1', $firstResult->id);
        $this->assertEquals('First test result', $firstResult->content);
        $this->assertEquals(0.95, $firstResult->similarity); // 1.0 - 0.05

        Http::assertSent(function ($request) {
            return $request->url() === $this->testHost.'/v1/graphql' &&
                   $request->method() === 'POST';
        });
    }

    public function test_search_throws_exception_on_api_error()
    {
        Http::fake([
            $this->testHost.'/v1/graphql' => Http::response(['error' => 'Search failed'], 500),
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Weaviate search failed');

        $this->driver->search('test_agent', [0.1, 0.2, 0.3], 'default', 5, 0.7);
    }

    public function test_delete_with_source_filter()
    {
        Http::fake([
            $this->testHost.'/v1/graphql' => Http::response([
                'data' => [
                    'Delete' => [
                        'TestAgentTestAgentDefault' => [
                            'successful' => 3,
                            'failed' => 0,
                        ]
                    ]
                ]
            ], 200),
        ]);

        $deletedCount = $this->driver->delete('test_agent', 'default', 'test-document.txt');

        $this->assertEquals(3, $deletedCount);

        Http::assertSent(function ($request) {
            $body = $request->data();
            return $request->url() === $this->testHost.'/v1/graphql' &&
                   $request->method() === 'POST' &&
                   str_contains($body['query'], 'source');
        });
    }

    public function test_delete_all_in_namespace()
    {
        Http::fake([
            $this->testHost.'/v1/graphql' => Http::response([
                'data' => [
                    'Delete' => [
                        'TestAgentTestAgentDefault' => [
                            'successful' => 10,
                            'failed' => 0,
                        ]
                    ]
                ]
            ], 200),
        ]);

        $deletedCount = $this->driver->delete('test_agent', 'default');

        $this->assertEquals(10, $deletedCount);

        Http::assertSent(function ($request) {
            $body = $request->data();
            return $request->url() === $this->testHost.'/v1/graphql' &&
                   ! str_contains($body['query'], 'source');
        });
    }

    public function test_get_statistics_returns_formatted_data()
    {
        $mockAggregateResults = [
            'data' => [
                'Aggregate' => [
                    'TestAgentTestAgentDefault' => [
                        [
                            'meta' => ['count' => 100],
                            'token_count' => ['sum' => 1500],
                            'embedding_provider' => [
                                'topOccurrences' => [
                                    ['value' => 'openai', 'occurs' => 70],
                                    ['value' => 'cohere', 'occurs' => 30],
                                ]
                            ],
                            'source' => [
                                'topOccurrences' => [
                                    ['value' => 'doc1.txt', 'occurs' => 60],
                                    ['value' => 'doc2.txt', 'occurs' => 40],
                                ]
                            ]
                        ]
                    ]
                ]
            ]
        ];

        Http::fake([
            $this->testHost.'/v1/graphql' => Http::response($mockAggregateResults, 200),
        ]);

        $stats = $this->driver->getStatistics('test_agent', 'default');

        $this->assertIsArray($stats);
        $this->assertEquals(100, $stats['total_memories']);
        $this->assertEquals(1500, $stats['total_tokens']);
        $this->assertEquals(['openai' => 70, 'cohere' => 30], $stats['providers']);
        $this->assertEquals(['doc1.txt' => 60, 'doc2.txt' => 40], $stats['sources']);
    }

    public function test_get_statistics_handles_errors_gracefully()
    {
        Http::fake([
            $this->testHost.'/v1/graphql' => Http::response(['error' => 'Class not found'], 404),
        ]);

        $stats = $this->driver->getStatistics('test_agent', 'default');

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
            $this->testHost.'/v1/meta' => Http::response(['status' => 'available'], 200),
        ]);

        $this->assertTrue($this->driver->isAvailable());
    }

    public function test_is_available_returns_false_when_unhealthy()
    {
        Http::fake([
            $this->testHost.'/v1/meta' => Http::response(['error' => 'Service unavailable'], 503),
        ]);

        $this->assertFalse($this->driver->isAvailable());
    }

    public function test_get_class_name_formats_correctly()
    {
        $className = $this->invokeMethod($this->driver, 'getClassName', ['test_agent', 'custom_namespace']);

        $this->assertEquals('TestAgentTestAgentCustomNamespace', $className);
    }

    public function test_make_request_includes_authorization_header()
    {
        Http::fake([
            $this->testHost.'/v1/test-endpoint' => Http::response(['success' => true], 200),
        ]);

        $this->invokeMethod($this->driver, 'makeRequest', ['GET', '/v1/test-endpoint']);

        Http::assertSent(function ($request) {
            return $request->hasHeader('Authorization', 'Bearer '.$this->testApiKey) &&
                   $request->hasHeader('Content-Type', 'application/json');
        });
    }

    public function test_make_request_without_api_key()
    {
        Config::set('vizra-adk.vector_memory.drivers.weaviate.api_key', null);
        $driver = new WeaviateVectorDriver();

        Http::fake([
            $this->testHost.'/v1/test-endpoint' => Http::response(['success' => true], 200),
        ]);

        $this->invokeMethod($driver, 'makeRequest', ['GET', '/v1/test-endpoint']);

        Http::assertSent(function ($request) {
            return ! $request->hasHeader('Authorization') &&
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