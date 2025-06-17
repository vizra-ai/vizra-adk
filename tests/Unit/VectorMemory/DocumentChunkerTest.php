<?php

namespace Vizra\VizraAdk\Tests\Unit\VectorMemory;

use Vizra\VizraAdk\Tests\TestCase;
use Vizra\VizraAdk\Services\DocumentChunker;
use Illuminate\Support\Facades\Config;

class DocumentChunkerTest extends TestCase
{
    protected DocumentChunker $chunker;

    protected function setUp(): void
    {
        parent::setUp();
        
        Config::set('vizra-adk.vector_memory.chunking', [
            'strategy' => 'sentence',
            'chunk_size' => 100,
            'overlap' => 20,
        ]);
        
        $this->chunker = new DocumentChunker();
    }

    public function test_chunks_by_sentence()
    {
        // Arrange - set smaller chunk size to force splitting
        Config::set('vizra-adk.vector_memory.chunking.chunk_size', 30);
        $chunker = new DocumentChunker();
        $content = 'First sentence here. Second sentence follows. Third sentence ends it.';

        // Act
        $chunks = $chunker->chunk($content);

        // Assert
        $this->assertIsArray($chunks);
        $this->assertGreaterThan(1, count($chunks));
        $this->assertStringContainsString('First sentence', $chunks[0]);
    }

    public function test_chunks_by_paragraph()
    {
        // Arrange - set smaller chunk size to force splitting
        Config::set('vizra-adk.vector_memory.chunking.strategy', 'paragraph');
        Config::set('vizra-adk.vector_memory.chunking.chunk_size', 30);
        $chunker = new DocumentChunker();
        
        $content = "First paragraph here.\n\nSecond paragraph follows.\n\nThird paragraph ends it.";

        // Act
        $chunks = $chunker->chunk($content);

        // Assert
        $this->assertIsArray($chunks);
        $this->assertGreaterThan(1, count($chunks));
    }

    public function test_chunks_by_fixed_size()
    {
        // Arrange
        Config::set('vizra-adk.vector_memory.chunking.strategy', 'fixed');
        Config::set('vizra-adk.vector_memory.chunking.chunk_size', 50);
        $chunker = new DocumentChunker();
        
        $content = str_repeat('This is a test sentence. ', 10);

        // Act
        $chunks = $chunker->chunk($content);

        // Assert
        $this->assertIsArray($chunks);
        $this->assertGreaterThan(1, count($chunks));
        
        foreach ($chunks as $chunk) {
            $this->assertLessThanOrEqual(60, strlen($chunk)); // Some flexibility for word boundaries
        }
    }

    public function test_handles_empty_content()
    {
        // Act
        $chunks = $this->chunker->chunk('');

        // Assert
        $this->assertIsArray($chunks);
        $this->assertEmpty($chunks);
    }

    public function test_handles_whitespace_only_content()
    {
        // Act
        $chunks = $this->chunker->chunk("   \n\t   ");

        // Assert
        $this->assertIsArray($chunks);
        $this->assertEmpty($chunks);
    }

    public function test_validates_chunks()
    {
        // Arrange
        $invalidChunks = [
            '',           // Empty
            '   ',        // Whitespace only
            'ab',         // Too short
            '!!!@#$',     // No alphanumeric content
            'Valid chunk content here'  // Valid
        ];

        // Act
        $validChunks = $this->chunker->validateChunks($invalidChunks);

        // Assert
        $this->assertIsArray($validChunks);
        $this->assertCount(1, $validChunks);
        $this->assertEquals('Valid chunk content here', $validChunks[0]);
    }

    public function test_estimates_optimal_chunk_size()
    {
        // Arrange
        $shortContent = 'Short content';
        $codeContent = 'function test() { return $var->method(); }';
        $normalContent = str_repeat('This is normal text content. ', 50);

        // Act
        $shortSize = $this->chunker->getOptimalChunkSize($shortContent);
        $codeSize = $this->chunker->getOptimalChunkSize($codeContent);
        $normalSize = $this->chunker->getOptimalChunkSize($normalContent);

        // Assert
        $this->assertEquals(strlen($shortContent), $shortSize);
        $this->assertLessThan($normalSize, $codeSize); // Code should get smaller chunks
        $this->assertEquals(100, $normalSize); // Normal content uses configured size
    }
}