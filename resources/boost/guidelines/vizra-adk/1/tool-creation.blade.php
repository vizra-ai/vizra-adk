# Creating Vizra ADK Tools

Tools extend agent capabilities by allowing them to interact with databases, APIs, external services, and perform specific actions. All tools must implement the `ToolInterface`.

## Tool Class Structure

Every tool MUST follow this structure:

```php
<?php

namespace App\Tools;

use Vizra\VizraADK\Contracts\ToolInterface;
use Vizra\VizraADK\Memory\AgentMemory;
use Vizra\VizraADK\System\AgentContext;

class {{ ToolName }}Tool implements ToolInterface
{
    /**
     * Define the tool's schema for the LLM
     */
    public function definition(): array
    {
        return [
            'name' => '{{ snake_case(ToolName) }}',
            'description' => '{{ Clear description of what this tool does }}',
            'parameters' => [
                'type' => 'object',
                'properties' => [
                    '{{ parameter_name }}' => [
                        'type' => '{{ string|number|boolean|array|object }}',
                        'description' => '{{ What this parameter is for }}',
                        // Optional fields:
                        'enum' => ['option1', 'option2'], // For restricted values
                        'default' => 'default_value',     // Default if not provided
                        'items' => [                      // For array types
                            'type' => 'string'
                        ],
                    ],
                ],
                'required' => ['{{ required_param }}'], // List required parameters
            ],
        ];
    }

    /**
     * Execute the tool with given arguments
     * 
     * @param array $arguments The parameters passed by the LLM
     * @param AgentContext $context Current execution context
     * @param AgentMemory $memory Agent's memory instance
     * @return string JSON-encoded result
     */
    public function execute(array $arguments, AgentContext $context, AgentMemory $memory): string
    {
        // Extract arguments
        $param = $arguments['{{ parameter_name }}'] ?? null;
        
        // Perform the tool's action
        try {
            // Your logic here
            $result = $this->performAction($param);
            
            return json_encode([
                'status' => 'success',
                'data' => $result,
            ]);
        } catch (\Exception $e) {
            return json_encode([
                'status' => 'error',
                'message' => $e->getMessage(),
            ]);
        }
    }
    
    private function performAction($param)
    {
        // Implementation details
    }
}
```

## Key Rules

1. **Interface Implementation**:
   - MUST implement `ToolInterface`
   - MUST have both `definition()` and `execute()` methods

2. **Naming Convention**:
   - Class name should end with `Tool`
   - Tool name in definition should be snake_case
   - Place tools in `App\Tools` namespace

3. **Return Format**:
   - ALWAYS return JSON-encoded strings
   - Include status indicators (success/error)
   - Provide clear error messages

4. **Parameter Schema**:
   - Use JSON Schema format
   - Be explicit about types
   - Include helpful descriptions
   - Mark required parameters

## Common Tool Patterns

### Database Query Tool
```php
class DatabaseQueryTool implements ToolInterface
{
    public function definition(): array
    {
        return [
            'name' => 'database_query',
            'description' => 'Execute database queries to retrieve information',
            'parameters' => [
                'type' => 'object',
                'properties' => [
                    'table' => [
                        'type' => 'string',
                        'description' => 'The database table to query',
                        'enum' => ['users', 'orders', 'products'], // Restrict to safe tables
                    ],
                    'conditions' => [
                        'type' => 'array',
                        'description' => 'Query conditions',
                        'items' => [
                            'type' => 'object',
                            'properties' => [
                                'field' => ['type' => 'string'],
                                'operator' => ['type' => 'string', 'enum' => ['=', '>', '<', 'like']],
                                'value' => ['type' => 'string'],
                            ],
                        ],
                    ],
                    'limit' => [
                        'type' => 'number',
                        'description' => 'Maximum number of results',
                        'default' => 10,
                    ],
                ],
                'required' => ['table'],
            ],
        ];
    }

    public function execute(array $arguments, AgentContext $context, AgentMemory $memory): string
    {
        $table = $arguments['table'];
        $conditions = $arguments['conditions'] ?? [];
        $limit = $arguments['limit'] ?? 10;

        try {
            $query = DB::table($table);
            
            foreach ($conditions as $condition) {
                $query->where(
                    $condition['field'],
                    $condition['operator'],
                    $condition['value']
                );
            }
            
            $results = $query->limit($limit)->get();
            
            return json_encode([
                'status' => 'success',
                'count' => $results->count(),
                'data' => $results->toArray(),
            ]);
        } catch (\Exception $e) {
            return json_encode([
                'status' => 'error',
                'message' => 'Query failed: ' . $e->getMessage(),
            ]);
        }
    }
}
```

