<?php

namespace AaronLumsden\LaravelAiADK\Tests;

use AaronLumsden\LaravelAiADK\Providers\AgentServiceProvider;
use Livewire\LivewireServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

class TestCase extends Orchestra
{
    protected function getPackageProviders($app)
    {
        return [
            LivewireServiceProvider::class,
            AgentServiceProvider::class,
        ];
    }

    protected function getPackageAliases($app)
    {
        return [
            'Agent' => \AaronLumsden\LaravelAiADK\Facades\Agent::class,
            'Workflow' => \AaronLumsden\LaravelAiADK\Facades\Workflow::class,
        ];
    }

    protected function getEnvironmentSetUp($app)
    {
        // Setup default database to use sqlite :memory:
        $app['config']->set('database.default', 'testbench');
        $app['config']->set('database.connections.testbench', [
            'driver'   => 'sqlite',
            'database' => ':memory:',
            'prefix'   => '',
        ]);

        // Set encryption key for tests
        $app['config']->set('app.key', 'base64:843sTC/OSjCKW+ZnImGjVdbrib089tC87dXdVlI+vc8=');

        // Load your package config if needed
        $config = require __DIR__ . '/../config/agent-adk.php';
        $app['config']->set('agent-adk', $config);
        
        // Override problematic settings for tests
        $app['config']->set('agent-adk.vector_memory.driver', 'sqlite');
        $app['config']->set('agent-adk.default_provider', 'mock');
        $app['config']->set('agent-adk.default_model', 'mock-model');
        
        // Set dummy API keys to prevent missing key errors
        $app['config']->set('services.openai.key', 'test-key');
        $app['config']->set('services.anthropic.key', 'test-key');
        $app['config']->set('services.google.key', 'test-key');
        
        // Disable tracing in tests to avoid database complexity
        $app['config']->set('agent-adk.tracing.enabled', false);
    }

    protected function setUp(): void
    {
        parent::setUp();

        // Run migrations for testing
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
    }

    protected function tearDown(): void
    {
        // Clear Mockery between tests to prevent conflicts
        if (class_exists(\Mockery::class)) {
            \Mockery::close();
        }
        
        parent::tearDown();
    }
}
