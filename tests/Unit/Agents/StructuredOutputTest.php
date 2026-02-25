<?php

use Prism\Prism\Contracts\Schema;
use Prism\Prism\Enums\FinishReason;
use Prism\Prism\Facades\Prism;
use Prism\Prism\Schema\ObjectSchema;
use Prism\Prism\Schema\StringSchema;
use Prism\Prism\Testing\StructuredResponseFake;
use Prism\Prism\ValueObjects\Meta;
use Prism\Prism\ValueObjects\Usage;
use Vizra\VizraADK\Agents\BaseLlmAgent;
use Vizra\VizraADK\System\AgentContext;

beforeEach(function () {
    $this->app[\Prism\Prism\PrismManager::class]->extend('mock', function () {
        return new class extends \Prism\Prism\Providers\Provider {};
    });
});

it('returns structured data when agent has schema and Prism returns StructuredResponse', function () {
    $schema = new ObjectSchema(
        name: 'result',
        description: 'Structured result',
        properties: [
            new StringSchema('name', 'The result name'),
            new StringSchema('value', 'The result value'),
        ],
        requiredFields: ['name', 'value']
    );

    $structuredData = [
        'name' => 'test_result',
        'value' => '42',
    ];

    $fakeResponse = StructuredResponseFake::make()
        ->withText(json_encode($structuredData, JSON_THROW_ON_ERROR))
        ->withStructured($structuredData)
        ->withFinishReason(FinishReason::Stop)
        ->withUsage(new Usage(10, 20))
        ->withMeta(new Meta('fake-1', 'fake-model'));

    Prism::fake([$fakeResponse]);

    $agent = new StructuredOutputTestAgent($schema);
    $context = new AgentContext('structured-output-test-session');

    $result = $agent->execute('Generate structured output', $context);

    expect($result)->toBeArray();
    expect($result)->toBe($structuredData);
    expect($result['name'])->toBe('test_result');
    expect($result['value'])->toBe('42');
});

it('returns text when agent has no schema', function () {
    $fakeResponse = \Prism\Prism\Testing\TextResponseFake::make()
        ->withText('Plain text response')
        ->withUsage(new Usage(10, 20))
        ->withMeta(new Meta('fake-1', 'fake-model'));

    Prism::fake([$fakeResponse]);

    $agent = new TextOnlyTestAgent;
    $context = new AgentContext('text-output-test-session');

    $result = $agent->execute('Generate text', $context);

    expect($result)->toBeString();
    expect($result)->toBe('Plain text response');
});

it('returns text when StructuredResponse has empty structured data', function () {
    $schema = new ObjectSchema(
        name: 'result',
        description: 'Structured result',
        properties: [
            new StringSchema('name', 'The result name'),
        ],
        requiredFields: ['name']
    );

    $fakeResponse = StructuredResponseFake::make()
        ->withText('Fallback text when structured is empty')
        ->withStructured([])
        ->withFinishReason(FinishReason::Stop)
        ->withUsage(new Usage(10, 20))
        ->withMeta(new Meta('fake-1', 'fake-model'));

    Prism::fake([$fakeResponse]);

    $agent = new StructuredOutputTestAgent($schema);
    $context = new AgentContext('empty-structured-test-session');

    $result = $agent->execute('Generate', $context);

    expect($result)->toBeString();
    expect($result)->toBe('Fallback text when structured is empty');
});

/**
 * Test agent that returns structured output via schema
 */
class StructuredOutputTestAgent extends BaseLlmAgent
{
    public function __construct(
        private readonly Schema $schema
    ) {
        parent::__construct();
    }

    protected string $name = 'structured-output-test-agent';

    protected string $description = 'Test agent with schema for structured output';

    protected string $instructions = 'Return structured data.';

    protected string $model = 'gpt-4o';

    protected array $tools = [];

    public function getSchema(): ?Schema
    {
        return $this->schema;
    }
}

/**
 * Test agent without schema - returns text
 */
class TextOnlyTestAgent extends BaseLlmAgent
{
    protected string $name = 'text-only-test-agent';

    protected string $description = 'Test agent without schema';

    protected string $instructions = 'Return plain text.';

    protected string $model = 'gpt-4o';

    protected array $tools = [];
}
