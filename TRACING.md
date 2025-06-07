# Agent Tracing System

The Laravel Agent ADK includes a comprehensive tracing system that tracks agent execution, LLM calls, tool executions, and sub-agent delegations. This provides valuable insights for debugging, performance analysis, and understanding agent behavior.

## Features

- **Hierarchical Span Tracking**: Traces are organized as trees of spans, showing parent-child relationships between operations
- **Multiple Trace Types**: Supports tracing of agent execution, LLM calls, tool calls, and sub-agent delegations
- **Session Management**: Groups related traces by session ID for conversation tracking
- **Performance Metrics**: Tracks duration, start/end times, and execution status
- **Error Tracking**: Captures exceptions and error states with detailed messages
- **CLI Visualization**: Multiple output formats including tree view, table, and JSON
- **Automatic Cleanup**: Configurable cleanup of old trace data

## Configuration

Tracing is configured in `config/agent-adk.php`:

```php
'tracing' => [
    'enabled' => env('AGENT_TRACING_ENABLED', true),
    'table' => 'agent_trace_spans',
    'cleanup_days' => 30,
],
```

### Configuration Options

- `enabled`: Enable/disable tracing system (default: true)
- `table`: Database table name for storing trace spans (default: 'agent_trace_spans')
- `cleanup_days`: Number of days to keep trace data (default: 30)

## Database Schema

The tracing system uses a single table with the following structure:

```sql
CREATE TABLE agent_trace_spans (
    id VARCHAR(26) PRIMARY KEY,           -- ULID
    trace_id VARCHAR(26) NOT NULL,        -- Groups related spans
    parent_span_id VARCHAR(26) NULL,      -- Hierarchical relationships
    session_id VARCHAR(255) NULL,         -- Session grouping
    agent_name VARCHAR(255) NOT NULL,     -- Agent identifier
    type VARCHAR(50) NOT NULL,            -- Span type (agent, llm_call, tool_call, etc.)
    name VARCHAR(255) NOT NULL,           -- Operation name
    input JSON NULL,                      -- Input data
    output JSON NULL,                     -- Output data
    metadata JSON NULL,                   -- Additional context
    status VARCHAR(20) DEFAULT 'running', -- running, completed, error
    error_message TEXT NULL,              -- Error details
    start_time TIMESTAMP NOT NULL,        -- Operation start
    end_time TIMESTAMP NULL,              -- Operation end
    duration_ms INTEGER NULL,             -- Duration in milliseconds
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL
);
```

## Span Types

The system tracks different types of operations:

### Agent Execution (`agent`)

- Top-level spans for entire agent runs
- Contains overall input/output and execution status
- Groups all sub-operations under this span

### LLM Calls (`llm_call`)

- Individual calls to language models
- Tracks prompts, responses, and model parameters
- Includes token usage and response metadata

### Tool Calls (`tool_call`)

- Individual tool executions
- Contains tool arguments and return values
- Tracks execution time and success/failure

### Sub-Agent Delegations (`sub_agent_delegation`)

- When one agent delegates to another
- Shows the delegation flow and results
- Maintains parent-child relationships

## CLI Commands

### Viewing Traces

```bash
# View traces for a specific session
php artisan agent:trace --session=session-123

# View a specific trace
php artisan agent:trace --trace=01JBXXX...

# Different output formats
php artisan agent:trace --session=session-123 --format=tree
php artisan agent:trace --session=session-123 --format=table
php artisan agent:trace --session=session-123 --format=json

# Filter by status
php artisan agent:trace --session=session-123 --status=error
php artisan agent:trace --session=session-123 --status=completed

# Limit results
php artisan agent:trace --session=session-123 --limit=10
```

### Cleaning Up Old Traces

```bash
# Clean up traces older than 30 days (default)
php artisan agent:trace:cleanup

# Specify custom retention period
php artisan agent:trace:cleanup --days=7

# Dry run to see what would be deleted
php artisan agent:trace:cleanup --dry-run

# Skip confirmation prompt
php artisan agent:trace:cleanup --force
```

## Output Formats

### Tree Format (Default)

Shows hierarchical relationships with visual tree structure:

```
📊 Trace: 01JBXXX... (customer_support) [completed] 1.2s
├── 🤖 agent: customer_support [completed] 1.2s
│   ├── 🧠 llm_call: chat_completion [completed] 800ms
│   ├── 🔧 tool_call: search_knowledge_base [completed] 300ms
│   └── 🧠 llm_call: chat_completion [completed] 100ms
```

