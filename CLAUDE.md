# Vizra ADK - Laravel AI Agent Development Kit

## Project Overview
Vizra ADK is a comprehensive Laravel package for building intelligent AI agents with tool usage, memory persistence, and workflow capabilities. This package integrates with multiple LLM providers (OpenAI, Anthropic, Google Gemini) through Prism PHP.

**Package Name**: vizra/vizra-adk  
**Version**: 0.0.17  
**PHP**: ^8.2  
**Laravel**: ^11.0 | ^12.0  
**License**: MIT

## Key Concepts

### Agents
- **BaseLlmAgent**: Core agent class that interfaces with LLMs
- **BaseWorkflowAgent**: Agent for orchestrating complex workflows
- Auto-discovery: Agents are automatically discovered without manual registration
- Support for sub-agent delegation and task distribution

### Tools
- Implement `ToolInterface` to create custom tools
- Tools extend agent capabilities (database, API, external services)
- MCP (Model Context Protocol) support for external tool servers
- Built-in tools: MemoryTool, VectorMemoryTool, DelegateToSubAgentTool

### Memory System
- Persistent conversation memory across sessions
- Vector memory for RAG (Retrieval Augmented Generation)
- Multiple embedding providers: OpenAI, Cohere, Gemini, Ollama
- Meilisearch integration for vector storage

### Workflows
- SequentialWorkflow: Execute agents in order
- ParallelWorkflow: Run multiple agents simultaneously
- ConditionalWorkflow: Branch based on conditions
- LoopWorkflow: Iterate over data or conditions

## Directory Structure

```
vizra-adk/
├── src/
│   ├── Agents/           # Core agent implementations
│   ├── Tools/            # Tool implementations and MCP wrapper
│   ├── Memory/           # Memory management
│   ├── Services/         # Core services (Manager, Registry, Tracer)
│   ├── Models/           # Eloquent models
│   ├── Console/Commands/ # Artisan commands
│   ├── Evaluations/      # Testing and evaluation framework
│   ├── Events/           # Laravel events
│   ├── Http/             # Controllers and API endpoints
│   ├── Livewire/         # Dashboard components
│   └── Providers/        # Service providers and embeddings
├── tests/
│   ├── Unit/             # Unit tests
│   ├── Feature/          # Feature tests
│   └── Integration/      # Integration tests
├── config/               # Package configuration
├── database/migrations/  # Database migrations
├── resources/views/      # Blade templates and Livewire views
└── examples/             # Example agents and tools
```

## Testing

### Test Framework
- **Pest PHP**: Modern testing framework
- **Orchestra Testbench**: Laravel package testing

### Running Tests
```bash
# Run all tests
./vendor/bin/pest

# Run with coverage
./vendor/bin/pest --coverage

# Run specific test file
./vendor/bin/pest tests/Unit/Agents/BaseLlmAgentTest.php

# Run tests in parallel
./vendor/bin/pest --parallel
```

### Test Structure
- Unit tests: Isolated component testing
- Feature tests: End-to-end functionality
- Integration tests: Cross-component interactions

## Development Commands

### Artisan Commands
```bash
# Install package (migrations, config)
php artisan vizra:install

# Create new agent
php artisan vizra:make:agent MyAgent

# Create new tool
php artisan vizra:make:tool MyTool

# Create evaluation
php artisan vizra:make:eval MyEvaluation

# Create assertion
php artisan vizra:make:assertion MyAssertion

# Interactive chat with agent
php artisan vizra:chat agent_name

# List discovered agents
php artisan vizra:agents

# Run evaluations
php artisan vizra:eval:run

# Start web dashboard
php artisan vizra:dashboard

# View agent trace
php artisan vizra:trace {traceId}

# Clean old traces
php artisan vizra:trace:cleanup

# Manage prompt versions
php artisan vizra:prompts

# MCP server management
php artisan vizra:mcp:list

# Vector memory operations
php artisan vizra:vector:store --file={file}
php artisan vizra:vector:search {query}
php artisan vizra:vector:stats
```

## Code Conventions

### Naming Conventions
- Agents: `{Purpose}Agent` extends `BaseLlmAgent`
- Tools: `{Action}Tool` implements `ToolInterface`
- Evaluations: `{Scenario}Evaluation` extends `BaseEvaluation`
- Assertions: `{Check}Assertion` extends `BaseAssertion`

### Namespace Structure
```php
Vizra\VizraADK\Agents\      # Agent classes
Vizra\VizraADK\Tools\       # Tool implementations
Vizra\VizraADK\Services\    # Core services
Vizra\VizraADK\Models\      # Eloquent models
Vizra\VizraADK\Evaluations\ # Evaluation framework
```

### Agent Definition Pattern
```php
class MyAgent extends BaseLlmAgent
{
    protected string $name = 'my_agent';
    protected string $description = 'Agent purpose';
    protected string $instructions = 'Detailed instructions';
    protected string $model = 'gpt-4o';
    protected array $tools = [
        MyTool::class,
    ];
}
```

### Tool Implementation Pattern
```php
class MyTool implements ToolInterface
{
    public function definition(): array
    {
        return [
            'name' => 'tool_name',
            'description' => 'What this tool does',
            'parameters' => [
                'type' => 'object',
                'properties' => [...],
                'required' => [...]
            ]
        ];
    }
    
    public function execute(array $arguments, AgentContext $context): string
    {
        // Tool logic here
        return json_encode($result);
    }
}
```

