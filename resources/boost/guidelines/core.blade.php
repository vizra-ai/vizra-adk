# Vizra ADK - AI Agent Development Kit

Vizra ADK is a comprehensive Laravel package for building intelligent AI agents with tool usage, memory persistence, and workflow capabilities.

## Package Overview

- **Package**: `vizra/vizra-adk`
- **Namespace**: `Vizra\VizraADK`
- **Laravel**: 11.x, 12.x
- **PHP**: 8.2+

## Core Concepts

### Agents
Agents are AI-powered classes that can reason, use tools, and maintain memory. All agents extend `BaseLlmAgent`.

```php
use Vizra\VizraADK\Agents\BaseLlmAgent;

class MyAgent extends BaseLlmAgent
{
    protected string $name = 'my_agent';
    protected string $description = 'What this agent does';
    protected string $instructions = 'System prompt for the LLM';
    protected string $model = 'gpt-4o';
    protected array $tools = [];
}
```

### Tools
Tools extend agent capabilities. Implement `ToolInterface` to create custom tools.

```php
use Vizra\VizraADK\Contracts\ToolInterface;
use Vizra\VizraADK\Agents\AgentContext;

class MyTool implements ToolInterface
{
    public function definition(): array
    {
        return [
            'name' => 'my_tool',
            'description' => 'What this tool does',
            'parameters' => [
                'type' => 'object',
                'properties' => [
                    'param1' => ['type' => 'string', 'description' => 'Parameter description'],
                ],
                'required' => ['param1'],
            ],
        ];
    }

    public function execute(array $arguments, AgentContext $context): string
    {
        return json_encode(['result' => 'success']);
    }
}
```

### Running Agents

```php
// Basic execution
$response = MyAgent::run('Hello, can you help me?')->go();

// With user context (persists memory)
$response = MyAgent::run('Remember my name is John')
    ->forUser($user)
    ->go();

// With session persistence
$response = MyAgent::run('What did we discuss?')
    ->forUser($user)
    ->withSession($sessionId)
    ->go();

// With streaming
$stream = MyAgent::run($message)
    ->streaming(true)
    ->go();
```

## Key Classes

| Class | Purpose |
|-------|---------|
| `BaseLlmAgent` | Base class for all agents |
| `BaseWorkflowAgent` | Agent for orchestrating workflows |
| `ToolInterface` | Contract for tool implementations |
| `AgentContext` | Execution context passed to tools |
| `AgentMemory` | Memory management |
| `DelegateToSubAgentTool` | Delegate tasks to sub-agents |
| `MemoryTool` | Store/retrieve persistent memories |
| `VectorMemoryTool` | Semantic search in memories (RAG) |

## Artisan Commands

```bash
# Create new agent
php artisan vizra:make:agent MyAgent

# Create new tool
php artisan vizra:make:tool MyTool

# List discovered agents
php artisan vizra:agents

# Interactive chat with agent
php artisan vizra:chat agent_name

# View execution trace
php artisan vizra:trace {traceId}

# Start web dashboard
php artisan vizra:dashboard
```

## Naming Conventions

- **Agents**: `{Purpose}Agent` (e.g., `CustomerSupportAgent`)
- **Tools**: `{Action}Tool` (e.g., `DatabaseQueryTool`)
- **Agent names**: snake_case (e.g., `customer_support`)
- **Namespace**: `App\Agents` for agents, `App\Tools` for tools

## Auto-Discovery

Agents are automatically discovered - no manual registration needed. Simply create the class in `App\Agents` namespace.

## LLM Providers

Vizra ADK supports multiple providers via Prism PHP:
- OpenAI (gpt-4o, gpt-4o-mini)
- Anthropic (claude-3-opus, claude-3-sonnet)
- Google (gemini-pro, gemini-flash)
- Ollama (local models)

## Configuration

Environment variables:
```env
OPENAI_API_KEY=
ANTHROPIC_API_KEY=
GEMINI_API_KEY=
```

Config file: `config/vizra-adk.php`

## Documentation

For detailed documentation, see: https://vizra.ai/docs
