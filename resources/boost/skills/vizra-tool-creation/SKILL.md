---
name: "Vizra ADK Tool Creation"
description: "Build custom tools for Vizra ADK agents - includes patterns for database, API, file, and email tools"
---

# Building Vizra ADK Tools

Tools extend agent capabilities by allowing them to interact with databases, APIs, files, and external services.

## Tool Class Structure

Every tool MUST implement `ToolInterface`:

```php
<?php

namespace App\Tools;

use Vizra\VizraADK\Contracts\ToolInterface;
use Vizra\VizraADK\Agents\AgentContext;

class {{ ToolName }}Tool implements ToolInterface
{
    /**
     * Define the tool's schema for the LLM
     */
    public function definition(): array
    {
        return [
            'name' => '{{ tool_name }}',
            'description' => '{{ What this tool does - be specific }}',
            'parameters' => [
                'type' => 'object',
                'properties' => [
                    'param1' => [
                        'type' => 'string',
                        'description' => '{{ Parameter description }}'
                    ],
                    'param2' => [
                        'type' => 'integer',
                        'description' => '{{ Parameter description }}'
                    ],
                ],
                'required' => ['param1'],
            ],
        ];
    }

    /**
     * Execute the tool with given arguments
     */
    public function execute(array $arguments, AgentContext $context): string
    {
        // Tool logic here

        return json_encode([
            'status' => 'success',
            'data' => $result
        ]);
    }
}
```

## Key Rules

1. **Naming Convention**:
   - Class name MUST end with `Tool`
   - The `name` in definition should be snake_case

2. **Definition Requirements**:
   - `name`: Unique identifier for the tool
   - `description`: Clear description of what the tool does (LLM uses this to decide when to use the tool)
   - `parameters`: JSON Schema format describing expected inputs

3. **Return Format**:
   - Always return JSON-encoded strings
   - Include status indicators for success/error
   - Provide meaningful error messages

## Parameter Types

```php
'parameters' => [
    'type' => 'object',
    'properties' => [
        // String parameter
        'name' => [
            'type' => 'string',
            'description' => 'The user name'
        ],

        // Integer parameter
        'count' => [
            'type' => 'integer',
            'description' => 'Number of items'
        ],

        // Boolean parameter
        'active' => [
            'type' => 'boolean',
            'description' => 'Whether the item is active'
        ],

        // Enum parameter
        'status' => [
            'type' => 'string',
            'enum' => ['pending', 'approved', 'rejected'],
            'description' => 'Current status'
        ],

        // Array parameter
        'tags' => [
            'type' => 'array',
            'items' => ['type' => 'string'],
            'description' => 'List of tags'
        ],

        // Object parameter
        'metadata' => [
            'type' => 'object',
            'properties' => [
                'key' => ['type' => 'string'],
                'value' => ['type' => 'string']
            ],
            'description' => 'Additional metadata'
        ],
    ],
    'required' => ['name', 'count'],
]
```

## Common Tool Patterns

### Database Query Tool
```php
class DatabaseQueryTool implements ToolInterface
{
    public function definition(): array
    {
        return [
            'name' => 'query_database',
            'description' => 'Execute a read-only database query to retrieve information',
            'parameters' => [
                'type' => 'object',
                'properties' => [
                    'table' => [
                        'type' => 'string',
                        'description' => 'The table to query'
                    ],
                    'conditions' => [
                        'type' => 'object',
                        'description' => 'WHERE conditions as key-value pairs'
                    ],
                    'limit' => [
                        'type' => 'integer',
                        'description' => 'Maximum number of results'
                    ],
                ],
                'required' => ['table'],
            ],
        ];
    }

    public function execute(array $arguments, AgentContext $context): string
    {
        $allowedTables = ['users', 'orders', 'products'];

        if (!in_array($arguments['table'], $allowedTables)) {
            return json_encode([
                'status' => 'error',
                'message' => 'Table not allowed'
            ]);
        }

        $query = DB::table($arguments['table']);

        if (isset($arguments['conditions'])) {
            foreach ($arguments['conditions'] as $key => $value) {
                $query->where($key, $value);
            }
        }

        $results = $query->limit($arguments['limit'] ?? 10)->get();

        return json_encode([
            'status' => 'success',
            'data' => $results,
            'count' => $results->count()
        ]);
    }
}
```

### API Integration Tool
```php
class ExternalApiTool implements ToolInterface
{
    public function definition(): array
    {
        return [
            'name' => 'call_external_api',
            'description' => 'Make requests to the external service API',
            'parameters' => [
                'type' => 'object',
                'properties' => [
                    'endpoint' => [
                        'type' => 'string',
                        'description' => 'API endpoint path'
                    ],
                    'method' => [
                        'type' => 'string',
                        'enum' => ['GET', 'POST'],
                        'description' => 'HTTP method'
                    ],
                    'data' => [
                        'type' => 'object',
                        'description' => 'Request payload for POST requests'
                    ],
                ],
                'required' => ['endpoint', 'method'],
            ],
        ];
    }

    public function execute(array $arguments, AgentContext $context): string
    {
        try {
            $response = Http::withToken(config('services.external.api_key'))
                ->baseUrl(config('services.external.base_url'))
                ->{strtolower($arguments['method'])}(
                    $arguments['endpoint'],
                    $arguments['data'] ?? []
                );

            return json_encode([
                'status' => 'success',
                'data' => $response->json(),
                'http_status' => $response->status()
            ]);
        } catch (\Exception $e) {
            return json_encode([
                'status' => 'error',
                'message' => $e->getMessage()
            ]);
        }
    }
}
```

