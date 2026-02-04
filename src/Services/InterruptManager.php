<?php

namespace Vizra\VizraADK\Services;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Event;
use Vizra\VizraADK\Events\InterruptApproved;
use Vizra\VizraADK\Events\InterruptRejected;
use Vizra\VizraADK\Events\InterruptRequested;
use Vizra\VizraADK\Exceptions\InterruptException;
use Vizra\VizraADK\Models\AgentInterrupt;
use Vizra\VizraADK\System\AgentContext;

/**
 * Service for managing Human-in-the-Loop interrupts.
 *
 * This service handles the creation, approval, rejection, and resumption
 * of agent execution interrupts that require human input or approval.
 */
class InterruptManager
{
    /**
     * Create an interrupt and pause execution.
     *
     * This method creates an interrupt record in the database and throws
     * an InterruptException to halt agent execution until human approval.
     *
     * @param AgentContext $context The current agent context
     * @param string $reason The reason for the interrupt
     * @param array $data Additional data to store with the interrupt
     * @param string $type The type of interrupt (approval, confirmation, input, feedback)
     * @param int $expiresInHours Hours until the interrupt expires (default: 24)
     * @throws InterruptException
     */
    public function interrupt(
        AgentContext $context,
        string $reason,
        array $data = [],
        string $type = AgentInterrupt::TYPE_APPROVAL,
        int $expiresInHours = 24
    ): never {
        $agentName = $context->getState('agent_name', 'unknown');

        $interrupt = AgentInterrupt::create([
            'session_id' => $context->getSessionId(),
            'workflow_id' => $context->getState('workflow_id'),
            'step_name' => $context->getState('current_step'),
            'agent_name' => $agentName,
            'type' => $type,
            'reason' => $reason,
            'data' => $data,
            'status' => AgentInterrupt::STATUS_PENDING,
            'expires_at' => now()->addHours($expiresInHours),
        ]);

        Event::dispatch(new InterruptRequested($context, $agentName, $interrupt, $reason, $data));

        throw new InterruptException($reason, $data, $interrupt);
    }

    /**
     * Request approval for an action.
     *
     * Convenience method for creating an approval-type interrupt.
     *
     * @param AgentContext $context The current agent context
     * @param string $reason The reason approval is needed
     * @param array $data Data about the action requiring approval
     * @throws InterruptException
     */
    public function requestApproval(
        AgentContext $context,
        string $reason,
        array $data = []
    ): never {
        $this->interrupt($context, $reason, $data, AgentInterrupt::TYPE_APPROVAL);
    }

    /**
     * Request confirmation from the user.
     *
     * Convenience method for creating a confirmation-type interrupt.
     *
     * @param AgentContext $context The current agent context
     * @param string $reason The reason confirmation is needed
     * @param array $data Data about what needs confirmation
     * @throws InterruptException
     */
    public function requestConfirmation(
        AgentContext $context,
        string $reason,
        array $data = []
    ): never {
        $this->interrupt($context, $reason, $data, AgentInterrupt::TYPE_CONFIRMATION);
    }

    /**
     * Request input from the user.
     *
     * Convenience method for creating an input-type interrupt.
     *
     * @param AgentContext $context The current agent context
     * @param string $reason The reason input is needed
     * @param array $data Data about what input is needed
     * @throws InterruptException
     */
    public function requestInput(
        AgentContext $context,
        string $reason,
        array $data = []
    ): never {
        $this->interrupt($context, $reason, $data, AgentInterrupt::TYPE_INPUT);
    }

    /**
     * Request feedback from the user.
     *
     * Convenience method for creating a feedback-type interrupt.
     *
     * @param AgentContext $context The current agent context
     * @param string $reason The reason feedback is needed
     * @param array $data Data about what feedback is needed
     * @throws InterruptException
     */
    public function requestFeedback(
        AgentContext $context,
        string $reason,
        array $data = []
    ): never {
        $this->interrupt($context, $reason, $data, AgentInterrupt::TYPE_FEEDBACK);
    }

