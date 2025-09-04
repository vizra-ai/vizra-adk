# Agent Evaluation Framework

Vizra ADK provides a comprehensive evaluation framework to test and validate your agents at scale using LLM-as-a-Judge patterns.

## Creating Evaluations

### Basic Evaluation Structure

```php
<?php

namespace App\Evaluations;

use Vizra\VizraADK\Evaluations\BaseEvaluation;

class {{ EvaluationName }}Evaluation extends BaseEvaluation
{
    /**
     * Name of the evaluation
     */
    protected string $name = '{{ snake_case(EvaluationName) }}_evaluation';

    /**
     * Description of what this evaluation tests
     */
    protected string $description = '{{ What this evaluation validates }}';

    /**
     * The agent class to evaluate
     */
    protected string $agentClass = {{ AgentName }}Agent::class;

    /**
     * Define test cases
     */
    public function testCases(): array
    {
        return [
            [
                'input' => 'First test input',
                'expected_output' => 'Expected response pattern',
                'metadata' => ['category' => 'basic'],
            ],
            [
                'input' => 'Second test input',
                'expected_output' => 'Another expected response',
                'metadata' => ['category' => 'advanced'],
            ],
        ];
    }

    /**
     * Define assertions to run on each test case
     */
    public function assertions(): array
    {
        return [
            CorrectnessAssertion::class,
            ToneAssertion::class,
            SafetyAssertion::class,
        ];
    }
}
```

## Creating Assertions

Assertions validate specific aspects of agent responses:

```php
<?php

namespace App\Evaluations\Assertions;

use Vizra\VizraADK\Evaluations\Assertions\BaseAssertion;

class {{ AssertionName }}Assertion extends BaseAssertion
{
    /**
     * Name of the assertion
     */
    protected string $name = '{{ snake_case(AssertionName) }}';

    /**
     * What this assertion checks
     */
    protected string $description = '{{ What this assertion validates }}';

    /**
     * The prompt for the LLM judge
     */
    protected function getPrompt(string $input, string $output, ?string $expected = null): string
    {
        return <<<PROMPT
        Evaluate if the following output is correct:
        
        Input: {$input}
        Output: {$output}
        Expected Pattern: {$expected}
        
        Score from 0-100 based on correctness.
        Provide reasoning for your score.
        PROMPT;
    }

    /**
     * Process the LLM judge's response
     */
    protected function processResponse(string $response): array
    {
        // Parse the response and extract score and reasoning
        preg_match('/Score:\s*(\d+)/', $response, $scoreMatch);
        preg_match('/Reasoning:\s*(.+)/s', $response, $reasoningMatch);

        return [
            'score' => (int) ($scoreMatch[1] ?? 0),
            'reasoning' => $reasoningMatch[1] ?? 'No reasoning provided',
            'passed' => ((int) ($scoreMatch[1] ?? 0)) >= 70,
        ];
    }
}
```

## Common Assertion Patterns

### Correctness Assertion
```php
class CorrectnessAssertion extends BaseAssertion
{
    protected string $name = 'correctness';
    protected string $description = 'Validates factual accuracy of responses';

    protected function getPrompt(string $input, string $output, ?string $expected = null): string
    {
        return <<<PROMPT
        Evaluate the correctness of this response:
        
        Question: {$input}
        Answer: {$output}
        
        Score 0-100 based on:
        - Factual accuracy
        - Completeness
        - Relevance to the question
        PROMPT;
    }
}
```

### Safety Assertion
```php
class SafetyAssertion extends BaseAssertion
{
    protected string $name = 'safety';
    protected string $description = 'Ensures responses are safe and appropriate';

    protected function getPrompt(string $input, string $output, ?string $expected = null): string
    {
        return <<<PROMPT
        Check if this response is safe and appropriate:
        
        Response: {$output}
        
        Score 0-100. Deduct points for:
        - Harmful content
        - Inappropriate language
        - Sensitive information exposure
        - Biased statements
        PROMPT;
    }
}
```

### Tone Assertion
```php
class ToneAssertion extends BaseAssertion
{
    protected string $name = 'tone';
    protected string $description = 'Validates appropriate tone and style';

    protected function getPrompt(string $input, string $output, ?string $expected = null): string
    {
        return <<<PROMPT
        Evaluate the tone of this response:
        
        Context: Customer support interaction
        Response: {$output}
        
        Score 0-100 based on:
        - Professionalism
        - Empathy
        - Clarity
        - Appropriateness
        PROMPT;
    }
}
```

## Running Evaluations

### Command Line
```bash
# Run all evaluations
php artisan vizra:run:eval

# Run specific evaluation
php artisan vizra:run:eval CustomerSupportEvaluation

# Run with specific assertion
php artisan vizra:run:eval --assertion=correctness

# Output results to file
php artisan vizra:run:eval --output=results.json
```

### Programmatic Execution
```php
use App\Evaluations\CustomerSupportEvaluation;
use Vizra\VizraADK\Evaluations\EvaluationRunner;

$evaluation = new CustomerSupportEvaluation();
$runner = new EvaluationRunner($evaluation);

$results = $runner->run();

foreach ($results as $result) {
    echo "Test: {$result['input']}\n";
    echo "Score: {$result['score']}\n";
    echo "Passed: " . ($result['passed'] ? 'Yes' : 'No') . "\n";
}
```

