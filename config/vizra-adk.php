<?php

return [
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
     * Each server configuration includes:
     * - command: The command to start the MCP server
     * - args: Arguments to pass to the server command
     * - enabled: Whether this server is enabled (default: true)
     * - timeout: Connection timeout in seconds (default: 30)
     * - use_pty: Use pseudo-terminal for interactive processes (default: false)
     */
    'mcp_servers' => [
        'filesystem' => [
            'command' => env('MCP_NPX_PATH', 'npx'),
            'args' => [
                '@modelcontextprotocol/server-filesystem',
                env('MCP_FILESYSTEM_PATH', app_path()), // Changed to allow access to app directory
            ],
            'enabled' => env('MCP_FILESYSTEM_ENABLED', false),
            'timeout' => 30,
        ],

        'github' => [
            'command' => env('MCP_NPX_PATH', 'npx'),
            'args' => [
                '@modelcontextprotocol/server-github',
                '--token',
                env('GITHUB_TOKEN', ''),
            ],
            'enabled' => env('MCP_GITHUB_ENABLED', false) && ! empty(env('GITHUB_TOKEN')),
            'timeout' => 45,
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
