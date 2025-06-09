# 📊 Evaluation & Testing

Building great AI agents requires more than just good code—you need systematic testing and evaluation. The Vizra SDK includes powerful evaluation tools, including LLM-as-a-Judge capabilities, to help you build confidence in your agents.

## 🎯 Why Evaluation Matters

**Traditional software testing** checks if code does what you expect.  
**AI agent evaluation** checks if agents do what users need.

```
Traditional Test: "Does this function return the right data type?"
Agent Evaluation: "Does this agent help customers effectively?"
```

### The Challenge with AI Testing

AI agents are different from traditional software:

- **Non-deterministic** - Same input might produce different outputs
- **Context-dependent** - Quality depends on conversation history
- **Subjective** - "Good" responses are often subjective
- **Complex interactions** - Tools, memory, and reasoning combine in unpredictable ways

### The Solution: Systematic Evaluation

The Vizra SDK provides multiple evaluation approaches:

1. **LLM-as-a-Judge** - Use AI to evaluate AI (surprisingly effective!)
2. **Traditional Assertions** - Programmatic checks for specific criteria
3. **Human-in-the-Loop** - Structured human evaluation workflows
4. **A/B Testing** - Compare different agent versions
5. **Performance Metrics** - Response time, token usage, tool calls

## 🧪 LLM-as-a-Judge Evaluation

This is where the magic happens. Use a powerful LLM to evaluate your agent's responses using human-like criteria.

### Creating an Evaluation

```bash
php artisan vizra:make:evaluation CustomerSupportEvaluation
```

This creates `app/Evaluations/CustomerSupportEvaluation.php`:

```php
<?php

namespace App\Evaluations;

use Vizra\VizraSdk\Evaluations\BaseLlmJudgeEvaluation;

class CustomerSupportEvaluation extends BaseLlmJudgeEvaluation
{
    protected string $name = 'Customer Support Quality';
    protected string $description = 'Evaluates customer support agent responses for helpfulness, accuracy, and professionalism';

    protected function getJudgePrompt(): string
    {
        return "
        You are evaluating a customer support agent's response.

        Rate the response on these criteria (1-5 scale):

        **Helpfulness (1-5):**
        - Does the response address the customer's question or concern?
        - Does it provide actionable next steps?
        - Is it genuinely useful to the customer?

        **Accuracy (1-5):**
        - Is the information provided correct?
        - Are any policies or procedures mentioned accurately?
        - Are there any factual errors?

        **Professionalism (1-5):**
        - Is the tone appropriate for customer service?
        - Is the language clear and professional?
        - Does it maintain a helpful, friendly demeanor?

        **Completeness (1-5):**
        - Does the response fully address the question?
        - Are all parts of multi-part questions answered?
        - Is any important information missing?

        Provide your evaluation in this format:

        Helpfulness: [score] - [brief explanation]
        Accuracy: [score] - [brief explanation]
        Professionalism: [score] - [brief explanation]
        Completeness: [score] - [brief explanation]

        Overall Score: [average of all scores]

        Summary: [2-3 sentence summary of the response quality and any major issues]
        ";
    }

    protected function parseJudgeResponse(string $judgeResponse): array
    {
        // Extract scores from the judge's response
        $scores = [];
        $summary = '';

        // Parse helpfulness score
        if (preg_match('/Helpfulness:\s*(\d+(?:\.\d+)?)/i', $judgeResponse, $matches)) {
            $scores['helpfulness'] = (float) $matches[1];
        }

        // Parse accuracy score
        if (preg_match('/Accuracy:\s*(\d+(?:\.\d+)?)/i', $judgeResponse, $matches)) {
            $scores['accuracy'] = (float) $matches[1];
        }

        // Parse professionalism score
        if (preg_match('/Professionalism:\s*(\d+(?:\.\d+)?)/i', $judgeResponse, $matches)) {
            $scores['professionalism'] = (float) $matches[1];
        }

        // Parse completeness score
        if (preg_match('/Completeness:\s*(\d+(?:\.\d+)?)/i', $judgeResponse, $matches)) {
            $scores['completeness'] = (float) $matches[1];
        }

        // Parse overall score
        if (preg_match('/Overall Score:\s*(\d+(?:\.\d+)?)/i', $judgeResponse, $matches)) {
            $scores['overall'] = (float) $matches[1];
        }

        // Extract summary
        if (preg_match('/Summary:\s*(.+)/is', $judgeResponse, $matches)) {
            $summary = trim($matches[1]);
        }

        // Calculate overall score if not provided
        if (!isset($scores['overall']) && count($scores) > 0) {
            $scores['overall'] = array_sum($scores) / count($scores);
        }

        return [
            'scores' => $scores,
            'summary' => $summary,
            'raw_response' => $judgeResponse,
        ];
    }

    protected function calculatePassCriteria(array $scores): bool
    {
        // Pass if overall score is 3.5 or higher
        return ($scores['overall'] ?? 0) >= 3.5;
    }
}
```

