# Turn Management & Regeneration

## What Changed
- Agent conversations now persist `turn_uuid`, `user_message_id`, and `variant_index` on every message (`src/Models/AgentMessage.php`).
- `StateManager` collapses older assistant variants per turn and exposes helper methods for preparing regeneration (`src/Services/StateManager.php`).
- `AgentContext` automatically assigns turn metadata when you add messages and keeps track of the next assistant variant (`src/System/AgentContext.php`).
- `AgentManager` gained a `regenerate()` entry point that replays a specific turn with fresh LLM output (`src/Services/AgentManager.php`).
- A new migration (`database/migrations/2025_06_07_000000_add_turn_metadata_to_agent_messages_table.php`) backfills turn metadata for existing transcripts.

Run the migration after updating:

```bash
php artisan migrate
```

## Turn Anatomy
Each user/assistant exchange is treated as a **turn** identified by a `turn_uuid`.

- User messages start a turn and receive the UUID automatically.
- Assistant replies share the same `turn_uuid` and increment `variant_index` for every regeneration.
- `user_message_id` links an assistant variant back to the originating user record so you can fetch all variants for a turn (`AgentMessage::assistantVariants()`).
- When loading history, earlier assistant variants are flagged as `hidden_from_prompt`, letting the LLM see only the most recent answer while you can still display prior variants to end users.

## Preparing Regeneration
`StateManager::prepareRegeneration()` is a convenience wrapper around `loadContext()` that:

1. Reloads the full session context (including memory state).
2. Locates the user message for the target `turn_uuid`.
3. Calculates the next `variant_index` for the assistant.
4. Marks previous assistant variants in the context as hidden so they stay out of the retry prompt.
5. Primes the `AgentContext` to reuse the turn metadata during execution.

It returns an array of `{ context, user_message, next_variant_index }` that `AgentManager::regenerate()` uses internally, but you can call it directly if you need finer control.

## Regenerating a Turn
Call `AgentManager::regenerate()` with the agent identifier, an existing session, and the turn UUID you want to retry:

```php
use Vizra\VizraADK\Services\AgentManager;

def regenerateShippingEstimate(AgentManager $agents, string $sessionId, string $turnUuid): string
{
    return $agents->regenerate(
        agentNameOrClass: 'shipping-bot',
        sessionId: $sessionId,
        turnUuid: $turnUuid,
        userId: auth()->id(), // optional, forwards to StateManager for memory scoping
    );
}
```

- The method fetches the original user prompt, replays it through the LLM, and persists the new assistant variant on success.
- The return value mirrors `AgentManager::run()`—either the LLM text or a stream, depending on your agent’s configuration.
- After regeneration, the latest assistant variant is the only one sent in future prompts, but all variants remain available in storage.

## Surfacing Turn Metadata in a UI
When rendering a transcript, expose each message’s `turn_uuid` and the current `variant_index`. Users can pick a turn to retry, then pass that UUID back to your application to trigger regeneration:

```php
$turns = AgentMessage::query()
    ->where('agent_session_id', $session->id)
    ->orderBy('created_at')
    ->with(['assistantVariants'])
    ->get();

foreach ($turns as $message) {
    // Display $message->turn_uuid and the latest $message->assistantVariants
}
```

For custom tooling that inserts messages manually, feed them through `AgentContext::addMessage()` so the turn bookkeeping stays consistent.

## Troubleshooting
- **Missing metadata?** Ensure the migration ran and any seed data uses `AgentContext::addMessage()` or provides `turn_uuid` and `variant_index` explicitly.
- **Regeneration returns the same output?** Confirm your agent doesn’t short-circuit in `afterLlmResponse()` by reusing cached text; each retry receives a fresh variant index, so you can store multiple alternatives.
- **Prompt includes old variants?** Verify you’re not overriding the `hidden_from_prompt` flag when manipulating history manually.
