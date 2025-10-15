# Prompt Caching

Prompt caching significantly reduces LLM API costs by storing and reusing frequently repeated content like system prompts, tool definitions, and conversation history. When using cached content, providers typically charge around 10% of the standard input token rate—resulting in up to 90% cost savings for multi-turn conversations and agentic workflows.

## Why Use Prompt Caching?

In typical agent interactions, much of the context remains constant across requests:
- System instructions stay the same
- Tool definitions don't change between calls
- Conversation history grows incrementally

Without caching, you pay full price to send this same content with every request. With caching enabled, you pay once to cache the content, then enjoy a 90% discount on subsequent reads. For applications with multiple interactions or long conversations, the savings compound quickly.

### Ideal Use Cases

- **Multi-turn conversations**: Chat interfaces where system prompts and tools remain consistent
- **Agentic workflows**: Complex agent chains that reuse the same tool definitions
- **RAG applications**: Static document context that doesn't change between queries
- **Customer support**: Agents with consistent personalities across many interactions
- **Code assistants**: Same codebase context applied to different questions

## Supported Providers

- **Anthropic (Direct)**: All Claude models support explicit prompt caching
- **OpenRouter**: Caching support varies by model
  - Claude models: Full explicit caching support
  - Gemini models: Automatic or explicit caching depending on version
  - OpenAI models: Automatic caching (provider-managed)
  - Other models: May support automatic caching

Cache parameters are automatically ignored by models that don't support them, making it safe to enable universally.

---

## Quick Start

Enable caching with two boolean properties:

```php
namespace App\Agents;

use Vizra\VizraADK\Agents\BaseLlmAgent;

class CustomerSupportAgent extends BaseLlmAgent
{
    //OpenRouter with Claude 3.7 Sonnet or you could use Anthropic directly
    protected ?string $provider = 'openrouter';
    protected string $model = 'anthropic/claude-3.7-sonnet';

    // Enable prompt caching
    protected bool $enablePromptCaching = true;
    protected bool $enableToolResultCaching = true;

    // Optional: Set cache TTL (default: '5m')
    protected string $cacheTTL = '5m'; // or '1h'
}
```

Your agent now caches:
- System instructions
- All tool definitions
- Conversation history
- Tool execution results

---

## How It Works

The SDK implements Anthropic's recommended caching strategy using three cache blocks:

### 1. System Prompt (Cache Block #1)

Your agent's instructions and personality are cached on the first request and reused in all subsequent requests.

```php
protected string $instructions = "You are a helpful customer support assistant...";
```

### 2. Tool Definitions (Cache Block #2)

All tools are cached together. Only the last tool in your tools array receives the `cache_control` marker, which caches all preceding tools as well. This follows Anthropic's best practice of minimizing cache breakpoints.

```php
protected array $tools = [
    ManageSitesTool::class,
    ManageClientsTool::class,
    SiteCareTool::class, // Only this one gets cache_control
];
```

### 3. Conversation History (Cache Block #3)

The last user message in the conversation history is marked for caching, enabling incremental caching as conversations grow. Each new turn builds on the previous cache.

### 4. Tool Results (Provider Option)

Tool execution results are cached via provider-specific options that don't count against Anthropic's 4-block limit.

### Cache Block Limit

Anthropic enforces a maximum of 4 cache breakpoints per request. Vizra ADK uses 3 strategically, leaving room for future enhancements while following Anthropic's documented best practices.

---

## Cost Analysis

Real-world examples using Claude Sonnet 3.7 ($3/MTok input, $0.30/MTok cached):

### Example 1: Customer Support Chat (5 turns)

```
Turn 1: System (1K) + Tools (2K) + User (200)
  Cost: $3/MTok × 3,200 = $9.60

Turn 2: Cache hit (3K) + User (200)
  Cost: $0.30/MTok × 3,000 + $3/MTok × 200 = $1.50

Turn 3: Cache hit (3,200) + User (200)
  Cost: $0.30/MTok × 3,200 + $3/MTok × 200 = $1.56

Turn 4: Cache hit (3,400) + User (200)
  Cost: $0.30/MTok × 3,400 + $3/MTok × 200 = $1.62

Turn 5: Cache hit (3,600) + User (200)
  Cost: $0.30/MTok × 3,600 + $3/MTok × 200 = $1.68

Total: $15.96 (vs $48.00 without caching)
Savings: 67%
```

### Example 2: Agentic Workflow (5 steps)

```
Step 1: System (1K) + Tools (3K) + Input (500)
  Cost: $3/MTok × 4,500 = $13.50

Step 2: Cache hit (4K) + Input (500) + Tool results (1K)
  Cost: $0.30/MTok × 4,000 + $3/MTok × 1,500 = $5.70

Steps 3-5: Same as Step 2
  Cost: $5.70 × 3 = $17.10

Total: $36.30 (vs $67.50 without caching)
Savings: 46%
```