### Creating Test Data

Create a CSV file with test scenarios:

```csv
input,expected_context,scenario_type
"My order is late, what's happening?",Order tracking and status updates,complaint
"Can I return this item?",Return policy and process,policy_question
"I need to change my shipping address",Address modification procedures,change_request
"Your website is broken",Technical issue escalation,technical_issue
"I love this product! Can I buy more?",Positive feedback and upselling,positive_feedback
"I want to cancel my subscription",Cancellation process and retention,cancellation
```

### Running Evaluations

#### Via Artisan Command

```bash
# Run a specific evaluation
php artisan vizra:evaluate customer_support CustomerSupportEvaluation --file=customer_support_data.csv

# Run with specific model for judging
php artisan vizra:evaluate customer_support CustomerSupportEvaluation --judge-model=gpt-4o

# Run and save detailed results
php artisan vizra:evaluate customer_support CustomerSupportEvaluation --output=results.json
```

#### Via Web Interface

Navigate to `/ai-adk/eval` in your Laravel application to use the visual evaluation runner:

1. **Select Evaluation** - Choose from available evaluations
2. **Upload Data** - CSV file with test scenarios
3. **Configure Settings** - Judge model, batch size, etc.
4. **Run & Monitor** - Watch real-time progress
5. **Review Results** - Detailed pass/fail analysis

#### Programmatically

```php
use App\Evaluations\CustomerSupportEvaluation;
use Vizra\VizraSdk\Services\EvaluationRunner;

$evaluation = new CustomerSupportEvaluation();
$runner = new EvaluationRunner();

$results = $runner->run(
    agentName: 'customer_support',
    evaluation: $evaluation,
    testData: $testData,
    judgeModel: 'gpt-4o'
);

// Results include:
// - Overall pass rate
// - Individual test results
// - Score breakdowns
// - Detailed feedback
```

## 📋 Traditional Assertion Testing

For more precise, programmatic testing, create evaluations with specific assertions:

