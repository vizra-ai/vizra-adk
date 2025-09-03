<?php

namespace Vizra\VizraADK\Tests\Unit\VectorMemory\Drivers;

use Illuminate\Support\Collection;
use Vizra\VizraADK\Contracts\VectorDriverInterface;
use Vizra\VizraADK\Models\VectorMemory;
use Vizra\VizraADK\Tests\TestCase;

/**
 * Abstract test class for testing any VectorDriverInterface implementation.
 * 
 * This class provides a standardized test suite that can be used to test
 * any vector driver implementation for consistent behavior.
 */
abstract class VectorDriverContractTest extends TestCase
{
    /**
     * The driver instance to test.
     */
    protected VectorDriverInterface $driver;

    /**
     * Create a driver instance for testing.
     * This method must be implemented by concrete test classes.
     */
    abstract protected function createDriver(): VectorDriverInterface;

    /**
     * Set up test environment for the specific driver.
     * This method can be overridden by concrete test classes if needed.
     */
    abstract protected function setUpDriver(): void;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->setUpDriver();
        $this->driver = $this->createDriver();
    }

    /**
     * Create a test VectorMemory instance.
     */
    protected function createTestVectorMemory(array $overrides = []): VectorMemory
    {
        $defaults = [
            'id' => 'test-id-' . uniqid(),
            'agent_name' => 'test_agent',
            'namespace' => 'default',
            'content' => 'This is test content for vector storage.',
            'metadata' => ['type' => 'test', 'category' => 'unit-test'],
            'source' => 'test-document.txt',
            'source_id' => 'doc-' . uniqid(),
            'chunk_index' => 0,
            'embedding_provider' => 'openai',
            'embedding_model' => 'text-embedding-3-small',
            'embedding_dimensions' => 1536,
            'embedding_vector' => array_fill(0, 1536, 0.1),
            'embedding_norm' => 1.0,
            'content_hash' => 'test-hash-' . uniqid(),
            'token_count' => 15,
            'created_at' => now(),
            'updated_at' => now(),
        ];

        return new VectorMemory(array_merge($defaults, $overrides));
    }

    /**
     * Test that the driver implements the VectorDriverInterface.
     */
    public function test_implements_vector_driver_interface()
    {
        $this->assertInstanceOf(VectorDriverInterface::class, $this->driver);
    }

    /**
     * Test the store method exists and returns boolean.
     */
    public function test_store_method_exists_and_returns_boolean()
    {
        $this->assertTrue(method_exists($this->driver, 'store'));
        
        $memory = $this->createTestVectorMemory();
        $result = $this->driver->store($memory);
        
        $this->assertIsBool($result);
    }

    /**
     * Test the search method exists and returns Collection.
     */
    public function test_search_method_exists_and_returns_collection()
    {
        $this->assertTrue(method_exists($this->driver, 'search'));
        
        $queryEmbedding = array_fill(0, 1536, 0.2);
        $result = $this->driver->search('test_agent', $queryEmbedding);
        
        $this->assertInstanceOf(Collection::class, $result);
    }

    /**
     * Test the delete method exists and returns integer.
     */
    public function test_delete_method_exists_and_returns_integer()
    {
        $this->assertTrue(method_exists($this->driver, 'delete'));
        
        $result = $this->driver->delete('test_agent', 'default');
        
        $this->assertIsInt($result);
        $this->assertGreaterThanOrEqual(0, $result);
    }

    /**
     * Test the getStatistics method exists and returns array.
     */
    public function test_get_statistics_method_exists_and_returns_array()
    {
        $this->assertTrue(method_exists($this->driver, 'getStatistics'));
        
        $result = $this->driver->getStatistics('test_agent', 'default');
        
        $this->assertIsArray($result);
        $this->assertArrayHasKey('total_memories', $result);
        $this->assertArrayHasKey('total_tokens', $result);
        $this->assertArrayHasKey('providers', $result);
        $this->assertArrayHasKey('sources', $result);
    }

    /**
     * Test the isAvailable method exists and returns boolean.
     */
    public function test_is_available_method_exists_and_returns_boolean()
    {
        $this->assertTrue(method_exists($this->driver, 'isAvailable'));
        
        $result = $this->driver->isAvailable();
        
        $this->assertIsBool($result);
    }

    /**
     * Test search method accepts all required parameters.
     */
    public function test_search_method_accepts_required_parameters()
    {
        $queryEmbedding = array_fill(0, 1536, 0.3);
        $result = $this->driver->search(
            agentName: 'test_agent',
            queryEmbedding: $queryEmbedding,
            namespace: 'custom_namespace',
            limit: 10,
            threshold: 0.8
        );
        
        $this->assertInstanceOf(Collection::class, $result);
    }

    /**
     * Test search results have expected structure.
     */
    public function test_search_results_have_expected_structure()
    {
        // First store a test memory
        $memory = $this->createTestVectorMemory();
        $this->driver->store($memory);
        
        $queryEmbedding = $memory->embedding_vector;
        $results = $this->driver->search('test_agent', $queryEmbedding, 'default', 5, 0.1);
        
        if ($results->isNotEmpty()) {
            $firstResult = $results->first();
            
            // Check required properties exist
            $this->assertObjectHasProperty('id', $firstResult);
            $this->assertObjectHasProperty('agent_name', $firstResult);
            $this->assertObjectHasProperty('namespace', $firstResult);
            $this->assertObjectHasProperty('content', $firstResult);
            $this->assertObjectHasProperty('metadata', $firstResult);
            $this->assertObjectHasProperty('source', $firstResult);
            $this->assertObjectHasProperty('source_id', $firstResult);
            $this->assertObjectHasProperty('embedding_provider', $firstResult);
            $this->assertObjectHasProperty('embedding_model', $firstResult);
            $this->assertObjectHasProperty('similarity', $firstResult);
        }
    }

    /**
     * Test delete with source filter.
     */
    public function test_delete_with_source_filter()
    {
        $result = $this->driver->delete('test_agent', 'default', 'specific-source.txt');
        
        $this->assertIsInt($result);
        $this->assertGreaterThanOrEqual(0, $result);
    }

    /**
     * Test statistics structure is consistent.
     */
    public function test_statistics_structure_is_consistent()
    {
        $stats = $this->driver->getStatistics('test_agent', 'default');
        
        $this->assertIsArray($stats);
        $this->assertArrayHasKey('total_memories', $stats);
        $this->assertArrayHasKey('total_tokens', $stats);
        $this->assertArrayHasKey('providers', $stats);
        $this->assertArrayHasKey('sources', $stats);
        
        $this->assertIsInt($stats['total_memories']);
        $this->assertIsInt($stats['total_tokens']);
        $this->assertIsArray($stats['providers']);
        $this->assertIsArray($stats['sources']);
    }
}