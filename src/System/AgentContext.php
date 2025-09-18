<?php

namespace Vizra\VizraADK\System;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;

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

    protected ?string $activeTurnUuid = null;

    protected int $currentVariantIndex = 0;

    protected int $nextVariantIndex = 0;

    /**
     * AgentContext constructor.
     *
     * @param  string|null  $sessionId  Unique identifier for the session.
     * @param  mixed|null  $userInput  Initial user input.
     * @param  array  $initialState  Optional initial state data.
     * @param  Collection|null  $conversationHistory  Optional initial conversation history.
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
        $this->conversationHistory = new Collection;
        $this->setConversationHistory($conversationHistory ?? new Collection);
    }

    /**
     * Get the session identifier.
     */
    public function getSessionId(): ?string
    {
        return $this->sessionId;
    }

    /**
     * Get the initial user input for this interaction.
     */
    public function getUserInput(): mixed
    {
        return $this->userInput;
    }

    /**
     * Set the user input.
     */
    public function setUserInput(mixed $input): void
    {
        $this->userInput = $input;
    }

    /**
     * Get a value from the session-scoped state.
     *
     * @param  string  $key  The key of the state variable.
     * @param  mixed|null  $default  The default value if the key is not found.
     */
    public function getState(string $key, mixed $default = null): mixed
    {
        return $this->state[$key] ?? $default;
    }

    /**
     * Set a value in the session-scoped state.
     *
     * @param  string  $key  The key of the state variable.
     * @param  mixed  $value  The value to set.
     */
    public function setState(string $key, mixed $value): void
    {
        $this->state[$key] = $value;
        // Potentially dispatch a StateUpdated event here or in StateManager
    }

    /**
     * Get all state data.
     */
    public function getAllState(): array
    {
        return $this->state;
    }

    /**
     * Load an array of state data, merging with existing state.
     */
    public function loadState(array $stateData): void
    {
        $this->state = array_merge($this->state, $stateData);
    }

    /**
     * Get the conversation history.
     */
    public function getConversationHistory(): Collection
    {
        return $this->conversationHistory;
    }

    /**
     * Add a message to the conversation history.
     *
     * @param  array  $message  Message structure: ['role' => 'user', 'content' => 'Hello', 'timestamp' => now()]
     */
    public function addMessage(array $message): void
    {
        $normalized = $this->prepareMessage($message);
        $this->conversationHistory->push($normalized);
    }

    /**
     * Set the entire conversation history.
     */
    public function setConversationHistory(Collection $history): void
    {
        $this->resetTurnTracking();

        $normalized = $history->map(function ($message) {
            if ($message instanceof Collection) {
                $message = $message->toArray();
            }

            return $this->prepareMessage((array) $message);
        });

        $this->conversationHistory = new Collection($normalized->all());
    }

    /**
     * Get the UUID representing the current conversation turn.
     */
    public function getActiveTurnUuid(): ?string
    {
        return $this->activeTurnUuid;
    }

    /**
     * Get the next assistant variant index for the active turn.
     */
    public function getNextVariantIndex(): int
    {
        return $this->nextVariantIndex;
    }

    /**
     * Prepare the context to continue an existing turn at a specific variant index.
     */
    public function useTurn(string $turnUuid, int $nextVariantIndex = 0): void
    {
        $this->activeTurnUuid = $turnUuid;
        $this->currentVariantIndex = $nextVariantIndex;
        $this->nextVariantIndex = $nextVariantIndex;
    }

    /**
     * Normalize message metadata prior to storage.
     */
    protected function prepareMessage(array $message): array
    {
        $role = $message['role'] ?? null;

        if ($role === 'user') {
            $turnUuid = $message['turn_uuid'] ?? (string) Str::uuid();
            $message['turn_uuid'] = $turnUuid;

            $variantIndex = $message['variant_index'] ?? 0;
            $message['variant_index'] = $variantIndex;

            $this->activeTurnUuid = $turnUuid;
            $this->currentVariantIndex = $variantIndex;
            $this->nextVariantIndex = max($variantIndex, 0);

            return $message;
        }

        if (! isset($message['turn_uuid'])) {
            if ($this->activeTurnUuid === null) {
                $this->activeTurnUuid = (string) Str::uuid();
            }

            $message['turn_uuid'] = $this->activeTurnUuid;
        } elseif ($message['turn_uuid'] !== $this->activeTurnUuid) {
            $this->activeTurnUuid = $message['turn_uuid'];
            $this->currentVariantIndex = 0;
            $this->nextVariantIndex = 0;
        }

        if ($role === 'assistant') {
            if (! isset($message['variant_index'])) {
                $message['variant_index'] = $this->nextVariantIndex;
            }

            $this->currentVariantIndex = $message['variant_index'];
            $this->nextVariantIndex = max($message['variant_index'] + 1, $this->nextVariantIndex);
        } else {
            if (! isset($message['variant_index'])) {
                $message['variant_index'] = $this->currentVariantIndex;
            } else {
                $this->currentVariantIndex = $message['variant_index'];
            }
        }

        return $message;
    }

    /**
     * Reset internal tracking for turn metadata.
     */
    protected function resetTurnTracking(): void
    {
        $this->activeTurnUuid = null;
        $this->currentVariantIndex = 0;
        $this->nextVariantIndex = 0;
    }
}
