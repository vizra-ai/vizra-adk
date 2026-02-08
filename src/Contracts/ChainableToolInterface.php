<?php

namespace Vizra\VizraADK\Contracts;

/**
 * Interface for tools that explicitly support chaining.
 *
 * This interface extends ToolInterface with additional methods
 * that describe how the tool's output should be transformed
 * for the next tool in a chain.
 *
 * Implementing this interface is optional - regular ToolInterface
 * tools can still be used in chains with explicit argument mappers.
 */
interface ChainableToolInterface extends ToolInterface
{
    /**
     * Get the expected input schema for this tool when used in a chain.
     *
     * This helps validate that the previous tool's output is compatible.
     *
     * @return array JSON Schema describing expected input.
     */
    public function getInputSchema(): array;

    /**
     * Get the output schema for this tool.
     *
     * This helps the next tool in the chain understand what to expect.
     *
     * @return array JSON Schema describing the output.
     */
    public function getOutputSchema(): array;

    /**
     * Transform the tool's output for the next tool in the chain.
     *
     * This method is called automatically when the tool is used in a chain.
     * It allows the tool to prepare its output in a format suitable for
     * passing to another tool.
     *
     * @param  string  $rawOutput  The raw output from execute().
     * @return mixed The transformed output for the next tool.
     */
    public function transformOutputForChain(string $rawOutput): mixed;

    /**
     * Accept input from the previous tool in the chain.
     *
     * This method transforms the previous tool's output into the
     * arguments array expected by this tool's execute() method.
     *
     * @param  mixed  $previousOutput  Output from the previous tool (already transformed).
     * @param  array  $initialArguments  The original arguments passed to the chain.
     * @return array Arguments for this tool's execute() method.
     */
    public function acceptChainInput(mixed $previousOutput, array $initialArguments): array;
}