```php
<?php

namespace App\Evaluations;

use Vizra\VizraSdk\Evaluations\BaseEvaluation;

class OrderResponseEvaluation extends BaseEvaluation
{
    protected string $name = 'Order Response Validation';

    public function evaluate(string $input, string $response, array $context = []): array
    {
        $assertions = [];

        // Check if order number is mentioned when provided
        if (isset($context['order_number'])) {
            $assertions['mentions_order_number'] = [
                'pass' => str_contains($response, $context['order_number']),
                'message' => 'Response should mention the order number',
                'expected' => $context['order_number'],
                'actual' => $this->extractOrderNumber($response),
            ];
        }

        // Check response length (not too short, not too long)
        $wordCount = str_word_count($response);
        $assertions['appropriate_length'] = [
            'pass' => $wordCount >= 10 && $wordCount <= 200,
            'message' => 'Response should be between 10 and 200 words',
            'expected' => '10-200 words',
            'actual' => "{$wordCount} words",
        ];

        // Check for professional tone (no caps, no offensive language)
        $assertions['professional_tone'] = [
            'pass' => !$this->hasUnprofessionalLanguage($response),
            'message' => 'Response should maintain professional tone',
            'expected' => 'Professional language',
            'actual' => $this->hasUnprofessionalLanguage($response) ? 'Unprofessional detected' : 'Professional',
        ];

        // Check for helpful elements (suggestions, next steps, contact info)
        $assertions['provides_help'] = [
            'pass' => $this->providesActionableHelp($response),
            'message' => 'Response should provide actionable help or next steps',
            'expected' => 'Actionable help provided',
            'actual' => $this->providesActionableHelp($response) ? 'Help provided' : 'No clear help',
        ];

        // Calculate overall pass rate
        $passedAssertions = array_filter($assertions, fn($a) => $a['pass']);
        $passRate = count($passedAssertions) / count($assertions);

        return [
            'pass' => $passRate >= 0.75, // Pass if 75% of assertions pass
            'pass_rate' => $passRate,
            'assertions' => $assertions,
            'summary' => $this->generateSummary($assertions),
        ];
    }

    private function extractOrderNumber(string $response): ?string
    {
        if (preg_match('/ORD-[A-Z0-9]+/', $response, $matches)) {
            return $matches[0];
        }
        return null;
    }

    private function hasUnprofessionalLanguage(string $response): bool
    {
        $unprofessionalPatterns = [
            '/[A-Z]{3,}/', // Excessive caps
            '/damn|crap|stupid/i', // Inappropriate words
            '/!!{2,}/', // Multiple exclamation marks
        ];

        foreach ($unprofessionalPatterns as $pattern) {
            if (preg_match($pattern, $response)) {
                return true;
            }
        }

        return false;
    }

    private function providesActionableHelp(string $response): bool
    {
        $helpfulPatterns = [
            '/you can|try|suggest|recommend/i',
            '/next step|follow these|here\'s how/i',
            '/contact|call|email|visit/i',
            '/let me|I\'ll help|I can/i',
        ];

        foreach ($helpfulPatterns as $pattern) {
            if (preg_match($pattern, $response)) {
                return true;
            }
        }

        return false;
    }
}
```

## 🎭 Specialized Evaluations

### Creative Content Evaluation

```php
class CreativeWritingEvaluation extends BaseLlmJudgeEvaluation
{
    protected function getJudgePrompt(): string
    {
        return "
        Evaluate this creative writing piece on:

        **Creativity (1-5):** Original ideas, unique perspective
        **Clarity (1-5):** Clear communication and structure
        **Engagement (1-5):** Compelling and interesting content
        **Tone Consistency (1-5):** Maintains appropriate voice throughout

        Consider the target audience and content type when evaluating.
        ";
    }
}
```

### Technical Accuracy Evaluation

````php
class TechnicalAccuracyEvaluation extends BaseEvaluation
{
    public function evaluate(string $input, string $response, array $context = []): array
    {
        $assertions = [];

        // Check for technical terminology usage
        if (isset($context['technical_terms'])) {
            foreach ($context['technical_terms'] as $term) {
                $assertions["uses_term_{$term}"] = [
                    'pass' => str_contains(strtolower($response), strtolower($term)),
                    'message' => "Should use technical term: {$term}",
                ];
            }
        }

        // Check for accurate code examples
        if (str_contains($input, 'code') || str_contains($input, 'example')) {
            $assertions['includes_code_example'] = [
                'pass' => preg_match('/```|\`[^`]+\`/', $response),
                'message' => 'Should include code examples when requested',
            ];
        }

        // Check for security considerations
        if (str_contains($input, 'security') || str_contains($input, 'password')) {
            $assertions['mentions_security'] = [
                'pass' => preg_match('/security|secure|encrypt|hash|protect/i', $response),
                'message' => 'Should address security considerations',
            ];
        }

        return $this->calculateResults($assertions);
    }
}
````

### Conversation Flow Evaluation

```php
class ConversationFlowEvaluation extends BaseLlmJudgeEvaluation
{
    protected function getJudgePrompt(): string
    {
        return "
        Evaluate this conversational response considering the conversation history:

        **Context Awareness (1-5):** Does the response show understanding of previous conversation?
        **Natural Flow (1-5):** Does the response follow naturally from the conversation?
        **Memory Usage (1-5):** Does it appropriately reference or build on previous information?
        **Question Handling (1-5):** Does it address questions or requests appropriately?

        Consider the entire conversation context, not just the individual response.
        ";
    }

    public function preparePrompt(string $input, string $response, array $context = []): string
    {
        $conversationHistory = $context['conversation_history'] ?? [];

        $prompt = "CONVERSATION HISTORY:\n";
        foreach ($conversationHistory as $turn) {
            $prompt .= "User: {$turn['user']}\nAgent: {$turn['agent']}\n\n";
        }

        $prompt .= "CURRENT EXCHANGE:\n";
        $prompt .= "User: {$input}\n";
        $prompt .= "Agent: {$response}\n\n";

        $prompt .= $this->getJudgePrompt();

        return $prompt;
    }
}
```

