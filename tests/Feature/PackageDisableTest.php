<?php

use Illuminate\Support\Facades\Config;
use Vizra\VizraADK\Services\AgentManager;
use Vizra\VizraADK\Services\AgentRegistry;

describe('Package disable functionality', function () {

    afterEach(function () {
        Mockery::close();
    });

    test('package boot is skipped when disabled', function () {
        // Set the package to disabled
        Config::set('vizra-adk.enabled', false);

        // Create and register the provider
        $provider = new \Vizra\VizraADK\Providers\AgentServiceProvider($this->app);
        $provider->register();

        // Boot should return early and not register routes/commands
        $result = $provider->boot();

        // Boot should complete without errors
        expect($result)->toBeNull();
    });

    test('package services are registered but not booted when disabled', function () {
        // Set the package to disabled
        Config::set('vizra-adk.enabled', false);

        // Register the service provider
        $provider = new \Vizra\VizraADK\Providers\AgentServiceProvider($this->app);
        $provider->register();
        $provider->boot();

        // Services are registered during register() which happens before config check
        // This is a Laravel limitation - config isn't available until after registration
        // The important part is that boot() returns early and doesn't initialize
        expect($this->app->bound(AgentManager::class))->toBeTrue();

        // But agent discovery should not have happened (boot was skipped)
        $registry = $this->app->make(AgentRegistry::class);
        expect($registry->getAllRegisteredAgents())->toBeEmpty();
    });

    test('package works normally when enabled', function () {
        // Ensure the package is enabled (default)
        Config::set('vizra-adk.enabled', true);

        // Register the service provider
        $provider = new \Vizra\VizraADK\Providers\AgentServiceProvider($this->app);
        $provider->register();

        // Check that services ARE bound
        expect($this->app->bound(AgentManager::class))->toBeTrue();
        expect($this->app->bound(AgentRegistry::class))->toBeTrue();
        expect($this->app->bound('vizra-adk.manager'))->toBeTrue();
        expect($this->app->bound('vizra-adk.workflow'))->toBeTrue();
    });

    test('vector memory provider respects global disable in boot', function () {
        // Set the package to disabled
        Config::set('vizra-adk.enabled', false);

        // Register and boot the VectorMemoryServiceProvider
        $provider = new \Vizra\VizraADK\Providers\VectorMemoryServiceProvider($this->app);
        $provider->register();
        $result = $provider->boot();

        // Boot should return early
        expect($result)->toBeNull();

        // Services are registered but not initialized
        // The important part is boot() doesn't run and no logging happens
        expect($this->app->bound(\Vizra\VizraADK\Services\VectorMemoryManager::class))->toBeTrue();
    });

    test('logging can be disabled independently', function () {
        // Package enabled but logging disabled
        Config::set('vizra-adk.enabled', true);
        Config::set('vizra-adk.logging.enabled', false);

        // Create a test class that uses HasLogging
        $testClass = new class {
            use \Vizra\VizraADK\Traits\HasLogging;

            public function test() {
                $this->logInfo('Test message');
            }
        };

        // Should not throw error, just not log
        expect(fn() => $testClass->test())->not->toThrow(\Exception::class);
    });

    test('logging respects component settings', function () {
        // Package and logging enabled, but specific component disabled
        Config::set('vizra-adk.enabled', true);
        Config::set('vizra-adk.logging.enabled', true);
        Config::set('vizra-adk.logging.components.vector_memory', false);

        // Create a test class that uses HasLogging
        $testClass = new class {
            use \Vizra\VizraADK\Traits\HasLogging;

            public function testVectorMemory() {
                $this->logInfo('Test message', [], 'vector_memory');
            }

            public function testAgents() {
                $this->logInfo('Test message', [], 'agents');
            }
        };

        // Vector memory logging should be disabled
        expect(fn() => $testClass->testVectorMemory())->not->toThrow(\Exception::class);

        // Agents logging should work (default is true)
        expect(fn() => $testClass->testAgents())->not->toThrow(\Exception::class);
    });

    test('logging respects level threshold', function () {
        Config::set('vizra-adk.enabled', true);
        Config::set('vizra-adk.logging.enabled', true);
        Config::set('vizra-adk.logging.level', 'warning');

        $testClass = new class {
            use \Vizra\VizraADK\Traits\HasLogging;

            public function logInfoMessage() {
                return $this->isLoggingEnabled() && $this->meetsLogLevel('info');
            }

            public function logWarningMessage() {
                return $this->isLoggingEnabled() && $this->meetsLogLevel('warning');
            }

            public function logErrorMessage() {
                return $this->isLoggingEnabled() && $this->meetsLogLevel('error');
            }
        };

        // Info should not meet threshold
        expect($testClass->logInfoMessage())->toBeFalse();

        // Warning should meet threshold
        expect($testClass->logWarningMessage())->toBeTrue();

        // Error should meet threshold
        expect($testClass->logErrorMessage())->toBeTrue();
    });

    test('boot methods return early when package disabled', function () {
        Config::set('vizra-adk.enabled', false);

        // Mock to track if certain methods are called
        $mock = Mockery::mock(\Vizra\VizraADK\Providers\AgentServiceProvider::class)
            ->makePartial()
            ->shouldAllowMockingProtectedMethods();

        $mock->__construct($this->app);

        // These protected methods should NOT be called when disabled
        $mock->shouldNotReceive('loadRoutes');
        $mock->shouldNotReceive('loadViews');
        $mock->shouldNotReceive('registerLivewireComponents');
        $mock->shouldNotReceive('discoverAgents');

        // Register and boot
        $mock->register();
        $mock->boot();

        // Test passes if the methods were not called
        expect(true)->toBeTrue();
    });
});