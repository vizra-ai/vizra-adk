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

## Resuming After Approval

After an interrupt is approved, you need to re-run the agent:

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
