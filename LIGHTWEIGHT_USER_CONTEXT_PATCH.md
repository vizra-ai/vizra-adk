# Lightweight User Context Patch

## Problem
When using `forUser($user)`, the entire User model (including all loaded relationships like roles, settings, teams, etc.) was being serialized into the agent context via `toArray()`. This caused:
- Large context payloads in session storage
- Slow serialization/deserialization
- Unnecessary data exposure in logs and traces
- Potential N+1 query issues when relationships are loaded

## Solution
Added backward-compatible support for lightweight user context with three prioritized approaches:

### Priority 1: Explicit Lightweight Context (New Method)
Use the new `withUserContext()` method to pass minimal user data:

```php
$userContext = [
    'user_id' => $user->id,
    'user_name' => $user->name,
    'user_email' => $user->email,
];

Agent::named('my-agent')
    ->run('Hello')
    ->forUser($user)  // Still needed for user_id extraction
    ->withUserContext($userContext)  // Overrides toArray() serialization
    ->go();
```

### Priority 2: Custom Model Method (Recommended Pattern)
Implement `toAgentContext()` on your User model:

```php
class User extends Authenticatable
{
    /**
     * Get lightweight context for agent execution
     */
    public function toAgentContext(): array
    {
        return [
            'user_id' => $this->id,
            'user_name' => $this->name,
            'user_email' => $this->email,
        ];
    }
}

// Now forUser() automatically uses the lightweight version
Agent::named('my-agent')
    ->run('Hello')
    ->forUser($user)  // Automatically calls toAgentContext()
    ->go();
```

### Priority 3: Full Model Serialization (Backward Compatible)
If neither `withUserContext()` nor `toAgentContext()` is used, falls back to `toArray()`:

```php
// Existing code continues to work unchanged
Agent::named('my-agent')
    ->run('Hello')
    ->forUser($user)  // Uses $user->toArray()
    ->go();
```

## Changes Made

### In `src/Execution/AgentExecutor.php`:

1. **Added new property** (line 50):
   ```php
   protected ?array $userContext = null;
   ```

2. **Added new method** (lines 68-88):
   ```php
   public function withUserContext(array $userContext): self
   ```

3. **Updated user serialization logic** (lines 317-366):
   - Check for explicit `$userContext` first
   - Check for `toAgentContext()` method second
   - Fall back to `toArray()` for backward compatibility
   - Properly extract `user_id`, `user_email`, and `user_name` from any source

## Migration Guide for Existing Projects

### Option A: Use EntityContextService (Laravel Apps)
```php
// Create a centralized service for lightweight user context
class EntityContextService
{
    public function getUserContext(User $user): array
    {
        return [
            'user_id' => $user->id,
            'user_name' => $user->name,
            'user_email' => $user->email,
        ];
    }
}

// In your controller
$contextService = app(EntityContextService::class);
$userContext = $contextService->getUserContext($user);

Agent::named('my-agent')
    ->run($input)
    ->forUser($user)
    ->withUserContext($userContext)
    ->go();
```

### Option B: Implement toAgentContext() on User Model
```php
// In app/Models/User.php
public function toAgentContext(): array
{
    return [
        'user_id' => $this->id,
        'user_name' => $this->name,
        'user_email' => $this->email,
    ];
}

// No changes needed in controllers - automatic!
```

### Option C: Do Nothing (Backward Compatible)
Existing code continues to work with full model serialization.

## Testing

Test that user data is stored correctly:

```php
$userContext = ['user_id' => 1, 'user_name' => 'Test', 'user_email' => 'test@example.com'];

$response = Agent::named('test-agent')
    ->run('Hello')
    ->withUserContext($userContext)
    ->withSession('test-session')
    ->go();

// Check logs/traces to verify only lightweight data is stored
```

## Benefits

- **Reduced Memory**: Only essential user fields stored in context
- **Faster Serialization**: No relationship trees to serialize
- **Better Logs**: Cleaner, more readable traces and logs
- **Backward Compatible**: Existing code works unchanged
- **Flexible**: Three approaches to fit different needs

## Commit Message

```
feat: Add lightweight user context support to AgentExecutor

- Add withUserContext() method for explicit lightweight user data
- Support toAgentContext() method on User models
- Maintain backward compatibility with toArray() fallback
- Prevents large relationship trees from being serialized into agent context
- Reduces memory footprint and improves performance

BREAKING CHANGE: None - fully backward compatible
```
