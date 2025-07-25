<?php

namespace Vizra\VizraADK\Providers;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Vizra\VizraADK\Contracts\EmbeddingProviderInterface;

class OllamaEmbeddingProvider implements EmbeddingProviderInterface
{
    protected string $baseUrl;

    protected string $model;

    protected array $dimensions;

    public function __construct()
    {
        $this->baseUrl = config('services.ollama.url', env('OLLAMA_URL', 'http://localhost:11434'));
        $this->model = config('vizra-adk.vector_memory.embedding_models.ollama', 'nomic-embed-text');

        $this->dimensions = config('vizra-adk.vector_memory.dimensions', [
            'nomic-embed-text' => 768,
            'mxbai-embed-large' => 1024,
            'all-minilm' => 384,
        ]);
    }

    public function embed(string|array $input): array
    {
        $inputs = is_array($input) ? $input : [$input];
        $embeddings = [];

        foreach ($inputs as $text) {
            if (strlen($text) > $this->getMaxInputLength()) {
                throw new RuntimeException("Input text exceeds maximum length of {$this->getMaxInputLength()} characters");
            }

            try {
                $response = Http::timeout(120)->post($this->baseUrl.'/api/embeddings', [
                    'model' => $this->model,
                    'prompt' => $text,
                ]);

                if (! $response->successful()) {
                    Log::error('Ollama embedding API error', [
                        'status' => $response->status(),
                        'response' => $response->body(),
                        'url' => $this->baseUrl,
                    ]);
                    throw new RuntimeException('Ollama embedding API request failed: '.$response->body());
                }

                $data = $response->json();

                if (! isset($data['embedding']) || ! is_array($data['embedding'])) {
                    throw new RuntimeException('Invalid response format from Ollama embedding API');
                }

                $embeddings[] = $data['embedding'];

            } catch (\Exception $e) {
                Log::error('Ollama embedding generation failed', [
                    'error' => $e->getMessage(),
                    'model' => $this->model,
                    'text_length' => strlen($text),
                    'url' => $this->baseUrl,
                ]);
                throw new RuntimeException('Failed to generate Ollama embeddings: '.$e->getMessage());
            }
        }

        return $embeddings;
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
        return 'ollama';
    }

    public function getMaxInputLength(): int
    {
        // Ollama models typically support longer contexts
        // But for embedding, we'll be conservative
        return 8000;
    }

    public function estimateCost(string|array $input): float
    {
        // Ollama is free when running locally
        return 0.0;
    }

    /**
     * Check if the Ollama service is available.
     */
    public function isAvailable(): bool
    {
        try {
            $response = Http::timeout(5)->get($this->baseUrl.'/api/tags');

            return $response->successful();
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Check if the specified model is available in Ollama.
     */
    public function isModelAvailable(): bool
    {
        try {
            $response = Http::timeout(5)->get($this->baseUrl.'/api/tags');

            if (! $response->successful()) {
                return false;
            }

            $data = $response->json();

            if (! isset($data['models']) || ! is_array($data['models'])) {
                return false;
            }

            foreach ($data['models'] as $model) {
                if (isset($model['name']) && str_contains($model['name'], $this->model)) {
                    return true;
                }
            }

            return false;

        } catch (\Exception $e) {
            Log::warning('Failed to check Ollama model availability', [
                'error' => $e->getMessage(),
                'model' => $this->model,
            ]);

            return false;
        }
    }
}
