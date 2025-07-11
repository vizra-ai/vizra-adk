<?php

use Livewire\Livewire;
use Vizra\VizraADK\Livewire\Dashboard;

it('can render dashboard component', function () {
    Livewire::test(Dashboard::class)
        ->assertSuccessful()
        ->assertSee('Vizra ADK')
        ->assertSee('Build, test, and deploy intelligent AI agents');
});

it('displays package information', function () {
    Livewire::test(Dashboard::class)
        ->assertSee('agents')
        ->assertSee('Quick Start')
        ->assertSee('Your Agents');
});

it('shows quick start commands', function () {
    Livewire::test(Dashboard::class)
        ->assertSee('php artisan vizra:make:agent MyAgent')
        ->assertSee('php artisan vizra:make:tool MyTool')
        ->assertSee('php artisan vizra:make:eval MyEvaluation')
        ->assertSee('php artisan vizra:discover-agents');
});
