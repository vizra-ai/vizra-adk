# Planning Agent

The PlanningAgent implements a **Plan-Execute-Reflect** workflow for handling complex, multi-step tasks with self-evaluation and iterative improvement.

## Overview

The PlanningAgent follows a loop:

1. **Plan** - Generate a structured plan with steps and dependencies
2. **Execute** - Run each step respecting dependency order
3. **Reflect** - Evaluate the results against success criteria
4. **Replan** - If unsatisfactory, create an improved plan and retry

This pattern is ideal for:
- Complex tasks requiring multiple steps
- Tasks that benefit from self-evaluation
- Scenarios where quality matters more than speed
- Problems that may need iterative refinement

## Quick Start

### Simple Usage

```php
use Vizra\VizraADK\Agents\PlanningAgent;

// Fluent API
$response = PlanningAgent::plan('Build a REST API for user management')
    ->go();

echo $response->result();
echo $response->score();  // 0.0 - 1.0
```

### With Configuration

```php
$response = PlanningAgent::plan('Research quantum computing advances')
    ->maxAttempts(5)           // Max replan attempts
    ->threshold(0.9)           // Satisfaction threshold
    ->forUser($user)           // User context
    ->go();

// Access detailed results
echo $response->result();
echo $response->plan()->goal;
echo $response->reflection()->score;
echo $response->attempts();
```

### Async/Queued Execution

```php
// Queue for background processing
PlanningAgent::plan('Generate comprehensive market analysis')
    ->onQueue('planning')
    ->then(fn($response) => notify($response->result()))
    ->go();

// Dispatch info returned immediately
// ['job_dispatched' => true, 'job_id' => '...', 'queue' => 'planning']
```

### Presets

```php
// High accuracy (5 attempts, 0.9 threshold)
PlanningAgent::plan('Critical task')->highAccuracy()->go();

// Fast execution (1 attempt, 0.6 threshold)
PlanningAgent::plan('Quick task')->fast()->go();

// Balanced (3 attempts, 0.8 threshold) - default
PlanningAgent::plan('Normal task')->balanced()->go();
```

## Architecture

```
src/
├── Agents/
│   ├── BasePlanningAgent.php    # Abstract base class
│   └── PlanningAgent.php        # Ready-to-use concrete agent
├── Execution/
│   └── PlanningAgentExecutor.php # Fluent builder
├── Jobs/
│   └── PlanningAgentJob.php     # Queue job for async
├── Planning/
│   ├── Plan.php                 # Plan data class
│   ├── PlanStep.php             # Step data class
│   ├── PlanningResponse.php     # Response wrapper
│   └── Reflection.php           # Reflection data class
└── Exceptions/
    └── PlanExecutionException.php
```

## Core Classes

### PlanningResponse

The response from a planning execution:

```php
$response = PlanningAgent::plan('Task')->go();

// Basic accessors
$response->result();        // Final text result
$response->isSuccess();     // Met satisfaction threshold?
$response->isFailed();      // Didn't meet threshold after max attempts
$response->attempts();      // Number of attempts made

// Plan details
$response->plan();          // Plan object
$response->goal();          // Plan's goal string
$response->steps();         // Array of PlanStep objects
$response->stepResults();   // Results keyed by step ID

// Reflection details
$response->reflection();    // Reflection object
$response->score();         // 0.0 - 1.0 satisfaction score
$response->strengths();     // Array of strengths
$response->weaknesses();    // Array of weaknesses
$response->suggestions();   // Array of suggestions

// Serialization
$response->toArray();
$response->toJson();
(string) $response;         // Returns result()
```

### Plan

Represents an execution plan:

```php
use Vizra\VizraADK\Planning\Plan;
use Vizra\VizraADK\Planning\PlanStep;

$plan = new Plan(
    goal: 'Create a user management system',
    steps: [
        new PlanStep(id: 1, action: 'Design database schema', dependencies: [], tools: ['database']),
        new PlanStep(id: 2, action: 'Create migrations', dependencies: [1], tools: []),
        new PlanStep(id: 3, action: 'Build API endpoints', dependencies: [2], tools: ['api']),
        new PlanStep(id: 4, action: 'Write tests', dependencies: [3], tools: ['testing']),
    ],
    successCriteria: [
        'All CRUD operations work',
        'Tests pass',
        'Documentation complete',
    ]
);

// Methods
$plan->getStepById(2);          // Get specific step
$plan->isCompleted();           // All steps done?
$plan->getCompletedStepIds();   // [1, 2, ...]
$plan->getExecutableSteps();    // Steps ready to run
$plan->toJson();
```

