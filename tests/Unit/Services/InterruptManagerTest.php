<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Vizra\VizraADK\Events\InterruptApproved;
use Vizra\VizraADK\Events\InterruptRejected;
use Vizra\VizraADK\Events\InterruptRequested;
use Vizra\VizraADK\Exceptions\InterruptException;
use Vizra\VizraADK\Models\AgentInterrupt;
use Vizra\VizraADK\Models\AgentSession;
use Vizra\VizraADK\Services\InterruptManager;
use Vizra\VizraADK\System\AgentContext;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Run migrations for testing
    $this->loadMigrationsFrom(__DIR__ . '/../../../database/migrations');

    $this->interruptManager = new InterruptManager();
});

function createTestContext(string $sessionId = null, string $agentName = 'test_agent'): AgentContext
{
    $sessionId = $sessionId ?? (string) Str::uuid();

    // Create the session in the database
    AgentSession::create([
        'session_id' => $sessionId,
        'agent_name' => $agentName,
        'state_data' => ['agent_name' => $agentName],
    ]);

    $context = new AgentContext($sessionId, 'Test input');
    $context->setState('agent_name', $agentName);

    return $context;
}

describe('InterruptManager', function () {
    it('creates an interrupt and throws InterruptException', function () {
        Event::fake();

        $context = createTestContext();

        try {
            $this->interruptManager->interrupt(
                $context,
                'Test approval needed',
                ['action' => 'delete', 'user_id' => 123]
            );

            // Should not reach here
            expect(false)->toBeTrue();
        } catch (InterruptException $e) {
            expect($e->getReason())->toBe('Test approval needed');
            expect($e->getData())->toBe(['action' => 'delete', 'user_id' => 123]);
            expect($e->getInterrupt())->toBeInstanceOf(AgentInterrupt::class);
        }

        Event::assertDispatched(InterruptRequested::class);
    });

    it('creates interrupt with correct data', function () {
        Event::fake();

        $context = createTestContext();

        try {
            $this->interruptManager->interrupt(
                $context,
                'Approval needed',
                ['key' => 'value'],
                AgentInterrupt::TYPE_CONFIRMATION,
                48
            );
        } catch (InterruptException $e) {
            $interrupt = $e->getInterrupt();

            expect($interrupt->session_id)->toBe($context->getSessionId());
            expect($interrupt->agent_name)->toBe('test_agent');
            expect($interrupt->type)->toBe(AgentInterrupt::TYPE_CONFIRMATION);
            expect($interrupt->reason)->toBe('Approval needed');
            expect($interrupt->data)->toBe(['key' => 'value']);
            expect($interrupt->status)->toBe(AgentInterrupt::STATUS_PENDING);
            expect($interrupt->expires_at)->not->toBeNull();
        }
    });

    it('can approve an interrupt', function () {
        Event::fake();

        // Create a pending interrupt
        $interrupt = AgentInterrupt::create([
            'session_id' => (string) Str::uuid(),
            'agent_name' => 'test_agent',
            'type' => AgentInterrupt::TYPE_APPROVAL,
            'reason' => 'Test',
            'data' => [],
            'status' => AgentInterrupt::STATUS_PENDING,
            'expires_at' => now()->addHours(24),
        ]);

        $approved = $this->interruptManager->approve(
            $interrupt->id,
            ['modified' => 'value'],
            'user_123'
        );

        expect($approved->status)->toBe(AgentInterrupt::STATUS_APPROVED);
        expect($approved->modifications)->toBe(['modified' => 'value']);
        expect($approved->resolved_by)->toBe('user_123');
        expect($approved->resolved_at)->not->toBeNull();

        Event::assertDispatched(InterruptApproved::class);
    });

    it('can reject an interrupt', function () {
        Event::fake();

        $interrupt = AgentInterrupt::create([
            'session_id' => (string) Str::uuid(),
            'agent_name' => 'test_agent',
            'type' => AgentInterrupt::TYPE_APPROVAL,
            'reason' => 'Test',
            'data' => [],
            'status' => AgentInterrupt::STATUS_PENDING,
            'expires_at' => now()->addHours(24),
        ]);

        $rejected = $this->interruptManager->reject(
            $interrupt->id,
            'Not authorized',
            'user_456'
        );

        expect($rejected->status)->toBe(AgentInterrupt::STATUS_REJECTED);
        expect($rejected->rejection_reason)->toBe('Not authorized');
        expect($rejected->resolved_by)->toBe('user_456');

        Event::assertDispatched(InterruptRejected::class);
    });

    it('can cancel an interrupt', function () {
        $interrupt = AgentInterrupt::create([
            'session_id' => (string) Str::uuid(),
            'agent_name' => 'test_agent',
            'type' => AgentInterrupt::TYPE_APPROVAL,
            'reason' => 'Test',
            'data' => [],
            'status' => AgentInterrupt::STATUS_PENDING,
            'expires_at' => now()->addHours(24),
        ]);

        $cancelled = $this->interruptManager->cancel($interrupt->id, 'user_789');

        expect($cancelled->status)->toBe(AgentInterrupt::STATUS_CANCELLED);
        expect($cancelled->resolved_by)->toBe('user_789');
    });

    it('can respond to an interrupt', function () {
        Event::fake();

        $interrupt = AgentInterrupt::create([
            'session_id' => (string) Str::uuid(),
            'agent_name' => 'test_agent',
            'type' => AgentInterrupt::TYPE_INPUT,
            'reason' => 'Need more information',
            'data' => ['field' => 'name'],
            'status' => AgentInterrupt::STATUS_PENDING,
            'expires_at' => now()->addHours(24),
        ]);

        $responded = $this->interruptManager->respond(
            $interrupt->id,
            'John Doe',
            'user_123'
        );

        expect($responded->status)->toBe(AgentInterrupt::STATUS_APPROVED);
        expect($responded->user_response)->toBe('John Doe');
        expect($responded->resolved_by)->toBe('user_123');

        Event::assertDispatched(InterruptApproved::class);
    });

    it('cannot approve an already resolved interrupt', function () {
        $interrupt = AgentInterrupt::create([
            'session_id' => (string) Str::uuid(),
            'agent_name' => 'test_agent',
            'type' => AgentInterrupt::TYPE_APPROVAL,
            'reason' => 'Test',
            'data' => [],
            'status' => AgentInterrupt::STATUS_APPROVED,
            'resolved_at' => now(),
        ]);

        expect(fn () => $this->interruptManager->approve($interrupt->id))
            ->toThrow(RuntimeException::class, 'Interrupt has already been resolved.');
    });

    it('cannot approve an expired interrupt', function () {
        $interrupt = AgentInterrupt::create([
            'session_id' => (string) Str::uuid(),
            'agent_name' => 'test_agent',
            'type' => AgentInterrupt::TYPE_APPROVAL,
            'reason' => 'Test',
            'data' => [],
            'status' => AgentInterrupt::STATUS_PENDING,
            'expires_at' => now()->subHours(1), // Expired 1 hour ago
        ]);

        expect(fn () => $this->interruptManager->approve($interrupt->id))
            ->toThrow(RuntimeException::class, 'Interrupt has expired and cannot be approved.');

        // Verify the interrupt was marked as expired
        $interrupt->refresh();
        expect($interrupt->status)->toBe(AgentInterrupt::STATUS_EXPIRED);
    });

    it('can get pending interrupts', function () {
        $sessionId = (string) Str::uuid();

        // Create pending interrupt
        AgentInterrupt::create([
            'session_id' => $sessionId,
            'agent_name' => 'test_agent',
            'type' => AgentInterrupt::TYPE_APPROVAL,
            'reason' => 'Test 1',
            'data' => [],
            'status' => AgentInterrupt::STATUS_PENDING,
            'expires_at' => now()->addHours(24),
        ]);

        // Create approved interrupt (should not appear in pending)
        AgentInterrupt::create([
            'session_id' => $sessionId,
            'agent_name' => 'test_agent',
            'type' => AgentInterrupt::TYPE_APPROVAL,
            'reason' => 'Test 2',
            'data' => [],
            'status' => AgentInterrupt::STATUS_APPROVED,
            'resolved_at' => now(),
        ]);

        $pending = $this->interruptManager->getPending($sessionId);

        expect($pending)->toHaveCount(1);
        expect($pending->first()->reason)->toBe('Test 1');
    });

    it('can get interrupts for a session', function () {
        $sessionId = (string) Str::uuid();

        AgentInterrupt::create([
            'session_id' => $sessionId,
            'agent_name' => 'test_agent',
            'type' => AgentInterrupt::TYPE_APPROVAL,
            'reason' => 'Test 1',
            'data' => [],
            'status' => AgentInterrupt::STATUS_PENDING,
            'expires_at' => now()->addHours(24),
        ]);

        AgentInterrupt::create([
            'session_id' => $sessionId,
            'agent_name' => 'test_agent',
            'type' => AgentInterrupt::TYPE_APPROVAL,
            'reason' => 'Test 2',
            'data' => [],
            'status' => AgentInterrupt::STATUS_APPROVED,
            'resolved_at' => now(),
        ]);

        // Get all interrupts
        $all = $this->interruptManager->getForSession($sessionId, false);
        expect($all)->toHaveCount(2);

        // Get only pending
        $pending = $this->interruptManager->getForSession($sessionId, true);
        expect($pending)->toHaveCount(1);
    });

    it('can check interrupt status', function () {
        $interrupt = AgentInterrupt::create([
            'session_id' => (string) Str::uuid(),
            'agent_name' => 'test_agent',
            'type' => AgentInterrupt::TYPE_APPROVAL,
            'reason' => 'Test',
            'data' => [],
            'status' => AgentInterrupt::STATUS_PENDING,
            'expires_at' => now()->addHours(24),
        ]);

        $status = $this->interruptManager->checkStatus($interrupt->id);

        expect($status['approved'])->toBeFalse();
        expect($status['status'])->toBe(AgentInterrupt::STATUS_PENDING);

        // Approve and check again
        $this->interruptManager->approve($interrupt->id, ['key' => 'value']);

        $status = $this->interruptManager->checkStatus($interrupt->id);

        expect($status['approved'])->toBeTrue();
        expect($status['modifications'])->toBe(['key' => 'value']);
        expect($status['status'])->toBe(AgentInterrupt::STATUS_APPROVED);
    });

    it('returns not_found status for non-existent interrupt', function () {
        $status = $this->interruptManager->checkStatus('non-existent-id');

        expect($status['status'])->toBe('not_found');
        expect($status['approved'])->toBeFalse();
    });

    it('checks tool approval requirement from config', function () {
        // Set config
        config(['vizra-adk.human_in_loop.tool_permissions' => [
            'delete_record' => ['require_approval' => true],
            'read_data' => ['require_approval' => false],
        ]]);

        expect($this->interruptManager->toolRequiresApproval('delete_record'))->toBeTrue();
        expect($this->interruptManager->toolRequiresApproval('read_data'))->toBeFalse();
        expect($this->interruptManager->toolRequiresApproval('unknown_tool'))->toBeFalse();
    });

    it('can expire overdue interrupts', function () {
        // Create expired interrupt
        AgentInterrupt::create([
            'session_id' => (string) Str::uuid(),
            'agent_name' => 'test_agent',
            'type' => AgentInterrupt::TYPE_APPROVAL,
            'reason' => 'Expired',
            'data' => [],
            'status' => AgentInterrupt::STATUS_PENDING,
            'expires_at' => now()->subHours(1),
        ]);

        // Create non-expired interrupt
        AgentInterrupt::create([
            'session_id' => (string) Str::uuid(),
            'agent_name' => 'test_agent',
            'type' => AgentInterrupt::TYPE_APPROVAL,
            'reason' => 'Valid',
            'data' => [],
            'status' => AgentInterrupt::STATUS_PENDING,
            'expires_at' => now()->addHours(24),
        ]);

        $expiredCount = $this->interruptManager->expireOverdue();

        expect($expiredCount)->toBe(1);
        expect(AgentInterrupt::where('status', AgentInterrupt::STATUS_EXPIRED)->count())->toBe(1);
        expect(AgentInterrupt::where('status', AgentInterrupt::STATUS_PENDING)->count())->toBe(1);
    });

    it('can cleanup old resolved interrupts', function () {
        // Create old resolved interrupt
        $old = AgentInterrupt::create([
            'session_id' => (string) Str::uuid(),
            'agent_name' => 'test_agent',
            'type' => AgentInterrupt::TYPE_APPROVAL,
            'reason' => 'Old',
            'data' => [],
            'status' => AgentInterrupt::STATUS_APPROVED,
            'resolved_at' => now()->subDays(60),
        ]);

        // Create recent resolved interrupt
        AgentInterrupt::create([
            'session_id' => (string) Str::uuid(),
            'agent_name' => 'test_agent',
            'type' => AgentInterrupt::TYPE_APPROVAL,
            'reason' => 'Recent',
            'data' => [],
            'status' => AgentInterrupt::STATUS_APPROVED,
            'resolved_at' => now()->subDays(5),
        ]);

        $deletedCount = $this->interruptManager->cleanup(30);

        expect($deletedCount)->toBe(1);
        expect(AgentInterrupt::count())->toBe(1);
    });
});