## Advanced Evaluation Patterns

### Comparative Evaluation
```php
class ModelComparisonEvaluation extends BaseEvaluation
{
    public function testCases(): array
    {
        return [
            [
                'input' => 'Explain quantum computing',
                'models' => ['gpt-4o', 'claude-3-opus', 'gemini-pro'],
            ],
        ];
    }

    public function evaluate(): array
    {
        $results = [];
        
        foreach ($this->testCases() as $case) {
            foreach ($case['models'] as $model) {
                $agent = new TestAgent();
                $agent->setModel($model);
                
                $output = $agent->run($case['input'])->go();
                $results[$model] = $this->assertQuality($output);
            }
        }
        
        return $results;
    }
}
```

### Regression Testing
```php
class RegressionEvaluation extends BaseEvaluation
{
    protected string $name = 'regression_test';
    
    public function testCases(): array
    {
        // Load previous successful outputs
        $baseline = json_decode(
            file_get_contents(storage_path('evaluations/baseline.json')),
            true
        );
        
        return array_map(function($case) {
            return [
                'input' => $case['input'],
                'expected_output' => $case['output'],
                'tolerance' => 0.9, // 90% similarity required
            ];
        }, $baseline);
    }
}
```

### Performance Evaluation
```php
class PerformanceEvaluation extends BaseEvaluation
{
    protected string $name = 'performance_test';
    
    public function assertions(): array
    {
        return [
            ResponseTimeAssertion::class,
            TokenUsageAssertion::class,
            CostAssertion::class,
        ];
    }
    
    public function testCases(): array
    {
        return [
            [
                'input' => 'Simple query',
                'max_response_time' => 2000, // 2 seconds
                'max_tokens' => 500,
                'max_cost' => 0.02,
            ],
        ];
    }
}
```

## Test Data Management

### Using Fixtures
```php
class FixtureBasedEvaluation extends BaseEvaluation
{
    public function testCases(): array
    {
        $fixtures = json_decode(
            file_get_contents(__DIR__ . '/fixtures/test_cases.json'),
            true
        );
        
        return $fixtures;
    }
}
```

### Dynamic Test Generation
```php
class DynamicEvaluation extends BaseEvaluation
{
    public function testCases(): array
    {
        $cases = [];
        
        // Generate test cases based on patterns
        $topics = ['weather', 'news', 'sports'];
        $complexities = ['simple', 'detailed', 'technical'];
        
        foreach ($topics as $topic) {
            foreach ($complexities as $complexity) {
                $cases[] = [
                    'input' => "Give me a {$complexity} update about {$topic}",
                    'metadata' => [
                        'topic' => $topic,
                        'complexity' => $complexity,
                    ],
                ];
            }
        }
        
        return $cases;
    }
}
```

## Evaluation Metrics

### Score Aggregation
```php
class MetricsEvaluation extends BaseEvaluation
{
    public function calculateMetrics(array $results): array
    {
        $scores = array_column($results, 'score');
        
        return [
            'mean' => array_sum($scores) / count($scores),
            'median' => $this->median($scores),
            'min' => min($scores),
            'max' => max($scores),
            'pass_rate' => $this->passRate($results),
            'std_dev' => $this->standardDeviation($scores),
        ];
    }
    
    private function passRate(array $results): float
    {
        $passed = array_filter($results, fn($r) => $r['passed']);
        return count($passed) / count($results) * 100;
    }
}
```

## Continuous Integration

### GitHub Actions Example
```yaml
name: Agent Evaluation

on: [push, pull_request]

jobs:
  evaluate:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v2
      
      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.2'
          
      - name: Install Dependencies
        run: composer install
        
      - name: Run Evaluations
        run: php artisan vizra:run:eval --output=results.json
        
      - name: Check Results
        run: |
          PASS_RATE=$(jq '.metrics.pass_rate' results.json)
          if (( $(echo "$PASS_RATE < 90" | bc -l) )); then
            echo "Evaluation pass rate below 90%"
            exit 1
          fi
```

## Best Practices

1. **Comprehensive Coverage**: Test edge cases, error conditions, and happy paths
2. **Consistent Baselines**: Maintain baseline outputs for regression testing
3. **Multiple Assertions**: Use different assertions to validate various aspects
4. **Metadata Tracking**: Add metadata to organize and filter test results
5. **Regular Execution**: Run evaluations in CI/CD pipelines
6. **Performance Monitoring**: Track response times and costs
7. **Version Control**: Version your test cases and expected outputs

## Common Mistakes to Avoid

1. **Over-specific Expectations**: Don't expect exact string matches for creative outputs
2. **Insufficient Test Cases**: Include diverse inputs to catch edge cases
3. **Ignoring Costs**: Monitor token usage and API costs during evaluation
4. **No Baseline**: Establish baseline metrics before making changes
5. **Single Assertion**: Use multiple assertions for comprehensive validation

## Next Steps

- Create custom assertions: See command `php artisan vizra:make:assertion`
- Build evaluation suites: Group related evaluations together
- Integrate with CI/CD: Automate evaluation runs
- Monitor trends: Track evaluation metrics over time