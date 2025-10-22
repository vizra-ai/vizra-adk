# Lightweight User Context

## Overview

The `withUserContext()` method allows you to pass minimal user data to agents without serializing the entire User model with all its relationships. This prevents exposure of sensitive data and reduces context size for better LLM performance.

## The Problem

Previously, when using `->forUser($user)`, the entire User model was serialized via `toArray()`, which included:

- **All loaded relationships**: roles, settings, teams, workspace, addons
- **Sensitive data**: credits, onboarding status, theme preferences, subdomains
- **Large relationship trees**: that bloat the agent context and expose unnecessary information

**Example of what the LLM could see:**
```json
{
  "id": 1,
  "name": "Bowe Frankema",
  "email": "bowe@wefoster.co",
  "credits": 1000,
  "onboarding_status": "completed",
  "theme_preference": "dark",
  "subdomain": "acme",
  "settings": {
    "notifications": true,
    "auto_backup": true
  },
  "currentTeam": {
    "id": 5,
    "name": "Acme Corp",
    "plan": "enterprise"
  },
  "workspace": {...},
  "addons": [...]
}
```

## The Solution: Three-Tier Priority System

Vizra ADK now supports three ways to control user context, with a clear priority order:

### Priority 1: Explicit Lightweight Context (Recommended)

Use `withUserContext()` to pass only the data you want the agent to have access to.

```php
use App\Features\Ai\Services\EntityContextService;

// Create lightweight context with only essential fields
$contextService = app(EntityContextService::class);
$userContext = $contextService->getUserContext($user);

// Pass to agent
Agent::named('support')
    ->run('Help me with my account')
    ->forUser($user)  // Still needed for session association
    ->withUserContext($userContext)  // Override with minimal data
    ->go();
```

**What the LLM sees:**
```json
{
  "id": 1,
  "name": "Bowe Frankema",
  "email": "bowe@wefoster.co"
}
```

### Priority 2: Model Convention (Optional)

Define a `toAgentContext()` method on your User model to automatically return lightweight context.

```php
// In app/Models/User.php
class User extends Authenticatable
{
    /**
     * Get lightweight context for agent interactions
     */
    public function toAgentContext(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'role' => $this->role,  // Only include what agents need
        ];
    }
}
```

**Usage (automatically uses toAgentContext if it exists):**
```php
Agent::named('support')
    ->run('Help me with my account')
    ->forUser($user)
    ->go();
```

### Priority 3: Full Serialization (Backward Compatible)

If neither `withUserContext()` nor `toAgentContext()` is used, the system falls back to `toArray()` for full backward compatibility.

```php
// Existing code continues to work
Agent::named('support')
    ->run('Help me with my account')
    ->forUser($user)
    ->go();
```

## Complete Examples

### Example 1: Livewire Chat Component

```php
namespace App\Features\Agents\Components\Livewire;

use Livewire\Component;
use Vizra\VizraADK\Facades\Agent;
use App\Features\Ai\Services\EntityContextService;

class AgentChat extends Component
{
    public function sendMessage($message)
    {
        $user = auth()->user();

        // Use EntityContextService for lightweight user context
        $contextService = app(EntityContextService::class);
        $userContext = $contextService->getUserContext($user);

        // Execute agent with lightweight context
        $stream = Agent::named('dollie')
            ->run($message)
            ->forUser($user)  // For session association
            ->withUserContext($userContext)  // Override with lightweight data
            ->withSession($this->sessionId)
            ->streaming()
            ->go();

        // Handle streaming response...
    }
}
```

### Example 2: HTTP Controller

```php
namespace App\Features\Agents\Presentation\Controllers;

use Illuminate\Http\Request;
use Vizra\VizraADK\Facades\Agent;
use App\Features\Ai\Services\EntityContextService;

class AgentController extends Controller
{
    public function respond(Request $request)
    {
        $user = auth()->user();

        // Create lightweight context
        $contextService = app(EntityContextService::class);
        $userContext = $contextService->getUserContext($user);

        // Execute agent
        $response = Agent::named($request->agent)
            ->run($request->message)
            ->forUser($user)
            ->withUserContext($userContext)
            ->withSession($request->session_id)
            ->go();

        return response()->json(['response' => $response]);
    }
}
```

### Example 3: Background Job

