<?php

namespace AaronLumsden\LaravelAgentADK\Providers;

use AaronLumsden\LaravelAgentADK\Contracts\EmbeddingProviderInterface;
use AaronLumsden\LaravelAgentADK\Providers\OpenAIEmbeddingProvider;
use AaronLumsden\LaravelAgentADK\Providers\CohereEmbeddingProvider;
use AaronLumsden\LaravelAgentADK\Providers\OllamaEmbeddingProvider;
use AaronLumsden\LaravelAgentADK\Services\VectorMemoryManager;
use AaronLumsden\LaravelAgentADK\Services\DocumentChunker;
use AaronLumsden\LaravelAgentADK\Console\Commands\VectorMemoryStore;
use AaronLumsden\LaravelAgentADK\Console\Commands\VectorMemorySearch;
use AaronLumsden\LaravelAgentADK\Console\Commands\VectorMemoryStats;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class VectorMemoryServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // Register the embedding provider based on configuration
        $this->app->singleton(EmbeddingProviderInterface::class, function ($app) {
            $provider = config('agent-adk.vector_memory.embedding_provider', 'openai');
            
            try {
                return match ($provider) {
                    'openai' => new OpenAIEmbeddingProvider(),
                    'cohere' => new CohereEmbeddingProvider(),
                    'ollama' => new OllamaEmbeddingProvider(),
                    default => throw new RuntimeException("Unsupported embedding provider: {$provider}"),
                };
            } catch (\Exception $e) {
                // Log the error but provide a fallback
                Log::warning('Failed to initialize embedding provider', [
                    'provider' => $provider,
                    'error' => $e->getMessage()
                ]);
                throw $e;
            }
        });

        // Register the document chunker
        $this->app->singleton(DocumentChunker::class, function ($app) {
            return new DocumentChunker();
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
        // Check if vector memory is enabled
        if (!config('agent-adk.vector_memory.enabled', true)) {
            return;
        }

        // Only validate configuration when not running artisan commands that don't need it
        $skipValidation = $this->app->runningInConsole() && 
                          in_array(request()->server('argv.1') ?? '', ['route:list', 'route:cache', 'config:cache']);

        if (!$skipValidation) {
            try {
                $this->validateConfiguration();
                
                // Log the vector memory provider being used
                $provider = config('agent-adk.vector_memory.embedding_provider');
                $model = config("agent-adk.vector_memory.embedding_models.{$provider}");
                
                Log::info('Vector Memory initialized', [
                    'provider' => $provider,
                    'model' => $model,
                    'driver' => config('agent-adk.vector_memory.driver'),
                ]);
            } catch (\Exception $e) {
                // Log the error but don't fail the application boot
                Log::warning('Vector Memory configuration validation failed', [
                    'error' => $e->getMessage()
                ]);
            }
        }
    }

    /**
     * Validate the vector memory configuration.
     */
    protected function validateConfiguration(): void
    {
        $provider = config('agent-adk.vector_memory.embedding_provider');
        $driver = config('agent-adk.vector_memory.driver');

        // Validate embedding provider
        $supportedProviders = ['openai', 'cohere', 'ollama', 'gemini'];
        if (!in_array($provider, $supportedProviders)) {
            throw new RuntimeException("Unsupported embedding provider: {$provider}. Supported: " . implode(', ', $supportedProviders));
        }

        // Validate driver
        $supportedDrivers = ['pgvector', 'meilisearch', 'qdrant', 'in_memory'];
        if (!in_array($driver, $supportedDrivers)) {
            throw new RuntimeException("Unsupported vector driver: {$driver}. Supported: " . implode(', ', $supportedDrivers));
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
                if (!config('services.openai.key') && !env('OPENAI_API_KEY')) {
                    throw new RuntimeException('OpenAI API key is required. Set OPENAI_API_KEY in your .env file.');
                }
                break;

            case 'cohere':
                if (!config('services.cohere.key') && !env('COHERE_API_KEY')) {
                    throw new RuntimeException('Cohere API key is required. Set COHERE_API_KEY in your .env file.');
                }
                break;

            case 'ollama':
                // Check if Ollama is available (only in production)
                if (app()->environment('production')) {
                    $ollamaProvider = new OllamaEmbeddingProvider();
                    if (!$ollamaProvider->isAvailable()) {
                        Log::warning('Ollama service not available', [
                            'url' => config('services.ollama.url', env('OLLAMA_URL', 'http://localhost:11434'))
                        ]);
                    }
                }
                break;

            case 'gemini':
                if (!config('services.gemini.key') && !env('GEMINI_API_KEY')) {
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
                $host = config('agent-adk.vector_memory.drivers.meilisearch.host');
                
                if (!$host) {
                    throw new RuntimeException('Meilisearch driver requires host configuration.');
                }
                
                // Check if Meilisearch is available (only in production)
                if (app()->environment('production')) {
                    try {
                        $response = \Illuminate\Support\Facades\Http::timeout(5)->get($host . '/health');
                        if (!$response->successful()) {
                            Log::warning('Meilisearch service not available', ['host' => $host]);
                        }
                    } catch (\Exception $e) {
                        Log::warning('Failed to connect to Meilisearch', [
                            'host' => $host,
                            'error' => $e->getMessage()
                        ]);
                    }
                }
                break;
                
            case 'pgvector':
                $connection = config('agent-adk.vector_memory.drivers.pgvector.connection', 'pgsql');
                $dbConfig = config("database.connections.{$connection}");
                
                if (!$dbConfig || $dbConfig['driver'] !== 'pgsql') {
                    throw new RuntimeException("pgvector driver requires a PostgreSQL database connection. Check connection: {$connection}");
                }
                break;

            case 'qdrant':
                $host = config('agent-adk.vector_memory.drivers.qdrant.host');
                $port = config('agent-adk.vector_memory.drivers.qdrant.port');
                
                if (!$host || !$port) {
                    throw new RuntimeException('Qdrant driver requires host and port configuration.');
                }
                break;

            case 'in_memory':
                $storagePath = config('agent-adk.vector_memory.drivers.in_memory.storage_path');
                
                if ($storagePath && !is_writable(dirname($storagePath))) {
                    throw new RuntimeException("In-memory driver storage path is not writable: {$storagePath}");
                }
                break;
        }
    }
}