# Agent Workflow Patterns

Vizra ADK provides powerful workflow capabilities to orchestrate multiple agents working together. Use the `Workflow` facade to create sequential, parallel, conditional, and loop workflows.

## Workflow Types Overview

1. **Sequential**: Agents execute one after another, passing data forward
2. **Parallel**: Multiple agents execute simultaneously
3. **Conditional**: Branch based on conditions
4. **Loop**: Iterate over data or repeat until condition

## Sequential Workflows

Execute agents in order, with each agent receiving the previous agent's output:

### Basic Sequential Flow
```php
use Vizra\VizraADK\Facades\Workflow;

$result = Workflow::sequential()
    ->then(ResearchAgent::class)    // First: gather information
    ->then(AnalysisAgent::class)    // Second: analyze findings
    ->then(ReportAgent::class)      // Third: generate report
    ->run('Analyze market trends for electric vehicles');

echo $result['final_result']; // Final report from the last agent
// $result['step_results'] contains results from each step
```

### Sequential with Custom Input Transform
```php
$workflow = Workflow::sequential()
    ->start(DataFetchAgent::class)
    ->then(DataCleanAgent::class, function($previousOutput) {
        // Transform output before passing to next agent
        return "Clean this data: " . $previousOutput;
    })
    ->then(DataAnalysisAgent::class)
    ->run('Fetch sales data from Q4');
```

### Quick Sequential Creation
```php
// Shorthand for simple sequences
$result = Workflow::sequential(
    WebScraperAgent::class,
    ContentParserAgent::class,
    SummarizerAgent::class
)->run($url);
```

## Parallel Workflows

Execute multiple agents simultaneously for faster processing:

### Basic Parallel Execution
```php
$results = Workflow::parallel()
    ->agents([
        'weather' => WeatherAgent::class,
        'news' => NewsAgent::class,
        'stocks' => StockAgent::class,
    ])
    ->run('Get updates for New York');

// Access individual results (each agent returns a string)
echo $results['weather'];  // Weather update
echo $results['news'];     // News headlines
echo $results['stocks'];   // Stock market info
```

### Parallel with Different Inputs
```php
$workflow = Workflow::parallel()
    ->agent('translator_french', TranslatorAgent::class, 'Translate to French: Hello')
    ->agent('translator_spanish', TranslatorAgent::class, 'Translate to Spanish: Hello')
    ->agent('translator_german', TranslatorAgent::class, 'Translate to German: Hello');

$translations = $workflow->run();
```

### Combining Parallel Results
```php
// Gather multiple perspectives then synthesize
$perspectives = Workflow::parallel()
    ->agents([
        'optimist' => OptimistAgent::class,
        'pessimist' => PessimistAgent::class,
        'realist' => RealistAgent::class,
    ])
    ->run('Evaluate this business proposal');

// Feed all perspectives to a synthesizer
$synthesis = SynthesisAgent::run(json_encode($perspectives))->go();
```

## Conditional Workflows

Branch execution based on conditions:

### Basic Conditional Flow
```php
$workflow = Workflow::conditional()
    ->when(function($input) {
        // Condition checker - return true/false for first agent
        $sentiment = SentimentAnalyzer::analyze($input);
        return $sentiment->score > 0.5;
    }, PositiveResponseAgent::class)
    ->otherwise(NegativeResponseAgent::class)
    ->run($customerFeedback);
```

### Multi-Branch Conditions
```php
$workflow = Workflow::conditional()
    ->when(function($input) {
        return str_contains($input, 'urgent');
    }, UrgentHandlerAgent::class)
    ->when(function($input) {
        return str_contains($input, 'important');
    }, StandardHandlerAgent::class)
    ->when(function($input) {
        return !str_contains($input, 'urgent') && !str_contains($input, 'important');
    }, DeferredHandlerAgent::class)
    ->otherwise(GeneralHandlerAgent::class) // Fallback
    ->run($supportTicket);
```

