<?php

namespace Vizra\VizraADK\Providers;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;
use Vizra\VizraADK\Traits\HasLogging;
use RuntimeException;
use Vizra\VizraADK\Console\Commands\VectorMemorySearch;
use Vizra\VizraADK\Console\Commands\VectorMemoryStats;
use Vizra\VizraADK\Console\Commands\VectorMemoryStore;
use Vizra\VizraADK\Contracts\EmbeddingProviderInterface;
use Vizra\VizraADK\Services\DocumentChunker;
use Vizra\VizraADK\Services\VectorMemoryManager;

class VectorMemoryServiceProvider extends ServiceProvider
{
    use HasLogging;
    /**
     * Register services.
     */
    public function register(): void
    {
        // Check if package is globally disabled
        if (! config('vizra-adk.enabled', true)) {
            return;
        }

        // Register the embedding provider based on configuration
        $this->app->singleton(EmbeddingProviderInterface::class, function ($app) {
            $provider = config('vizra-adk.vector_memory.embedding_provider', 'openai');

            try {
                return match ($provider) {
                    'openai' => new OpenAIEmbeddingProvider,
                    'cohere' => new CohereEmbeddingProvider,
                    'ollama' => new OllamaEmbeddingProvider,
                    'gemini', 'google' => new GeminiEmbeddingProvider,
                    default => throw new RuntimeException("Unsupported embedding provider: {$provider}"),
                };
            } catch (\Exception $e) {
                // Log the error but provide a fallback
                $this->logWarning('Failed to initialize embedding provider', [
                    'provider' => $provider,
                    'error' => $e->getMessage(),
                ], 'vector_memory');
                throw $e;
            }
        });

        // Register the document chunker
        $this->app->singleton(DocumentChunker::class, function ($app) {
            return new DocumentChunker;
        });

        // Register the vector memory manager
        $this->app->singleton(VectorMemoryManager::class, function ($app) {
            return new VectorMemoryManager(
                $app->make(EmbeddingProviderInterface::class),
                $app->make(DocumentChunker::class)
            );
        });

        // Register console commands
        if ($this->app->runningInConsole()) {
            $this->commands([
                VectorMemoryStore::class,
                VectorMemorySearch::class,
                VectorMemoryStats::class,
            ]);
        }
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Check if package is globally enabled
        if (! config('vizra-adk.enabled', true)) {
            return;
        }

        // Check if vector memory is enabled
        if (! config('vizra-adk.vector_memory.enabled', true)) {
            return;
        }

        // Only validate configuration when not running artisan commands that don't need it
        $skipValidation = $this->app->runningInConsole() &&
                          in_array(request()->server('argv.1') ?? '', ['route:list', 'route:cache', 'config:cache']);

        if (! $skipValidation) {
            try {
                $this->validateConfiguration();

                // Log the vector memory provider being used
                $provider = config('vizra-adk.vector_memory.embedding_provider');
                $model = config("vizra-adk.vector_memory.embedding_models.{$provider}");

                $this->logInfo('Vector Memory initialized', [
                    'provider' => $provider,
                    'model' => $model,
                    'driver' => config('vizra-adk.vector_memory.driver'),
                ], 'vector_memory');
            } catch (\Exception $e) {
                // Log the error but don't fail the application boot
                $this->logWarning('Vector Memory configuration validation failed', [
                    'error' => $e->getMessage(),
                ], 'vector_memory');
            }
        }
    }

    /**
     * Validate the vector memory configuration.
     */
    protected function validateConfiguration(): void
    {
        $provider = config('vizra-adk.vector_memory.embedding_provider');
        $driver = config('vizra-adk.vector_memory.driver');

        // Validate embedding provider
        $supportedProviders = ['openai', 'cohere', 'ollama', 'gemini', 'google'];
        if (! in_array($provider, $supportedProviders)) {
            throw new RuntimeException("Unsupported embedding provider: {$provider}. Supported: ".implode(', ', $supportedProviders));
        }

        // Validate driver
        $supportedDrivers = ['pgvector', 'meilisearch'];
        if (! in_array($driver, $supportedDrivers)) {
            throw new RuntimeException("Unsupported vector driver: {$driver}. Supported: ".implode(', ', $supportedDrivers));
        }

        // Validate provider-specific configuration
        $this->validateProviderConfiguration($provider);

        // Validate driver-specific configuration
        $this->validateDriverConfiguration($driver);
    }

    /**
     * Validate provider-specific configuration.
     */
    protected function validateProviderConfiguration(string $provider): void
    {
        switch ($provider) {
            case 'openai':
                if (! config('services.openai.key') && ! env('OPENAI_API_KEY')) {
                    throw new RuntimeException('OpenAI API key is required. Set OPENAI_API_KEY in your .env file.');
                }
                break;

            case 'cohere':
                if (! config('services.cohere.key') && ! env('COHERE_API_KEY')) {
                    throw new RuntimeException('Cohere API key is required. Set COHERE_API_KEY in your .env file.');
                }
                break;

            case 'ollama':
                // Check if Ollama is available (only in production)
                if (app()->environment('production')) {
                    $ollamaProvider = new OllamaEmbeddingProvider;
                    if (! $ollamaProvider->isAvailable()) {
                        $this->logWarning('Ollama service not available', [
                            'url' => config('services.ollama.url', env('OLLAMA_URL', 'http://localhost:11434')),
                        ], 'vector_memory');
                    }
                }
                break;

            case 'gemini':
            case 'google':
                if (! config('services.gemini.key') && ! env('GEMINI_API_KEY')) {
                    throw new RuntimeException('Gemini API key is required. Set GEMINI_API_KEY in your .env file.');
                }
                break;
        }
    }

    /**
     * Validate driver-specific configuration.
     */
    protected function validateDriverConfiguration(string $driver): void
    {
        switch ($driver) {
            case 'meilisearch':
                $host = config('vizra-adk.vector_memory.drivers.meilisearch.host');

                if (! $host) {
                    throw new RuntimeException('Meilisearch driver requires host configuration.');
                }

                // Check if Meilisearch is available (only in production)
                if (app()->environment('production')) {
                    try {
                        $response = \Illuminate\Support\Facades\Http::timeout(5)->get($host.'/health');
                        if (! $response->successful()) {
                            $this->logWarning('Meilisearch service not available', ['host' => $host], 'vector_memory');
                        }
                    } catch (\Exception $e) {
                        $this->logWarning('Failed to connect to Meilisearch', [
                            'host' => $host,
                            'error' => $e->getMessage(),
                        ], 'vector_memory');
                    }
                }
                break;

            case 'pgvector':
                $connection = config('vizra-adk.vector_memory.drivers.pgvector.connection', 'pgsql');
                $dbConfig = config("database.connections.{$connection}");

                if (! $dbConfig || $dbConfig['driver'] !== 'pgsql') {
                    throw new RuntimeException("pgvector driver requires a PostgreSQL database connection. Check connection: {$connection}");
                }
                break;

        }
    }
}
