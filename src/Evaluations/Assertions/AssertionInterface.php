<?php

namespace Vizra\VizraADK\Evaluations\Assertions;

/**
 * Interface for custom assertion classes.
 *
 * Custom assertions allow you to create reusable validation logic
 * that can be shared across multiple evaluations.
 */
interface AssertionInterface
{
    /**
     * Execute the assertion against the given response.
     *
     * @param  string  $response  The LLM response to evaluate
     * @param  mixed  ...$params  Additional parameters for the assertion
     * @return array{
     *     status: bool,
     *     message: string,
     *     expected?: mixed,
     *     actual?: mixed
     * }
     */
    public function assert(string $response, ...$params): array;

    /**
     * Get the name of this assertion.
     * Used for logging and identification purposes.
     */
    public function getName(): string;
}
