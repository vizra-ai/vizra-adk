<?php

namespace AaronLumsden\LaravelAgentADK\Contracts;

use AaronLumsden\LaravelAgentADK\System\AgentContext;

/**
 * Interface ToolInterface
 * Defines the contract for tools that can be used by agents.
 */
interface ToolInterface
{
    /**
     * Get the tool's definition for the LLM (JSON schema compatible).
     *
     * Example: [
     *  'name' => 'get_current_weather',
     *  'description' => 'Get the current weather in a given location',
     *  'parameters' => [
     *      'type' => 'object',
     *      'properties' => [
     *          'location' => ['type' => 'string', 'description' => 'The city and state, e.g. San Francisco, CA'],
     *      ],
     *      'required' => ['location'],
     *  ]
     * ]
     *
     * @return array The tool definition.
     */
    public function definition(): array;

    /**
     * Execute the tool's logic.
     *
     * @param array $arguments Arguments provided by the LLM.
     * @param AgentContext $context The current agent context.
     * @return string JSON string representation of the tool's result.
     */
    public function execute(array $arguments, AgentContext $context): string;
}
