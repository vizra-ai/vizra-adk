<?php

use Illuminate\Support\Facades\Route;
use AaronLumsden\LaravelAiADK\Livewire\Dashboard;
use AaronLumsden\LaravelAiADK\Livewire\ChatInterface;
use AaronLumsden\LaravelAiADK\Livewire\EvalRunner;
use AaronLumsden\LaravelAiADK\Livewire\Analytics;

/*
|--------------------------------------------------------------------------
| Laravel Agent ADK Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for the Laravel Agent ADK package.
| These routes provide a web interface for managing and monitoring your agents.
|
*/

Route::get('/', Dashboard::class)->name('dashboard');
Route::get('/chat', ChatInterface::class)->name('chat');
Route::get('/eval', EvalRunner::class)->name('eval-runner');
Route::get('/analytics', Analytics::class)->name('analytics');
Route::get('/test-modal', function () {
    return view('agent-adk::test-modal');
})->name('test-modal');
