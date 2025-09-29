<?php

namespace Vizra\VizraADK\Providers;

use Illuminate\Support\Facades\Http;
use RuntimeException;
use Vizra\VizraADK\Contracts\EmbeddingProviderInterface;
use Vizra\VizraADK\Traits\HasLogging;

class GeminiEmbeddingProvider implements EmbeddingProviderInterface
{
    use HasLogging;
    protected string $apiKey;

    protected string $model;

    protected string $baseUrl;

    protected array $dimensions;

    public function __construct()
    {
        $apiKey = config('services.gemini.key') ?? env('GEMINI_API_KEY');

        if (empty($apiKey)) {
            throw new \RuntimeException('Gemini API key is required. Set GEMINI_API_KEY environment variable or services.gemini.key config.');
        }

        $this->apiKey = $apiKey;
        $this->model = config('vizra-adk.vector_memory.embedding_models.gemini', 'text-embedding-004');
        $this->baseUrl = config('services.gemini.url', 'https://generativelanguage.googleapis.com/v1beta');

        $this->dimensions = config('vizra-adk.vector_memory.dimensions', [
            'text-embedding-004' => 768,
        ]);

        if (! $this->apiKey) {
            throw new RuntimeException('Gemini API key is required for embedding generation');
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
            $embeddings = [];

            // Gemini API currently doesn't support batch embedding in a single request,
            // so we need to make individual requests
            foreach ($inputs as $text) {
                $response = Http::withHeaders([
                    'Content-Type' => 'application/json',
                ])->timeout(60)->post($this->baseUrl.'/models/'.$this->model.':embedContent?key='.$this->apiKey, [
                    'model' => 'models/'.$this->model,
                    'content' => [
                        'parts' => [
                            ['text' => $text]
                        ]
                    ],
                    'taskType' => 'RETRIEVAL_DOCUMENT',
                    'title' => 'Embedding request',
                ]);

                if (! $response->successful()) {
                    $this->logError('Gemini embedding API error', [
                        'status' => $response->status(),
                        'response' => $response->body(),
                    ], 'vector_memory');
                    throw new RuntimeException('Gemini embedding API request failed: '.$response->body());
                }

                $data = $response->json();

                if (! isset($data['embedding']['values'])) {
                    throw new RuntimeException('Invalid response format from Gemini embedding API');
                }

                $embeddings[] = $data['embedding']['values'];
            }

            // Log usage for monitoring
            $this->logInfo('Gemini embedding usage', [
                'model' => $this->model,
                'input_count' => count($inputs),
                'total_characters' => array_sum(array_map('strlen', $inputs)),
            ], 'vector_memory');

            return $embeddings;

        } catch (\Exception $e) {
            $this->logError('Gemini embedding generation failed', [
                'error' => $e->getMessage(),
                'model' => $this->model,
                'input_count' => count($inputs),
            ], 'vector_memory');
            throw new RuntimeException('Failed to generate Gemini embeddings: '.$e->getMessage());
        }
    }

    public function getDimensions(): int
    {
        return $this->dimensions[$this->model] ?? 768;
    }

    public function getModel(): string
    {
        return $this->model;
    }

    public function getProviderName(): string
    {
        return 'gemini';
    }

    public function getMaxInputLength(): int
    {
        // Gemini has generous limits for embeddings
        // text-embedding-004 supports up to 2048 tokens (~8,000 characters)
        return 8000;
    }

    public function estimateCost(string|array $input): float
    {
        $inputs = is_array($input) ? $input : [$input];
        $totalCharacters = 0;

        foreach ($inputs as $text) {
            $totalCharacters += strlen($text);
        }

        // Gemini pricing (as of 2024):
        // text-embedding-004: Free tier up to 1,500 requests/minute
        // Beyond free tier: $0.00001 per 1K characters
        $pricePerThousandCharacters = 0.00001;

        return ($totalCharacters / 1000) * $pricePerThousandCharacters;
    }

    /**
     * Validate that the model is supported.
     */
    protected function validateModel(): void
    {
        $supportedModels = [
            'text-embedding-004',
        ];

        if (! in_array($this->model, $supportedModels)) {
            throw new RuntimeException("Unsupported Gemini embedding model: {$this->model}");
        }
    }
}