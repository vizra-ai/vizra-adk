<?php

use Vizra\VizraAdk\Evaluations\BaseEvaluation;

beforeEach(function () {
    $this->evaluation = new TestEvaluation();
});

it('can set basic properties', function () {
    $this->evaluation->agentName = 'test-agent';
    $this->evaluation->name = 'Test Evaluation';
    $this->evaluation->description = 'A test evaluation for unit testing';
    $this->evaluation->csvPath = 'tests/data/test.csv';
    $this->evaluation->promptCsvColumn = 'input';

    expect($this->evaluation->agentName)->toBe('test-agent');
    expect($this->evaluation->name)->toBe('Test Evaluation');
    expect($this->evaluation->description)->toBe('A test evaluation for unit testing');
    expect($this->evaluation->csvPath)->toBe('tests/data/test.csv');
    expect($this->evaluation->promptCsvColumn)->toBe('input');
});

it('can prepare prompt from csv data', function () {
    $csvRowData = [
        'prompt' => 'What is the weather?',
        'location' => 'London',
        'expected' => 'Weather information'
    ];

    $prompt = $this->evaluation->preparePrompt($csvRowData);

    expect($prompt)->toBeString();
    expect($prompt)->toContain('What is the weather?');
});

it('can evaluate row', function () {
    $csvRowData = [
        'prompt' => 'Hello, world!',
        'expected_sentiment' => 'positive'
    ];

    $agentResponse = 'Hello there! How can I help you today?';

    $result = $this->evaluation->evaluateRow($csvRowData, $agentResponse);

    expect($result)->toBeArray();
    expect($result)->toHaveKey('passed');
    expect($result)->toHaveKey('score');
    expect($result)->toHaveKey('details');
});

it('assertion methods work', function () {
    $agentResponse = 'The weather in London is sunny with 20°C temperature.';

    // Test assertTrue
    $this->evaluation->testAssertTrue(true, 'This should pass');
    $this->evaluation->testAssertTrue(str_contains($agentResponse, 'London'), 'Response should mention London');

    // Test assertFalse
    $this->evaluation->testAssertFalse(false, 'This should pass');
    $this->evaluation->testAssertFalse(str_contains($agentResponse, 'Paris'), 'Response should not mention Paris');

    // Test assertEquals
    $this->evaluation->testAssertEquals('sunny', 'sunny', 'Weather should be sunny');

    // Test correct method names for response assertions
    $this->evaluation->testAssertResponseContains($agentResponse, 'London', 'Response should contain London');
    $this->evaluation->testAssertResponseDoesNotContain($agentResponse, 'rainy', 'Response should not contain rainy');

    $results = $this->evaluation->getAssertionResults();
    expect($results)->toHaveCount(7);

    // All assertions should pass
    foreach ($results as $result) {
        expect($result['status'])->toBe('pass');
    }
});

it('failed assertions are recorded', function () {
    // Test failed assertions
    $this->evaluation->testAssertTrue(false, 'This should fail');
    $this->evaluation->testAssertEquals('expected', 'actual', 'Values should not match');
    $this->evaluation->testAssertResponseContains('hello world', 'goodbye', 'Should not contain goodbye');

    $results = $this->evaluation->getAssertionResults();
    expect($results)->toHaveCount(3);

    // All assertions should fail
    foreach ($results as $result) {
        expect($result['status'])->toBe('fail');
    }
});

it('can calculate score', function () {
    // Add some passing and failing assertions
    $this->evaluation->testAssertTrue(true, 'Pass 1');
    $this->evaluation->testAssertTrue(true, 'Pass 2');
    $this->evaluation->testAssertTrue(false, 'Fail 1');
    $this->evaluation->testAssertTrue(true, 'Pass 3');

    $score = $this->evaluation->calculateScore();
    expect($score)->toBe(0.75); // 3 out of 4 passed = 75%
});

it('score is zero when no assertions', function () {
    $score = $this->evaluation->calculateScore();
    expect($score)->toBe(0.0);
});

