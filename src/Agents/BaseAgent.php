<?php

namespace Vizra\VizraADK\Agents;

use Vizra\VizraADK\Execution\AgentExecutor;
use Vizra\VizraADK\System\AgentContext;

/**
 * Abstract Class BaseAgent
 * Establishes the common contract for all agents.
 */
abstract class BaseAgent
{
    /**
     * The unique name of the agent.
     * Used for registration and identification.
     */
    protected string $name = '';

    /**
     * A brief description of what the agent does.
     */
    protected string $description = '';

    /**
     * Get the name of the agent.
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Get the description of the agent.
     */
    public function getDescription(): string
    {
        return $this->description;
    }

    /**
     * Execute the agent's primary logic.
     *
     * @param  mixed  $input  The input for the agent.
     * @param  AgentContext  $context  The context for this execution.
     * @return mixed The result of the agent's execution.
     */
    abstract public function execute(mixed $input, AgentContext $context): mixed;

    /**
     * Create a fluent agent executor to run the agent.
     *
     * Usage: CustomerSupportAgent::run('Where is my order?')->forUser($user)->go()
     *
     * @param  mixed  $input  The input for the agent.
     */
    public static function run(mixed $input): AgentExecutor
    {
        return new AgentExecutor(static::class, $input);
    }
}