### Cost Structure

| Operation | Cost Multiplier | When Applied |
|-----------|----------------|--------------|
| Cache Write (5m) | 1.25x | First request only |
| Cache Read (5m) | 0.10x | Subsequent requests |
| Cache Write (1h) | 2.00x | First request only |
| Cache Read (1h) | 0.10x | Subsequent requests |

---

## Configuration Options

### `enablePromptCaching` (bool, default: `false`)

Enables caching for system prompts, tool definitions, and conversation history.

### `enableToolResultCaching` (bool, default: `false`)

Enables caching for tool execution results using provider-specific options that don't count against block limits.

### `cacheTTL` (string, default: `'5m'`)

Controls cache time-to-live. The cache timer resets each time cached content is accessed.

**5-Minute Cache (`'5m'`)**
- Expires after 5 minutes of inactivity
- Free cache refresh on each use
- Best for: Interactive chats, quick workflows, development

**1-Hour Cache (`'1h'`)**
- Expires after 1 hour of inactivity
- Costs 2x base price (still cheaper than no caching)
- Best for: Long-running workflows, batch jobs, scheduled tasks

**Note**: Maximum cache lifetime is 1 hour. Caches are organization-specific and never shared between organizations.

Learn more: [Anthropic Cache Storage & Sharing](https://docs.anthropic.com/en/docs/build-with-claude/prompt-caching#cache-storage-and-sharing)

---

## Cache Invalidation

Cache is invalidated when:
- System instructions change
- Tool definitions are added, removed, or modified
- Tool parameters or descriptions change

Cache is **NOT** invalidated by:
- New user messages
- Temperature or max_tokens changes
- Different conversations using the same agent configuration

---

## Best Practices

### When to Enable Caching

- Multi-turn conversations with consistent system prompts
- Agents with multiple tools that don't change frequently
- Workflows reusing the same context across requests
- RAG implementations with static document context
- Any scenario with more than 1,024 tokens of reusable content

### When to Avoid Caching

- Single-turn requests with no follow-up
- Prompts under 1,024 tokens (won't be cached anyway)
- Agents with frequently changing tool definitions
- Scenarios where cache write cost exceeds read savings

### Optimization Tips

1. **Maintain stable content**: Avoid modifying system prompts or tools during active sessions
2. **Choose appropriate TTL**: Use `5m` for interactive sessions, `1h` for batch processing
3. **Monitor performance**: Verify savings via provider consoles
4. **Group related tools**: Tools cached together reduce block usage

---

## Monitoring

To verify caching is working and inspect cache usage:

- **Anthropic**: Check the [Anthropic Console](https://console.anthropic.com/) for detailed request metrics including cache creation and cache read tokens
- **OpenRouter**: View cache usage in the [Activity page](https://openrouter.ai/activity) or see the [OpenRouter caching inspection guide](https://openrouter.ai/docs/features/prompt-caching#inspecting-cache-usage)

---

## Technical Details

### Implementation

Caching uses Prism's `withProviderOptions()` method to set cache control parameters:

```php
$systemMessage = (new SystemMessage($content))
    ->withProviderOptions([
        'cacheType' => 'ephemeral',
        'ttl' => $this->cacheTTL,
    ]);
```

### Provider Detection

The SDK automatically detects caching support. Can be extended for other providers in the future.

```php
protected function supportsPromptCaching(): bool
{
    $provider = $this->getProvider();
    $providerLower = strtolower($provider);

    return $provider === Provider::Anthropic->value
        || $provider === Provider::OpenRouter->value
        || $providerLower === 'anthropic'
        || $providerLower === 'openrouter';
}
```

### Minimum Token Requirements

- **Claude Opus 4 / Sonnet 4**: 1,024 tokens minimum

Content below these thresholds won't be cached (no error, silently ignored).

### Advanced: Runtime Cache Control

```php
class FlexibleAgent extends BaseLlmAgent
{
    protected bool $enablePromptCaching = true;
    protected string $cacheTTL = '5m';

    public function useLongerCache(): self
    {
        $this->cacheTTL = '1h';
        return $this;
    }
}

// Switch to 1-hour cache at runtime
$agent->useLongerCache();
```

---

## References

- [Anthropic Prompt Caching Documentation](https://docs.anthropic.com/claude/docs/prompt-caching)
- [Anthropic Cache Storage & Sharing](https://docs.anthropic.com/en/docs/build-with-claude/prompt-caching#cache-storage-and-sharing)
- [OpenRouter Prompt Caching Documentation](https://openrouter.ai/docs/features/prompt-caching)
- [Anthropic Cache Invalidation Rules](https://docs.anthropic.com/en/docs/build-with-claude/prompt-caching#what-invalidates-the-cache)