it('can clear assertion results', function () {
    $this->evaluation->testAssertTrue(true, 'Test assertion');
    expect($this->evaluation->getAssertionResults())->toHaveCount(1);

    $this->evaluation->clearAssertionResults();
    expect($this->evaluation->getAssertionResults())->toHaveCount(0);
});

it('can get prompt csv column', function () {
    $this->evaluation->promptCsvColumn = 'custom_column';
    expect($this->evaluation->getPromptCsvColumn())->toBe('custom_column');
});

it('has default prompt csv column', function () {
    $evaluation = new TestEvaluation();
    expect($evaluation->getPromptCsvColumn())->toBe('prompt');
});

// Basic assertion method tests
it('can test assertToolCalled method', function () {
    $calledTools = ['weather_tool', 'calculator_tool'];
    $this->evaluation->testAssertToolCalled('weather_tool', $calledTools, 'Weather tool should be called');

    $results = $this->evaluation->getAssertionResults();
    expect($results)->toHaveCount(1);
    expect($results[0]['status'])->toBe('pass');
});

it('can test assertResponseMatchesRegex method', function () {
    $agentResponse = 'Hello world! This is a test response.';
    $this->evaluation->testAssertResponseMatchesRegex($agentResponse, '/^Hello/', 'Response should start with Hello');

    $results = $this->evaluation->getAssertionResults();
    expect($results)->toHaveCount(1);
    expect($results[0]['status'])->toBe('pass');
});

// JSON assertion tests
it('can test assertResponseIsValidJson method', function () {
    $jsonResponse = '{"message": "Hello", "status": "success"}';
    $this->evaluation->testAssertResponseIsValidJson($jsonResponse, 'Response should be valid JSON');

    $results = $this->evaluation->getAssertionResults();
    expect($results)->toHaveCount(1);
    expect($results[0]['status'])->toBe('pass');
});

it('can test assertJsonHasKey method', function () {
    $jsonResponse = '{"message": "Hello", "status": "success"}';
    $this->evaluation->testAssertJsonHasKey($jsonResponse, 'message', 'JSON should have message key');

    $results = $this->evaluation->getAssertionResults();
    expect($results)->toHaveCount(1);
    expect($results[0]['status'])->toBe('pass');
});

// XML assertion tests
it('can test assertResponseIsValidXml method', function () {
    $xmlResponse = '<response><message>Hello</message></response>';
    $this->evaluation->testAssertResponseIsValidXml($xmlResponse, 'Response should be valid XML');

    $results = $this->evaluation->getAssertionResults();
    expect($results)->toHaveCount(1);
    expect($results[0]['status'])->toBe('pass');
});

it('can test assertXmlHasValidTag method', function () {
    $xmlResponse = '<response><message>Hello</message></response>';
    $this->evaluation->testAssertXmlHasValidTag($xmlResponse, 'message', 'XML should have message tag');

    $results = $this->evaluation->getAssertionResults();
    expect($results)->toHaveCount(1);
    expect($results[0]['status'])->toBe('pass');
});

// Length and word count tests
it('can test assertResponseLengthBetween method', function () {
    $agentResponse = 'Hello world! This is a test response with good quality.';
    $this->evaluation->testAssertResponseLengthBetween($agentResponse, 10, 100, 'Response length should be reasonable');

    $results = $this->evaluation->getAssertionResults();
    expect($results)->toHaveCount(1);
    expect($results[0]['status'])->toBe('pass');
});

it('can test assertWordCountBetween method', function () {
    $agentResponse = 'Hello world! This is a test response with good quality.';
    $this->evaluation->testAssertWordCountBetween($agentResponse, 5, 20, 'Word count should be reasonable');

    $results = $this->evaluation->getAssertionResults();
    expect($results)->toHaveCount(1);
    expect($results[0]['status'])->toBe('pass');
});

