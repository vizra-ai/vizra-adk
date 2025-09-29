<?php

namespace Vizra\VizraADK\Providers;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;
use Vizra\VizraADK\Console\Commands\AgentChatCommand;
use Vizra\VizraADK\Console\Commands\AgentDiscoverCommand;
use Vizra\VizraADK\Console\Commands\AgentTraceCleanupCommand;
use Vizra\VizraADK\Console\Commands\AgentTraceCommand;
use Vizra\VizraADK\Console\Commands\BoostInstallCommand;
use Vizra\VizraADK\Console\Commands\DashboardCommand;
use Vizra\VizraADK\Console\Commands\InstallCommand;
use Vizra\VizraADK\Console\Commands\MakeAgentCommand;
use Vizra\VizraADK\Console\Commands\MakeAssertionCommand;
use Vizra\VizraADK\Console\Commands\MakeEvalCommand;
use Vizra\VizraADK\Console\Commands\MakeToolCommand;
use Vizra\VizraADK\Console\Commands\ManagePromptsCommand;
use Vizra\VizraADK\Console\Commands\MCPListServersCommand;
use Vizra\VizraADK\Console\Commands\RunEvalCommand;
use Vizra\VizraADK\Livewire\Analytics;
use Vizra\VizraADK\Livewire\ChatInterface;
use Vizra\VizraADK\Livewire\Dashboard;
use Vizra\VizraADK\Livewire\EvalRunner;
use Vizra\VizraADK\Services\AgentBuilder;
use Vizra\VizraADK\Services\AgentDiscovery;
use Vizra\VizraADK\Services\AgentManager;
use Vizra\VizraADK\Services\AgentRegistry;
use Vizra\VizraADK\Services\AnalyticsService;
use Vizra\VizraADK\Services\MemoryManager;
use Vizra\VizraADK\Services\StateManager;
use Vizra\VizraADK\Services\Tracer;
use Vizra\VizraADK\Services\WorkflowManager;

class AgentServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../../config/vizra-adk.php',
            'vizra-adk'
        );

        // Check if the package is globally disabled
        if (! config('vizra-adk.enabled', true)) {
            return;
        }

        // Merge vizra-adk providers into prism config
        $vizraProviders = config('vizra-adk.providers', []);
        if (!empty($vizraProviders)) {
            config(['prism.providers' => array_merge(
                config('prism.providers', []),
                $vizraProviders
            )]);
        }

        // Register the VectorMemoryServiceProvider only if package is enabled
        $this->app->register(VectorMemoryServiceProvider::class);

        $this->app->singleton(AgentRegistry::class, function (Application $app) {
            return new AgentRegistry($app);
        });

        $this->app->singleton(AgentBuilder::class, function (Application $app) {
            return new AgentBuilder($app, $app->make(AgentRegistry::class));
        });

        $this->app->singleton(MemoryManager::class, function (Application $app) {
            return new MemoryManager;
        });

        $this->app->singleton(StateManager::class, function (Application $app) {
            return new StateManager($app->make(MemoryManager::class));
        });

        $this->app->singleton(Tracer::class, function (Application $app) {
            return new Tracer;
        });

        $this->app->singleton(AnalyticsService::class, function (Application $app) {
            return new AnalyticsService;
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
        $this->app->alias(AgentManager::class, 'vizra-adk.manager');

        // Register WorkflowManager for the Workflow facade
        $this->app->singleton(WorkflowManager::class, function (Application $app) {
            return new WorkflowManager;
        });

        $this->app->alias(WorkflowManager::class, 'vizra-adk.workflow');

        // Register AgentDiscovery service
        $this->app->singleton(AgentDiscovery::class, function (Application $app) {
            return new AgentDiscovery;
        });

        // Register MCP services
        $this->app->singleton(\Vizra\VizraADK\Services\MCP\MCPClientManager::class, function (Application $app) {
            return new \Vizra\VizraADK\Services\MCP\MCPClientManager;
        });

        $this->app->singleton(\Vizra\VizraADK\Services\MCP\MCPToolDiscovery::class, function (Application $app) {
            return new \Vizra\VizraADK\Services\MCP\MCPToolDiscovery(
                $app->make(\Vizra\VizraADK\Services\MCP\MCPClientManager::class)
            );
        });
    }

    public function boot(): void
    {
        // Check if the package is globally disabled
        if (! config('vizra-adk.enabled', true)) {
            return;
        }

        $this->publishes([
            __DIR__.'/../../config/vizra-adk.php' => config_path('vizra-adk.php'),
        ], 'vizra-adk-config');

        $this->publishes([
            __DIR__.'/../../database/migrations/' => database_path('migrations'),
        ], 'vizra-adk-migrations');

        if ($this->app->runningInConsole()) {
            $this->commands([
                InstallCommand::class,
                MakeAgentCommand::class,
                MakeAssertionCommand::class,
                MakeToolCommand::class,
                AgentChatCommand::class,
                MakeEvalCommand::class,
                RunEvalCommand::class,
                AgentTraceCommand::class,
                AgentTraceCleanupCommand::class,
                DashboardCommand::class,
                AgentDiscoverCommand::class,
                MCPListServersCommand::class,
                ManagePromptsCommand::class,
                BoostInstallCommand::class,
            ]);
        }

        $this->loadRoutes();
        $this->loadViews();
        $this->registerLivewireComponents();
        $this->discoverAgents();
    }

    protected function discoverAgents(): void
    {
        // Skip agent discovery if package is disabled
        if (! config('vizra-adk.enabled', true)) {
            return;
        }

        /** @var AgentDiscovery $discovery */
        $discovery = $this->app->make(AgentDiscovery::class);
        $agents = $discovery->discover();

        /** @var AgentRegistry $registry */
        $registry = $this->app->make(AgentRegistry::class);

        foreach ($agents as $className => $agentName) {
            $registry->register($agentName, $className);
        }
    }

    protected function registerLivewireComponents(): void
    {
        // Skip Livewire registration if package is disabled
        if (! config('vizra-adk.enabled', true)) {
            return;
        }

        if (class_exists(Livewire::class)) {
            Livewire::component('vizra-adk-dashboard', Dashboard::class);
            Livewire::component('vizra-adk-chat-interface', ChatInterface::class);
            Livewire::component('vizra-adk-eval-runner', EvalRunner::class);
            Livewire::component('vizra-adk-analytics', Analytics::class);
        }
    }

    protected function loadViews(): void
    {
        // Skip view loading if package is disabled
        if (! config('vizra-adk.enabled', true)) {
            return;
        }

        $this->loadViewsFrom(__DIR__.'/../../resources/views', 'vizra-adk');

    }

    protected function loadRoutes(): void
    {
        // Skip route loading if package is disabled
        if (! config('vizra-adk.enabled', true)) {
            return;
        }

        // Load API routes
        if (config('vizra-adk.routes.enabled', true)) {
            Route::group([
                'prefix' => config('vizra-adk.routes.prefix', 'api/vizra-adk'),
                'middleware' => config('vizra-adk.routes.middleware', ['api']),
            ], function () {
                $this->loadRoutesFrom(__DIR__.'/../../routes/api.php');
            });
        }

        // Load web routes
        if (config('vizra-adk.routes.web.enabled', true)) {
            Route::group([
                'prefix' => config('vizra-adk.routes.web.prefix', 'vizra'),
                'middleware' => config('vizra-adk.routes.web.middleware', ['web']),
                'as' => 'vizra.',
            ], function () {
                $this->loadRoutesFrom(__DIR__.'/../../routes/web.php');
            });
        }
    }
}
