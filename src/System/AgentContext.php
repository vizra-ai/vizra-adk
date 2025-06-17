<?php

namespace Vizra\VizraAdk\System;

use Illuminate\Support\Collection;

/**
 * Class AgentContext
 * Encapsulates the context for an agent's execution session.
 */
class AgentContext
{
    protected ?string $sessionId;
    protected mixed $userInput;
    protected array $state = [];
    protected Collection $conversationHistory; // Collection of AgentMessage arrays or objects

    /**
     * AgentContext constructor.
     *
     * @param string|null $sessionId Unique identifier for the session.
     * @param mixed|null $userInput Initial user input.
     * @param array $initialState Optional initial state data.
     * @param Collection|null $conversationHistory Optional initial conversation history.
     */
    public function __construct(
        ?string $sessionId,
        mixed $userInput = null,
        array $initialState = [],
        ?Collection $conversationHistory = null
    ) {
        $this->sessionId = $sessionId;
        $this->userInput = $userInput;
        $this->state = $initialState;
        $this->conversationHistory = $conversationHistory ?? new Collection();
    }

    /**
     * Get the session identifier.
     * @return string|null
     */
    public function getSessionId(): ?string
    {
        return $this->sessionId;
    }

    /**
     * Get the initial user input for this interaction.
     * @return mixed
     */
    public function getUserInput(): mixed
    {
        return $this->userInput;
    }

    /**
     * Set the user input.
     * @param mixed $input
     */
    public function setUserInput(mixed $input): void
    {
        $this->userInput = $input;
    }

    /**
     * Get a value from the session-scoped state.
     *
     * @param string $key The key of the state variable.
     * @param mixed|null $default The default value if the key is not found.
     * @return mixed
     */
    public function getState(string $key, mixed $default = null): mixed
    {
        return $this->state[$key] ?? $default;
    }

    /**
     * Set a value in the session-scoped state.
     *
     * @param string $key The key of the state variable.
     * @param mixed $value The value to set.
     */
    public function setState(string $key, mixed $value): void
    {
        $this->state[$key] = $value;
        // Potentially dispatch a StateUpdated event here or in StateManager
    }

    /**
     * Get all state data.
     * @return array
     */
    public function getAllState(): array
    {
        return $this->state;
    }

    /**
     * Load an array of state data, merging with existing state.
     * @param array $stateData
     */
    public function loadState(array $stateData): void
    {
        $this->state = array_merge($this->state, $stateData);
    }

    /**
     * Get the conversation history.
     * @return Collection
     */
    public function getConversationHistory(): Collection
    {
        return $this->conversationHistory;
    }

    /**
     * Add a message to the conversation history.
     *
     * @param array $message Message structure: ['role' => 'user', 'content' => 'Hello', 'timestamp' => now()]
     */
    public function addMessage(array $message): void
    {
        $this->conversationHistory->push($message);
    }

    /**
     * Set the entire conversation history.
     * @param Collection $history
     */
    public function setConversationHistory(Collection $history): void
    {
        $this->conversationHistory = $history;
    }
}
