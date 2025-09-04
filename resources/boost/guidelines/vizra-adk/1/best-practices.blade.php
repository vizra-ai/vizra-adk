# Vizra ADK Best Practices

Follow these best practices to build robust, maintainable, and efficient AI agent systems.

## Naming Conventions

### Agents
```php
// Good: Descriptive, ends with 'Agent'
CustomerSupportAgent
DataAnalysisAgent
ContentGeneratorAgent

// Bad: Vague or missing suffix
Support
Analyzer
GenContent
```

### Tools
```php
// Good: Action-oriented, ends with 'Tool'
DatabaseQueryTool
EmailSenderTool
FileUploaderTool

// Bad: Noun-only or missing suffix
Database
EmailService
Upload
```

### Agent Names (Internal)
```php
// Good: snake_case, descriptive
protected string $name = 'customer_support';
protected string $name = 'data_analyzer';

// Bad: camelCase or spaces
protected string $name = 'customerSupport';
protected string $name = 'Customer Support';
```

## Project Structure

```
app/
├── Agents/
│   ├── Support/
│   │   ├── CustomerSupportAgent.php
│   │   └── TechnicalSupportAgent.php
│   ├── Analytics/
│   │   ├── DataAnalysisAgent.php
│   │   └── ReportGeneratorAgent.php
│   └── BaseAgents/
│       └── CustomBaseAgent.php
├── Tools/
│   ├── Database/
│   │   └── QueryTool.php
│   ├── Communication/
│   │   ├── EmailTool.php
│   │   └── SlackTool.php
│   └── BaseTool.php
└── Workflows/
    ├── CustomerServiceWorkflow.php
    └── DataPipelineWorkflow.php
```

## Agent Design

### Single Responsibility
```php
// Good: Focused agent
class InvoiceGeneratorAgent extends BaseLlmAgent
{
    protected string $description = 'Generates invoices from order data';
    // Only handles invoice generation
}

// Bad: Doing too much
class EverythingAgent extends BaseLlmAgent
{
    protected string $description = 'Handles orders, invoices, emails, and reports';
    // Too many responsibilities
}
```

### Clear Instructions
```php
// Good: Specific, structured instructions
protected string $instructions = <<<'INSTRUCTIONS'
    You are a technical documentation writer.
    
    Your responsibilities:
    - Write clear, concise documentation
    - Include code examples
    - Follow company style guide
    
    Guidelines:
    - Use active voice
    - Avoid jargon
    - Include prerequisites
    - Provide step-by-step instructions
    INSTRUCTIONS;

// Bad: Vague instructions
protected string $instructions = 'You help with documentation.';
```

## Tool Implementation

### Error Handling
```php
public function execute(array $arguments, AgentContext $context, AgentMemory $memory): string
{
    try {
        // Validate inputs first
        if (empty($arguments['required_field'])) {
            throw new \InvalidArgumentException('required_field is missing');
        }
        
        // Perform operation
        $result = $this->performOperation($arguments);
        
        return json_encode([
            'status' => 'success',
            'data' => $result
        ]);
        
    } catch (\InvalidArgumentException $e) {
        return json_encode([
            'status' => 'error',
            'error_type' => 'validation',
            'message' => $e->getMessage()
        ]);
    } catch (\Exception $e) {
        Log::error('Tool execution failed', [
            'tool' => static::class,
            'error' => $e->getMessage()
        ]);
        
        return json_encode([
            'status' => 'error',
            'error_type' => 'execution',
            'message' => 'Operation failed: ' . $e->getMessage()
        ]);
    }
}
```

### Input Validation
```php
public function execute(array $arguments, AgentContext $context, AgentMemory $memory): string
{
    // Validate and sanitize inputs
    $validated = validator($arguments, [
        'email' => 'required|email',
        'amount' => 'required|numeric|min:0|max:10000',
        'date' => 'required|date|after:today'
    ])->validate();
    
    // Use validated data
    return $this->process($validated);
}
```

## Performance Optimization

### Model Selection
```php
// Use appropriate models for the task
class SimpleTaskAgent extends BaseLlmAgent
{
    protected string $model = 'gpt-4o-mini'; // Cheaper, faster for simple tasks
}

class ComplexReasoningAgent extends BaseLlmAgent
{
    protected string $model = 'gpt-4o'; // More capable for complex tasks
}
```

### Caching
```php
class CachedAgent extends BaseLlmAgent
{
    public function execute($input)
    {
        $cacheKey = 'agent_' . $this->name . '_' . md5($input);
        
        return Cache::remember($cacheKey, 3600, function() use ($input) {
            return parent::execute($input);
        });
    }
}
```

