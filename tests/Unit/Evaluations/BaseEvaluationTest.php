<?php

use AaronLumsden\LaravelAgentADK\Evaluations\BaseEvaluation;

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
}
