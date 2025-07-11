<?php

use Illuminate\Support\Facades\App;
use Vizra\VizraADK\Agents\BaseLlmAgent;
use Vizra\VizraADK\Contracts\EmbeddingProviderInterface;
use Vizra\VizraADK\Facades\Agent;
use Vizra\VizraADK\Models\VectorMemory;
use Vizra\VizraADK\Services\DocumentChunker;
use Vizra\VizraADK\Services\VectorMemoryManager;
use Vizra\VizraADK\System\AgentContext;

beforeEach(function () {
    // Mock embedding provider for tests
    $mockEmbeddingProvider = Mockery::mock(EmbeddingProviderInterface::class);
    $mockEmbeddingProvider->shouldReceive('getProviderName')->andReturn('test-provider');
    $mockEmbeddingProvider->shouldReceive('getModel')->andReturn('test-model');
    $mockEmbeddingProvider->shouldReceive('getDimensions')->andReturn(384);
    $mockEmbeddingProvider->shouldReceive('embed')->andReturn([array_fill(0, 384, 0.5)]);

    // Create VectorMemoryManager with mocks
    $vectorMemoryManager = new VectorMemoryManager(
        $mockEmbeddingProvider,
        new DocumentChunker
    );

    App::instance(VectorMemoryManager::class, $vectorMemoryManager);

    // Register test agent
    Agent::build(DocumentationAgent::class)->register();
});

/**
 * Feature tests for vector/RAG functionality
 */
describe('Agent Vector/RAG Features', function () {

    it('allows agents to build and query their own knowledge base', function () {
        $agent = Agent::named('documentation-agent');

        // Agent should be able to add documents
        $agent->learnFrom('Laravel provides a clean, simple email API powered by the popular Symfony Mailer component.');
        $agent->learnFrom('You can configure mail drivers in the config/mail.php configuration file.');
        $agent->learnFrom('Laravel supports SMTP, Mailgun, Postmark, Amazon SES, and sendmail drivers.');

        // Agent should be able to recall information
        $context = new AgentContext('test-session');
        $response = $agent->execute('How can I send emails in Laravel?', $context);

        expect($response)->toContain('email');
        expect($response)->toContain('mail');
    });

    it('maintains separate knowledge bases for different agents', function () {
        // Register two different agents
        Agent::build(SupportAgent::class)->register();
        Agent::build(SalesAgent::class)->register();

        $supportAgent = Agent::named('support-agent');
        $salesAgent = Agent::named('sales-agent');

        // Each agent learns different things
        $supportAgent->learnFrom('Our support hours are Monday to Friday, 9 AM to 5 PM EST.');
        $supportAgent->learnFrom('For technical issues, please provide error logs and steps to reproduce.');

        $salesAgent->learnFrom('Our pricing starts at $29/month for the basic plan.');
        $salesAgent->learnFrom('Enterprise plans include custom SLA and dedicated support.');

        // Verify knowledge isolation
        $supportKnowledge = VectorMemory::where('agent_name', 'support-agent')->count();
        $salesKnowledge = VectorMemory::where('agent_name', 'sales-agent')->count();

        expect($supportKnowledge)->toBe(2);
        expect($salesKnowledge)->toBe(2);

        // Verify that each agent's knowledge is isolated by checking database directly
        $supportPricingCount = VectorMemory::where('agent_name', 'support-agent')
            ->where('content', 'LIKE', '%pricing%')
            ->count();

        $salesSupportHoursCount = VectorMemory::where('agent_name', 'sales-agent')
            ->where('content', 'LIKE', '%support hours%')
            ->count();

        // Support agent shouldn't have pricing info
        expect($supportPricingCount)->toBe(0);

        // Sales agent shouldn't have support hours info
        expect($salesSupportHoursCount)->toBe(0);
    });

    it('supports different namespaces for organizing knowledge', function () {
        $agent = Agent::named('documentation-agent');

        // Add to different namespaces
        $agent->learnInCategory('installation', 'Run composer require vizra/vizra-adk to install the package.');
        $agent->learnInCategory('installation', 'Add the service provider to config/app.php if not using auto-discovery.');
        $agent->learnInCategory('configuration', 'Configure your LLM provider in config/vizra.php.');
        $agent->learnInCategory('configuration', 'Set your API keys in the .env file.');

        // Query specific categories
        $installationDocs = $agent->searchCategory('installation', 'How to install?');
        $configDocs = $agent->searchCategory('configuration', 'How to configure?');

        expect($installationDocs)->toHaveCount(2);
        expect($configDocs)->toHaveCount(2);

        // Clear a specific category
        $agent->forgetCategory('installation');

        $remainingInstall = VectorMemory::where('agent_name', 'documentation-agent')
            ->where('namespace', 'installation')
            ->count();

        $remainingConfig = VectorMemory::where('agent_name', 'documentation-agent')
            ->where('namespace', 'configuration')
            ->count();

        expect($remainingInstall)->toBe(0);
        expect($remainingConfig)->toBe(2);
    });

    it('provides statistics about stored knowledge', function () {
        $agent = Agent::named('documentation-agent');

        // Add documents directly with addChunk to avoid chunking
        $vectorManager = App::make(VectorMemoryManager::class);
        $vectorManager->addChunk(
            $agent->getName(),
            'First document about Laravel.',
            ['type' => 'doc'],
            'default',
            'manual.pdf'
        );
        $vectorManager->addChunk(
            $agent->getName(),
            'Second document about PHP.',
            ['type' => 'doc'],
            'default',
            'tutorial.md'
        );
        $vectorManager->addChunk(
            $agent->getName(),
            'Third document about Laravel.',
            ['type' => 'doc'],
            'default',
            'manual.pdf'
        );

        $stats = $agent->getKnowledgeStats();

        expect($stats)->toHaveKey('total_memories');
        expect($stats)->toHaveKey('total_tokens');
        expect($stats)->toHaveKey('sources');
        expect($stats['total_memories'])->toBeGreaterThanOrEqual(3);
        expect($stats['sources'])->toHaveKey('manual.pdf');
        expect($stats['sources'])->toHaveKey('tutorial.md');
    });

    it('can use RAG to enhance responses with context', function () {
        $agent = Agent::named('documentation-agent');

        // Build knowledge base
        $agent->learnFrom(<<<'DOC'
        Vizra ADK Tools must implement the ToolInterface which requires two methods:
        1. definition() - Returns the tool schema for the LLM
        2. execute() - Performs the tool's action and returns the result
        
        Example tool implementation:
        ```php
        class WeatherTool implements ToolInterface {
            public function definition(): array {
                return [
                    'type' => 'function',
                    'function' => [
                        'name' => 'get_weather',
                        'description' => 'Get weather for a location',
                        'parameters' => [
                            'type' => 'object',
                            'properties' => [
                                'location' => ['type' => 'string']
                            ],
                            'required' => ['location']
                        ]
                    ]
                ];
            }
            
            public function execute(array $parameters): mixed {
                // Implementation here
            }
        }
        ```
        DOC);

        // Query should return relevant context
        $context = new AgentContext('test-session');
        $response = $agent->execute('How do I create a custom tool?', $context);

        expect($response)->toContain('ToolInterface');
        expect($response)->toContain('definition()');
        expect($response)->toContain('execute()');
    });
});

