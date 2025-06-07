<?php

namespace AaronLumsden\LaravelAiADK\Services;

class DocumentChunker
{
    protected string $strategy;
    protected int $chunkSize;
    protected int $overlap;

    public function __construct()
    {
        $this->strategy = config('agent-adk.vector_memory.chunking.strategy', 'sentence');
        $this->chunkSize = config('agent-adk.vector_memory.chunking.chunk_size', 1000);
        $this->overlap = config('agent-adk.vector_memory.chunking.overlap', 200);
    }

    /**
     * Chunk text content based on the configured strategy.
     */
    public function chunk(string $content): array
    {
        $content = trim($content);
        
        if (empty($content)) {
            return [];
        }

        return match ($this->strategy) {
            'sentence' => $this->chunkBySentence($content),
            'paragraph' => $this->chunkByParagraph($content),
            'fixed' => $this->chunkByFixedSize($content),
            default => $this->chunkBySentence($content),
        };
    }

    /**
     * Chunk by sentences, respecting chunk size limits.
     */
    protected function chunkBySentence(string $content): array
    {
        // Split into sentences using regex that handles common sentence endings
        $sentences = preg_split('/(?<=[.!?])\s+/', $content, -1, PREG_SPLIT_NO_EMPTY);
        
        if (empty($sentences)) {
            return [$content];
        }

        $chunks = [];
        $currentChunk = '';
        
        foreach ($sentences as $sentence) {
            $sentence = trim($sentence);
            
            // If adding this sentence would exceed chunk size, save current chunk
            if (!empty($currentChunk) && strlen($currentChunk . ' ' . $sentence) > $this->chunkSize) {
                $chunks[] = trim($currentChunk);
                
                // Start new chunk with overlap if configured
                $currentChunk = $this->getOverlapContent($currentChunk) . $sentence;
            } else {
                $currentChunk = empty($currentChunk) ? $sentence : $currentChunk . ' ' . $sentence;
            }
        }
        
        // Add the final chunk if it has content
        if (!empty(trim($currentChunk))) {
            $chunks[] = trim($currentChunk);
        }
        
        return array_filter($chunks, fn($chunk) => !empty(trim($chunk)));
    }

    /**
     * Chunk by paragraphs, respecting chunk size limits.
     */
    protected function chunkByParagraph(string $content): array
    {
        // Split by double newlines (paragraph breaks)
        $paragraphs = preg_split('/\n\s*\n/', $content, -1, PREG_SPLIT_NO_EMPTY);
        
        if (empty($paragraphs)) {
            return [$content];
        }

        $chunks = [];
        $currentChunk = '';
        
        foreach ($paragraphs as $paragraph) {
            $paragraph = trim($paragraph);
            
            // If this single paragraph is too large, chunk it by sentences
            if (strlen($paragraph) > $this->chunkSize) {
                // Save current chunk if it has content
                if (!empty($currentChunk)) {
                    $chunks[] = trim($currentChunk);
                    $currentChunk = '';
                }
                
                // Chunk the large paragraph by sentences
                $sentenceChunks = $this->chunkBySentence($paragraph);
                $chunks = array_merge($chunks, $sentenceChunks);
                continue;
            }
            
            // If adding this paragraph would exceed chunk size, save current chunk
            if (!empty($currentChunk) && strlen($currentChunk . "\n\n" . $paragraph) > $this->chunkSize) {
                $chunks[] = trim($currentChunk);
                $currentChunk = $paragraph;
            } else {
                $currentChunk = empty($currentChunk) ? $paragraph : $currentChunk . "\n\n" . $paragraph;
            }
        }
        
        // Add the final chunk if it has content
        if (!empty(trim($currentChunk))) {
            $chunks[] = trim($currentChunk);
        }
        
        return array_filter($chunks, fn($chunk) => !empty(trim($chunk)));
    }

    /**
     * Chunk by fixed character size with overlap.
     */
    protected function chunkByFixedSize(string $content): array
    {
        $chunks = [];
        $contentLength = strlen($content);
        $position = 0;
        
        while ($position < $contentLength) {
            $chunkEnd = min($position + $this->chunkSize, $contentLength);
            
            // Try to break at word boundary if we're not at the end
            if ($chunkEnd < $contentLength) {
                $nextSpace = strpos($content, ' ', $chunkEnd);
                $prevSpace = strrpos($content, ' ', $chunkEnd - $contentLength);
                
                // Choose the closer word boundary
                if ($nextSpace !== false && $prevSpace !== false) {
                    $chunkEnd = ($nextSpace - $chunkEnd) < ($chunkEnd - $prevSpace) ? $nextSpace : $prevSpace;
                } elseif ($prevSpace !== false) {
                    $chunkEnd = $prevSpace;
                } elseif ($nextSpace !== false) {
                    $chunkEnd = $nextSpace;
                }
            }
            
            $chunk = substr($content, $position, $chunkEnd - $position);
            $chunks[] = trim($chunk);
            
            // Move position forward, accounting for overlap
            $position = max($position + 1, $chunkEnd - $this->overlap);
        }
        
        return array_filter($chunks, fn($chunk) => !empty(trim($chunk)));
    }

    /**
     * Get overlap content from the end of a chunk.
     */
    protected function getOverlapContent(string $chunk): string
    {
        if ($this->overlap <= 0 || strlen($chunk) <= $this->overlap) {
            return '';
        }
        
        $overlapText = substr($chunk, -$this->overlap);
        
        // Try to start overlap at word boundary
        $firstSpace = strpos($overlapText, ' ');
        if ($firstSpace !== false && $firstSpace < $this->overlap / 2) {
            $overlapText = substr($overlapText, $firstSpace + 1);
        }
        
        return empty(trim($overlapText)) ? '' : trim($overlapText) . ' ';
    }

    /**
     * Estimate optimal chunk size based on content type.
     */
    public function getOptimalChunkSize(string $content): int
    {
        $length = strlen($content);
        
        // For very short content, use as-is
        if ($length <= 500) {
            return $length;
        }
        
        // For code or structured data (lots of special characters)
        $specialCharRatio = (strlen($content) - strlen(preg_replace('/[^a-zA-Z0-9\s]/', '', $content))) / $length;
        if ($specialCharRatio > 0.3) {
            return min(800, $this->chunkSize); // Smaller chunks for code
        }
        
        // For regular text, use configured size
        return $this->chunkSize;
    }

    /**
     * Validate that chunks are reasonable.
     */
    public function validateChunks(array $chunks): array
    {
        $validChunks = [];
        
        foreach ($chunks as $chunk) {
            $chunk = trim($chunk);
            
            // Skip empty or very short chunks
            if (strlen($chunk) < 10) {
                continue;
            }
            
            // Skip chunks that are mostly whitespace or special characters
            $alphanumericRatio = strlen(preg_replace('/[^a-zA-Z0-9]/', '', $chunk)) / strlen($chunk);
            if ($alphanumericRatio < 0.1) {
                continue;
            }
            
            $validChunks[] = $chunk;
        }
        
        return $validChunks;
    }
}