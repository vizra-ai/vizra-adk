<?php

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Vizra\VizraADK\Traits\HasLogging;

class TestClassWithLogging
{
    use HasLogging;

    public function testLogInfo(string $message, array $context = [], ?string $component = null): void
    {
        $this->logInfo($message, $context, $component);
    }

    public function testLogWarning(string $message, array $context = [], ?string $component = null): void
    {
        $this->logWarning($message, $context, $component);
    }

    public function testLogError(string $message, array $context = [], ?string $component = null): void
    {
        $this->logError($message, $context, $component);
    }
}

beforeEach(function () {
    $this->testClass = new TestClassWithLogging();
});

describe('HasLogging trait', function () {

    test('respects global enabled flag', function () {
        Config::set('vizra-adk.enabled', false);

        Log::shouldReceive('info')->never();
        Log::shouldReceive('warning')->never();

        $this->testClass->testLogInfo('Test message');
        $this->testClass->testLogWarning('Test warning');
    });

    test('respects logging enabled flag', function () {
        Config::set('vizra-adk.enabled', true);
        Config::set('vizra-adk.logging.enabled', false);

        Log::shouldReceive('info')->never();
        Log::shouldReceive('warning')->never();

        $this->testClass->testLogInfo('Test message');
        $this->testClass->testLogWarning('Test warning');
    });

    test('respects component-specific logging settings', function () {
        Config::set('vizra-adk.enabled', true);
        Config::set('vizra-adk.logging.enabled', true);
        Config::set('vizra-adk.logging.level', 'debug');
        Config::set('vizra-adk.logging.components.vector_memory', false);
        Config::set('vizra-adk.logging.components.agents', true);

        // Vector memory logging should be disabled
        Log::shouldReceive('info')->never();
        $this->testClass->testLogInfo('Test message', [], 'vector_memory');

        // Agent logging should work
        Log::shouldReceive('info')
            ->once()
            ->with('[VizraADK:agents] Test message', []);
        $this->testClass->testLogInfo('Test message', [], 'agents');
    });

    test('respects log level threshold', function () {
        Config::set('vizra-adk.enabled', true);
        Config::set('vizra-adk.logging.enabled', true);
        Config::set('vizra-adk.logging.level', 'warning');

        // Info should not log (below threshold)
        Log::shouldReceive('info')->never();
        $this->testClass->testLogInfo('Info message');

        // Warning should log (meets threshold)
        Log::shouldReceive('warning')
            ->once()
            ->with('[VizraADK] Warning message', []);
        $this->testClass->testLogWarning('Warning message');

        // Error should log (above threshold)
        Log::shouldReceive('error')
            ->once()
            ->with('[VizraADK] Error message', []);
        $this->testClass->testLogError('Error message');
    });

    test('formats log messages without component', function () {
        Config::set('vizra-adk.enabled', true);
        Config::set('vizra-adk.logging.enabled', true);
        Config::set('vizra-adk.logging.level', 'info');

        Log::shouldReceive('info')
            ->once()
            ->with('[VizraADK] Test message', ['key' => 'value']);
        $this->testClass->testLogInfo('Test message', ['key' => 'value']);
    });

    test('formats log messages with component', function () {
        Config::set('vizra-adk.enabled', true);
        Config::set('vizra-adk.logging.enabled', true);
        Config::set('vizra-adk.logging.level', 'info');
        Config::set('vizra-adk.logging.components.vector_memory', true);

        Log::shouldReceive('info')
            ->once()
            ->with('[VizraADK:vector_memory] Test message', ['key' => 'value']);
        $this->testClass->testLogInfo('Test message', ['key' => 'value'], 'vector_memory');
    });

    test('handles none log level correctly', function () {
        Config::set('vizra-adk.enabled', true);
        Config::set('vizra-adk.logging.enabled', true);
        Config::set('vizra-adk.logging.level', 'none');

        // Nothing should be logged
        Log::shouldReceive('info')->never();
        Log::shouldReceive('warning')->never();
        Log::shouldReceive('error')->never();

        $this->testClass->testLogInfo('Info message');
        $this->testClass->testLogWarning('Warning message');
        $this->testClass->testLogError('Error message');
    });
});