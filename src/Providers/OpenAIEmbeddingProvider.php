<?php

namespace Vizra\VizraADK\Providers;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Vizra\VizraADK\Contracts\EmbeddingProviderInterface;
use Vizra\VizraADK\Traits\HasLogging;

class OpenAIEmbeddingProvider implements EmbeddingProviderInterface
{
    use HasLogging;
    protected string $apiKey;

    protected string $model;

    protected string $baseUrl;

    protected array $dimensions;

    public function __construct()
    {
        $apiKey = config('services.openai.key') ?? env('OPENAI_API_KEY');

        if (empty($apiKey)) {
            throw new \RuntimeException('OpenAI API key is required. Set OPENAI_API_KEY environment variable or services.openai.key config.');
        }

        $this->apiKey = $apiKey;
        $this->model = config('vizra-adk.vector_memory.embedding_models.openai', 'text-embedding-3-small');
        $this->baseUrl = config('services.openai.url', 'https://api.openai.com/v1');

        $this->dimensions = config('vizra-adk.vector_memory.dimensions', [
            'text-embedding-3-small' => 1536,
            'text-embedding-3-large' => 3072,
            'text-embedding-ada-002' => 1536,
        ]);

        if (! $this->apiKey) {
            throw new RuntimeException('OpenAI API key is required for embedding generation');
        }
    }

    public function embed(string|array $input): array
    {
        $inputs = is_array($input) ? $input : [$input];

        // Validate input lengths
        foreach ($inputs as $text) {
            if (strlen($text) > $this->getMaxInputLength()) {
                throw new RuntimeException("Input text exceeds maximum length of {$this->getMaxInputLength()} characters");
            }
        }

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer '.$this->apiKey,
                'Content-Type' => 'application/json',
            ])->timeout(60)->post($this->baseUrl.'/embeddings', [
                'model' => $this->model,
                'input' => $inputs,
                'encoding_format' => 'float',
            ]);

            if (! $response->successful()) {
                $this->logError('OpenAI embedding API error', [
                    'status' => $response->status(),
                    'response' => $response->body(),
                ], 'vector_memory');
                throw new RuntimeException('OpenAI embedding API request failed: '.$response->body());
            }

            $data = $response->json();

            if (! isset($data['data']) || ! is_array($data['data'])) {
                throw new RuntimeException('Invalid response format from OpenAI embedding API');
            }

            // Extract embeddings from response
            $embeddings = [];
            foreach ($data['data'] as $item) {
                if (! isset($item['embedding'])) {
                    throw new RuntimeException('Missing embedding in API response');
                }
                $embeddings[] = $item['embedding'];
            }

            // Log usage for cost tracking
            if (isset($data['usage'])) {
                $this->logInfo('OpenAI embedding usage', [
                    'model' => $this->model,
                    'prompt_tokens' => $data['usage']['prompt_tokens'] ?? 0,
                    'total_tokens' => $data['usage']['total_tokens'] ?? 0,
                ], 'vector_memory');
            }

            return $embeddings;

        } catch (\Exception $e) {
            $this->logError('OpenAI embedding generation failed', [
                'error' => $e->getMessage(),
                'model' => $this->model,
                'input_count' => count($inputs),
            ], 'vector_memory');
            throw new RuntimeException('Failed to generate OpenAI embeddings: '.$e->getMessage());
        }
    }

    public function getDimensions(): int
    {
        return $this->dimensions[$this->model] ?? 1536;
    }

    public function getModel(): string
    {
        return $this->model;
    }

    public function getProviderName(): string
    {
        return 'openai';
    }

    public function getMaxInputLength(): int
    {
        // OpenAI has a token limit, but we'll use character limit as approximation
        // text-embedding-3-* models support up to 8192 tokens (~32,000 characters)
        return 30000;
    }

    public function estimateCost(string|array $input): float
    {
        $inputs = is_array($input) ? $input : [$input];
        $totalTokens = 0;

        // Rough estimation: ~4 characters per token for English text
        foreach ($inputs as $text) {
            $totalTokens += ceil(strlen($text) / 4);
        }

        // OpenAI pricing (as of 2024):
        // text-embedding-3-small: $0.00002 per 1K tokens
        // text-embedding-3-large: $0.00013 per 1K tokens
        $pricePerThousandTokens = match ($this->model) {
            'text-embedding-3-small' => 0.00002,
            'text-embedding-3-large' => 0.00013,
            'text-embedding-ada-002' => 0.0001,
            default => 0.00002,
        };

        return ($totalTokens / 1000) * $pricePerThousandTokens;
    }

    /**
     * Validate that the model is supported.
     */
    protected function validateModel(): void
    {
        $supportedModels = [
            'text-embedding-3-small',
            'text-embedding-3-large',
            'text-embedding-ada-002',
        ];

        if (! in_array($this->model, $supportedModels)) {
            throw new RuntimeException("Unsupported OpenAI embedding model: {$this->model}");
        }
    }
}