describe('AgentInterrupt Model', function () {
    it('has correct status check methods', function () {
        $pending = AgentInterrupt::create([
            'session_id' => (string) Str::uuid(),
            'agent_name' => 'test_agent',
            'type' => AgentInterrupt::TYPE_APPROVAL,
            'reason' => 'Test',
            'data' => [],
            'status' => AgentInterrupt::STATUS_PENDING,
            'expires_at' => now()->addHours(24),
        ]);

        expect($pending->isPending())->toBeTrue();
        expect($pending->isApproved())->toBeFalse();
        expect($pending->isRejected())->toBeFalse();
        expect($pending->isResolved())->toBeFalse();
        expect($pending->isExpired())->toBeFalse();
    });

    it('detects expired interrupts correctly', function () {
        $expired = AgentInterrupt::create([
            'session_id' => (string) Str::uuid(),
            'agent_name' => 'test_agent',
            'type' => AgentInterrupt::TYPE_APPROVAL,
            'reason' => 'Test',
            'data' => [],
            'status' => AgentInterrupt::STATUS_PENDING,
            'expires_at' => now()->subHours(1),
        ]);

        expect($expired->isExpired())->toBeTrue();
    });

    it('uses scopes correctly', function () {
        $sessionId = (string) Str::uuid();

        AgentInterrupt::create([
            'session_id' => $sessionId,
            'agent_name' => 'agent_a',
            'type' => AgentInterrupt::TYPE_APPROVAL,
            'reason' => 'Test 1',
            'data' => [],
            'status' => AgentInterrupt::STATUS_PENDING,
            'expires_at' => now()->addHours(24),
        ]);

        AgentInterrupt::create([
            'session_id' => $sessionId,
            'agent_name' => 'agent_b',
            'type' => AgentInterrupt::TYPE_APPROVAL,
            'reason' => 'Test 2',
            'data' => [],
            'status' => AgentInterrupt::STATUS_APPROVED,
        ]);

        expect(AgentInterrupt::pending()->count())->toBe(1);
        expect(AgentInterrupt::forSession($sessionId)->count())->toBe(2);
        expect(AgentInterrupt::forAgent('agent_a')->count())->toBe(1);
        expect(AgentInterrupt::active()->count())->toBe(1);
    });
});