### Table Format

Structured table view with all span details:

```
| Type      | Name              | Status    | Duration | Agent           |
|-----------|------------------|-----------|----------|-----------------|
| agent     | customer_support | completed | 1.2s     | customer_support|
| llm_call  | chat_completion  | completed | 800ms    | customer_support|
| tool_call | search_kb        | completed | 300ms    | customer_support|
```

### JSON Format

Complete structured data export:

```json
{
  "trace_id": "01JBXXX...",
  "session_id": "session-123",
  "spans": [
    {
      "id": "01JBYYY...",
      "type": "agent",
      "name": "customer_support",
      "status": "completed",
      "duration_ms": 1200,
      "children": [...]
    }
  ]
}
```

## Status Icons

The CLI uses visual indicators for different span types and statuses:

### Type Icons

- 📊 Trace root
- 🤖 Agent execution
- 🧠 LLM call
- 🔧 Tool call
- 🔄 Sub-agent delegation

### Status Icons

- ✅ Completed successfully
- ❌ Failed with error
- ⏳ Still running

## Programmatic Access

### Using the Tracer Service

```php
use AaronLumsden\LaravelAiADK\Services\Tracer;

// Get tracer instance
$tracer = app(Tracer::class);

// Get spans for a session
$spans = $tracer->getSpansForSession('session-123');

// Get spans for a specific trace
$spans = $tracer->getSpansForTrace('01JBXXX...');

// Clean up old traces
$deleted = $tracer->cleanupOldTraces(30);
```

### Using the TraceSpan Model

```php
use AaronLumsden\LaravelAiADK\Models\TraceSpan;

// Query spans directly
$spans = TraceSpan::forSession('session-123')->get();

// Get error spans
$errors = TraceSpan::withStatus('error')->get();

// Get root spans (no parent)
$roots = TraceSpan::roots()->get();

// Get children of a span
$children = $span->children;

// Get full hierarchy as tree
$tree = TraceSpan::buildTree($spans);
```

## Integration with Agents

The tracing system is automatically integrated into `BaseLlmAgent`. All agent executions, LLM calls, tool calls, and sub-agent delegations are traced without requiring any changes to your agent code.

### Lifecycle Hooks

Tracing occurs at these lifecycle points:

1. **Agent Start**: Creates root span when agent begins execution
2. **Before LLM Call**: Creates span for each LLM interaction
3. **After LLM Response**: Completes LLM span with response data
4. **Before Tool Call**: Creates span for each tool execution
5. **After Tool Result**: Completes tool span with results
6. **Before Sub-Agent**: Creates span for sub-agent delegation
7. **After Sub-Agent**: Completes delegation span
8. **Agent End**: Completes root span with final results

### Custom Metadata

You can add custom metadata to traces through the agent context:

```php
// In an agent method
$context->addTraceMetadata([
    'user_intent' => 'product_inquiry',
    'complexity_score' => 0.8,
    'custom_data' => $additionalInfo
]);
```

## Performance Considerations

- Tracing adds minimal overhead (typically <5ms per operation)
- Database writes are batched for efficiency
- Old traces are automatically cleaned up based on configuration
- Tracing can be disabled entirely in production if needed
- Failed database operations gracefully degrade without affecting agent execution

## Troubleshooting

### No Traces Appearing

1. Check if tracing is enabled: `config('agent-adk.tracing.enabled')`
2. Verify database table exists: `php artisan migrate`
3. Check database permissions
4. Look for errors in application logs

### Performance Issues

1. Reduce retention period: Lower `cleanup_days` in config
2. Run cleanup more frequently: `php artisan agent:trace:cleanup`
3. Consider disabling tracing in high-volume production environments
4. Index the database table for better query performance

### Missing Session Data

1. Ensure session IDs are properly set in agent context
2. Check that the same session ID is used across related operations
3. Verify session storage is working correctly

## Best Practices

1. **Use descriptive names** for custom spans and metadata
2. **Regular cleanup** to prevent database bloat
3. **Monitor performance** impact in high-volume environments
4. **Use filters** when viewing traces to focus on specific issues
5. **Export JSON** for detailed analysis or integration with other tools
6. **Set appropriate retention** periods based on your debugging needs

## Security Notes

- Trace data may contain sensitive information from LLM calls and tool executions
- Consider data retention policies and compliance requirements
- Sanitize sensitive data before storing in trace metadata
- Restrict access to trace viewing commands in production environments
