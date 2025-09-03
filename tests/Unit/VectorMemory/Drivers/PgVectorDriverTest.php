<?php

namespace Vizra\VizraADK\Tests\Unit\VectorMemory\Drivers;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Vizra\VizraADK\Contracts\VectorDriverInterface;
use Vizra\VizraADK\Models\VectorMemory;
use Vizra\VizraADK\Services\Drivers\PgVectorDriver;

class PgVectorDriverTest extends VectorDriverContractTest
{
    use RefreshDatabase;

    protected function setUpDriver(): void
    {
        // No additional setup needed for PgVector driver
    }

    protected function createDriver(): VectorDriverInterface
    {
        return new PgVectorDriver();
    }

    public function test_constructor_creates_instance()
    {
        $this->assertInstanceOf(PgVectorDriver::class, $this->driver);
    }

    public function test_store_updates_embedding_in_database()
    {
        // Skip if not using PostgreSQL
        if (DB::connection()->getDriverName() !== 'pgsql') {
            $this->markTestSkipped('PgVector driver requires PostgreSQL connection');
        }

        $memory = VectorMemory::factory()->create([
            'agent_name' => 'test_agent',
            'namespace' => 'default',
            'embedding_vector' => array_fill(0, 1536, 0.1),
        ]);

        $result = $this->driver->store($memory);

        $this->assertTrue($result);

        // Verify the embedding was stored in the pgvector column
        $storedMemory = VectorMemory::find($memory->id);
        $this->assertNotNull($storedMemory);
    }

    public function test_store_throws_exception_with_non_postgresql_connection()
    {
        // Mock a non-PostgreSQL connection
        DB::shouldReceive('connection->getDriverName')->andReturn('mysql');

        $memory = $this->createTestVectorMemory();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('PgVector driver requires PostgreSQL database connection');

        $this->driver->store($memory);
    }

    public function test_search_with_pgvector_extension()
    {
        // Skip if not using PostgreSQL
        if (DB::connection()->getDriverName() !== 'pgsql') {
            $this->markTestSkipped('PgVector driver requires PostgreSQL connection');
        }

        // Create test memories
        $memory1 = VectorMemory::factory()->create([
            'agent_name' => 'test_agent',
            'namespace' => 'default',
            'content' => 'First test content',
            'embedding_vector' => array_fill(0, 1536, 0.9),
        ]);

        $memory2 = VectorMemory::factory()->create([
            'agent_name' => 'test_agent',
            'namespace' => 'default',
            'content' => 'Second test content',
            'embedding_vector' => array_fill(0, 1536, 0.1),
        ]);

        // Store embeddings using the driver
        $this->driver->store($memory1);
        $this->driver->store($memory2);

        // Search for similar vectors
        $queryEmbedding = array_fill(0, 1536, 0.9); // Should match memory1 better
        $results = $this->driver->search('test_agent', $queryEmbedding, 'default', 5, 0.1);

        $this->assertInstanceOf(Collection::class, $results);
        $this->assertGreaterThan(0, $results->count());

        if ($results->isNotEmpty()) {
            $firstResult = $results->first();
            $this->assertEquals($memory1->id, $firstResult->id);
            $this->assertEquals('First test content', $firstResult->content);
        }
    }

    public function test_search_throws_exception_with_non_postgresql_connection()
    {
        // Mock a non-PostgreSQL connection
        DB::shouldReceive('connection->getDriverName')->andReturn('mysql');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('PgVector driver requires PostgreSQL database connection');

        $this->driver->search('test_agent', [0.1, 0.2, 0.3]);
    }

    public function test_delete_removes_records_from_database()
    {
        // Create test memories
        VectorMemory::factory()->create([
            'agent_name' => 'test_agent',
            'namespace' => 'default',
            'source' => 'test-source.txt',
        ]);

        VectorMemory::factory()->create([
            'agent_name' => 'test_agent',
            'namespace' => 'default',
            'source' => 'other-source.txt',
        ]);

        // Delete by source
        $deletedCount = $this->driver->delete('test_agent', 'default', 'test-source.txt');

        $this->assertEquals(1, $deletedCount);

        // Verify only one record was deleted
        $remaining = VectorMemory::forAgent('test_agent')->inNamespace('default')->count();
        $this->assertEquals(1, $remaining);
    }

    public function test_delete_all_in_namespace()
    {
        // Create test memories
        VectorMemory::factory()->count(3)->create([
            'agent_name' => 'test_agent',
            'namespace' => 'default',
        ]);

        VectorMemory::factory()->create([
            'agent_name' => 'test_agent',
            'namespace' => 'other',
        ]);

        // Delete all in default namespace
        $deletedCount = $this->driver->delete('test_agent', 'default');

        $this->assertEquals(3, $deletedCount);

        // Verify only default namespace records were deleted
        $remainingDefault = VectorMemory::forAgent('test_agent')->inNamespace('default')->count();
        $remainingOther = VectorMemory::forAgent('test_agent')->inNamespace('other')->count();
        
        $this->assertEquals(0, $remainingDefault);
        $this->assertEquals(1, $remainingOther);
    }

    public function test_get_statistics_returns_accurate_data()
    {
        // Create test memories with different providers and sources
        VectorMemory::factory()->create([
            'agent_name' => 'test_agent',
            'namespace' => 'default',
            'embedding_provider' => 'openai',
            'source' => 'doc1.txt',
            'token_count' => 50,
        ]);

        VectorMemory::factory()->create([
            'agent_name' => 'test_agent',
            'namespace' => 'default',
            'embedding_provider' => 'openai',
            'source' => 'doc2.txt',
            'token_count' => 75,
        ]);

        VectorMemory::factory()->create([
            'agent_name' => 'test_agent',
            'namespace' => 'default',
            'embedding_provider' => 'cohere',
            'source' => 'doc1.txt',
            'token_count' => 30,
        ]);

        $stats = $this->driver->getStatistics('test_agent', 'default');

        $this->assertIsArray($stats);
        $this->assertEquals(3, $stats['total_memories']);
        $this->assertEquals(155, $stats['total_tokens']); // 50 + 75 + 30
        $this->assertEquals(['openai' => 2, 'cohere' => 1], $stats['providers']);
        $this->assertEquals(['doc1.txt' => 2, 'doc2.txt' => 1], $stats['sources']);
    }

    public function test_is_available_checks_pgvector_extension()
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            $this->assertFalse($this->driver->isAvailable());
        } else {
            // For PostgreSQL connections, this depends on whether pgvector is installed
            $result = $this->driver->isAvailable();
            $this->assertIsBool($result);
        }
    }
}