```php
namespace App\Features\Agents\Infrastructure\Laravel\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Vizra\VizraADK\Facades\Agent;
use App\Features\Ai\Services\EntityContextService;

class RunAgentJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        protected string $agentSlug,
        protected string $message,
        protected int $userId,
        protected string $sessionId
    ) {}

    public function handle()
    {
        $user = User::find($this->userId);

        // Create lightweight context
        $contextService = app(EntityContextService::class);
        $userContext = $contextService->getUserContext($user);

        // Execute agent
        $response = Agent::named($this->agentSlug)
            ->run($this->message)
            ->forUser($user)
            ->withUserContext($userContext)
            ->withSession($this->sessionId)
            ->go();

        // Handle response...
    }
}
```

### Example 4: Custom User Context

You can customize what data to include based on the agent's needs:

```php
// For a billing agent - include payment information
$userContext = [
    'id' => $user->id,
    'name' => $user->name,
    'email' => $user->email,
    'subscription_plan' => $user->currentTeam->subscriptionPlan->name,
    'billing_email' => $user->billing_email,
];

Agent::named('billing-support')
    ->run('Check my invoice')
    ->forUser($user)
    ->withUserContext($userContext)
    ->go();
```

```php
// For a technical support agent - include technical details
$userContext = [
    'id' => $user->id,
    'name' => $user->name,
    'email' => $user->email,
    'account_type' => $user->account_type,
    'sites_count' => $user->containers()->count(),
];

Agent::named('technical-support')
    ->run('Help with site migration')
    ->forUser($user)
    ->withUserContext($userContext)
    ->go();
```

## EntityContextService

The `EntityContextService` provides centralized methods for generating lightweight context for different entities.

### Available Methods

```php
use App\Features\Ai\Services\EntityContextService;

$contextService = app(EntityContextService::class);

// User context (id, name, email)
$userContext = $contextService->getUserContext($user);

// Client context (with markdown formatting)
$clientContext = $contextService->getClientContext($client, [
    'format' => 'standard',  // or 'compact'
    'with_meta' => false,
]);

// Site context (with markdown formatting)
$siteContext = $contextService->getSiteContext($site, [
    'format' => 'standard',  // or 'compact'
    'with_meta' => false,
]);

// Team context (comprehensive team data)
$teamContext = $contextService->getTeamContext($team, $user);
```

### User Context Structure

```php
[
    'id' => 1,
    'name' => 'Bowe Frankema',
    'email' => 'bowe@wefoster.co',
]
```

## Benefits

### 1. Privacy & Security
- Prevents accidental exposure of sensitive user data to LLMs
- Only essential information is shared with agents
- Fine-grained control over what data each agent can access

### 2. Performance
- Smaller context size improves LLM response quality
- Reduces token usage and costs
- Faster processing with minimal context

### 3. Flexibility
- Three approaches to fit different needs
- Easy to customize per agent type
- Centralized via EntityContextService

### 4. Backward Compatible
- Existing implementations continue working
- No breaking changes
- Gradual migration path

## Migration Guide

### Step 1: Create EntityContextService (if not exists)

```php
namespace App\Features\Ai\Services;

use App\Models\User;

class EntityContextService
{
    public function getUserContext(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
        ];
    }
}
```

### Step 2: Update Agent Execution Points

Find all places where you execute agents:

```bash
# Search for agent execution patterns
grep -r "->forUser(" app/
grep -r "Agent::named" app/
grep -r "Agent::run" app/
```

### Step 3: Add withUserContext() Calls

**Before:**
```php
Agent::named('support')
    ->run($message)
    ->forUser($user)
    ->go();
```

**After:**
```php
$contextService = app(EntityContextService::class);
$userContext = $contextService->getUserContext($user);

Agent::named('support')
    ->run($message)
    ->forUser($user)
    ->withUserContext($userContext)
    ->go();
```

### Step 4: Test Your Agents

Verify that agents still have access to the information they need:

```php
// Test that agent knows user's name
$response = Agent::named('support')
    ->run('What is my name?')
    ->forUser($user)
    ->withUserContext($userContext)
    ->go();

// Should respond with: "Your name is [user's name]"
```

## Key Implementation Details

### Session Association

The `withUserContext()` method ensures sessions are properly associated with users by extracting the user_id BEFORE creating the session:

```php
// Inside AgentExecutor.php
$userId = null;
if ($this->userContext !== null) {
    // Extract from explicit userContext
    $userId = $this->userContext['user_id'] ?? $this->userContext['id'] ?? null;
} elseif ($this->user) {
    // Extract from User model
    $userId = $this->user->getKey();
}

// Create session with correct user_id
$agentContext = $stateManager->loadContext($agentName, $sessionId, $this->input, $userId);
```

### Key Naming Convention

