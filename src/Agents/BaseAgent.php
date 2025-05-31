<?php

namespace AaronLumsden\LaravelAgentADK\Agents;

use AaronLumsden\LaravelAgentADK\System\AgentContext;

/**
 * Abstract Class BaseAgent
 * Establishes the common contract for all agents.
 */
abstract class BaseAgent
{
    /**
     * The unique name of the agent.
     * Used for registration and identification.
     * @var string
     */
    protected string $name = '';

    /**
     * A brief description of what the agent does.
     * @var string
     */
    protected string $description = '';

    /**
     * Get the name of the agent.
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Get the description of the agent.
     * @return string
     */
    public function getDescription(): string
    {
        return $this->description;
    }

    /**
     * Execute the agent's primary logic.
     *
     * @param mixed $input The input for the agent.
     * @param AgentContext $context The context for this execution.
     * @return mixed The result of the agent's execution.
     */
    abstract public function run(mixed $input, AgentContext $context): mixed;
}
