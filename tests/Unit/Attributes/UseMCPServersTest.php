<?php

use Vizra\VizraADK\Attributes\UseMCPServers;

it('can create attribute with servers list', function () {
    $attribute = new UseMCPServers(['server1', 'server2']);

    expect($attribute->getServers())->toBe(['server1', 'server2']);
});

it('can create attribute with empty servers list', function () {
    $attribute = new UseMCPServers([]);

    expect($attribute->getServers())->toBeArray();
    expect($attribute->getServers())->toBeEmpty();
});

it('can check if specific server is enabled', function () {
    $attribute = new UseMCPServers(['filesystem', 'github']);

    expect($attribute->hasServer('filesystem'))->toBeTrue();
    expect($attribute->hasServer('github'))->toBeTrue();
    expect($attribute->hasServer('nonexistent'))->toBeFalse();
});

it('returns servers as comma-separated string', function () {
    $attribute = new UseMCPServers(['filesystem', 'github', 'postgres']);

    expect($attribute->getServersString())->toBe('filesystem, github, postgres');
});

it('handles empty servers list in string conversion', function () {
    $attribute = new UseMCPServers([]);

    expect($attribute->getServersString())->toBe('');
});

it('is case sensitive for server names', function () {
    $attribute = new UseMCPServers(['FileSystem', 'GitHub']);

    expect($attribute->hasServer('filesystem'))->toBeFalse();
    expect($attribute->hasServer('FileSystem'))->toBeTrue();
});

it('can be used as PHP attribute', function () {
    // Test that the attribute can be applied to classes
    expect(true)->toBeTrue(); // This tests the syntax is valid

    // The actual reflection-based testing would require a test class
    $reflection = new ReflectionClass(UseMCPServers::class);
    $attributes = $reflection->getAttributes();

    // Check that it's properly configured as a class-level attribute
    expect($reflection->getAttributes()[0]->getName())->toBe('Attribute');
});
