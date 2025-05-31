<?php

return [
    /**
     * Default LLM model to use with Prism-PHP.
     * This can be overridden by specific agent configurations.
     * Example: 'gemini-pro', 'gpt-4-turbo', 'claude-3-opus-20240229'
     */
    'default_model' => env('AGENT_ADK_DEFAULT_MODEL', 'gemini-pro'),

    /**
     * Database table names used by the package.
     * You can change these if they conflict with existing tables.
     */
    'tables' => [
        'agent_sessions' => 'agent_sessions',
        'agent_messages' => 'agent_messages',
    ],

    /**
     * Namespaces for user-defined classes.
     * These are used by the artisan 'make' commands.
     */
    'namespaces' => [
        'agents' => 'App\Agents', // Default namespace for generated Agent classes
        'tools'  => 'App\Tools',   // Default namespace for generated Tool classes
    ],

    /**
     * Agent Manager Configuration
     * Settings related to how agents are discovered, registered, and run.
     */
    'manager' => [
        // If you want to automatically discover and register agent classes from a specific directory:
        // 'auto_discover_agents' => true,
        // 'agent_paths' => [
        //    app_path('Agents') => 'App\Agents', // path => namespace
        // ],
    ],

    /**
     * Prism-PHP specific configurations.
     * You can specify your Prism-PHP client settings here.
     * Refer to Prism-PHP documentation for available options.
     *
     * Example:
     * 'prism' => [
     *     'api_key' => env('PRISM_API_KEY'),
     *     'client_options' => [
     *         // 'base_uri' => '...',
     *         // 'timeout' => 60,
     *     ]
     * ]
     */
    'prism' => [
        'api_key' => env('PRISM_API_KEY'), // Example, user should configure their Prism provider
        'client_options' => [],
        // 'default_provider' => 'openai', // Example: 'openai', 'gemini', 'anthropic'
                                        // This might be useful if Prism-PHP evolves to support multiple providers directly
                                        // For now, Prism client is usually instantiated for a specific provider
    ],
];
