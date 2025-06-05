<?php

use AaronLumsden\LaravelAgentADK\Livewire\Dashboard;
use AaronLumsden\LaravelAgentADK\Services\AgentRegistry;
use Livewire\Livewire;

it('can render dashboard component', function () {
    Livewire::test(Dashboard::class)
        ->assertSuccessful()
        ->assertSee('Welcome to Laravel Agent ADK')
        ->assertSee('Build powerful AI agents with Laravel');
});

it('displays package information', function () {
    Livewire::test(Dashboard::class)
        ->assertSee('Package Version')
        ->assertSee('Registered Agents')
        ->assertSee('Status');
});

it('shows quick start commands', function () {
    Livewire::test(Dashboard::class)
        ->assertSee('php artisan agent:make:agent MyAgent')
        ->assertSee('php artisan agent:make:tool MyTool')
        ->assertSee('php artisan agent:chat agent_name')
        ->assertSee('php artisan agent:eval eval_name');
});
