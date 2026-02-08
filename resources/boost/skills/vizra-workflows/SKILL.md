---
name: "Vizra ADK Workflows"
description: "Orchestrate complex multi-agent workflows - sequential, parallel, conditional, and loop patterns"
---

# Vizra ADK Workflow Patterns

Workflows orchestrate multiple agents to accomplish complex tasks. Vizra ADK supports sequential, parallel, conditional, and loop workflows.

## Workflow Types

| Workflow | Use Case |
|----------|----------|
| Sequential | Steps that must execute in order |
| Parallel | Independent tasks that can run simultaneously |
| Conditional | Branching based on conditions or results |
| Loop | Iterating over data or repeating until condition met |

## Sequential Workflow

Execute agents in order, passing results between steps:

```php
use Vizra\VizraADK\Workflows\SequentialWorkflow;

$workflow = SequentialWorkflow::create()
    ->addStep('research', ResearchAgent::class)
    ->addStep('analyze', AnalysisAgent::class)
    ->addStep('report', ReportGeneratorAgent::class);

$result = $workflow->run([
    'topic' => 'Market trends in AI',
    'depth' => 'comprehensive'
]);
```

### Passing Data Between Steps

```php
$workflow = SequentialWorkflow::create()
    ->addStep('fetch', DataFetcherAgent::class)
    ->addStep('transform', function ($previousResult, $context) {
        // Transform data from previous step
        return TransformAgent::run($previousResult['data'])
            ->withParameters(['format' => 'json'])
            ->go();
    })
    ->addStep('store', DataStorageAgent::class);
```

## Parallel Workflow

Run multiple agents simultaneously for independent tasks:

```php
use Vizra\VizraADK\Workflows\ParallelWorkflow;

$workflow = ParallelWorkflow::create()
    ->addTask('sentiment', SentimentAnalysisAgent::class)
    ->addTask('keywords', KeywordExtractionAgent::class)
    ->addTask('summary', SummarizationAgent::class);

$results = $workflow->run([
    'text' => $documentText
]);

// Results contain output from all agents
// $results['sentiment'], $results['keywords'], $results['summary']
```

### With Timeout

```php
$workflow = ParallelWorkflow::create()
    ->addTask('fast', FastAgent::class)
    ->addTask('slow', SlowAgent::class)
    ->timeout(30); // 30 second timeout

$results = $workflow->run($input);
```

## Conditional Workflow

Branch execution based on conditions:

```php
use Vizra\VizraADK\Workflows\ConditionalWorkflow;

$workflow = ConditionalWorkflow::create()
    ->addStep('classify', ClassificationAgent::class)
    ->branch(
        condition: fn($result) => $result['category'] === 'urgent',
        then: UrgentHandlerAgent::class,
        else: StandardHandlerAgent::class
    );

$result = $workflow->run(['ticket' => $ticketContent]);
```

### Multiple Conditions

```php
$workflow = ConditionalWorkflow::create()
    ->addStep('analyze', AnalysisAgent::class)
    ->switch(
        selector: fn($result) => $result['priority'],
        cases: [
            'high' => HighPriorityAgent::class,
            'medium' => MediumPriorityAgent::class,
            'low' => LowPriorityAgent::class,
        ],
        default: StandardAgent::class
    );
```

## Loop Workflow

Iterate over data or repeat until condition:

```php
use Vizra\VizraADK\Workflows\LoopWorkflow;

// Iterate over items
$workflow = LoopWorkflow::create()
    ->forEach(
        items: fn($context) => $context['documents'],
        agent: DocumentProcessorAgent::class
    );

$results = $workflow->run([
    'documents' => $documentList
]);
```

### With Condition

```php
$workflow = LoopWorkflow::create()
    ->repeat(
        agent: RefinementAgent::class,
        until: fn($result, $iteration) =>
            $result['quality_score'] >= 0.9 || $iteration >= 5
    );
```

### Map-Reduce Pattern

```php
$workflow = LoopWorkflow::create()
    ->map(
        items: fn($ctx) => $ctx['chunks'],
        agent: ChunkProcessorAgent::class
    )
    ->reduce(
        agent: AggregatorAgent::class
    );

$result = $workflow->run([
    'chunks' => $textChunks
]);
```

## Complex Workflow Compositions

### Research Pipeline

