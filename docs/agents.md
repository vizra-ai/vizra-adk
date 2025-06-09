# 🧠 Agent Development Guide

Building great AI agents is part art, part science. This guide covers everything from basic concepts to advanced techniques that will help you create intelligent, reliable, and delightful agent experiences.

## 🎯 Agent Fundamentals

### What Makes a Great Agent?

**Great agents are:**

- **Focused** - They have a clear purpose and domain expertise
- **Helpful** - They provide useful, actionable responses
- **Consistent** - They behave predictably and maintain personality
- **Contextual** - They remember past conversations and learn from them
- **Capable** - They can use tools to get real work done

### The Agent Lifecycle

```
User Input → Context Loading → Tool Execution → LLM Processing → Response → Memory Storage
     ↑                                                                           ↓
     └─────────────────── Session Continuity ←──────────────────────────────────┘
```

## 🏗️ Building Your Agent

### Basic Agent Structure

```php
<?php

namespace App\Agents;

use Vizra\VizraSdk\Agents\BaseLlmAgent;

class CustomerSupportAgent extends BaseLlmAgent
{
    // Core agent identity
    protected string $instructions = "...";

    // Available capabilities
    protected array $tools = [];

    // Behavior configuration
    protected string $model = 'gpt-4o';
    protected float $temperature = 0.7;
    protected int $maxTokens = 1500;

    // Custom methods for advanced behavior
    public function beforeProcessing(string $input, AgentContext $context): void
    {
        // Pre-processing logic
    }

    public function afterProcessing(string $response, AgentContext $context): string
    {
        // Post-processing logic
        return $response;
    }
}
```

## 📝 Writing Effective Instructions

Your agent's instructions are its DNA. Here's how to write instructions that create amazing agents:

### ✅ Good Instructions

```php
protected string $instructions = "
You are Alex, a senior customer support specialist for TechShop, an online electronics retailer.

## Your Role
- Help customers with orders, returns, product questions, and technical issues
- Always be friendly, professional, and solution-oriented
- Escalate complex issues to human agents when appropriate

## Your Knowledge
- You have access to our order system, product catalog, and knowledge base
- You know our return policy (30 days), shipping times (2-5 business days), and warranty terms
- You're familiar with our top product categories: smartphones, laptops, gaming gear, and accessories

## Your Personality
- Warm and approachable, but not overly casual
- Patient with frustrated customers
- Proactive in offering solutions and alternatives
- Use clear, jargon-free language

## Response Style
- Start with empathy if the customer has an issue
- Provide specific, actionable steps
- Include relevant order numbers, product links, or tracking info when available
- End with a question to ensure you've fully helped

## Escalation Guidelines
- Escalate if: refund over $500, complex technical repairs, legal issues, or angry customers
- Always explain why you're escalating and what the customer can expect next
";
```

### ❌ Poor Instructions

```php
// Too vague and generic
protected string $instructions = "You are a helpful assistant. Answer questions politely.";

// Too technical and rigid
protected string $instructions = "Process customer queries using decision tree protocols.
Execute function calls as specified in the knowledge management system.";

// Too restrictive
protected string $instructions = "Only answer questions about products.
Do not help with anything else. Follow the script exactly.";
```

### 🎨 Instructions Best Practices

**1. Define Clear Identity**

```php
"You are Jamie, a fitness coach specializing in beginner-friendly workouts..."
```

**2. Set Boundaries**

```php
"Focus on workout routines and nutrition basics. For medical concerns,
recommend consulting a healthcare professional."
```

**3. Provide Context**

```php
"Our gym offers equipment for: cardio, strength training, yoga, and swimming.
Peak hours are 6-9 AM and 6-9 PM."
```

**4. Include Examples**

```php
"When suggesting workouts, format like this:
**Exercise:** Push-ups
**Sets:** 3 sets of 10-15 reps
**Notes:** Keep core tight, full range of motion"
```

## 🧰 Tools and Capabilities

Tools are what make agents truly powerful. They can interact with your application, external APIs, and real-world systems.

### Built-in Tools

The Vizra SDK comes with several ready-to-use tools:

```php
protected array $tools = [
    // Memory and knowledge
    VectorMemoryTool::class,        // Store and retrieve information

    // Development helpers (will be built-in soon)
    DatabaseQueryTool::class,       // Query your database
    WebSearchTool::class,          // Search the internet
    FileOperationTool::class,      // Read/write files
    EmailTool::class,              // Send emails
    SlackTool::class,              // Post to Slack
];
```

### Custom Tools

