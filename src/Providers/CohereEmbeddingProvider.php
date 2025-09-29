<?php

namespace Vizra\VizraADK\Providers;

use Illuminate\Support\Facades\Http;
use RuntimeException;
use Vizra\VizraADK\Contracts\EmbeddingProviderInterface;
use Vizra\VizraADK\Traits\HasLogging;

class CohereEmbeddingProvider implements EmbeddingProviderInterface
{
    use HasLogging;
    protected string $apiKey;

    protected string $model;

    protected string $baseUrl;

    protected array $dimensions;

    public function __construct()
    {
        $apiKey = config('services.cohere.key') ?? env('COHERE_API_KEY');

        if (empty($apiKey)) {
            throw new \RuntimeException('Cohere API key is required. Set COHERE_API_KEY environment variable or services.cohere.key config.');
        }

        $this->apiKey = $apiKey;
        $this->model = config('vizra-adk.vector_memory.embedding_models.cohere', 'embed-english-v3.0');
        $this->baseUrl = config('services.cohere.url', 'https://api.cohere.ai/v1');

        $this->dimensions = config('vizra-adk.vector_memory.dimensions', [
            'embed-english-v3.0' => 1024,
            'embed-multilingual-v3.0' => 1024,
            'embed-english-light-v3.0' => 384,
            'embed-multilingual-light-v3.0' => 384,
        ]);

        if (! $this->apiKey) {
            throw new RuntimeException('Cohere API key is required for embedding generation');
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
            ])->timeout(60)->post($this->baseUrl.'/embed', [
                'model' => $this->model,
                'texts' => $inputs,
                'input_type' => 'search_document', // For retrieval/search use case
                'embedding_types' => ['float'],
            ]);

            if (! $response->successful()) {
                $this->logError('Cohere embedding API error', [
                    'status' => $response->status(),
                    'response' => $response->body(),
                ], 'vector_memory');
                throw new RuntimeException('Cohere embedding API request failed: '.$response->body());
            }

            $data = $response->json();

            if (! isset($data['embeddings']['float']) || ! is_array($data['embeddings']['float'])) {
                throw new RuntimeException('Invalid response format from Cohere embedding API');
            }

            $embeddings = $data['embeddings']['float'];

            // Log usage for cost tracking
            if (isset($data['meta']['billed_units'])) {
                $this->logInfo('Cohere embedding usage', [
                    'model' => $this->model,
                    'input_tokens' => $data['meta']['billed_units']['input_tokens'] ?? 0,
                ], 'vector_memory');
            }

            return $embeddings;

        } catch (\Exception $e) {
            $this->logError('Cohere embedding generation failed', [
                'error' => $e->getMessage(),
                'model' => $this->model,
                'input_count' => count($inputs),
            ], 'vector_memory');
            throw new RuntimeException('Failed to generate Cohere embeddings: '.$e->getMessage());
        }
    }

    public function getDimensions(): int
    {
        return $this->dimensions[$this->model] ?? 1024;
    }

    public function getModel(): string
    {
        return $this->model;
    }

    public function getProviderName(): string
    {
        return 'cohere';
    }

    public function getMaxInputLength(): int
    {
        // Cohere supports up to 512 tokens per input, ~2000 characters
        return 2000;
    }

    public function estimateCost(string|array $input): float
    {
        $inputs = is_array($input) ? $input : [$input];
        $totalTokens = 0;

        // Rough estimation: ~4 characters per token for English text
        foreach ($inputs as $text) {
            $totalTokens += ceil(strlen($text) / 4);
        }

        // Cohere pricing (as of 2024):
        // embed-english-v3.0: $0.0001 per 1K tokens
        // embed-multilingual-v3.0: $0.0001 per 1K tokens
        // embed-english-light-v3.0: $0.0001 per 1K tokens
        // embed-multilingual-light-v3.0: $0.0001 per 1K tokens
        $pricePerThousandTokens = 0.0001;

        return ($totalTokens / 1000) * $pricePerThousandTokens;
    }
}