```php
$researchPipeline = SequentialWorkflow::create()
    // Step 1: Gather information from multiple sources in parallel
    ->addStep('gather', ParallelWorkflow::create()
        ->addTask('web', WebResearchAgent::class)
        ->addTask('database', DatabaseResearchAgent::class)
        ->addTask('documents', DocumentResearchAgent::class)
    )
    // Step 2: Analyze gathered information
    ->addStep('analyze', AnalysisAgent::class)
    // Step 3: Generate report
    ->addStep('report', ReportGeneratorAgent::class);

$report = $researchPipeline->run([
    'topic' => 'Competitive analysis',
    'sources' => ['web', 'internal_docs', 'database']
]);
```

### Customer Service Escalation

```php
$customerService = ConditionalWorkflow::create()
    // Initial classification
    ->addStep('classify', TicketClassifierAgent::class)
    // Route based on complexity
    ->branch(
        condition: fn($r) => $r['complexity'] === 'simple',
        then: SequentialWorkflow::create()
            ->addStep('respond', AutoResponderAgent::class)
            ->addStep('close', TicketCloserAgent::class),
        else: ConditionalWorkflow::create()
            ->branch(
                condition: fn($r) => $r['requires_human'],
                then: HumanEscalationAgent::class,
                else: SequentialWorkflow::create()
                    ->addStep('research', IssueResearchAgent::class)
                    ->addStep('respond', DetailedResponderAgent::class)
            )
    );
```

### Data Processing Pipeline

```php
$dataPipeline = SequentialWorkflow::create()
    // Validate incoming data
    ->addStep('validate', DataValidationAgent::class)
    // Process each record
    ->addStep('process', LoopWorkflow::create()
        ->forEach(
            items: fn($ctx) => $ctx['records'],
            agent: RecordProcessorAgent::class
        )
    )
    // Aggregate results
    ->addStep('aggregate', AggregationAgent::class)
    // Generate summary
    ->addStep('summarize', SummaryAgent::class);
```

## Error Handling in Workflows

```php
$workflow = SequentialWorkflow::create()
    ->addStep('risky', RiskyAgent::class)
    ->onError(function ($error, $step, $context) {
        Log::error("Workflow failed at step: {$step}", [
            'error' => $error->getMessage()
        ]);

        // Return fallback or rethrow
        return FallbackAgent::run($context)->go();
    })
    ->addStep('continue', NextAgent::class);
```

### Retry Logic

```php
$workflow = SequentialWorkflow::create()
    ->addStep('api_call', ExternalApiAgent::class)
    ->withRetry(
        maxAttempts: 3,
        delay: 1000, // milliseconds
        backoff: 'exponential'
    );
```

## Workflow Context

Access and modify context throughout workflow:

```php
$workflow = SequentialWorkflow::create()
    ->addStep('init', function ($input, $context) {
        $context->set('started_at', now());
        return InitAgent::run($input)->go();
    })
    ->addStep('process', ProcessorAgent::class)
    ->addStep('finalize', function ($result, $context) {
        $duration = now()->diffInSeconds($context->get('started_at'));
        return FinalizeAgent::run($result)
            ->withParameters(['duration' => $duration])
            ->go();
    });
```

## Performance Optimization

### Use Parallel for Independent Tasks

```php
// Good: Independent tasks run in parallel
$workflow = ParallelWorkflow::create()
    ->addTask('email', SendEmailAgent::class)
    ->addTask('sms', SendSmsAgent::class)
    ->addTask('push', SendPushAgent::class);

// Bad: Sequential when not needed
$workflow = SequentialWorkflow::create()
    ->addStep('email', SendEmailAgent::class)
    ->addStep('sms', SendSmsAgent::class)
    ->addStep('push', SendPushAgent::class);
```

### Set Appropriate Timeouts

```php
$workflow = ParallelWorkflow::create()
    ->addTask('fast', FastAgent::class)
    ->addTask('slow', SlowAgent::class)
    ->timeout(60)
    ->onTimeout(function ($completedTasks) {
        // Handle partial results
        return $completedTasks;
    });
```

## Testing Workflows

```php
class WorkflowTest extends TestCase
{
    public function test_sequential_workflow_completes()
    {
        $workflow = SequentialWorkflow::create()
            ->addStep('first', FirstAgent::class)
            ->addStep('second', SecondAgent::class);

        $result = $workflow->run(['input' => 'test']);

        $this->assertNotNull($result);
        $this->assertEquals('expected', $result['status']);
    }
}
```