Creating custom tools is straightforward:

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
            'description' => 'Look up customer order details by order number or email',
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
                ],
                // At least one parameter is required
                'anyOf' => [
                    ['required' => ['order_number']],
                    ['required' => ['email']],
                ],
            ],
        ];
    }

    public function execute(array $arguments, AgentContext $context): string
    {
        if (isset($arguments['order_number'])) {
            $order = Order::where('order_number', $arguments['order_number'])->first();
        } else {
            $order = Order::where('customer_email', $arguments['email'])
                         ->latest()
                         ->first();
        }

        if (!$order) {
            return json_encode([
                'found' => false,
                'message' => 'No order found with the provided information'
            ]);
        }

        return json_encode([
            'found' => true,
            'order_number' => $order->order_number,
            'status' => $order->status,
            'total' => $order->total_amount,
            'items' => $order->items->map(fn($item) => [
                'name' => $item->product_name,
                'quantity' => $item->quantity,
                'price' => $item->price,
            ]),
            'shipping_address' => $order->shipping_address,
            'tracking_number' => $order->tracking_number,
            'estimated_delivery' => $order->estimated_delivery_date,
        ]);
    }
}
```

## 🧠 Memory and Context

Agents can remember information across conversations, making them much more useful for ongoing relationships.

### Session Memory

Automatic memory that persists throughout a conversation:

```php
public function beforeProcessing(string $input, AgentContext $context): void
{
    // Store user preferences
    if (str_contains(strtolower($input), 'my name is')) {
        preg_match('/my name is (\w+)/i', $input, $matches);
        if (isset($matches[1])) {
            $context->setState('user_name', $matches[1]);
        }
    }

    // Track conversation topics
    $topics = $context->getState('conversation_topics', []);
    $topics[] = $this->extractTopic($input);
    $context->setState('conversation_topics', array_unique($topics));
}

public function afterProcessing(string $response, AgentContext $context): string
{
    // Personalize responses based on stored information
    if ($userName = $context->getState('user_name')) {
        $response = str_replace('Hello!', "Hello, {$userName}!", $response);
    }

    return $response;
}
```

### Vector Memory

Long-term semantic memory for storing and retrieving information:

```php
protected array $tools = [
    VectorMemoryTool::class,
];

// In your instructions:
protected string $instructions = "
...
If you need to remember important information about this customer for future
conversations, use the vector_memory tool to store it. Examples:
- Customer preferences and history
- Previous issues and resolutions
- Special circumstances or notes

Before answering complex questions, search your memory for relevant context
that might help provide a better response.
";
```

## 🎭 Personality and Tone

### Consistent Character

```php
protected string $instructions = "
You are Marcus, a laid-back but knowledgeable tech support specialist.

## Your Personality
- **Speaking Style**: Casual but professional, like talking to a tech-savvy friend
- **Humor**: Light tech humor is okay, but never at the customer's expense
- **Approach**: 'Let's figure this out together' rather than 'here's what you did wrong'
- **Phrases you use**: 'No worries', 'Let me take a look', 'Here's what I'm thinking'
- **Phrases you avoid**: 'Obviously', 'You should have', 'It's simple'

## Example Interactions
Customer: 'My wifi keeps dropping out'
You: 'Ah, the classic wifi gremlins! No worries, let's get this sorted.
Can you tell me what device you're using and when this started happening?'
";
```

### Adaptive Tone

```php
public function beforeProcessing(string $input, AgentContext $context): void
{
    // Detect customer mood and adjust accordingly
    $frustrationLevel = $this->detectFrustration($input);
    $context->setState('customer_mood', $frustrationLevel);
}

private function detectFrustration(string $input): string
{
    $frustratedWords = ['angry', 'frustrated', 'upset', 'terrible', 'awful', 'hate'];
    $urgentWords = ['urgent', 'asap', 'immediately', 'now', 'emergency'];

    $text = strtolower($input);

    if (str_contains($text, '!!!') || str_contains($text, 'CAPS')) {
        return 'very_frustrated';
    }

    foreach ($frustratedWords as $word) {
        if (str_contains($text, $word)) {
            return 'frustrated';
        }
    }

    foreach ($urgentWords as $word) {
        if (str_contains($text, $word)) {
            return 'urgent';
        }
    }

    return 'neutral';
}
```

## 🚀 Advanced Patterns

### Multi-Turn Conversations

```php
public function beforeProcessing(string $input, AgentContext $context): void
{
    // Track conversation flow
    $conversationFlow = $context->getState('conversation_flow', []);
    $currentStep = end($conversationFlow) ?: 'initial';

    // Determine next step based on current state and input
    $nextStep = $this->determineNextStep($currentStep, $input, $context);
    $conversationFlow[] = $nextStep;

    $context->setState('conversation_flow', $conversationFlow);
    $context->setState('current_step', $nextStep);
}

