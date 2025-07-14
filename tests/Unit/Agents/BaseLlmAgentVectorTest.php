<?php

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\App;
use Vizra\VizraADK\Agents\BaseLlmAgent;
use Vizra\VizraADK\Contracts\EmbeddingProviderInterface;
use Vizra\VizraADK\Models\VectorMemory;
use Vizra\VizraADK\Services\DocumentChunker;
use Vizra\VizraADK\Services\VectorMemoryManager;
use Vizra\VizraADK\Services\AgentVectorProxy;

beforeEach(function () {
    // Set up mocks for VectorMemoryManager dependencies
    $this->mockEmbeddingProvider = Mockery::mock(EmbeddingProviderInterface::class);
    $this->mockEmbeddingProvider->shouldReceive('getProviderName')->andReturn('test-provider');
    $this->mockEmbeddingProvider->shouldReceive('getModel')->andReturn('test-model');
    $this->mockEmbeddingProvider->shouldReceive('getDimensions')->andReturn(384);

    $this->mockChunker = Mockery::mock(DocumentChunker::class);

    // Create a real VectorMemoryManager instance with mocked dependencies
    $this->vectorMemoryManager = new VectorMemoryManager(
        $this->mockEmbeddingProvider,
        $this->mockChunker
    );

    // Bind to container
    App::instance(VectorMemoryManager::class, $this->vectorMemoryManager);

    // Create test agent
    $this->agent = new TestAgentWithVector;
});

/**
 * Unit tests for vector() method
 */
describe('vector() method', function () {
    it('returns AgentVectorProxy instance', function () {
        $vector = $this->agent->testVector();

        expect($vector)->toBeInstanceOf(AgentVectorProxy::class);
        expect($vector->getAgentClass())->toBe(TestAgentWithVector::class);
    });

    it('returns new proxy instances on multiple calls', function () {
        $first = $this->agent->testVector();
        $second = $this->agent->testVector();
        $third = $this->agent->testVector();

        expect($first)->not->toBe($second);
        expect($second)->not->toBe($third);
        // But they should all wrap the same agent class
        expect($first->getAgentClass())->toBe($second->getAgentClass());
        expect($second->getAgentClass())->toBe($third->getAgentClass());
    });

    it('is a public method', function () {
        $reflection = new ReflectionClass(BaseLlmAgent::class);
        $method = $reflection->getMethod('vector');

        expect($method->isPublic())->toBeTrue();
    });
});

/**
 * Unit tests for rag() method
 */
describe('rag() method', function () {
    it('returns AgentVectorProxy instance', function () {
        $rag = $this->agent->testRag();

        expect($rag)->toBeInstanceOf(AgentVectorProxy::class);
    });

    it('returns new proxy instance different from vector()', function () {
        $vector = $this->agent->testVector();
        $rag = $this->agent->testRag();

        expect($rag)->not->toBe($vector);
        expect($rag->getAgentClass())->toBe($vector->getAgentClass());
    });

    it('is a public method', function () {
        $reflection = new ReflectionClass(BaseLlmAgent::class);
        $method = $reflection->getMethod('rag');

        expect($method->isPublic())->toBeTrue();
    });
});

/**
 * Integration tests for vector memory usage
 */
