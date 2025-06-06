# Laravel Agent ADK 🤖 (Agent Development Kit)

**Laravel Agent ADK** is a powerful PHP package that simplifies building AI agents with Laravel. Think of it as your AI agent's foundation - it handles the complex infrastructure so you can focus on building intelligent systems that actually work.

## Table of Contents 📋

- [Quick Start](#quick-start-)
- [What's This All About?](#whats-this-all-about-)
- [Why Choose Laravel Agent ADK?](#why-choose-laravel-agent-adk-)
- [Requirements](#requirements-)
- [Installation & Setup](#installation--setup-)
- [Core Features](#core-features-)
- [Building Your First Agent](#building-your-first-agent-)
- [Memory System](#memory-system-)
- [Tracing & Debugging](#tracing--debugging-)
- [Vector Memory & RAG](#vector-memory--rag-)
- [Advanced Features](#advanced-features-)
  - [Tool System](#tool-system)
  - [Sub-Agent Delegation](#sub-agent-delegation)
  - [Generation Parameters](#generation-parameters)
  - [Streaming Responses](#streaming-responses)
  - [Event System](#event-system)
  - [Error Handling](#error-handling)
- [Evaluations](#evaluations)
- [Configuration](#configuration)
- [Security Best Practices](#security-best-practices)
- [Performance Considerations](#performance-considerations)
- [Testing](#testing)
- [Deployment](#deployment)
- [Troubleshooting](#troubleshooting-)
- [Contributing](#contributing-)

## Quick Start ⚡

Get up and running in 5 minutes:

```bash
# Install the package
composer require aaronlumsden/laravel-agent-adk

# Set up everything
php artisan agent:install

# Run migrations
php artisan migrate

# Add your API key to .env
echo "OPENAI_API_KEY=your_key_here" >> .env

# Create your first agent
php artisan agent:make:agent ChatBot

# Test it immediately
php artisan agent:chat chat_bot
```

## What's This All About? 🤔

AI agents are autonomous digital assistants that can think, decide, and take action. With Laravel Agent ADK, you get to build these intelligent helpers using familiar Laravel patterns and without the typical AI integration headaches.

**Key Capabilities:**

- **Smart Conversation Management**: Automatic context and history tracking
- **Tool Integration**: Let agents use APIs, databases, and external services
- **Sub-Agent Delegation**: Build hierarchical agent systems with task specialization
- **Multi-LLM Support**: OpenAI, Anthropic, Google Gemini through [Prism-PHP](https://prismphp.com/)
- **Execution Tracing**: Comprehensive debugging and performance analysis
- **Quality Assurance**: Built-in evaluation system with LLM-as-a-Judge
- **Laravel Native**: Events, service providers, Artisan commands - it all just works

## Why Choose Laravel Agent ADK? 🌟

| Feature                  | Laravel Agent ADK | DIY Approach         |
| ------------------------ | ----------------- | -------------------- |
| **Setup Time**           | 5 minutes         | Hours/Days           |
| **State Management**     | Automatic         | Manual complexity    |
| **Multi-LLM Support**    | Built-in          | Custom integration   |
| **Tool System**          | Declarative       | Imperative coding    |
| **Sub-Agent Delegation** | Built-in          | Complex architecture |
| **Execution Tracing**    | Visual debugging  | Custom logging       |
| **Quality Testing**      | LLM evaluations   | Manual testing       |
| **Laravel Integration**  | Native            | Custom glue code     |

## Requirements 📋

- **PHP**: 8.1 or higher
- **Laravel**: 10.0 or higher
- **LLM Provider**: At least one API key for:
  - OpenAI (GPT models)
  - Anthropic (Claude models)
  - Google (Gemini models)

## Installation & Setup 🚀

### 1. Install the Package

```bash
composer require aaronlumsden/laravel-agent-adk
```

### 2. Initialize the Package

```bash
php artisan agent:install
```

This command:

- Publishes the configuration file to `config/agent-adk.php`
- Creates database migrations for sessions and context storage
- Sets up the directory structure

### 3. Run Migrations

```bash
php artisan migrate
```

### 4. Configure Your Environment

Add your LLM API keys to `.env`:

```dotenv
# OpenAI Configuration
OPENAI_API_KEY=sk-your-openai-key-here
OPENAI_URL=https://api.openai.com/v1

# Anthropic Configuration
ANTHROPIC_API_KEY=sk-ant-your-anthropic-key

# Google Gemini Configuration
GEMINI_API_KEY=your-gemini-key-here

# Package Defaults
AGENT_ADK_DEFAULT_PROVIDER=openai
AGENT_ADK_DEFAULT_MODEL=gpt-4o
AGENT_ADK_DEFAULT_TEMPERATURE=0.7
```

## Core Features ✨

- **🏗️ Class-Based Agents**: Extend `BaseLlmAgent` with full IDE support
- **🎨 Fluent Builder**: Quick agent creation with `Agent::define()`
- **🔧 Tool System**: Declarative tool definitions with automatic parameter validation
- **🤖 Sub-Agent Delegation**: Hierarchical agent systems with task specialization
- **📚 Conversation Memory**: Automatic context and history management
- **🧠 Vector Memory & RAG**: Semantic search and retrieval-augmented generation
- **🌐 Multi-Provider**: OpenAI, Anthropic, Gemini support via Prism-PHP
- **🔍 Execution Tracing**: Visual debugging with hierarchical span tracking
- **⚡ Streaming Responses**: Real-time streaming for enhanced user experience
- **🎯 Smart Routing**: Automatic tool selection and execution
- **📊 Quality Assurance**: Built-in evaluation framework
- **⚡ Performance**: Optimized for production workloads
- **🔒 Security**: Input validation and sanitization built-in

## Building Your First Agent 🛠️

### 1. Create the Agent Class

```bash
php artisan agent:make:agent CustomerSupportAgent
```

This generates `app/Agents/CustomerSupportAgent.php`:

```php
namespace App\Agents;

use AaronLumsden\LaravelAgentADK\Agents\BaseLlmAgent;
use AaronLumsden\LaravelAgentADK\System\AgentContext;

class CustomerSupportAgent extends BaseLlmAgent
{
    protected string $name = 'customer_support';
    protected string $description = 'Helpful customer service assistant';

    protected string $instructions = 'You are a friendly customer service agent. Be helpful, professional, and concise. Always ask clarifying questions when needed.';

    protected string $model = 'gpt-4o';
    protected ?float $temperature = 0.3; // Lower temperature for consistency
    protected ?int $maxTokens = 500;

    protected function registerTools(): array
    {
        return [
            \App\Tools\OrderLookupTool::class,
            \App\Tools\RefundProcessorTool::class,
        ];
    }

    // Optional: Customize behavior with hooks
    public function beforeLlmCall(array $inputMessages, AgentContext $context): array
    {
        // Add customer context if available
        if ($customerId = $context->getState('customer_id')) {
            $context->setState('customer_tier', $this->getCustomerTier($customerId));
        }

        return $inputMessages;
    }

    private function getCustomerTier(string $customerId): string
    {
        // Your business logic here
        return 'premium';
    }
}
```

### 2. Register Your Agent

In `app/Providers/AppServiceProvider.php`:

```php
use AaronLumsden\LaravelAgentADK\Facades\Agent;
use App\Agents\CustomerSupportAgent;

public function boot(): void
{
    Agent::build(CustomerSupportAgent::class)->register();

    // Or create simple agents on-the-fly
    Agent::define('greeter')
         ->description('Friendly greeting agent')
         ->instructions('Greet users warmly and ask how you can help. Keep it under 30 words.')
         ->model('gpt-4o-mini')
         ->temperature(0.8)
         ->register();
}
```

### 3. Use Your Agent

```php
use AaronLumsden\LaravelAgentADK\Facades\Agent;

// In a controller
public function chat(Request $request)
{
    $input = $request->validated()['message'];
    $sessionId = $request->session()->getId();

    try {
        $response = Agent::run('customer_support', $input, $sessionId);
        return response()->json(['reply' => $response]);
    } catch (\Exception $e) {
        return response()->json(['error' => 'Service temporarily unavailable'], 503);
    }
}

// Or test directly in terminal
// php artisan agent:chat customer_support
```

## Memory System 🧠

Laravel Agent ADK includes a comprehensive memory system that enables agents to maintain long-term knowledge across multiple conversations. The memory system consists of two complementary components:

- **Sessions**: Short-term conversation threads for individual interactions
- **Memory**: Long-term knowledge that spans an agent's entire lifespan

### Understanding Memory vs Sessions

**Sessions** represent individual conversation threads:

- Store messages and context for a single interaction
- Automatically cleaned up after a period of inactivity
- Focus on immediate conversation flow

**Memory** represents persistent agent knowledge:

- Stores learned insights, facts, and summaries across all conversations
- Persists indefinitely to build agent expertise over time
- Tracks total session count and key patterns

### Memory Components

#### 1. Memory Summary

A high-level description of the agent's knowledge and capabilities:

```php
// Automatically managed or manually updated
$memoryManager->updateSummary('customer_support',
    'Specialized in billing and technical support with expertise in payment processing and account management.'
);
```

#### 2. Key Learnings

Important insights learned from conversations:

```php
// Add learnings from agent interactions
$memoryManager->addLearning('customer_support',
    'Users prefer quick, direct answers for billing questions'
);

$memoryManager->addLearning('customer_support',
    'Complex technical issues require step-by-step guidance'
);
```

#### 3. Facts Database

Structured knowledge about users, preferences, and domain-specific information:

```php
// Store facts about users or domain knowledge
$memoryManager->updateMemoryData('customer_support', [
    'primary_customer_segment' => 'small_business',
    'common_issues' => ['billing', 'account_access', 'feature_requests'],
    'preferred_communication_style' => 'professional_friendly'
]);
```

### Using the Memory Tool

Agents can manage their own memory through the built-in `MemoryTool`:

```php
class CustomerSupportAgent extends BaseLlmAgent
{
    protected function registerTools(): array
    {
        return [
            \AaronLumsden\LaravelAgentADK\Tools\MemoryTool::class,
            // ... other tools
        ];
    }
}
```

The Memory Tool provides these actions:

#### Get Memory Context

```php
// Agent can retrieve its current memory state
[
    'action' => 'get_context'
]
```

#### Add Learning

```php
// Agent can add new insights
[
    'action' => 'add_learning',
    'content' => 'Customers respond better to empathetic language when frustrated'
]
```

#### Add Facts

```php
// Agent can store factual information
[
    'action' => 'add_fact',
    'key' => 'billing_system_version',
    'value' => 'v2.1.4'
]
```

#### Get Conversation History

```php
// Agent can review past conversations
[
    'action' => 'get_history',
    'limit' => 20  // optional, defaults to 50
]
```

### Memory Integration with State

Memory automatically integrates with the agent's context:

```php
// Memory context is automatically included when loading agent state
$response = Agent::run('customer_support', 'How can I help?', 'session-123');

// The agent receives memory context like:
// "Based on your memory: You specialize in billing support and have learned
//  that users prefer quick responses. You know the billing system is v2.1.4..."
```

### Memory Events

Listen for memory updates in your application:

```php
// Listen for memory changes
Event::listen(MemoryUpdated::class, function ($event) {
    $memory = $event->memory;
    $session = $event->session; // nullable
    $updateType = $event->updateType; // 'learning', 'fact', 'summary'

    // Log important memory updates, trigger notifications, etc.
    Log::info("Agent {$memory->agent_name} learned: {$updateType}");
});
```

### Memory Management

#### Programmatic Management

```php
use AaronLumsden\LaravelAgentADK\Services\MemoryManager;

$memoryManager = app(MemoryManager::class);

// Get agent's memory context
$context = $memoryManager->getMemoryContextArray('customer_support');

// Add learning
$memoryManager->addLearning('customer_support', 'New insight');

// Update facts
$memoryManager->updateMemoryData('customer_support', ['key' => 'value']);

// Update summary
$memoryManager->updateSummary('customer_support', 'Updated description');
```

#### Session Cleanup

```php
// Clean up old sessions while preserving memory
$deletedCount = $memoryManager->cleanupOldSessions('customer_support', 30); // 30 days
```

#### Conversation History

```php
// Get conversation history across sessions
$history = $memoryManager->getConversationHistory('customer_support', 100);
```

### Manual Memory Management

All memory management methods support an optional `$userId` parameter for user-specific memories:

```php
use AaronLumsden\LaravelAgentADK\Services\MemoryManager;

$memoryManager = app(MemoryManager::class);

// Basic usage - applies to all users of the agent
$memoryManager->addLearning('customer_support', 'Always greet customers warmly');
$memoryManager->updateMemoryData('sales_agent', ['product_knowledge' => 'updated']);
$memoryManager->addFact('support_agent', 'system_version', 'v2.1.4');
$memoryManager->updateSummary('billing_agent', 'Specialized in payment processing');

// User-specific usage - applies only to specific user
$userId = 123;
$memoryManager->addLearning('customer_support', 'User prefers email over phone', $userId);
$memoryManager->updateMemoryData('sales_agent', ['budget' => 5000], $userId);
$memoryManager->addFact('support_agent', 'preferred_language', 'Spanish', $userId);
$memoryManager->updateSummary('billing_agent', 'VIP customer with premium support', $userId);

// Retrieve memory context (with optional user ID)
$globalContext = $memoryManager->getMemoryContext('customer_support');
$userContext = $memoryManager->getMemoryContext('customer_support', $userId);
```

### Manual Memory Management Examples

#### User Onboarding Example

```php
// When a user signs up or provides preferences
use AaronLumsden\LaravelAgentADK\Services\MemoryManager;

class UserController extends Controller
{
    public function updatePreferences(Request $request)
    {
        $memoryManager = app(MemoryManager::class);
        $agentName = 'customer_support';

        // Store user preferences in agent memory
        $memoryManager->updateMemoryData($agentName, [
            'user_' . $request->user()->id . '_communication_style' => $request->input('communication_style'),
            'user_' . $request->user()->id . '_preferred_language' => $request->input('language', 'English'),
            'user_' . $request->user()->id . '_account_type' => $request->user()->account_type,
            'user_' . $request->user()->id . '_timezone' => $request->input('timezone'),
        ]);

        // Add a learning about user behavior patterns
        $memoryManager->addLearning($agentName,
            "User {$request->user()->email} prefers {$request->input('communication_style')} communication style"
        );

        return response()->json(['status' => 'preferences_saved']);
    }
}
```

#### Post-Interaction Learning

```php
// After successful customer interaction
public function recordSuccessfulInteraction($agentName, $sessionId, $resolutionType)
{
    $memoryManager = app(MemoryManager::class);

    // Record what worked well
    $memoryManager->addLearning($agentName,
        "Successfully resolved {$resolutionType} issue using step-by-step guidance"
    );

    // Update statistics
    $currentStats = $memoryManager->getMemoryContextArray($agentName)['facts'];
    $successCount = ($currentStats['successful_resolutions'] ?? 0) + 1;

    $memoryManager->updateMemoryData($agentName, [
        'successful_resolutions' => $successCount,
        'last_successful_resolution' => now()->toISOString(),
        'most_effective_approach' => $resolutionType,
    ]);
}
```

#### Domain Knowledge Updates

```php
// When system updates or new policies are implemented
public function updateSystemKnowledge()
{
    $memoryManager = app(MemoryManager::class);

    // Update all customer support agents with new system info
    $agentNames = ['customer_support', 'billing_support', 'technical_support'];

    foreach ($agentNames as $agentName) {
        $memoryManager->updateMemoryData($agentName, [
            'system_version' => 'v3.2.1',
            'new_features' => ['automated_refunds', 'instant_chat_transfer', 'priority_queuing'],
            'policy_update_date' => now()->toDateString(),
            'knowledge_base_url' => 'https://internal.company.com/kb/v3.2.1',
        ]);

        $memoryManager->addLearning($agentName,
            'System updated to v3.2.1 with new automated refund capabilities - customers can now get instant refunds for purchases under $50'
        );
    }
}
```

#### Quick Memory Usage Example

Here's how to quickly use memory in your application without complex hooks:

```php
// In a controller or service
class ChatController extends Controller
{
    public function handleChat(Request $request)
    {
        $memoryManager = app(MemoryManager::class);
        $agentName = 'customer_support';
        $userId = $request->user()->id;

        // Before running the agent, update any relevant memory
        if ($request->has('feedback')) {
            $memoryManager->addLearning($agentName,
                "User feedback: {$request->input('feedback')}",
                $userId
            );
        }

        // Run the agent (memory is automatically included)
        $response = Agent::run($agentName, $request->input('message'), $request->session()->getId());

        // After the interaction, learn from it
        if ($response && str_contains(strtolower($response), 'resolved')) {
            $memoryManager->addLearning($agentName,
                'Successfully resolved user issue',
                $userId
            );
        }

        return response()->json(['reply' => $response]);
    }
}
```

### Automatic Agent Memory Usage

#### Simple Memory Integration

Here's the simplest way to add memory to your agent:

```php
class SimpleAgent extends BaseLlmAgent
{
    protected string $name = 'simple_agent';
    protected string $instructions = 'You are a helpful assistant.';

    // Automatically load memory before each LLM call
    protected function beforeLlmCall(array $inputMessages, AgentContext $context): array
    {
        $memoryManager = app(MemoryManager::class);

        // Get memory context (with optional user ID)
        $userId = $context->getState('user_id'); // optional
        $memoryContext = $memoryManager->getMemoryContext($this->getName(), $userId);

        // Store in context - getInstructionsWithMemory() will automatically use this
        $context->setState('memory_context', $memoryContext);

        return $inputMessages;
    }

    // Learn from successful interactions
    protected function afterLlmResponse(Response $response, AgentContext $context): mixed
    {
        $responseText = $response->text ?? '';

        // Simple learning: if user says thanks, remember the approach worked
        if (str_contains(strtolower($responseText), 'thank')) {
            $memoryManager = app(MemoryManager::class);
            $userId = $context->getState('user_id'); // optional

            $memoryManager->addLearning($this->getName(),
                'User appreciated this type of response',
                $userId
            );
        }

        return $response;
    }
}
```

The key points:

1. **beforeLlmCall**: Load memory and store in `$context->setState('memory_context', $memoryContext)`
2. **getInstructionsWithMemory()**: Automatically injects memory into agent instructions
3. **afterLlmResponse**: Learn from interactions and update memory
4. **Optional $userId**: Add as the last parameter to make memories user-specific

#### Using Memory in beforeLlmCall Hook

The `beforeLlmCall` hook is a powerful lifecycle method in `BaseLlmAgent` that allows you to automatically inject memory context before each LLM interaction. Here are comprehensive examples:

##### Example 1: Customer Support Agent with Memory-Enhanced Context

```php
class CustomerSupportAgent extends BaseLlmAgent
{
    protected string $name = 'customer_support';
    protected string $instructions = 'You are a helpful customer service agent...';

    /**
     * Automatically enhance conversations with relevant memory before LLM calls
     */
    public function beforeLlmCall(array $inputMessages, AgentContext $context): array
    {
        $memoryManager = app(MemoryManager::class);

        // Get current user info from context or session
        $userId = $context->getState('user_id');
        $currentIssue = $this->extractIssueType($context->getLastUserMessage());

        // Retrieve relevant memory context
        $memoryContext = $memoryManager->getMemoryContext($this->getName());

        // Store memory context in agent context state for use in instructions
        $context->setState('memory_context', $memoryContext);

        // Extract user-specific facts
        $userFacts = [];
        if ($memoryContext && isset($memoryContext['facts'])) {
            foreach ($memoryContext['facts'] as $key => $value) {
                if (str_contains($key, "user_{$userId}_")) {
                    $userFacts[$key] = $value;
                }
            }
        }

        // Store personalization data in context for instructions
        if (!empty($userFacts)) {
            $personalizationData = [
                'communication_style' => $userFacts["user_{$userId}_communication_style"] ?? null,
                'account_type' => $userFacts["user_{$userId}_account_type"] ?? null,
                'preferred_language' => $userFacts["user_{$userId}_preferred_language"] ?? null,
            ];

            $context->setState('user_personalization', array_filter($personalizationData));
        }

        // Add relevant learnings for this issue type
        if ($memoryContext && isset($memoryContext['key_learnings'])) {
            $relevantLearnings = collect($memoryContext['key_learnings'])
                ->filter(fn($learning) => str_contains(strtolower($learning), strtolower($currentIssue)))
                ->take(3)
                ->values()
                ->toArray();

            $context->setState('relevant_learnings', $relevantLearnings);
        }

        // Add recent successful approaches
        if ($memoryContext && isset($memoryContext['facts'])) {
            $recentSuccesses = [];
            foreach ($memoryContext['facts'] as $key => $value) {
                if (str_contains($key, 'last_success_' . $currentIssue)) {
                    $recentSuccesses[] = $value;
                }
            }

            $context->setState('recent_successes', array_slice($recentSuccesses, 0, 2));
        }

        return $inputMessages;
    }

    /**
     * Automatically extract learnings after successful interactions
     */
    public function afterLlmResponse(Response $response, AgentContext $context): mixed
    {
        $responseText = $response->text ?? '';

        // Auto-extract learnings based on response patterns
        if ($this->isSuccessfulResolution($responseText)) {
            $memoryManager = app(MemoryManager::class);
            $issueType = $this->extractIssueType($context->getLastUserMessage());

            // Add learning about successful resolution approach
            $memoryManager->addLearning($this->getName(),
                "Successfully resolved {$issueType} by " . $this->extractApproach($responseText)
            );

            // Update success metrics
            $memoryManager->updateMemoryData($this->getName(), [
                'last_success_' . $issueType => now()->toISOString(),
                'total_' . $issueType . '_resolutions' => ($this->getSuccessCount($issueType) + 1),
                'most_effective_' . $issueType . '_approach' => $this->extractApproach($responseText),
            ]);
        }

        // Extract user satisfaction indicators
        $lastUserMessage = $context->getLastUserMessage();
        if ($this->detectUserSatisfaction($lastUserMessage)) {
            $memoryManager = app(MemoryManager::class);
            $memoryManager->addLearning($this->getName(),
                "User expressed satisfaction with response approach: " . $this->extractResponseType($responseText)
            );
        }

        return $response;
    }


    private function extractIssueType(string $input): string
    {
        $patterns = [
            'billing' => ['bill', 'charge', 'payment', 'invoice', 'refund', 'subscription'],
            'technical' => ['error', 'bug', 'broken', 'not working', 'issue', 'crash'],
            'account' => ['login', 'password', 'access', 'profile', 'settings', 'account'],
            'shipping' => ['delivery', 'shipping', 'tracking', 'order', 'package'],
        ];

        $input = strtolower($input);
        foreach ($patterns as $type => $keywords) {
            foreach ($keywords as $keyword) {
                if (str_contains($input, $keyword)) {
                    return $type;
                }
            }
        }

        return 'general';
    }

    private function isSuccessfulResolution(string $response): bool
    {
        $successIndicators = [
            'resolved', 'fixed', 'completed', 'processed', 'approved',
            'should work now', 'try again', 'all set', 'problem solved'
        ];

        $response = strtolower($response);
        foreach ($successIndicators as $indicator) {
            if (str_contains($response, $indicator)) {
                return true;
            }
        }

        return false;
    }

    private function extractApproach(string $response): string
    {
        if (str_contains($response, 'step')) return 'providing step-by-step instructions';
        if (str_contains($response, 'escalat')) return 'escalating to specialist';
        if (str_contains($response, 'refund') || str_contains($response, 'credit')) return 'offering refund/credit';
        if (str_contains($response, 'reset') || str_contains($response, 'restart')) return 'system reset approach';
        if (str_contains($response, 'guide') || str_contains($response, 'tutorial')) return 'providing detailed guidance';

        return 'standard support protocol';
    }

    private function detectUserSatisfaction(string $input): bool
    {
        $satisfactionIndicators = ['thank you', 'thanks', 'perfect', 'great', 'excellent', 'helpful', 'solved'];
        $input = strtolower($input);

        foreach ($satisfactionIndicators as $indicator) {
            if (str_contains($input, $indicator)) {
                return true;
            }
        }

        return false;
    }

    private function extractResponseType(string $response): string
    {
        if (str_contains($response, 'detailed') || str_contains($response, 'thorough')) return 'detailed explanation';
        if (str_contains($response, 'quick') || str_contains($response, 'simple')) return 'concise solution';
        if (str_contains($response, 'alternative') || str_contains($response, 'option')) return 'multiple options';

        return 'standard response';
    }

    private function getSuccessCount(string $issueType): int
    {
        $memoryManager = app(MemoryManager::class);
        $memoryContext = $memoryManager->getMemoryContext($this->getName());
        return $memoryContext['facts']['total_' . $issueType . '_resolutions'] ?? 0;
    }
}
```

##### Example 2: Personal Assistant Agent with Advanced Memory Integration

```php
class PersonalAssistantAgent extends BaseLlmAgent
{
    protected string $name = 'personal_assistant';
    protected string $instructions = 'You are a personal assistant who adapts to user preferences...';

    /**
     * Inject personalized context and relationship memory using proper hooks
     */
    public function beforeLlmCall(array $inputMessages, AgentContext $context): array
    {
        $memoryManager = app(MemoryManager::class);
        $userId = $context->getState('user_id');

        // Get memory context using the actual service method
        $memoryContext = $memoryManager->getMemoryContext($this->getName());

        // Store memory data in context state for use in getInstructionsWithMemory()
        $context->setState('memory_context', $memoryContext);

        if ($memoryContext && isset($memoryContext['facts'])) {
            // 1. Communication Style Adaptation
            $communicationStyle = $memoryContext['facts']['communication_style'] ?? null;
            if ($communicationStyle) {
                $context->setState('communication_style', $communicationStyle);
            }

            // 2. Current Projects and Interests
            $currentProjects = [];
            foreach ($memoryContext['facts'] as $key => $value) {
                if (str_starts_with($key, 'current_project_')) {
                    $currentProjects[] = $value;
                }
            }
            $context->setState('current_projects', $currentProjects);

            // 3. Important Relationships and Context
            $relationships = [];
            foreach ($memoryContext['facts'] as $key => $value) {
                if (str_starts_with($key, 'relationship_')) {
                    $relationshipName = str_replace('relationship_', '', $key);
                    $relationships[$relationshipName] = $value;
                }
            }
            $context->setState('relationships', $relationships);

            // 4. Preferences and Constraints
            $preferences = [];
            foreach ($memoryContext['facts'] as $key => $value) {
                if (str_starts_with($key, 'preference_')) {
                    $prefName = str_replace('preference_', '', $key);
                    $preferences[$prefName] = $value;
                }
            }
            $context->setState('preferences', $preferences);
        }

        // 5. Recent Context and Follow-ups from learnings
        if ($memoryContext && isset($memoryContext['key_learnings'])) {
            $recentInteractions = collect($memoryContext['key_learnings'])
                ->filter(fn($learning) => str_contains($learning, 'follow up') || str_contains($learning, 'reminder'))
                ->take(3)
                ->values()
                ->toArray();

            $context->setState('recent_interactions', $recentInteractions);
        }

        return $inputMessages;
    }

    /**
     * Extract personal insights and update relationship context
     */
    public function afterLlmResponse(Response $response, AgentContext $context): mixed
    {
        $memoryManager = app(MemoryManager::class);
        $userId = $context->getState('user_id');
        $lastUserMessage = $context->getLastUserMessage();
        $responseText = $response->text ?? '';


        // Extract new project mentions
        if (preg_match('/working on (.+?)(?:\.|$)/i', $lastUserMessage, $matches)) {
            $project = trim($matches[1]);
            $memoryManager->updateMemoryData($this->getName(), [
                'current_project_' . md5($project) => $project,
                'project_start_' . md5($project) => now()->toDateString()
            ]);
        }

        // Extract relationship updates
        if (preg_match('/my (.+?) (?:is|was) (.+?)(?:\.|$)/i', $lastUserMessage, $matches)) {
            $person = trim($matches[1]);
            $context_info = trim($matches[2]);
            $memoryManager->updateMemoryData($this->getName(), [
                'relationship_' . strtolower($person) => $context_info
            ]);
        }

        // Extract preferences from user feedback
        if (str_contains($lastUserMessage, 'I prefer') || str_contains($lastUserMessage, 'I like')) {
            preg_match('/I (?:prefer|like) (.+?)(?:\.|$)/i', $lastUserMessage, $matches);
            if (!empty($matches[1])) {
                $preference = trim($matches[1]);
                $memoryManager->updateMemoryData($this->getName(), [
                    'preference_' . md5($preference) => $preference,
                    'preference_learned_at' => now()->toISOString()
                ]);
            }
        }

        // Add follow-up reminders if assistant suggested them
        if (str_contains($responseText, 'remind') || str_contains($responseText, 'follow up')) {
            $memoryManager->addLearning($this->getName(),
                "Reminder set: " . $this->extractReminderContext($responseText, $lastUserMessage)
            );
        }

        return $response;
    }

    private function extractReminderContext(string $response, string $userInput): string
    {
        // Extract what the reminder is about
        if (preg_match('/remind.+?(?:about|to) (.+?)(?:\.|$)/i', $response, $matches)) {
            return trim($matches[1]);
        }

        // Fallback to user input context
        return "Follow up on: " . Str::limit($userInput, 50);
    }
}
```

#### Memory-Aware Tool Usage

```php
class SmartWeatherTool implements ToolInterface
{
    public function execute(array $arguments, AgentContext $context): string
    {
        $memoryManager = app(MemoryManager::class);
        $agentName = $context->getState('agent_name');

        // Check if we know user's preferred units from memory
        $userId = $context->getState('user_id');
        $memoryContext = $memoryManager->getMemoryContext($agentName);

        $preferredUnits = 'metric'; // default
        $timezone = 'UTC'; // default

        if ($memoryContext && isset($memoryContext['facts'])) {
            $preferredUnits = $memoryContext['facts']["user_{$userId}_preferred_units"] ?? 'metric';
            $timezone = $memoryContext['facts']["user_{$userId}_timezone"] ?? 'UTC';
        }

        // Use memory to enhance the weather request
        $location = $arguments['location'];
        $units = $arguments['units'] ?? $preferredUnits;

        $weatherData = $this->fetchWeather($location, $units, $timezone);

        // Learn from user interactions with weather data
        if (isset($arguments['units']) && $arguments['units'] !== $preferredUnits) {
            $memoryManager->updateMemoryData($agentName, [
                "user_{$userId}_preferred_units" => $arguments['units']
            ]);
        }

        return $weatherData;
    }

    private function fetchWeather(string $location, string $units, string $timezone): string
    {
        // Implementation would call actual weather API
        return "Weather data for {$location} in {$units} units (timezone: {$timezone})";
    }
}
```

#### Automatic Memory Updates via Middleware

```php
class AgentMemoryMiddleware
{
    public function handle($request, Closure $next)
    {
        $response = $next($request);

        // Auto-update memory based on API interactions
        if ($request->is('api/agent/*') && $response->isSuccessful()) {
            $this->updateMemoryFromInteraction($request, $response);
        }

        return $response;
    }

    private function updateMemoryFromInteraction($request, $response)
    {
        $memoryManager = app(MemoryManager::class);
        $agentName = $request->route('agent');

        // Track API usage patterns
        $memoryManager->updateMemoryData($agentName, [
            'last_api_call' => now()->toISOString(),
            'total_api_calls' => $this->incrementApiCount($agentName),
            'most_recent_endpoint' => $request->path(),
        ]);

        // Learn from user feedback if provided
        if ($request->has('feedback') && $request->feedback === 'helpful') {
            $memoryManager->addLearning($agentName,
                "User found response helpful for query: " . Str::limit($request->input('query'), 100)
            );
        }
    }
}
```

### Best Practices

1. **Learning Extraction**: Encourage agents to extract learnings from successful interactions
2. **Fact Organization**: Structure facts logically with consistent key naming
3. **Memory Summaries**: Update summaries periodically to reflect agent evolution
4. **Privacy Considerations**: Avoid storing sensitive user data in long-term memory
5. **Memory Cleanup**: Implement regular cleanup of outdated facts and learnings

### Memory Integration Best Practices

Based on the actual implementation, here are key best practices for memory integration:

#### 1. Use Context State for Memory Data

```php
// In beforeLlmCall - store memory data in context state
$context->setState('memory_context', $memoryManager->getMemoryContext($this->getName()));
$context->setState('user_preferences', $userPreferences);

// The base agent will automatically use this in getInstructionsWithMemory()
```

#### 2. Hook Signatures Matter

```php
// Correct hook signatures (match the actual implementation)
public function beforeLlmCall(array $inputMessages, AgentContext $context): array
public function afterLlmResponse(Response $response, AgentContext $context): mixed
```

#### 3. Memory Service Method Usage

```php
// Use actual MemoryManager methods
$memoryManager->getMemoryContext($agentName);           // Returns memory object
$memoryManager->addLearning($agentName, $learning);     // Add new learning
$memoryManager->updateMemoryData($agentName, $facts);   // Update facts
$memoryManager->addFact($agentName, $key, $value);      // Add single fact
```

#### 4. Context Data Access

```php
// Access user messages correctly
$lastUserMessage = $context->getLastUserMessage();
$allMessages = $context->getMessages();

// Response object handling
$responseText = $response->text ?? '';
if ($response instanceof Response) {
    // Handle properly typed response
}
```

#### 5. Memory Context Flow

1. **beforeLlmCall**: Load memory → Store in context state
2. **getInstructionsWithMemory**: Automatically formats context state into system prompt
3. **LLM Call**: Enhanced instructions with memory context
4. **afterLlmResponse**: Extract insights → Update memory

This approach ensures memory context is seamlessly integrated without manual message manipulation.

## Tracing & Debugging 🔍

Laravel Agent ADK includes a comprehensive tracing system that automatically tracks agent execution, LLM calls, tool executions, and sub-agent delegations. This provides valuable insights for debugging, performance analysis, and understanding agent behavior.

### Key Features

- **Hierarchical Span Tracking**: Traces organized as trees showing parent-child relationships
- **Multiple Trace Types**: Agent execution, LLM calls, tool calls, and sub-agent delegations
- **Session Management**: Groups related traces by session ID for conversation tracking
- **Performance Metrics**: Duration, start/end times, and execution status
- **Error Tracking**: Captures exceptions and error states with detailed messages
- **CLI Visualization**: Tree view, table, and JSON output formats

### Configuration

Tracing is configured in `config/agent-adk.php`:

```php
'tracing' => [
    'enabled' => env('AGENT_TRACING_ENABLED', true),
    'table' => 'agent_trace_spans',
    'cleanup_days' => 30,
],
```

### Viewing Traces

Use the `agent:trace` command to visualize agent execution:

```bash
# View traces for a specific session
php artisan agent:trace session-123

# View in different formats
php artisan agent:trace session-123 --format=tree
php artisan agent:trace session-123 --format=table
php artisan agent:trace session-123 --format=json

# Show only errors
php artisan agent:trace session-123 --errors-only

# View with input/output details
php artisan agent:trace session-123 --show-input --show-output
```

### Example Trace Output

```
📊 Trace: 01JBXXX... (customer_support) [completed] 1.2s
├── 🤖 agent: customer_support [completed] 1.2s
│   ├── 🧠 llm_call: gpt-4o [completed] 800ms
│   ├── 🔧 tool_call: search_knowledge_base [completed] 300ms
│   └── 🧠 llm_call: gpt-4o [completed] 100ms
```

### Span Types

- **`agent_run`**: Top-level spans for entire agent executions
- **`llm_call`**: Individual calls to language models
- **`tool_call`**: Individual tool executions
- **`sub_agent_delegation`**: When one agent delegates to another

### Cleaning Up Old Traces

Automatically clean up old trace data to prevent database bloat:

```bash
# Clean up traces older than 30 days (default)
php artisan agent:trace:cleanup

# Specify custom retention period
php artisan agent:trace:cleanup --days=7

# Dry run to see what would be deleted
php artisan agent:trace:cleanup --dry-run

# Skip confirmation prompt
php artisan agent:trace:cleanup --force
```

### Programmatic Access

Access tracing data programmatically:

```php
use AaronLumsden\LaravelAgentADK\Services\Tracer;

$tracer = app(Tracer::class);

// Get spans for a session
$spans = $tracer->getSpansForSession('session-123');

// Get spans for a specific trace
$spans = $tracer->getSpansForTrace('01JBXXX...');

// Clean up old traces
$deleted = $tracer->cleanupOldTraces(30);
```

### Using TraceSpan Model

```php
use AaronLumsden\LaravelAgentADK\Models\TraceSpan;

// Query spans directly
$spans = TraceSpan::forSession('session-123')->get();

// Get error spans
$errors = TraceSpan::withStatus('error')->get();

// Get root spans (no parent)
$roots = TraceSpan::rootSpans()->get();
```

### Automatic Integration

Tracing is automatically integrated into `BaseLlmAgent` - no code changes required. All agent executions, LLM calls, tool calls, and sub-agent delegations are traced transparently.

For complete documentation, see the [TRACING.md](./TRACING.md) file.

## Vector Memory & RAG 🧠

Laravel Agent ADK includes a powerful Vector Memory system that enables Retrieval-Augmented Generation (RAG) workflows. This allows agents to store, search, and retrieve information using semantic similarity, making them capable of answering questions based on large knowledge bases.

### Key Features

- **Multi-Provider Embeddings**: OpenAI, Cohere, Ollama, and Gemini support
- **Flexible Storage**: Meilisearch, PostgreSQL + pgvector, or in-memory
- **Automatic Chunking**: Smart document splitting with configurable strategies
- **Semantic Search**: Find relevant content using meaning, not just keywords
- **RAG Integration**: Seamless context generation for agent responses
- **Laravel Native**: Built with Laravel patterns and conventions

### Quick Setup with Meilisearch

Meilisearch provides excellent vector search capabilities with built-in indexing and filtering. Here's how to set it up:

#### 1. Install Meilisearch

```bash
# Using Docker (recommended)
docker run -it --rm \
    -p 7700:7700 \
    -e MEILI_ENV='development' \
    -v $(pwd)/meili_data:/meili_data \
    getmeili/meilisearch:v1.6

# Or install locally
curl -L https://install.meilisearch.com | sh
./meilisearch
```

#### 2. Configure Environment Variables

Add to your `.env` file:

```env
# Vector Memory Configuration
AGENT_VECTOR_MEMORY_ENABLED=true
AGENT_VECTOR_DRIVER=meilisearch
AGENT_EMBEDDING_PROVIDER=openai

# Meilisearch Configuration
AGENT_MEILISEARCH_HOST=http://localhost:7700
AGENT_MEILISEARCH_API_KEY=your_master_key_here
AGENT_MEILISEARCH_INDEX_PREFIX=agent_vectors_

# OpenAI for Embeddings
OPENAI_API_KEY=sk-your-openai-api-key-here
AGENT_OPENAI_EMBEDDING_MODEL=text-embedding-3-small

# Document Chunking (optional)
AGENT_CHUNKING_STRATEGY=sentence
AGENT_CHUNK_SIZE=1000
AGENT_CHUNK_OVERLAP=200
```

#### 3. Run Database Migrations

```bash
php artisan migrate
```

#### 4. Test the Setup

```bash
# Store some content
php artisan vector:store document_assistant \
    --content="Laravel Agent ADK is a powerful framework for building AI agents with RAG capabilities" \
    --namespace=docs \
    --source=manual

# Search for content
php artisan vector:search document_assistant "AI agent framework" \
    --namespace=docs \
    --rag

# View statistics
php artisan vector:stats document_assistant --namespace=docs
```

### Using Vector Memory in Agents

#### Create a RAG-Enabled Agent

```php
namespace App\Agents;

use AaronLumsden\LaravelAgentADK\Agents\BaseLlmAgent;
use App\Tools\VectorMemoryTool;

class DocumentAssistantAgent extends BaseLlmAgent
{
    protected string $name = 'document_assistant';
    protected string $description = 'AI assistant with RAG capabilities';
    
    protected string $instructions = 'You are a knowledgeable assistant with access to stored documents. 
        When answering questions, first search your vector memory for relevant information, 
        then provide comprehensive answers based on the retrieved context.';

    protected function registerTools(): array
    {
        return [
            VectorMemoryTool::class,
        ];
    }
}
```

#### Register and Use the Agent

```php
// In AppServiceProvider
Agent::build(DocumentAssistantAgent::class)->register();

// Use the agent
$response = Agent::run('document_assistant', 
    'What is Laravel Agent ADK and what are its main features?', 
    'user-session-123'
);
```

### Vector Memory Operations

#### Store Documents

```php
use App\VectorMemory\Services\VectorMemoryManager;

$vectorMemory = app(VectorMemoryManager::class);

// Store a document with automatic chunking
$memories = $vectorMemory->addDocument(
    agentName: 'support_agent',
    content: $documentContent,
    metadata: [
        'title' => 'User Guide',
        'category' => 'documentation',
        'version' => '2.1'
    ],
    namespace: 'help_docs',
    source: 'user_guide.pdf'
);

echo "Stored {$memories->count()} chunks in vector memory";
```

#### Search and Retrieve

```php
// Semantic search
$results = $vectorMemory->search(
    agentName: 'support_agent',
    query: 'How do I configure authentication?',
    namespace: 'help_docs',
    limit: 5,
    threshold: 0.7
);

// Generate RAG context
$ragContext = $vectorMemory->generateRagContext(
    agentName: 'support_agent',
    query: 'authentication setup process',
    namespace: 'help_docs'
);

echo $ragContext['context']; // Formatted context for LLM
```

#### Use in Agents with VectorMemoryTool

The `VectorMemoryTool` provides agents with direct access to vector memory operations:

```php
// Agents can store content
$storePrompt = "Store this information: Laravel uses Eloquent ORM for database operations";

// Agents can search and answer
$queryPrompt = "What ORM does Laravel use?";

$response = Agent::run('document_assistant', $queryPrompt, 'session-123');
// Agent will automatically search vector memory and provide context-aware answers
```

### Command Line Management

#### Store Content

```bash
# From file
php artisan vector:store my_agent --file=/path/to/document.pdf --namespace=docs

# Direct content  
php artisan vector:store my_agent --content="Your content here" --source=manual

# With metadata
php artisan vector:store my_agent \
    --file=guide.md \
    --metadata='{"category":"guide","priority":"high"}'
```

#### Search Content

```bash
# Basic search
php artisan vector:search my_agent "search query" --namespace=docs

# Generate RAG context
php artisan vector:search my_agent "complex question" --rag --limit=10

# JSON output
php artisan vector:search my_agent "query" --json
```

#### Monitor Usage

```bash
# Agent statistics
php artisan vector:stats my_agent --namespace=docs --detailed

# Global statistics
php artisan vector:stats --detailed
```

### Alternative Storage Options

#### PostgreSQL + pgvector

For high-performance production workloads:

```env
AGENT_VECTOR_DRIVER=pgvector
DB_CONNECTION=pgsql
# Ensure PostgreSQL has pgvector extension installed
```

#### In-Memory (Development)

For development and testing:

```env
AGENT_VECTOR_DRIVER=in_memory
AGENT_MEMORY_PERSISTENCE=true
```

### Configuration Options

#### Embedding Providers

```env
# OpenAI (recommended for quality)
AGENT_EMBEDDING_PROVIDER=openai
OPENAI_API_KEY=sk-your-key
AGENT_OPENAI_EMBEDDING_MODEL=text-embedding-3-small

# Cohere (cost-effective)
AGENT_EMBEDDING_PROVIDER=cohere
COHERE_API_KEY=your-cohere-key

# Ollama (free, local)
AGENT_EMBEDDING_PROVIDER=ollama
OLLAMA_URL=http://localhost:11434
```

#### Search Settings

```env
AGENT_SIMILARITY_THRESHOLD=0.7    # Minimum similarity score (0.0-1.0)
AGENT_MAX_SEARCH_RESULTS=5        # Maximum results per search
AGENT_DISTANCE_METRIC=cosine      # cosine, euclidean, dot_product
```

#### RAG Context Generation

```env
AGENT_RAG_MAX_CONTEXT_LENGTH=4000  # Maximum context characters
AGENT_RAG_INCLUDE_METADATA=true    # Include metadata in context
```

### Performance Tips

1. **Choose the right embedding model**: `text-embedding-3-small` offers good performance/cost balance
2. **Optimize chunk size**: 500-1000 characters work well for most content
3. **Use appropriate similarity thresholds**: Start with 0.7, adjust based on results
4. **Leverage metadata**: Use structured metadata for better filtering
5. **Monitor costs**: Track embedding generation usage for commercial providers

### Demo and Examples

Run the included demo to see RAG in action:

```bash
php demo_rag_capabilities.php
```

This demonstrates:
- Document storage with automatic chunking
- Semantic search across stored content  
- RAG-powered question answering
- Cross-namespace searching
- Real-world usage patterns

For detailed setup instructions and advanced configuration, see `VECTOR_MEMORY_SETUP.md` in the project root.

## Advanced Features 🚀

### Tool System

Tools extend your agent's capabilities beyond text generation. Here's a real-world example:

```bash
php artisan agent:make:tool WeatherApiTool
```

```php
namespace App\Tools;

use AaronLumsden\LaravelAgentADK\Contracts\ToolInterface;
use AaronLumsden\LaravelAgentADK\System\AgentContext;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WeatherApiTool implements ToolInterface
{
    public function definition(): array
    {
        return [
            'name' => 'get_weather',
            'description' => 'Get current weather conditions for any city worldwide',
            'parameters' => [
                'type' => 'object',
                'properties' => [
                    'city' => [
                        'type' => 'string',
                        'description' => 'City name (e.g., "London" or "New York, NY")',
                    ],
                    'country' => [
                        'type' => 'string',
                        'description' => 'Country code (optional, e.g., "GB", "US")',
                    ],
                    'units' => [
                        'type' => 'string',
                        'enum' => ['metric', 'imperial'],
                        'description' => 'Temperature units',
                        'default' => 'metric'
                    ]
                ],
                'required' => ['city'],
            ],
        ];
    }

    public function execute(array $arguments, AgentContext $context): string
    {
        $city = $arguments['city'];
        $country = $arguments['country'] ?? '';
        $units = $arguments['units'] ?? 'metric';

        // Build location string
        $location = $country ? "{$city},{$country}" : $city;

        try {
            $response = Http::timeout(10)->get('https://api.openweathermap.org/data/2.5/weather', [
                'q' => $location,
                'appid' => config('services.openweather.key'),
                'units' => $units
            ]);

            if (!$response->successful()) {
                return "Sorry, I couldn't fetch weather data for {$city}. Please check the city name.";
            }

            $data = $response->json();

            return json_encode([
                'location' => $data['name'] . ', ' . $data['sys']['country'],
                'temperature' => $data['main']['temp'] . '°' . ($units === 'metric' ? 'C' : 'F'),
                'description' => ucfirst($data['weather'][0]['description']),
                'humidity' => $data['main']['humidity'] . '%',
                'wind_speed' => $data['wind']['speed'] . ($units === 'metric' ? ' m/s' : ' mph')
            ]);

        } catch (\Exception $e) {
            Log::error('Weather API error', ['error' => $e->getMessage(), 'city' => $city]);
            return "I'm having trouble accessing weather data right now. Please try again later.";
        }
    }
}
```

### Sub-Agent Delegation

Build sophisticated hierarchical agent systems where parent agents can delegate specialized tasks to child agents (sub-agents). This enables complex workflows with task specialization and modular agent architectures.

#### Creating Sub-Agents

Sub-agents are regular agents that can be used independently or as children of parent agents:

```bash
# Create the specialized sub-agents
php artisan agent:make:agent TechnicalSupportAgent
php artisan agent:make:agent BillingSupportAgent
php artisan agent:make:agent OrderSpecialistAgent

# Create the parent agent
php artisan agent:make:agent CustomerServiceAgent
```

#### Registering Sub-Agents

In your parent agent, register sub-agents by implementing the `registerSubAgents()` method:

```php
namespace App\Agents;

use AaronLumsden\LaravelAgentADK\Agents\BaseLlmAgent;
use App\Agents\TechnicalSupportAgent;
use App\Agents\BillingSupportAgent;
use App\Agents\OrderSpecialistAgent;

class CustomerServiceAgent extends BaseLlmAgent
{
    protected string $name = 'customer_service';
    protected string $instructions = 'You are a customer service manager. You can handle general inquiries or delegate to specialists when needed.';

    /**
     * Register sub-agents that this agent can delegate to
     */
    protected function registerSubAgents(): array
    {
        return [
            'technical_support' => TechnicalSupportAgent::class,
            'billing_support' => BillingSupportAgent::class,
            'order_specialist' => OrderSpecialistAgent::class,
        ];
    }

    protected function registerTools(): array
    {
        return [
            // Your regular tools here
        ];
    }
}
```

#### How Delegation Works

1. **Automatic Tool Creation**: When sub-agents are registered, the parent agent automatically gains access to a `delegate_to_sub_agent` tool
2. **LLM Awareness**: The parent agent's instructions are enhanced to inform the LLM about available sub-agents
3. **Intelligent Delegation**: The LLM can choose to delegate tasks by calling the delegation tool
4. **Context Isolation**: Each sub-agent runs in its own isolated context with optional context summary from the parent

#### Example Usage

```php
use AaronLumsden\LaravelAgentADK\Facades\Agent;

// The parent agent can handle requests directly
$response = Agent::run('customer_service', "Hello, I need help", 'session-123');

// Or it can intelligently delegate to sub-agents
$response = Agent::run('customer_service',
    "My billing is wrong and I was overcharged last month",
    'session-123'
);
// The LLM will likely delegate this to the billing_support sub-agent

$response = Agent::run('customer_service',
    "My internet connection keeps dropping",
    'session-123'
);
// The LLM will likely delegate this to the technical_support sub-agent
```

#### Nested Sub-Agents

Sub-agents can have their own sub-agents, creating multi-level hierarchies:

```php
class TechnicalSupportAgent extends BaseLlmAgent
{
    protected string $name = 'technical_support';

    protected function registerSubAgents(): array
    {
        return [
            'network_specialist' => NetworkSpecialistAgent::class,
            'software_specialist' => SoftwareSpecialistAgent::class,
        ];
    }
}
```

#### Benefits

- **Task Specialization**: Each agent can focus on specific domains with optimized instructions and tools
- **Modular Architecture**: Agents can be developed, tested, and maintained independently
- **Scalable Complexity**: Handle complex workflows by breaking them into specialized components
- **Context Isolation**: Sub-agents operate independently, preventing context pollution
- **Reusability**: Sub-agents can be shared across multiple parent agents

#### Monitoring Delegation Events

The system dispatches a `TaskDelegated` event whenever a task is delegated to a sub-agent, providing comprehensive monitoring capabilities:

```bash
php artisan make:listener TrackTaskDelegation --event="AaronLumsden\LaravelAgentADK\Events\TaskDelegated"
```

```php
namespace App\Listeners;

use AaronLumsden\LaravelAgentADK\Events\TaskDelegated;
use Illuminate\Support\Facades\Log;

class TrackTaskDelegation
{
    public function handle(TaskDelegated $event): void
    {
        // Log delegation details
        Log::info('Task delegated', [
            'parent_agent' => $event->parentAgentName,
            'sub_agent' => $event->subAgentName,
            'task_input' => $event->taskInput,
            'context_summary' => $event->contextSummary,
            'delegation_depth' => $event->delegationDepth,
            'parent_session' => $event->parentContext->getSessionId(),
            'sub_session' => $event->subAgentContext->getSessionId(),
        ]);

        // Monitor for potential issues
        if ($event->delegationDepth > 3) {
            Log::warning('Deep delegation detected', [
                'depth' => $event->delegationDepth,
                'chain' => "{$event->parentAgentName} -> {$event->subAgentName}"
            ]);
        }

        // Track metrics for analytics
        $this->trackDelegationMetrics($event);
    }

    private function trackDelegationMetrics(TaskDelegated $event): void
    {
        // Send to your analytics service
        // Track delegation patterns, success rates, etc.
    }
}
```

**Event Properties:**

- `$parentContext` - The parent agent's context
- `$subAgentContext` - The newly created sub-agent context
- `$parentAgentName` - Name of the delegating agent
- `$subAgentName` - Name of the receiving sub-agent
- `$taskInput` - The task being delegated
- `$contextSummary` - Context summary provided to sub-agent
- `$delegationDepth` - Current nesting level (for recursion monitoring)

For detailed implementation examples and best practices, see the [Sub-Agent Documentation](docs/SUB_AGENTS.md).

### Generation Parameters

Fine-tune your agent's response style with these parameters:

| Parameter       | Range    | Best For           | Example Use Case                                          |
| --------------- | -------- | ------------------ | --------------------------------------------------------- |
| **Temperature** | 0.0-1.0+ | Creativity control | 0.1 (factual Q&A), 0.7 (balanced), 0.9 (creative writing) |
| **Max Tokens**  | 1-4000+  | Response length    | 100 (concise), 1000 (detailed), 2000+ (comprehensive)     |
| **Top-P**       | 0.0-1.0  | Token diversity    | 0.1 (focused), 0.5 (balanced), 0.9 (diverse)              |

**⚠️ Important**: Use either `temperature` OR `topP`, not both simultaneously.

**Configuration Examples:**

```php
// Method 1: Agent class properties
protected ?float $temperature = 0.3;  // Consistent responses
protected ?int $maxTokens = 500;      // Concise answers
protected ?float $topP = null;        // Use temperature instead

// Method 2: Fluent configuration
$agent->setTemperature(0.8)
      ->setMaxTokens(1500)
      ->setTopP(null);

// Method 3: Global defaults in config/agent-adk.php
'default_generation_params' => [
    'temperature' => 0.7,
    'max_tokens' => 1000,
    'top_p' => null,
],

// Method 4: Environment variables
AGENT_ADK_DEFAULT_TEMPERATURE=0.7
AGENT_ADK_DEFAULT_MAX_TOKENS=1000
AGENT_ADK_DEFAULT_TOP_P=
```

### Streaming Responses

Enable real-time streaming responses from your agents for enhanced user experience. When streaming is enabled, agents return a `Stream` object that can be consumed incrementally.

**Enabling Streaming:**

```php
// Method 1: In agent class constructor or property
class ChatAgent extends BaseLlmAgent
{
    protected bool $streaming = true;  // Enable streaming by default

    // Or set it dynamically in constructor
    public function __construct()
    {
        parent::__construct();
        $this->setStreaming(true);
    }
}

// Method 2: Fluent configuration
$agent = Agent::named('chat_agent')
    ->setStreaming(true);

// Method 3: Before running the agent
Agent::named('chat_agent')
    ->setStreaming(true)
    ->run('Tell me a story', 'session-123');
```

**Consuming Streaming Responses:**

```php
use AaronLumsden\LaravelAgentADK\Facades\Agent;

// Enable streaming and get the stream object
$stream = Agent::named('chat_agent')
    ->setStreaming(true)
    ->run('Tell me about Laravel', 'user-session-123');

// Option 1: Iterate over chunks
foreach ($stream as $chunk) {
    echo $chunk;  // Output each piece as it arrives
    flush();      // Send to browser immediately
}

// Option 2: Convert to string (waits for completion)
$fullResponse = (string) $stream;

// Option 3: Process chunks with callback
$stream->each(function ($chunk) {
    // Process each chunk individually
    broadcast(new StreamChunk($chunk));  // Real-time updates via WebSockets
});
```

**Real-time Web Interface Example:**

```php
// Controller method for streaming chat
public function streamChat(Request $request)
{
    $stream = Agent::named('chat_assistant')
        ->setStreaming(true)
        ->run($request->input('message'), $request->input('session_id'));

    return response()->stream(function () use ($stream) {
        foreach ($stream as $chunk) {
            echo "data: " . json_encode(['chunk' => $chunk]) . "\n\n";
            flush();
        }
        echo "data: " . json_encode(['done' => true]) . "\n\n";
    }, 200, [
        'Content-Type' => 'text/event-stream',
        'Cache-Control' => 'no-cache',
        'Connection' => 'keep-alive',
    ]);
}
```

**JavaScript Frontend Integration:**

```javascript
// Stream consumption with Server-Sent Events
const eventSource = new EventSource("/api/agent/stream-chat");
const chatContainer = document.getElementById("chat-container");

eventSource.onmessage = function (event) {
  const data = JSON.parse(event.data);

  if (data.done) {
    eventSource.close();
    return;
  }

  // Append streaming chunk to chat interface
  chatContainer.innerHTML += data.chunk;
  chatContainer.scrollTop = chatContainer.scrollHeight;
};
```

**Important Notes:**

- **Tool Calls**: When streaming is enabled, tool calls and sub-agent delegations are bypassed for performance
- **Response Processing**: The `afterLlmResponse()` hook is called but response processing is minimal for streaming
- **Context Management**: Context is not automatically updated when streaming; handle manually if needed
- **Error Handling**: Stream errors should be handled at the consumer level

**When to Use Streaming:**

✅ **Good for:**

- Real-time chat interfaces
- Long-form content generation
- Interactive storytelling
- Live coding assistance
- Step-by-step tutorials

❌ **Avoid for:**

- Tool-heavy workflows
- Multi-step reasoning tasks
- Sub-agent delegation
- Complex context management

### Agent Lifecycle Hooks

Customize your agent's behavior at key points in the execution cycle with these built-in hooks:

```php
class YourAgent extends BaseLlmAgent
{
    /**
     * Called before sending messages to the LLM.
     * Use this to modify messages, add context, or validate input.
     */
    public function beforeLlmCall(array $inputMessages, AgentContext $context): array
    {
        // Example: Add a system note or modify user input
        $context->setState('last_interaction_time', now());
        // $inputMessages[] = ['role' => 'system', 'content' => 'User is on a mobile device.'];
        return $inputMessages;
    }

    /**
     * Called after receiving a response from the LLM.
     * Use this to process, validate, or transform the LLM response.
     */
    public function afterLlmResponse(Response $response, AgentContext $context): mixed
    {
        // Example: Log responses, validate output structure, apply post-processing
        return $response;
    }

    /**
     * Called before executing any tool.
     * Use this to modify arguments, add authentication, or validate permissions.
     */
    public function beforeToolCall(string $toolName, array $arguments, AgentContext $context): array
    {
        // Example: Validate tool permissions, inject API keys into arguments, log tool call attempts
        return $arguments;
    }

    /**
     * Called after tool execution completes.
     * Use this to process results, handle errors, or format output before it's sent back to the LLM.
     */
    public function afterToolResult(string $toolName, string $result, AgentContext $context): string
    {
        // Example: Format tool results into a specific string, handle tool errors gracefully, log tool usage
        return $result;
    }

    /**
     * Called before delegating a task to a sub-agent.
     * Use this to modify delegation parameters, add authorization checks, or log delegation attempts.
     */
    public function beforeSubAgentDelegation(string $subAgentName, string $taskInput, string $contextSummary, AgentContext $parentContext): array
    {
        // Example: Validate delegation permissions, modify task input, enhance context summary
        return [$subAgentName, $taskInput, $contextSummary];
    }

    /**
     * Called after a sub-agent completes a delegated task.
     * Use this to process results, validate responses, or perform cleanup.
     */
    public function afterSubAgentDelegation(string $subAgentName, string $taskInput, string $subAgentResult, AgentContext $parentContext, AgentContext $subAgentContext): string
    {
        // Example: Process sub-agent results, add metadata, validate responses
        return $subAgentResult;
    }
}
```

These hooks provide powerful customization points for logging, validation, authentication, and result processing without modifying the core agent logic.

### Event System

Hook into agent execution with Laravel events:

```bash
php artisan make:listener LogAgentInteractions --event="AaronLumsden\LaravelAgentADK\Events\AgentResponseGenerated"
```

```php
namespace App\Listeners;

use AaronLumsden\LaravelAgentADK\Events\AgentResponseGenerated;
use Illuminate\Support\Facades\Log;

class LogAgentInteractions
{
    public function handle(AgentResponseGenerated $event): void
    {
        Log::info('Agent response generated', [
            'agent' => $event->agentName,
            'session_id' => $event->context->getSessionId(),
            'response_length' => strlen($event->response),
            'user_input' => $event->context->getUserInput(),
        ]);

        // Track metrics, send to analytics, etc.
    }
}
```

**Task Delegation Monitoring:**

```php
namespace App\Listeners;

use AaronLumsden\LaravelAgentADK\Events\TaskDelegated;
use Illuminate\Support\Facades\Log;

class TrackTaskDelegation
{
    public function handle(TaskDelegated $event): void
    {
        Log::info('Task delegated to sub-agent', [
            'parent_agent' => $event->parentAgentName,
            'sub_agent' => $event->subAgentName,
            'task_input' => $event->taskInput,
            'context_summary' => $event->contextSummary,
            'delegation_depth' => $event->delegationDepth,
            'parent_session' => $event->parentContext->getSessionId(),
            'sub_session' => $event->subAgentContext->getSessionId(),
        ]);

        // Monitor delegation patterns, detect potential issues
        if ($event->delegationDepth > 3) {
            Log::warning('Deep delegation detected', [
                'depth' => $event->delegationDepth,
                'chain' => "{$event->parentAgentName} -> {$event->subAgentName}"
            ]);
        }

        // Track delegation metrics for analytics
        $this->trackDelegationMetrics($event);
    }

    private function trackDelegationMetrics(TaskDelegated $event): void
    {
        // Send metrics to your analytics service
        // Track delegation frequency, depth, success rates, etc.
    }
}
```

**Available Events:**

- `AgentExecutionStarting` - Before agent processing begins
- `AgentExecutionFinished` - After agent completes
- `LlmCallInitiating` - Before LLM API call
- `LlmResponseReceived` - After LLM responds
- `ToolCallInitiating` - Before tool execution
- `ToolCallCompleted` - After tool execution
- `TaskDelegated` - When a task is delegated to a sub-agent
- `AgentResponseGenerated` - Final response ready
- `StateUpdated` - When context state changes

### Error Handling

Robust error handling for production environments:

```php
use AaronLumsden\LaravelAgentADK\Facades\Agent;
use AaronLumsden\LaravelAgentADK\Exceptions\ToolExecutionException;

public function handleChat(Request $request)
{
    try {
        $response = Agent::run('support_agent', $request->input('message'), $request->session()->getId());

        return response()->json([
            'success' => true,
            'response' => $response
        ]);

    } catch (ToolExecutionException $e) {
        // Tool-specific errors
        Log::warning('Tool execution failed', [
            'tool' => $e->getToolName(),
            'error' => $e->getMessage()
        ]);

        return response()->json([
            'success' => false,
            'error' => 'I encountered an issue with one of my tools. Please try again.',
            'code' => 'TOOL_ERROR'
        ], 500);

    } catch (\RuntimeException $e) {
        // LLM API errors
        Log::error('LLM API error', ['error' => $e->getMessage()]);

        return response()->json([
            'success' => false,
            'error' => 'I\'m temporarily unavailable. Please try again in a moment.',
            'code' => 'LLM_ERROR'
        ], 503);

    } catch (\Exception $e) {
        // Unexpected errors
        Log::error('Unexpected agent error', [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);

        return response()->json([
            'success' => false,
            'error' => 'Something went wrong. Please try again.',
            'code' => 'UNKNOWN_ERROR'
        ], 500);
    }
}
```

## Evaluations

The evaluation system helps ensure your agents perform consistently and meet quality standards. It combines traditional assertions with AI-powered quality assessment.

### Creating an Evaluation

```bash
php artisan agent:make:eval CustomerServiceEvaluation
```

### Example: Customer Service Quality Evaluation

```php
namespace App\Evaluations;

use AaronLumsden\LaravelAgentADK\Evaluations\BaseEvaluation;

class CustomerServiceEvaluation extends BaseEvaluation
{
    public string $agentName = 'customer_support';
    public string $name = 'Customer Service Quality Assessment';
    public string $description = 'Evaluates customer service responses for helpfulness and professionalism';
    public string $csvPath = 'storage/evaluations/customer_service_scenarios.csv';
    public string $promptCsvColumn = 'customer_query';

    public function preparePrompt(array $csvRowData): string
    {
        return $csvRowData[$this->promptCsvColumn];
    }

    public function evaluateRow(array $csvRowData, string $llmResponse): array
    {
        $this->resetAssertionResults();

        // Basic response validation
        $this->assertResponseIsNotEmpty($llmResponse, 'Agent must provide a response');
        $this->assertResponseLengthBetween($llmResponse, 20, 500, 'Response should be appropriately sized');

        // Professional tone check
        $this->assertResponseDoesNotContain($llmResponse, 'sorry, I can\'t help',
            'Agent should not give up easily');

        // Helpfulness assessment using LLM judge
        $this->assertLlmJudge(
            $llmResponse,
            'The response should be helpful, professional, empathetic, and provide actionable guidance. It should address the customer\'s concern directly.',
            'llm_judge',
            'pass',
            'Response should meet customer service standards'
        );

        // Quality scoring
        $this->assertLlmJudgeQuality(
            $llmResponse,
            'Rate based on: 1) Helpfulness and problem-solving, 2) Professional and empathetic tone, 3) Clarity and completeness, 4) Appropriate next steps provided',
            7,
            'llm_judge',
            'Customer service quality should be high'
        );

        // Check for required elements if specified in CSV
        if (isset($csvRowData['must_include'])) {
            $requiredElements = explode(',', $csvRowData['must_include']);
            $this->assertContainsAllOf($llmResponse, $requiredElements,
                'Response must include all required elements');
        }

        $assertionStatuses = array_column($this->assertionResults, 'status');
        $finalStatus = !in_array('fail', $assertionStatuses, true) ? 'pass' : 'fail';

        return [
            'row_data' => $csvRowData,
            'llm_response' => $llmResponse,
            'assertions' => $this->assertionResults,
            'final_status' => $finalStatus,
        ];
    }
}
```

### Available Assertion Methods

The `BaseEvaluation` class provides a comprehensive set of assertion methods to validate agent responses:

**Basic Content Assertions:**

- `assertResponseContains()` - Checks if the response contains a specific substring
- `assertResponseDoesNotContain()` - Checks if the response does not contain a specific substring
- `assertResponseStartsWith()` - Checks if the response starts with a specific prefix
- `assertResponseEndsWith()` - Checks if the response ends with a specific suffix
- `assertResponseMatchesRegex()` - Checks if the response matches a given regular expression
- `assertResponseIsNotEmpty()` - Checks if the response is not empty after trimming whitespace

**Length and Format Assertions:**

- `assertResponseLengthBetween()` - Checks if the response character length is within a specified range
- `assertWordCountBetween()` - Checks if the response word count is within a specified range
- `assertResponseIsValidJson()` - Checks if the response is a valid JSON string
- `assertJsonHasKey()` - Checks if the decoded JSON response contains a specific key
- `assertResponseIsValidXml()` - Checks if the response is a valid XML string
- `assertXmlHasValidTag()` - Checks if the XML response contains a specific tag

**Content Analysis Assertions:**

- `assertContainsAnyOf()` - Checks if the response contains at least one of the provided substrings
- `assertContainsAllOf()` - Checks if the response contains all of the provided substrings
- `assertResponseHasPositiveSentiment()` - Performs a basic keyword-based check for positive sentiment

**Tool and Logic Assertions:**

- `assertToolCalled()` - Checks if a specific tool was called by the agent
- `assertEquals()` - Checks if two values are equal using loose comparison
- `assertTrue()` - Checks if a given condition is true
- `assertFalse()` - Checks if a given condition is false
- `assertGreaterThan()` - Checks if the actual value is greater than the expected value
- `assertLessThan()` - Checks if the actual value is less than the expected value

**Safety & Content Assertions:**

- `assertNotToxic()` - Checks if the response does not contain toxic, harmful, or inappropriate content (supports custom word lists)
- `assertNoPII()` - Checks if the response does not contain personally identifiable information (email, SSN, phone, credit card, IP address)
- `assertGrammarCorrect()` - Performs basic grammar validation (multiple spaces, punctuation, capitalization)
- `assertReadabilityLevel()` - Calculates Flesch-Kincaid grade level to ensure appropriate reading difficulty
- `assertNoRepetition()` - Checks for excessive word repetition in the response
- `assertResponseTime()` - Validates that response generation time is within acceptable limits
- `assertIsBritishSpelling()` - Checks if the response uses British spelling conventions (colour, centre, realise, etc.)
- `assertIsAmericanSpelling()` - Checks if the response uses American spelling conventions (color, center, realize, etc.)

**AI-Powered Judge Assertions:**

- `assertLlmJudge()` - Uses another LLM agent to judge the response based on given criteria for a pass/fail outcome
- `assertLlmJudgeQuality()` - Uses an LLM agent to rate the response quality on a numeric scale against given criteria
- `assertLlmJudgeComparison()` - Uses an LLM agent to compare the actual response against a reference response based on criteria

**Example Usage:**

```php
// Safety check with custom toxic words
$this->assertNotToxic($response, ['spam', 'scam'], 'Response should not contain spam content');

// PII validation
$this->assertNoPII($response, 'Response should not leak customer data');

// Readability for general audience
$this->assertReadabilityLevel($response, 8, 'Response should be readable by 8th graders');

// Performance validation
$this->assertResponseTime($processingTime, 5.0, 'Response should be generated quickly');

// Spelling conventions for localized content
$this->assertIsBritishSpelling($response, 'UK content should use British spelling');
$this->assertIsAmericanSpelling($response, 'US content should use American spelling');

// Advanced quality scoring
$this->assertLlmJudgeQuality(
    $response,
    'Rate based on accuracy, helpfulness, and professional tone',
    8,
    'llm_judge',
    'Response quality should be excellent'
);
```

## Configuration

Key configuration options in `config/agent-adk.php`:

```php
return [
    // Default LLM provider and model
    'default_provider' => env('AGENT_ADK_DEFAULT_PROVIDER', 'openai'),
    'default_model' => env('AGENT_ADK_DEFAULT_MODEL', 'gemini-pro'),

    // Generation parameters
    'default_generation_params' => [
        'temperature' => env('AGENT_ADK_DEFAULT_TEMPERATURE', null),
        'max_tokens' => env('AGENT_ADK_DEFAULT_MAX_TOKENS', null),
        'top_p' => env('AGENT_ADK_DEFAULT_TOP_P', null),
    ],

    // Database table names
    'tables' => [
        'agent_sessions' => 'agent_sessions',
        'agent_messages' => 'agent_messages',
    ],

    // Namespaces for generated classes
    'namespaces' => [
        'agents' => 'App\Agents',
        'tools'  => 'App\Tools',
    ],

    // Built-in API routes
    'routes' => [
        'enabled' => true,
        'prefix' => 'api/agent-adk',
        'middleware' => ['api'],
    ],

    // Prism-PHP configuration
    'prism' => [
        'api_key' => env('PRISM_API_KEY'),
        'client_options' => [],
    ],
];
```

## Security Best Practices

### Input Validation and Sanitization

```php
// In your agent class
public function beforeLlmCall(array $inputMessages, AgentContext $context): array
{
    $userInput = $context->getUserInput();

    // Length validation
    if (strlen($userInput) > 4000) {
        throw new \InvalidArgumentException('Input too long');
    }

    // Content filtering
    if ($this->containsProhibitedContent($userInput)) {
        throw new \InvalidArgumentException('Input contains prohibited content');
    }

    // Sanitize HTML if needed
    $sanitizedInput = strip_tags($userInput);
    $context->setUserInput($sanitizedInput);

    return $inputMessages;
}

private function containsProhibitedContent(string $input): bool
{
    $prohibitedPatterns = [
        '/\b(exec|eval|system|shell_exec)\s*\(/i',
        '/<script\b[^<]*(?:(?!<\/script>)<[^<]*)*<\/script>/mi',
        // Add your patterns
    ];

    foreach ($prohibitedPatterns as $pattern) {
        if (preg_match($pattern, $input)) {
            return true;
        }
    }

    return false;
}
```

### API Key Management

```php
// Use Laravel's encrypted configuration for sensitive data
// .env
OPENAI_API_KEY=sk-your-key-here

// In production, consider using:
// - Laravel Vault
// - AWS Secrets Manager
// - Azure Key Vault
// - HashiCorp Vault
```

### Rate Limiting

```php
// Apply rate limiting to your agent endpoints
Route::middleware(['throttle:agent-chat'])->group(function () {
    Route::post('/chat', [ChatController::class, 'handle']);
});

// In RouteServiceProvider.php
protected function configureRateLimiting()
{
    RateLimiter::for('agent-chat', function (Request $request) {
        return Limit::perMinute(30)->by($request->ip());
    });
}
```

## Performance Considerations

### Optimization Strategies

1. **Response Caching**

```php
// Cache frequent responses
public function run(mixed $input, AgentContext $context): mixed
{
    $cacheKey = 'agent_response:' . md5($this->name . $input);

    return Cache::remember($cacheKey, 300, function() use $input, $context) {
        return parent::run($input, $context);
    });
}
```

2. **Tool Result Caching**

```php
// In your tool's execute method
public function execute(array $arguments, AgentContext $context): string
{
    $cacheKey = 'tool_result:' . md5(json_encode($arguments));

    return Cache::remember($cacheKey, 600, function() use ($arguments) {
        return $this->performApiCall($arguments);
    });
}
```

3. **Database Optimization**

```php
// Index your agent tables
Schema::table('agent_sessions', function (Blueprint $table) {
    $table->index(['session_id', 'created_at']);
    $table->index('agent_name');
});
```

### Memory Management

- Set appropriate `max_tokens` values (typically 500-2000)
- Clean up old conversation contexts regularly
- Use pagination for large tool result sets
- Monitor memory usage with tools like Telescope

### Sub-Agent Performance

- **Delegation Depth**: Consider limiting nested delegation to prevent excessive recursion
- **Context Isolation**: Each sub-agent creates separate contexts, which consume additional memory
- **Caching Strategy**: Cache frequently delegated sub-agent responses to reduce redundant processing
- **Monitoring**: Track delegation patterns to identify performance bottlenecks in agent hierarchies

## Testing

### Unit Testing Your Agents

```php
namespace Tests\Unit\Agents;

use Tests\TestCase;
use App\Agents\CustomerSupportAgent;
use AaronLumsden\LaravelAgentADK\System\AgentContext;
use AaronLumsden\LaravelAgentADK\Facades\Agent;

class CustomerSupportAgentTest extends TestCase
{
    public function test_agent_registration()
    {
        $agent = Agent::named('customer_support');
        $this->assertInstanceOf(CustomerSupportAgent::class, $agent);
    }

    public function test_agent_responds_to_greeting()
    {
        $response = Agent::run('customer_support', 'Hello', 'test-session');

        $this->assertIsString($response);
        $this->assertNotEmpty($response);
        $this->assertStringContainsString('hello', strtolower($response));
    }

    public function test_agent_handles_context()
    {
        $sessionId = 'test-session-' . uniqid();

        // First interaction
        Agent::run('customer_support', 'My name is John', $sessionId);

        // Second interaction should remember context
        $response = Agent::run('customer_support', 'What is my name?', $sessionId);

        $this->assertStringContainsString('John', $response);
    }
}
```

### Integration Testing

```php
public function test_agent_api_endpoint()
{
    $response = $this->postJson('/api/agent-adk/interact', [
        'agent_name' => 'customer_support',
        'input' => 'I need help with my order',
        'session_id' => 'test-session'
    ]);

    $response->assertStatus(200)
             ->assertJsonStructure([
                 'agent_name',
                 'session_id',
                 'response'
             ]);
}
```

## Deployment

### Production Checklist

- [ ] Set proper environment variables
- [ ] Configure rate limiting
- [ ] Set up monitoring and logging
- [ ] Test error handling scenarios
- [ ] Verify API key security
- [ ] Configure caching strategy
- [ ] Set up context cleanup jobs
- [ ] Test agent evaluations

### Environment Configuration

```bash
# Production .env additions
AGENT_ADK_DEFAULT_TEMPERATURE=0.3  # Lower for consistency
AGENT_ADK_DEFAULT_MODEL=gpt-4o     # Reliable model
LOG_LEVEL=warning                   # Reduce log noise

# Optional: Use Redis for caching
CACHE_DRIVER=redis
SESSION_DRIVER=redis
```

### Context Cleanup Job

```php
// Create a scheduled job to clean up old contexts
php artisan make:command CleanupAgentContexts

// In the command
public function handle()
{
    $cutoff = now()->subHours(24); // Clean up contexts older than 24 hours

    DB::table(config('agent-adk.tables.agent_sessions'))
      ->where('updated_at', '<', $cutoff)
      ->delete();

    DB::table(config('agent-adk.tables.agent_messages'))
      ->where('created_at', '<', $cutoff)
      ->delete();

    $this->info('Cleaned up old agent contexts');
}

// In app/Console/Kernel.php
protected function schedule(Schedule $schedule)
{
    $schedule->command('agent:cleanup-contexts')->daily();
}
```

## Troubleshooting 🔧

### Common Issues

**"Agent not found" Error**

```php
// Ensure agent is registered in AppServiceProvider
Agent::build(YourAgent::class)->register();
```

**"Tool execution failed" Error**

```php
// Check tool namespace and registration
protected function registerTools(): array
{
    return [
        \App\Tools\YourTool::class, // Correct namespace
    ];
}
```

**Memory Issues**

```php
// Reduce max_tokens or implement response caching
protected ?int $maxTokens = 500; // Instead of 2000
```

**API Rate Limits**

```php
// Implement exponential backoff in your tools
try {
    $response = Http::retry(3, 1000)->get($url);
} catch (Exception $e) {
    // Handle rate limiting
}
```

### Debug Mode

Enable detailed logging by setting your Laravel log level:

```php
// In .env
LOG_LEVEL=debug
```

## Contributing 🤝

We welcome contributions! Here's how you can help:

1. **Report Issues**: Use GitHub issues for bugs and feature requests
2. **Submit PRs**: Follow PSR-12 coding standards
3. **Add Tests**: Include tests for new features
4. **Update Docs**: Keep documentation current
5. **Share Examples**: Contribute real-world use cases

### Development Setup

```bash
git clone https://github.com/aaronlumsden/laravel-agent-adk.git
cd laravel-agent-adk
composer install
cp .env.example .env
php artisan key:generate
```

## License 📄

MIT License - see [LICENSE](LICENSE) for details.

---

**Ready to build something amazing?** Start with the [Quick Start](#quick-start-) guide and join our community of Laravel AI developers! 🚀
