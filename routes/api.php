<?php

use Illuminate\Support\Facades\Route;
use Vizra\VizraADK\Http\Controllers\AgentApiController;
use Vizra\VizraADK\Http\Controllers\InterruptController;
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

/*
|--------------------------------------------------------------------------
| Human-in-the-Loop Interrupt Routes
|--------------------------------------------------------------------------
|
| Routes for managing agent execution interrupts that require human
| approval, input, or feedback.
|
*/

Route::prefix('interrupts')->group(function () {
    // List all interrupts (with optional filters)
    Route::get('/', [InterruptController::class, 'index'])
        ->name('vizra.api.interrupts.index');

    // Get a specific interrupt
    Route::get('/{id}', [InterruptController::class, 'show'])
        ->name('vizra.api.interrupts.show');

    // Get interrupt status
    Route::get('/{id}/status', [InterruptController::class, 'status'])
        ->name('vizra.api.interrupts.status');

    // Approve an interrupt
    Route::post('/{id}/approve', [InterruptController::class, 'approve'])
        ->name('vizra.api.interrupts.approve');

    // Reject an interrupt
    Route::post('/{id}/reject', [InterruptController::class, 'reject'])
        ->name('vizra.api.interrupts.reject');

    // Cancel an interrupt
    Route::post('/{id}/cancel', [InterruptController::class, 'cancel'])
        ->name('vizra.api.interrupts.cancel');

    // Respond to an interrupt (for input/feedback types)
    Route::post('/{id}/respond', [InterruptController::class, 'respond'])
        ->name('vizra.api.interrupts.respond');
});

// Session-specific interrupt routes
Route::get('/sessions/{sessionId}/interrupts', [InterruptController::class, 'forSession'])
    ->name('vizra.api.sessions.interrupts');

// Agent-specific interrupt routes
Route::get('/agents/{agentName}/interrupts', [InterruptController::class, 'forAgent'])
    ->name('vizra.api.agents.interrupts');
