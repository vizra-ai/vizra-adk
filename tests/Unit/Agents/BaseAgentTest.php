<?php

use Vizra\VizraADK\Agents\BaseAgent;
use Vizra\VizraADK\System\AgentContext;

/**
 * Test implementation of BaseAgent for testing purposes
 */
class TestAgent extends BaseAgent
{
    protected string $name = 'test-agent';

    protected string $description = 'A test agent for unit testing';

    public function execute(mixed $input, AgentContext $context): mixed
    {
        return 'Processed: '.($input ?? '');
    }
}

beforeEach(function () {
    $this->agent = new TestAgent;
});

it('can get agent name', function () {
    expect($this->agent->getName())->toBe('test-agent');
});

it('can get agent description', function () {
    expect($this->agent->getDescription())->toBe('A test agent for unit testing');
});

it('can execute agent', function () {
    $context = new AgentContext('test-session');
    $result = $this->agent->execute('test input', $context);

    expect($result)->toBe('Processed: test input');
});

it('handles empty input', function () {
    $context = new AgentContext('test-session');
    $result = $this->agent->execute('', $context);

    expect($result)->toBe('Processed: ');
});

it('handles null input', function () {
    $context = new AgentContext('test-session');
    $result = $this->agent->execute(null, $context);

    expect($result)->toBe('Processed: ');
});
