# 🛠️ Tools & Capabilities

Tools are what give your agents real power. They bridge the gap between conversation and action, allowing agents to interact with your Laravel application, external APIs, databases, and the real world.

## 🎯 What Are Tools?

Think of tools as superpowers for your agents. While LLMs are great at understanding and generating text, tools let them:

- **Query your database** - "Show me all orders from last month"
- **Call APIs** - "Get the current weather for Tokyo"
- **Send emails** - "Email the customer their order confirmation"
- **Read files** - "What's in the latest sales report?"
- **Store memories** - "Remember that this customer prefers morning deliveries"

## 🏗️ Tool Architecture

```
┌─────────────────┐    ┌─────────────────┐    ┌─────────────────┐
│     Agent       │    │      Tool       │    │   Your App      │
│                 │    │                 │    │                 │
│ "I need to      │───►│ definition()    │    │ • Database      │
│  look up an     │    │ execute()       │───►│ • APIs          │
│  order"         │    │                 │    │ • Files         │
│                 │◄───│ Returns JSON    │    │ • Services      │
└─────────────────┘    └─────────────────┘    └─────────────────┘
```

## 🚀 Built-in Tools

The Vizra SDK comes with several powerful tools ready to use:

### 📊 Vector Memory Tool

Store and retrieve information using semantic search:

```php
protected array $tools = [
    VectorMemoryTool::class,
];
```

**Capabilities:**

- Store documents and knowledge
- Semantic search across stored content
- RAG (Retrieval-Augmented Generation) context
- Memory statistics and management

**Example agent instruction:**

```php
protected string $instructions = "
When customers ask about our policies or procedures, search your memory
for relevant information before responding. If you learn something new
about a customer, store it for future conversations.

Use the vector_memory tool to:
- Store: Important customer preferences and history
- Search: Company policies, product information, past conversations
";
```

### 🔄 More Built-in Tools (Coming Soon)

We're actively developing additional built-in tools:

```php
// Database operations
DatabaseQueryTool::class,     // Execute safe database queries

// External integrations
WebSearchTool::class,         // Search the internet
EmailTool::class,             // Send emails via Laravel Mail
SlackTool::class,            // Post to Slack channels
CalendarTool::class,         // Manage calendar events

// File operations
FileOperationTool::class,     // Read, write, and manage files
ImageProcessingTool::class,   // Analyze and process images
PdfTool::class,              // Extract text from PDFs

// Laravel-specific
ArtisanTool::class,          // Run Artisan commands
CacheTool::class,            // Manage application cache
LogTool::class,              // Read and analyze logs
```

## 🎨 Creating Custom Tools

Custom tools are where the real magic happens. Here's how to build tools tailored to your application:

### Step 1: Generate a Tool

```bash
php artisan agent:make:tool OrderLookupTool
```

### Step 2: Define the Tool Interface

```php
<?php

namespace App\Tools;

use Vizra\VizraSdk\Contracts\ToolInterface;
use Vizra\VizraSdk\System\AgentContext;

class OrderLookupTool implements ToolInterface
{
    public function definition(): array
    {
        return [
            'name' => 'lookup_order',
            'description' => 'Find customer orders by order number, email, or phone',
            'parameters' => [
                'type' => 'object',
                'properties' => [
                    'order_number' => [
                        'type' => 'string',
                        'description' => 'Order number (e.g., ORD-12345)',
                    ],
                    'email' => [
                        'type' => 'string',
                        'description' => 'Customer email address',
                    ],
                    'phone' => [
                        'type' => 'string',
                        'description' => 'Customer phone number',
                    ],
                    'include_items' => [
                        'type' => 'boolean',
                        'description' => 'Include order items in response',
                        'default' => true,
                    ],
                ],
                // At least one search parameter is required
                'anyOf' => [
                    ['required' => ['order_number']],
                    ['required' => ['email']],
                    ['required' => ['phone']],
                ],
            ],
        ];
    }

    public function execute(array $arguments, AgentContext $context): string
    {
        // Implementation goes here...
    }
}
```

### Step 3: Implement the Logic