/**
 * Documentation agent that uses vector memory
 */
class DocumentationAgent extends BaseLlmAgent
{
    protected string $name = 'documentation-agent';

    protected string $description = 'An agent that manages documentation';

    protected string $instructions = 'You are a documentation assistant. Use your knowledge base to answer questions accurately.';

    protected string $model = 'gpt-3.5-turbo';

    public function learnFrom(string $content, array $metadata = []): void
    {
        $this->vector()->addDocument($this->getName(), $content, $metadata);
    }

    public function learnInCategory(string $category, string $content): void
    {
        $this->vector()->addChunk($this->getName(), $content, [], $category);
    }

    public function searchCategory(string $category, string $query): \Illuminate\Support\Collection
    {
        return $this->rag()->search($this->getName(), $query, $category);
    }

    public function forgetCategory(string $category): int
    {
        return $this->vector()->deleteMemories($this->getName(), $category);
    }

    public function getKnowledgeStats(): array
    {
        return $this->vector()->getStatistics($this->getName());
    }

    public function execute(mixed $input, AgentContext $context): mixed
    {
        // Mock implementation for testing
        $ragContext = $this->rag()->generateRagContext($this->getName(), $input);

        if (! empty($ragContext['context'])) {
            return 'Based on my knowledge: '.$ragContext['context'];
        }

        return "I don't have specific information about that in my knowledge base.";
    }
}

/**
 * Support agent example
 */
class SupportAgent extends BaseLlmAgent
{
    protected string $name = 'support-agent';

    protected string $description = 'Customer support agent';

    protected string $instructions = 'You are a helpful support agent.';

    protected string $model = 'gpt-3.5-turbo';

    public function learnFrom(string $content): void
    {
        $this->vector()->addChunk($this->getName(), $content);
    }
}

/**
 * Sales agent example
 */
class SalesAgent extends BaseLlmAgent
{
    protected string $name = 'sales-agent';

    protected string $description = 'Sales agent';

    protected string $instructions = 'You are a knowledgeable sales agent.';

    protected string $model = 'gpt-3.5-turbo';

    public function learnFrom(string $content): void
    {
        $this->vector()->addChunk($this->getName(), $content);
    }
}
