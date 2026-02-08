---
name: "Vizra ADK Evaluation Framework"
description: "Test and evaluate AI agents with automated evaluations, assertions, and LLM-as-a-Judge patterns"
---

# Vizra ADK Evaluation Framework

The evaluation framework enables automated testing of AI agents at scale, including LLM-as-a-Judge evaluation patterns.

## Core Concepts

| Component | Purpose |
|-----------|---------|
| **Evaluation** | Test suite containing test cases for an agent |
| **Test Case** | Input/expected output pair for testing |
| **Assertion** | Validation rule for agent responses |
| **Judge** | LLM-based evaluation of response quality |

## Creating Evaluations

### Basic Evaluation

```php
<?php

namespace App\Evaluations;

use Vizra\VizraADK\Evaluations\BaseEvaluation;

class CustomerServiceEvaluation extends BaseEvaluation
{
    /**
     * The agent being evaluated
     */
    protected string $agent = 'customer_service';

    /**
     * Evaluation description
     */
    protected string $description = 'Evaluates customer service agent responses';

    /**
     * Define test cases
     */
    public function testCases(): array
    {
        return [
            [
                'name' => 'greeting_response',
                'input' => 'Hello, I need help with my order',
                'assertions' => [
                    'contains_greeting',
                    'offers_assistance',
                    'professional_tone',
                ],
            ],
            [
                'name' => 'refund_request',
                'input' => 'I want a refund for order #12345',
                'context' => [
                    'order_id' => '12345',
                    'order_status' => 'delivered',
                ],
                'assertions' => [
                    'acknowledges_request',
                    'asks_for_reason',
                    'explains_policy',
                ],
            ],
            [
                'name' => 'complaint_handling',
                'input' => 'This is terrible service! I\'ve been waiting for weeks!',
                'assertions' => [
                    'empathetic_response',
                    'apologizes',
                    'offers_solution',
                    'no_defensive_language',
                ],
            ],
        ];
    }
}
```

### With Expected Outputs

```php
public function testCases(): array
{
    return [
        [
            'name' => 'specific_answer',
            'input' => 'What are your business hours?',
            'expected' => 'Monday to Friday, 9 AM to 5 PM',
            'assertions' => [
                'exact_match', // or 'contains', 'semantic_match'
            ],
        ],
    ];
}
```

## Creating Assertions

### Basic Assertion

```php
<?php

namespace App\Evaluations\Assertions;

use Vizra\VizraADK\Evaluations\BaseAssertion;

class ContainsGreetingAssertion extends BaseAssertion
{
    /**
     * Assertion description
     */
    protected string $description = 'Response should contain a greeting';

    /**
     * Evaluate the assertion
     */
    public function evaluate(string $response, array $context = []): bool
    {
        $greetings = ['hello', 'hi', 'good morning', 'good afternoon', 'welcome'];

        $lowercaseResponse = strtolower($response);

        foreach ($greetings as $greeting) {
            if (str_contains($lowercaseResponse, $greeting)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Failure message
     */
    public function failureMessage(): string
    {
        return 'Response did not contain a greeting';
    }
}
```

### Parameterized Assertion

```php
class ContainsKeywordAssertion extends BaseAssertion
{
    protected string $description = 'Response should contain specific keyword';

    public function __construct(
        protected string $keyword,
        protected bool $caseSensitive = false
    ) {}

    public function evaluate(string $response, array $context = []): bool
    {
        if ($this->caseSensitive) {
            return str_contains($response, $this->keyword);
        }

        return str_contains(
            strtolower($response),
            strtolower($this->keyword)
        );
    }

    public function failureMessage(): string
    {
        return "Response did not contain keyword: {$this->keyword}";
    }
}

// Usage in test case
'assertions' => [
    new ContainsKeywordAssertion('refund'),
    new ContainsKeywordAssertion('policy'),
],
```

### JSON Schema Assertion

```php
use Vizra\VizraADK\Evaluations\Assertions\JsonSchemaAssertion;

'assertions' => [
    new JsonSchemaAssertion([
        'type' => 'object',
        'required' => ['status', 'message'],
        'properties' => [
            'status' => ['type' => 'string', 'enum' => ['success', 'error']],
            'message' => ['type' => 'string'],
            'data' => ['type' => 'object'],
        ],
    ]),
],
```

## LLM-as-a-Judge

Use an LLM to evaluate response quality:

### Basic Judge

```php
use Vizra\VizraADK\Evaluations\Assertions\LlmJudgeAssertion;

'assertions' => [
    new LlmJudgeAssertion(
        criteria: 'The response should be helpful, accurate, and professional',
        model: 'gpt-4o'
    ),
],
```

### Detailed Rubric

```php
new LlmJudgeAssertion(
    criteria: <<<'CRITERIA'
        Evaluate the customer service response on these dimensions:

        1. Empathy (0-3): Does the response acknowledge the customer's feelings?
        2. Helpfulness (0-3): Does it provide actionable assistance?
        3. Accuracy (0-3): Is the information correct?
        4. Professionalism (0-3): Is the tone appropriate?

        Total score should be at least 9/12 to pass.
        CRITERIA,
    model: 'gpt-4o',
    threshold: 0.75
),
```

### Comparative Judge

```php
new LlmComparativeAssertion(
    referenceResponse: $idealResponse,
    criteria: 'Response should be as helpful as or better than the reference',
    model: 'gpt-4o'
),
```