describe('vector memory usage', function () {
    it('can store documents', function () {
        // Arrange
        $content = 'This is a test document about Laravel.';
        $chunks = ['This is a test document', 'about Laravel.'];
        $mockEmbedding = array_fill(0, 384, 0.1);

        $this->mockChunker->shouldReceive('chunk')
            ->with($content)
            ->once()
            ->andReturn($chunks);

        $this->mockEmbeddingProvider->shouldReceive('embed')
            ->twice()
            ->andReturn([$mockEmbedding]);

        // Act
        $result = $this->agent->storeDocument($content);

        // Assert
        expect($result)->toBeInstanceOf(Collection::class);
        expect($result)->toHaveCount(2);
    });

    it('can search documents', function () {
        // Arrange
        $query = 'Laravel framework';
        $queryEmbedding = array_fill(0, 384, 0.5);

        // Create test memory
        VectorMemory::create([
            'agent_name' => $this->agent->getName(),
            'namespace' => 'default',
            'content' => 'Laravel is a PHP web framework.',
            'embedding_provider' => 'test-provider',
            'embedding_model' => 'test-model',
            'embedding_dimensions' => 384,
            'embedding_vector' => array_fill(0, 384, 0.4),
            'embedding_norm' => 1.0,
            'content_hash' => hash('sha256', $this->agent->getName().':Laravel is a PHP'),
            'token_count' => 10,
        ]);

        $this->mockEmbeddingProvider->shouldReceive('embed')
            ->with($query)
            ->once()
            ->andReturn([$queryEmbedding]);

        // Act
        $results = $this->agent->searchDocuments($query);

        // Assert
        expect($results)->toBeInstanceOf(Collection::class);
        expect($results->count())->toBeGreaterThan(0);
        expect($results->first()->content)->toContain('Laravel');
    });

    it('can generate RAG context', function () {
        // Arrange
        $query = 'What is MVC pattern?';
        $queryEmbedding = array_fill(0, 384, 0.5);

        // Create memories
        VectorMemory::create([
            'agent_name' => $this->agent->getName(),
            'namespace' => 'patterns',
            'content' => 'MVC stands for Model-View-Controller architectural pattern.',
            'metadata' => ['source' => 'patterns.pdf'],
            'source' => 'patterns.pdf',
            'embedding_provider' => 'test-provider',
            'embedding_model' => 'test-model',
            'embedding_dimensions' => 384,
            'embedding_vector' => array_fill(0, 384, 0.6),
            'embedding_norm' => 1.0,
            'content_hash' => hash('sha256', $this->agent->getName().':MVC stands for'),
            'token_count' => 10,
        ]);

        $this->mockEmbeddingProvider->shouldReceive('embed')
            ->with($query)
            ->once()
            ->andReturn([$queryEmbedding]);

        // Act
        $context = $this->agent->generateContext($query, 'patterns');

        // Assert
        expect($context)->toBeArray();
        expect($context)->toHaveKeys(['context', 'sources', 'query', 'total_results']);
        expect($context['context'])->toContain('MVC');
        expect($context['query'])->toBe($query);
    });
});

/**
 * Feature tests for namespaces
 */
describe('namespace functionality', function () {
    it('can organize memories by namespace', function () {
        $mockEmbedding = array_fill(0, 384, 0.1);

        $this->mockEmbeddingProvider->shouldReceive('embed')
            ->times(3)
            ->andReturn([$mockEmbedding]);

        // Store in different namespaces
        $this->agent->storeInNamespace('docs', 'Documentation content');
        $this->agent->storeInNamespace('code', 'Code example');
        $this->agent->storeInNamespace('docs', 'More documentation');

        // Check counts
        $docsCount = VectorMemory::where('agent_name', $this->agent->getName())
            ->where('namespace', 'docs')
            ->count();

        $codeCount = VectorMemory::where('agent_name', $this->agent->getName())
            ->where('namespace', 'code')
            ->count();

        expect($docsCount)->toBe(2);
        expect($codeCount)->toBe(1);
    });

    it('can delete memories by namespace', function () {
        $mockEmbedding = array_fill(0, 384, 0.1);

        $this->mockEmbeddingProvider->shouldReceive('embed')
            ->times(3)
            ->andReturn([$mockEmbedding]);

        // Store memories
        $this->agent->storeInNamespace('temp', 'Temp data 1');
        $this->agent->storeInNamespace('temp', 'Temp data 2');
        $this->agent->storeInNamespace('keep', 'Important data');

        // Delete temp namespace
        $deleted = $this->agent->clearNamespace('temp');

        expect($deleted)->toBe(2);

        // Verify deletion
        $tempCount = VectorMemory::where('agent_name', $this->agent->getName())
            ->where('namespace', 'temp')
            ->count();

        $keepCount = VectorMemory::where('agent_name', $this->agent->getName())
            ->where('namespace', 'keep')
            ->count();

        expect($tempCount)->toBe(0);
        expect($keepCount)->toBe(1);
    });
});

