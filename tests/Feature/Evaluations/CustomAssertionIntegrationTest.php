<?php

namespace Vizra\VizraADK\Tests\Feature\Evaluations;

use InvalidArgumentException;
use Vizra\VizraADK\Evaluations\Assertions\ContainsProductAssertion;
use Vizra\VizraADK\Evaluations\Assertions\PriceFormatAssertion;
use Vizra\VizraADK\Evaluations\BaseEvaluation;
use Vizra\VizraADK\Tests\TestCase;

class CustomAssertionIntegrationTest extends TestCase
{
    public function test_custom_assertion_works_in_evaluation()
    {
        $evaluation = new class extends BaseEvaluation
        {
            public string $agentName = 'test_agent';

            public string $name = 'Test Evaluation';

            public function preparePrompt(array $csvRowData): string
            {
                return $csvRowData['prompt'] ?? '';
            }

            public function evaluateRow(array $csvRowData, string $llmResponse): array
            {
                $this->resetAssertionResults();

                // Test assertWith
                $assertion = new ContainsProductAssertion;
                $this->assertWith($assertion, $llmResponse, 'iPhone');

                // Test assertCustom
                $this->assertCustom(ContainsProductAssertion::class, $llmResponse, 'MacBook');

                $allPassed = collect($this->assertionResults)
                    ->every(fn ($r) => $r['status'] === 'pass');

                return [
                    'assertions' => $this->assertionResults,
                    'final_status' => $allPassed ? 'pass' : 'fail',
                ];
            }
        };

        $result = $evaluation->evaluateRow(
            ['prompt' => 'test'],
            'The new iPhone and MacBook are great products.'
        );

        $this->assertEquals('pass', $result['final_status']);
        $this->assertCount(2, $result['assertions']);
        $this->assertEquals('pass', $result['assertions'][0]['status']);
        $this->assertEquals('pass', $result['assertions'][1]['status']);
    }

    public function test_multiple_assertion_types_in_evaluation()
    {
        $evaluation = new class extends BaseEvaluation
        {
            public string $agentName = 'test_agent';

            public string $name = 'Multi-Assertion Test';

            public function preparePrompt(array $csvRowData): string
            {
                return $csvRowData['prompt'] ?? '';
            }

            public function evaluateRow(array $csvRowData, string $llmResponse): array
            {
                $this->resetAssertionResults();

                // Mix built-in and custom assertions
                $this->assertResponseContains($llmResponse, 'iPhone');

                $priceAssertion = new PriceFormatAssertion;
                $this->assertWith($priceAssertion, $llmResponse, '$');

                $allPassed = collect($this->assertionResults)
                    ->every(fn ($r) => $r['status'] === 'pass');

                return [
                    'assertions' => $this->assertionResults,
                    'final_status' => $allPassed ? 'pass' : 'fail',
                ];
            }
        };

        $result = $evaluation->evaluateRow(
            ['prompt' => 'test'],
            'The iPhone costs $999.99'
        );

        $this->assertEquals('pass', $result['final_status']);
        $this->assertCount(2, $result['assertions']);

        // Check that different assertion methods are recorded
        $methods = array_column($result['assertions'], 'assertion_method');
        $this->assertStringContainsString('assertResponseContains', $methods[0]);
        $this->assertEquals('PriceFormatAssertion', $methods[1]);
    }

    public function test_assert_custom_throws_exception_for_invalid_class()
    {
        $evaluation = new class extends BaseEvaluation
        {
            public string $agentName = 'test_agent';

            public function preparePrompt(array $csvRowData): string
            {
                return '';
            }

            public function evaluateRow(array $csvRowData, string $llmResponse): array
            {
                $this->assertCustom('NonExistentClass', $llmResponse);

                return [];
            }
        };

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Assertion class 'NonExistentClass' not found");

        $evaluation->evaluateRow([], 'test response');
    }

    public function test_assert_custom_throws_exception_for_non_assertion_class()
    {
        $evaluation = new class extends BaseEvaluation
        {
            public string $agentName = 'test_agent';

            public function preparePrompt(array $csvRowData): string
            {
                return '';
            }

            public function evaluateRow(array $csvRowData, string $llmResponse): array
            {
                // Try to use a non-assertion class
                $this->assertCustom(\stdClass::class, $llmResponse);

                return [];
            }
        };

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('must implement AssertionInterface');

        $evaluation->evaluateRow([], 'test response');
    }

    public function test_csv_driven_custom_assertions()
    {
        $evaluation = new class extends BaseEvaluation
        {
            public string $agentName = 'test_agent';

            public string $name = 'CSV-Driven Test';

            public function preparePrompt(array $csvRowData): string
            {
                return $csvRowData['prompt'] ?? '';
            }

            public function evaluateRow(array $csvRowData, string $llmResponse): array
            {
                $this->resetAssertionResults();

                // Use assertion specified in CSV
                if (isset($csvRowData['assertion_class']) && isset($csvRowData['assertion_params'])) {
                    $params = json_decode($csvRowData['assertion_params'], true) ?? [];
                    $this->assertCustom($csvRowData['assertion_class'], $llmResponse, ...$params);
                }

                $allPassed = collect($this->assertionResults)
                    ->every(fn ($r) => $r['status'] === 'pass');

                return [
                    'assertions' => $this->assertionResults,
                    'final_status' => $allPassed ? 'pass' : 'fail',
                ];
            }
        };

        $csvRow = [
            'prompt' => 'test',
            'assertion_class' => ContainsProductAssertion::class,
            'assertion_params' => '["Galaxy S24"]',
        ];

        $result = $evaluation->evaluateRow(
            $csvRow,
            'The Samsung Galaxy S24 is an amazing phone!'
        );

        $this->assertEquals('pass', $result['final_status']);
        $this->assertCount(1, $result['assertions']);
        $this->assertEquals('ContainsProductAssertion', $result['assertions'][0]['assertion_method']);
    }
}
