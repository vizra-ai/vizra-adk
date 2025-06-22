<?php

namespace Vizra\VizraADK\Tests\Integration;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Collection;
use Vizra\VizraADK\Agents\BaseLlmAgent;
use Vizra\VizraADK\Contracts\ToolInterface;
use Vizra\VizraADK\Services\DocumentChunker;
use Vizra\VizraADK\Services\VectorMemoryManager;
use Vizra\VizraADK\System\AgentContext;
use Vizra\VizraADK\Tests\TestCase;
use Vizra\VizraADK\VectorMemory\Providers\OpenAIEmbeddingProvider;

/**
 * Integration tests for vector/RAG functionality in real agent scenarios
 */
class AgentVectorMemoryIntegrationTest extends TestCase
{
    use DatabaseTransactions;

    protected KnowledgeBaseAgent $agent;

    protected VectorMemoryManager $vectorManager;

    protected function setUp(): void
    {
        parent::setUp();

        // Skip if no OpenAI API key is available
        if (! config('vizra-adk.llm_providers.openai.api_key')) {
            $this->markTestSkipped('OpenAI API key not configured');
        }

        // Use real embedding provider for integration tests
        $embeddingProvider = new OpenAIEmbeddingProvider(
            config('vizra-adk.llm_providers.openai.api_key'),
            'text-embedding-3-small'
        );

        $chunker = new DocumentChunker;

        $this->vectorManager = new VectorMemoryManager($embeddingProvider, $chunker);
        $this->app->instance(VectorMemoryManager::class, $this->vectorManager);

        // Create agent instance
        $this->agent = new KnowledgeBaseAgent;
    }

    /**
     * Test building a knowledge base and using it for RAG
     */
    public function test_can_build_and_query_knowledge_base()
    {
        // Build a knowledge base about Laravel
        $documents = [
            [
                'content' => 'Laravel is a web application framework with expressive, elegant syntax. It was created by Taylor Otwell and follows the model-view-controller (MVC) architectural pattern.',
                'metadata' => ['topic' => 'overview', 'source' => 'laravel_intro.md'],
            ],
            [
                'content' => 'Eloquent ORM is Laravel\'s built-in object-relational mapper. It provides an ActiveRecord implementation for working with your database. Each database table has a corresponding Model.',
                'metadata' => ['topic' => 'database', 'source' => 'eloquent_guide.md'],
            ],
            [
                'content' => 'Blade is the simple, yet powerful templating engine that is included with Laravel. Unlike some PHP templating engines, Blade does not restrict you from using plain PHP code in your templates.',
                'metadata' => ['topic' => 'views', 'source' => 'blade_docs.md'],
            ],
            [
                'content' => 'Laravel\'s service container is a powerful tool for managing class dependencies and performing dependency injection. Dependency injection is a fancy phrase that essentially means this: class dependencies are "injected" into the class via the constructor or, in some cases, "setter" methods.',
                'metadata' => ['topic' => 'architecture', 'source' => 'container_docs.md'],
            ],
            [
                'content' => 'Artisan is the command-line interface included with Laravel. Artisan exists at the root of your application as the artisan script and provides a number of helpful commands that can assist you while you build your application.',
                'metadata' => ['topic' => 'cli', 'source' => 'artisan_docs.md'],
            ],
        ];

        // Store documents
        foreach ($documents as $doc) {
            $result = $this->agent->addToKnowledgeBase($doc['content'], $doc['metadata']);
            $this->assertInstanceOf(Collection::class, $result);
            $this->assertGreaterThan(0, $result->count());
        }

        // Test various queries
        $queries = [
            'What is Eloquent?' => 'Eloquent ORM',
            'How does Laravel handle templates?' => 'Blade',
            'What is dependency injection in Laravel?' => 'service container',
            'Tell me about Laravel CLI' => 'Artisan',
        ];

        foreach ($queries as $query => $expectedContent) {
            $results = $this->agent->queryKnowledgeBase($query);

            $this->assertInstanceOf(Collection::class, $results);
            $this->assertGreaterThan(0, $results->count());

            // Check if the most relevant result contains expected content
            $topResult = $results->first();
            $this->assertStringContainsString($expectedContent, $topResult->content);
            $this->assertGreaterThan(0.5, $topResult->similarity);
        }
    }

    /**
     * Test using RAG in agent responses
     */
    public function test_agent_can_use_rag_for_responses()
    {
        // Build knowledge base
        $this->agent->addToKnowledgeBase(
            'The Vizra ADK supports three LLM providers: OpenAI (GPT models), Anthropic (Claude models), and Google (Gemini models). You can configure the default provider in the config/vizra.php file.',
            ['topic' => 'llm_providers']
        );

        $this->agent->addToKnowledgeBase(
            'To create a new agent, use the command: php artisan vizra:make:agent AgentName. This will create a new agent class in the app/Agents directory.',
            ['topic' => 'agent_creation']
        );

        $this->agent->addToKnowledgeBase(
            'Tools in Vizra ADK implement the ToolInterface and must define two methods: definition() which returns the tool schema, and execute() which performs the tool action.',
            ['topic' => 'tools']
        );

        // Test RAG context generation
        $context = $this->agent->generateRagContext('How do I create a new agent?');

        $this->assertArrayHasKey('context', $context);
        $this->assertArrayHasKey('sources', $context);
        $this->assertStringContainsString('php artisan vizra:make:agent', $context['context']);

        // Test with multiple relevant documents
        $context = $this->agent->generateRagContext('What LLM providers are supported?');

        $this->assertStringContainsString('OpenAI', $context['context']);
        $this->assertStringContainsString('Anthropic', $context['context']);
        $this->assertStringContainsString('Google', $context['context']);
    }

