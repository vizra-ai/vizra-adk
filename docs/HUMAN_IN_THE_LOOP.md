# Human-in-the-Loop (HITL) Guide

This guide explains how to use the Human-in-the-Loop functionality in Vizra ADK to pause agent execution and request human approval, input, or feedback.

## Overview

Human-in-the-Loop (HITL) allows your agents to:
- **Pause execution** when human oversight is needed
- **Request approval** before performing sensitive actions
- **Collect user input** when additional information is required
- **Gather feedback** on agent decisions or outputs

## Quick Start

### 1. Run the Migration

```bash
php artisan migrate
```

This creates the `agent_interrupts` table.

### 2. Use in Your Agent

```php
use Vizra\VizraADK\Agents\BaseLlmAgent;

class MyAgent extends BaseLlmAgent
{
    protected string $name = 'my_agent';
    protected string $instructions = 'You are a helpful assistant.';

    public function execute(mixed $input, AgentContext $context): mixed
    {
        // Check if action needs approval
        if ($this->isDangerousAction($input)) {
            $this->requireApproval(
                'This action will delete user data',
                ['user_id' => $userId, 'action' => 'delete']
            );
            // Execution stops here until approved
        }

        return parent::execute($input, $context);
    }
}
```

### 3. Handle the Interrupt

When an interrupt is created, an `InterruptException` is thrown. Your application should catch this and notify the user:

```php
use Vizra\VizraADK\Exceptions\InterruptException;

try {
    $response = Agent::run('my_agent', $input, $sessionId);
} catch (InterruptException $e) {
    // Agent needs human approval
    return response()->json([
        'status' => 'awaiting_approval',
        'interrupt_id' => $e->getInterruptId(),
        'reason' => $e->getReason(),
        'data' => $e->getData(),
    ]);
}
```

### 4. Approve or Reject via API

```bash
# Approve
curl -X POST /api/vizra-adk/interrupts/{id}/approve \
  -H "Content-Type: application/json" \
  -d '{"user_id": "admin_123"}'

# Reject
curl -X POST /api/vizra-adk/interrupts/{id}/reject \
  -H "Content-Type: application/json" \
  -d '{"reason": "Not authorized", "user_id": "admin_123"}'
```

## Interrupt Types

### Approval
For actions that need permission to proceed:

```php
$this->requireApproval(
    'Deleting user account',
    ['user_id' => 123, 'email' => 'user@example.com']
);
```

### Confirmation
For verifying the user wants to proceed:

```php
$this->requireConfirmation(
    'Send email to 500 recipients?',
    ['recipient_count' => 500, 'subject' => 'Newsletter']
);
```

### Input
When additional information is needed:

```php
$this->requireInput(
    'Please provide the target date',
    ['field' => 'target_date', 'format' => 'YYYY-MM-DD']
);
```

### Feedback
To collect user feedback on agent output:

```php
$this->requireFeedback(
    'Please review this draft response',
    ['draft' => $generatedResponse]
);
```

## API Reference

### List Interrupts
```
GET /api/vizra-adk/interrupts
```

Query parameters:
- `session_id` - Filter by session
- `agent_name` - Filter by agent
- `status` - Filter by status (pending, approved, rejected, expired, cancelled)
- `pending_only` - Only return active interrupts (default: false)

### Get Interrupt
```
GET /api/vizra-adk/interrupts/{id}
```

### Check Status
```
GET /api/vizra-adk/interrupts/{id}/status
```

Returns:
```json
{
    "approved": false,
    "modifications": null,
    "response": null,
    "status": "pending"
}
```

### Approve
```
POST /api/vizra-adk/interrupts/{id}/approve
```

Body:
```json
{
    "modifications": {"key": "value"},
    "user_id": "admin_123"
}
```

### Reject
```
POST /api/vizra-adk/interrupts/{id}/reject
```

Body:
```json
{
    "reason": "Not authorized for this action",
    "user_id": "admin_123"
}
```

