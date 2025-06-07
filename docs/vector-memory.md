# 💾 Vector Memory & RAG

Give your agents superpowers with long-term memory and knowledge retrieval. Vector memory allows agents to store, search, and retrieve information semantically—just like human memory, but better.

## 🎯 What Is Vector Memory?

**Traditional databases** store data you can query with exact matches.  
**Vector memory** stores meaning you can search with concepts.

```
Traditional: "Find all records where status = 'shipped'"
Vector Memory: "Find information about delayed deliveries and customer complaints"
```

### The Magic of Semantic Search

Vector memory converts text into high-dimensional vectors (embeddings) that capture semantic meaning. Similar concepts cluster together in vector space, enabling powerful semantic search.

```php
// These would all find relevant information about shipping issues:
$agent->searchMemory("Package hasn't arrived yet");
$agent->searchMemory("Where is my order?");  
$agent->searchMemory("Tracking shows delivered but I don't have it");
$agent->searchMemory("Delayed shipment problems");
```

## 🚀 Quick Start

### Step 1: Configure Vector Memory

Add to your `.env`:

```env
# Vector Memory Driver (meilisearch, pinecone, weaviate, local)
VECTOR_MEMORY_DRIVER=meilisearch

# Meilisearch Configuration
MEILISEARCH_HOST=http://localhost:7700
MEILISEARCH_API_KEY=your-api-key

# Embedding Provider (openai, cohere, local)
EMBEDDING_PROVIDER=openai
EMBEDDING_MODEL=text-embedding-ada-002

# OpenAI API Key (for embeddings)
OPENAI_API_KEY=your-openai-api-key
```

### Step 2: Add Vector Memory to Your Agent

```php
<?php

namespace App\Agents;

use AaronLumsden\LaravelAiADK\Agents\BaseLlmAgent;
use App\Tools\VectorMemoryTool;

class CustomerSupportAgent extends BaseLlmAgent
{
    protected string $instructions = "
    You are a helpful customer support agent with access to company knowledge and customer history.
    
    **Using Your Memory:**
    - ALWAYS search your memory for relevant information before responding
    - Store important customer details and preferences for future conversations
    - Remember solutions to common problems to help future customers
    
    **Memory Guidelines:**
    - Search for: policies, procedures, common issues, customer history
    - Store: customer preferences, successful solutions, important notes
    - Use context from memory to provide personalized, accurate responses
    ";

    protected array $tools = [
        VectorMemoryTool::class,
    ];
}
```

### Step 3: Store Knowledge

```php
use AaronLumsden\LaravelAiADK\Facades\VectorMemory;

// Store company policies
VectorMemory::store(
    agentName: 'customer_support',
    content: "Our return policy allows returns within 30 days of purchase for items in original condition. Digital products cannot be returned. Customers need original receipt or order number.",
    metadata: ['type' => 'policy', 'category' => 'returns'],
    source: 'return_policy.pdf'
);

// Store customer interaction
VectorMemory::store(
    agentName: 'customer_support', 
    content: "Customer John Smith (john@email.com) prefers morning delivery between 9-11 AM. Lives in apartment building, needs building code 1234 for delivery. Previous order ORD-98765 had delivery issues.",
    metadata: ['type' => 'customer_info', 'customer_email' => 'john@email.com'],
    source: 'customer_conversation'
);

// Store troubleshooting solution
VectorMemory::store(
    agentName: 'customer_support',
    content: "For WiFi connectivity issues with Model X router: 1) Unplug power for 30 seconds 2) Check ethernet cable connections 3) Reset network settings 4) Update firmware. This solution worked for 95% of similar cases.",
    metadata: ['type' => 'solution', 'product' => 'Model X Router', 'issue' => 'wifi'],
    source: 'tech_support_kb'
);
```

### Step 4: Test Your Agent