    /**
     * Test namespace isolation
     */
    public function test_namespaces_provide_isolation()
    {
        // Store in different namespaces
        $this->agent->vector()->addChunk(
            $this->agent->getName(),
            'Public information about the company',
            ['access' => 'public'],
            'public_docs'
        );

        $this->agent->vector()->addChunk(
            $this->agent->getName(),
            'Internal confidential information',
            ['access' => 'restricted'],
            'internal_docs'
        );

        $this->agent->vector()->addChunk(
            $this->agent->getName(),
            'More public information',
            ['access' => 'public'],
            'public_docs'
        );

        // Search only public namespace
        $publicResults = $this->agent->vector()->search(
            $this->agent->getName(),
            'information',
            'public_docs'
        );

        // Search only internal namespace
        $internalResults = $this->agent->vector()->search(
            $this->agent->getName(),
            'information',
            'internal_docs'
        );

        // Verify isolation
        $this->assertEquals(2, $publicResults->count());
        $this->assertEquals(1, $internalResults->count());

        // Verify content
        foreach ($publicResults as $result) {
            $this->assertStringContainsString('public', strtolower($result->content));
        }

        $this->assertStringContainsString('confidential', $internalResults->first()->content);
    }

    /**
     * Test document chunking with real content
     */
    public function test_document_chunking_with_long_content()
    {
        $longDocument = <<<DOC
        Laravel is a web application framework with expressive, elegant syntax. We believe development must be an enjoyable and creative experience to be truly fulfilling. Laravel takes the pain out of development by easing common tasks used in many web projects.

        Laravel provides powerful features including:

        Routing: Laravel provides a simple, expressive method of defining routes. Routes are defined in your route files, which are located in the routes directory. These files are automatically loaded by your application's App\Providers\RouteServiceProvider. The routes/web.php file defines routes that are for your web interface.

        Middleware: Middleware provide a convenient mechanism for inspecting and filtering HTTP requests entering your application. For example, Laravel includes a middleware that verifies the user of your application is authenticated. If the user is not authenticated, the middleware will redirect the user to your application's login screen.

        Controllers: Instead of defining all of your request handling logic as closures in your route files, you may wish to organize this behavior using "controller" classes. Controllers can group related request handling logic into a single class. For example, a UserController class might handle all incoming requests related to users, including showing, creating, updating, and deleting users.

        Eloquent ORM: The Eloquent ORM included with Laravel provides a beautiful, simple ActiveRecord implementation for working with your database. Each database table has a corresponding "Model" which is used to interact with that table. In addition to retrieving records from the database table, Eloquent models allow you to insert, update, and delete records from the table as well.

        Blade Templates: Blade is the simple, yet powerful templating engine that is included with Laravel. Unlike some PHP templating engines, Blade does not restrict you from using plain PHP code in your templates. In fact, all Blade templates are compiled into plain PHP code and cached until they are modified, meaning Blade adds essentially zero overhead to your application.
        DOC;

        $result = $this->agent->addToKnowledgeBase($longDocument, ['source' => 'laravel_features.txt']);

        // Should be chunked into multiple pieces
        $this->assertInstanceOf(Collection::class, $result);
        $this->assertGreaterThan(1, $result->count());

        // Test searching across chunks
        $routingResults = $this->agent->queryKnowledgeBase('How does routing work in Laravel?');
        $this->assertGreaterThan(0, $routingResults->count());
        $this->assertStringContainsString('Routes are defined', $routingResults->first()->content);

        $ormResults = $this->agent->queryKnowledgeBase('What is Eloquent?');
        $this->assertGreaterThan(0, $ormResults->count());
        $this->assertStringContainsString('ActiveRecord', $ormResults->first()->content);
    }

    /**
     * Test similarity threshold filtering
     */
    public function test_similarity_threshold_filtering()
    {
        // Add some documents
        $this->agent->addToKnowledgeBase('PHP is a server-side scripting language.', ['topic' => 'php']);
        $this->agent->addToKnowledgeBase('JavaScript is a client-side scripting language.', ['topic' => 'javascript']);
        $this->agent->addToKnowledgeBase('Python is a high-level programming language.', ['topic' => 'python']);

        // Search with high threshold
        $highThresholdResults = $this->agent->rag()->search(
            $this->agent->getName(),
            'Tell me about PHP programming',
            'knowledge_base',
            10,
            0.8 // High threshold
        );

        // Search with low threshold
        $lowThresholdResults = $this->agent->rag()->search(
            $this->agent->getName(),
            'Tell me about PHP programming',
            'knowledge_base',
            10,
            0.3 // Low threshold
        );

        // High threshold should return fewer results
        $this->assertLessThan($lowThresholdResults->count(), $highThresholdResults->count());

        // Most relevant result should be about PHP
        if ($highThresholdResults->count() > 0) {
            $this->assertStringContainsString('PHP', $highThresholdResults->first()->content);
        }
    }