### Respond (for input/feedback types)
```
POST /api/vizra-adk/interrupts/{id}/respond
```

Body:
```json
{
    "response": "User's response text",
    "user_id": "user_456"
}
```

### Cancel
```
POST /api/vizra-adk/interrupts/{id}/cancel
```

### Get Interrupts for Session
```
GET /api/vizra-adk/sessions/{sessionId}/interrupts
```

### Get Interrupts for Agent
```
GET /api/vizra-adk/agents/{agentName}/interrupts
```

## Configuration

In `config/vizra-adk.php`:

```php
'human_in_loop' => [
    // Enable/disable HITL globally
    'enabled' => env('VIZRA_ADK_HITL_ENABLED', true),

    // Hours until pending interrupts expire
    'default_expiration_hours' => env('VIZRA_ADK_HITL_EXPIRATION_HOURS', 24),

    // Days to keep resolved interrupts
    'cleanup_days' => env('VIZRA_ADK_HITL_CLEANUP_DAYS', 30),

    // Tool-specific permissions
    'tool_permissions' => [
        // Default for all tools
        '*' => [
            'require_approval' => false,
        ],

        // Specific tool requiring approval
        'delete_record' => [
            'require_approval' => true,
            'approval_message' => 'This will permanently delete data.',
        ],

        'send_email' => [
            'require_approval' => true,
            'approval_message' => 'An email will be sent.',
        ],
    ],
],
```

## Using with Tools

### Check Tool Approval in Agent

```php
protected function beforeToolCall(string $toolName, array $arguments, AgentContext $context): array
{
    // Check if this tool requires approval
    if ($this->isHitlEnabled() && $this->toolRequiresApproval($toolName)) {
        $message = $this->getToolApprovalMessage($toolName)
            ?? "Tool '{$toolName}' requires approval";

        $this->requireApproval($message, [
            'tool' => $toolName,
            'arguments' => $arguments,
        ]);
    }

    return parent::beforeToolCall($toolName, $arguments, $context);
}
```

## Events

Listen for HITL events in your application:

```php
// In EventServiceProvider
protected $listen = [
    \Vizra\VizraADK\Events\InterruptRequested::class => [
        NotifyAdminOfPendingApproval::class,
    ],
    \Vizra\VizraADK\Events\InterruptApproved::class => [
        LogApprovalAction::class,
        ResumeAgentExecution::class,
    ],
    \Vizra\VizraADK\Events\InterruptRejected::class => [
        NotifyUserOfRejection::class,
    ],
];
```

### Event Properties

**InterruptRequested:**
- `$context` - AgentContext
- `$agentName` - string
- `$interrupt` - AgentInterrupt model
- `$reason` - string
- `$data` - array

**InterruptApproved:**
- `$interrupt` - AgentInterrupt model
- `$modifications` - array|null
- `$resolvedBy` - string|null

**InterruptRejected:**
- `$interrupt` - AgentInterrupt model
- `$rejectionReason` - string|null
- `$resolvedBy` - string|null

## Complete End-to-End Flow

This section walks through the entire HITL flow from interrupt creation to resumption.

### Step 1: Agent Triggers an Interrupt

When your agent calls `requireApproval()`, it:
1. Creates an `AgentInterrupt` record in the database
2. Throws an `InterruptException` containing the interrupt details

```php
// Inside your agent
$this->requireApproval('Delete this user?', ['user_id' => 123]);
// Execution halts here - InterruptException is thrown
```

### Step 2: Catch the Exception and Get the Interrupt ID

The `InterruptException` contains everything you need:

```php
use Vizra\VizraADK\Exceptions\InterruptException;

try {
    $response = Agent::run('my_agent', $input, $sessionId);
    return response()->json(['status' => 'completed', 'response' => $response]);

} catch (InterruptException $e) {
    // Get the interrupt ID from the exception
    $interruptId = $e->getInterruptId();  // <-- This is where you get the ID!

    // You can also access the full interrupt model
    $interrupt = $e->getInterrupt();

    // Or convert to array for easy JSON response
    $interruptData = $e->toArray();
    // Returns: ['interrupted' => true, 'interrupt_id' => '...', 'reason' => '...', 'data' => [...]]

    // Store the interrupt_id and session_id for later resumption
    // Option 1: Return to frontend (frontend stores it)
    return response()->json([
        'status' => 'awaiting_approval',
        'interrupt_id' => $interruptId,        // Frontend needs this to approve/reject
        'session_id' => $sessionId,            // Frontend needs this to resume
        'original_input' => $input,            // Frontend needs this to resume
        'reason' => $e->getReason(),
        'data' => $e->getData(),
    ], 202);

    // Option 2: Store in your database for async processing
    // PendingTask::create([
    //     'interrupt_id' => $interruptId,
    //     'session_id' => $sessionId,
    //     'original_input' => $input,
    //     'agent_name' => 'my_agent',
    // ]);
}
```

### Step 3: User Approves or Rejects

**Via API:**
```bash
# Approve (using the interrupt_id from step 2)
curl -X POST /api/vizra-adk/interrupts/01HXYZ123ABC/approve \
  -H "Content-Type: application/json" \
  -d '{"user_id": "admin_123"}'

# Or reject
curl -X POST /api/vizra-adk/interrupts/01HXYZ123ABC/reject \
  -H "Content-Type: application/json" \
  -d '{"reason": "Not authorized", "user_id": "admin_123"}'
```

**Via Code:**
```php
use Vizra\VizraADK\Services\InterruptManager;

$manager = app(InterruptManager::class);

// Approve
$manager->approve($interruptId, $modifications, $userId);

// Or reject
$manager->reject($interruptId, 'Reason for rejection', $userId);
```

### Step 4: Resume Agent Execution

After approval, re-run the agent with the **same session ID** and **same input**:

```php
use Vizra\VizraADK\Services\InterruptManager;
use Vizra\VizraADK\Exceptions\InterruptException;

public function resumeAgent(Request $request)
{
    $interruptId = $request->input('interrupt_id');
    $sessionId = $request->input('session_id');
    $originalInput = $request->input('original_input');
    $agentName = $request->input('agent_name', 'my_agent');

    // Check if the interrupt was approved
    $manager = app(InterruptManager::class);
    $status = $manager->checkStatus($interruptId);

    if ($status['status'] === 'rejected') {
        return response()->json([
            'status' => 'rejected',
            'reason' => $status['response'] ?? 'Request was rejected',
        ], 403);
    }

    if ($status['status'] !== 'approved') {
        return response()->json([
            'status' => 'pending',
            'message' => 'Interrupt has not been approved yet',
        ], 400);
    }

    // Re-run the agent - it will continue from where it left off
    try {
        $response = Agent::run($agentName, $originalInput, $sessionId);

        return response()->json([
            'status' => 'completed',
            'response' => $response,
        ]);

    } catch (InterruptException $e) {
        // Agent hit another interrupt point
        return response()->json([
            'status' => 'awaiting_approval',
            'interrupt_id' => $e->getInterruptId(),
            'reason' => $e->getReason(),
        ], 202);
    }
}
```

### Step 5: Agent Checks Approval and Continues

Your agent should check if approval was already granted before requesting again:

```php
class MyAgent extends BaseLlmAgent
{
    public function execute(mixed $input, AgentContext $context): mixed
    {
        // Check if we already have approval for this action
        $approvalKey = 'approved_delete_' . $input['user_id'];

        if (!$context->getState($approvalKey)) {
            // Check for pending/approved interrupts for this session
            $pendingInterrupts = $this->getPendingInterrupts();
            $alreadyApproved = $pendingInterrupts->isEmpty()
                ? false
                : $pendingInterrupts->first()->isApproved();

            if (!$alreadyApproved) {
                $this->requireApproval(
                    'Delete this user?',
                    ['user_id' => $input['user_id']]
                );
            }

            // Mark as approved in context so we don't ask again
            $context->setState($approvalKey, true);
        }

        // Continue with the operation
        return $this->deleteUser($input['user_id']);
    }
}
```

