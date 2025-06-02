<?php

namespace AaronLumsden\LaravelAgentADK\Evaluations;

use InvalidArgumentException;

abstract class BaseEvaluation
{
    /**
     * The alias/name of the agent to be used for this evaluation (e.g., 'weather_reporter').
     * This name will be used with Agent::run() or resolved via AgentManager.
     */
    public string $agentName;

    /**
     * Human-readable name for the evaluation.
     * Example: "Product Review Sentiment Analysis"
     */
    public string $name = '';

    /**
     * Brief description of what this evaluation tests.
     */
    public string $description = '';

    /**
     * Path to the CSV file containing test data.
     * Should be relative to the Laravel project's base_path().
     * Example: 'app/Evaluations/data/my_test_data.csv'
     */
    public string $csvPath = '';

    /**
     * Column in the CSV file that contains the primary prompt text or key input.
     * Example: 'user_query' or 'text_input'
     */
    public string $promptCsvColumn = 'prompt';

    /**
     * Stores the results of assertion methods called during an evaluateRow execution.
     */
    protected array $assertionResults = [];

    /**
     * Prepares the prompt to be sent to the LLM based on a row of CSV data.
     * The concrete implementation in the evaluation class should use getPromptCsvColumn()
     * to fetch the main input from $csvRowData and construct the full prompt.
     */
    abstract public function preparePrompt(array $csvRowData): string;

    /**
     * Evaluates a single row of CSV data against the LLM's response.
     * This method should utilize the assertion methods to check various conditions
     * and then compile these assertion results into a final structured array for the row.
     *
     * @param array $csvRowData The data from a single row of the CSV file.
     * @param string $llmResponse The response from the LLM for the prompt generated from $csvRowData.
     * @return array Structured results/metrics for the row.
     */
    abstract public function evaluateRow(array $csvRowData, string $llmResponse): array;

    /**
     * Resets the assertion results. This should be called before processing each new row by the runner.
     */
    protected function resetAssertionResults(): void
    {
        $this->assertionResults = [];
    }

    /**
     * Helper method to add an assertion result to the internal log.
     */
    protected function recordAssertion(string $method, bool $status, string $message, $expected = null, $actual = null): array
    {
        $result = [
            'assertion_method' => $method,
            'status' => $status ? 'pass' : 'fail',
            'message' => $message,
        ];

        if (func_num_args() >= 4) { // If expected was provided
            $result['expected'] = $expected;
        }
        if (func_num_args() >= 5) { // If actual was provided
            $result['actual'] = $actual;
        }

        $this->assertionResults[] = $result;
        return $result;
    }

    // --- Concrete Assertion Methods ---

    protected function assertResponseContains(string $actualResponse, string $expectedSubstring, string $message = 'Response should contain substring.'): array
    {
        $status = strpos($actualResponse, $expectedSubstring) !== false;
        return $this->recordAssertion(static::class . '::' . __FUNCTION__, $status, $message, $expectedSubstring, $actualResponse);
    }

    protected function assertResponseDoesNotContain(string $actualResponse, string $unexpectedSubstring, string $message = 'Response should not contain substring.'): array
    {
        $status = strpos($actualResponse, $unexpectedSubstring) === false;
        return $this->recordAssertion(static::class . '::' . __FUNCTION__, $status, $message, $unexpectedSubstring, $actualResponse);
    }

    protected function assertToolCalled(string $expectedToolName, array $calledTools, string $message = 'Expected tool was not called.'): array
    {
        $status = false;
        foreach ($calledTools as $tool) {
            if (is_string($tool) && $tool === $expectedToolName) {
                $status = true;
                break;
            }
            if (is_object($tool)) {
                if (isset($tool->name) && $tool->name === $expectedToolName) {
                    $status = true;
                    break;
                }
                if (isset($tool->tool_name) && $tool->tool_name === $expectedToolName) {
                    $status = true;
                    break;
                }
            }
        }
        return $this->recordAssertion(static::class . '::' . __FUNCTION__, $status, $message, $expectedToolName, $calledTools);
    }

    protected function assertEquals($expected, $actual, string $message = 'Values should be equal.'): array
    {
        $status = ($expected == $actual); // Using '==' for loose comparison as per original
        return $this->recordAssertion(static::class . '::' . __FUNCTION__, $status, $message, $expected, $actual);
    }

    protected function assertTrue(bool $condition, string $message = 'Condition should be true.'): array
    {
        $status = ($condition === true);
        return $this->recordAssertion(static::class . '::' . __FUNCTION__, $status, $message, true, $condition);
    }

    protected function assertFalse(bool $condition, string $message = 'Condition should be false.'): array
    {
        $status = ($condition === false);
        return $this->recordAssertion(static::class . '::' . __FUNCTION__, $status, $message, false, $condition);
    }
}
