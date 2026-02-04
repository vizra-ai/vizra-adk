<?php

namespace Vizra\VizraADK\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Validator;
use Vizra\VizraADK\Models\AgentInterrupt;
use Vizra\VizraADK\Services\InterruptManager;

/**
 * Controller for managing Human-in-the-Loop interrupts.
 */
class InterruptController extends Controller
{
    public function __construct(
        protected InterruptManager $interruptManager
    ) {}

    /**
     * List all pending interrupts.
     *
     * GET /api/vizra-adk/interrupts
     *
     * Query parameters:
     * - session_id: Filter by session ID
     * - agent_name: Filter by agent name
     * - status: Filter by status (pending, approved, rejected, expired, cancelled)
     * - pending_only: If true, only return active (pending and not expired) interrupts
     */
    public function index(Request $request): JsonResponse
    {
        $query = AgentInterrupt::query()
            ->orderBy('created_at', 'desc');

        if ($request->has('session_id')) {
            $query->forSession($request->input('session_id'));
        }

        if ($request->has('agent_name')) {
            $query->forAgent($request->input('agent_name'));
        }

        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->boolean('pending_only', false)) {
            $query->active();
        }

        $interrupts = $query->paginate($request->input('per_page', 15));

        return response()->json($interrupts);
    }

    /**
     * Get a specific interrupt.
     *
     * GET /api/vizra-adk/interrupts/{id}
     */
    public function show(string $id): JsonResponse
    {
        $interrupt = AgentInterrupt::find($id);

        if (!$interrupt) {
            return response()->json([
                'error' => 'Interrupt not found',
                'message' => "No interrupt found with ID: {$id}",
            ], 404);
        }

        return response()->json([
            'data' => $interrupt,
            'is_expired' => $interrupt->isExpired(),
            'is_resolved' => $interrupt->isResolved(),
        ]);
    }

    /**
     * Approve an interrupt.
     *
     * POST /api/vizra-adk/interrupts/{id}/approve
     *
     * Body parameters:
     * - modifications: (optional) Array of modifications to apply to context
     * - user_id: (optional) ID of the user approving
     */
    public function approve(Request $request, string $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'modifications' => 'nullable|array',
            'user_id' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            $interrupt = $this->interruptManager->approve(
                $id,
                $request->input('modifications'),
                $request->input('user_id')
            );

            return response()->json([
                'message' => 'Interrupt approved successfully',
                'data' => $interrupt,
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'error' => 'Interrupt not found',
                'message' => "No interrupt found with ID: {$id}",
            ], 404);
        } catch (\RuntimeException $e) {
            return response()->json([
                'error' => 'Cannot approve interrupt',
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Reject an interrupt.
     *
     * POST /api/vizra-adk/interrupts/{id}/reject
     *
     * Body parameters:
     * - reason: (optional) Reason for rejection
     * - user_id: (optional) ID of the user rejecting
     */
    public function reject(Request $request, string $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'reason' => 'nullable|string|max:1000',
            'user_id' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            $interrupt = $this->interruptManager->reject(
                $id,
                $request->input('reason'),
                $request->input('user_id')
            );

            return response()->json([
                'message' => 'Interrupt rejected successfully',
                'data' => $interrupt,
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'error' => 'Interrupt not found',
                'message' => "No interrupt found with ID: {$id}",
            ], 404);
        } catch (\RuntimeException $e) {
            return response()->json([
                'error' => 'Cannot reject interrupt',
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Cancel an interrupt.
     *
     * POST /api/vizra-adk/interrupts/{id}/cancel
     *
     * Body parameters:
     * - user_id: (optional) ID of the user cancelling
     */
    public function cancel(Request $request, string $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            $interrupt = $this->interruptManager->cancel(
                $id,
                $request->input('user_id')
            );

            return response()->json([
                'message' => 'Interrupt cancelled successfully',
                'data' => $interrupt,
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'error' => 'Interrupt not found',
                'message' => "No interrupt found with ID: {$id}",
            ], 404);
        } catch (\RuntimeException $e) {
            return response()->json([
                'error' => 'Cannot cancel interrupt',
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Provide a response to an interrupt (for input/feedback types).
     *
     * POST /api/vizra-adk/interrupts/{id}/respond
     *
     * Body parameters:
     * - response: (required) The user's response
     * - user_id: (optional) ID of the user responding
     */
    public function respond(Request $request, string $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'response' => 'required|string',
            'user_id' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            $interrupt = $this->interruptManager->respond(
                $id,
                $request->input('response'),
                $request->input('user_id')
            );

            return response()->json([
                'message' => 'Response recorded successfully',
                'data' => $interrupt,
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'error' => 'Interrupt not found',
                'message' => "No interrupt found with ID: {$id}",
            ], 404);
        } catch (\RuntimeException $e) {
            return response()->json([
                'error' => 'Cannot respond to interrupt',
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Check the status of an interrupt.
     *
     * GET /api/vizra-adk/interrupts/{id}/status
     */
    public function status(string $id): JsonResponse
    {
        $status = $this->interruptManager->checkStatus($id);

        if ($status['status'] === 'not_found') {
            return response()->json([
                'error' => 'Interrupt not found',
                'message' => "No interrupt found with ID: {$id}",
            ], 404);
        }

        return response()->json($status);
    }

    /**
     * Get pending interrupts for a session.
     *
     * GET /api/vizra-adk/sessions/{sessionId}/interrupts
     */
    public function forSession(Request $request, string $sessionId): JsonResponse
    {
        $pendingOnly = $request->boolean('pending_only', true);

        $interrupts = $this->interruptManager->getForSession($sessionId, $pendingOnly);

        return response()->json([
            'data' => $interrupts,
            'count' => $interrupts->count(),
        ]);
    }

    /**
     * Get pending interrupts for an agent.
     *
     * GET /api/vizra-adk/agents/{agentName}/interrupts
     */
    public function forAgent(Request $request, string $agentName): JsonResponse
    {
        $pendingOnly = $request->boolean('pending_only', true);

        $interrupts = $this->interruptManager->getForAgent($agentName, $pendingOnly);

        return response()->json([
            'data' => $interrupts,
            'count' => $interrupts->count(),
        ]);
    }
}