## Running Evaluations

### CLI Commands

```bash
# Run all evaluations
php artisan vizra:eval:run

# Run specific evaluation
php artisan vizra:eval:run --evaluation=CustomerServiceEvaluation

# Run with verbose output
php artisan vizra:eval:run --verbose

# Run specific test case
php artisan vizra:eval:run --evaluation=CustomerServiceEvaluation --case=greeting_response

# Output results as JSON
php artisan vizra:eval:run --format=json

# Save results to file
php artisan vizra:eval:run --output=results.json
```

### Programmatic Execution

```php
use Vizra\VizraADK\Services\EvaluationRunner;

$runner = app(EvaluationRunner::class);

// Run evaluation
$results = $runner->run(CustomerServiceEvaluation::class);

// Check results
foreach ($results->testCases as $testCase) {
    echo "{$testCase->name}: " . ($testCase->passed ? 'PASSED' : 'FAILED') . "\n";

    foreach ($testCase->assertions as $assertion) {
        if (!$assertion->passed) {
            echo "  - {$assertion->name}: {$assertion->message}\n";
        }
    }
}

// Get summary
echo "Passed: {$results->passedCount}/{$results->totalCount}\n";
```

## Advanced Evaluation Patterns

### Context Setup

```php
class OrderEvaluation extends BaseEvaluation
{
    protected string $agent = 'order_assistant';

    /**
     * Setup context before each test
     */
    protected function setUp(): void
    {
        // Create test data
        $this->testOrder = Order::factory()->create([
            'status' => 'processing',
            'total' => 99.99,
        ]);
    }

    /**
     * Cleanup after each test
     */
    protected function tearDown(): void
    {
        $this->testOrder->delete();
    }

    public function testCases(): array
    {
        return [
            [
                'name' => 'order_status_query',
                'input' => "What's the status of my order?",
                'context' => [
                    'order_id' => fn() => $this->testOrder->id,
                ],
                'assertions' => ['mentions_processing_status'],
            ],
        ];
    }
}
```

### Dynamic Test Cases

```php
public function testCases(): array
{
    $testCases = [];

    // Generate test cases from data
    $scenarios = json_decode(file_get_contents('test_scenarios.json'), true);

    foreach ($scenarios as $scenario) {
        $testCases[] = [
            'name' => $scenario['id'],
            'input' => $scenario['input'],
            'expected' => $scenario['expected'],
            'assertions' => $this->buildAssertions($scenario['checks']),
        ];
    }

    return $testCases;
}
```

### Regression Testing

```php
class RegressionEvaluation extends BaseEvaluation
{
    public function testCases(): array
    {
        // Load historical test cases that previously failed
        $regressions = EvaluationResult::where('status', 'failed')
            ->where('fixed', true)
            ->get();

        return $regressions->map(fn($r) => [
            'name' => "regression_{$r->id}",
            'input' => $r->input,
            'assertions' => ['should_not_regress'],
            'context' => ['original_failure' => $r->failure_reason],
        ])->toArray();
    }
}
```

## Built-in Assertions

| Assertion | Purpose |
|-----------|---------|
| `ExactMatchAssertion` | Response exactly matches expected |
| `ContainsAssertion` | Response contains substring |
| `RegexAssertion` | Response matches regex pattern |
| `JsonSchemaAssertion` | Response matches JSON schema |
| `LengthAssertion` | Response length within bounds |
| `SentimentAssertion` | Response has expected sentiment |
| `LlmJudgeAssertion` | LLM evaluates response quality |
| `NoHallucinationAssertion` | Response doesn't contain made-up facts |
| `SafetyAssertion` | Response doesn't contain harmful content |

## Custom Assertion Example

```php
class NoHallucinationAssertion extends BaseAssertion
{
    protected string $description = 'Response should not contain hallucinated facts';

    public function __construct(
        protected array $knownFacts
    ) {}

    public function evaluate(string $response, array $context = []): bool
    {
        // Use LLM to check for hallucinations
        $judge = app(LlmJudge::class);

        return $judge->evaluate(
            prompt: "Does this response contain any facts not supported by the known facts?",
            response: $response,
            context: ['known_facts' => $this->knownFacts]
        );
    }
}
```

## Evaluation Reports

### Generate HTML Report

```bash
php artisan vizra:eval:run --report=html --output=report.html
```

### Report Contents

- Overall pass/fail statistics
- Per-test-case results
- Assertion details
- Response comparisons
- Timing metrics
- Failure analysis

## CI/CD Integration

### GitHub Actions Example

```yaml
- name: Run Agent Evaluations
  run: |
    php artisan vizra:eval:run --format=junit --output=results.xml

- name: Upload Results
  uses: actions/upload-artifact@v3
  with:
    name: evaluation-results
    path: results.xml
```

## Artisan Commands

```bash
# Create new evaluation
php artisan vizra:make:eval CustomerServiceEvaluation

# Create new assertion
php artisan vizra:make:assertion ContainsGreetingAssertion

# Run evaluations
php artisan vizra:eval:run

# List evaluations
php artisan vizra:eval:list
```

## Best Practices

1. **Test edge cases** - Include difficult scenarios, not just happy paths
2. **Use multiple assertions** - Combine rule-based and LLM-based checks
3. **Version test cases** - Track changes to test expectations over time
4. **Automate in CI/CD** - Run evaluations on every deploy
5. **Monitor trends** - Track pass rates over time to detect regressions
