# Memory and Context Management

Vizra ADK agents can maintain memory across conversations, enabling contextual and personalized interactions.

## Memory Types

1. **Session Memory**: Conversation history within a session
2. **User Memory**: Persistent memory tied to a user
3. **Vector Memory**: Semantic search-enabled long-term memory

## Basic Memory Usage

### User-Scoped Memory
```php
// First conversation
$response1 = MyAgent::run('My name is John and I love pizza')
    ->forUser($user)
    ->go();

// Later conversation - agent remembers
$response2 = MyAgent::run('What is my name and what do I like?')
    ->forUser($user)
    ->go(); // Will remember "John" and "pizza"
```

### Session-Based Memory
```php
// Maintain context within a specific session
$sessionId = 'support-ticket-123';

// Multiple interactions in same session
$response1 = MyAgent::run('I have an issue with my order #456')
    ->forUser($user)
    ->withSession($sessionId)
    ->go();

$response2 = MyAgent::run('What order number did I mention?')
    ->forUser($user)
    ->withSession($sessionId)
    ->go(); // Remembers order #456
```

## Memory Tools

### Using MemoryTool
```php
use Vizra\VizraADK\Tools\MemoryTool;

class AssistantAgent extends BaseLlmAgent
{
    protected array $tools = [
        MemoryTool::class, // Enables memory operations
    ];
    
    protected string $instructions = <<<'INSTRUCTIONS'
        You are a helpful assistant with memory capabilities.
        
        Use the memory tool to:
        - Store important information about the user
        - Recall previous conversations
        - Update existing memories
        INSTRUCTIONS;
}
```

### Using VectorMemoryTool
```php
use Vizra\VizraADK\Tools\VectorMemoryTool;

class KnowledgeAgent extends BaseLlmAgent
{
    protected array $tools = [
        VectorMemoryTool::class, // Semantic search in memories
    ];
    
    protected string $instructions = <<<'INSTRUCTIONS'
        You are a knowledge management assistant.
        
        Use vector memory to:
        - Store information with semantic meaning
        - Search for relevant past information
        - Find similar concepts discussed before
        INSTRUCTIONS;
}
```

## Programmatic Memory Access

### Storing Memories
```php
use Vizra\VizraADK\Memory\AgentMemory;

// Memory is typically managed internally by agents
// In custom tools or agents, you can access memory methods:

// Store facts and learnings
$memory->addFact('User prefers email communication', 0.9);
$memory->addLearning('Customer is price-sensitive');
$memory->addPreference('communication', 'email');
```

### Retrieving Memories
```php
// Get specific memory types
$facts = $memory->getFacts();
$learnings = $memory->getLearnings();
$summary = $memory->getSummary();
$preferences = $memory->getPreferences();

// Search memories semantically
$relevant = $memory->search('communication preferences');
```

### Vector Memory Operations
```php
use Vizra\VizraADK\Services\VectorMemoryManager;

$vectorManager = app(VectorMemoryManager::class);

// Store with embeddings
$vectorManager->store(
    'The user is interested in machine learning and AI',
    ['user_id' => $user->id]
);

// Semantic search
$relevant = $vectorManager->search(
    'artificial intelligence topics',
    limit: 5
);
```

## Memory Patterns

### Profile Building
```php
class ProfileBuilderAgent extends BaseLlmAgent
{
    protected string $instructions = <<<'INSTRUCTIONS'
        Build a comprehensive user profile over time.
        
        Track and remember:
        - Personal preferences
        - Communication style
        - Past decisions
        - Goals and objectives
        
        Update the profile as you learn more.
        INSTRUCTIONS;
    
    protected array $tools = [
        MemoryTool::class,
    ];
}

// Usage
$agent = ProfileBuilderAgent::run('I prefer morning meetings and detailed reports')
    ->forUser($user)
    ->go();
```

### Context-Aware Responses
```php
class ContextualAgent extends BaseLlmAgent
{
    protected string $instructions = <<<'INSTRUCTIONS'
        Always consider conversation history when responding.
        Reference previous discussions when relevant.
        Maintain continuity across interactions.
        INSTRUCTIONS;
        
    public function execute($input)
    {
        // Automatically loads conversation history
        return $this->run($input)
            ->forUser($this->user)
            ->withSession($this->sessionId)
            ->go();
    }
}
```

## Memory Configuration

### In Agent Class
```php
class ConfiguredMemoryAgent extends BaseLlmAgent
{
    // Configure conversation history inclusion
    protected bool $includeConversationHistory = true;  // Include conversation history
    protected string $contextStrategy = 'recent'; // How to include context: 'recent', 'full', 'none'
    protected int $historyLimit = 10; // Maximum messages to include when using 'recent'
}
```

## Advanced Memory Techniques

### Memory Summarization
```php
class SummarizingAgent extends BaseLlmAgent
{
    protected function preprocessMemory($history)
    {
        if (count($history) > 20) {
            // Summarize older conversations
            $oldMessages = array_slice($history, 0, -10);
            $summary = $this->summarize($oldMessages);
            
            return array_merge(
                [$summary],
                array_slice($history, -10)
            );
        }
        return $history;
    }
}
```

### Selective Memory
```php
class SelectiveMemoryAgent extends BaseLlmAgent
{
    protected string $instructions = <<<'INSTRUCTIONS'
        Only remember important information:
        - User preferences
        - Key decisions
        - Action items
        - Personal details
        
        Ignore small talk and redundant information.
        INSTRUCTIONS;
}
```

### Memory Segregation
```php
// Different memory scopes for different purposes
$agent = MyAgent::run($input)
    ->forUser($user)
    ->withSession('personal')  // Personal context
    ->go();

$agent = MyAgent::run($workInput)
    ->forUser($user)
    ->withSession('work')      // Work context
    ->go();
```

## Memory Best Practices

1. **Clear Session Boundaries**: Use distinct session IDs for different conversations
2. **Memory Hygiene**: Periodically clean old or irrelevant memories
3. **Privacy**: Never store sensitive data (passwords, SSN, etc.)
4. **Summarization**: Summarize long conversations to stay within token limits
5. **Indexing**: Use vector memory for searchable long-term storage

## Common Issues and Solutions

### Memory Not Persisting
```php
// Ensure user context is provided
$response = Agent::run($input)
    ->forUser($user) // Required for persistence
    ->go();
```

### Token Limit Exceeded
```php
// Configure agent to use less history
class EfficientAgent extends BaseLlmAgent
{
    protected bool $includeHistory = true;
    protected string $contextStrategy = 'recent'; // Only recent messages
    
    // Or handle in preprocessing
    protected function preprocessMemory($history)
    {
        // Only keep last 10 messages
        return array_slice($history, -10);
    }
}
```

### Memory Retrieval Performance
```php
// Use caching for frequently accessed memories
Cache::remember("user_facts_{$user->id}", 3600, function() use ($memory) {
    return $memory->getFacts();
});

// Cache conversation context
Cache::remember("user_context_{$user->id}", 3600, function() use ($context) {
    return $context->getConversationHistory();
});
```

## Next Steps

- Implement sub-agent memory sharing: See `sub-agents.blade.php`
- Test memory-dependent agents: See `evaluation.blade.php`