### Streaming for Long Responses
```php
// Enable streaming for better UX
$stream = $agent->run($input)
    ->streaming(true)
    ->go();

// Iterate over stream chunks
foreach ($stream as $chunk) {
    echo $chunk; // Send to frontend immediately
    ob_flush();
    flush();
}
```

## Security

### Never Expose Secrets
```php
// Good: Use config/env
protected function getApiKey()
{
    return config('services.external_api.key');
}

// Bad: Hardcoded secrets
protected $apiKey = 'sk-abc123...'; // NEVER DO THIS
```

### Input Sanitization
```php
public function execute(array $arguments, AgentContext $context, AgentMemory $memory): string
{
    // Sanitize file paths
    $path = str_replace(['..', '/', '\\'], '', $arguments['filename']);
    $safePath = storage_path('app/user-files/' . $path);
    
    // Validate allowed operations
    if (!in_array($arguments['operation'], ['read', 'write', 'list'])) {
        return json_encode(['error' => 'Invalid operation']);
    }
}
```

### Permission Checks
```php
public function execute(array $arguments, AgentContext $context, AgentMemory $memory): string
{
    $user = $context->getUser();
    
    if (!$user->can('perform-sensitive-action')) {
        return json_encode([
            'status' => 'error',
            'message' => 'Unauthorized'
        ]);
    }
    
    // Proceed with operation
}
```

## Testing

### Unit Tests for Agents
```php
class AgentTest extends TestCase
{
    public function test_agent_responds_appropriately()
    {
        $agent = new CustomerSupportAgent();
        $response = $agent->run('I need help with my order')
            ->forUser($this->user)
            ->go();
        
        $this->assertNotEmpty($response);
        $this->assertIsString($response);
    }
}
```

### Tool Testing
```php
class ToolTest extends TestCase
{
    public function test_tool_handles_errors_gracefully()
    {
        $tool = new DatabaseQueryTool();
        $result = $tool->execute(
            ['invalid' => 'params'],
            new AgentContext(),
            new AgentMemory()
        );
        
        $decoded = json_decode($result, true);
        $this->assertEquals('error', $decoded['status']);
    }
}
```

## Monitoring and Debugging

### Use Traces
```php
// Enable tracing for debugging
$response = MyAgent::run($input)
    ->withTracing(true)
    ->go();

// Review trace
php artisan vizra:trace $response->traceId
```

### Logging
```php
class LoggingAgent extends BaseLlmAgent
{
    public function execute($input)
    {
        Log::info('Agent execution started', [
            'agent' => $this->name,
            'input_length' => strlen($input)
        ]);
        
        $result = parent::execute($input);
        
        Log::info('Agent execution completed', [
            'agent' => $this->name,
            'response' => $result
        ]);
        
        return $result;
    }
}
```

## Memory Management

### Limit History Size
```php
// Prevent token limit issues in agent class
class EfficientAgent extends BaseLlmAgent
{
    protected bool $includeHistory = true;
    protected string $contextStrategy = 'recent'; // Only recent messages
    
    // Or override preprocessing
    protected function preprocessMemory($history)
    {
        // Keep only last 20 exchanges
        return array_slice($history, -20);
    }
}
```

### Summarize Long Conversations
```php
class MemoryEfficientAgent extends BaseLlmAgent
{
    protected function prepareMemory($history)
    {
        if (count($history) > 30) {
            // Summarize older messages
            $summary = $this->summarizeHistory(array_slice($history, 0, -10));
            return array_merge([$summary], array_slice($history, -10));
        }
        return $history;
    }
}
```

## Common Pitfalls to Avoid

1. **Token Limit Exceeded**: Always consider model token limits
2. **Infinite Delegation Loops**: Set max delegation depth
3. **Uncaught Exceptions**: Always wrap tool logic in try-catch
4. **Memory Leaks**: Clean up old sessions and traces
5. **Hardcoded Values**: Use configuration files
6. **Missing Validation**: Always validate user inputs
7. **Poor Error Messages**: Provide helpful error information
8. **Blocking Operations**: Use queues for long-running tasks

## Production Checklist

- [ ] All agents have clear, specific instructions
- [ ] Tools include comprehensive error handling
- [ ] Sensitive operations have permission checks
- [ ] API keys are in environment variables
- [ ] Agents are tested with unit tests
- [ ] Memory limits are configured appropriately
- [ ] Logging is implemented for debugging
- [ ] Performance bottlenecks are identified
- [ ] Documentation is complete and current
- [ ] Security audit has been performed