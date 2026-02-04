# Planning Agent Pattern

The PlanningAgent pattern implements a **Plan-Execute-Reflect** workflow for handling complex, multi-step tasks. This pattern is inspired by frameworks like LangGraph and provides structured reasoning capabilities for your agents.

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

### 1. Create Your Planning Agent

```php
<?php

namespace App\Agents;

use Vizra\VizraADK\Agents\Patterns\PlanningAgent;
use Vizra\VizraADK\Agents\Patterns\Data\Plan;
use Vizra\VizraADK\Agents\Patterns\Data\PlanStep;
use Vizra\VizraADK\System\AgentContext;

class ResearchAgent extends PlanningAgent
{
    protected string $name = 'research-agent';
    protected string $description = 'Researches topics and produces comprehensive reports';
    protected string $instructions = 'You are a thorough research assistant.';
    protected string $model = 'gpt-4o';

    // Optional: Configure behavior
    protected int $maxReplanAttempts = 3;
    protected float $satisfactionThreshold = 0.8;

    protected function executeStep(PlanStep $step, array $previousResults, AgentContext $context): string
    {
        // Build context from previous steps
        $previousContext = !empty($previousResults)
            ? "Previous findings:\n" . implode("\n", $previousResults)
            : '';

        $prompt = "Execute this step: {$step->action}\n\n{$previousContext}";

        // Use parent's LLM capabilities
        return $this->callLlmForJson($this->instructions, $prompt, $context);
    }

    protected function synthesizeResults(Plan $plan, array $results, AgentContext $context): string
    {
        $prompt = "Synthesize these research findings into a coherent report:\n\n";
        $prompt .= "Goal: {$plan->goal}\n\n";

        foreach ($results as $stepId => $result) {
            $step = $plan->getStepById($stepId);
            $prompt .= "## {$step->action}\n{$result}\n\n";
        }

        return $this->callLlmForJson($this->instructions, $prompt, $context);
    }
}
```

### 2. Use Your Agent

```php
use App\Agents\ResearchAgent;
use Vizra\VizraADK\System\AgentContext;

$agent = new ResearchAgent();
$context = new AgentContext('session-123');

$result = $agent->execute(
    'Research the impact of AI on software development practices',
    $context
);

echo $result;
```

## Core Concepts

### Plan

A `Plan` represents a structured approach to completing a task:

```php
use Vizra\VizraADK\Agents\Patterns\Data\Plan;
use Vizra\VizraADK\Agents\Patterns\Data\PlanStep;

$plan = new Plan(
    goal: 'Create a comprehensive market analysis',
    steps: [
        new PlanStep(id: 1, action: 'Identify key competitors', dependencies: [], tools: ['web_search']),
        new PlanStep(id: 2, action: 'Analyze market trends', dependencies: [], tools: ['database']),
        new PlanStep(id: 3, action: 'Compare product features', dependencies: [1, 2], tools: []),
        new PlanStep(id: 4, action: 'Generate final report', dependencies: [3], tools: []),
    ],
    successCriteria: [
        'All major competitors identified',
        'Market trends supported by data',
        'Actionable recommendations provided',
    ]
);
```

Plans can be serialized to/from JSON:

```php
// To JSON
$json = $plan->toJson();

// From JSON
$plan = Plan::fromJson($jsonString);
```

### PlanStep

Each step has:

| Property | Type | Description |
|----------|------|-------------|
| `id` | int | Unique identifier |
| `action` | string | What this step does |
| `dependencies` | array | IDs of steps that must complete first |
| `tools` | array | Tools this step might use |

```php
use Vizra\VizraADK\Agents\Patterns\Data\PlanStep;

$step = new PlanStep(
    id: 2,
    action: 'Analyze the data',
    dependencies: [1],  // Must wait for step 1
    tools: ['calculator', 'database']
);

// Check if dependencies are satisfied
$completedStepIds = [1, 3, 4];
if ($step->areDependenciesSatisfied($completedStepIds)) {
    // Safe to execute
}

// Mark as completed
$step->setCompleted(true);
$step->setResult('Analysis complete: found 3 anomalies');
```

### Reflection

A `Reflection` captures the evaluation of execution results:

```php
use Vizra\VizraADK\Agents\Patterns\Data\Reflection;

$reflection = new Reflection(
    satisfactory: false,
    score: 0.65,
    strengths: ['Comprehensive data coverage', 'Clear methodology'],
    weaknesses: ['Missing recent data', 'Conclusions too general'],
    suggestions: ['Include 2024 statistics', 'Add specific recommendations']
);

// Check if improvement is needed
if ($reflection->requiresImprovement()) {
    // Trigger replanning
}

// Get feedback summary
echo $reflection->getSummary();
// Output: Weaknesses: Missing recent data, Conclusions too general
//         Suggestions: Include 2024 statistics, Add specific recommendations
```

## Configuration

### Replan Attempts

Control how many times the agent will attempt to improve its results:

```php
class MyAgent extends PlanningAgent
{
    protected int $maxReplanAttempts = 5;  // Default is 3
}

// Or at runtime
$agent->setMaxReplanAttempts(5);
```

### Satisfaction Threshold

Set the score (0-1) required to consider results acceptable:

```php
class MyAgent extends PlanningAgent
{
    protected float $satisfactionThreshold = 0.9;  // Default is 0.8
}

// Or at runtime
$agent->setSatisfactionThreshold(0.9);
```

### Custom Instructions

Override the default prompts for planning and reflection:

```php
class MyAgent extends PlanningAgent
{
    protected string $plannerInstructions = <<<'PROMPT'
    You are a strategic planner. Create detailed plans with these rules:
    1. Each step must be independently verifiable
    2. Include rollback procedures for risky steps
    3. Estimate time for each step

    Output as JSON: {"goal": "...", "steps": [...], "success_criteria": [...]}
    PROMPT;

    protected string $reflectionInstructions = <<<'PROMPT'
    Evaluate the execution against business KPIs:
    1. Was the goal achieved?
    2. Were there any errors or issues?
    3. What would you do differently?

    Output as JSON: {"satisfactory": bool, "score": 0-1, "strengths": [], "weaknesses": [], "suggestions": []}
    PROMPT;
}

// Or at runtime
$agent->setPlannerInstructions('Custom planning prompt...');
$agent->setReflectionInstructions('Custom reflection prompt...');
```

## Advanced Usage

### Using Tools in Steps

Integrate your tools within step execution:

```php
protected function executeStep(PlanStep $step, array $previousResults, AgentContext $context): string
{
    // Check if step requires specific tools
    if (in_array('web_search', $step->tools)) {
        $searchTool = $this->loadedTools['web_search'] ?? null;
        if ($searchTool) {
            $searchResults = $searchTool->execute(['query' => $step->action], $context);
            return "Search results: {$searchResults}";
        }
    }

    // Default: use LLM to execute
    return $this->callLlmForJson($this->instructions, $step->action, $context);
}
```

### Accessing Step Results

Previous step results are available during execution:

```php
protected function executeStep(PlanStep $step, array $previousResults, AgentContext $context): string
{
    // Access specific previous step result
    if (in_array(1, $step->dependencies)) {
        $step1Result = $previousResults[1] ?? 'No result';
        // Use step 1's output...
    }

    // Or from context
    $step1Result = $context->getState('step_1_result');

    return "Executed with context from dependencies";
}
```

### Handling Failures

The agent automatically handles step failures by replanning:

```php
use Vizra\VizraADK\Exceptions\PlanExecutionException;

protected function executeStep(PlanStep $step, array $previousResults, AgentContext $context): string
{
    try {
        $result = $this->riskyOperation($step);
        return $result;
    } catch (\Exception $e) {
        // This will trigger replanning
        throw PlanExecutionException::forStep($step, $e->getMessage(), $e);
    }
}
```

### Custom Plan Generation

Override plan generation for specialized logic:

```php
protected function generatePlan(mixed $input, AgentContext $context): Plan
{
    // Use your own planning logic
    if (str_contains($input, 'quick')) {
        return new Plan(
            goal: $input,
            steps: [new PlanStep(id: 1, action: 'Quick response', dependencies: [], tools: [])],
            successCriteria: ['Fast response provided']
        );
    }

    // Or call parent for default LLM-based planning
    return parent::generatePlan($input, $context);
}
```

### Custom Reflection Logic

Override reflection for domain-specific evaluation:

```php
protected function reflect(mixed $input, string $result, Plan $plan, AgentContext $context): Reflection
{
    // Custom validation logic
    $hasAllSections = str_contains($result, '## Summary')
        && str_contains($result, '## Details');

    $wordCount = str_word_count($result);
    $lengthScore = min($wordCount / 500, 1.0);  // Target 500 words

    $score = ($hasAllSections ? 0.5 : 0) + ($lengthScore * 0.5);

    return new Reflection(
        satisfactory: $score >= $this->satisfactionThreshold,
        score: $score,
        strengths: $hasAllSections ? ['All sections present'] : [],
        weaknesses: !$hasAllSections ? ['Missing required sections'] : [],
        suggestions: $wordCount < 300 ? ['Add more detail'] : []
    );
}
```

## Tracing and Debugging

The PlanningAgent integrates with Vizra ADK's tracing system:

```php
// Traces are automatically created
$result = $agent->execute('My task', $context);

// View trace in dashboard
// php artisan vizra:dashboard

// Or programmatically
$traceId = $context->getState('trace_id');
```

Logged events include:
- Plan generation
- Step execution (with dependencies)
- Reflection results
- Replan attempts
- Final outcome

## Testing

Example test for a planning agent:

```php
use App\Agents\ResearchAgent;
use Vizra\VizraADK\System\AgentContext;

it('generates a valid plan', function () {
    $agent = new ResearchAgent();
    $context = new AgentContext('test-session');

    $plan = $agent->publicGeneratePlan('Research AI trends', $context);

    expect($plan->goal)->not->toBeEmpty();
    expect($plan->steps)->not->toBeEmpty();
    expect($plan->steps[0])->toBeInstanceOf(PlanStep::class);
});

it('respects step dependencies', function () {
    $agent = new ResearchAgent();
    $context = new AgentContext('test-session');

    // Create plan with dependencies
    $plan = new Plan(
        goal: 'Test',
        steps: [
            new PlanStep(id: 1, action: 'First', dependencies: [], tools: []),
            new PlanStep(id: 2, action: 'Second', dependencies: [1], tools: []),
        ],
        successCriteria: []
    );

    $result = $agent->publicExecutePlan($plan, $context);

    // Step 2 should have access to step 1's result
    expect($context->getState('step_1_result'))->not->toBeNull();
});
```

## Example: Code Review Agent

A complete example of a planning agent that reviews code:

```php
<?php

namespace App\Agents;

use Vizra\VizraADK\Agents\Patterns\PlanningAgent;
use Vizra\VizraADK\Agents\Patterns\Data\Plan;
use Vizra\VizraADK\Agents\Patterns\Data\PlanStep;
use Vizra\VizraADK\System\AgentContext;

class CodeReviewAgent extends PlanningAgent
{
    protected string $name = 'code-review-agent';
    protected string $description = 'Reviews code for quality, security, and best practices';
    protected string $instructions = 'You are an expert code reviewer.';
    protected string $model = 'gpt-4o';
    protected int $maxReplanAttempts = 2;
    protected float $satisfactionThreshold = 0.85;

    protected string $plannerInstructions = <<<'PROMPT'
    Create a code review plan. Consider:
    1. Code style and formatting
    2. Security vulnerabilities
    3. Performance issues
    4. Best practices
    5. Test coverage

    Output as JSON with goal, steps (each with id, action, dependencies, tools), and success_criteria.
    PROMPT;

    protected function executeStep(PlanStep $step, array $previousResults, AgentContext $context): string
    {
        $code = $context->getUserInput();

        $prompt = <<<PROMPT
        Review this code for: {$step->action}

        Code:
        ```
        {$code}
        ```

        Previous findings:
        {$this->formatPreviousResults($previousResults)}

        Provide specific, actionable feedback.
        PROMPT;

        return $this->callLlmForJson($this->instructions, $prompt, $context);
    }

    protected function synthesizeResults(Plan $plan, array $results, AgentContext $context): string
    {
        $findings = implode("\n\n", array_map(
            fn($id, $result) => "### " . $plan->getStepById($id)->action . "\n" . $result,
            array_keys($results),
            $results
        ));

        $prompt = <<<PROMPT
        Create a code review summary from these findings:

        {$findings}

        Format as:
        ## Summary
        ## Critical Issues
        ## Recommendations
        ## Approval Status
        PROMPT;

        return $this->callLlmForJson($this->instructions, $prompt, $context);
    }

    private function formatPreviousResults(array $results): string
    {
        if (empty($results)) {
            return 'None yet.';
        }
        return implode("\n", $results);
    }
}
```

## Best Practices

1. **Keep steps atomic** - Each step should do one thing well
2. **Use meaningful dependencies** - Only add dependencies that are truly required
3. **Set appropriate thresholds** - Higher thresholds mean more iterations
4. **Handle failures gracefully** - Throw `PlanExecutionException` for recoverable errors
5. **Log important events** - Use the built-in logging for debugging
6. **Test your synthesis logic** - The final output depends on good result combination

## API Reference

### PlanningAgent

| Method | Description |
|--------|-------------|
| `execute(mixed $input, AgentContext $context)` | Run the plan-execute-reflect loop |
| `setMaxReplanAttempts(int $attempts)` | Set max replan attempts |
| `setSatisfactionThreshold(float $threshold)` | Set satisfaction threshold (0-1) |
| `setPlannerInstructions(string $instructions)` | Set custom planner prompt |
| `setReflectionInstructions(string $instructions)` | Set custom reflection prompt |

### Plan

| Method | Description |
|--------|-------------|
| `Plan::fromJson(string $json)` | Create plan from JSON |
| `toJson()` | Serialize to JSON |
| `getStepById(int $id)` | Get a specific step |
| `isComplete()` | Check if all steps are done |

### PlanStep

| Method | Description |
|--------|-------------|
| `PlanStep::fromArray(array $data)` | Create from array |
| `areDependenciesSatisfied(array $completedIds)` | Check if ready to execute |
| `setCompleted(bool $completed)` | Mark step as done |
| `setResult(string $result)` | Store step result |

### Reflection

| Method | Description |
|--------|-------------|
| `Reflection::fromJson(string $json)` | Create from JSON |
| `requiresImprovement()` | Check if replanning needed |
| `getSummary()` | Get feedback summary |

## See Also

- [Gap Analysis - Planning & Reasoning Agents](./GAP_ANALYSIS.md#4-planning--reasoning-agents)
- [BaseLlmAgent Documentation](./AGENTS.md)
- [Workflow Patterns](./WORKFLOWS.md)