private function determineNextStep(string $currentStep, string $input, AgentContext $context): string
{
    return match($currentStep) {
        'initial' => $this->hasOrderNumber($input) ? 'order_lookup' : 'gather_info',
        'gather_info' => 'order_lookup',
        'order_lookup' => $this->isIssueResolved($input) ? 'complete' : 'troubleshoot',
        'troubleshoot' => $this->needsEscalation($input) ? 'escalate' : 'resolve',
        default => 'complete'
    };
}
```

### Proactive Assistance

```php
public function afterProcessing(string $response, AgentContext $context): string
{
    $currentStep = $context->getState('current_step');

    // Add proactive suggestions based on conversation state
    if ($currentStep === 'order_lookup') {
        $response .= "\n\n💡 **While I have your order up, would you like me to:**";
        $response .= "\n- Check your delivery status";
        $response .= "\n- Review your recent orders";
        $response .= "\n- Help with returns or exchanges";
    }

    return $response;
}
```

### Error Handling and Fallbacks

```php
public function handleToolError(string $toolName, \Exception $error, AgentContext $context): string
{
    // Log the error for debugging
    \Log::error("Tool error in agent", [
        'tool' => $toolName,
        'error' => $error->getMessage(),
        'agent' => static::class,
        'session_id' => $context->getSessionId(),
    ]);

    // Provide helpful fallback responses
    return match($toolName) {
        'lookup_order' => "I'm having trouble accessing our order system right now.
                          Can you email us at support@example.com with your order number?
                          We'll get back to you within 2 hours.",

        'vector_memory' => "I can't access my knowledge base at the moment, but I can
                           still help with general questions about our products and policies.",

        default => "I encountered a technical issue, but I'm still here to help!
                   What else can I assist you with?"
    };
}
```

## 🎯 Testing Your Agents

### Manual Testing

```bash
# Interactive chat testing
php artisan agent:chat your_agent_name

# Single message testing
php artisan agent:test your_agent_name "Test message here"
```

### Automated Testing

```php
// tests/Feature/Agents/CustomerSupportAgentTest.php
public function test_handles_order_lookup_correctly()
{
    $agent = new CustomerSupportAgent();
    $context = new AgentContext();

    $response = $agent->run("Can you look up order ORD-12345?", $context);

    $this->assertStringContainsString('ORD-12345', $response);
    $this->assertStringContainsString('status', $response);
}

public function test_escalates_complex_issues()
{
    $agent = new CustomerSupportAgent();
    $context = new AgentContext();

    $response = $agent->run("I want to sue you for $10,000 damages!", $context);

    $this->assertStringContainsString('escalate', strtolower($response));
    $this->assertStringContainsString('manager', strtolower($response));
}
```

## 📊 Performance and Optimization

### Model Selection

```php
// Fast responses for simple tasks
class QuickHelpAgent extends BaseLlmAgent
{
    protected string $model = 'gpt-4o-mini';
    protected float $temperature = 0.3;
    protected int $maxTokens = 500;
}

// Detailed responses for complex tasks
class TechnicalSupportAgent extends BaseLlmAgent
{
    protected string $model = 'gpt-4o';
    protected float $temperature = 0.7;
    protected int $maxTokens = 2000;
}
```

### Caching Strategies

```php
public function beforeProcessing(string $input, AgentContext $context): void
{
    // Cache common responses
    $cacheKey = 'agent_response:' . md5($input);

    if ($cached = cache()->get($cacheKey)) {
        $context->setState('cached_response', $cached);
        return;
    }
}

public function afterProcessing(string $response, AgentContext $context): string
{
    // Cache the response for similar future queries
    if (!$context->getState('cached_response')) {
        $cacheKey = 'agent_response:' . md5($context->getLastUserInput());
        cache()->put($cacheKey, $response, now()->addMinutes(30));
    }

    return $response;
}
```

## 🎉 Agent Examples

Check out these real-world agent implementations:

- **[Customer Support Agent](examples/customer-support-agent.md)** - Handle orders, returns, and FAQs
- **[Content Creator Agent](examples/content-creator-agent.md)** - Generate blog posts, social media, and marketing copy
- **[Data Analyst Agent](examples/data-analyst-agent.md)** - Query databases and generate insights
- **[Personal Assistant Agent](examples/personal-assistant-agent.md)** - Manage calendars, emails, and tasks
- **[Code Review Agent](examples/code-review-agent.md)** - Review code and suggest improvements

---

<p align="center">
<strong>Ready to build powerful tools for your agents?</strong><br>
<a href="tools.md">Next: Tools & Capabilities →</a>
</p>