```php
use AaronLumsden\LaravelAiADK\Facades\Agent;

// The agent will automatically search memory for relevant information
$response = Agent::run('customer_support', "Hi, I'm John Smith and I want to return an item I bought last week");

// Response will include:
// - Information about return policy (30 days, original condition)
// - Recognition of customer (delivery preferences, previous issues)
// - Personalized service based on stored customer data
```

## 🛠️ Vector Memory Drivers

Choose the driver that fits your needs:

### Meilisearch (Recommended for Most)

**Pros:** Open-source, fast, easy to deploy, great for Laravel apps  
**Cons:** Newer to vector search (but improving rapidly)

```env
VECTOR_MEMORY_DRIVER=meilisearch
MEILISEARCH_HOST=http://localhost:7700
MEILISEARCH_API_KEY=your-api-key
```

**Setup:**
```bash
# Using Docker
docker run -it --rm -p 7700:7700 getmeili/meilisearch:v1.5

# Using Docker Compose (recommended)
# Add to your docker-compose.yml:
meilisearch:
  image: getmeili/meilisearch:v1.5
  ports:
    - "7700:7700"
  environment:
    - MEILI_MASTER_KEY=your-secure-key
  volumes:
    - meilisearch_data:/meili_data
```

### Pinecone (Recommended for Scale)

**Pros:** Purpose-built for vectors, highly scalable, managed service  
**Cons:** Paid service, vendor lock-in

```env
VECTOR_MEMORY_DRIVER=pinecone
PINECONE_API_KEY=your-pinecone-api-key
PINECONE_ENVIRONMENT=us-west1-gcp
```

### Weaviate (Recommended for Advanced)

**Pros:** Advanced features, hybrid search, GraphQL API  
**Cons:** More complex setup, resource intensive

```env
VECTOR_MEMORY_DRIVER=weaviate
WEAVIATE_HOST=http://localhost:8080
WEAVIATE_API_KEY=your-api-key
```

### Local Driver (Recommended for Development)

**Pros:** No external dependencies, works offline  
**Cons:** Limited scalability, slower search

```env
VECTOR_MEMORY_DRIVER=local
VECTOR_MEMORY_LOCAL_PATH=storage/vector_memory
```

## 🧠 Advanced Memory Patterns

### Hierarchical Knowledge Organization

Organize knowledge with namespaces for different types of information:

```php
// Company policies and procedures
VectorMemory::store(
    agentName: 'customer_support',
    content: $policyText,
    namespace: 'policies',
    metadata: ['department' => 'customer_service', 'version' => '2024.1']
);

// Product knowledge base
VectorMemory::store(
    agentName: 'customer_support', 
    content: $productInfo,
    namespace: 'products',
    metadata: ['category' => 'electronics', 'brand' => 'TechCorp']
);

// Customer interaction history
VectorMemory::store(
    agentName: 'customer_support',
    content: $customerConversation,
    namespace: 'customers',
    metadata: ['customer_id' => 12345, 'interaction_type' => 'support']
);
```

### Dynamic Memory Updates

Keep knowledge current with automatic updates:

```php
class PolicyUpdateJob implements ShouldQueue
{
    public function handle()
    {
        // Remove outdated policy information
        VectorMemory::delete(
            agentName: 'customer_support',
            namespace: 'policies',
            source: 'return_policy_v1.pdf'
        );
        
        // Add updated policy
        $newPolicyContent = file_get_contents('policies/return_policy_v2.pdf');
        VectorMemory::store(
            agentName: 'customer_support',
            content: $this->extractTextFromPdf($newPolicyContent),
            namespace: 'policies',
            metadata: ['version' => '2.0', 'effective_date' => now()],
            source: 'return_policy_v2.pdf'
        );
    }
}
```

### Contextual Memory Search

Search with context for more relevant results:

```php
// In your agent's beforeProcessing method
public function beforeProcessing(string $input, AgentContext $context): void
{
    // Get conversation context
    $customerEmail = $context->getState('customer_email');
    $currentIssue = $context->getState('current_issue_type');
    
    // Search with contextual filters
    $relevantMemories = VectorMemory::search(
        agentName: 'customer_support',
        query: $input,
        namespace: 'all',
        filters: [
            'OR' => [
                ['metadata.customer_email' => $customerEmail],
                ['metadata.issue_type' => $currentIssue],
                ['metadata.type' => 'policy']
            ]
        ],
        limit: 5
    );
    
    // Add memories to context for the LLM
    if ($relevantMemories->isNotEmpty()) {
        $memoryContext = $this->formatMemoriesForContext($relevantMemories);
        $context->setState('relevant_memories', $memoryContext);
    }
}
```

## 🔍 RAG (Retrieval-Augmented Generation)

RAG combines the power of retrieval with generation, allowing agents to use retrieved knowledge to enhance their responses.

### Basic RAG Implementation

```php
class KnowledgeBaseAgent extends BaseLlmAgent
{
    protected string $instructions = "
    You are a knowledgeable assistant with access to a comprehensive knowledge base.
    
    **RAG Process:**
    1. When answering questions, FIRST search your knowledge base for relevant information
    2. Use the retrieved context to inform your response
    3. Cite sources when providing factual information
    4. If no relevant information is found, clearly state the limitations
    
    **Response Format:**
    - Answer the question using retrieved knowledge
    - Include source references: [Source: document_name.pdf]
    - If information is incomplete, suggest where to find more details
    ";

    protected array $tools = [VectorMemoryTool::class];

    public function beforeProcessing(string $input, AgentContext $context): void
    {
        // Automatically retrieve relevant context for every query
        $ragContext = VectorMemory::generateRagContext(
            agentName: 'knowledge_base',
            query: $input,
            limit: 3,
            threshold: 0.7
        );
        
        if (!empty($ragContext['context'])) {
            // Inject retrieved context into the conversation
            $enhancedInput = $input . "\n\n--- RELEVANT KNOWLEDGE ---\n" . $ragContext['context'];
            $context->setState('enhanced_input', $enhancedInput);
            $context->setState('rag_sources', $ragContext['sources']);
        }
    }
}
```

### Advanced RAG with Re-ranking

For better relevance, implement result re-ranking:

```php
use AaronLumsden\LaravelAiADK\Services\VectorMemoryManager;

class AdvancedRagAgent extends BaseLlmAgent
{
    public function generateEnhancedRagContext(string $query, int $initialLimit = 10, int $finalLimit = 3): array
    {
        // Step 1: Retrieve more results than needed
        $initialResults = VectorMemory::search(
            agentName: 'advanced_rag',
            query: $query,
            limit: $initialLimit,
            threshold: 0.6 // Lower threshold for initial retrieval
        );
        
        if ($initialResults->isEmpty()) {
            return ['context' => '', 'sources' => []];
        }
        
        // Step 2: Re-rank results using cross-encoder or advanced scoring
        $rerankedResults = $this->reRankResults($query, $initialResults);
        
        // Step 3: Take top results after re-ranking
        $finalResults = $rerankedResults->take($finalLimit);
        
        // Step 4: Format context with metadata
        $context = $this->formatRagContext($finalResults, $query);
        
        return [
            'context' => $context,
            'sources' => $finalResults->pluck('source')->unique()->toArray(),
            'confidence' => $this->calculateConfidence($finalResults),
        ];
    }
    
    private function reRankResults(string $query, Collection $results): Collection
    {
        // Score results based on multiple factors
        return $results->map(function ($result) use ($query) {
            $result->rerank_score = $this->calculateRerankScore($query, $result);
            return $result;
        })->sortByDesc('rerank_score');
    }
    
    private function calculateRerankScore(string $query, $result): float
    {
        $score = $result->similarity; // Base similarity score
        
        // Boost recent content
        $daysSinceCreated = now()->diffInDays($result->created_at);
        $recencyBoost = max(0, 1 - ($daysSinceCreated / 365));
        $score += $recencyBoost * 0.1;
        
        // Boost high-authority sources
        $authorityBoost = $this->getSourceAuthority($result->source);
        $score += $authorityBoost * 0.2;
        
        // Boost exact keyword matches
        $keywordBoost = $this->calculateKeywordMatch($query, $result->content);
        $score += $keywordBoost * 0.15;
        
        return $score;
    }
}
```

