<?php

use Illuminate\Support\Facades\Route;
use AaronLumsden\LaravelAgentADK\Livewire\Dashboard;
use AaronLumsden\LaravelAgentADK\Livewire\ChatInterface;

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