The system checks for both `user_id` and `id` keys in the context array:

```php
// Both formats are supported:
$userContext = ['id' => 1, 'name' => 'John', 'email' => 'john@example.com'];
$userContext = ['user_id' => 1, 'user_name' => 'John', 'user_email' => 'john@example.com'];
```

However, for consistency with EntityContextService, prefer the unprefixed format:
```php
['id' => ..., 'name' => ..., 'email' => ...]
```

## Troubleshooting

### Agent doesn't know user's name

**Problem:** Agent responds with "I don't know your name"

**Solution:** Ensure you're passing `withUserContext()` with the correct keys:

```php
$userContext = [
    'id' => $user->id,     // Not 'user_id'
    'name' => $user->name, // Not 'user_name'
    'email' => $user->email, // Not 'user_email'
];
```

### Sessions not linked to user

**Problem:** Sessions are created without user_id

**Solution:** Ensure you're still calling `->forUser($user)` even when using `withUserContext()`:

```php
Agent::named('support')
    ->run($message)
    ->forUser($user)  // ← Still needed for session association
    ->withUserContext($userContext)
    ->go();
```

### Agent still has access to sensitive data

**Problem:** Agent knows about credits, settings, etc.

**Solution:** Check that you're using `withUserContext()` at ALL execution points:

```bash
# Find all execution points
grep -r "->forUser(" app/Features/Agents/

# Ensure each one has withUserContext()
```

## API Reference

### AgentExecutor::withUserContext()

```php
/**
 * Set lightweight user context (takes precedence over user model serialization)
 *
 * @param  array  $userContext  Associative array with user data
 * @return self
 */
public function withUserContext(array $userContext): self
```

**Parameters:**
- `$userContext` - Array with user data. Expected keys: `id`, `name`, `email`

**Returns:** `self` for method chaining

**Example:**
```php
$executor = Agent::named('support')
    ->run($message)
    ->withUserContext([
        'id' => 1,
        'name' => 'John Doe',
        'email' => 'john@example.com',
    ]);
```

### EntityContextService::getUserContext()

```php
/**
 * Generate user context with essential information only
 *
 * @param  User  $user  The user model
 * @return array Associative array with basic user info
 */
public function getUserContext(User $user): array
```

**Parameters:**
- `$user` - The User model instance

**Returns:** Array with keys: `id`, `name`, `email`

**Example:**
```php
$contextService = app(EntityContextService::class);
$userContext = $contextService->getUserContext(auth()->user());
```

## Best Practices

### 1. Use EntityContextService
Always use the centralized service instead of manually creating arrays:

✅ **Good:**
```php
$contextService = app(EntityContextService::class);
$userContext = $contextService->getUserContext($user);
```

❌ **Avoid:**
```php
$userContext = ['id' => $user->id, 'name' => $user->name, 'email' => $user->email];
```

### 2. Keep Context Minimal
Only include data the agent actually needs:

✅ **Good:**
```php
// Support agent only needs identity
['id' => $user->id, 'name' => $user->name, 'email' => $user->email]
```

❌ **Avoid:**
```php
// Don't include unnecessary data
['id' => $user->id, 'name' => $user->name, 'email' => $user->email,
 'credits' => $user->credits, 'settings' => $user->settings, ...]
```

### 3. Customize Per Agent Type
Different agents may need different context:

```php
// Billing agent
$billingContext = [
    'id' => $user->id,
    'name' => $user->name,
    'email' => $user->email,
    'subscription_plan' => $user->subscription_plan,
];

// Technical support agent
$technicalContext = [
    'id' => $user->id,
    'name' => $user->name,
    'email' => $user->email,
    'sites_count' => $user->containers()->count(),
];
```

### 4. Always Call forUser()
Even when using `withUserContext()`, still call `forUser()` for session association:

✅ **Good:**
```php
Agent::named('support')
    ->forUser($user)
    ->withUserContext($userContext)
    ->go();
```

❌ **Avoid:**
```php
Agent::named('support')
    ->withUserContext($userContext)  // Missing forUser()
    ->go();
```

## Related Documentation

- [CLAUDE.md](../CLAUDE.md) - Vizra ADK overview
- [AgentExecutor](../src/Execution/AgentExecutor.php) - Source implementation
- [EntityContextService](../../api-layer/app/Features/Ai/Services/EntityContextService.php) - Context service

## Support

For issues or questions:
- GitHub Issues: https://github.com/BoweFrankema/vizra-adk/issues
- Documentation: https://docs.vizra.dev
