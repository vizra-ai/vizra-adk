<?php

namespace Vizra\VizraADK\Evaluations\Assertions;

/**
 * Base class for custom assertions.
 *
 * Provides common functionality and helper methods for creating assertions.
 */
abstract class BaseAssertion implements AssertionInterface
{
    /**
     * Execute the assertion against the given response.
     *
     * @param  string  $response  The LLM response to evaluate
     * @param  mixed  ...$params  Additional parameters for the assertion
     */
    abstract public function assert(string $response, ...$params): array;

    /**
     * Get the name of this assertion.
     * By default, returns the class basename.
     */
    public function getName(): string
    {
        return class_basename($this);
    }

    /**
     * Helper method to create a consistent result array.
     *
     * @param  bool  $status  Whether the assertion passed
     * @param  string  $message  Human-readable message about the result
     * @param  mixed  $expected  The expected value (optional)
     * @param  mixed  $actual  The actual value found (optional)
     */
    protected function result(bool $status, string $message, $expected = null, $actual = null): array
    {
        $result = [
            'status' => $status,
            'message' => $message,
        ];

        if ($expected !== null) {
            $result['expected'] = $expected;
        }

        if ($actual !== null) {
            $result['actual'] = $actual;
        }

        return $result;
    }
}