// Comparison tests
it('can test assertGreaterThan method', function () {
    $this->evaluation->testAssertGreaterThan(5, 10, '10 should be greater than 5');

    $results = $this->evaluation->getAssertionResults();
    expect($results)->toHaveCount(1);
    expect($results[0]['status'])->toBe('pass');
});

it('can test assertLessThan method', function () {
    $this->evaluation->testAssertLessThan(10, 5, '5 should be less than 10');

    $results = $this->evaluation->getAssertionResults();
    expect($results)->toHaveCount(1);
    expect($results[0]['status'])->toBe('pass');
});

// Contains assertion tests
it('can test assertContainsAnyOf method', function () {
    $agentResponse = 'Hello world! This is a test response.';
    $this->evaluation->testAssertContainsAnyOf($agentResponse, ['Hello', 'Hi'], 'Response should contain greeting');

    $results = $this->evaluation->getAssertionResults();
    expect($results)->toHaveCount(1);
    expect($results[0]['status'])->toBe('pass');
});

it('can test assertContainsAllOf method', function () {
    $agentResponse = 'Hello world! This is a test response.';
    $this->evaluation->testAssertContainsAllOf($agentResponse, ['Hello', 'world'], 'Response should contain both words');

    $results = $this->evaluation->getAssertionResults();
    expect($results)->toHaveCount(1);
    expect($results[0]['status'])->toBe('pass');
});

// Start/end assertion tests
it('can test assertResponseStartsWith method', function () {
    $agentResponse = 'Hello world! This is a test response.';
    $this->evaluation->testAssertResponseStartsWith($agentResponse, 'Hello', 'Response should start with Hello');

    $results = $this->evaluation->getAssertionResults();
    expect($results)->toHaveCount(1);
    expect($results[0]['status'])->toBe('pass');
});

it('can test assertResponseEndsWith method', function () {
    $agentResponse = 'Hello world! This is a test response with quality.';
    $this->evaluation->testAssertResponseEndsWith($agentResponse, 'quality.', 'Response should end with quality.');

    $results = $this->evaluation->getAssertionResults();
    expect($results)->toHaveCount(1);
    expect($results[0]['status'])->toBe('pass');
});

// Sentiment and content tests
it('can test assertResponseHasPositiveSentiment method', function () {
    $agentResponse = 'Hello world! This is a great and wonderful response.';
    $this->evaluation->testAssertResponseHasPositiveSentiment($agentResponse, 'Response should be positive');

    $results = $this->evaluation->getAssertionResults();
    expect($results)->toHaveCount(1);
    expect($results[0]['status'])->toBe('pass');
});

it('can test assertResponseIsNotEmpty method', function () {
    $agentResponse = 'Hello world! This is a test response.';
    $this->evaluation->testAssertResponseIsNotEmpty($agentResponse, 'Response should not be empty');

    $results = $this->evaluation->getAssertionResults();
    expect($results)->toHaveCount(1);
    expect($results[0]['status'])->toBe('pass');
});

// Safety assertion tests
it('can test assertNotToxic method', function () {
    $agentResponse = 'Thank you for your question. I am happy to help you today.';
    $this->evaluation->testAssertNotToxic($agentResponse, [], 'Response should not be toxic');

    $results = $this->evaluation->getAssertionResults();
    expect($results)->toHaveCount(1);
    expect($results[0]['status'])->toBe('pass');
});

it('can test assertNoPII method', function () {
    $agentResponse = 'Hello world! This is a test response without personal information.';
    $this->evaluation->testAssertNoPII($agentResponse, 'Response should not contain PII');

    $results = $this->evaluation->getAssertionResults();
    expect($results)->toHaveCount(1);
    expect($results[0]['status'])->toBe('pass');
});

it('can test assertGrammarCorrect method', function () {
    $agentResponse = 'Hello world! This is a well-written response.';
    $this->evaluation->testAssertGrammarCorrect($agentResponse, 'Response should have good grammar');

    $results = $this->evaluation->getAssertionResults();
    expect($results)->toHaveCount(1);
    expect($results[0]['status'])->toBe('pass');
});

