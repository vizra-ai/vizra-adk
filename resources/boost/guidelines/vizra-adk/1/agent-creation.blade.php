# Creating Vizra ADK Agents

Vizra ADK agents are AI-powered Laravel classes that can reason, use tools, and maintain memory. All agents must extend `BaseLlmAgent`.

## Agent Class Structure

Every agent MUST follow this structure:

```php
<?php

namespace App\Agents;

use Vizra\VizraADK\Agents\BaseLlmAgent;

class {{ AgentName }}Agent extends BaseLlmAgent
{
    /**
     * Unique identifier for the agent (snake_case)
     */
    protected string $name = '{{ snake_case(AgentName) }}';

    /**
     * Brief description of what the agent does
     */
    protected string $description = '{{ One line description }}';

    /**
     * System prompt/instructions for the LLM
     */
    protected string $instructions = <<<'INSTRUCTIONS'
        You are a {{ role description }}.
        
        Your capabilities include:
        - {{ capability 1 }}
        - {{ capability 2 }}
        
        Guidelines:
        - {{ guideline 1 }}
        - {{ guideline 2 }}
        INSTRUCTIONS;

    /**
     * LLM model to use
     * Options: 'gpt-4o', 'gpt-4o-mini', 'claude-3-opus', 'claude-3-sonnet', 'gemini-pro', etc.
     */
    protected string $model = '{{ model_name }}';

    /**
     * Optional: Temperature setting (0.0 to 1.0)
     * Lower = more deterministic, Higher = more creative
     */
    protected ?float $temperature = null;

    /**
     * Optional: Maximum tokens for response
     */
    protected ?int $maxTokens = null;

    /**
     * Optional: Maximum steps for tool execution (default: 5)
     */
    protected int $maxSteps = 5;

    /**
     * Optional: Provider override (e.g., 'openai', 'anthropic', 'google')
     */
    protected ?string $provider = null;

    /**
     * Optional: Top-p sampling parameter
     */
    protected ?float $topP = null;

    /**
     * Tools this agent can use
     * Each tool must implement ToolInterface
     */
    protected array $tools = [
        // ToolClass::class,
    ];
}
```

## Key Rules

1. **Naming Convention**: 
   - Class name MUST end with `Agent`
   - The `$name` property must be unique and in snake_case
   - Place agents in `App\Agents` namespace

2. **Auto-Discovery**:
   - Agents are automatically discovered - NO registration needed
   - Simply create the class and it's ready to use

3. **Required Properties**:
   - `$name`: Unique identifier
   - `$description`: Brief description
   - `$instructions`: System prompt for the LLM
   - `$model`: Which LLM model to use

4. **Instructions Best Practices**:
   - Be specific about the agent's role
   - List capabilities clearly
   - Include behavioral guidelines
   - Use heredoc (<<<'INSTRUCTIONS') for multi-line instructions

## Common Agent Patterns

### Customer Service Agent
```php
class CustomerServiceAgent extends BaseLlmAgent
{
    protected string $name = 'customer_service';
    protected string $description = 'Handles customer inquiries and support tickets';
    protected string $instructions = <<<'INSTRUCTIONS'
        You are a professional customer service representative.
        
        Your approach:
        - Always be polite and empathetic
        - Gather all necessary information before providing solutions
        - Escalate complex issues appropriately
        
        Remember to:
        - Thank the customer for their patience
        - Confirm understanding of their issue
        - Provide clear next steps
        INSTRUCTIONS;
    protected string $model = 'gpt-4o';
    protected array $tools = [
        OrderLookupTool::class,
        TicketCreationTool::class,
        RefundProcessorTool::class,
    ];
}
```

### Data Analysis Agent
```php
class DataAnalysisAgent extends BaseLlmAgent
{
    protected string $name = 'data_analyst';
    protected string $description = 'Analyzes data and generates insights';
    protected string $instructions = <<<'INSTRUCTIONS'
        You are an expert data analyst.
        
        Your responsibilities:
        - Analyze provided data thoroughly
        - Identify patterns and anomalies
        - Generate actionable insights
        - Create clear visualizations when appropriate
        
        Always:
        - Verify data quality first
        - Explain your methodology
        - Provide confidence levels for findings
        INSTRUCTIONS;
    protected string $model = 'gpt-4o';
    protected ?float $temperature = 0.3; // Lower temperature for analytical tasks
    protected array $tools = [
        DatabaseQueryTool::class,
        ChartGeneratorTool::class,
        StatisticalAnalysisTool::class,
    ];
}
```

### Creative Content Agent
```php
class ContentCreatorAgent extends BaseLlmAgent
{
    protected string $name = 'content_creator';
    protected string $description = 'Creates engaging content for various platforms';
    protected string $instructions = <<<'INSTRUCTIONS'
        You are a creative content specialist.
        
        Your expertise includes:
        - Writing engaging blog posts
        - Creating social media content
        - Developing marketing copy
        - Crafting email campaigns
        
        Style guidelines:
        - Match the brand voice
        - Use active voice
        - Keep sentences concise
        - Include calls to action
        INSTRUCTIONS;
    protected string $model = 'claude-3-opus';
    protected ?float $temperature = 0.8; // Higher temperature for creativity
    protected array $tools = [
        SEOAnalyzerTool::class,
        ImageGeneratorTool::class,
    ];
}
```

