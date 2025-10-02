<?php

return [
    /**
     * Master switch to enable/disable the Vizra ADK package.
     * When false, the package will not initialize or perform any operations.
     */
    'enabled' => env('VIZRA_ADK_ENABLED', true),

    /**
     * Logging configuration for the package.
     * Control how the package logs information, warnings, and errors.
     */
    'logging' => [
        /**
         * Enable or disable logging across the entire package.
         * When false, no logs will be written by any Vizra ADK component.
         */
        'enabled' => env('VIZRA_ADK_LOGGING_ENABLED', true),

        /**
         * Log level threshold. Only messages of this level or higher will be logged.
         * Options: 'debug', 'info', 'warning', 'error', 'critical', 'none'
         */
        'level' => env('VIZRA_ADK_LOGGING_LEVEL', 'warning'),

        /**
         * Specific component logging controls.
         * Fine-grained control over which components can log.
         */
        'components' => [
            'vector_memory' => env('VIZRA_ADK_LOG_VECTOR_MEMORY', false),
            'agents' => env('VIZRA_ADK_LOG_AGENTS', true),
            'tools' => env('VIZRA_ADK_LOG_TOOLS', true),
            'mcp' => env('VIZRA_ADK_LOG_MCP', true),
            'traces' => env('VIZRA_ADK_LOG_TRACES', false),
        ],
    ],

    /**
     * Default LLM provider to use with Prism-PHP.
     * This can be overridden by specific agent configurations.
     *
     * Supported providers:
     * - 'openai' - OpenAI (GPT-4, GPT-3.5, etc.)
     * - 'anthropic' - Anthropic (Claude models)
     * - 'gemini' or 'google' - Google Gemini
     * - 'deepseek' - DeepSeek AI
     * - 'ollama' - Ollama (local models like Llama, CodeLlama, Phi)
     * - 'mistral' - Mistral AI (Mistral, Mixtral models)
     * - 'groq' - Groq (Fast inference)
     * - 'xai' or 'grok' - xAI (Grok models)
     * - 'voyageai' or 'voyage' - Voyage AI (Embeddings)
     * - 'openrouter' - OpenRouter (Access 100+ models through unified API)
     */
    'default_provider' => env('VIZRA_ADK_DEFAULT_PROVIDER', 'google'),

    /**
     * Default LLM model to use with Prism-PHP.
     * This can be overridden by specific agent configurations.
     * Example: 'gemini-pro', 'gpt-4-turbo', 'claude-3-opus-20240229'
     */
    'default_model' => env('VIZRA_ADK_DEFAULT_MODEL', 'gemini-1.5-flash'),

    /**
     * Default generation parameters for LLM requests.
     * These can be overridden by specific agent configurations.
     */
    'default_generation_params' => [
        'temperature' => env('VIZRA_ADK_DEFAULT_TEMPERATURE', null), // null means use provider default
        'max_tokens' => env('VIZRA_ADK_DEFAULT_MAX_TOKENS', null),   // null means use provider default
        'top_p' => env('VIZRA_ADK_DEFAULT_TOP_P', null),             // null means use provider default
    ],

    /**
     * HTTP client configuration for LLM API calls.
     * Controls timeout settings for API requests to prevent premature timeouts.
     */
    'http' => [
        'timeout' => env('VIZRA_ADK_HTTP_TIMEOUT', 120),           // Total timeout in seconds (default: 2 minutes)
        'connect_timeout' => env('VIZRA_ADK_HTTP_CONNECT_TIMEOUT', 10), // Connection timeout in seconds
    ],

    /**
     * Provider-specific configurations.
     * Configure API keys and settings for each LLM provider.
     * 
     * Note: Most providers are configured through Prism PHP's configuration.
     * Set these environment variables in your .env file:
     * - OPENAI_API_KEY
     * - ANTHROPIC_API_KEY
     * - GEMINI_API_KEY
     * - DEEPSEEK_API_KEY
     * - MISTRAL_API_KEY
     * - GROQ_API_KEY
     * - XAI_API_KEY
     * - VOYAGEAI_API_KEY
     * - OPENROUTER_API_KEY
     * 
     * For OpenRouter:
     * - API Key: Get from https://openrouter.ai/settings
     * - Base URL: https://openrouter.ai/api/v1 (handled by Prism)
     * - Models: Use format "provider/model" (e.g., "openai/gpt-4", "anthropic/claude-3-opus")
     */
    'providers' => [
        'openrouter' => [
            'api_key' => env('OPENROUTER_API_KEY'),
            'base_url' => 'https://openrouter.ai/api/v1',
            // Optional: Set default headers for OpenRouter
            'http_referer' => env('OPENROUTER_HTTP_REFERER', config('app.url')),
            'app_name' => env('OPENROUTER_APP_NAME', config('app.name')),
        ],
    ],

    /**
     * Sub-agent delegation settings.
     * Controls behavior of agent hierarchies and delegation.
     */
    'max_delegation_depth' => env('VIZRA_ADK_MAX_DELEGATION_DEPTH', 5), // Maximum depth for nested sub-agent delegation

    /**
     * Database table names used by the package.
     * You can change these if they conflict with existing tables.
     */
    'tables' => [
        'agent_sessions' => 'agent_sessions',
        'agent_messages' => 'agent_messages',
        'agent_memories' => 'agent_memories',
        'agent_vector_memories' => 'agent_vector_memories',
        'agent_trace_spans' => 'agent_trace_spans',
    ],

    /**
     * Tracing configuration.
     * Controls the execution tracing system for debugging and performance analysis.
     */
    'tracing' => [
        'enabled' => env('VIZRA_ADK_TRACING_ENABLED', true),
        'cleanup_days' => env('VIZRA_ADK_TRACING_CLEANUP_DAYS', 30), // Days to keep trace data
    ],

    /**
     * Namespaces for user-defined classes.
     * These are used by the artisan 'make' commands.
     */
    'namespaces' => [
        'agents' => 'App\Agents',           // Default namespace for generated Agent classes
        'tools' => 'App\Tools',            // Default namespace for generated Tool classes
        'evaluations' => 'App\Evaluations', // Default namespace for generated Evaluation classes
    ],

    'routes' => [
        'enabled' => true, // Master switch for package routes
        'prefix' => 'api/vizra-adk', // Default prefix for all package API routes
        'middleware' => ['api'], // Default middleware group for package routes
        'web' => [
            'enabled' => env('VIZRA_ADK_WEB_ENABLED', true), // Enable web interface
            'prefix' => 'vizra', // Prefix for web routes
            'middleware' => ['web'], // Middleware for web routes
        ],
    ],

    /**
     * OpenAI API Compatibility Configuration
     * Maps OpenAI model names to your agent names for the /chat/completions endpoint.
     */
    'openai_model_mapping' => [
        'gpt-4' => env('VIZRA_ADK_OPENAI_GPT4_AGENT', 'chat_agent'),
        'gpt-4-turbo' => env('VIZRA_ADK_OPENAI_GPT4_TURBO_AGENT', 'chat_agent'),
        'gpt-3.5-turbo' => env('VIZRA_ADK_OPENAI_GPT35_AGENT', 'chat_agent'),
        'gpt-4o' => env('VIZRA_ADK_OPENAI_GPT4O_AGENT', 'chat_agent'),
        'gpt-4o-mini' => env('VIZRA_ADK_OPENAI_GPT4O_MINI_AGENT', 'chat_agent'),
        // Add more mappings as needed
    ],

    /**
     * Default agent to use when no specific mapping is found
     */
    'default_chat_agent' => env('VIZRA_ADK_DEFAULT_CHAT_AGENT', 'chat_agent'),

    /**
     * Model Context Protocol (MCP) Configuration
     * Define MCP servers that agents can connect to for enhanced capabilities.
     *
     * Supported transports:
     * - 'stdio': Subprocess communication via stdin/stdout (default)
     * - 'http': Remote HTTP/SSE server communication
     *
     * STDIO Transport Configuration:
     * - transport: 'stdio' (optional, default)
     * - command: The command to start the MCP server
     * - args: Arguments to pass to the server command
     * - enabled: Whether this server is enabled (default: true)
     * - timeout: Connection timeout in seconds (default: 30)
     * - use_pty: Use pseudo-terminal for interactive processes (default: false)
     *
     * HTTP Transport Configuration:
     * - transport: 'http'
     * - url: The HTTP endpoint URL for the MCP server
     * - api_key: Optional API key for authentication
     * - enabled: Whether this server is enabled (default: true)
     * - timeout: Request timeout in seconds (default: 30)
     * - headers: Optional additional HTTP headers (default: [])
     */
    'mcp_servers' => [
        // STDIO transport example - local MCP server via subprocess
        'filesystem' => [
            'transport' => 'stdio', // Optional, default is 'stdio'
            'command' => env('MCP_NPX_PATH', 'npx'),
            'args' => [
                '@modelcontextprotocol/server-filesystem',
                env('MCP_FILESYSTEM_PATH', app_path()), // Changed to allow access to app directory
            ],
            'enabled' => env('MCP_FILESYSTEM_ENABLED', false),
            'timeout' => 30,
        ],

        // STDIO transport example - GitHub MCP server
        'github' => [
            'transport' => 'stdio',
            'command' => env('MCP_NPX_PATH', 'npx'),
            'args' => [
                '@modelcontextprotocol/server-github',
                '--token',
                env('GITHUB_TOKEN', ''),
            ],
            'enabled' => env('MCP_GITHUB_ENABLED', false) && ! empty(env('GITHUB_TOKEN')),
            'timeout' => 45,
        ],

        // HTTP transport example - remote MCP server via HTTP/SSE
        'github_http' => [
            'transport' => 'http',
            'url' => env('MCP_GITHUB_HTTP_URL', 'http://localhost:8001/api/mcp'),
            'api_key' => env('MCP_GITHUB_HTTP_API_KEY'),
            'enabled' => env('MCP_GITHUB_HTTP_ENABLED', false),
            'timeout' => 45,
            'headers' => [
                // Optional additional headers
                // 'X-Custom-Header' => 'value',
            ],
        ],

        'postgres' => [
            'command' => env('MCP_NPX_PATH', 'npx'),
            'args' => [
                '@modelcontextprotocol/server-postgres',
                '--connection-string',
                env('MCP_POSTGRES_URL', env('DATABASE_URL', '')),
            ],
            'enabled' => env('MCP_POSTGRES_ENABLED', false) && ! empty(env('DATABASE_URL')),
            'timeout' => 30,
        ],

        'brave_search' => [
            'command' => env('MCP_NPX_PATH', 'npx'),
            'args' => [
                '@modelcontextprotocol/server-brave-search',
                '--api-key',
                env('BRAVE_API_KEY', ''),
            ],
            'enabled' => env('MCP_BRAVE_SEARCH_ENABLED', false) && ! empty(env('BRAVE_API_KEY')),
            'timeout' => 30,
        ],

        'slack' => [
            'command' => env('MCP_NPX_PATH', 'npx'),
            'args' => [
                '@modelcontextprotocol/server-slack',
                '--bot-token',
                env('SLACK_BOT_TOKEN', ''),
            ],
            'enabled' => env('MCP_SLACK_ENABLED', false) && ! empty(env('SLACK_BOT_TOKEN')),
            'timeout' => 30,
        ],

        // Example custom MCP server
        // 'custom_api' => [
        //     'command' => 'python',
        //     'args' => ['/path/to/your/mcp-server.py'],
        //     'enabled' => true,
        //     'timeout' => 60,
        // ],
    ],

    /**
     * Prompt versioning configuration.
     * Controls how agent prompts are stored and versioned.
     */
    'prompts' => [
        /**
         * Enable database storage for prompts.
         * When true, prompts can be stored in the database for dynamic updates.
         */
        'use_database' => env('VIZRA_ADK_PROMPTS_USE_DATABASE', false),

        /**
         * Path where file-based prompts are stored.
         * Default: resources/prompts
         */
        'storage_path' => env('VIZRA_ADK_PROMPTS_PATH', resource_path('prompts')),

        /**
         * Enable prompt usage tracking.
         * When true, tracks which prompt versions are used in each session.
         */
        'track_usage' => env('VIZRA_ADK_PROMPTS_TRACK_USAGE', false),

        /**
         * Cache TTL for database prompts (in seconds).
         * Set to 0 to disable caching.
         */
        'cache_ttl' => env('VIZRA_ADK_PROMPTS_CACHE_TTL', 300),

        /**
         * Default prompt version to use when none is specified.
         * Can be 'latest', 'default', or a specific version string.
         */
        'default_version' => env('VIZRA_ADK_PROMPTS_DEFAULT_VERSION', 'default'),
    ],

    /**
     * Vector Memory & RAG Configuration
     * Configure semantic search and document retrieval capabilities.
     */
    'vector_memory' => [
        /**
         * Enable vector memory functionality.
         */
        'enabled' => env('VIZRA_ADK_VECTOR_ENABLED', true),

        /**
         * Vector storage driver.
         * Supported: 'pgvector', 'meilisearch'
         */
        'driver' => env('VIZRA_ADK_VECTOR_DRIVER', 'pgvector'),

        /**
         * Embedding provider for generating vectors.
         * Supported: 'openai', 'cohere', 'ollama', 'gemini'
         */
        'embedding_provider' => env('VIZRA_ADK_EMBEDDING_PROVIDER', 'openai'),

        /**
         * Embedding models for each provider.
         */
        'embedding_models' => [
            'openai' => env('VIZRA_ADK_OPENAI_EMBEDDING_MODEL', 'text-embedding-3-small'),
            'cohere' => env('VIZRA_ADK_COHERE_EMBEDDING_MODEL', 'embed-english-v3.0'),
            'ollama' => env('VIZRA_ADK_OLLAMA_EMBEDDING_MODEL', 'nomic-embed-text'),
            'gemini' => env('VIZRA_ADK_GEMINI_EMBEDDING_MODEL', 'text-embedding-004'),
        ],

        /**
         * Model dimensions for calculating similarity.
         */
        'dimensions' => [
            // OpenAI models
            'text-embedding-3-small' => 1536,
            'text-embedding-3-large' => 3072,
            'text-embedding-ada-002' => 1536,
            // Cohere models
            'embed-english-v3.0' => 1024,
            'embed-multilingual-v3.0' => 1024,
            // Ollama models
            'nomic-embed-text' => 768,
            'mxbai-embed-large' => 1024,
            // Gemini models
            'text-embedding-004' => 768,
        ],

        /**
         * Driver-specific configurations.
         */
        'drivers' => [
            'pgvector' => [
                'connection' => env('VIZRA_ADK_PGVECTOR_CONNECTION', 'pgsql'),
            ],

            'meilisearch' => [
                'host' => env('MEILISEARCH_HOST', 'http://localhost:7700'),
                'api_key' => env('MEILISEARCH_KEY'),
                'index_prefix' => env('MEILISEARCH_PREFIX', 'agent_vectors_'),
                'embedder' => env('MEILISEARCH_EMBEDDER', 'default'),
                'semantic_ratio' => env('MEILISEARCH_SEMANTIC_RATIO', 1.0),
            ],
        ],

        /**
         * Document chunking configuration.
         */
        'chunking' => [
            'strategy' => env('VIZRA_ADK_CHUNK_STRATEGY', 'sentence'), // 'sentence' or 'paragraph'
            'chunk_size' => env('VIZRA_ADK_CHUNK_SIZE', 1000), // Characters per chunk
            'overlap' => env('VIZRA_ADK_CHUNK_OVERLAP', 200), // Overlap between chunks
            'separators' => ["\n\n", "\n", ". ", ", ", " "],
            'keep_separators' => true,
        ],

        /**
         * RAG (Retrieval-Augmented Generation) configuration.
         */
        'rag' => [
            'context_template' => "Based on the following context:\n{context}\n\nAnswer this question: {query}",
            'max_context_length' => env('VIZRA_ADK_RAG_MAX_CONTEXT', 4000),
            'include_metadata' => env('VIZRA_ADK_RAG_INCLUDE_METADATA', true),
        ],
    ],
];