### Nested Conditions
```php
$workflow = Workflow::conditional()
    ->when(function($input) {
        return detectRequestType($input) === 'technical';
    }, TechnicalRouterAgent::class) // Delegate to another agent for complex routing
    ->when(function($input) {
        return detectRequestType($input) === 'billing';
    }, BillingAgent::class)
    ->otherwise(GeneralSupportAgent::class)
    ->run($customerRequest);
```

## Loop Workflows

Iterate over collections or repeat until conditions are met:

### For Each Loop
```php
$items = ['item1', 'item2', 'item3'];

$results = Workflow::loop()
    ->agent(ProcessorAgent::class)
    ->forEach($items)
    ->run();

// Results array contains output for each item
foreach ($results['iteration_results'] as $iteration => $result) {
    if ($result['success']) {
        echo "Processed item {$iteration}: {$result['result']}\n";
    }
}
```

### While Loop with Condition
```php
$workflow = Workflow::while(
    RefineAgent::class,
    function($output) {
        // Continue while quality score is below threshold
        $score = QualityChecker::evaluate($output);
        return $score < 0.9;
    },
    10 // Maximum iterations to prevent infinite loops
);

$refinedContent = $workflow->run($draftContent);
```

### Loop with Accumulator
```php
$workflow = Workflow::loop()
    ->agent(DataEnrichmentAgent::class)
    ->forEach($records)
    ->accumulate(function($results) {
        // Combine all enriched records
        return array_merge(...$results);
    });

$enrichedDataset = $workflow->run();
```

### Loop Until Success
```php
$workflow = Workflow::loop()
    ->agent(ApiCallerAgent::class)
    ->until(function($output) {
        // Retry until we get a successful response
        $result = json_decode($output, true);
        return $result && $result['status'] === 'success';
    })
    ->maxIterations(5)
    ->run('Call external API');
```

## Complex Workflow Compositions

### Research and Report Pipeline
```php
class ResearchWorkflow
{
    public static function execute(string $topic)
    {
        // Step 1: Parallel research from multiple sources
        $research = Workflow::parallel()
            ->agents([
                'web' => WebResearchAgent::class,
                'academic' => AcademicSearchAgent::class,
                'news' => NewsResearchAgent::class,
            ])
            ->run($topic);

        // Step 2: Sequential processing of research
        $report = Workflow::sequential()
            ->then(DataConsolidatorAgent::class)
            ->then(FactCheckerAgent::class)
            ->then(ReportWriterAgent::class)
            ->then(EditorAgent::class)
            ->run(json_encode($research));

        return $report;
    }
}
```

### Customer Service Escalation
```php
class CustomerServiceWorkflow
{
    public static function handle(string $inquiry)
    {
        return Workflow::conditional()
            ->when(function($input) {
                $assessment = InitialAssessmentAgent::run($input)->go();
                $data = json_decode($assessment, true);
                return $data['complexity'] === 'simple';
            }, AutoResponseAgent::class)
            ->when(function($input) {
                $assessment = InitialAssessmentAgent::run($input)->go();
                $data = json_decode($assessment, true);
                return $data['complexity'] === 'moderate';
            }, ModerateComplexityAgent::class)
            ->when(function($input) {
                $assessment = InitialAssessmentAgent::run($input)->go();
                $data = json_decode($assessment, true);
                return $data['complexity'] === 'complex';
            }, ComplexIssueAgent::class)
            ->otherwise(GeneralHandlerAgent::class)
            ->run($inquiry);
    }
}
```

### Data Processing Pipeline
```php
class DataPipelineWorkflow
{
    public static function process(array $datasets)
    {
        // Step 1: Validate all datasets in parallel
        $validated = Workflow::parallel()
            ->agents(array_map(fn($d) => ValidationAgent::class, $datasets))
            ->run($datasets);

        // Step 2: Process each valid dataset
        $processed = Workflow::loop()
            ->agent(DataTransformAgent::class)
            ->forEach(array_filter($validated, fn($v) => $v->valid))
            ->run();

        // Step 3: Aggregate results
        $final = Workflow::sequential()
            ->then(DataAggregatorAgent::class)
            ->then(ReportGeneratorAgent::class)
            ->run(json_encode($processed));

        return $final;
    }
}
```

