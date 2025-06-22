<?php

use Vizra\VizraADK\Services\AgentDiscovery;
use Vizra\VizraADK\Services\AgentManager;
use Vizra\VizraADK\Services\AgentRegistry;

it('can instantiate the agent registry', function () {
    $registry = app(AgentRegistry::class);

    expect($registry)->toBeInstanceOf(AgentRegistry::class);
});

it('can instantiate the agent manager', function () {
    $manager = app(AgentManager::class);

    expect($manager)->toBeInstanceOf(AgentManager::class);
});

it('has the agent facade available', function () {
    expect(class_exists('Vizra\VizraADK\Facades\Agent'))->toBeTrue();
});

it('can resolve agent registry from container', function () {
    $registry1 = app(AgentRegistry::class);
    $registry2 = app(AgentRegistry::class);

    expect($registry1)->toBe($registry2);
});

it('can instantiate the agent discovery service', function () {
    $discovery = app(AgentDiscovery::class);

    expect($discovery)->toBeInstanceOf(AgentDiscovery::class);
});

it('runs auto-discovery on boot', function () {
    // Create mock services
    $mockDiscovery = Mockery::mock(AgentDiscovery::class);
    $mockDiscovery->shouldReceive('discover')->once()->andReturn([
        'App\Agents\TestAgent' => 'test_agent',
    ]);

    $mockRegistry = Mockery::mock(AgentRegistry::class);
    $mockRegistry->shouldReceive('register')->with('test_agent', 'App\Agents\TestAgent')->once();

    // Bind mocks to container
    $this->app->instance(AgentDiscovery::class, $mockDiscovery);
    $this->app->instance(AgentRegistry::class, $mockRegistry);

    // Create and boot the service provider
    $provider = new \Vizra\VizraADK\Providers\AgentServiceProvider($this->app);
    $provider->boot();

    // Assertions are in the mock expectations
});

it('discovery service is registered as singleton', function () {
    $discovery1 = app(AgentDiscovery::class);
    $discovery2 = app(AgentDiscovery::class);

    expect($discovery1)->toBe($discovery2);
});