### API Integration Tool
```php
class WeatherApiTool implements ToolInterface
{
    public function definition(): array
    {
        return [
            'name' => 'weather_lookup',
            'description' => 'Get current weather information for a location',
            'parameters' => [
                'type' => 'object',
                'properties' => [
                    'location' => [
                        'type' => 'string',
                        'description' => 'City name or coordinates',
                    ],
                    'units' => [
                        'type' => 'string',
                        'description' => 'Temperature units',
                        'enum' => ['celsius', 'fahrenheit'],
                        'default' => 'celsius',
                    ],
                ],
                'required' => ['location'],
            ],
        ];
    }

    public function execute(array $arguments, AgentContext $context, AgentMemory $memory): string
    {
        $location = $arguments['location'];
        $units = $arguments['units'] ?? 'celsius';

        try {
            $response = Http::get('https://api.weather.com/v1/current', [
                'location' => $location,
                'units' => $units,
                'api_key' => config('services.weather.key'),
            ]);

            if ($response->successful()) {
                return json_encode([
                    'status' => 'success',
                    'weather' => $response->json(),
                ]);
            }

            return json_encode([
                'status' => 'error',
                'message' => 'Weather API returned an error',
            ]);
        } catch (\Exception $e) {
            return json_encode([
                'status' => 'error',
                'message' => 'Failed to fetch weather: ' . $e->getMessage(),
            ]);
        }
    }
}
```

### File Operation Tool
```php
class FileManagerTool implements ToolInterface
{
    public function definition(): array
    {
        return [
            'name' => 'file_manager',
            'description' => 'Read, write, or list files in the storage',
            'parameters' => [
                'type' => 'object',
                'properties' => [
                    'operation' => [
                        'type' => 'string',
                        'enum' => ['read', 'write', 'list', 'delete'],
                        'description' => 'The file operation to perform',
                    ],
                    'path' => [
                        'type' => 'string',
                        'description' => 'File or directory path',
                    ],
                    'content' => [
                        'type' => 'string',
                        'description' => 'Content to write (for write operation)',
                    ],
                ],
                'required' => ['operation', 'path'],
            ],
        ];
    }

    public function execute(array $arguments, AgentContext $context, AgentMemory $memory): string
    {
        $operation = $arguments['operation'];
        $path = $arguments['path'];
        
        // Sanitize path to prevent directory traversal
        $path = str_replace(['..', '//'], '', $path);
        $fullPath = storage_path('app/agent-files/' . $path);

        try {
            switch ($operation) {
                case 'read':
                    if (!file_exists($fullPath)) {
                        throw new \Exception('File not found');
                    }
                    return json_encode([
                        'status' => 'success',
                        'content' => file_get_contents($fullPath),
                    ]);

                case 'write':
                    $content = $arguments['content'] ?? '';
                    File::ensureDirectoryExists(dirname($fullPath));
                    file_put_contents($fullPath, $content);
                    return json_encode([
                        'status' => 'success',
                        'message' => 'File written successfully',
                    ]);

                case 'list':
                    $files = File::files($fullPath);
                    return json_encode([
                        'status' => 'success',
                        'files' => array_map('basename', $files),
                    ]);

                case 'delete':
                    if (file_exists($fullPath)) {
                        unlink($fullPath);
                    }
                    return json_encode([
                        'status' => 'success',
                        'message' => 'File deleted',
                    ]);

                default:
                    throw new \Exception('Invalid operation');
            }
        } catch (\Exception $e) {
            return json_encode([
                'status' => 'error',
                'message' => $e->getMessage(),
            ]);
        }
    }
}
```

