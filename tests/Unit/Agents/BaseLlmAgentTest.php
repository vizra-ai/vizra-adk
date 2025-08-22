<?php

use Prism\Prism\Enums\Provider;
use Prism\Prism\PrismManager;
use Prism\Prism\ValueObjects\ProviderTool;
use Vizra\VizraADK\Agents\BaseLlmAgent;
use Vizra\VizraADK\System\AgentContext;

beforeEach(function () {
    $this->agent = new TestLlmAgent;

    $this->app[PrismManager::class]->extend('mock', function () {
        return new class extends \Prism\Prism\Providers\Provider {};
    });
});

it('can get agent instructions', function () {
    $instructions = $this->agent->getInstructions();
    expect($instructions)->toBe('Test LLM agent instructions');
});

it('can get agent model', function () {
    $model = $this->agent->getModel();
    expect($model)->toBe('gpt-3.5-turbo');
});

it('can get provider', function () {
    $provider = $this->agent->getProvider();
    expect($provider)->toBe(config('vizra-adk.default_provider'));
});

it('can get temperature', function () {
    $temperature = $this->agent->getTemperature();
    expect($temperature)->toBe(0.7);
});

it('can get max tokens', function () {
    $maxTokens = $this->agent->getMaxTokens();
    expect($maxTokens)->toBe(1000);
});

it('can load tools', function () {
    $tools = $this->agent->getLoadedTools();
    expect($tools)->toBeArray();
});

it('can load provider tools as Prism provider tool object values', function () {
    $providerTools = $this->agent->getProviderToolsForPrism();
    expect($providerTools)->toBeArray();

    collect($providerTools)->each(
        fn ($tool) => expect($tool)->toBeInstanceOf(ProviderTool::class),
    );
});

// Streaming functionality tests
it('has streaming disabled by default', function () {
    expect($this->agent->getStreaming())->toBeFalse();
});

it('can enable streaming', function () {
    $this->agent->setStreaming(true);
    expect($this->agent->getStreaming())->toBeTrue();
});

it('can disable streaming', function () {
    $this->agent->setStreaming(true);
    $this->agent->setStreaming(false);
    expect($this->agent->getStreaming())->toBeFalse();
});

it('setStreaming returns agent instance for fluent interface', function () {
    $result = $this->agent->setStreaming(true);
    expect($result)->toBe($this->agent);
});

it('can chain streaming configuration with other methods', function () {
    $agent = $this->agent
        ->setStreaming(true)
        ->setTemperature(0.5)
        ->setMaxTokens(200);

    expect($agent->getStreaming())->toBeTrue();
    expect($agent->getTemperature())->toBe(0.5);
    expect($agent->getMaxTokens())->toBe(200);
});

it('executes with context', function () {
    $context = new AgentContext('test-session');

    // Mock the LLM response since we can't actually call the API in tests
    $result = $this->agent->execute('Hello', $context);

    // Basic test to ensure the method runs without error
    expect($result)->toBeString();
});

// showInChatUi functionality tests
it('has showInChatUi enabled by default', function () {
    expect($this->agent->getShowInChatUi())->toBeTrue();
});

it('can disable showInChatUi', function () {
    $this->agent->setShowInChatUi(false);
    expect($this->agent->getShowInChatUi())->toBeFalse();
});

it('can enable showInChatUi', function () {
    $this->agent->setShowInChatUi(false);
    $this->agent->setShowInChatUi(true);
    expect($this->agent->getShowInChatUi())->toBeTrue();
});

it('setShowInChatUi returns agent instance for fluent interface', function () {
    $result = $this->agent->setShowInChatUi(false);
    expect($result)->toBe($this->agent);
});

it('can chain showInChatUi configuration with other methods', function () {
    $agent = $this->agent
        ->setShowInChatUi(false)
        ->setStreaming(true)
        ->setTemperature(0.5);

    expect($agent->getShowInChatUi())->toBeFalse();
    expect($agent->getStreaming())->toBeTrue();
    expect($agent->getTemperature())->toBe(0.5);
});

it('can create agent with showInChatUi disabled', function () {
    $hiddenAgent = new HiddenTestAgent;
    expect($hiddenAgent->getShowInChatUi())->toBeFalse();
});

it('can set custom provider', function () {
    $this->agent->setProvider('mock');
    expect($this->agent->getProvider())->toBe('mock');
});

it('throws exception for invalid provider', function () {
    $this->agent->setProvider('invalid-provider');
})->throws(\InvalidArgumentException::class, 'Provider [invalid-provider] is not supported.');

/**
 * Test implementation of BaseLlmAgent for testing purposes
 */
class TestLlmAgent extends BaseLlmAgent
{
    protected string $name = 'test-llm-agent';

    protected string $description = 'A test LLM agent for unit testing';

    protected string $instructions = 'Test LLM agent instructions';

    protected string $model = 'gpt-3.5-turbo';

    protected ?float $temperature = 0.7;

    protected ?int $maxTokens = 1000;

    protected array $providerTools = [
        'web_search_preview',
    ];

    public function getInstructions(): string
    {
        return $this->instructions;
    }

    public function getModel(): string
    {
        return $this->model;
    }

    public function getProvider(): string
    {
        return parent::getProvider();
    }

    public function getTemperature(): ?float
    {
        return $this->temperature;
    }

    public function getMaxTokens(): ?int
    {
        return $this->maxTokens;
    }

    public function getLoadedTools(): array
    {
        // Make sure tools are loaded before returning them
        $this->loadTools();

        return $this->loadedTools;
    }

    // Override execute method to avoid actual API calls in tests
    public function execute(mixed $input, AgentContext $context): mixed
    {
        // Simple mock response for testing
        return 'Test response for: '.$input;
    }
}

/**
 * Test agent with showInChatUi disabled
 */
class HiddenTestAgent extends BaseLlmAgent
{
    protected string $name = 'hidden-test-agent';

    protected string $description = 'A hidden test agent for unit testing';

    protected string $instructions = 'Hidden test agent instructions';

    protected string $model = 'gpt-3.5-turbo';

    protected bool $showInChatUi = false;

    public function getInstructions(): string
    {
        return $this->instructions;
    }

    public function execute(mixed $input, AgentContext $context): mixed
    {
        return 'Hidden test response for: '.$input;
    }
}
