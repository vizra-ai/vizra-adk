<?php

namespace Vizra\VizraADK\Tests\Unit\Console\Commands;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Mockery;
use Vizra\VizraADK\Models\VectorMemory;
use Vizra\VizraADK\Services\VectorMemoryManager;
use Vizra\VizraADK\Tests\TestCase;

class VectorMemoryStoreTest extends TestCase
{
    protected $mockVectorMemoryManager;

    protected function setUp(): void
    {
        parent::setUp();

        // Register the VectorMemoryServiceProvider to ensure command is available
        $this->app->register(\Vizra\VizraADK\Providers\VectorMemoryServiceProvider::class);

        // Mock the VectorMemoryManager service
        $this->mockVectorMemoryManager = Mockery::mock(VectorMemoryManager::class);
        $this->app->instance(VectorMemoryManager::class, $this->mockVectorMemoryManager);
    }

    /**
     * Test that the command calls VectorMemoryManager::addDocument with correct parameter structure
     * This ensures the fix for "Unknown named parameter $agentName" doesn't regress
     */
    public function test_command_calls_vector_memory_manager_with_correct_parameters()
    {
        // Arrange
        $tempFile = sys_get_temp_dir() . '/test.csv';
        $content = "Question,Answer\nWhat is Laravel?,A PHP framework";
        File::put($tempFile, $content);

        $mockMemories = Collection::make([
            new VectorMemory(['id' => 1]),
        ]);

        // Critical test: Verify the method is called with:
        // 1. First param: agentClass (not agentName)
        // 2. Second param: array with 'source_id' (not 'sourceId')
        $this->mockVectorMemoryManager->shouldReceive('addDocument')
            ->once()
            ->withArgs(function ($agentClass, $contentOrArray) use ($content) {
                return $agentClass === 'test_agent' &&
                    is_array($contentOrArray) &&
                    $contentOrArray['content'] === $content &&
                    array_key_exists('source_id', $contentOrArray) && // Must be 'source_id', not 'sourceId'
                    $contentOrArray['namespace'] === 'default';
            })
            ->andReturn($mockMemories);

        $this->mockVectorMemoryManager->shouldReceive('getStatistics')
            ->once()
            ->andReturn([
                'total_memories' => 1,
                'total_tokens' => 50,
                'providers' => ['openai' => 1],
            ]);

        // Act
        $this->artisan('vector:store', [
            'agent' => 'test_agent',
            '--file' => $tempFile,
        ])
            ->assertSuccessful();

        // Cleanup
        File::delete($tempFile);
    }

    /**
     * Test that the command works with all optional parameters
     */
    public function test_command_passes_all_optional_parameters_correctly()
    {
        // Arrange
        $content = 'Test content';
        $metadata = ['key' => 'value'];
        $mockMemories = Collection::make([new VectorMemory(['id' => 1])]);

        $this->mockVectorMemoryManager->shouldReceive('addDocument')
            ->once()
            ->withArgs(function ($agentClass, $contentOrArray) use ($content, $metadata) {
                return $agentClass === 'my_agent' &&
                    $contentOrArray['content'] === $content &&
                    $contentOrArray['metadata'] === $metadata &&
                    $contentOrArray['namespace'] === 'custom' &&
                    $contentOrArray['source'] === 'manual' &&
                    $contentOrArray['source_id'] === 'id-123';
            })
            ->andReturn($mockMemories);

        $this->mockVectorMemoryManager->shouldReceive('getStatistics')
            ->andReturn(['total_memories' => 1, 'total_tokens' => 10, 'providers' => []]);

        // Act
        $this->artisan('vector:store', [
            'agent' => 'my_agent',
            '--content' => $content,
            '--namespace' => 'custom',
            '--source' => 'manual',
            '--source-id' => 'id-123',
            '--metadata' => json_encode($metadata),
        ])
            ->assertSuccessful();
    }
}