## Key Features

### LLM Integration
- Uses Prism PHP for multi-provider support
- Providers: OpenAI, Anthropic, Google Gemini, Ollama
- Streaming response support
- Token usage tracking

### Memory & Context
- Session-based conversation history
- User-specific memory persistence
- Vector memory for semantic search
- Document chunking and embedding

### Tracing & Debugging
- Detailed execution traces with TraceSpan model
- Parent-child span relationships
- Performance metrics and timing
- Web dashboard for visualization

### Evaluation Framework
- Automated agent testing at scale
- LLM-as-a-Judge evaluation
- Custom assertions
- Test case management

### MCP Integration
- Model Context Protocol support
- External tool server connectivity
- Dynamic tool discovery
- Process management for MCP servers

## Database Schema

### Tables
- `agent_sessions`: Conversation sessions
- `agent_messages`: Message history
- `agent_memories`: Persistent memory
- `agent_prompt_versions`: Prompt versioning
- `agent_prompt_usage`: Prompt usage tracking
- `agent_trace_spans`: Execution traces
- `agent_vector_memories`: Vector embeddings

## Configuration

### Environment Variables
```env
# LLM Providers
OPENAI_API_KEY=
ANTHROPIC_API_KEY=
GEMINI_API_KEY=

# Vector Memory
MEILISEARCH_HOST=
MEILISEARCH_KEY=

# Embedding Provider
VIZRA_EMBEDDING_PROVIDER=openai
VIZRA_EMBEDDING_MODEL=text-embedding-3-small
```

### Config File
`config/vizra-adk.php` contains:
- Provider settings
- Model configurations
- Memory settings
- Tracing options
- Dashboard configuration

## Common Workflows

### Creating an Agent
1. Generate agent class: `php artisan vizra:make:agent`
2. Define properties (name, description, instructions, model)
3. Add tools to the `$tools` array
4. Agent is auto-discovered and ready to use

### Building a Tool
1. Generate tool class: `php artisan vizra:make:tool`
2. Define the tool schema in `definition()`
3. Implement logic in `execute()`
4. Add tool to agent's `$tools` array

### Running Evaluations
1. Create evaluation: `php artisan vizra:make:eval`
2. Define test cases and expected outcomes
3. Add assertions for validation
4. Run: `php artisan vizra:eval:run`

### Using Vector Memory
1. Store documents: `php artisan vizra:vector:store --file=file.txt`
2. Search: `php artisan vizra:vector:search "query"`
3. Use VectorMemoryTool in agents for RAG

## API Endpoints

### Web Routes
- `/vizra/dashboard` - Main dashboard
- `/vizra/chat/{agent}` - Chat interface
- `/vizra/eval` - Evaluation runner

### API Routes
- `POST /api/agent/{name}/chat` - Chat with agent
- `POST /api/agent/{name}/execute` - Execute agent
- `GET /api/agent/{name}/sessions` - Get sessions
- `POST /v1/chat/completions` - OpenAI-compatible endpoint

## Important Patterns

### Agent Execution Flow
1. AgentBuilder constructs context
2. AgentExecutor manages execution
3. Tracer records spans
4. Tools are executed as needed
5. Memory is persisted
6. Response returned (streaming or complete)

### Context Management
- AgentContext carries state through execution
- Includes user, session, parameters, memory
- Passed to all tools and sub-agents

### Event System
- Events fired at key points (execution start/end, tool calls, LLM calls)
- Used for logging, monitoring, and extensions
- Key events: AgentExecutionStarting, ToolCallInitiating, LlmResponseReceived

## Debugging Tips

### View Traces
```bash
# Get trace ID from execution
php artisan vizra:trace {traceId}

# Or use web dashboard
php artisan vizra:dashboard
```

### Check Agent Discovery
```bash
php artisan vizra:agents
```

### Test Individual Tools
Tools can be tested in isolation by instantiating and calling execute() directly with mock context.

### Memory Debugging
- Check `agent_memories` table for persistence issues
- Verify session continuity in `agent_sessions`
- Review vector embeddings in `agent_vector_memories`

## Performance Considerations

- Use streaming for long responses
- Implement caching for expensive operations
- Batch vector operations when possible
- Clean old traces regularly: `php artisan vizra:trace:cleanup`
- Monitor token usage in dashboard

## Security Notes

- API keys stored in .env file
- Session-based isolation for multi-user systems
- Tool execution sandboxing recommended
- Input validation in tool parameters
- Rate limiting for API endpoints

## Quick Reference

### Essential Classes
- `BaseLlmAgent` - Main agent base class
- `ToolInterface` - Tool contract
- `AgentContext` - Execution context
- `AgentMemory` - Memory management
- `Tracer` - Execution tracing
- `AgentRegistry` - Agent discovery

### Key Services
- `AgentManager` - Central management
- `MemoryManager` - Memory operations
- `VectorMemoryManager` - Vector operations
- `MCPClientManager` - MCP integration
- `WorkflowManager` - Workflow orchestration

### Facades
- `Agent::discover()` - Find agents
- `Agent::get($name)` - Get specific agent
- `Workflow::sequential()` - Create workflow