```php
public function execute(array $arguments, AgentContext $context): string
{
    // Build the query based on provided parameters
    $query = Order::with(['items.product', 'customer']);

    if (isset($arguments['order_number'])) {
        $query->where('order_number', $arguments['order_number']);
    } elseif (isset($arguments['email'])) {
        $query->whereHas('customer', function($q) use ($arguments) {
            $q->where('email', $arguments['email']);
        });
    } elseif (isset($arguments['phone'])) {
        $query->whereHas('customer', function($q) use ($arguments) {
            $q->where('phone', $arguments['phone']);
        });
    }

    $orders = $query->latest()->take(5)->get();

    if ($orders->isEmpty()) {
        return json_encode([
            'found' => false,
            'message' => 'No orders found with the provided information'
        ]);
    }

    // Format the response
    $result = [
        'found' => true,
        'count' => $orders->count(),
        'orders' => $orders->map(function($order) use ($arguments) {
            $orderData = [
                'order_number' => $order->order_number,
                'status' => $order->status,
                'total' => '$' . number_format($order->total_amount, 2),
                'order_date' => $order->created_at->format('M j, Y'),
                'customer' => [
                    'name' => $order->customer->name,
                    'email' => $order->customer->email,
                ],
                'shipping_address' => $order->shipping_address,
            ];

            // Include items if requested
            if ($arguments['include_items'] ?? true) {
                $orderData['items'] = $order->items->map(function($item) {
                    return [
                        'product' => $item->product->name,
                        'quantity' => $item->quantity,
                        'price' => '$' . number_format($item->price, 2),
                    ];
                });
            }

            // Add tracking info if available
            if ($order->tracking_number) {
                $orderData['tracking'] = [
                    'number' => $order->tracking_number,
                    'carrier' => $order->shipping_carrier,
                    'estimated_delivery' => $order->estimated_delivery_date?->format('M j, Y'),
                ];
            }

            return $orderData;
        }),
    ];

    return json_encode($result);
}
```

## 🎯 Tool Best Practices

### ✅ Good Tool Design

**1. Clear, Descriptive Names**

```php
// Good
'name' => 'lookup_customer_orders',
'name' => 'send_order_confirmation_email',
'name' => 'calculate_shipping_cost',

// Avoid
'name' => 'do_something',
'name' => 'helper_function',
'name' => 'process',
```

**2. Detailed Descriptions**

```php
'description' => 'Look up customer orders by order number, email, or phone number.
Returns order details including status, items, and tracking information.',

// Not just:
'description' => 'Gets orders',
```

**3. Well-Defined Parameters**

```php
'parameters' => [
    'type' => 'object',
    'properties' => [
        'query' => [
            'type' => 'string',
            'description' => 'Search query (product name, SKU, or category)',
            'minLength' => 2,
            'maxLength' => 100,
        ],
        'category' => [
            'type' => 'string',
            'description' => 'Filter by category (electronics, clothing, books)',
            'enum' => ['electronics', 'clothing', 'books', 'home', 'sports'],
        ],
        'max_price' => [
            'type' => 'number',
            'description' => 'Maximum price filter',
            'minimum' => 0,
        ],
    ],
    'required' => ['query'],
],
```

**4. Helpful Error Handling**

```php
public function execute(array $arguments, AgentContext $context): string
{
    try {
        // Validate input
        if (empty($arguments['query'])) {
            return json_encode([
                'success' => false,
                'error' => 'Search query is required',
                'suggestion' => 'Try searching for a product name or category'
            ]);
        }

        // Perform the operation
        $results = $this->searchProducts($arguments);

        return json_encode([
            'success' => true,
            'results' => $results,
            'total' => count($results),
        ]);

    } catch (\Exception $e) {
        // Log the error for debugging
        \Log::error('Product search tool error', [
            'arguments' => $arguments,
            'error' => $e->getMessage(),
        ]);

        return json_encode([
            'success' => false,
            'error' => 'Search temporarily unavailable',
            'suggestion' => 'Please try again in a moment'
        ]);
    }
}
```

### ❌ Common Pitfalls

**1. Returning Raw Exceptions**

```php
// Don't do this
public function execute(array $arguments, AgentContext $context): string
{
    $result = SomeService::doSomething($arguments['param']);
    return json_encode($result); // What if this throws an exception?
}
```

**2. Inconsistent Response Formats**

```php
// Don't mix formats
return "Order not found"; // Sometimes string
return json_encode(['error' => 'Order not found']); // Sometimes JSON
```

**3. Security Issues**