### Alternative: Query Pending Interrupts by Session

If you lose track of the interrupt ID, you can always query by session:

```php
// Get all pending interrupts for a session
$interrupts = AgentInterrupt::forSession($sessionId)->active()->get();

// Or via API
// GET /api/vizra-adk/sessions/{sessionId}/interrupts?pending_only=true

// Or via InterruptManager
$manager = app(InterruptManager::class);
$pending = $manager->getForSession($sessionId, pendingOnly: true);

if ($pending->isNotEmpty()) {
    $interrupt = $pending->first();
    // Now you have the interrupt_id: $interrupt->id
}
```

## Resuming After Approval (Simple Version)

For simple cases where you just need to check and resume:

```php
use Vizra\VizraADK\Services\InterruptManager;

$interruptManager = app(InterruptManager::class);
$status = $interruptManager->checkStatus($interruptId);

if ($status['approved']) {
    // Get any modifications made during approval
    $modifications = $status['modifications'] ?? [];

    // Re-run the agent with the same session
    $response = Agent::run('my_agent', $originalInput, $sessionId);
}
```

## Programmatic Usage

You can also use the InterruptManager directly:

```php
use Vizra\VizraADK\Services\InterruptManager;

$manager = app(InterruptManager::class);

// Get pending interrupts
$pending = $manager->getPending($sessionId, $agentName);

// Approve programmatically
$interrupt = $manager->approve($interruptId, ['modified' => 'value'], $userId);

// Reject programmatically
$interrupt = $manager->reject($interruptId, 'Reason for rejection', $userId);

// Expire overdue interrupts (call from scheduler)
$expiredCount = $manager->expireOverdue();

// Cleanup old resolved interrupts
$deletedCount = $manager->cleanup(30); // Keep 30 days
```

## Scheduled Cleanup

Add to your `app/Console/Kernel.php`:

```php
protected function schedule(Schedule $schedule)
{
    // Expire overdue interrupts every hour
    $schedule->call(function () {
        app(InterruptManager::class)->expireOverdue();
    })->hourly();

    // Cleanup old interrupts daily
    $schedule->call(function () {
        $days = config('vizra-adk.human_in_loop.cleanup_days', 30);
        app(InterruptManager::class)->cleanup($days);
    })->daily();
}
```

## Best Practices

1. **Be specific with reasons** - Clear reasons help humans make informed decisions
2. **Include relevant data** - Provide context about what's being approved
3. **Set appropriate expiration** - Don't let interrupts linger indefinitely
4. **Handle rejections gracefully** - Inform users why their request was denied
5. **Log approvals** - Maintain an audit trail of who approved what
6. **Use appropriate interrupt types** - Match the type to the interaction needed

## Example: Complete Workflow

```php
// 1. Agent requests approval
class OrderAgent extends BaseLlmAgent
{
    public function processRefund(int $orderId, float $amount): void
    {
        if ($amount > 100) {
            $this->requireApproval(
                'Large refund requires manager approval',
                [
                    'order_id' => $orderId,
                    'amount' => $amount,
                    'customer' => $this->getCustomerInfo($orderId),
                ]
            );
        }

        $this->executeRefund($orderId, $amount);
    }
}

// 2. Controller catches interrupt
public function processRefund(Request $request)
{
    try {
        $agent = Agent::get('order_agent');
        $agent->processRefund($request->order_id, $request->amount);

        return response()->json(['status' => 'completed']);
    } catch (InterruptException $e) {
        return response()->json([
            'status' => 'pending_approval',
            'interrupt_id' => $e->getInterruptId(),
            'reason' => $e->getReason(),
            'data' => $e->getData(),
        ], 202);
    }
}

// 3. Manager approves via UI/API
// POST /api/vizra-adk/interrupts/{id}/approve

// 4. System retries the operation
// The agent re-runs, and since the approval exists, proceeds
```
