<?php

namespace Vizra\VizraADK\Services;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Str;
use Illuminate\Support\Traits\Macroable;
use Vizra\VizraADK\Agents\BaseLlmAgent;
use Vizra\VizraADK\Exceptions\AgentConfigurationException;

/**
 * Class AgentBuilder
 * Provides a fluent interface for defining and registering agents.
 *
 * This class supports Laravel macros, allowing you to extend the fluent builder
 * with custom methods. This is particularly useful for adding tracking, logging,
 * or custom configuration methods.
 *
 * Example usage:
 * ```php
 * use Vizra\VizraADK\Services\AgentBuilder;
 * use Illuminate\Database\Eloquent\Model;
 *
 * // Register a macro to track token usage with a model
 * AgentBuilder::macro('track', function (Model $model) {
 *     $this->trackedModel = $model;
 *     return $this;
 * });
 *
 * // Use the macro when building/registering the agent
 * Agent::build(MyAgent::class)
 *     ->track(Unit::find(12))
 *     ->register();
 *
 * // Then run the agent using the executor API
 * MyAgent::run('User input')
 *     ->forUser($user)
 *     ->go();
 * ```
 */
class AgentBuilder
{
    use Macroable;
    protected Application $app;

    protected AgentRegistry $registry;

    protected ?string $agentClass = null;

    protected ?string $name = null;

    protected ?string $description = null;

    protected ?string $instructions = null;

    protected ?string $model = null;

    // For class-based builds, to apply overrides
    protected ?string $instructionOverride = null;

    protected ?string $promptVersion = null;

    public function __construct(Application $app, AgentRegistry $registry)
    {
        $this->app = $app;
        $this->registry = $registry;
    }

    /**
     * Start defining an agent from a class.
     * The agent's default properties will be taken from the class.
     *
     * @param  string  $agentClass  The fully qualified class name of the agent.
     * @return $this
     *
     * @throws AgentConfigurationException If the class does not exist or is not a BaseLlmAgent.
     */
    public function build(string $agentClass): self
    {
        if (! class_exists($agentClass)) {
            throw new AgentConfigurationException("Agent class '{$agentClass}' not found.");
        }
        if (! is_subclass_of($agentClass, BaseLlmAgent::class)) {
            throw new AgentConfigurationException("Agent class '{$agentClass}' must extend ".BaseLlmAgent::class);
        }

        $this->agentClass = $agentClass;
        // Attempt to derive name from class if not explicitly set later
        /** @var BaseLlmAgent $tempInstance */
        $tempInstance = $this->app->make($agentClass);
        $this->name = $tempInstance->getName() ?: 'agent-'.Str::slug(class_basename($agentClass));
        $this->description = $tempInstance->getDescription();
        $this->instructions = $tempInstance->getInstructions();
        $this->model = $tempInstance->getModel();

        return $this;
    }

    /**
     * Start defining an ad-hoc agent with a specific name.
     *
     * @param  string  $name  The unique name for this ad-hoc agent.
     * @return $this
     */
    public function define(string $name): self
    {
        $this->agentClass = null; // Marks it as an ad-hoc definition
        $this->name = $name;

        return $this;
    }

    /**
     * Set the description for the agent.
     *
     * @return $this
     */
    public function description(string $description): self
    {
        $this->description = $description;

        return $this;
    }

    /**
     * Set the instructions (system prompt) for the LLM agent.
     * This will override any instructions defined in a class-based agent if called after build().
     *
     * @return $this
     */
    public function instructions(string $instructions): self
    {
        $this->instructions = $instructions;

        return $this;
    }

    /**
     * Set the LLM model for the agent.
     * This will override any model defined in a class-based agent if called after build().
     *
     * @return $this
     */
    public function model(string $model): self
    {
        $this->model = $model;

        return $this;
    }

    /**
     * Override the instructions for a class-based agent.
     * This is a more specific alias for instructions() when using build().
     *
     * @return $this
     */
    public function withInstructionOverride(string $instructions): self
    {
        if ($this->agentClass === null && $this->name === null) {
            throw new AgentConfigurationException('withInstructionOverride can only be used after build() or define().');
        }
        $this->instructionOverride = $instructions;

        return $this;
    }

    /**
     * Set the prompt version for the agent.
     *
     * @return $this
     */
    public function withPromptVersion(string $version): self
    {
        $this->promptVersion = $version;

        return $this;
    }

    /**
     * Register the currently configured agent.
     *
     * @return BaseLlmAgent|null Returns the agent instance if class-based, or null for ad-hoc (as ad-hoc are definitions).
     *
     * @throws AgentConfigurationException If essential properties are missing.
     */
    public function register(): ?BaseLlmAgent
    {
        if (empty($this->name)) {
            throw new AgentConfigurationException('Agent name is required for registration.');
        }

        if ($this->agentClass) {
            // Registering a class-based agent
            $this->registry->register($this->name, $this->agentClass);

            // Apply overrides if any, by fetching the shared instance from registry
            /** @var BaseLlmAgent $instance */
            $instance = $this->registry->getAgent($this->name);

            if (! ($instance instanceof BaseLlmAgent)) {
                throw new AgentConfigurationException("Registered agent '{$this->name}' is not a BaseLlmAgent.");
            }

            if ($this->instructionOverride !== null) {
                $instance->setInstructions($this->instructionOverride);
            }
            if ($this->model !== null && $this->model !== $instance->getModel()) {
                $instance->setModel($this->model);
            }
            if ($this->promptVersion !== null) {
                $instance->setPromptVersion($this->promptVersion);
            }
            // Note: Description for class-based agents is primarily from the class itself.
            // Overriding it here might be confusing, so it's not directly supported but could be added.

            $this->resetBuilder();

            return $instance;

        } else {
            // Registering an ad-hoc (defined) agent
            if (empty($this->instructions)) {
                throw new AgentConfigurationException('Instructions are required for ad-hoc agent definition.');
            }
            // For ad-hoc agents, we store their definition.
            // The actual instantiation will happen when Agent::run() or Agent::named() is called,
            // potentially creating a generic LLM agent instance configured with these details.
            // This part will be more fully realized in Step 12.
            $definition = [
                'name' => $this->name,
                'description' => $this->description ?? '',
                'instructions' => $this->instructions,
                'model' => $this->model ?? config('vizra-adk.default_model', 'gemini-pro'),
                'tools' => [], // Ad-hoc agents initially have no tools via this builder path for MVP
                'type' => 'ad_hoc_llm',
            ];
            $this->registry->register($this->name, $definition);
            $this->resetBuilder();

            return null; // Ad-hoc registration doesn't return an instance directly from here.
        }
    }

    /**
     * Reset builder state for the next definition.
     */
    protected function resetBuilder(): void
    {
        $this->agentClass = null;
        $this->name = null;
        $this->description = null;
        $this->instructions = null;
        $this->model = null;
        $this->instructionOverride = null;
        $this->promptVersion = null;
    }
}
