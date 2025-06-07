<?php

namespace AaronLumsden\LaravelAiADK\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Illuminate\Contracts\Foundation\Application;
use Livewire\Livewire;
use AaronLumsden\LaravelAiADK\Services\AgentBuilder;
use AaronLumsden\LaravelAiADK\Services\AgentRegistry;
use AaronLumsden\LaravelAiADK\Services\StateManager;
use AaronLumsden\LaravelAiADK\Services\MemoryManager;
use AaronLumsden\LaravelAiADK\Services\AgentManager;
use AaronLumsden\LaravelAiADK\Services\WorkflowManager;
use AaronLumsden\LaravelAiADK\Services\Tracer;
use AaronLumsden\LaravelAiADK\Services\AnalyticsService;
use AaronLumsden\LaravelAiADK\Livewire\Dashboard;
use AaronLumsden\LaravelAiADK\Livewire\ChatInterface;
use AaronLumsden\LaravelAiADK\Livewire\EvalRunner;
use AaronLumsden\LaravelAiADK\Livewire\Analytics;
use AaronLumsden\LaravelAiADK\Console\Commands\InstallCommand;
use AaronLumsden\LaravelAiADK\Console\Commands\MakeAgentCommand;
use AaronLumsden\LaravelAiADK\Console\Commands\MakeToolCommand;
use AaronLumsden\LaravelAiADK\Console\Commands\AgentChatCommand;
use AaronLumsden\LaravelAiADK\Console\Commands\MakeEvalCommand;
use AaronLumsden\LaravelAiADK\Console\Commands\RunEvalCommand;
use AaronLumsden\LaravelAiADK\Console\Commands\AgentTraceCleanupCommand;
use AaronLumsden\LaravelAiADK\Console\Commands\AgentTraceCommand;
use AaronLumsden\LaravelAiADK\Console\Commands\DashboardCommand;

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

        $this->app->singleton(AnalyticsService::class, function (Application $app) {
            return new AnalyticsService();
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
        $this->app->alias(AgentManager::class, 'laravel-ai-adk.manager');

        // Register WorkflowManager for the Workflow facade
        $this->app->singleton(WorkflowManager::class, function (Application $app) {
            return new WorkflowManager();
        });

        $this->app->alias(WorkflowManager::class, 'laravel-ai-adk.workflow');
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
            Livewire::component('agent-adk-eval-runner', EvalRunner::class);
            Livewire::component('agent-adk-analytics', Analytics::class);
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