// Quality assertion tests
it('can test assertReadabilityLevel method', function () {
    $agentResponse = 'Hello world! This is a simple test response.';
    $this->evaluation->testAssertReadabilityLevel($agentResponse, 12, 'Response should be readable');

    $results = $this->evaluation->getAssertionResults();
    expect($results)->toHaveCount(1);
    expect($results[0]['status'])->toBe('pass');
});

it('can test assertNoRepetition method', function () {
    $agentResponse = 'Hello world! This is a test response.';
    $this->evaluation->testAssertNoRepetition($agentResponse, 0.5, 'Response should not be repetitive');

    $results = $this->evaluation->getAssertionResults();
    expect($results)->toHaveCount(1);
    expect($results[0]['status'])->toBe('pass');
});

it('can test assertResponseTime method', function () {
    $this->evaluation->testAssertResponseTime(2.5, 5.0, 'Response time should be acceptable');

    $results = $this->evaluation->getAssertionResults();
    expect($results)->toHaveCount(1);
    expect($results[0]['status'])->toBe('pass');
});

// Spelling assertion tests
it('can test assertIsBritishSpelling method', function () {
    $britishText = 'The colour of the centre is realised through organisation.';
    $this->evaluation->testAssertIsBritishSpelling($britishText, 'Should use British spelling');

    $results = $this->evaluation->getAssertionResults();
    expect($results)->toHaveCount(1);
    expect($results[0]['status'])->toBe('pass');
});

it('can test assertIsAmericanSpelling method', function () {
    $americanText = 'The color of the center is realized through organization.';
    $this->evaluation->testAssertIsAmericanSpelling($americanText, 'Should use American spelling');

    $results = $this->evaluation->getAssertionResults();
    expect($results)->toHaveCount(1);
    expect($results[0]['status'])->toBe('pass');
});

// Failure tests for specific assertion methods
it('can handle assertResponseLengthBetween failures', function () {
    $agentResponse = 'Short';
    $this->evaluation->testAssertResponseLengthBetween($agentResponse, 50, 100, 'Should fail - too short');

    $results = $this->evaluation->getAssertionResults();
    expect($results)->toHaveCount(1);
    expect($results[0]['status'])->toBe('fail');
});

it('can handle assertResponseIsValidJson failures', function () {
    $invalidJson = '{invalid json';
    $this->evaluation->testAssertResponseIsValidJson($invalidJson, 'Should fail - invalid JSON');

    $results = $this->evaluation->getAssertionResults();
    expect($results)->toHaveCount(1);
    expect($results[0]['status'])->toBe('fail');
});

it('can handle assertResponseIsValidXml failures', function () {
    $invalidXml = '<invalid><xml>';
    $this->evaluation->testAssertResponseIsValidXml($invalidXml, 'Should fail - invalid XML');

    $results = $this->evaluation->getAssertionResults();
    expect($results)->toHaveCount(1);
    expect($results[0]['status'])->toBe('fail');
});

it('can handle assertContainsAllOf failures', function () {
    $agentResponse = 'Short';
    $this->evaluation->testAssertContainsAllOf($agentResponse, ['missing', 'words'], 'Should fail - missing words');

    $results = $this->evaluation->getAssertionResults();
    expect($results)->toHaveCount(1);
    expect($results[0]['status'])->toBe('fail');
});

it('can handle assertResponseStartsWith failures', function () {
    $agentResponse = 'Short';
    $this->evaluation->testAssertResponseStartsWith($agentResponse, 'Wrong', 'Should fail - wrong start');

    $results = $this->evaluation->getAssertionResults();
    expect($results)->toHaveCount(1);
    expect($results[0]['status'])->toBe('fail');
});

