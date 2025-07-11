<?php

use Vizra\VizraADK\Agents\BaseLlmAgent;
use Vizra\VizraADK\Exceptions\AgentConfigurationException;
use Vizra\VizraADK\Services\AgentBuilder;
use Vizra\VizraADK\Services\AgentRegistry;
use Vizra\VizraADK\System\AgentContext;

beforeEach(function () {
    $this->registry = new AgentRegistry($this->app);
    $this->builder = new AgentBuilder($this->app, $this->registry);
});

it('can build agent from class', function () {
    $builder = $this->builder->build(TestBuilderAgent::class);

    expect($builder)->toBeInstanceOf(AgentBuilder::class);
});

it('throws exception for non existent class', function () {
    $this->builder->build('NonExistentClass');
})->throws(AgentConfigurationException::class, "Agent class 'NonExistentClass' not found.");

it('throws exception for invalid class', function () {
    expect(fn () => $this->builder->build(\stdClass::class))
        ->toThrow(AgentConfigurationException::class, 'must extend');
});

it('can define ad hoc agent', function () {
    $builder = $this->builder->define('test-agent');

    expect($builder)->toBeInstanceOf(AgentBuilder::class);
});

it('can set agent properties', function () {
    $builder = $this->builder
        ->define('custom-agent')
        ->description('A custom test agent')
        ->instructions('You are a helpful assistant')
        ->model('gpt-4');

    expect($builder)->toBeInstanceOf(AgentBuilder::class);
});

it('can register defined agent', function () {
    $this->builder
        ->define('registered-agent')
        ->description('A registered test agent')
        ->instructions('You are a test assistant')
        ->register();

    expect($this->registry->hasAgent('registered-agent'))->toBeTrue();
});

it('can register class based agent', function () {
    $agent = $this->builder
        ->build(TestBuilderAgent::class)
        ->register();

    expect($agent)->toBeInstanceOf(TestBuilderAgent::class);
    expect($this->registry->hasAgent('test-builder-agent'))->toBeTrue();
});

it('throws exception when registering without name', function () {
    $this->builder->register();
})->throws(AgentConfigurationException::class, 'Agent name is required for registration.');

it('throws exception when registering ad hoc agent without instructions', function () {
    $this->builder
        ->define('incomplete-agent')
        ->description('Missing instructions')
        ->register();
})->throws(AgentConfigurationException::class, 'Instructions are required for ad-hoc agent definition.');

it('can override class instructions with withInstructionOverride', function () {
    $agent = $this->builder
        ->build(TestBuilderAgent::class)
        ->withInstructionOverride('Override instructions')
        ->register();

    expect($agent)->toBeInstanceOf(TestBuilderAgent::class);
    expect($agent->getInstructions())->toBe('Override instructions');
});

it('fluent interface methods return builder', function () {
    $builder = $this->builder->define('test');

    expect($builder->description('test'))->toBe($builder);
    expect($builder->instructions('test'))->toBe($builder);
    expect($builder->model('gpt-3.5-turbo'))->toBe($builder);
});

it('can chain multiple operations', function () {
    $this->builder
        ->define('chained-agent')
        ->description('A chained agent')
        ->instructions('You are helpful')
        ->model('gpt-4')
        ->register();

    expect($this->registry->hasAgent('chained-agent'))->toBeTrue();
});

it('can override model for class based agent', function () {
    $agent = $this->builder
        ->build(TestBuilderAgent::class)
        ->model('gpt-4')
        ->register();

    expect($agent->getModel())->toBe('gpt-4');
});

/**
 * Test LLM agent for builder testing
 */
class TestBuilderAgent extends BaseLlmAgent
{
    protected string $name = 'test-builder-agent';

    protected string $description = 'A test agent for builder testing';

    protected string $instructions = 'Default test instructions';

    protected string $model = 'gpt-3.5-turbo';

    public function loadTools(): void
    {
        // No tools for testing
    }

    public function execute(mixed $input, AgentContext $context): mixed
    {
        return 'Builder test response: '.$input;
    }
}
