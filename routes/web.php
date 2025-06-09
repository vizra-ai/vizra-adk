<?php

use Illuminate\Support\Facades\Route;
use Vizra\VizraSdk\Livewire\Dashboard;
use Vizra\VizraSdk\Livewire\ChatInterface;
use Vizra\VizraSdk\Livewire\EvalRunner;
use Vizra\VizraSdk\Livewire\Analytics;

/*
|--------------------------------------------------------------------------
| Vizra SDK Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for the Vizra SDK package.
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
