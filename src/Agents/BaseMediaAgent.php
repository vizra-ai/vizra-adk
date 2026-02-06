<?php

namespace Vizra\VizraADK\Agents;

use Vizra\VizraADK\Execution\MediaAgentExecutor;
use Vizra\VizraADK\System\AgentContext;

/**
 * Abstract Class BaseMediaAgent
 * Base class for media generation agents (image, audio, video).
 *
 * These agents don't use LLMs directly - they interface with
 * media generation APIs like DALL-E, Imagen, TTS, etc.
 */
abstract class BaseMediaAgent extends BaseAgent
{
    /**
     * The media provider (e.g., 'openai', 'google')
     */
    protected string $provider;

    /**
     * The model to use for generation
     */
    protected string $model;

    /**
     * Whether to show this agent in the Chat UI agent selector.
     * Default is true to show all agents.
     * Set to false to hide from the chat interface.
     */
    protected bool $showInChatUi = true;

    /**
     * Get the provider
     */
    public function getProvider(): string
    {
        return $this->provider;
    }

    /**
     * Get the model
     */
    public function getModel(): string
    {
        return $this->model;
    }

    /**
     * Set the provider
     */
    public function setProvider(string $provider): static
    {
        $this->provider = $provider;
        return $this;
    }

    /**
     * Set the model
     */
    public function setModel(string $model): static
    {
        $this->model = $model;
        return $this;
    }

    /**
     * Get whether to show in Chat UI
     */
    public function getShowInChatUi(): bool
    {
        return $this->showInChatUi;
    }

    /**
     * Set whether to show in Chat UI
     */
    public function setShowInChatUi(bool $showInChatUi): static
    {
        $this->showInChatUi = $showInChatUi;
        return $this;
    }

    /**
     * Get instructions for this agent.
     * Media agents return their description as instructions.
     */
    public function getInstructions(): string
    {
        return $this->description;
    }

    /**
     * Get loaded tools for this agent.
     * Media agents don't use tools, so this returns an empty array.
     */
    public function getLoadedTools(): array
    {
        return [];
    }

    /**
     * Get loaded sub-agents for this agent.
     * Media agents don't use sub-agents, so this returns an empty array.
     */
    public function getLoadedSubAgents(): array
    {
        return [];
    }

    /**
     * Create a fluent media agent executor.
     *
     * Usage: ImageAgent::run('A sunset over the ocean')->quality('hd')->go()
     *
     * @param mixed $input The prompt or input for generation
     */
    public static function run(mixed $input): MediaAgentExecutor
    {
        return new MediaAgentExecutor(static::class, $input);
    }

    /**
     * Execute the media generation.
     * Implemented by subclasses (ImageAgent, AudioAgent, etc.)
     */
    abstract public function execute(mixed $input, AgentContext $context): mixed;

    /**
     * Get the executor class for this media agent type.
     * Can be overridden by subclasses for specialized executors.
     */
    public static function getExecutorClass(): string
    {
        return MediaAgentExecutor::class;
    }
}
