<?php

use Vizra\VizraSdk\Exceptions\AgentNotFoundException;
use Vizra\VizraSdk\Exceptions\AgentConfigurationException;
use Vizra\VizraSdk\Exceptions\ToolExecutionException;

it('creates agent not found exception correctly', function () {
    $exception = new AgentNotFoundException('Agent not found');

    expect($exception)->toBeInstanceOf(\Exception::class);
    expect($exception->getMessage())->toBe('Agent not found');
});

it('creates agent configuration exception correctly', function () {
    $exception = new AgentConfigurationException('Invalid configuration');

    expect($exception)->toBeInstanceOf(\Exception::class);
    expect($exception->getMessage())->toBe('Invalid configuration');
});

it('creates tool execution exception correctly', function () {
    $exception = new ToolExecutionException('Tool execution failed');

    expect($exception)->toBeInstanceOf(\Exception::class);
    expect($exception->getMessage())->toBe('Tool execution failed');
});

it('can catch exceptions as base exception', function () {
    $exceptions = [
        new AgentNotFoundException('Test message'),
        new AgentConfigurationException('Test message'),
        new ToolExecutionException('Test message')
    ];

    foreach ($exceptions as $exception) {
        try {
            throw $exception;
        } catch (\Exception $e) {
            expect($e->getMessage())->toBe('Test message');
        }
    }
});

it('maintains stack trace in exceptions', function () {
    try {
        throwAgentNotFoundException();
    } catch (AgentNotFoundException $e) {
        expect($e->getTraceAsString())->toContain('throwAgentNotFoundException');
    }
});

it('can have previous exception', function () {
    $previousException = new \RuntimeException('Previous error');
    $exception = new AgentNotFoundException('Agent error', 0, $previousException);

    expect($exception->getPrevious())->toBe($previousException);
});

it('can have custom codes', function () {
    $exception = new AgentConfigurationException('Custom error', 1001);

    expect($exception->getCode())->toBe(1001);
});

function throwAgentNotFoundException(): void
{
    throw new AgentNotFoundException('Test exception for stack trace');
}
