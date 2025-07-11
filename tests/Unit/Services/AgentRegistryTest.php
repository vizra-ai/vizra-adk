<?php

use Vizra\VizraADK\Agents\BaseAgent;
use Vizra\VizraADK\Exceptions\AgentConfigurationException;
use Vizra\VizraADK\Exceptions\AgentNotFoundException;
use Vizra\VizraADK\Services\AgentDiscovery;
use Vizra\VizraADK\Services\AgentRegistry;
use Vizra\VizraADK\System\AgentContext;

beforeEach(function () {
    $this->registry = new AgentRegistry($this->app);
});

it('can register agent with class name', function () {
    $this->registry->register('test-agent', TestRegistryAgent::class);

    $agent = $this->registry->getAgent('test-agent');

    expect($agent)->toBeInstanceOf(TestRegistryAgent::class);
    expect($agent->getName())->toBe('test-registry-agent');
});

it('can register agent with ad hoc LLM configuration', function () {
    $config = [
        'type' => 'ad_hoc_llm',
        'name' => 'Test Ad Hoc Agent',
        'instructions' => 'You are a test agent.',
    ];

    $this->registry->register('ad-hoc-agent', $config);

    expect($this->registry->hasAgent('ad-hoc-agent'))->toBeTrue();

    // Test that we can actually get the agent instance
    $agent = $this->registry->getAgent('ad-hoc-agent');
    expect($agent)->toBeInstanceOf(\Vizra\VizraADK\Agents\GenericLlmAgent::class);
    expect($agent->getName())->toBe('Test Ad Hoc Agent');
    expect($agent->getInstructions())->toBe('You are a test agent.');
});

it('throws exception for unregistered agent', function () {
    $this->registry->getAgent('non-existent');
})->throws(AgentNotFoundException::class, "Agent 'non-existent' is not registered.");

it('can check if agent is registered', function () {
    expect($this->registry->hasAgent('test-agent'))->toBeFalse();

    $this->registry->register('test-agent', TestRegistryAgent::class);

    expect($this->registry->hasAgent('test-agent'))->toBeTrue();
});

it('can get all registered agents', function () {
    $this->registry->register('agent1', TestRegistryAgent::class);
    $this->registry->register('agent2', TestRegistryAgent::class);

    $agents = $this->registry->getAllRegisteredAgents();

    expect($agents)->toBeArray();
    expect($agents)->toHaveKey('agent1');
    expect($agents)->toHaveKey('agent2');
});

it('caches agent instances', function () {
    $this->registry->register('cached-agent', TestRegistryAgent::class);

    $agent1 = $this->registry->getAgent('cached-agent');
    $agent2 = $this->registry->getAgent('cached-agent');

    expect($agent1)->toBe($agent2);
});

it('throws exception for invalid configuration', function () {
    $config = [
        'invalid' => 'configuration',
    ];

    $this->registry->register('invalid-agent', $config);

    $this->registry->getAgent('invalid-agent');
})->throws(AgentConfigurationException::class);

it('throws exception for non-existent class', function () {
    $this->registry->register('invalid-class-agent', 'NonExistentClass');

    $this->registry->getAgent('invalid-class-agent');
})->throws(AgentConfigurationException::class);

it('throws exception for class not extending BaseAgent', function () {
    $this->registry->register('invalid-base-agent', stdClass::class);

    $this->registry->getAgent('invalid-base-agent');
})->throws(AgentConfigurationException::class);

/**
 * Test agent for registry testing
 */
class TestRegistryAgent extends BaseAgent
{
    protected string $name = 'test-registry-agent';

    protected string $description = 'A test agent for registry testing';

    public function execute(mixed $input, AgentContext $context): mixed
    {
        return 'Registry test response: '.$input;
    }
}

// Tests for lazy discovery
it('triggers lazy discovery when agent not found', function () {
    // Create a mock discovery service that returns our test agent
    $mockDiscovery = Mockery::mock(AgentDiscovery::class);
    $mockDiscovery->shouldReceive('clearCache')->once();
    $mockDiscovery->shouldReceive('discover')->once()->andReturn([
        TestRegistryAgent::class => 'test-registry-agent',
    ]);

    $this->app->instance(AgentDiscovery::class, $mockDiscovery);

    // Agent should not be registered initially
    expect($this->registry->hasAgent('test-registry-agent'))->toBeFalse();

    // Getting the agent should trigger discovery
    $agent = $this->registry->getAgent('test-registry-agent');

    expect($agent)->toBeInstanceOf(TestRegistryAgent::class);
    expect($this->registry->hasAgent('test-registry-agent'))->toBeTrue();
});

it('does not trigger discovery for already registered agents', function () {
    // Pre-register an agent
    $this->registry->register('test-agent', TestRegistryAgent::class);

    // Create a mock discovery that should NOT be called
    $mockDiscovery = Mockery::mock(AgentDiscovery::class);
    $mockDiscovery->shouldNotReceive('clearCache');
    $mockDiscovery->shouldNotReceive('discover');

    $this->app->instance(AgentDiscovery::class, $mockDiscovery);

    // Getting the pre-registered agent should not trigger discovery
    $agent = $this->registry->getAgent('test-agent');

    expect($agent)->toBeInstanceOf(TestRegistryAgent::class);
});

it('throws exception when agent not found even after discovery', function () {
    // Create a mock discovery service that returns empty
    $mockDiscovery = Mockery::mock(AgentDiscovery::class);
    $mockDiscovery->shouldReceive('clearCache')->once();
    $mockDiscovery->shouldReceive('discover')->once()->andReturn([]);

    $this->app->instance(AgentDiscovery::class, $mockDiscovery);

    $this->registry->getAgent('non-existent');
})->throws(AgentNotFoundException::class, "Agent 'non-existent' is not registered.");

it('lazy discovery registers all discovered agents', function () {
    // Create a mock discovery service that returns multiple agents
    $mockDiscovery = Mockery::mock(AgentDiscovery::class);
    $mockDiscovery->shouldReceive('clearCache')->once();
    $mockDiscovery->shouldReceive('discover')->once()->andReturn([
        TestRegistryAgent::class => 'test-registry-agent',
        'App\Agents\AnotherAgent' => 'another-agent',
        'App\Agents\ThirdAgent' => 'third-agent',
    ]);

    $this->app->instance(AgentDiscovery::class, $mockDiscovery);

    // None should be registered initially
    expect($this->registry->hasAgent('test-registry-agent'))->toBeFalse();
    expect($this->registry->hasAgent('another-agent'))->toBeFalse();
    expect($this->registry->hasAgent('third-agent'))->toBeFalse();

    // Trigger discovery by requesting one agent
    $agent = $this->registry->getAgent('test-registry-agent');

    // All discovered agents should now be registered
    expect($this->registry->hasAgent('test-registry-agent'))->toBeTrue();
    expect($this->registry->hasAgent('another-agent'))->toBeTrue();
    expect($this->registry->hasAgent('third-agent'))->toBeTrue();
});
