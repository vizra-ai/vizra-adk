<?php

namespace AaronLumsden\LaravelAgentADK\Facades;

use Illuminate\Support\Facades\Facade;
use AaronLumsden\LaravelAgentADK\Services\AgentBuilder; // For type hinting
use AaronLumsden\LaravelAgentADK\Agents\BaseAgent; // For type hinting

/**
 * @method static AgentBuilder build(string $agentClass)
 * @method static AgentBuilder define(string $name)
 * @method static BaseAgent named(string $agentName)
 * @method static mixed run(string $agentName, mixed $input, ?string $sessionId = null)
 * @method static array getAllRegisteredAgents()
 * @method static bool hasAgent(string $name)
 *
 * @see \AaronLumsden\LaravelAgentADK\Services\AgentManager // The underlying class facade will point to
 */
class Agent extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor(): string
    {
        // This will point to a new AgentManager class that combines AgentBuilder and AgentRegistry functionalities
        // For now, let's make it point to AgentBuilder, and we'll create AgentManager in Step 12 or refine this.
        // return AgentBuilder::class; // Temporary - will be AgentManager::class
        return 'laravel-agent-adk.manager'; // Binding name for AgentManager
    }
}