## Using Your Agent

### Basic Execution
```php
use App\Agents\MyAgent;

// Simple execution
$response = MyAgent::run('Hello, can you help me?')->go();

// With user context (maintains memory)
$response = MyAgent::run('Remember my name is John')
    ->forUser($user)
    ->go();

// With session persistence
$response = MyAgent::run('What did we discuss earlier?')
    ->forUser($user)
    ->withSession($sessionId)
    ->go();
```

### Advanced Execution Options
```php
// Pass parameters to the agent
$response = MyAgent::run($message)
    ->withParameters([
        'context' => 'customer_support',
        'priority' => 'high',
        'metadata' => ['ticket_id' => 12345]
    ])
    ->go();

// Enable streaming (returns a stream object)
$stream = MyAgent::run($message)
    ->forUser($user)
    ->streaming(true)
    ->go();

// The stream can be iterated or converted to string
foreach ($stream as $chunk) {
    echo $chunk; // Handle each token as it arrives
}

// Get the response (returns a string by default)
$result = MyAgent::run($message)->go();
echo $result;  // The response is a string
```

## Sub-Agent Delegation

Agents can delegate to other agents using the `DelegateToSubAgentTool`:

```php
use Vizra\VizraADK\Tools\DelegateToSubAgentTool;

class ManagerAgent extends BaseLlmAgent
{
    protected string $name = 'manager';
    protected string $description = 'Coordinates tasks between specialized agents';
    protected string $instructions = <<<'INSTRUCTIONS'
        You are a task coordinator. Delegate specialized tasks to appropriate agents:
        - Use 'research' agent for information gathering
        - Use 'writer' agent for content creation
        - Use 'analyzer' agent for data analysis
        INSTRUCTIONS;
    protected string $model = 'gpt-4o';
    protected array $tools = [
        DelegateToSubAgentTool::class,
    ];
}
```

## Memory and Context

### Persistent Memory
```php
// Memory persists across conversations for a user
$response1 = MyAgent::run('My favorite color is blue')
    ->forUser($user)
    ->go();

// Later conversation - agent remembers
$response2 = MyAgent::run('What is my favorite color?')
    ->forUser($user)
    ->go(); // Will remember "blue"
```

### Session-Based Memory
```php
// Create a session for a specific conversation thread
$sessionId = 'support-ticket-123';

$response = MyAgent::run($message)
    ->forUser($user)
    ->withSession($sessionId)
    ->go();
```

### Using Memory Tools
```php
use Vizra\VizraADK\Tools\MemoryTool;
use Vizra\VizraADK\Tools\VectorMemoryTool;

class KnowledgeAgent extends BaseLlmAgent
{
    protected array $tools = [
        MemoryTool::class,       // Store/retrieve memories
        VectorMemoryTool::class,  // Semantic search in memories
    ];
}
```

## Model Selection Guide

Choose the appropriate model based on your needs:

- **GPT-4o**: Best for complex reasoning, tool use, and general tasks
- **GPT-4o-mini**: Cost-effective for simpler tasks
- **Claude-3-Opus**: Excellent for creative writing and analysis
- **Claude-3-Sonnet**: Balanced performance and cost
- **Gemini-Pro**: Good for multi-modal tasks
- **Gemini-Flash**: Fast responses for simple queries

## Error Handling

Agents handle errors gracefully by default, but you can customize behavior:

```php
try {
    $response = MyAgent::run($message)->go();
} catch (\Vizra\VizraADK\Exceptions\AgentException $e) {
    // Handle agent-specific errors
    Log::error('Agent error: ' . $e->getMessage());
}
```

## Testing Your Agent

```php
// In your test file
use Tests\TestCase;
use App\Agents\MyAgent;

class MyAgentTest extends TestCase
{
    public function test_agent_responds_correctly()
    {
        $response = MyAgent::run('test message')->go();
        
        $this->assertNotEmpty($response);
        $this->assertIsString($response);
    }
}
```

## Common Mistakes to Avoid

1. **Don't forget to extend BaseLlmAgent** - Your agent won't work without it
2. **Don't use duplicate agent names** - Each $name must be unique
3. **Don't hardcode sensitive data** - Use environment variables or configuration
4. **Don't make instructions too vague** - Be specific about the agent's role
5. **Don't forget to include required tools** - Agents need tools to interact with systems

## Debugging Tips

1. Use the Vizra dashboard to test agents: `php artisan vizra:dashboard`
2. Check agent traces: `php artisan vizra:trace {traceId}`
3. List all discovered agents: `php artisan vizra:agents`
4. Test in CLI: `php artisan vizra:chat {agent_name}`

## Next Steps

- Create custom tools for your agent: See `tool-creation.blade.php`
- Build agent workflows: See `workflow-patterns.blade.php`
- Add evaluation tests: See `evaluation.blade.php`