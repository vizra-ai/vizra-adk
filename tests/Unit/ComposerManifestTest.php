<?php

/*
 * The package is retired. Packagist reconciles the abandonment replacement from
 * the default branch's composer.json, so the "abandoned" key is the only thing
 * that puts a pointer in front of the installed base on `composer update`.
 * These tests exist so a later edit cannot quietly drop it.
 */

function composerManifest(): array
{
    return json_decode(file_get_contents(__DIR__.'/../../composer.json'), true, 512, JSON_THROW_ON_ERROR);
}

it('marks the package as abandoned', function () {
    expect(composerManifest())->toHaveKey('abandoned');
});

it('names laravel/ai as the replacement', function () {
    $abandoned = composerManifest()['abandoned'];

    // A package name, not `true` — `true` prints "No replacement was suggested".
    // The README sends agent-builders to the official Laravel AI SDK, and the
    // Composer notice has to say the same thing.
    expect($abandoned)
        ->toBeString()
        ->toBe('laravel/ai');
});
