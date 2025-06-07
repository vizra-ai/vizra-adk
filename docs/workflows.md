# Workflow Agents Guide

Workflow Agents provide powerful orchestration capabilities for your Laravel Agent ADK applications. Unlike LLM agents that use artificial intelligence for decision-making, Workflow Agents control the execution flow of other agents in predefined, deterministic patterns.

## Table of Contents

1. [Overview](#overview)
2. [Installation & Setup](#installation--setup)
3. [Workflow Types](#workflow-types)
4. [Sequential Workflows](#sequential-workflows)
5. [Parallel Workflows](#parallel-workflows)
6. [Conditional Workflows](#conditional-workflows)
7. [Loop Workflows](#loop-workflows)
8. [Advanced Features](#advanced-features)
9. [Best Practices](#best-practices)
10. [API Reference](#api-reference)

## Overview

Workflow Agents are perfect for:
- **Structured Processes**: When you need predictable execution patterns
- **Multi-step Operations**: Breaking complex tasks into manageable steps
- **Parallel Processing**: Running multiple agents simultaneously
- **Conditional Logic**: Routing execution based on data or conditions
- **Iterative Tasks**: Repeating operations until conditions are met

### Key Benefits

✅ **Deterministic**: Predictable execution patterns  
✅ **Efficient**: No LLM overhead for flow control  
✅ **Flexible**: Combine different workflow types  
✅ **Laravel-friendly**: Familiar fluent syntax  
✅ **Testable**: Easy to unit test and debug  

## Installation & Setup

Workflow Agents are included with Laravel Agent ADK v2.0+. No additional installation required.

```php
use AaronLumsden\LaravelAgentADK\Facades\Workflow;
```

## Workflow Types

### 1. Sequential Workflows
Execute agents one after another, passing results between steps.

### 2. Parallel Workflows  
Run multiple agents simultaneously and collect results.

### 3. Conditional Workflows
Route to different agents based on conditions (if-else logic).

### 4. Loop Workflows
Repeat agent execution based on conditions (while, until, times, forEach).

## Sequential Workflows

Perfect for step-by-step processes where each step depends on the previous one.

### Basic Usage

```php
use AaronLumsden\LaravelAgentADK\Facades\Workflow;

// Simple sequential execution
$result = Workflow::sequential()
    ->start('DataCollectorAgent')
    ->then('DataValidatorAgent')
    ->then('DataProcessorAgent')
    ->execute($input);
```

### Quick Creation

```php
// Create with agent names directly
$result = Workflow::sequential('DataCollectorAgent', 'DataValidatorAgent', 'DataProcessorAgent')
    ->execute($input);
```

### With Parameters

```php
$result = Workflow::sequential()
    ->start('UserAgent', ['user_id' => 123])
    ->then('EmailAgent', fn($result) => ['email' => $result['user']['email']])
    ->then('NotificationAgent')
    ->execute($input);
```

### Error Handling & Cleanup

```php
$result = Workflow::sequential()
    ->start('DataProcessorAgent')
    ->then('ValidationAgent')
    ->finally('CleanupAgent') // Always runs, even on failure
    ->onSuccess(fn($result) => Log::info('Workflow completed', $result))
    ->onFailure(fn($error) => Log::error('Workflow failed', ['error' => $error]))
    ->execute($input);
```

### Conditional Steps

```php
$result = Workflow::sequential()
    ->start('AuthAgent')
    ->when('PremiumAgent', fn($result) => $result['user']['is_premium'])
    ->then('ProcessAgent')
    ->execute($input);
```

### Result Handling

```php
$workflow = Workflow::sequential()
    ->start('FirstAgent')
    ->then('SecondAgent')
    ->execute($input);

// Access final result
$finalResult = $workflow['final_result'];

// Access individual step results
$firstResult = $workflow['step_results']['FirstAgent'];
$secondResult = $workflow['step_results']['SecondAgent'];
```

## Parallel Workflows

Execute multiple agents simultaneously for improved performance.

### Basic Parallel Execution

```php
$result = Workflow::parallel()
    ->agents(['WeatherAgent', 'NewsAgent', 'StockAgent'])
    ->execute($input);
```

### With Different Parameters

```php
$result = Workflow::parallel()
    ->agents([
        'WeatherAgent' => ['location' => 'London'],
        'NewsAgent' => ['category' => 'tech'],
        'StockAgent' => ['symbol' => 'AAPL']
    ])
    ->execute($input);
```

### Wait Strategies

```php
// Wait for all agents to complete (default)
$result = Workflow::parallel()
    ->agents(['AgentA', 'AgentB', 'AgentC'])
    ->waitForAll()
    ->execute($input);

// Return as soon as any agent completes
$result = Workflow::parallel()
    ->agents(['AgentA', 'AgentB', 'AgentC'])
    ->waitForAny()
    ->execute($input);

// Wait for specific number of completions
$result = Workflow::parallel()
    ->agents(['AgentA', 'AgentB', 'AgentC'])
    ->waitFor(2) // Wait for 2 agents to complete
    ->execute($input);
```

### Error Handling

```php
// Fail immediately on first error (default)
$result = Workflow::parallel()
    ->agents(['AgentA', 'AgentB'])
    ->failFast(true)
    ->execute($input);

// Continue even if some agents fail
$result = Workflow::parallel()
    ->agents(['AgentA', 'AgentB', 'AgentC'])
    ->failFast(false)
    ->execute($input);
```

### Async Execution with Queues

```php
// Use Laravel queues for true async execution
$result = Workflow::parallel()
    ->agents(['LongRunningAgent', 'AnotherLongAgent'])
    ->async(true)
    ->execute($input);

// Returns job tracking info instead of results
// {
//     "job_ids": ["job_1", "job_2"],
//     "session_id": "workflow_123",
//     "workflow_type": "parallel_async",
//     "status": "queued"
// }

// Retrieve results later
$results = ParallelWorkflow::getAsyncResults('workflow_123');
```

### Result Structure

```php
$result = Workflow::parallel()
    ->agents(['AgentA', 'AgentB'])
    ->execute($input);

// Result structure:
// {
//     "results": {
//         "AgentA": "result_a",
//         "AgentB": "result_b"
//     },
//     "errors": {},
//     "completed_count": 2,
//     "total_count": 2,
//     "workflow_type": "parallel"
// }
```

## Conditional Workflows

Route execution to different agents based on conditions - like if-else statements for agents.

### Basic Conditional Logic

```php
$result = Workflow::conditional()
    ->when(fn($input) => $input['type'] === 'premium', 'PremiumAgent')
    ->when(fn($input) => $input['type'] === 'basic', 'BasicAgent')
    ->otherwise('DefaultAgent')
    ->execute($input);
```

### Built-in Condition Helpers

```php
$result = Workflow::conditional()
    // Equality check
    ->whenEquals('status', 'active', 'ActiveUserAgent')
    
    // Numeric comparisons
    ->whenGreaterThan('score', 90, 'HighScoreAgent')
    ->whenLessThan('age', 18, 'MinorAgent')
    
    // Existence checks
    ->whenExists('email', 'EmailAgent')
    ->whenEmpty('description', 'NoDescriptionAgent')
    
    // Regular expression matching
    ->whenMatches('email', '/^.+@.+\..+$/', 'ValidEmailAgent')
    
    // Fallback
    ->otherwise('DefaultAgent')
    ->execute($input);
```

### Nested Data Access

```php
// Use dot notation for nested array/object access
$result = Workflow::conditional()
    ->whenEquals('user.membership.type', 'premium', 'PremiumAgent')
    ->whenGreaterThan('user.profile.score', 100, 'HighScoreAgent')
    ->otherwise('DefaultAgent')
    ->execute([
        'user' => [
            'membership' => ['type' => 'premium'],
            'profile' => ['score' => 150]
        ]
    ]);
```

### Complex Conditions

```php
$result = Workflow::conditional()
    ->when(function($input) {
        return $input['user']['age'] >= 18 && 
               $input['user']['verified'] === true &&
               $input['user']['subscription'] === 'active';
    }, 'FullAccessAgent')
    ->when(fn($input) => $input['user']['age'] >= 18, 'AdultAgent')
    ->otherwise('RestrictedAgent')
    ->execute($input);
```

### Parameters with Conditions

```php
$result = Workflow::conditional()
    ->when(
        fn($input) => $input['type'] === 'premium',
        'PremiumAgent',
        ['features' => 'all'] // Static parameters
    )
    ->when(
        fn($input) => $input['type'] === 'basic',
        'BasicAgent',
        fn($input) => ['features' => $input['allowed_features']] // Dynamic parameters
    )
    ->execute($input);
```

### Result Structure

```php
// Result structure:
// {
//     "result": "agent_result",
//     "matched_agent": "PremiumAgent",
//     "was_default": false,
//     "workflow_type": "conditional"
// }
```

## Loop Workflows

Repeat agent execution based on various conditions.

### Times Loop

```php
// Execute agent exactly N times
$result = Workflow::times('ProcessorAgent', 5)
    ->execute($input);

// Or using the fluent API
$result = Workflow::loop()
    ->agent('ProcessorAgent')
    ->times(5)
    ->execute($input);
```

### While Loop

```php
// Continue while condition is true
$result = Workflow::while('ProcessorAgent', fn($input) => $input['counter'] < 10)
    ->execute(['counter' => 0]);

// Or using the fluent API
$result = Workflow::loop()
    ->agent('ProcessorAgent')
    ->while(fn($input) => $input['counter'] < 10)
    ->execute(['counter' => 0]);
```

### Until Loop

```php
// Continue until condition becomes true
$result = Workflow::until('ProcessorAgent', fn($input) => $input['done'] === true)
    ->execute(['done' => false]);

// Or using the fluent API
$result = Workflow::loop()
    ->agent('ProcessorAgent')
    ->until(fn($input) => $input['done'] === true)
    ->execute(['done' => false]);
```

### ForEach Loop

```php
// Iterate over a collection
$items = ['apple', 'banana', 'orange'];
$result = Workflow::forEach('ProcessItemAgent', $items)
    ->execute();

// Agent receives parameters:
// {
//     "item": "apple",          // Current item
//     "key": 0,                 // Current key/index
//     "iteration": 1,           // Current iteration number
//     "original_input": ...,    // Original workflow input
//     "original_params": ...    // Original agent parameters
// }
```

### ForEach with Associative Arrays

```php
$data = [
    'user_123' => ['name' => 'John', 'email' => 'john@example.com'],
    'user_456' => ['name' => 'Jane', 'email' => 'jane@example.com']
];

$result = Workflow::forEach('ProcessUserAgent', $data)
    ->execute();

// Agent receives:
// {
//     "item": {"name": "John", "email": "john@example.com"},
//     "key": "user_123",
//     "iteration": 1,
//     ...
// }
```

### Safety & Error Handling

```php
$result = Workflow::loop()
    ->agent('ProcessorAgent')
    ->while(fn($input) => true) // Could loop forever
    ->maxIterations(100)        // Safety limit
    ->continueOnError()         // Don't stop on agent failures
    ->execute($input);
```

### Loop Result Structure

```php
// Result structure:
// {
//     "iterations": 5,
//     "results": {
//         "1": {"iteration": 1, "input": ..., "result": ..., "success": true},
//         "2": {"iteration": 2, "input": ..., "result": ..., "success": true},
//         ...
//     },
//     "loop_type": "times",
//     "completed_normally": true,
//     "final_input": ...
// }
```

### Accessing Loop Results

```php
$workflow = Workflow::loop()
    ->agent('ProcessorAgent')
    ->times(3)
    ->execute($input);

// Get specific iteration result
$firstResult = $workflow->getIterationResult(1);

// Get only successful results
$successfulResults = $workflow->getSuccessfulResults();

// Get only failed results
$failedResults = $workflow->getFailedResults();
```

## Advanced Features

### Combining Workflow Types

```php
// Sequential workflow with parallel steps
$result = Workflow::sequential()
    ->start('InitAgent')
    ->then(function($result) {
        return Workflow::parallel()
            ->agents(['DataAgent', 'CacheAgent'])
            ->execute($result);
    })
    ->then('MergeAgent')
    ->execute($input);
```

### Nested Workflows

```php
$parallelWorkflow = Workflow::parallel()
    ->agents(['WeatherAgent', 'NewsAgent']);

$result = Workflow::sequential()
    ->start('AuthAgent')
    ->then($parallelWorkflow) // Embed parallel workflow
    ->then('ProcessResultsAgent')
    ->execute($input);
```

### Timeouts & Retries

```php
$result = Workflow::sequential()
    ->start('UnreliableAgent')
    ->timeout(60)                    // 60 second timeout
    ->retryOnFailure(3, 2000)       // 3 retries with 2 second delay
    ->execute($input);
```

### Callbacks & Events

```php
$result = Workflow::sequential()
    ->start('ProcessorAgent')
    ->onSuccess(function($result, $stepResults) {
        Log::info('Workflow completed successfully', [
            'final_result' => $result,
            'step_results' => $stepResults
        ]);
    })
    ->onFailure(function($error, $stepResults) {
        Log::error('Workflow failed', [
            'error' => $error->getMessage(),
            'completed_steps' => array_keys($stepResults)
        ]);
    })
    ->onComplete(function($result, $success, $stepResults) {
        // Called regardless of success/failure
        Event::dispatch(new WorkflowCompleted($result, $success));
    })
    ->execute($input);
```

### Creating Workflows from Arrays

```php
$definition = [
    'type' => 'sequential',
    'steps' => [
        ['agent' => 'FirstAgent', 'params' => ['param1' => 'value1']],
        ['agent' => 'SecondAgent', 'params' => ['param2' => 'value2']],
        ['agent' => 'ThirdAgent']
    ]
];

$workflow = Workflow::fromArray($definition);
$result = $workflow->execute($input);
```

### Workflow State Management

```php
$workflow = Workflow::sequential()
    ->start('FirstAgent')
    ->then('SecondAgent');

// Execute workflow
$result = $workflow->execute($input);

// Access step results
$firstResult = $workflow->getStepResult('FirstAgent');
$allResults = $workflow->getResults();

// Reset for reuse
$workflow->reset();
$newResult = $workflow->execute($newInput);
```

## Best Practices

### 1. Choose the Right Workflow Type

```php
// ✅ Good: Use sequential for dependent steps
Workflow::sequential()
    ->start('ValidateDataAgent')    // Must happen first
    ->then('ProcessDataAgent')      // Depends on validation
    ->then('SaveDataAgent');        // Depends on processing

// ✅ Good: Use parallel for independent tasks
Workflow::parallel()
    ->agents(['EmailAgent', 'SmsAgent', 'PushAgent']); // Can all run simultaneously

// ✅ Good: Use conditional for branching logic
Workflow::conditional()
    ->whenEquals('user_type', 'premium', 'PremiumOnboardingAgent')
    ->otherwise('BasicOnboardingAgent');

// ✅ Good: Use loops for repetitive tasks
Workflow::forEach('ProcessFileAgent', $files);
```

### 2. Handle Errors Gracefully

```php
// ✅ Good: Always provide error handling
$result = Workflow::sequential()
    ->start('CriticalAgent')
    ->onFailure(function($error) {
        // Log error, send notifications, cleanup, etc.
        Log::error('Critical workflow failed', ['error' => $error]);
        NotificationService::alertAdmins($error);
    })
    ->finally('CleanupAgent') // Always cleanup
    ->execute($input);
```

### 3. Use Appropriate Wait Strategies

```php
// ✅ Good: Use waitForAny for performance-critical scenarios
Workflow::parallel()
    ->agents(['PrimaryAPI', 'BackupAPI', 'CacheAPI'])
    ->waitForAny()     // Return as soon as any succeeds
    ->failFast(false); // Don't fail if backup APIs fail

// ✅ Good: Use waitForAll for data consistency
Workflow::parallel()
    ->agents(['ValidateUser', 'ValidatePayment', 'ValidateInventory'])
    ->waitForAll()     // All validations must pass
    ->failFast(true);  // Fail immediately if any validation fails
```

### 4. Optimize Performance

```php
// ✅ Good: Use async for long-running parallel workflows
Workflow::parallel()
    ->agents(['LongProcessA', 'LongProcessB', 'LongProcessC'])
    ->async(true)      // Use queue system
    ->execute($input);

// ✅ Good: Set reasonable timeouts
Workflow::sequential()
    ->start('ExternalAPIAgent')
    ->timeout(30)      // 30 second timeout for external calls
    ->retryOnFailure(3, 1000)
    ->execute($input);
```

### 5. Make Workflows Testable

```php
// ✅ Good: Create reusable workflow classes
class UserOnboardingWorkflow extends SequentialWorkflow
{
    public function build(): self
    {
        return $this
            ->start('ValidateUserAgent')
            ->then('CreateAccountAgent')
            ->then('SendWelcomeEmailAgent')
            ->finally('TrackOnboardingAgent');
    }
}

// Use in tests
public function test_user_onboarding_workflow()
{
    Agent::fake();
    
    $workflow = new UserOnboardingWorkflow();
    $result = $workflow->build()->execute($userData);
    
    Agent::assertCalled('ValidateUserAgent');
    Agent::assertCalled('CreateAccountAgent');
    $this->assertTrue($result['final_result']['success']);
}
```

### 6. Use Descriptive Agent Names

```php
// ❌ Bad: Generic names
Workflow::sequential()
    ->start('Agent1')
    ->then('Agent2')
    ->then('Agent3');

// ✅ Good: Descriptive names
Workflow::sequential()
    ->start('ValidateOrderDataAgent')
    ->then('CalculateShippingCostAgent')
    ->then('ProcessPaymentAgent')
    ->then('SendOrderConfirmationAgent');
```

## API Reference

### Workflow Facade

```php
// Factory methods
Workflow::sequential(string ...$agentNames): SequentialWorkflow
Workflow::parallel(array $agents = []): ParallelWorkflow
Workflow::conditional(): ConditionalWorkflow
Workflow::loop(string $agentName = null): LoopWorkflow

// Quick loop creation
Workflow::while(string $agentName, Closure $condition, int $maxIterations = 100): LoopWorkflow
Workflow::until(string $agentName, Closure $condition, int $maxIterations = 100): LoopWorkflow
Workflow::times(string $agentName, int $times): LoopWorkflow
Workflow::forEach(string $agentName, array $collection): LoopWorkflow

// Array-based creation
Workflow::fromArray(array $definition): BaseWorkflowAgent
```

### SequentialWorkflow

```php
// Fluent methods
start(string $agentName, mixed $params = null, array $options = []): self
then(string $agentName, mixed $params = null, array $options = []): self
finally(string $agentName, mixed $params = null, array $options = []): self
when(string $agentName, Closure $condition, mixed $params = null, array $options = []): self

// Static factory
SequentialWorkflow::create(string ...$agentNames): self
```

### ParallelWorkflow

```php
// Fluent methods
agents(array|string $agents, mixed $params = null, array $options = []): self
waitForAll(): self
waitForAny(): self
waitFor(int $count): self
failFast(bool $failFast = true): self
async(bool $async = true): self

// Static methods
ParallelWorkflow::create(array $agents): self
ParallelWorkflow::getAsyncResults(string $sessionId): array
```

### ConditionalWorkflow

```php
// Condition methods
when(string|Closure $condition, string $agentName, mixed $params = null, array $options = []): self
whenEquals(string $key, mixed $value, string $agentName, mixed $params = null, array $options = []): self
whenGreaterThan(string $key, mixed $value, string $agentName, mixed $params = null, array $options = []): self
whenLessThan(string $key, mixed $value, string $agentName, mixed $params = null, array $options = []): self
whenExists(string $key, string $agentName, mixed $params = null, array $options = []): self
whenEmpty(string $key, string $agentName, mixed $params = null, array $options = []): self
whenMatches(string $key, string $pattern, string $agentName, mixed $params = null, array $options = []): self
otherwise(string $agentName, mixed $params = null, array $options = []): self
else(string $agentName, mixed $params = null, array $options = []): self

// Static factory
ConditionalWorkflow::create(string|Closure $condition, string $thenAgent, string $elseAgent = null): self
```

### LoopWorkflow

```php
// Loop type methods
while(string|Closure $condition): self
until(string|Closure $condition): self
times(int $times): self
forEach(array $collection): self

// Configuration methods
agent(string $agentName, mixed $params = null, array $options = []): self
maxIterations(int $max): self
breakOnError(bool $break = true): self
continueOnError(): self

// Result methods
getIterationResult(int $iteration): mixed
getSuccessfulResults(): array
getFailedResults(): array

// Static factories
LoopWorkflow::createWhile(string $agentName, string|Closure $condition, int $maxIterations = 100): self
LoopWorkflow::createTimes(string $agentName, int $times): self
LoopWorkflow::createForEach(string $agentName, array $collection): self
```

### Base Methods (All Workflows)

```php
// Execution
execute(mixed $input, AgentContext $context = null): mixed
run(mixed $input, AgentContext $context): mixed

// Callbacks
onSuccess(Closure $callback): self
onFailure(Closure $callback): self
onComplete(Closure $callback): self

// Configuration
timeout(int $seconds): self
retryOnFailure(int $attempts, int $delayMs = 1000): self

// State management
getResults(): array
getStepResult(string $agentName): mixed
reset(): self
```

---

For more information, see the [Laravel Agent ADK Documentation](../README.md).