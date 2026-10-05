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

it('points the abandonment notice at the retirement post', function () {
    $abandoned = composerManifest()['abandoned'];

    // A string, not `true` — `true` prints "No replacement was suggested".
    expect($abandoned)
        ->toBeString()
        ->toBe('https://vizra.ai/blog/i-retired-a-laravel-package-with-38000-installs-heres-what-i-built-instead');
});