    /**
     * Approve an interrupt.
     *
     * @param string $interruptId The interrupt ID
     * @param array|null $modifications Optional modifications to apply to the context
     * @param string|null $userId The user who approved the interrupt
     * @return AgentInterrupt The updated interrupt
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    public function approve(
        string $interruptId,
        ?array $modifications = null,
        ?string $userId = null
    ): AgentInterrupt {
        $interrupt = AgentInterrupt::findOrFail($interruptId);

        if ($interrupt->isExpired()) {
            $interrupt->update(['status' => AgentInterrupt::STATUS_EXPIRED]);
            throw new \RuntimeException('Interrupt has expired and cannot be approved.');
        }

        if ($interrupt->isResolved()) {
            throw new \RuntimeException('Interrupt has already been resolved.');
        }

        $interrupt->update([
            'status' => AgentInterrupt::STATUS_APPROVED,
            'modifications' => $modifications,
            'resolved_by' => $userId,
            'resolved_at' => now(),
        ]);

        Event::dispatch(new InterruptApproved($interrupt, $modifications, $userId));

        return $interrupt;
    }

    /**
     * Reject an interrupt.
     *
     * @param string $interruptId The interrupt ID
     * @param string|null $reason The reason for rejection
     * @param string|null $userId The user who rejected the interrupt
     * @return AgentInterrupt The updated interrupt
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    public function reject(
        string $interruptId,
        ?string $reason = null,
        ?string $userId = null
    ): AgentInterrupt {
        $interrupt = AgentInterrupt::findOrFail($interruptId);

        if ($interrupt->isResolved()) {
            throw new \RuntimeException('Interrupt has already been resolved.');
        }

        $interrupt->update([
            'status' => AgentInterrupt::STATUS_REJECTED,
            'rejection_reason' => $reason,
            'resolved_by' => $userId,
            'resolved_at' => now(),
        ]);

        Event::dispatch(new InterruptRejected($interrupt, $reason, $userId));

        return $interrupt;
    }

    /**
     * Cancel an interrupt.
     *
     * @param string $interruptId The interrupt ID
     * @param string|null $userId The user who cancelled the interrupt
     * @return AgentInterrupt The updated interrupt
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    public function cancel(string $interruptId, ?string $userId = null): AgentInterrupt
    {
        $interrupt = AgentInterrupt::findOrFail($interruptId);

        if ($interrupt->isResolved()) {
            throw new \RuntimeException('Interrupt has already been resolved.');
        }

        $interrupt->update([
            'status' => AgentInterrupt::STATUS_CANCELLED,
            'resolved_by' => $userId,
            'resolved_at' => now(),
        ]);

        return $interrupt;
    }

    /**
     * Provide a response to an interrupt (for input/feedback types).
     *
     * @param string $interruptId The interrupt ID
     * @param string $response The user's response
     * @param string|null $userId The user who provided the response
     * @return AgentInterrupt The updated interrupt
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    public function respond(
        string $interruptId,
        string $response,
        ?string $userId = null
    ): AgentInterrupt {
        $interrupt = AgentInterrupt::findOrFail($interruptId);

        if ($interrupt->isExpired()) {
            $interrupt->update(['status' => AgentInterrupt::STATUS_EXPIRED]);
            throw new \RuntimeException('Interrupt has expired.');
        }

        if ($interrupt->isResolved()) {
            throw new \RuntimeException('Interrupt has already been resolved.');
        }

        $interrupt->update([
            'status' => AgentInterrupt::STATUS_APPROVED,
            'user_response' => $response,
            'resolved_by' => $userId,
            'resolved_at' => now(),
        ]);

        Event::dispatch(new InterruptApproved($interrupt, ['response' => $response], $userId));

        return $interrupt;
    }

    /**
     * Get an interrupt by ID.
     *
     * @param string $interruptId The interrupt ID
     * @return AgentInterrupt|null
     */
    public function get(string $interruptId): ?AgentInterrupt
    {
        return AgentInterrupt::find($interruptId);
    }

    /**
     * Get all pending interrupts.
     *
     * @param string|null $sessionId Filter by session ID
     * @param string|null $agentName Filter by agent name
     * @return Collection<AgentInterrupt>
     */
    public function getPending(?string $sessionId = null, ?string $agentName = null): Collection
    {
        return AgentInterrupt::query()
            ->active()
            ->when($sessionId, fn ($q) => $q->forSession($sessionId))
            ->when($agentName, fn ($q) => $q->forAgent($agentName))
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Get all interrupts for a session.
     *
     * @param string $sessionId The session ID
     * @param bool $pendingOnly Only return pending interrupts
     * @return Collection<AgentInterrupt>
     */
    public function getForSession(string $sessionId, bool $pendingOnly = false): Collection
    {
        return AgentInterrupt::query()
            ->forSession($sessionId)
            ->when($pendingOnly, fn ($q) => $q->active())
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Get all interrupts for an agent.
     *
     * @param string $agentName The agent name
     * @param bool $pendingOnly Only return pending interrupts
     * @return Collection<AgentInterrupt>
     */
    public function getForAgent(string $agentName, bool $pendingOnly = false): Collection
    {
        return AgentInterrupt::query()
            ->forAgent($agentName)
            ->when($pendingOnly, fn ($q) => $q->active())
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Check if a tool requires approval based on configuration.
     *
     * @param string $toolName The tool name
     * @return bool
     */
    public function toolRequiresApproval(string $toolName): bool
    {
        $toolPermissions = config('vizra-adk.human_in_loop.tool_permissions', []);

        // Check specific tool config
        if (isset($toolPermissions[$toolName])) {
            return $toolPermissions[$toolName]['require_approval'] ?? false;
        }

        // Check default config
        return $toolPermissions['*']['require_approval'] ?? false;
    }

    /**
     * Check if the interrupt has been approved and get any modifications.
     *
     * @param string $interruptId The interrupt ID
     * @return array{approved: bool, modifications: array|null, response: string|null}
     */
    public function checkStatus(string $interruptId): array
    {
        $interrupt = AgentInterrupt::find($interruptId);

        if (!$interrupt) {
            return [
                'approved' => false,
                'modifications' => null,
                'response' => null,
                'status' => 'not_found',
            ];
        }

        return [
            'approved' => $interrupt->isApproved(),
            'modifications' => $interrupt->modifications,
            'response' => $interrupt->user_response,
            'status' => $interrupt->status,
        ];
    }

    /**
     * Expire all overdue interrupts.
     *
     * This should be called periodically (e.g., via a scheduled command).
     *
     * @return int The number of expired interrupts
     */
    public function expireOverdue(): int
    {
        return AgentInterrupt::query()
            ->where('status', AgentInterrupt::STATUS_PENDING)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->update([
                'status' => AgentInterrupt::STATUS_EXPIRED,
                'resolved_at' => now(),
            ]);
    }

    /**
     * Clean up old resolved interrupts.
     *
     * @param int $daysToKeep Number of days to keep resolved interrupts
     * @return int The number of deleted interrupts
     */
    public function cleanup(int $daysToKeep = 30): int
    {
        return AgentInterrupt::query()
            ->whereIn('status', [
                AgentInterrupt::STATUS_APPROVED,
                AgentInterrupt::STATUS_REJECTED,
                AgentInterrupt::STATUS_EXPIRED,
                AgentInterrupt::STATUS_CANCELLED,
            ])
            ->where('resolved_at', '<', now()->subDays($daysToKeep))
            ->delete();
    }
}
