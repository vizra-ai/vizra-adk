# Sub-Agent Delegation

Vizra ADK allows agents to delegate tasks to other specialized agents, creating powerful hierarchical systems.

## Basic Sub-Agent Delegation

### Using DelegateToSubAgentTool
```php
use Vizra\VizraADK\Tools\DelegateToSubAgentTool;

class ManagerAgent extends BaseLlmAgent
{
    protected string $name = 'manager';
    
    protected string $instructions = <<<'INSTRUCTIONS'
        You are a task coordinator. Delegate specialized tasks:
        - Use 'researcher' for information gathering
        - Use 'writer' for content creation
        - Use 'analyzer' for data analysis
        - Use 'emailer' for sending emails
        
        Coordinate their work to complete complex tasks.
        INSTRUCTIONS;
    
    protected array $tools = [
        DelegateToSubAgentTool::class,
    ];
}
```

## Delegation Patterns

### Task Distribution
```php
class ProjectManagerAgent extends BaseLlmAgent
{
    protected string $instructions = <<<'INSTRUCTIONS'
        You manage software development projects.
        
        Delegate tasks as follows:
        - 'requirements_analyst' for gathering requirements
        - 'system_designer' for architecture design
        - 'developer' for implementation details
        - 'tester' for test planning
        - 'documenter' for documentation
        
        Coordinate their outputs to deliver complete solutions.
        INSTRUCTIONS;
    
    protected array $tools = [
        DelegateToSubAgentTool::class,
    ];
}
```

### Hierarchical Delegation
```php
class CEOAgent extends BaseLlmAgent
{
    protected string $name = 'ceo';
    protected string $instructions = <<<'INSTRUCTIONS'
        You are the CEO agent. Delegate high-level tasks to:
        - 'cto' for technology decisions
        - 'cfo' for financial analysis
        - 'cmo' for marketing strategies
        INSTRUCTIONS;
    protected array $tools = [DelegateToSubAgentTool::class];
}

class CTOAgent extends BaseLlmAgent
{
    protected string $name = 'cto';
    protected string $instructions = <<<'INSTRUCTIONS'
        You are the CTO agent. Handle technology decisions.
        Delegate specific tasks to:
        - 'lead_developer' for implementation
        - 'security_expert' for security review
        - 'infrastructure' for deployment
        INSTRUCTIONS;
    protected array $tools = [DelegateToSubAgentTool::class];
}
```

## Context Passing

Sub-agents inherit context from parent agents:

```php
// Parent agent execution
$response = ManagerAgent::run('Create a marketing campaign')
    ->forUser($user)
    ->withSession('campaign-123')
    ->withParameters(['budget' => 50000])
    ->go();

// Sub-agents automatically receive:
// - User context ($user)
// - Session ID ('campaign-123')
// - Parameters (['budget' => 50000])
// - Conversation history
```

## Advanced Delegation Strategies

### Specialist Selection
```php
class SmartRouterAgent extends BaseLlmAgent
{
    protected string $instructions = <<<'INSTRUCTIONS'
        Analyze incoming requests and route to the most appropriate specialist.
        
        Available specialists:
        - 'legal_expert': Legal questions, contracts, compliance
        - 'financial_advisor': Budgets, investments, financial planning
        - 'technical_support': Technical issues, troubleshooting
        - 'creative_designer': Design, branding, creative content
        
        Choose based on the nature of the request.
        INSTRUCTIONS;
}
```

### Parallel Sub-Agent Execution
```php
class ResearchCoordinatorAgent extends BaseLlmAgent
{
    protected string $instructions = <<<'INSTRUCTIONS'
        For comprehensive research, delegate to multiple agents:
        
        1. Send the topic to 'web_researcher'
        2. Send the topic to 'academic_researcher'
        3. Send the topic to 'news_researcher'
        
        Compile and synthesize all findings.
        INSTRUCTIONS;
}
```

### Quality Control Chain
```php
class ContentProductionAgent extends BaseLlmAgent
{
    protected string $instructions = <<<'INSTRUCTIONS'
        Produce high-quality content through delegation:
        
        1. 'writer' - Create initial draft
        2. 'editor' - Review and improve
        3. 'fact_checker' - Verify facts
        4. 'proofreader' - Final polish
        
        Pass the output through each stage sequentially.
        INSTRUCTIONS;
}
```

## Configuration and Limits

### Maximum Delegation Depth
```php
// In config/vizra-adk.php
'max_delegation_depth' => 5, // Prevent infinite delegation chains
```

### Agent Access Control
```php
class RestrictedManagerAgent extends BaseLlmAgent
{
    protected array $allowedSubAgents = [
        'researcher',
        'writer',
        'analyzer',
    ];
    
    protected string $instructions = <<<'INSTRUCTIONS'
        You can only delegate to: researcher, writer, analyzer.
        Do not attempt to delegate to other agents.
        INSTRUCTIONS;
}
```

## Error Handling

### Handling Sub-Agent Failures
```php
class ResilientManagerAgent extends BaseLlmAgent
{
    protected string $instructions = <<<'INSTRUCTIONS'
        If a sub-agent fails or returns an error:
        1. Try an alternative agent if available
        2. Attempt the task yourself if possible
        3. Report the specific failure clearly
        
        Never fail silently - always provide feedback.
        INSTRUCTIONS;
}
```

## Memory Sharing

Sub-agents can access and contribute to shared memory:

```php
class MemorySharingManagerAgent extends BaseLlmAgent
{
    protected string $instructions = <<<'INSTRUCTIONS'
        Share context with sub-agents:
        - Pass relevant background information
        - Sub-agents can access conversation history
        - Their findings are added to shared memory
        
        Build collective knowledge through delegation.
        INSTRUCTIONS;
    
    protected array $tools = [
        DelegateToSubAgentTool::class,
        MemoryTool::class,
    ];
}
```

## Testing Sub-Agent Systems

```php
class SubAgentTest extends TestCase
{
    public function test_manager_delegates_correctly()
    {
        $response = ManagerAgent::run('Research and write about AI')
            ->forUser($user)
            ->go();
        
        // Check that sub-agents were called
        $traces = AgentTracer::getTraces();
        $this->assertStringContainsString('researcher', $traces);
        $this->assertStringContainsString('writer', $traces);
    }
}
```

## Best Practices

1. **Clear Role Definition**: Each agent should have a specific, well-defined purpose
2. **Avoid Circular Delegation**: Prevent agents from delegating back to parents
3. **Depth Limits**: Set maximum delegation depth to prevent infinite chains
4. **Error Propagation**: Handle sub-agent failures gracefully
5. **Context Preservation**: Ensure context flows properly through delegation
6. **Performance**: Consider parallel delegation for independent tasks
7. **Documentation**: Document the delegation hierarchy clearly

## Common Patterns

### Expert System
```php
// Multiple specialist agents coordinated by a manager
ManagerAgent -> [
    DiagnosticAgent,
    TreatmentAgent,
    FollowUpAgent
]
```

### Pipeline Processing
```php
// Sequential delegation through stages
InputAgent -> ProcessorAgent -> ValidatorAgent -> OutputAgent
```

### Load Balancing
```php
// Distribute work among similar agents
LoadBalancerAgent -> [
    Worker1Agent,
    Worker2Agent,
    Worker3Agent
]
```

## Next Steps

- Test delegation chains: See `evaluation.blade.php`
- Optimize delegation: See `best-practices.blade.php`