### PlanStep

Represents a single step:

```php
use Vizra\VizraADK\Planning\PlanStep;

$step = new PlanStep(
    id: 2,
    action: 'Implement authentication',
    dependencies: [1],      // Must wait for step 1
    tools: ['auth_tool']    // Tools this step might use
);

// State management
$step->isCompleted();
$step->setCompleted(true);
$step->setResult('Auth implemented successfully');
$step->getResult();

// Dependency checking
$step->hasDependencies();
$step->areDependenciesSatisfied([1, 3, 4]);  // true if all deps in array

// Serialization
$step->toArray();
PlanStep::fromArray([...]);
```

### Reflection

Captures execution evaluation:

```php
use Vizra\VizraADK\Planning\Reflection;

$reflection = new Reflection(
    satisfactory: false,
    score: 0.65,
    strengths: ['Clear implementation', 'Good structure'],
    weaknesses: ['Missing error handling', 'No tests'],
    suggestions: ['Add try-catch blocks', 'Write unit tests']
);

$reflection->requiresImprovement();  // true (based on satisfactory flag)
$reflection->getSummary();           // Formatted weaknesses + suggestions
$reflection->toJson();
Reflection::fromJson($json);
```

## Creating Custom Planning Agents

Extend `BasePlanningAgent` for custom behavior:

```php
<?php

namespace App\Agents;

use Vizra\VizraADK\Agents\BasePlanningAgent;
use Vizra\VizraADK\Planning\Plan;
use Vizra\VizraADK\Planning\PlanStep;
use Vizra\VizraADK\System\AgentContext;

class CodeReviewAgent extends BasePlanningAgent
{
    protected string $name = 'code-review-agent';
    protected string $description = 'Reviews code for quality and security';
    protected string $instructions = 'You are an expert code reviewer.';
    protected string $model = 'gpt-4o';

    // Optional: customize thresholds
    protected int $maxReplanAttempts = 2;
    protected float $satisfactionThreshold = 0.85;

    /**
     * Execute a single step.
     */
    protected function executeStep(PlanStep $step, array $previousResults, AgentContext $context): string
    {
        $code = $context->getUserInput();

        // Build prompt with previous context
        $prompt = "Review this code for: {$step->action}\n\nCode:\n```\n{$code}\n```";

        if (!empty($previousResults)) {
            $prompt .= "\n\nPrevious findings:\n" . implode("\n", $previousResults);
        }

        // Use tools if specified
        if (in_array('security_scanner', $step->tools)) {
            $scanResult = $this->runSecurityScan($code);
            $prompt .= "\n\nSecurity scan results: {$scanResult}";
        }

        return $this->callLlmForJson($this->instructions, $prompt, $context);
    }

    /**
     * Combine step results into final output.
     */
    protected function synthesizeResults(Plan $plan, array $results, AgentContext $context): string
    {
        $summary = "# Code Review Report\n\n";
        $summary .= "## Goal\n{$plan->goal}\n\n";

        foreach ($plan->steps as $step) {
            $result = $results[$step->id] ?? 'Not completed';
            $summary .= "## {$step->action}\n{$result}\n\n";
        }

        return $summary;
    }
}
```

Use your custom agent:

```php
$response = CodeReviewAgent::plan($codeToReview)
    ->maxAttempts(3)
    ->threshold(0.9)
    ->go();
```

## Configuration

### Environment Variables

```env
# Optional: Configure defaults
VIZRA_PLANNING_MODEL=gpt-4o
VIZRA_PLANNING_MAX_ATTEMPTS=3
VIZRA_PLANNING_THRESHOLD=0.8
```

### Config File

In `config/vizra-adk.php`:

```php
'planning' => [
    'model' => env('VIZRA_PLANNING_MODEL', 'gpt-4o'),
    'max_attempts' => env('VIZRA_PLANNING_MAX_ATTEMPTS', 3),
    'threshold' => env('VIZRA_PLANNING_THRESHOLD', 0.8),
],
```

