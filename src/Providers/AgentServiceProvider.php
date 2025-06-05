<?php

namespace AaronLumsden\LaravelAgentADK\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Illuminate\Contracts\Foundation\Application;
use Livewire\Livewire;
use AaronLumsden\LaravelAgentADK\Services\AgentBuilder;
use AaronLumsden\LaravelAgentADK\Services\AgentRegistry;
use AaronLumsden\LaravelAgentADK\Services\StateManager;
use AaronLumsden\LaravelAgentADK\Services\MemoryManager;
use AaronLumsden\LaravelAgentADK\Services\AgentManager;
use AaronLumsden\LaravelAgentADK\Services\Tracer;
use AaronLumsden\LaravelAgentADK\Livewire\Dashboard;
use AaronLumsden\LaravelAgentADK\Livewire\ChatInterface;
use AaronLumsden\LaravelAgentADK\Console\Commands\InstallCommand;
use AaronLumsden\LaravelAgentADK\Console\Commands\MakeAgentCommand;
use AaronLumsden\LaravelAgentADK\Console\Commands\MakeToolCommand;
use AaronLumsden\LaravelAgentADK\Console\Commands\AgentChatCommand;
use AaronLumsden\LaravelAgentADK\Console\Commands\MakeEvalCommand;
use AaronLumsden\LaravelAgentADK\Console\Commands\RunEvalCommand;
use AaronLumsden\LaravelAgentADK\Console\Commands\AgentTraceCleanupCommand;
use AaronLumsden\LaravelAgentADK\Console\Commands\AgentTraceCommand;
use AaronLumsden\LaravelAgentADK\Console\Commands\DashboardCommand;

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

        $this->app->singleton(MemoryManager::class, function (Application $app) {
            return new MemoryManager();
        });

        $this->app->singleton(StateManager::class, function (Application $app) {
            return new StateManager($app->make(MemoryManager::class));
        });

        $this->app->singleton(Tracer::class, function (Application $app) {
            return new Tracer();
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
                RunEvalCommand::class,
                AgentTraceCommand::class,
                AgentTraceCleanupCommand::class,
                DashboardCommand::class
            ]);
        }

        $this->loadRoutes();
        $this->loadViews();
        $this->registerLivewireComponents();
    }

    protected function registerLivewireComponents(): void
    {
        if (class_exists(Livewire::class)) {
            Livewire::component('agent-adk-dashboard', Dashboard::class);
            Livewire::component('agent-adk-chat-interface', ChatInterface::class);
        }
    }

    protected function loadViews(): void
    {
        $this->loadViewsFrom(__DIR__.'/../../resources/views', 'agent-adk');

        $this->publishes([
            __DIR__.'/../../resources/views' => resource_path('views/vendor/agent-adk'),
        ], 'agent-adk-views');
    }

    protected function loadRoutes(): void
    {
        // Load API routes
        if (config('agent-adk.routes.enabled', true)) {
            Route::group([
                'prefix' => config('agent-adk.routes.prefix', 'api/agent-adk'),
                'middleware' => config('agent-adk.routes.middleware', ['api']),
            ], function () {
                $this->loadRoutesFrom(__DIR__.'/../../routes/api.php');
            });
        }

        // Load web routes
        if (config('agent-adk.routes.web.enabled', true)) {
            Route::group([
                'prefix' => config('agent-adk.routes.web.prefix', 'ai-adk'),
                'middleware' => config('agent-adk.routes.web.middleware', ['web']),
                'as' => 'agent-adk.',
            ], function () {
                $this->loadRoutesFrom(__DIR__.'/../../routes/web.php');
            });
        }
    }
}