## Workflow Context and Parameters

### Passing Context Through Workflows
```php
$workflow = Workflow::sequential()
    ->then(FirstAgent::class)
    ->then(SecondAgent::class)
    ->forUser($user)              // Maintain user context
    ->withSession($sessionId)     // Maintain session
    ->withParameters([            // Custom parameters
        'mode' => 'production',
        'priority' => 'high'
    ])
    ->run($input);
```

### Workflow with Memory Persistence
```php
// Each agent in the workflow shares memory
$workflow = Workflow::sequential()
    ->then(GatherRequirementsAgent::class)
    ->then(DesignSolutionAgent::class)
    ->then(ImplementationAgent::class)
    ->forUser($user)
    ->withSession('project-123')
    ->run('Build a user authentication system');
```

## Error Handling in Workflows

### Workflow with Error Recovery
```php
try {
    $result = Workflow::sequential()
        ->then(RiskyOperationAgent::class)
        ->then(ProcessingAgent::class)
        ->onError(function($error, $stage) {
            // Log error and potentially recover
            Log::error("Workflow failed at stage {$stage}: {$error}");
            return ErrorRecoveryAgent::run($error)->go();
        })
        ->run($input);
} catch (WorkflowException $e) {
    // Handle complete workflow failure
    return FallbackAgent::run($input)->go();
}
```

### Partial Failure Handling
```php
$results = Workflow::parallel()
    ->agents($agentList)
    ->continueOnFailure() // Don't stop if one agent fails
    ->run($input);

// Check which agents succeeded
foreach ($results as $key => $result) {
    if ($result instanceof \Exception) {
        Log::warning("Agent {$key} failed: " . $result->getMessage());
    }
}
```

## Performance Optimization

### Timeout Configuration
```php
$workflow = Workflow::parallel()
    ->agents($agents)
    ->timeout(30) // 30 second timeout per agent
    ->run($input);
```

### Caching Workflow Results
```php
$cacheKey = 'workflow_' . md5($input);

$result = Cache::remember($cacheKey, 3600, function() use ($input) {
    return Workflow::sequential()
        ->agent(ExpensiveAgent1::class)
        ->agent(ExpensiveAgent2::class)
        ->run($input);
});
```

## Testing Workflows

```php
use Tests\TestCase;

class WorkflowTest extends TestCase
{
    public function test_sequential_workflow()
    {
        $workflow = Workflow::sequential()
            ->agent(TestAgent1::class)
            ->agent(TestAgent2::class);
        
        $result = $workflow->run('test input');
        
        $this->assertNotNull($result);
        $this->assertStringContainsString('expected', $result['final_result']);
    }
    
    public function test_parallel_workflow_returns_all_results()
    {
        $results = Workflow::parallel()
            ->agents([
                'a' => AgentA::class,
                'b' => AgentB::class,
            ])
            ->run('test');
        
        $this->assertCount(2, $results);
        $this->assertArrayHasKey('a', $results);
        $this->assertArrayHasKey('b', $results);
    }
}
```

## Best Practices

1. **Always set maximum iterations** for loops to prevent infinite execution
2. **Use meaningful branch names** in conditional workflows
3. **Consider timeout settings** for parallel workflows
4. **Log workflow stages** for debugging
5. **Cache expensive workflow results** when appropriate
6. **Test each workflow path** independently
7. **Document complex workflows** with clear comments
8. **Use error handlers** for production workflows
9. **Monitor workflow performance** with traces
10. **Break complex workflows** into reusable components

## Common Pitfalls

1. **Infinite loops**: Always set `maxIterations` on loops
2. **Memory issues**: Be careful with large datasets in loops
3. **Timeout failures**: Set appropriate timeouts for long-running agents
4. **Context loss**: Remember to pass user/session context through workflows
5. **Error propagation**: Handle errors at appropriate levels

## Next Steps

- Learn about memory persistence: See `memory-usage.blade.php`
- Implement sub-agent delegation: See `sub-agents.blade.php`
- Test workflow quality: See `evaluation.blade.php`