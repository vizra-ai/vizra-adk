<?php

namespace Vizra\VizraADK\Tests;

use Livewire\LivewireServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;
use Prism\Prism\PrismServiceProvider;
use Vizra\VizraADK\Providers\AgentServiceProvider;

class TestCase extends Orchestra
{
    protected function getPackageProviders($app)
    {
        return [
            LivewireServiceProvider::class,
            AgentServiceProvider::class,
            PrismServiceProvider::class,
        ];
    }

    protected function getPackageAliases($app)
    {
        return [
            'Agent' => \Vizra\VizraADK\Facades\Agent::class,
            'Workflow' => \Vizra\VizraADK\Facades\Workflow::class,
        ];
    }

    protected function getEnvironmentSetUp($app)
    {
        // Setup default database to use sqlite :memory:
        $app['config']->set('database.default', 'testbench');
        $app['config']->set('database.connections.testbench', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        // Set encryption key for tests
        $app['config']->set('app.key', 'base64:843sTC/OSjCKW+ZnImGjVdbrib089tC87dXdVlI+vc8=');

        // Load your package config if needed
        $config = require __DIR__.'/../config/vizra-adk.php';
        $app['config']->set('vizra-adk', $config);

        // Override problematic settings for tests
        $app['config']->set('vizra-adk.vector_memory.driver', 'sqlite');
        $app['config']->set('vizra-adk.default_provider', 'mock');
        $app['config']->set('vizra-adk.default_model', 'mock-model');

        // Set dummy API keys to prevent missing key errors
        $app['config']->set('services.openai.key', 'test-key');
        $app['config']->set('services.anthropic.key', 'test-key');
        $app['config']->set('services.google.key', 'test-key');

        // Configure Prism providers
        $app['config']->set('prism.providers.openai', [
            'api_key' => 'test-key',
            'url' => 'https://api.openai.com/v1/',
            'organization' => null,
            'project' => null,
        ]);

        $app['config']->set('prism.providers.anthropic', [
            'api_key' => 'test-key',
            'version' => '2023-06-01',
        ]);

        $app['config']->set('prism.providers.gemini', [
            'api_key' => 'test-key',
        ]);

        // Disable tracing in tests by default (individual tests can override)
        $app['config']->set('vizra-adk.tracing.enabled', false);
    }

    protected function setUp(): void
    {
        parent::setUp();

        // Reset Mockery container to clean state before each test
        if (class_exists(\Mockery::class)) {
            \Mockery::resetContainer();
        }

        // Run migrations for testing
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
    }

    protected function tearDown(): void
    {
        // Clear all Mockery mocks and expectations
        if (class_exists(\Mockery::class)) {
            \Mockery::close();
        }

        parent::tearDown();
    }
}
