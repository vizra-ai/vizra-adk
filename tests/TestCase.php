<?php

namespace AaronLumsden\LaravelAgentADK\Tests;

use AaronLumsden\LaravelAgentADK\Providers\AgentServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

class TestCase extends Orchestra
{
    protected function getPackageProviders($app)
    {
        return [
            AgentServiceProvider::class,
        ];
    }

    protected function getPackageAliases($app)
    {
        return [
            'Agent' => \AaronLumsden\LaravelAgentADK\Facades\Agent::class,
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

        // Load your package config if needed
        $app['config']->set('agent-adk', require __DIR__ . '/../config/agent-adk.php');
    }

    protected function setUp(): void
    {
        parent::setUp();

        // Run migrations for testing
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
    }
}