### Multi-Source RAG

Combine information from multiple knowledge sources:

```php
public function generateMultiSourceRag(string $query): array
{
    $contexts = [];
    
    // Search different knowledge namespaces
    $namespaces = ['policies', 'products', 'troubleshooting', 'faqs'];
    
    foreach ($namespaces as $namespace) {
        $results = VectorMemory::search(
            agentName: 'multi_source',
            query: $query,
            namespace: $namespace,
            limit: 2,
            threshold: 0.7
        );
        
        if ($results->isNotEmpty()) {
            $contexts[$namespace] = [
                'content' => $results->pluck('content')->implode("\n\n"),
                'sources' => $results->pluck('source')->unique()->toArray(),
                'count' => $results->count(),
            ];
        }
    }
    
    // Synthesize contexts
    $synthesizedContext = $this->synthesizeContexts($contexts, $query);
    
    return [
        'context' => $synthesizedContext,
        'source_breakdown' => $contexts,
        'total_sources' => collect($contexts)->flatMap(fn($c) => $c['sources'])->unique()->count(),
    ];
}
```

## 📊 Memory Analytics & Optimization

### Memory Usage Analytics

Monitor and optimize your vector memory:

```php
use AaronLumsden\LaravelAiADK\Facades\VectorMemory;

// Get comprehensive memory statistics
$stats = VectorMemory::getStatistics('customer_support');

echo "Total Memories: {$stats['total_memories']}\n";
echo "Total Tokens: {$stats['total_tokens']}\n";
echo "Average Similarity in Recent Searches: {$stats['avg_similarity']}\n";

// Source breakdown
foreach ($stats['sources'] as $source => $count) {
    echo "Source '{$source}': {$count} memories\n";
}

// Provider breakdown
foreach ($stats['providers'] as $provider => $count) {
    echo "Provider '{$provider}': {$count} memories\n";
}
```

### Search Quality Analysis

Track and improve search relevance:

```php
class SearchQualityAnalyzer
{
    public function analyzeSearchQuality(string $agentName, int $days = 7): array
    {
        $searches = $this->getRecentSearches($agentName, $days);
        
        $analysis = [
            'total_searches' => $searches->count(),
            'avg_results_returned' => $searches->avg('results_count'),
            'avg_similarity_score' => $searches->avg('top_similarity'),
            'no_results_rate' => $searches->where('results_count', 0)->count() / $searches->count(),
            'low_quality_rate' => $searches->where('top_similarity', '<', 0.7)->count() / $searches->count(),
        ];
        
        // Identify common queries with poor results
        $poorQueries = $searches->where('top_similarity', '<', 0.6)->pluck('query');
        $analysis['improvement_opportunities'] = $this->identifyImprovementOpportunities($poorQueries);
        
        return $analysis;
    }
    
    public function suggestOptimizations(array $analysis): array
    {
        $suggestions = [];
        
        if ($analysis['no_results_rate'] > 0.1) {
            $suggestions[] = 'Consider lowering similarity threshold or adding more diverse content';
        }
        
        if ($analysis['low_quality_rate'] > 0.3) {
            $suggestions[] = 'Review and improve content chunking strategy';
            $suggestions[] = 'Consider adding more specific metadata for better filtering';
        }
        
        if ($analysis['avg_results_returned'] < 2) {
            $suggestions[] = 'Increase search result limit or expand knowledge base';
        }
        
        return $suggestions;
    }
}
```

### Memory Cleanup and Maintenance

Keep your vector memory optimized:

```php
class VectorMemoryMaintenance
{
    public function runMaintenance(string $agentName): array
    {
        $results = [];
        
        // Remove duplicate content
        $duplicatesRemoved = $this->removeDuplicates($agentName);
        $results['duplicates_removed'] = $duplicatesRemoved;
        
        // Remove outdated information
        $outdatedRemoved = $this->removeOutdatedContent($agentName);
        $results['outdated_removed'] = $outdatedRemoved;
        
        // Consolidate fragmented memories
        $consolidated = $this->consolidateFragmentedMemories($agentName);
        $results['memories_consolidated'] = $consolidated;
        
        // Update embedding models if needed
        $updated = $this->updateEmbeddings($agentName);
        $results['embeddings_updated'] = $updated;
        
        return $results;
    }
    
    private function removeDuplicates(string $agentName): int
    {
        // Find memories with identical or very similar content
        $memories = VectorMemory::getAllMemories($agentName);
        $duplicates = [];
        
        foreach ($memories as $memory) {
            $similar = VectorMemory::search(
                agentName: $agentName,
                query: $memory->content,
                threshold: 0.95,
                limit: 5
            );
            
            // If we find highly similar content, mark for removal
            $duplicateIds = $similar->where('id', '!=', $memory->id)
                                  ->where('similarity', '>', 0.95)
                                  ->pluck('id');
                                  
            $duplicates = array_merge($duplicates, $duplicateIds->toArray());
        }
        
        // Remove duplicates
        $uniqueDuplicates = array_unique($duplicates);
        foreach ($uniqueDuplicates as $duplicateId) {
            VectorMemory::deleteById($duplicateId);
        }
        
        return count($uniqueDuplicates);
    }
}
```

## 🔐 Security & Privacy

### Data Privacy Controls

Implement privacy controls for sensitive information:

```php
class PrivacyAwareVectorMemory
{
    public function storeWithPrivacy(
        string $agentName,
        string $content,
        array $metadata = [],
        string $privacyLevel = 'standard'
    ): Collection {
        // Sanitize content based on privacy level
        $sanitizedContent = $this->sanitizeContent($content, $privacyLevel);
        
        // Add privacy metadata
        $metadata['privacy_level'] = $privacyLevel;
        $metadata['contains_pii'] = $this->detectPII($content);
        $metadata['retention_days'] = $this->getRetentionPeriod($privacyLevel);
        
        return VectorMemory::store(
            agentName: $agentName,
            content: $sanitizedContent,
            metadata: $metadata
        );
    }
    
    private function sanitizeContent(string $content, string $privacyLevel): string
    {
        switch ($privacyLevel) {
            case 'high':
                // Remove all PII
                $content = $this->removePII($content);
                break;
                
            case 'medium':
                // Hash/anonymize PII
                $content = $this->anonymizePII($content);
                break;
                
            case 'standard':
            default:
                // Mark but don't remove PII
                $content = $this->markPII($content);
                break;
        }
        
        return $content;
    }
    
    private function detectPII(string $content): bool
    {
        $piiPatterns = [
            '/\b[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Z|a-z]{2,}\b/', // Email
            '/\b\d{3}-\d{2}-\d{4}\b/', // SSN
            '/\b\d{3}[-.]?\d{3}[-.]?\d{4}\b/', // Phone
            '/\b\d{4}[-\s]?\d{4}[-\s]?\d{4}[-\s]?\d{4}\b/', // Credit Card
        ];
        
        foreach ($piiPatterns as $pattern) {
            if (preg_match($pattern, $content)) {
                return true;
            }
        }
        
        return false;
    }
}
```

### Access Controls

Implement fine-grained access controls:

```php
class SecureVectorMemory
{
    public function searchWithPermissions(
        string $agentName,
        string $query,
        User $user,
        array $options = []
    ): Collection {
        // Apply user-based filters
        $filters = $this->buildPermissionFilters($user);
        
        // Add permission filters to search
        $options['filters'] = array_merge($options['filters'] ?? [], $filters);
        
        $results = VectorMemory::search($agentName, $query, $options);
        
        // Post-process results based on permissions
        return $this->filterResultsByPermissions($results, $user);
    }
    
    private function buildPermissionFilters(User $user): array
    {
        $filters = [];
        
        // Restrict based on user role
        if (!$user->hasRole('admin')) {
            $filters['metadata.privacy_level'] = ['standard', 'low'];
        }
        
        // Restrict to user's department
        if ($user->department) {
            $filters['metadata.department'] = $user->department;
        }
        
        // Restrict based on customer access
        if ($user->hasRole('customer')) {
            $filters['metadata.customer_id'] = $user->id;
        }
        
        return $filters;
    }
}
```

## 🎯 Best Practices

### 1. Content Chunking Strategy

Break content into meaningful chunks:

```php
class SmartContentChunker
{
    public function chunkContent(string $content, string $contentType = 'text'): array
    {
        switch ($contentType) {
            case 'policy_document':
                return $this->chunkBySection($content);
                
            case 'conversation':
                return $this->chunkByTopic($content);
                
            case 'product_description':
                return $this->chunkByFeature($content);
                
            default:
                return $this->chunkBySentence($content, 500); // 500 char chunks
        }
    }
    
    private function chunkBySection(string $content): array
    {
        // Split by headers, maintain context
        $sections = preg_split('/\n(?=#{1,3}\s)/', $content);
        
        return collect($sections)->map(function ($section, $index) {
            return [
                'content' => trim($section),
                'chunk_type' => 'section',
                'chunk_index' => $index,
                'title' => $this->extractTitle($section),
            ];
        })->toArray();
    }
}
```

### 2. Embedding Model Selection

Choose the right embedding model for your use case:

```php
// config/agent-adk.php
'embedding' => [
    'default_provider' => 'openai',
    'providers' => [
        'openai' => [
            'model' => 'text-embedding-3-large', // Higher quality
            'dimensions' => 3072,
            'use_for' => ['general', 'customer_support', 'knowledge_base'],
        ],
        'cohere' => [
            'model' => 'embed-multilingual-v3.0', // Multilingual support
            'use_for' => ['multilingual', 'international_support'],
        ],
        'local' => [
            'model' => 'all-MiniLM-L6-v2', // Fast, good quality
            'use_for' => ['development', 'privacy_sensitive'],
        ],
    ],
];
```

### 3. Metadata Strategy

Use metadata effectively for filtering and organization:

```php
$metadata = [
    // Core categorization
    'type' => 'customer_interaction',
    'category' => 'support_ticket',
    'subcategory' => 'billing_issue',
    
    // Temporal information
    'created_date' => now()->toISOString(),
    'last_updated' => now()->toISOString(),
    'valid_until' => now()->addMonths(6)->toISOString(),
    
    // Relevance scoring
    'importance' => 'high', // high, medium, low
    'frequency_accessed' => 0,
    'success_rate' => 0.95, // For solutions
    
    // Access control
    'visibility' => 'internal', // public, internal, restricted
    'department' => 'customer_service',
    'access_level' => 'standard',
    
    // Content characteristics
    'language' => 'en',
    'content_length' => strlen($content),
    'confidence' => 0.9,
    
    // Business context
    'customer_tier' => 'premium',
    'product_line' => 'electronics',
    'region' => 'north_america',
];
```

## 🎉 Real-World Examples

Ready to see vector memory in action?

- **[Customer Support Knowledge Base](examples/customer-support-memory.md)** - Policies, procedures, and customer history
- **[E-commerce Product Recommendations](examples/ecommerce-memory.md)** - Product data and user preferences
- **[Content Creation Assistant](examples/content-memory.md)** - Brand guidelines and content libraries
- **[Technical Documentation Search](examples/technical-memory.md)** - Code examples and troubleshooting guides

---

<p align="center">
<strong>Ready to configure and deploy your agents?</strong><br>
<a href="configuration.md">Next: Configuration & Deployment →</a>
</p>