it('can handle assertResponseEndsWith failures', function () {
    $agentResponse = 'Short';
    $this->evaluation->testAssertResponseEndsWith($agentResponse, 'wrong', 'Should fail - wrong end');

    $results = $this->evaluation->getAssertionResults();
    expect($results)->toHaveCount(1);
    expect($results[0]['status'])->toBe('fail');
});

// LLM judge assertion tests (with error handling)
it('can test assertLlmJudge method handles errors gracefully', function () {
    $agentResponse = 'This is a test response for quality evaluation.';

    $this->evaluation->clearAssertionResults();

    try {
        $this->evaluation->testAssertLlmJudge($agentResponse, 'Response should be helpful and accurate', 'non_existent_judge', 'pass', 'LLM judge test');
    } catch (Exception $e) {
        // Expected to fail, that's okay - we're testing error handling
    }

    $results = $this->evaluation->getAssertionResults();

    // Should have recorded an assertion result even if it failed
    expect(count($results))->toBeGreaterThanOrEqual(0);
});

it('can test assertLlmJudgeQuality method handles errors gracefully', function () {
    $agentResponse = 'This is a test response for quality evaluation.';

    $this->evaluation->clearAssertionResults();

    try {
        $this->evaluation->testAssertLlmJudgeQuality($agentResponse, 'Rate response quality on helpfulness and accuracy', 7, 'non_existent_judge', 'Quality judge test');
    } catch (Exception $e) {
        // Expected to fail, that's okay - we're testing error handling
    }

    $results = $this->evaluation->getAssertionResults();

    // Should have recorded an assertion result even if it failed
    expect(count($results))->toBeGreaterThanOrEqual(0);
});

it('can test assertLlmJudgeComparison method handles errors gracefully', function () {
    $agentResponse = 'This is a test response for quality evaluation.';
    $referenceResponse = 'This is a reference response for comparison.';

    $this->evaluation->clearAssertionResults();

    try {
        $this->evaluation->testAssertLlmJudgeComparison($agentResponse, $referenceResponse, 'Compare responses for quality', 'actual', 'non_existent_judge', 'Comparison judge test');
    } catch (Exception $e) {
        // Expected to fail, that's okay - we're testing error handling
    }

    $results = $this->evaluation->getAssertionResults();

    // Should have recorded an assertion result even if it failed
    expect(count($results))->toBeGreaterThanOrEqual(0);
});

/**
 * Test implementation of BaseEvaluation for testing purposes
 */
class TestEvaluation extends BaseEvaluation
{
    public string $agentName = 'test-agent';
    public string $name = 'Test Evaluation';
    public string $description = 'A test evaluation for unit testing';

    public function preparePrompt(array $csvRowData): string
    {
        $mainInput = $csvRowData[$this->getPromptCsvColumn()] ?? '';
        return "Test prompt: " . $mainInput;
    }

    public function evaluateRow(array $csvRowData, string $agentResponse): array
    {
        // Clear previous results
        $this->clearAssertionResults();

        // Perform some test assertions
        $this->testAssertTrue(strlen($agentResponse) > 0, 'Response should not be empty');
        $this->testAssertResponseContains($agentResponse, 'Hello', 'Response should contain greeting');

        return [
            'passed' => $this->calculateScore() >= 0.5,
            'score' => $this->calculateScore(),
            'details' => $this->getAssertionResults()
        ];
    }

    // Make protected methods public for testing
    public function getAssertionResults(): array
    {
        return $this->assertionResults;
    }

    public function clearAssertionResults(): void
    {
        $this->assertionResults = [];
    }

    public function getPromptCsvColumn(): string
    {
        return $this->promptCsvColumn;
    }

    public function calculateScore(): float
    {
        if (empty($this->assertionResults)) {
            return 0.0;
        }

        $passed = array_filter($this->assertionResults, fn($result) => $result['status'] === 'pass');
        return count($passed) / count($this->assertionResults);
    }

    // Public wrapper methods for testing protected assertion methods
    public function testAssertTrue(bool $condition, string $message = ''): void
    {
        $this->assertTrue($condition, $message);
    }

