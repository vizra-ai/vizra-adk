# Personal Shopping Assistant - Context Demo

This example demonstrates how to effectively use `AgentContext` to build stateful, context-aware agents in the Vizra ADK.

## What This Example Shows

### 1. **Session State Management**
The agent maintains persistent state throughout the conversation:

```php
// Cart state
$cart = $context->getState('cart', []);
$budget = $context->getState('budget');
$preferences = $context->getState('preferences', []);
$totalSpent = $context->getState('total_spent', 0);
```

### 2. **Lifecycle Hooks for Context Updates**

**beforeLlmCall()** - Inject context into prompts:
```php
public function beforeLlmCall(array $inputMessages, AgentContext $context): array
{
    // Build context summary with cart, budget, preferences
    $contextSummary = $this->buildContextSummary($cart, $budget, $preferences, $totalSpent);
    
    // Inject into system message
    $inputMessages[0]['content'] = $this->instructions . "\n\n" . $contextSummary;
    
    return $inputMessages;
}
```

**afterLlmResponse()** - Extract insights and update context:
```php
public function afterLlmResponse(Response|Generator $response, AgentContext $context): mixed
{
    // Extract user preferences from conversation
    $this->extractAndStorePreferences($responseText, $context);
    
    // Update shopping goals
    $this->updateShoppingGoals($responseText, $context);
    
    return $response;
}
```

### 3. **Tool Integration with Context**
The `CartManagerTool` demonstrates how tools can read from and update context:

```php
// Read current state
$cart = $context->getState('cart', []);
$budget = $context->getState('budget');

// Update state after operations
$context->setState('cart', $cart);
$context->setState('total_spent', $newTotal);
```

### 4. **LLM-Structured Context Extraction**
The agent uses sophisticated JSON-structured output instead of regex parsing:

```php
// The LLM intelligently structures its own output
$this->parseStructuredResponse($responseText, $context);

// JSON example from LLM:
{
  "context_update": {
    "shopping_goals": {
      "budget": 150,
      "purpose": "gifts for family"
    },
    "preferences": {
      "recipients": {
        "mom": "loves plants and gardening",
        "brother": "tech-savvy, likes gadgets"
      }
    }
  }
}
```

## Demo Conversation Flow

```
User: "I need to buy gifts for my family, budget is $200"
→ Context: budget=200, shopping_purpose="gifts for my family"

User: "My mom loves gardening and eco-friendly products"  
→ Context: preferences.recipients.mom="loves gardening and eco-friendly products"

Agent: "Great! Let me add an organic seed starter kit to your cart - $25"
→ Uses cart_manager tool to add item
→ Context: cart=[{name: "Organic seed starter kit", price: 25}], total_spent=25

User: "Perfect! What about something for my tech-savvy brother?"
→ Context: preferences.recipients.brother="tech-savvy"

Agent: "How about wireless earbuds for $45? I can add them to your cart."
→ Agent knows budget (has $175 remaining) and brother's preferences

User: "Add them. What's in my cart now?"
→ Tool updates context: cart=[...], total_spent=70
→ Agent responds with current cart summary and remaining budget ($130)
```

## Key Context Features

### State Persistence
- **Cart Items**: Full product details with prices
- **Budget Tracking**: Prevents overspending
- **User Preferences**: Learned from conversation
- **Shopping Goals**: Purpose, target count, etc.

### Dynamic Prompt Injection
Every LLM call includes current context:
```
=== CURRENT CONTEXT ===
Budget: $200.00
Spent: $70.00
Remaining: $130.00

Current Cart:
- Organic seed starter kit: $25.00
- Wireless earbuds: $45.00

User Preferences:
- recipients: mom=loves gardening and eco-friendly products, brother=tech-savvy
========================
```

### Tool State Updates
Tools automatically maintain context consistency:
- Add/remove items updates cart and total
- Budget checking prevents overspending
- All changes reflected in next conversation turn

## Usage

```bash
# Test the agent
php artisan vizra:chat shopping_assistant

# Try these conversation patterns:
# 1. Set budget: "My budget is $150"
# 2. Add preferences: "I like eco-friendly brands"  
# 3. Add items: "Add a plant pot for $20"
# 4. Check status: "What's in my cart?"
# 5. Continue shopping with context awareness
```

## Learning Points

1. **Context State**: Use `getState()` and `setState()` for session persistence
2. **Lifecycle Hooks**: Inject context via `getInstructionsWithMemory()`, extract insights in `afterLlmResponse()`
3. **Tool Integration**: Tools can read and update context seamlessly
4. **Dynamic Prompts**: Include current state in every LLM interaction
5. **LLM-Structured Output**: Let the LLM structure its own context data in JSON format
6. **Smart Extraction**: LLM understands context better than regex patterns
7. **Maintainable Code**: No complex regex patterns to maintain

## Key Innovation: JSON-Structured Context

This example demonstrates a sophisticated approach where the LLM structures its own output for context management:

- **More Intelligent**: LLM interprets "my mom loves plants" as gardening preference
- **More Robust**: Works with any phrasing or conversation style  
- **More Maintainable**: No regex patterns to debug or update
- **More Accurate**: LLM infers context that regex would miss
- **More Extensible**: Easy to add new context fields

This pattern can be adapted for many use cases: project management, customer support, educational tutoring, or any application requiring stateful conversations with intelligent context extraction.