```php
// Never do this - SQL injection risk!
$query = "SELECT * FROM users WHERE email = '{$arguments['email']}'";
$results = DB::select($query);

// Use parameter binding instead
$results = DB::select('SELECT * FROM users WHERE email = ?', [$arguments['email']]);
```

## 🔐 Security Considerations

### Input Validation

Always validate and sanitize tool inputs:

```php
public function execute(array $arguments, AgentContext $context): string
{
    // Validate required parameters
    $validator = Validator::make($arguments, [
        'email' => 'required|email|max:255',
        'amount' => 'required|numeric|min:0|max:10000',
        'order_id' => 'required|string|regex:/^ORD-[A-Z0-9]+$/',
    ]);

    if ($validator->fails()) {
        return json_encode([
            'success' => false,
            'error' => 'Invalid parameters',
            'details' => $validator->errors()->first(),
        ]);
    }

    // Proceed with validated data
    $validatedData = $validator->validated();
    // ...
}
```

### Permission Checks

Ensure tools respect user permissions:

```php
public function execute(array $arguments, AgentContext $context): string
{
    // Get the current user from context
    $userId = $context->getState('user_id');
    $user = User::find($userId);

    // Check permissions
    if (!$user || !$user->can('view-orders')) {
        return json_encode([
            'success' => false,
            'error' => 'Insufficient permissions',
        ]);
    }

    // Scope data to user's access level
    $orders = Order::where('user_id', $userId)->get();
    // ...
}
```

### Rate Limiting

Protect external APIs and expensive operations:

```php
use Illuminate\Support\Facades\RateLimiter;

public function execute(array $arguments, AgentContext $context): string
{
    $key = 'api-calls:' . $context->getSessionId();

    if (RateLimiter::tooManyAttempts($key, 10)) {
        $seconds = RateLimiter::availableIn($key);

        return json_encode([
            'success' => false,
            'error' => "Rate limit exceeded. Try again in {$seconds} seconds.",
        ]);
    }

    RateLimiter::hit($key, 60); // 10 calls per minute

    // Make the API call
    // ...
}
```

## 📊 Advanced Tool Patterns

### Context-Aware Tools

Tools can access and modify agent context:

```php
public function execute(array $arguments, AgentContext $context): string
{
    // Get previous conversation context
    $customerName = $context->getState('customer_name');
    $preferredLanguage = $context->getState('language', 'en');

    // Perform operation with context
    $result = $this->performSearch($arguments, $preferredLanguage);

    // Update context for future use
    $context->setState('last_search_query', $arguments['query']);
    $context->setState('search_results_count', count($result));

    return json_encode($result);
}
```

### Streaming Tools

For long-running operations, provide progress updates:

```php
public function execute(array $arguments, AgentContext $context): string
{
    $totalSteps = 100;
    $results = [];

    for ($i = 0; $i < $totalSteps; $i++) {
        // Perform work
        $stepResult = $this->processStep($i);
        $results[] = $stepResult;

        // Send progress update
        if ($i % 10 === 0) {
            $progress = intval(($i / $totalSteps) * 100);
            $context->streamUpdate([
                'type' => 'progress',
                'message' => "Processing... {$progress}% complete",
                'progress' => $progress,
            ]);
        }
    }

    return json_encode([
        'success' => true,
        'results' => $results,
        'total_processed' => $totalSteps,
    ]);
}
```

### Composite Tools

Tools that orchestrate multiple operations:

```php
class CompleteOrderTool implements ToolInterface
{
    public function execute(array $arguments, AgentContext $context): string
    {
        try {
            // Step 1: Validate inventory
            $inventory = $this->checkInventory($arguments['items']);
            if (!$inventory['available']) {
                return json_encode(['success' => false, 'error' => 'Items not available']);
            }

            // Step 2: Calculate pricing
            $pricing = $this->calculatePricing($arguments['items'], $arguments['customer_id']);

            // Step 3: Process payment
            $payment = $this->processPayment($pricing['total'], $arguments['payment_method']);
            if (!$payment['success']) {
                return json_encode(['success' => false, 'error' => 'Payment failed']);
            }

            // Step 4: Create order
            $order = $this->createOrder([
                'customer_id' => $arguments['customer_id'],
                'items' => $arguments['items'],
                'payment_id' => $payment['payment_id'],
                'total' => $pricing['total'],
            ]);

            // Step 5: Send confirmation
            $this->sendOrderConfirmation($order);

            return json_encode([
                'success' => true,
                'order' => $order,
                'message' => 'Order completed successfully',
            ]);

        } catch (\Exception $e) {
            // Rollback any partial operations
            $this->rollbackPartialOrder($context->getState('partial_order_id'));

            return json_encode([
                'success' => false,
                'error' => 'Order processing failed',
                'support_message' => 'Please contact support with reference: ' . $context->getSessionId(),
            ]);
        }
    }
}
```

