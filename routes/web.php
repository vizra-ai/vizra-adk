<?php

use Illuminate\Support\Facades\Route;
use Vizra\VizraADK\Livewire\Dashboard;
use Vizra\VizraADK\Livewire\ChatInterface;
use Vizra\VizraADK\Livewire\EvalRunner;

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
