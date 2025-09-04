# Troubleshooting Vizra ADK

Common issues and solutions when working with Vizra ADK agents.

## Common Errors

### Agent Not Found

**Error**: `Agent 'my_agent' not found`

**Solutions**:
1. Ensure agent class extends `BaseLlmAgent`
2. Check namespace is correct (`App\Agents`)
3. Clear cache: `php artisan cache:clear`
4. Run discovery: `php artisan vizra:agents`
5. Verify agent has unique `$name` property

```php
// Correct
class MyAgent extends BaseLlmAgent
{
    protected string $name = 'my_agent'; // Must be unique
}

// Wrong
class MyAgent extends BaseAgent // Wrong base class
{
    // Missing $name property
}
```

### Tool Execution Failed

**Error**: `Tool execution failed: undefined method`

**Solutions**:
1. Implement both required methods in tool:
```php
class MyTool implements ToolInterface
{
    public function definition(): array { /* ... */ }
    public function execute(array $arguments, AgentContext $context, AgentMemory $memory): string { /* ... */ }
}
```

2. Return JSON-encoded string from execute():
```php
// Correct
return json_encode(['status' => 'success', 'data' => $result]);

// Wrong
return $result; // Not JSON-encoded
```

### Memory Not Persisting

**Error**: Agent doesn't remember previous conversations

**Solutions**:
1. Provide user context:
```php
// Correct
$response = MyAgent::run($input)
    ->forUser($user) // Required for memory
    ->go();

// Wrong
$response = MyAgent::run($input)->go(); // No user context
```

2. Check database migrations:
```bash
php artisan migrate:status
php artisan migrate
```

3. Verify memory tables exist:
- `agent_sessions`
- `agent_messages`
- `agent_memories`

### Token Limit Exceeded

**Error**: `Maximum context length exceeded`

**Solutions**:
1. Limit conversation history in agent:
```php
class EfficientAgent extends BaseLlmAgent
{
    protected string $contextStrategy = 'recent'; // Only recent messages
    
    protected function preprocessMemory($history)
    {
        return array_slice($history, -10); // Last 10 messages
    }
}
```

2. Use smaller models for simple tasks:
```php
protected string $model = 'gpt-4o-mini'; // Instead of gpt-4o
```

3. Reduce max tokens:
```php
protected ?int $maxTokens = 1000; // Limit response length
```

### Streaming Not Working

**Error**: Stream returns as string instead of streaming

**Solutions**:
```php
// Correct
$stream = MyAgent::run($input)
    ->streaming(true) // Enable streaming
    ->go();

foreach ($stream as $chunk) {
    echo $chunk; // Process chunks
}

// Wrong
->stream(function($chunk) { /* ... */ }) // This method doesn't exist
```

### Workflow Execution Errors

**Error**: `Call to undefined method agent()`

**Solutions**:
```php
// Sequential workflow - use then() or start()
Workflow::sequential()
    ->then(FirstAgent::class)  // Correct
    ->then(SecondAgent::class)
    ->run($input);

// Wrong
->agent(FirstAgent::class) // Wrong method for sequential
```

### API Key Issues

**Error**: `Invalid API key` or `Unauthorized`

**Solutions**:
1. Check `.env` file:
```env
OPENAI_API_KEY=sk-...
ANTHROPIC_API_KEY=sk-ant-...
GEMINI_API_KEY=...
```

2. Clear config cache:
```bash
php artisan config:clear
php artisan config:cache
```

3. Verify provider configuration:
```php
protected string $model = 'gpt-4o';
protected ?string $provider = 'openai'; // Explicit provider
```

## Performance Issues

### Slow Response Times

**Solutions**:
1. Use appropriate models:
```php
// Fast, cheaper models for simple tasks
protected string $model = 'gpt-4o-mini';
protected string $model = 'gemini-flash';
```

2. Enable caching:
```php
Cache::remember("agent_response_{$hash}", 3600, function() {
    return $agent->run($input)->go();
});
```

3. Use parallel workflows:
```php
// Process multiple agents simultaneously
Workflow::parallel()
    ->agents($agents)
    ->run($input);
```

### High Token Usage

**Solutions**:
1. Optimize instructions:
```php
// Concise, clear instructions
protected string $instructions = 'You are a helpful assistant. Be concise.';
```