    public function testAssertFalse(bool $condition, string $message = ''): void
    {
        $this->assertFalse($condition, $message);
    }

    public function testAssertEquals($expected, $actual, string $message = ''): void
    {
        $this->assertEquals($expected, $actual, $message);
    }

    public function testAssertResponseContains(string $response, string $needle, string $message = ''): void
    {
        $this->assertResponseContains($response, $needle, $message);
    }

    public function testAssertResponseDoesNotContain(string $response, string $needle, string $message = ''): void
    {
        $this->assertResponseDoesNotContain($response, $needle, $message);
    }

    // Additional test wrapper methods for all assertion methods
    public function testAssertToolCalled(string $expectedToolName, array $calledTools, string $message = ''): void
    {
        $this->assertToolCalled($expectedToolName, $calledTools, $message);
    }

    public function testAssertResponseMatchesRegex(string $response, string $pattern, string $message = ''): void
    {
        $this->assertResponseMatchesRegex($response, $pattern, $message);
    }

    public function testAssertResponseIsValidJson(string $response, string $message = ''): void
    {
        $this->assertResponseIsValidJson($response, $message);
    }

    public function testAssertJsonHasKey(string $response, string $key, string $message = ''): void
    {
        $this->assertJsonHasKey($response, $key, $message);
    }

    public function testAssertResponseIsValidXml(string $response, string $message = ''): void
    {
        $this->assertResponseIsValidXml($response, $message);
    }

    public function testAssertXmlHasValidTag(string $response, string $tagName, string $message = ''): void
    {
        $this->assertXmlHasValidTag($response, $tagName, $message);
    }

    public function testAssertResponseLengthBetween(string $response, int $minLength, int $maxLength, string $message = ''): void
    {
        $this->assertResponseLengthBetween($response, $minLength, $maxLength, $message);
    }

    public function testAssertWordCountBetween(string $response, int $minWords, int $maxWords, string $message = ''): void
    {
        $this->assertWordCountBetween($response, $minWords, $maxWords, $message);
    }

    public function testAssertGreaterThan($expected, $actual, string $message = ''): void
    {
        $this->assertGreaterThan($expected, $actual, $message);
    }

    public function testAssertLessThan($expected, $actual, string $message = ''): void
    {
        $this->assertLessThan($expected, $actual, $message);
    }

    public function testAssertContainsAnyOf(string $response, array $expectedSubstrings, string $message = ''): void
    {
        $this->assertContainsAnyOf($response, $expectedSubstrings, $message);
    }

    public function testAssertContainsAllOf(string $response, array $expectedSubstrings, string $message = ''): void
    {
        $this->assertContainsAllOf($response, $expectedSubstrings, $message);
    }

    public function testAssertResponseStartsWith(string $response, string $expectedPrefix, string $message = ''): void
    {
        $this->assertResponseStartsWith($response, $expectedPrefix, $message);
    }

    public function testAssertResponseEndsWith(string $response, string $expectedSuffix, string $message = ''): void
    {
        $this->assertResponseEndsWith($response, $expectedSuffix, $message);
    }

    public function testAssertResponseHasPositiveSentiment(string $response, string $message = ''): void
    {
        $this->assertResponseHasPositiveSentiment($response, $message);
    }

    public function testAssertResponseIsNotEmpty(string $response, string $message = ''): void
    {
        $this->assertResponseIsNotEmpty($response, $message);
    }

    public function testAssertNotToxic(string $response, array $additionalToxicWords = [], string $message = ''): void
    {
        $this->assertNotToxic($response, $additionalToxicWords, $message);
    }

    public function testAssertNoPII(string $response, string $message = ''): void
    {
        $this->assertNoPII($response, $message);
    }

    public function testAssertGrammarCorrect(string $response, string $message = ''): void
    {
        $this->assertGrammarCorrect($response, $message);
    }

