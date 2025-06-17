<?php

use Vizra\VizraAdk\Services\AgentRegistry;
use Vizra\VizraAdk\Agents\BaseAgent;
use Vizra\VizraAdk\Exceptions\AgentNotFoundException;
use Vizra\VizraAdk\Exceptions\AgentConfigurationException;
use Vizra\VizraAdk\System\AgentContext;

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
        'instructions' => 'You are a test agent.'
    ];

    $this->registry->register('ad-hoc-agent', $config);

    expect($this->registry->hasAgent('ad-hoc-agent'))->toBeTrue();

    // Test that we can actually get the agent instance
    $agent = $this->registry->getAgent('ad-hoc-agent');
    expect($agent)->toBeInstanceOf(\Vizra\VizraAdk\Agents\GenericLlmAgent::class);
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
        'invalid' => 'configuration'
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

    public function run(mixed $input, AgentContext $context): mixed
    {
        return 'Registry test response: ' . $input;
    }
}