### File Operations Tool
```php
class FileOperationsTool implements ToolInterface
{
    public function definition(): array
    {
        return [
            'name' => 'file_operations',
            'description' => 'Read or write files in the allowed storage directory',
            'parameters' => [
                'type' => 'object',
                'properties' => [
                    'operation' => [
                        'type' => 'string',
                        'enum' => ['read', 'write', 'list'],
                        'description' => 'Operation to perform'
                    ],
                    'path' => [
                        'type' => 'string',
                        'description' => 'File path relative to storage'
                    ],
                    'content' => [
                        'type' => 'string',
                        'description' => 'Content to write (for write operation)'
                    ],
                ],
                'required' => ['operation', 'path'],
            ],
        ];
    }

    public function execute(array $arguments, AgentContext $context): string
    {
        // Sanitize path to prevent directory traversal
        $safePath = str_replace(['..', '//'], '', $arguments['path']);
        $fullPath = storage_path('app/agent-files/' . $safePath);

        switch ($arguments['operation']) {
            case 'read':
                if (!file_exists($fullPath)) {
                    return json_encode(['status' => 'error', 'message' => 'File not found']);
                }
                return json_encode([
                    'status' => 'success',
                    'content' => file_get_contents($fullPath)
                ]);

            case 'write':
                file_put_contents($fullPath, $arguments['content'] ?? '');
                return json_encode(['status' => 'success', 'message' => 'File written']);

            case 'list':
                $files = glob($fullPath . '/*');
                return json_encode([
                    'status' => 'success',
                    'files' => array_map('basename', $files)
                ]);
        }
    }
}
```

### Email Tool
```php
class EmailTool implements ToolInterface
{
    public function definition(): array
    {
        return [
            'name' => 'send_email',
            'description' => 'Send an email to a recipient',
            'parameters' => [
                'type' => 'object',
                'properties' => [
                    'to' => [
                        'type' => 'string',
                        'description' => 'Recipient email address'
                    ],
                    'subject' => [
                        'type' => 'string',
                        'description' => 'Email subject line'
                    ],
                    'body' => [
                        'type' => 'string',
                        'description' => 'Email body content'
                    ],
                ],
                'required' => ['to', 'subject', 'body'],
            ],
        ];
    }

    public function execute(array $arguments, AgentContext $context): string
    {
        try {
            // Validate email
            if (!filter_var($arguments['to'], FILTER_VALIDATE_EMAIL)) {
                return json_encode([
                    'status' => 'error',
                    'message' => 'Invalid email address'
                ]);
            }

            Mail::raw($arguments['body'], function ($message) use ($arguments) {
                $message->to($arguments['to'])
                        ->subject($arguments['subject']);
            });

            return json_encode([
                'status' => 'success',
                'message' => 'Email sent successfully'
            ]);
        } catch (\Exception $e) {
            return json_encode([
                'status' => 'error',
                'message' => 'Failed to send email: ' . $e->getMessage()
            ]);
        }
    }
}
```

## Error Handling Best Practices

```php
public function execute(array $arguments, AgentContext $context): string
{
    try {
        // Validate inputs
        if (empty($arguments['required_field'])) {
            return json_encode([
                'status' => 'error',
                'error_type' => 'validation',
                'message' => 'required_field is missing'
            ]);
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

## Security Best Practices

1. **Validate all inputs** using Laravel's validator
2. **Sanitize file paths** to prevent directory traversal
3. **Whitelist allowed operations** instead of blacklisting
4. **Use environment variables** for API keys and secrets
5. **Check user permissions** via AgentContext when needed

```php
public function execute(array $arguments, AgentContext $context): string
{
    // Check user permissions
    $user = $context->getUser();
    if (!$user->can('perform-action')) {
        return json_encode([
            'status' => 'error',
            'message' => 'Unauthorized'
        ]);
    }

    // Validate inputs
    $validated = validator($arguments, [
        'email' => 'required|email',
        'amount' => 'required|numeric|min:0|max:10000',
    ])->validate();

    // Proceed with validated data
}
```

## Adding Tools to Agents

```php
class MyAgent extends BaseLlmAgent
{
    protected array $tools = [
        DatabaseQueryTool::class,
        EmailTool::class,
        FileOperationsTool::class,
    ];
}
```

## Artisan Commands

```bash
# Create new tool
php artisan vizra:make:tool MyTool
```
