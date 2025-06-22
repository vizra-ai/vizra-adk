<?php

use Illuminate\Support\Facades\Route;
use Vizra\VizraADK\Http\Controllers\WebController;

/*
|--------------------------------------------------------------------------
| Vizra SDK Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for the Vizra SDK package.
| These routes provide a web interface for managing and monitoring your agents.
|
*/

Route::get('/', [WebController::class, 'dashboard'])->name('dashboard');
Route::get('/chat', [WebController::class, 'chat'])->name('chat');
Route::get('/eval', [WebController::class, 'eval'])->name('eval-runner');
