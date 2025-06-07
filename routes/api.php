<?php

use Illuminate\Support\Facades\Route;
use AaronLumsden\LaravelAiADK\Http\Controllers\AgentApiController;

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
    ->name('agent-adk.api.interact');

// Example of a route for a specific, predefined agent if desired
// Route::post('/weather', function (Request $request) {
//     $input = $request->input('message');
//     $sessionId = $request->input('session_id', session()->getId());
//     try {
//         $response = \AaronLumsden\LaravelAiADK\Facades\Agent::run('weather_reporter', $input, $sessionId);
//         return response()->json(['response' => $response, 'session_id' => $sessionId]);
//     } catch (\Throwable $e) {
//         return response()->json(['error' => $e->getMessage()], 500);
//     }
// })->name('agent-adk.api.weather');
