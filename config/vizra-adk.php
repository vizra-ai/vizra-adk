<?php

return [
    /**
     * Default LLM provider to use with Prism-PHP.
     * This can be overridden by specific agent configurations.
     * Options: 'openai', 'anthropic', 'gemini'
     */
    'default_provider' => env('VIZRA_ADK_DEFAULT_PROVIDER', 'openai'),

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
        'tools'  => 'App\Tools',            // Default namespace for generated Tool classes
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
];