2. Limit tool calls:
```php
protected int $maxSteps = 3; // Limit tool execution steps
```

3. Use token tracking:
```php
$result = Agent::run($input)->go();
Log::info('Response: ' . $result);
```

## Debugging Techniques

### Enable Tracing

View detailed execution traces:
```bash
# Get trace ID from response
$response = Agent::run($input)->go();
$traceId = $response->traceId;

# View trace
php artisan vizra:trace $traceId
```

### Use Dashboard

Interactive debugging:
```bash
php artisan vizra:dashboard
# Visit http://localhost:8000/vizra/dashboard
```

### Check Logs

Laravel logs:
```php
// Add logging to agents
class DebugAgent extends BaseLlmAgent
{
    public function execute($input, AgentContext $context)
    {
        Log::info('Agent executing', [
            'agent' => $this->name,
            'input' => $input,
        ]);
        
        $result = parent::execute($input, $context);
        
        Log::info('Agent completed', [
            'agent' => $this->name,
            'response' => $result,
        ]);
        
        return $result;
    }
}
```

### Test in Isolation

```php
// Test agent directly
$agent = new MyAgent();
$context = new AgentContext();
$result = $agent->execute('test input', $context);

// Test tool directly
$tool = new MyTool();
$context = new AgentContext();
$memory = new AgentMemory($agent);
$result = $tool->execute(['param' => 'value'], $context, $memory);
```

## Database Issues

### Migration Errors

**Solutions**:
```bash
# Reset and re-run migrations
php artisan migrate:rollback
php artisan migrate

# Or fresh migration (WARNING: deletes data)
php artisan migrate:fresh
```

### Check Table Structure
```sql
-- Verify tables exist
SHOW TABLES LIKE 'agent_%';

-- Check table structure
DESCRIBE agent_sessions;
DESCRIBE agent_messages;
```

## Configuration Issues

### Package Not Loading

**Solutions**:
1. Check service provider registration:
```php
// config/app.php (if not auto-discovered)
'providers' => [
    Vizra\VizraADK\Providers\AgentServiceProvider::class,
],
```

2. Clear all caches:
```bash
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear
composer dump-autoload
```

### Route Conflicts

**Solutions**:
```php
// config/vizra-adk.php
'routes' => [
    'prefix' => 'custom-prefix', // Change route prefix
    'middleware' => ['auth'], // Add middleware
],
```

## Memory Issues

### Out of Memory

**Solutions**:
1. Increase PHP memory limit:
```php
// In code
ini_set('memory_limit', '512M');

// Or in php.ini
memory_limit = 512M
```

2. Use cursor for large datasets:
```php
// Use cursor instead of get()
Model::cursor()->each(function($item) {
    // Process item
});
```

## Queue Issues

### Jobs Not Processing

**Solutions**:
1. Start queue worker:
```bash
php artisan queue:work
```

2. Check failed jobs:
```bash
php artisan queue:failed
php artisan queue:retry all
```

3. Use sync driver for debugging:
```env
QUEUE_CONNECTION=sync
```

## Quick Fixes

### Clear Everything
```bash
php artisan cache:clear && \
php artisan config:clear && \
php artisan route:clear && \
php artisan view:clear && \
composer dump-autoload
```

### Reinstall Package
```bash
composer remove vizra/vizra-adk
composer require vizra/vizra-adk
php artisan vizra:install
```

### Check System Requirements
```bash
php -v  # PHP 8.2+
php -m  # Check required extensions
composer show vizra/vizra-adk # Check version
```

## Getting Help

1. **Check Documentation**: https://vizra.ai/docs
2. **GitHub Issues**: https://github.com/vizra/vizra-adk/issues
3. **Run Diagnostics**: `php artisan vizra:diagnose` (if available)
4. **Enable Debug Mode**: Set `APP_DEBUG=true` in `.env`

## Prevention Tips

1. **Always extend correct base class** (`BaseLlmAgent` for agents)
2. **Return JSON from tools** (use `json_encode()`)
3. **Provide user context** for memory persistence
4. **Set appropriate models** for task complexity
5. **Monitor token usage** to control costs
6. **Test incrementally** with simple cases first
7. **Use type hints** for better IDE support
8. **Keep instructions concise** to save tokens
9. **Cache expensive operations** when possible
10. **Run tests regularly** with evaluation framework