## PlanningAgentExecutor Methods

| Method | Description |
|--------|-------------|
| `plan(string $input)` | Static entry point |
| `maxAttempts(int $n)` | Set max replan attempts |
| `threshold(float $t)` | Set satisfaction threshold (0-1) |
| `highAccuracy()` | Preset: 5 attempts, 0.9 threshold |
| `fast()` | Preset: 1 attempt, 0.6 threshold |
| `balanced()` | Preset: 3 attempts, 0.8 threshold |
| `using(string $model)` | Override LLM model |
| `forUser(Model $user)` | Set user context |
| `withSession(string $id)` | Set session ID |
| `withContext(array $data)` | Add context data |
| `withPlannerInstructions(string $p)` | Custom planner prompt |
| `withReflectionInstructions(string $p)` | Custom reflection prompt |
| `async()` | Enable async execution |
| `onQueue(string $queue)` | Dispatch to queue |
| `delay(int $seconds)` | Delay queued execution |
| `tries(int $n)` | Job retry attempts |
| `timeout(int $seconds)` | Job timeout |
| `then(Closure $cb)` | Callback on completion |
| `go()` | Execute and return response |

## Tool Integration

The PlanningAgent can be used as a tool in other agents:

```php
$agent = new PlanningAgent();

// Get tool definition for LLM
$definition = $agent->toToolDefinition();

// Execute from tool call
$result = $agent->executeFromToolCall([
    'task' => 'Analyze the market trends',
    'max_attempts' => 3,
    'threshold' => 0.8,
], $context);
```

## Events

The planning agent dispatches events for monitoring:

```php
// Synchronous completion
Event::listen('planning.job.completed', function ($data) {
    // $data['job_id'], $data['response'], etc.
});

// Agent-specific completion
Event::listen('planning.planning_agent.completed', function ($data) {
    // Handle completion
});

// Job failure
Event::listen('planning.job.failed', function ($data) {
    // $data['error'], $data['job_id']
});
```

## Tracing

Planning execution is automatically traced:

```bash
# View trace details
php artisan vizra:trace {traceId}

# Or use the dashboard
php artisan vizra:dashboard
```

Trace includes:
- Plan generation
- Each step execution with dependencies
- Reflection results
- Replan attempts
- Final outcome

## Best Practices

1. **Set appropriate thresholds** - Higher means more iterations but better quality
2. **Keep steps atomic** - Each step should do one thing well
3. **Use meaningful dependencies** - Only add required dependencies
4. **Handle failures gracefully** - The agent will replan on errors
5. **Monitor with tracing** - Use traces to debug and optimize
6. **Queue long tasks** - Use `onQueue()` for complex planning tasks

## Example: Research Agent

```php
use Vizra\VizraADK\Agents\BasePlanningAgent;

class ResearchAgent extends BasePlanningAgent
{
    protected string $name = 'research-agent';
    protected string $description = 'Conducts thorough research on topics';
    protected string $model = 'gpt-4o';
    protected int $maxReplanAttempts = 3;
    protected float $satisfactionThreshold = 0.85;

    protected string $plannerInstructions = <<<'PROMPT'
    Create a research plan with these phases:
    1. Initial exploration
    2. Deep dive into key areas
    3. Cross-reference findings
    4. Synthesize conclusions

    Output as JSON with goal, steps, and success_criteria.
    PROMPT;

    protected function executeStep(PlanStep $step, array $previousResults, AgentContext $context): string
    {
        // Customize step execution for research
        return parent::callLlmForJson(
            $this->instructions,
            "Research step: {$step->action}",
            $context
        );
    }

    protected function synthesizeResults(Plan $plan, array $results, AgentContext $context): string
    {
        return "# Research Report: {$plan->goal}\n\n" . implode("\n\n", $results);
    }
}

// Usage
$report = ResearchAgent::plan('Impact of AI on software development')
    ->highAccuracy()
    ->go();
```

## See Also

- [Gap Analysis - Planning & Reasoning Agents](./GAP_ANALYSIS.md#4-planning--reasoning-agents)
- [BaseLlmAgent Documentation](./AGENTS.md)
- [Workflow Patterns](./WORKFLOWS.md)