### Email Sending Tool
```php
class EmailSenderTool implements ToolInterface
{
    public function definition(): array
    {
        return [
            'name' => 'send_email',
            'description' => 'Send an email to specified recipients',
            'parameters' => [
                'type' => 'object',
                'properties' => [
                    'to' => [
                        'type' => 'string',
                        'description' => 'Recipient email address',
                    ],
                    'subject' => [
                        'type' => 'string',
                        'description' => 'Email subject line',
                    ],
                    'body' => [
                        'type' => 'string',
                        'description' => 'Email body content (HTML supported)',
                    ],
                    'cc' => [
                        'type' => 'array',
                        'description' => 'CC recipients',
                        'items' => ['type' => 'string'],
                    ],
                ],
                'required' => ['to', 'subject', 'body'],
            ],
        ];
    }

    public function execute(array $arguments, AgentContext $context, AgentMemory $memory): string
    {
        try {
            $mail = Mail::raw($arguments['body'], function ($message) use ($arguments) {
                $message->to($arguments['to'])
                        ->subject($arguments['subject']);
                
                if (isset($arguments['cc'])) {
                    $message->cc($arguments['cc']);
                }
            });

            return json_encode([
                'status' => 'success',
                'message' => 'Email sent successfully',
            ]);
        } catch (\Exception $e) {
            return json_encode([
                'status' => 'error',
                'message' => 'Failed to send email: ' . $e->getMessage(),
            ]);
        }
    }
}
```

## Using Context and Memory

Tools receive `AgentContext` and `AgentMemory` for accessing execution context:

```php
public function execute(array $arguments, AgentContext $context, AgentMemory $memory): string
{
    // Access current user
    $user = $context->getUser();
    
    // Access session ID
    $sessionId = $context->getSessionId();
    
    // Access custom parameters
    $customParam = $context->getParameter('custom_key');
    
    // Store something in memory
    $memory->remember('last_action', 'Sent email to customer');
    
    // Retrieve from memory
    $lastAction = $memory->recall('last_action');
    
    // Access conversation history
    $history = $memory->getConversationHistory();
    
    return json_encode(['status' => 'success']);
}
```

## Advanced Tool Patterns

### Multi-Step Tool with Progress
```php
class DataProcessorTool implements ToolInterface
{
    public function definition(): array
    {
        return [
            'name' => 'process_data',
            'description' => 'Process large datasets with progress tracking',
            'parameters' => [
                'type' => 'object',
                'properties' => [
                    'dataset_id' => [
                        'type' => 'string',
                        'description' => 'ID of the dataset to process',
                    ],
                ],
                'required' => ['dataset_id'],
            ],
        ];
    }

    public function execute(array $arguments, AgentContext $context, AgentMemory $memory): string
    {
        $datasetId = $arguments['dataset_id'];
        
        try {
            // Store progress in memory for agent to track
            $memory->remember('processing_status', 'started');
            
            $dataset = Dataset::find($datasetId);
            $totalItems = $dataset->items()->count();
            $processed = 0;
            
            foreach ($dataset->items()->cursor() as $item) {
                // Process item
                $this->processItem($item);
                $processed++;
                
                // Update progress
                if ($processed % 100 === 0) {
                    $progress = round(($processed / $totalItems) * 100, 2);
                    $memory->remember('processing_progress', $progress);
                }
            }
            
            $memory->remember('processing_status', 'completed');
            
            return json_encode([
                'status' => 'success',
                'processed' => $processed,
                'message' => "Processed {$processed} items successfully",
            ]);
        } catch (\Exception $e) {
            $memory->remember('processing_status', 'failed');
            return json_encode([
                'status' => 'error',
                'message' => $e->getMessage(),
            ]);
        }
    }
}
```

