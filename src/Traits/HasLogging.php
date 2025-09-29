<?php

namespace Vizra\VizraADK\Traits;

use Illuminate\Support\Facades\Log;

trait HasLogging
{
    /**
     * Check if logging is enabled for a specific component.
     */
    protected function isLoggingEnabled(?string $component = null): bool
    {
        // Check if package is globally enabled
        if (! config('vizra-adk.enabled', true)) {
            return false;
        }

        // Check if logging is globally enabled
        if (! config('vizra-adk.logging.enabled', true)) {
            return false;
        }

        // If a specific component is provided, check its setting
        if ($component !== null) {
            return config("vizra-adk.logging.components.{$component}", true);
        }

        return true;
    }

    /**
     * Check if a log level meets the threshold.
     */
    protected function meetsLogLevel(string $level): bool
    {
        $configuredLevel = config('vizra-adk.logging.level', 'warning');

        $levels = [
            'debug' => 0,
            'info' => 1,
            'warning' => 2,
            'error' => 3,
            'critical' => 4,
            'none' => 5,
        ];

        $configuredPriority = $levels[$configuredLevel] ?? 2;
        $messagePriority = $levels[$level] ?? 1;

        return $messagePriority >= $configuredPriority;
    }

    /**
     * Log a debug message if logging is enabled.
     */
    protected function logDebug(string $message, array $context = [], ?string $component = null): void
    {
        if ($this->isLoggingEnabled($component) && $this->meetsLogLevel('debug')) {
            Log::debug($this->formatLogMessage($message, $component), $context);
        }
    }

    /**
     * Log an info message if logging is enabled.
     */
    protected function logInfo(string $message, array $context = [], ?string $component = null): void
    {
        if ($this->isLoggingEnabled($component) && $this->meetsLogLevel('info')) {
            Log::info($this->formatLogMessage($message, $component), $context);
        }
    }

    /**
     * Log a warning message if logging is enabled.
     */
    protected function logWarning(string $message, array $context = [], ?string $component = null): void
    {
        if ($this->isLoggingEnabled($component) && $this->meetsLogLevel('warning')) {
            Log::warning($this->formatLogMessage($message, $component), $context);
        }
    }

    /**
     * Log an error message if logging is enabled.
     */
    protected function logError(string $message, array $context = [], ?string $component = null): void
    {
        if ($this->isLoggingEnabled($component) && $this->meetsLogLevel('error')) {
            Log::error($this->formatLogMessage($message, $component), $context);
        }
    }

    /**
     * Log a critical message if logging is enabled.
     */
    protected function logCritical(string $message, array $context = [], ?string $component = null): void
    {
        if ($this->isLoggingEnabled($component) && $this->meetsLogLevel('critical')) {
            Log::critical($this->formatLogMessage($message, $component), $context);
        }
    }

    /**
     * Format the log message with component prefix.
     */
    protected function formatLogMessage(string $message, ?string $component = null): string
    {
        if ($component) {
            return "[VizraADK:{$component}] {$message}";
        }

        return "[VizraADK] {$message}";
    }
}