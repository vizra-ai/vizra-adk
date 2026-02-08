---
name: "Vizra ADK Memory System"
description: "Implement persistent memory, session context, and vector memory (RAG) for AI agents"
---

# Vizra ADK Memory System

Vizra ADK provides multiple memory types for agents to maintain context across conversations and sessions.

## Memory Types

| Type | Purpose | Persistence |
|------|---------|-------------|
| **Session Memory** | Conversation history within a session | Session lifetime |
| **User Memory** | Information tied to a specific user | Permanent |
| **Vector Memory** | Semantic search and RAG | Permanent |

## Basic Memory Usage

### Session-Based Memory

Conversation history persists within a session:

```php
use App\Agents\MyAgent;

$sessionId = 'conversation-123';

// First message in session
$response1 = MyAgent::run('My name is John')
    ->forUser($user)
    ->withSession($sessionId)
    ->go();

// Later in the same session - agent remembers context
$response2 = MyAgent::run('What is my name?')
    ->forUser($user)
    ->withSession($sessionId)
    ->go(); // Will remember "John"
```

### User-Based Memory

Memory persists across all sessions for a user:

```php
// First conversation
$response1 = MyAgent::run('I prefer dark mode and short responses')
    ->forUser($user)
    ->go();

// New conversation - user preferences are remembered
$response2 = MyAgent::run('Help me with my project')
    ->forUser($user)
    ->go(); // Agent remembers preferences
```

## Memory Tools

### MemoryTool

Allow agents to explicitly store and retrieve memories:

```php
use Vizra\VizraADK\Tools\MemoryTool;

class PersonalAssistantAgent extends BaseLlmAgent
{
    protected string $name = 'personal_assistant';
    protected string $instructions = <<<'INSTRUCTIONS'
        You are a personal assistant. Use the memory tool to:
        - Remember important facts the user tells you
        - Recall information when asked
        - Update memories when information changes
        INSTRUCTIONS;

    protected array $tools = [
        MemoryTool::class,
    ];
}
```

The agent can then use commands like:
- `remember: User's birthday is March 15`
- `recall: birthday`
- `forget: old address`

### VectorMemoryTool

Enable semantic search for RAG (Retrieval Augmented Generation):

```php
use Vizra\VizraADK\Tools\VectorMemoryTool;

class KnowledgeAgent extends BaseLlmAgent
{
    protected string $name = 'knowledge_agent';
    protected string $instructions = <<<'INSTRUCTIONS'
        You are a knowledge assistant with access to the company documentation.
        Use the vector memory tool to search for relevant information before answering questions.
        Always cite the source of information.
        INSTRUCTIONS;

    protected array $tools = [
        VectorMemoryTool::class,
    ];
}
```

## Programmatic Memory Access

### Storing Memories

```php
use Vizra\VizraADK\Services\MemoryManager;

$memoryManager = app(MemoryManager::class);

// Store a memory for a user
$memoryManager->store(
    userId: $user->id,
    key: 'preferences',
    value: [
        'theme' => 'dark',
        'language' => 'en',
        'notifications' => true
    ]
);

// Store with tags for organization
$memoryManager->store(
    userId: $user->id,
    key: 'project_alpha_notes',
    value: $notes,
    tags: ['projects', 'alpha', 'notes']
);
```

### Retrieving Memories

```php
// Get specific memory
$preferences = $memoryManager->get($user->id, 'preferences');

// Get all memories with a tag
$projectMemories = $memoryManager->getByTag($user->id, 'projects');

// Search memories
$results = $memoryManager->search($user->id, 'project deadline');
```

### Updating and Deleting

```php
// Update existing memory
$memoryManager->update(
    userId: $user->id,
    key: 'preferences',
    value: array_merge($currentPrefs, ['theme' => 'light'])
);

// Delete specific memory
$memoryManager->delete($user->id, 'old_data');

// Clear all user memories
$memoryManager->clearUser($user->id);
```

## Vector Memory for RAG

### Storing Documents

```php
use Vizra\VizraADK\Services\VectorMemoryManager;

$vectorManager = app(VectorMemoryManager::class);

// Store a document with automatic chunking
$vectorManager->store(
    content: $documentContent,
    metadata: [
        'source' => 'company_handbook',
        'section' => 'policies',
        'updated_at' => now()
    ]
);

// Store multiple documents
$vectorManager->storeMany([
    ['content' => $doc1, 'metadata' => ['type' => 'policy']],
    ['content' => $doc2, 'metadata' => ['type' => 'guide']],
]);
```

### Searching Vector Memory

```php
// Semantic search
$results = $vectorManager->search(
    query: 'What is the vacation policy?',
    limit: 5
);

// Search with metadata filter
$results = $vectorManager->search(
    query: 'onboarding process',
    limit: 5,
    filter: ['type' => 'guide']
);
```

### CLI Commands

```bash
# Store documents from file
php artisan vizra:vector:store --file=handbook.pdf

# Store directory of documents
php artisan vizra:vector:store --directory=docs/

# Search vector memory
php artisan vizra:vector:search "vacation policy"

# View statistics
php artisan vizra:vector:stats
```

## Memory Patterns