### Tool with Validation
```php
class PaymentProcessorTool implements ToolInterface
{
    public function definition(): array
    {
        return [
            'name' => 'process_payment',
            'description' => 'Process a payment transaction',
            'parameters' => [
                'type' => 'object',
                'properties' => [
                    'amount' => [
                        'type' => 'number',
                        'description' => 'Payment amount in cents',
                    ],
                    'currency' => [
                        'type' => 'string',
                        'enum' => ['USD', 'EUR', 'GBP'],
                    ],
                    'payment_method' => [
                        'type' => 'string',
                        'enum' => ['card', 'bank_transfer', 'paypal'],
                    ],
                ],
                'required' => ['amount', 'currency', 'payment_method'],
            ],
        ];
    }

    public function execute(array $arguments, AgentContext $context, AgentMemory $memory): string
    {
        // Validate amount
        if ($arguments['amount'] <= 0) {
            return json_encode([
                'status' => 'error',
                'message' => 'Amount must be positive',
            ]);
        }
        
        if ($arguments['amount'] > 100000000) { // $1,000,000 limit
            return json_encode([
                'status' => 'error',
                'message' => 'Amount exceeds maximum limit',
            ]);
        }
        
        // Additional validation based on payment method
        if ($arguments['payment_method'] === 'bank_transfer' && $arguments['amount'] < 10000) {
            return json_encode([
                'status' => 'error',
                'message' => 'Bank transfer minimum is $100',
            ]);
        }
        
        try {
            // Process payment...
            $transactionId = $this->processWithProvider($arguments);
            
            return json_encode([
                'status' => 'success',
                'transaction_id' => $transactionId,
                'amount' => $arguments['amount'],
                'currency' => $arguments['currency'],
            ]);
        } catch (\Exception $e) {
            return json_encode([
                'status' => 'error',
                'message' => 'Payment failed: ' . $e->getMessage(),
            ]);
        }
    }
}
```

## Testing Your Tools

```php
use Tests\TestCase;
use App\Tools\MyTool;
use Vizra\VizraADK\System\AgentContext;
use Vizra\VizraADK\Memory\AgentMemory;

class MyToolTest extends TestCase
{
    public function test_tool_executes_successfully()
    {
        $tool = new MyTool();
        
        // Create mock context and memory
        $context = new AgentContext();
        $memory = new AgentMemory();
        
        $result = $tool->execute(
            ['param' => 'value'],
            $context,
            $memory
        );
        
        $decoded = json_decode($result, true);
        $this->assertEquals('success', $decoded['status']);
    }
    
    public function test_tool_definition_is_valid()
    {
        $tool = new MyTool();
        $definition = $tool->definition();
        
        $this->assertArrayHasKey('name', $definition);
        $this->assertArrayHasKey('description', $definition);
        $this->assertArrayHasKey('parameters', $definition);
    }
}
```

## Security Best Practices

1. **Input Validation**: Always validate and sanitize inputs
2. **Path Traversal**: Prevent directory traversal in file operations
3. **SQL Injection**: Use query builders, never raw SQL with user input
4. **Rate Limiting**: Implement rate limits for expensive operations
5. **Authentication**: Verify user permissions in sensitive tools
6. **Secrets**: Never expose API keys or passwords in responses

```php
// Good: Using query builder
DB::table($table)->where('id', $id)->get();

// Bad: Raw SQL with user input
DB::select("SELECT * FROM {$table} WHERE id = {$id}");

// Good: Path sanitization
$path = str_replace(['..', '//'], '', $userPath);

// Good: Permission check
if (!$context->getUser()->can('delete-files')) {
    return json_encode(['status' => 'error', 'message' => 'Unauthorized']);
}
```

## Common Mistakes to Avoid

1. **Not returning JSON** - Always return JSON-encoded strings
2. **Missing error handling** - Wrap operations in try-catch blocks
3. **Forgetting required parameters** - List all required params
4. **Vague descriptions** - Be specific about what the tool does
5. **Not using AgentContext** - Leverage context for user/session data

## Performance Tips

1. **Async Operations**: Use queues for long-running tasks
2. **Caching**: Cache expensive operations when appropriate
3. **Pagination**: Limit results to prevent memory issues
4. **Lazy Loading**: Use cursor() for large datasets
5. **Connection Pooling**: Reuse database/API connections

## Next Steps

- Combine tools in agents: See `agent-creation.blade.php`
- Build tool workflows: See `workflow-patterns.blade.php`
- Use memory effectively: See `memory-usage.blade.php`