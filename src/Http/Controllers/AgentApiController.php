<?php

namespace Vizra\VizraAdk\Http\Controllers;

namespace Vizra\VizraAdk\Http\Controllers;

use Vizra\VizraAdk\Facades\Agent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller; // Base controller
use Illuminate\Support\Facades\Validator;
use Vizra\VizraAdk\Exceptions\AgentNotFoundException;

class AgentApiController extends Controller
{
    /**
     * Handle a request to interact with a specified agent.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function handleAgentInteraction(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'agent_name' => 'required|string',
            'input' => 'required|string', // Assuming text input for simplicity
            'session_id' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $agentName = $request->input('agent_name');
        $input = $request->input('input');
        $sessionId = $request->input('session_id', session()->getId()); // Use Laravel session ID if none provided

        if (!Agent::hasAgent($agentName)) {
            return response()->json([
               'error' => "Agent '{$agentName}' is not registered or found.",
               'message' => "Please ensure the agent is registered, typically in a ServiceProvider using Agent::build() or Agent::define()."
            ], 404);
        }

        try {
            $response = Agent::run($agentName, $input, $sessionId);

            return response()->json([
                'agent_name' => $agentName,
                'session_id' => $sessionId, // Return the session ID used
                'response' => $response,
            ]);

        } catch (AgentNotFoundException $e) {
            // This might be redundant if Agent::hasAgent() check is solid, but good for safety
            return response()->json(['error' => "Agent '{$agentName}' could not be found or loaded.", 'detail' => $e->getMessage()], 404);
        } catch (\Vizra\VizraAdk\Exceptions\ToolExecutionException $e) {
            // Log the full error for server-side diagnostics
            logger()->error("Tool execution error for agent {$agentName}: " . $e->getMessage(), ['exception' => $e]);
            return response()->json(['error' => 'A tool required by the agent failed to execute.', 'detail' => $e->getMessage()], 500);
        } catch (\Vizra\VizraAdk\Exceptions\AgentConfigurationException $e) {
            logger()->error("Agent configuration error for agent {$agentName}: " . $e->getMessage(), ['exception' => $e]);
            return response()->json(['error' => 'Agent configuration error.', 'detail' => $e->getMessage()], 500);
        } catch (\Throwable $e) {
            // Catch-all for other unexpected errors, including Prism-PHP client errors
            logger()->error("Error during agent '{$agentName}' execution: " . $e->getMessage(), ['exception' => $e]);
            return response()->json(['error' => 'An unexpected error occurred while processing your request with the agent.', 'detail' => $e->getMessage()], 500);
        }
    }
}