### Building User Profiles

```php
class ProfileBuildingAgent extends BaseLlmAgent
{
    protected string $instructions = <<<'INSTRUCTIONS'
        Build a comprehensive user profile by:
        1. Asking about their preferences gradually
        2. Remembering details they share
        3. Inferring preferences from behavior
        4. Updating profile as preferences change
        INSTRUCTIONS;

    protected array $tools = [
        MemoryTool::class,
    ];

    public function afterExecution($response, $context)
    {
        // Extract and store any preferences mentioned
        $this->extractAndStorePreferences($response, $context);
    }
}
```

### Context-Aware Responses

```php
class ContextAwareAgent extends BaseLlmAgent
{
    public function beforeExecution($input, $context)
    {
        // Load relevant memories before processing
        $memories = $this->loadRelevantMemories($context);

        // Inject into context
        $context->setParameter('user_context', $memories);

        return $input;
    }

    protected function loadRelevantMemories($context)
    {
        $memoryManager = app(MemoryManager::class);

        return [
            'preferences' => $memoryManager->get($context->getUserId(), 'preferences'),
            'recent_topics' => $memoryManager->getByTag($context->getUserId(), 'recent'),
            'important' => $memoryManager->getByTag($context->getUserId(), 'important'),
        ];
    }
}
```

### Conversation Summarization

```php
class SummarizingAgent extends BaseLlmAgent
{
    protected int $maxHistoryLength = 50;

    public function beforeExecution($input, $context)
    {
        $history = $context->getHistory();

        if (count($history) > $this->maxHistoryLength) {
            // Summarize older messages
            $toSummarize = array_slice($history, 0, -20);
            $summary = $this->summarize($toSummarize);

            // Store summary and trim history
            $context->setParameter('conversation_summary', $summary);
            $context->setHistory(array_slice($history, -20));
        }

        return $input;
    }

    protected function summarize($messages)
    {
        return SummarizerAgent::run(json_encode($messages))
            ->withParameters(['style' => 'concise'])
            ->go();
    }
}
```

## Memory Configuration

### Config File Settings

```php
// config/vizra-adk.php

return [
    'memory' => [
        // Default memory driver
        'driver' => env('VIZRA_MEMORY_DRIVER', 'database'),

        // Memory TTL (time to live)
        'ttl' => env('VIZRA_MEMORY_TTL', 86400 * 30), // 30 days

        // Maximum memories per user
        'max_per_user' => env('VIZRA_MAX_MEMORIES', 1000),
    ],

    'vector_memory' => [
        // Embedding provider
        'provider' => env('VIZRA_EMBEDDING_PROVIDER', 'openai'),

        // Embedding model
        'model' => env('VIZRA_EMBEDDING_MODEL', 'text-embedding-3-small'),

        // Vector store
        'store' => env('VIZRA_VECTOR_STORE', 'meilisearch'),

        // Chunk size for documents
        'chunk_size' => env('VIZRA_CHUNK_SIZE', 500),

        // Chunk overlap
        'chunk_overlap' => env('VIZRA_CHUNK_OVERLAP', 50),
    ],
];
```

### Environment Variables

```env
# Memory settings
VIZRA_MEMORY_DRIVER=database
VIZRA_MEMORY_TTL=2592000

# Vector memory / Embeddings
VIZRA_EMBEDDING_PROVIDER=openai
VIZRA_EMBEDDING_MODEL=text-embedding-3-small
VIZRA_VECTOR_STORE=meilisearch
MEILISEARCH_HOST=http://localhost:7700
MEILISEARCH_KEY=your-master-key
```

## Best Practices

### 1. Scope Memories Appropriately

```php
// Good: User-specific memory
$memoryManager->store($user->id, 'preferences', $prefs);

// Good: Session-specific for temporary data
$context->setSessionData('current_task', $task);

// Bad: Global memory without user scope
$memoryManager->store(null, 'data', $data);
```

### 2. Clean Up Old Memories

```php
// Periodic cleanup
$memoryManager->deleteOlderThan($user->id, now()->subMonths(6));

// Or use TTL on store
$memoryManager->store($user->id, 'temp_data', $data, ttl: 3600);
```

### 3. Structure Memory Keys

```php
// Good: Hierarchical keys
$memoryManager->store($user->id, 'projects.alpha.deadline', $date);
$memoryManager->store($user->id, 'projects.alpha.status', 'active');

// Good: Use tags for cross-cutting concerns
$memoryManager->store($user->id, 'note_123', $note, tags: ['notes', 'project_alpha']);
```

### 4. Handle Memory Limits

```php
class MemoryAwareAgent extends BaseLlmAgent
{
    public function beforeExecution($input, $context)
    {
        $history = $context->getHistory();

        // Limit token usage
        if ($this->estimateTokens($history) > 4000) {
            $history = $this->trimHistory($history, 4000);
            $context->setHistory($history);
        }

        return $input;
    }
}
```

## Database Tables

Memories are stored in these tables:
- `agent_memories` - Key-value persistent memories
- `agent_sessions` - Session data
- `agent_messages` - Conversation history
- `agent_vector_memories` - Vector embeddings