## 📈 Performance Evaluation

Monitor and evaluate performance metrics:

```php
class PerformanceEvaluation extends BaseEvaluation
{
    public function evaluate(string $input, string $response, array $context = []): array
    {
        $assertions = [];
        $metrics = $context['metrics'] ?? [];

        // Response time evaluation
        if (isset($metrics['response_time'])) {
            $assertions['response_time'] = [
                'pass' => $metrics['response_time'] < 5000, // 5 seconds
                'message' => 'Response should be under 5 seconds',
                'expected' => '< 5000ms',
                'actual' => $metrics['response_time'] . 'ms',
            ];
        }

        // Token usage evaluation
        if (isset($metrics['tokens_used'])) {
            $assertions['token_efficiency'] = [
                'pass' => $metrics['tokens_used'] < 2000,
                'message' => 'Should use fewer than 2000 tokens',
                'expected' => '< 2000 tokens',
                'actual' => $metrics['tokens_used'] . ' tokens',
            ];
        }

        // Tool usage evaluation
        if (isset($metrics['tools_called'])) {
            $toolCallCount = count($metrics['tools_called']);
            $assertions['appropriate_tool_usage'] = [
                'pass' => $toolCallCount <= 3, // Don't call too many tools
                'message' => 'Should use tools efficiently (≤3 calls)',
                'expected' => '≤ 3 tool calls',
                'actual' => $toolCallCount . ' tool calls',
            ];
        }

        return $this->calculateResults($assertions);
    }
}
```

## 🔄 A/B Testing Framework

Compare different agent versions or configurations:

```php
use Vizra\VizraSdk\Services\AbTestRunner;

$abTest = new AbTestRunner();

$results = $abTest->run([
    'control' => [
        'agent' => 'customer_support_v1',
        'config' => ['temperature' => 0.7],
    ],
    'variant' => [
        'agent' => 'customer_support_v2',
        'config' => ['temperature' => 0.3],
    ],
], [
    'evaluation' => CustomerSupportEvaluation::class,
    'test_data' => $testData,
    'sample_size' => 100,
]);

// Results show which version performed better
echo "Control Pass Rate: {$results['control']['pass_rate']}\n";
echo "Variant Pass Rate: {$results['variant']['pass_rate']}\n";
echo "Statistical Significance: {$results['significance']}\n";
```

## 📊 Evaluation Analytics

### Built-in Analytics Dashboard

Navigate to `/ai-adk/analytics` to see:

- **Pass Rate Trends** - How your agents improve over time
- **Score Distributions** - Which criteria agents struggle with
- **Performance Metrics** - Response times, token usage, tool calls
- **Failure Analysis** - Common failure patterns and causes

### Custom Analytics Queries

```php
use Vizra\VizraSdk\Models\EvaluationResult;

// Get pass rates by evaluation type
$pasRates = EvaluationResult::selectRaw('
    evaluation_name,
    AVG(CASE WHEN passed THEN 1 ELSE 0 END) as pass_rate,
    COUNT(*) as total_tests
')
->where('created_at', '>=', now()->subDays(30))
->groupBy('evaluation_name')
->get();

// Get failing tests for analysis
$failures = EvaluationResult::where('passed', false)
->with(['agent', 'evaluation_data'])
->latest()
->take(50)
->get();

// Get performance trends
$trends = EvaluationResult::selectRaw('
    DATE(created_at) as date,
    AVG(score) as avg_score,
    AVG(response_time) as avg_response_time
')
->where('created_at', '>=', now()->subDays(7))
->groupBy('date')
->orderBy('date')
->get();
```

## 🎯 Evaluation Best Practices

### 1. Start Simple, Get Complex

```php
// Start with basic assertions
class BasicEvaluation extends BaseEvaluation
{
    public function evaluate(string $input, string $response, array $context = []): array
    {
        return [
            'pass' => !empty($response) && strlen($response) > 10,
            'message' => 'Response should not be empty and have minimum length',
        ];
    }
}

// Then add LLM judging
class IntermediateEvaluation extends BaseLlmJudgeEvaluation { /* ... */ }

// Finally, add complex multi-criteria evaluation
class AdvancedEvaluation extends BaseLlmJudgeEvaluation { /* ... */ }
```