describe('InterruptException', function () {
    it('contains correct data', function () {
        $interrupt = AgentInterrupt::create([
            'session_id' => (string) Str::uuid(),
            'agent_name' => 'test_agent',
            'type' => AgentInterrupt::TYPE_APPROVAL,
            'reason' => 'Test reason',
            'data' => ['key' => 'value'],
            'status' => AgentInterrupt::STATUS_PENDING,
        ]);

        $exception = new InterruptException(
            'Test reason',
            ['key' => 'value'],
            $interrupt
        );

        expect($exception->getReason())->toBe('Test reason');
        expect($exception->getData())->toBe(['key' => 'value']);
        expect($exception->getInterrupt())->toBe($interrupt);
        expect($exception->getInterruptId())->toBe($interrupt->id);
        expect($exception->getMessage())->toBe('Execution interrupted: Test reason');
    });

    it('can be converted to array', function () {
        $interrupt = AgentInterrupt::create([
            'session_id' => (string) Str::uuid(),
            'agent_name' => 'test_agent',
            'type' => AgentInterrupt::TYPE_APPROVAL,
            'reason' => 'Test reason',
            'data' => ['key' => 'value'],
            'status' => AgentInterrupt::STATUS_PENDING,
        ]);

        $exception = new InterruptException(
            'Test reason',
            ['key' => 'value'],
            $interrupt
        );

        $array = $exception->toArray();

        expect($array['interrupted'])->toBeTrue();
        expect($array['interrupt_id'])->toBe($interrupt->id);
        expect($array['reason'])->toBe('Test reason');
        expect($array['data'])->toBe(['key' => 'value']);
    });
});