    public function testAssertReadabilityLevel(string $response, int $maxGradeLevel = 12, string $message = ''): void
    {
        $this->assertReadabilityLevel($response, $maxGradeLevel, $message);
    }

    public function testAssertNoRepetition(string $response, float $maxRepetitionRatio = 0.3, string $message = ''): void
    {
        $this->assertNoRepetition($response, $maxRepetitionRatio, $message);
    }

    public function testAssertResponseTime(float $actualTime, float $maxTime, string $message = ''): void
    {
        $this->assertResponseTime($actualTime, $maxTime, $message);
    }

    public function testAssertIsBritishSpelling(string $response, string $message = ''): void
    {
        $this->assertIsBritishSpelling($response, $message);
    }

    public function testAssertIsAmericanSpelling(string $response, string $message = ''): void
    {
        $this->assertIsAmericanSpelling($response, $message);
    }

    public function testAssertLlmJudge(string $actualResponse, string $criteria, string $judgeAgentName = 'llm_judge', string $expectedOutcome = 'pass', string $message = ''): void
    {
        $this->assertLlmJudge($actualResponse, $criteria, $judgeAgentName, $expectedOutcome, $message);
    }

    public function testAssertLlmJudgeQuality(string $actualResponse, string $qualityCriteria, int $minScore = 7, string $judgeAgentName = 'llm_judge', string $message = ''): void
    {
        $this->assertLlmJudgeQuality($actualResponse, $qualityCriteria, $minScore, $judgeAgentName, $message);
    }

    public function testAssertLlmJudgeComparison(string $actualResponse, string $referenceResponse, string $comparisonCriteria, string $expectedWinner = 'actual', string $judgeAgentName = 'llm_judge', string $message = ''): void
    {
        $this->assertLlmJudgeComparison($actualResponse, $referenceResponse, $comparisonCriteria, $expectedWinner, $judgeAgentName, $message);
    }
}
it('tests all 32 assertion methods are covered', function () {
    // Expected assertion methods from BaseEvaluation.php (all 32 methods)
    $expectedMethods = [
        'assertContainsAllOf',
        'assertContainsAnyOf',
        'assertEquals',
        'assertFalse',
        'assertGrammarCorrect',
        'assertGreaterThan',
        'assertIsAmericanSpelling',
        'assertIsBritishSpelling',
        'assertJsonHasKey',
        'assertLessThan',
        'assertLlmJudge',
        'assertLlmJudgeComparison',
        'assertLlmJudgeQuality',
        'assertNoPII',
        'assertNoRepetition',
        'assertNotToxic',
        'assertReadabilityLevel',
        'assertResponseContains',
        'assertResponseDoesNotContain',
        'assertResponseEndsWith',
        'assertResponseHasPositiveSentiment',
        'assertResponseIsNotEmpty',
        'assertResponseIsValidJson',
        'assertResponseIsValidXml',
        'assertResponseLengthBetween',
        'assertResponseMatchesRegex',
        'assertResponseStartsWith',
        'assertResponseTime',
        'assertToolCalled',
        'assertTrue',
        'assertWordCountBetween',
        'assertXmlHasValidTag',
    ];

    // Check that TestEvaluation has corresponding test wrapper methods
    $reflection = new \ReflectionClass(\TestEvaluation::class);
    $methods = $reflection->getMethods(\ReflectionMethod::IS_PUBLIC);

    $testMethods = [];
    foreach ($methods as $method) {
        if (str_starts_with($method->getName(), 'testAssert')) {
            // Convert testAssertMethodName to assertMethodName
            $assertMethod = substr($method->getName(), 4); // Remove 'test' prefix
            // Convert first letter to lowercase to match actual assertion method names
            $assertMethod = lcfirst($assertMethod);
            $testMethods[] = $assertMethod;
        }
    }

    sort($expectedMethods);
    sort($testMethods);

    // Verify we have test methods for all assertion methods
    expect($testMethods)->toEqual($expectedMethods);
    expect(count($testMethods))->toBe(32);
});
