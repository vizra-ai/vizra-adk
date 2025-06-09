<?php

use Vizra\VizraSdk\Services\AgentRegistry;
use Vizra\VizraSdk\Services\AgentManager;

it('can instantiate the agent registry', function () {
    $registry = app(AgentRegistry::class);

    expect($registry)->toBeInstanceOf(AgentRegistry::class);
});

it('can instantiate the agent manager', function () {
    $manager = app(AgentManager::class);

    expect($manager)->toBeInstanceOf(AgentManager::class);
});

it('has the agent facade available', function () {
    expect(class_exists('Vizra\VizraSdk\Facades\Agent'))->toBeTrue();
});

it('can resolve agent registry from container', function () {
    $registry1 = app(AgentRegistry::class);
    $registry2 = app(AgentRegistry::class);

    expect($registry1)->toBe($registry2);
});
