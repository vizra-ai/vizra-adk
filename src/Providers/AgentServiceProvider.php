<?php

namespace AaronLumsden\LaravelAgentADK\Providers;

use Illuminate\Support\Facades\Route;
use AaronLumsden\LaravelAgentADK\Services\AgentBuilder;
use AaronLumsden\LaravelAgentADK\Services\AgentRegistry;
use AaronLumsden\LaravelAgentADK\Services\StateManager;
use AaronLumsden\LaravelAgentADK\Services\AgentManager; // Added
use AaronLumsden\LaravelAgentADK\Console\Commands\InstallCommand;
use AaronLumsden\LaravelAgentADK\Console\Commands\MakeAgentCommand;
use AaronLumsden\LaravelAgentADK\Console\Commands\MakeToolCommand;
use AaronLumsden\LaravelAgentADK\Console\Commands\AgentChatCommand;
use AaronLumsden\LaravelAgentADK\Console\Commands\MakeEvalCommand;
use AaronLumsden\LaravelAgentADK\Console\Commands\RunEvalCommand;
use Illuminate\Support\ServiceProvider;
use Illuminate\Contracts\Foundation\Application; // Added for type hint

class AgentServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../../config/agent-adk.php',
            'agent-adk'
        );

        $this->app->singleton(AgentRegistry::class, function (Application $app) {
            return new AgentRegistry($app);
        });

        $this->app->singleton(AgentBuilder::class, function (Application $app) {
            return new AgentBuilder($app, $app->make(AgentRegistry::class));
        });

        $this->app->singleton(StateManager::class, function (Application $app) {
            return new StateManager(); // StateManager doesn't have app dependency currently
        });

        // Bind the AgentManager for the Facade and general use
        $this->app->singleton(AgentManager::class, function (Application $app) {
            return new AgentManager(
                $app,
                $app->make(AgentRegistry::class),
                $app->make(AgentBuilder::class),
                $app->make(StateManager::class)
            );
        });

        // Ensure the facade accessor points to the AgentManager binding
        $this->app->alias(AgentManager::class, 'laravel-agent-adk.manager');
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../../config/agent-adk.php' => config_path('agent-adk.php'),
        ], 'agent-adk-config');

        $this->publishes([
            __DIR__.'/../../database/migrations/' => database_path('migrations'),
        ], 'agent-adk-migrations');

        if ($this->app->runningInConsole()) {
            $this->commands([
                InstallCommand::class,
                MakeAgentCommand::class,
                MakeToolCommand::class,
                AgentChatCommand::class,
                MakeEvalCommand::class,
                RunEvalCommand::class
            ]);
        }

        $this->loadRoutes();
    }

    protected function loadRoutes(): void
    {
        if (config('agent-adk.routes.enabled', true)) { // Make route loading configurable
            Route::group([
                'prefix' => config('agent-adk.routes.prefix', 'api/agent-adk'), // Configurable prefix
                'middleware' => config('agent-adk.routes.middleware', ['api']), // Configurable middleware
            ], function () {
                $this->loadRoutesFrom(__DIR__.'/../../routes/api.php');
            });
        }
    }
}