    /**
     * Test metadata filtering and source tracking
     */
    public function test_metadata_and_source_tracking()
    {
        // Add documents with metadata
        $this->agent->vector()->addDocument(
            $this->agent->getName(),
            'Laravel uses migrations to manage database schema.',
            ['topic' => 'database', 'version' => '10.x', 'type' => 'documentation'],
            'knowledge_base',
            'laravel_10_docs.pdf'
        );

        $this->agent->vector()->addDocument(
            $this->agent->getName(),
            'Laravel 9 introduced new features for database management.',
            ['topic' => 'database', 'version' => '9.x', 'type' => 'changelog'],
            'knowledge_base',
            'laravel_9_changelog.md'
        );

        // Get statistics
        $stats = $this->agent->vector()->getStatistics($this->agent->getName(), 'knowledge_base');

        $this->assertArrayHasKey('sources', $stats);
        $this->assertArrayHasKey('laravel_10_docs.pdf', $stats['sources']);
        $this->assertArrayHasKey('laravel_9_changelog.md', $stats['sources']);

        // Delete by source
        $deleted = $this->agent->vector()->deleteMemoriesBySource(
            $this->agent->getName(),
            'laravel_9_changelog.md',
            'knowledge_base'
        );

        $this->assertGreaterThan(0, $deleted);

        // Verify deletion
        $remainingStats = $this->agent->vector()->getStatistics($this->agent->getName(), 'knowledge_base');
        $this->assertArrayNotHasKey('laravel_9_changelog.md', $remainingStats['sources']);
        $this->assertArrayHasKey('laravel_10_docs.pdf', $remainingStats['sources']);
    }
}

/**
 * Example agent that uses vector memory for knowledge base functionality
 */
class KnowledgeBaseAgent extends BaseLlmAgent
{
    protected string $name = 'knowledge-base-agent';

    protected string $description = 'An agent that maintains and queries a knowledge base';

    protected string $instructions = 'You are a helpful assistant with access to a knowledge base. Use the knowledge base to provide accurate information.';

    protected string $model = 'gpt-3.5-turbo';

    protected array $tools = [KnowledgeSearchTool::class];

    /**
     * Add document to the knowledge base
     */
    public function addToKnowledgeBase(string $content, array $metadata = []): Collection
    {
        return $this->vector()->addDocument(
            $this->getName(),
            $content,
            $metadata,
            'knowledge_base'
        );
    }

    /**
     * Query the knowledge base
     */
    public function queryKnowledgeBase(string $query, int $limit = 5): Collection
    {
        return $this->rag()->search(
            $this->getName(),
            $query,
            'knowledge_base',
            $limit,
            0.5
        );
    }

    /**
     * Generate RAG context for a query
     */
    public function generateRagContext(string $query): array
    {
        return $this->rag()->generateRagContext(
            $this->getName(),
            $query,
            'knowledge_base',
            5
        );
    }

    /**
     * Override execute to include RAG context
     */
    public function execute(string $input, AgentContext $context): mixed
    {
        // Generate RAG context
        $ragContext = $this->generateRagContext($input);

        // Add context to the input
        if (! empty($ragContext['context'])) {
            $enhancedInput = "Context from knowledge base:\n".$ragContext['context']."\n\nUser query: ".$input;
        } else {
            $enhancedInput = $input;
        }

        // Call parent execute with enhanced input
        return parent::execute($enhancedInput, $context);
    }
}

/**
 * Example tool that searches the knowledge base
 */
class KnowledgeSearchTool implements ToolInterface
{
    public function definition(): array
    {
        return [
            'type' => 'function',
            'function' => [
                'name' => 'search_knowledge',
                'description' => 'Search the knowledge base for relevant information',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'query' => [
                            'type' => 'string',
                            'description' => 'The search query',
                        ],
                        'limit' => [
                            'type' => 'integer',
                            'description' => 'Maximum number of results to return',
                            'default' => 3,
                        ],
                    ],
                    'required' => ['query'],
                ],
            ],
        ];
    }

    public function execute(array $parameters): mixed
    {
        $agent = app(KnowledgeBaseAgent::class);
        $results = $agent->queryKnowledgeBase(
            $parameters['query'],
            $parameters['limit'] ?? 3
        );

        if ($results->isEmpty()) {
            return 'No relevant information found in the knowledge base.';
        }

        return $results->map(function ($result) {
            return [
                'content' => $result->content,
                'similarity' => round($result->similarity, 2),
                'source' => $result->source ?? 'knowledge_base',
            ];
        })->toArray();
    }
}