## 🧪 Testing Your Tools

### Unit Testing

```php
// tests/Unit/Tools/OrderLookupToolTest.php

use Tests\TestCase;
use App\Tools\OrderLookupTool;
use Vizra\VizraSdk\System\AgentContext;

class OrderLookupToolTest extends TestCase
{
    public function test_lookup_by_order_number()
    {
        // Arrange
        $order = Order::factory()->create(['order_number' => 'ORD-12345']);
        $tool = new OrderLookupTool();
        $context = new AgentContext();

        // Act
        $result = $tool->execute(['order_number' => 'ORD-12345'], $context);
        $response = json_decode($result, true);

        // Assert
        $this->assertTrue($response['found']);
        $this->assertEquals('ORD-12345', $response['orders'][0]['order_number']);
    }

    public function test_handles_missing_order()
    {
        $tool = new OrderLookupTool();
        $context = new AgentContext();

        $result = $tool->execute(['order_number' => 'NONEXISTENT'], $context);
        $response = json_decode($result, true);

        $this->assertFalse($response['found']);
        $this->assertStringContainsString('No orders found', $response['message']);
    }

    public function test_validates_required_parameters()
    {
        $tool = new OrderLookupTool();
        $context = new AgentContext();

        $result = $tool->execute([], $context);
        $response = json_decode($result, true);

        $this->assertFalse($response['success']);
        $this->assertStringContainsString('required', $response['error']);
    }
}
```

### Integration Testing

```php
// tests/Feature/Tools/ToolIntegrationTest.php

public function test_agent_can_use_order_lookup_tool()
{
    // Create test data
    $order = Order::factory()->create(['order_number' => 'ORD-TEST-123']);

    // Create agent with tool
    $agent = new CustomerSupportAgent();
    $context = new AgentContext();

    // Test agent using the tool
    $response = $agent->run(
        "Can you look up order ORD-TEST-123?",
        $context
    );

    // Verify the agent found and used the order data
    $this->assertStringContainsString('ORD-TEST-123', $response);
    $this->assertStringContainsString($order->customer->name, $response);
}
```

## 📚 Real-World Examples

### E-commerce Tools

```php
// Product search with inventory checking
class ProductSearchTool implements ToolInterface { /* ... */ }

// Shopping cart management
class CartManagementTool implements ToolInterface { /* ... */ }

// Order tracking and updates
class OrderTrackingTool implements ToolInterface { /* ... */ }

// Customer review analysis
class ReviewAnalysisTool implements ToolInterface { /* ... */ }
```

### SaaS Application Tools

```php
// User analytics and insights
class UserAnalyticsTool implements ToolInterface { /* ... */ }

// Feature usage tracking
class FeatureUsageTool implements ToolInterface { /* ... */ }

// Billing and subscription management
class SubscriptionTool implements ToolInterface { /* ... */ }

// Support ticket creation
class SupportTicketTool implements ToolInterface { /* ... */ }
```

### Content Management Tools

```php
// Content creation and editing
class ContentEditorTool implements ToolInterface { /* ... */ }

// SEO analysis and optimization
class SeoAnalysisTool implements ToolInterface { /* ... */ }

// Social media posting
class SocialMediaTool implements ToolInterface { /* ... */ }

// Performance analytics
class AnalyticsTool implements ToolInterface { /* ... */ }
```

## 🎯 Next Steps

Ready to build even smarter agents? Here's what to explore next:

- **[Evaluation & Testing](evaluation.md)** - Test your tools and agents at scale
- **[Vector Memory & RAG](vector-memory.md)** - Give your agents long-term memory
- **[Configuration](configuration.md)** - Advanced configuration and optimization

---

<p align="center">
<strong>Want to see more examples?</strong><br>
<a href="evaluation.md">Next: Evaluation & Testing →</a>
</p>