/**
 * Feature test for statistics
 */
it('can get vector memory statistics', function () {
    // Create test memories
    VectorMemory::create([
        'agent_name' => $this->agent->getName(),
        'namespace' => 'default',
        'content' => 'Memory 1',
        'source' => 'file1.txt',
        'embedding_provider' => 'openai',
        'embedding_model' => 'text-embedding-3-small',
        'embedding_dimensions' => 1536,
        'embedding_vector' => [],
        'content_hash' => hash('sha256', $this->agent->getName().':Memory 1'),
        'token_count' => 20,
    ]);

    VectorMemory::create([
        'agent_name' => $this->agent->getName(),
        'namespace' => 'default',
        'content' => 'Memory 2',
        'source' => 'file2.txt',
        'embedding_provider' => 'openai',
        'embedding_model' => 'text-embedding-3-small',
        'embedding_dimensions' => 1536,
        'embedding_vector' => [],
        'content_hash' => hash('sha256', $this->agent->getName().':Memory 2'),
        'token_count' => 30,
    ]);

    $stats = $this->agent->getVectorStats();

    expect($stats)->toBeArray();
    expect($stats['total_memories'])->toBe(2);
    expect($stats['total_tokens'])->toBe(50);
    expect($stats['providers'])->toHaveKey('openai');
    expect($stats['sources'])->toHaveCount(2);
});

/**
 * Real-world usage scenario
 */
it('supports real-world RAG workflow', function () {
    // Build knowledge base
    $knowledge = [
        'Laravel is a web application framework with expressive syntax.',
        'Eloquent ORM provides a beautiful ActiveRecord implementation.',
        'Blade templating engine makes writing templates enjoyable.',
        'Artisan is the command-line interface included with Laravel.',
        'Middleware provide a convenient mechanism for filtering HTTP requests.',
    ];

    $mockEmbedding = array_fill(0, 384, 0.1);
    $this->mockEmbeddingProvider->shouldReceive('embed')
        ->times(6) // 5 for storing + 1 for searching
        ->andReturn([$mockEmbedding]);

    // Store knowledge
    foreach ($knowledge as $fact) {
        $this->agent->testVector()->addChunk(
            [
                'content' => $fact,
                'metadata' => ['type' => 'documentation'],
                'namespace' => 'knowledge'
            ]
        );
    }

    // Search for information
    $query = 'What is the ORM in Laravel?';
    $context = $this->agent->testRag()->generateRagContext(
        $query,
        [
            'namespace' => 'knowledge',
            'limit' => 3
        ]
    );

    expect($context)->toBeArray();
    expect($context['context'])->toContain('Eloquent');
    expect($context['total_results'])->toBeGreaterThanOrEqual(1);
});

/**
 * Test agent implementation with public wrappers for protected methods
 */
class TestAgentWithVector extends BaseLlmAgent
{
    protected string $name = 'test-vector-agent';

    protected string $description = 'Test agent for vector memory';

    protected string $instructions = 'Test vector memory functionality';

    protected string $model = 'gpt-3.5-turbo';

    public function testVector(): AgentVectorProxy
    {
        return $this->vector();
    }

    public function testRag(): AgentVectorProxy
    {
        return $this->rag();
    }

    public function storeDocument(string $content, array $metadata = []): Collection
    {
        return $this->vector()->addDocument($content, $metadata);
    }

    public function searchDocuments(string $query, int $limit = 5): Collection
    {
        return $this->rag()->search($query, $limit);
    }

    public function generateContext(string $query, string $namespace = 'default'): array
    {
        return $this->rag()->generateRagContext($query, ['namespace' => $namespace]);
    }

    public function storeInNamespace(string $namespace, string $content): ?VectorMemory
    {
        return $this->vector()->addChunk([
            'content' => $content,
            'namespace' => $namespace
        ]);
    }

    public function clearNamespace(string $namespace): int
    {
        return $this->vector()->deleteMemories($namespace);
    }

    public function getVectorStats(string $namespace = 'default'): array
    {
        return $this->vector()->getStatistics($namespace);
    }
}
