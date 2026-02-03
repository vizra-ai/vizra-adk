<?php

namespace Vizra\VizraADK\Tools\Chaining;

/**
 * Trait that provides default implementations for ChainableToolInterface.
 *
 * Tools can use this trait to quickly implement chainability with
 * sensible defaults while overriding specific methods as needed.
 */
trait ChainableTool
{
    /**
     * Get the expected input schema for this tool when used in a chain.
     *
     * Default: accepts any input.
     */
    public function getInputSchema(): array
    {
        return ['type' => 'object'];
    }

    /**
     * Get the output schema for this tool.
     *
     * Default: returns a string (standard tool output).
     */
    public function getOutputSchema(): array
    {
        return ['type' => 'string'];
    }

    /**
     * Transform the tool's output for the next tool in the chain.
     *
     * Default: attempts to JSON decode the output, falls back to raw string.
     */
    public function transformOutputForChain(string $rawOutput): mixed
    {
        $decoded = json_decode($rawOutput, true);

        if (json_last_error() === JSON_ERROR_NONE) {
            return $decoded;
        }

        return $rawOutput;
    }

    /**
     * Accept input from the previous tool in the chain.
     *
     * Default: if previous output is an array, use it as arguments.
     * Otherwise, wrap it in an 'input' key.
     */
    public function acceptChainInput(mixed $previousOutput, array $initialArguments): array
    {
        if (is_array($previousOutput)) {
            return array_merge($initialArguments, $previousOutput);
        }

        return array_merge($initialArguments, ['input' => $previousOutput]);
    }
}
