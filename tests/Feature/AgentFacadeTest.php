<?php

use Vizra\VizraAdk\Facades\Agent;

it('can access the agent facade', function () {
    expect(Agent::class)->toBeTruthy();
});

it('has the expected facade methods available', function () {
    $reflectionClass = new ReflectionClass(Agent::class);
    $methods = $reflectionClass->getMethods();

    // Check that the facade has methods
    expect($methods)->not()->toBeEmpty();
});

it('can check if config is loaded', function () {
    $config = config('agent-adk');

    expect($config)->toBeArray();
});