### 2. Use Diverse Test Data

```csv
# Include various scenarios
input,scenario_type,difficulty,expected_behavior
"Hi there!",greeting,easy,friendly_greeting
"My order #12345 never arrived and I'm very upset!",complaint,hard,empathy_and_resolution
"Can you explain quantum computing in simple terms?",complex_question,medium,clear_explanation
"asdfghjkl",nonsense,easy,clarification_request
"I WANT MY MONEY BACK RIGHT NOW!!!",angry_customer,hard,de_escalation
```

### 3. Regular Evaluation Cadence

```bash
# Set up automated evaluation runs
# In your CI/CD pipeline or scheduled tasks

# Daily smoke tests
php artisan vizra:evaluate customer_support BasicEvaluation --file=smoke_tests.csv

# Weekly comprehensive evaluation
php artisan vizra:evaluate customer_support ComprehensiveEvaluation --file=full_test_suite.csv

# Monthly A/B tests
php artisan vizra:ab-test customer_support_v1 customer_support_v2 --evaluation=CustomerSupportEvaluation
```

### 4. Monitor and Alert

```php
// In your monitoring system
$recentPassRate = EvaluationResult::where('agent_name', 'customer_support')
    ->where('created_at', '>=', now()->subHours(24))
    ->avg('passed');

if ($recentPassRate < 0.8) {
    // Alert! Performance degradation detected
    \Log::alert('Agent performance below threshold', [
        'agent' => 'customer_support',
        'pass_rate' => $recentPassRate,
        'threshold' => 0.8,
    ]);
}
```

## 🚀 Advanced Evaluation Techniques

### Multi-Agent Evaluation

Test how multiple agents work together:

```php
class MultiAgentEvaluation extends BaseEvaluation
{
    public function evaluate(string $input, string $response, array $context = []): array
    {
        // Test handoffs between agents
        $handoffQuality = $this->evaluateHandoff($context['conversation_flow']);

        // Test consistency across agents
        $consistencyScore = $this->evaluateConsistency($context['agent_responses']);

        // Test collaborative problem solving
        $collaborationScore = $this->evaluateCollaboration($context['agent_interactions']);

        return [
            'pass' => ($handoffQuality + $consistencyScore + $collaborationScore) / 3 >= 0.7,
            'scores' => compact('handoffQuality', 'consistencyScore', 'collaborationScore'),
        ];
    }
}
```

### Adversarial Testing

Test edge cases and potential misuse:

```php
class AdversarialEvaluation extends BaseEvaluation
{
    public function evaluate(string $input, string $response, array $context = []): array
    {
        $assertions = [];

        // Test prompt injection resistance
        $assertions['prompt_injection_resistance'] = [
            'pass' => !$this->isPromptInjection($input, $response),
            'message' => 'Should resist prompt injection attempts',
        ];

        // Test inappropriate content filtering
        $assertions['content_filtering'] = [
            'pass' => !$this->hasInappropriateContent($response),
            'message' => 'Should not generate inappropriate content',
        ];

        // Test information leakage
        $assertions['no_info_leakage'] = [
            'pass' => !$this->leaksSystemInfo($response),
            'message' => 'Should not leak system information',
        ];

        return $this->calculateResults($assertions);
    }
}
```

## 🎉 Real-World Examples

Check out these evaluation implementations:

- **[E-commerce Support Evaluation](examples/ecommerce-support-evaluation.md)** - Order handling, returns, complaints
- **[Content Creator Evaluation](examples/content-creator-evaluation.md)** - Writing quality, brand consistency, SEO
- **[Technical Support Evaluation](examples/technical-support-evaluation.md)** - Accuracy, troubleshooting, escalation
- **[Sales Agent Evaluation](examples/sales-agent-evaluation.md)** - Lead qualification, persuasion, objection handling

---

<p align="center">
<strong>Ready to give your agents superpowers with memory?</strong><br>
<a href="vector-memory.md">Next: Vector Memory & RAG →</a>
</p>
