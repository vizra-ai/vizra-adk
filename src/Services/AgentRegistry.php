<?php

namespace AaronLumsden\LaravelAgentADK\Services;

use AaronLumsden\LaravelAgentADK\Agents\BaseAgent;
use AaronLumsden\LaravelAgentADK\Agents\BaseLlmAgent; // Added
use AaronLumsden\LaravelAgentADK\Agents\GenericLlmAgent; // Will be created
use AaronLumsden\LaravelAgentADK\Exceptions\AgentNotFoundException;
use AaronLumsden\LaravelAgentADK\Exceptions\AgentConfigurationException; // Added
use Illuminate\Contracts\Foundation\Application;

class AgentRegistry
{
    protected Application $app;
    protected array $registeredAgents = [];
    protected array $agentInstances = [];

    public function __construct(Application $app)
    {
        $this->app = $app;
    }

    public function register(string $name, string|array $configuration): void
    {
        $this->registeredAgents[$name] = $configuration;
        unset($this->agentInstances[$name]);
    }

    /**
     * Get an instance of a registered agent.
     *
     * @param string $name The name of the agent.
     * @return BaseAgent
     * @throws AgentNotFoundException If the agent is not registered or cannot be instantiated.
     * @throws AgentConfigurationException If configuration is invalid.
     */
    public function getAgent(string $name): BaseAgent
    {
        if (isset($this->agentInstances[$name])) {
            return $this->agentInstances[$name];
        }

        if (!isset($this->registeredAgents[$name])) {
            throw new AgentNotFoundException("Agent '{$name}' is not registered.");
        }

        $config = $this->registeredAgents[$name];

        if (is_string($config)) { // Class-based agent
            if (!class_exists($config)) {
                throw new AgentConfigurationException("Agent class '{$config}' for agent '{$name}' not found.");
            }
            if (!is_subclass_of($config, BaseAgent::class)) {
                throw new AgentConfigurationException("Class '{$config}' for agent '{$name}' must extend " . BaseAgent::class);
            }
            /** @var BaseAgent $instance */
            $instance = $this->app->make($config);
            $this->agentInstances[$name] = $instance; // Cache it
            return $instance;
        } elseif (is_array($config) && isset($config['type']) && $config['type'] === 'ad_hoc_llm') {
            // Ad-hoc LLM agent definition
            /** @var GenericLlmAgent $instance */
            $instance = $this->app->make(GenericLlmAgent::class);
            $instance->setName($config['name']);
            // $instance->setDescription($config['description'] ?? ''); // Description not directly settable on BaseAgent/BaseLlmAgent post-init
            $instance->setInstructions($config['instructions']);
            $instance->setModel($config['model'] ?? config('agent-adk.default_model', 'gemini-pro'));
            // Tools for ad-hoc agents are not part of MVP builder, but could be loaded if defined in array
            // foreach (($config['tools'] ?? []) as $toolClass) {
            //     $instance->registerTool($toolClass); // Requires GenericLlmAgent to have such a method
            // }
            $this->agentInstances[$name] = $instance; // Cache it
            return $instance;
        }

        throw new AgentConfigurationException("Configuration for agent '{$name}' is invalid or type is not supported for direct instantiation.");
    }

    public function hasAgent(string $name): bool
    {
        return isset($this->registeredAgents[$name]);
    }

    public function getAllRegisteredAgents(): array
    {
        return $this->registeredAgents;
    }
}
