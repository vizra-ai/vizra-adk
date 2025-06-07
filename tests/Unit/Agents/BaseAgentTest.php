<?php

use AaronLumsden\LaravelAiADK\Agents\BaseAgent;
use AaronLumsden\LaravelAiADK\System\AgentContext;

/**
 * Test implementation of BaseAgent for testing purposes
 */
class TestAgent extends BaseAgent
{
    protected string $name = 'test-agent';
    protected string $description = 'A test agent for unit testing';

    public function run(mixed $input, AgentContext $context): mixed
    {
        return 'Processed: ' . ($input ?? '');
    }
}

beforeEach(function () {
    $this->agent = new TestAgent();
});

it('can get agent name', function () {
    expect($this->agent->getName())->toBe('test-agent');
});

it('can get agent description', function () {
    expect($this->agent->getDescription())->toBe('A test agent for unit testing');
});

it('can run agent', function () {
    $context = new AgentContext('test-session');
    $result = $this->agent->run('test input', $context);

    expect($result)->toBe('Processed: test input');
});

it('handles empty input', function () {
    $context = new AgentContext('test-session');
    $result = $this->agent->run('', $context);

    expect($result)->toBe('Processed: ');
});

it('handles null input', function () {
    $context = new AgentContext('test-session');
    $result = $this->agent->run(null, $context);

    expect($result)->toBe('Processed: ');
});
