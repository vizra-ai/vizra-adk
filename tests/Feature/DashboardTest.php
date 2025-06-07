<?php

use AaronLumsden\LaravelAiADK\Livewire\Dashboard;
use AaronLumsden\LaravelAiADK\Services\AgentRegistry;
use Livewire\Livewire;

it('can render dashboard component', function () {
    Livewire::test(Dashboard::class)
        ->assertSuccessful()
        ->assertSee('Laravel Agent ADK')
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
        ->assertSee('php artisan agent:make:agent MyAgent')
        ->assertSee('php artisan agent:make:tool MyTool')
        ->assertSee('php artisan agent:make:eval MyEvaluation')
        ->assertSee('php artisan agent:list');
});
