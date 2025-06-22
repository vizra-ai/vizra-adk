<?php

namespace Vizra\VizraADK\Services;

use Illuminate\Contracts\Foundation\Application;
use Vizra\VizraADK\Agents\BaseAgent; // Added
use Vizra\VizraADK\Agents\BaseLlmAgent; // Will be created
use Vizra\VizraADK\Agents\GenericLlmAgent;
use Vizra\VizraADK\Exceptions\AgentConfigurationException; // Added
use Vizra\VizraADK\Exceptions\AgentNotFoundException;

class AgentRegistry
{
    protected Application $app;

    protected array $registeredAgents = [];

    protected array $agentInstances = [];

    protected array $classToNameMap = [];

    public function __construct(Application $app)
    {
        $this->app = $app;
    }

    public function register(string $name, string|array $configuration): void
    {
        $this->registeredAgents[$name] = $configuration;
        unset($this->agentInstances[$name]);

        // If configuration is a class name, register the mapping
        if (is_string($configuration) && class_exists($configuration)) {
            $this->classToNameMap[$configuration] = $name;
        }
    }

    /**
     * Get an instance of a registered agent.
     *
     * @param  string  $name  The name of the agent.
     *
     * @throws AgentNotFoundException If the agent is not registered or cannot be instantiated.
     * @throws AgentConfigurationException If configuration is invalid.
     */
    public function getAgent(string $name): BaseAgent
    {
        if (isset($this->agentInstances[$name])) {
            return $this->agentInstances[$name];
        }

        if (! isset($this->registeredAgents[$name])) {
            // Try lazy discovery
            if ($this->discoverAgent($name)) {
                return $this->getAgent($name);
            }

            throw new AgentNotFoundException("Agent '{$name}' is not registered.");
        }

        $config = $this->registeredAgents[$name];

        if (is_string($config)) { // Class-based agent
            if (! class_exists($config)) {
                throw new AgentConfigurationException("Agent class '{$config}' for agent '{$name}' not found.");
            }
            if (! is_subclass_of($config, BaseAgent::class)) {
                throw new AgentConfigurationException("Class '{$config}' for agent '{$name}' must extend ".BaseAgent::class);
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
            $instance->setModel($config['model'] ?? config('vizra-adk.default_model', 'gemini-pro'));
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

    /**
     * Try to discover an agent by name
     */
    protected function discoverAgent(string $name): bool
    {
        /** @var AgentDiscovery $discovery */
        $discovery = $this->app->make(AgentDiscovery::class);

        // Clear cache and re-discover
        $discovery->clearCache();
        $agents = $discovery->discover();

        // Register all discovered agents
        foreach ($agents as $className => $agentName) {
            $this->register($agentName, $className);
        }

        // Check if the requested agent is now registered
        return isset($this->registeredAgents[$name]);
    }

    /**
     * Get agent name by class name
     */
    public function getAgentNameByClass(string $className): ?string
    {
        // First check our mapping
        if (isset($this->classToNameMap[$className])) {
            return $this->classToNameMap[$className];
        }

        // Try to discover agents if not found
        $this->discoverAgent('_trigger_discovery_');

        // Check again after discovery
        if (isset($this->classToNameMap[$className])) {
            return $this->classToNameMap[$className];
        }

        // As a last resort, try to instantiate and get the name
        if (class_exists($className) && is_subclass_of($className, BaseAgent::class)) {
            try {
                /** @var BaseAgent $instance */
                $instance = $this->app->make($className);
                $name = $instance->getName();

                // Register it for future use
                $this->register($name, $className);

                return $name;
            } catch (\Throwable $e) {
                // Failed to instantiate
            }
        }

        return null;
    }

    /**
     * Resolve agent name from either a string name or class name
     *
     * @throws AgentNotFoundException
     */
    public function resolveAgentName(string $agentNameOrClass): string
    {
        // If it's already a registered agent name, return it
        if ($this->hasAgent($agentNameOrClass)) {
            return $agentNameOrClass;
        }

        // Try to resolve as a class name
        $agentName = $this->getAgentNameByClass($agentNameOrClass);
        if ($agentName !== null) {
            return $agentName;
        }

        throw new AgentNotFoundException("Cannot resolve agent '{$agentNameOrClass}' as either a name or class.");
    }
}
