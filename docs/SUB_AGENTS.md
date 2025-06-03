# Sub-Agent Support in Laravel Agent ADK

This document describes the sub-agent functionality that allows parent agents to delegate tasks to specialized child agents.

## Overview

Sub-agents enable hierarchical agent architectures where a parent agent can delegate specific tasks or sub-goals to specialized child agents. This promotes:

- **Separation of concerns**: Each agent can focus on its area of expertise
- **Modularity**: Sub-agents can be reused across different parent agents
- **Scalability**: Complex problems can be broken down into manageable parts
- **Nested delegation**: Sub-agents can have their own sub-agents for deep specialization

## How It Works

### 1. Sub-Agent Registration

Parent agents register sub-agents by implementing the `registerSubAgents()` method:

```php
class CustomerServiceAgent extends BaseLlmAgent
{
    protected function registerSubAgents(): array
    {
        return [
            'technical_support' => TechnicalSupportAgent::class,
            'billing_support' => BillingSupportAgent::class,
            'order_specialist' => OrderSpecialistAgent::class,
        ];
    }
}
```

### 2. Automatic Delegation Tool

When sub-agents are registered, the parent agent automatically gains access to a `delegate_to_sub_agent` tool that:

- Lists available sub-agents in the tool description
- Validates delegation parameters
- Creates isolated contexts for sub-agent execution
- Returns structured results from sub-agent operations

### 3. Enhanced Instructions

Parent agents with sub-agents automatically receive enhanced instructions that inform the LLM about:

- Available sub-agents and their names
- How to use the delegation tool
- When delegation might be appropriate

### 4. Context Isolation

Each sub-agent execution creates a separate context with:

- Unique session ID (parent session + sub-agent identifier)
- Optional context summary from the parent agent
- Independent conversation history
- Isolated state management

## Implementation Details

### Core Components

1. **BaseLlmAgent** - Enhanced with sub-agent support:

   - `registerSubAgents()`: Abstract method for sub-agent registration
   - `loadSubAgents()`: Loads and instantiates sub-agents
   - `getSubAgent(string $name)`: Retrieves specific sub-agent instances
   - `getLoadedSubAgents()`: Returns all loaded sub-agents

2. **DelegateToSubAgentTool** - Handles delegation:
   - Automatically added when sub-agents are present
   - Validates parameters and sub-agent availability
   - Creates isolated execution contexts
   - Returns structured JSON responses

### API Methods

```php
// Get a specific sub-agent
$subAgent = $parentAgent->getSubAgent('technical_support');

// Get all loaded sub-agents
$allSubAgents = $parentAgent->getLoadedSubAgents();

// Check if an agent has sub-agents
$hasSubAgents = !empty($parentAgent->getLoadedSubAgents());
```

### Delegation Tool Parameters

The delegation tool accepts these parameters:

- `sub_agent_name` (required): The registered name of the sub-agent
- `task_input` (required): The task/question to pass to the sub-agent
- `context_summary` (optional): Background context from the parent agent

### Response Format

Delegation tool returns JSON with:

```json
{
  "sub_agent": "technical_support",
  "task_input": "User can't connect to WiFi",
  "result": "I've identified the issue...",
  "success": true
}
```

Or in case of errors:

```json
{
  "error": "Sub-agent 'unknown_agent' not found",
  "available_sub_agents": ["technical_support", "billing_support"],
  "success": false
}
```

## Usage Examples

### Basic Sub-Agent Usage

```php
class CustomerServiceAgent extends BaseLlmAgent
{
    protected string $instructions = 'You are a customer service agent. Use sub-agents for specialized help.';

    protected function registerSubAgents(): array
    {
        return [
            'technical' => TechnicalSupportAgent::class,
            'billing' => BillingSupportAgent::class,
        ];
    }
}
```

### Nested Sub-Agents

```php
class TechnicalSupportAgent extends BaseLlmAgent
{
    protected function registerSubAgents(): array
    {
        return [
            'network_specialist' => NetworkSpecialistAgent::class,
            'software_specialist' => SoftwareTroubleshootingAgent::class,
        ];
    }
}
```

### Using the Customer Service Example

```php
use AaronLumsden\LaravelAgentADK\Facades\Agent;

// Register the customer service agent
Agent::build(CustomerServiceAgent::class)->register();

// Run the agent with a complex query
$response = Agent::run('customer-service',
    'I have a billing question about my last invoice and also need help with my internet connection',
    'session-123'
);

// The agent will automatically decide whether to handle directly or delegate to sub-agents
```

## Testing Sub-Agents

The package includes comprehensive tests for sub-agent functionality:

```php
// Test sub-agent registration and loading
it('can register and load sub-agents', function () {
    $subAgents = $parentAgent->getLoadedSubAgents();
    expect($subAgents)->toHaveCount(2);
});

// Test delegation tool execution
it('delegation tool executes successfully', function () {
    $delegationTool = new DelegateToSubAgentTool($parentAgent);
    $result = $delegationTool->execute([
        'sub_agent_name' => 'specialist',
        'task_input' => 'Complex problem'
    ], $context);

    $decoded = json_decode($result, true);
    expect($decoded['success'])->toBeTrue();
});
```

## Best Practices

### 1. Agent Design

- Keep parent agents focused on coordination and routing
- Design sub-agents with clear, specialized responsibilities
- Use descriptive names for sub-agents that indicate their purpose

### 2. Context Management

- Use context summaries to provide relevant background to sub-agents
- Keep sub-agent contexts focused and minimal
- Avoid passing sensitive information in context summaries

### 3. Error Handling

- Sub-agents should handle their own errors gracefully
- Parent agents should validate delegation responses
- Provide fallback behavior when sub-agents are unavailable

### 4. Performance

- Consider caching sub-agent instances for repeated use
- Limit delegation depth to avoid excessive nesting
- Monitor sub-agent execution times and costs

### 5. Testing

- Test both successful and failed delegation scenarios
- Verify context isolation between parent and sub-agents
- Test nested sub-agent scenarios where applicable

## Limitations and Considerations

1. **Recursion Depth**: While nested sub-agents are supported, excessive nesting can impact performance
2. **Context Size**: Each delegation creates a new context, which may increase memory usage
3. **Cost Management**: Each sub-agent execution may incur LLM API costs
4. **Debugging**: Nested agent calls can make debugging more complex

## Migration from Non-Sub-Agent Systems

Existing agents can be gradually enhanced with sub-agent support:

1. Identify specialized functionality that could be extracted
2. Create focused sub-agents for specific domains
3. Implement `registerSubAgents()` in the parent agent
4. Update parent agent instructions to leverage delegation
5. Test delegation scenarios thoroughly

The sub-agent system is designed to be backward compatible - agents without sub-agents continue to work exactly as before.
