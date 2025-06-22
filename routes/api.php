<?php

use Illuminate\Support\Facades\Route;
use Vizra\VizraADK\Http\Controllers\AgentApiController;
use Vizra\VizraADK\Http\Controllers\OpenAICompatibleController;

/*
|--------------------------------------------------------------------------
| Agent ADK API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your package. These
| routes are loaded by the AgentServiceProvider.
|
*/

Route::post('/interact', [AgentApiController::class, 'handleAgentInteraction'])
    ->name('vizra.api.interact');

// OpenAI-compatible Chat Completions API
Route::post('/chat/completions', [OpenAICompatibleController::class, 'chatCompletions'])
    ->name('vizra.api.openai.chat.completions');
