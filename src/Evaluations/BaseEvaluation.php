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

    protected function assertResponseMatchesRegex(string $actualResponse, string $pattern, string $message = 'Response should match regex pattern.'): array
    {
        $status = preg_match($pattern, $actualResponse) === 1;
        return $this->recordAssertion(static::class . '::' . __FUNCTION__, $status, $message, $pattern, $actualResponse);
    }

    protected function assertResponseIsValidJson(string $actualResponse, string $message = 'Response should be valid JSON.'): array
    {
        json_decode($actualResponse);
        $status = json_last_error() === JSON_ERROR_NONE;
        return $this->recordAssertion(static::class . '::' . __FUNCTION__, $status, $message, 'valid JSON', $actualResponse);
    }

    protected function assertJsonHasKey(string $actualResponse, string $key, string $message = 'JSON response should contain key.'): array
    {
        $decoded = json_decode($actualResponse, true);
        $status = $decoded !== null && array_key_exists($key, $decoded);
        return $this->recordAssertion(static::class . '::' . __FUNCTION__, $status, $message, $key, $actualResponse);
    }

    protected function assertResponseIsValidXml(string $actualResponse, string $message = 'Response should be valid XML.'): array
    {
        libxml_use_internal_errors(true);
        $doc = simplexml_load_string($actualResponse);
        $status = $doc !== false;
        libxml_clear_errors();
        return $this->recordAssertion(static::class . '::' . __FUNCTION__, $status, $message, 'valid XML', $actualResponse);
    }

    protected function assertXmlHasValidTag(string $actualResponse, string $tagName, string $message = 'XML response should contain valid tag.'): array
    {
        libxml_use_internal_errors(true);
        $doc = simplexml_load_string($actualResponse);

        if ($doc === false) {
            libxml_clear_errors();
            $status = false;
        } else {
            // Check if the tag exists using XPath
            $elements = $doc->xpath("//{$tagName}");
            $status = !empty($elements);
        }

        libxml_clear_errors();
        return $this->recordAssertion(static::class . '::' . __FUNCTION__, $status, $message, $tagName, $actualResponse);
    }

    protected function assertResponseLengthBetween(string $actualResponse, int $minLength, int $maxLength, string $message = 'Response length should be within range.'): array
    {
        $length = strlen($actualResponse);
        $status = $length >= $minLength && $length <= $maxLength;
        return $this->recordAssertion(static::class . '::' . __FUNCTION__, $status, $message, "between {$minLength}-{$maxLength} chars", "{$length} chars");
    }

    protected function assertWordCountBetween(string $actualResponse, int $minWords, int $maxWords, string $message = 'Word count should be within range.'): array
    {
        $wordCount = str_word_count($actualResponse);
        $status = $wordCount >= $minWords && $wordCount <= $maxWords;
        return $this->recordAssertion(static::class . '::' . __FUNCTION__, $status, $message, "between {$minWords}-{$maxWords} words", "{$wordCount} words");
    }

    protected function assertGreaterThan($expected, $actual, string $message = 'Actual value should be greater than expected.'): array
    {
        $status = $actual > $expected;
        return $this->recordAssertion(static::class . '::' . __FUNCTION__, $status, $message, "> {$expected}", $actual);
    }

    protected function assertLessThan($expected, $actual, string $message = 'Actual value should be less than expected.'): array
    {
        $status = $actual < $expected;
        return $this->recordAssertion(static::class . '::' . __FUNCTION__, $status, $message, "< {$expected}", $actual);
    }

    protected function assertContainsAnyOf(string $actualResponse, array $expectedSubstrings, string $message = 'Response should contain at least one of the expected substrings.'): array
    {
        $status = false;
        $foundSubstring = null;
        foreach ($expectedSubstrings as $substring) {
            if (strpos($actualResponse, $substring) !== false) {
                $status = true;
                $foundSubstring = $substring;
                break;
            }
        }
        return $this->recordAssertion(static::class . '::' . __FUNCTION__, $status, $message, $expectedSubstrings, $foundSubstring ?? 'none found');
    }

    protected function assertContainsAllOf(string $actualResponse, array $expectedSubstrings, string $message = 'Response should contain all expected substrings.'): array
    {
        $missingSubstrings = [];
        foreach ($expectedSubstrings as $substring) {
            if (strpos($actualResponse, $substring) === false) {
                $missingSubstrings[] = $substring;
            }
        }
        $status = empty($missingSubstrings);
        return $this->recordAssertion(static::class . '::' . __FUNCTION__, $status, $message, $expectedSubstrings, $missingSubstrings ?: 'all found');
    }

    protected function assertResponseStartsWith(string $actualResponse, string $expectedPrefix, string $message = 'Response should start with expected prefix.'): array
    {
        $status = strpos($actualResponse, $expectedPrefix) === 0;
        return $this->recordAssertion(static::class . '::' . __FUNCTION__, $status, $message, $expectedPrefix, substr($actualResponse, 0, strlen($expectedPrefix)));
    }

    protected function assertResponseEndsWith(string $actualResponse, string $expectedSuffix, string $message = 'Response should end with expected suffix.'): array
    {
        $status = substr($actualResponse, -strlen($expectedSuffix)) === $expectedSuffix;
        return $this->recordAssertion(static::class . '::' . __FUNCTION__, $status, $message, $expectedSuffix, substr($actualResponse, -strlen($expectedSuffix)));
    }

    protected function assertResponseHasPositiveSentiment(string $actualResponse, string $message = 'Response should have positive sentiment.'): array
    {
        $positiveWords = ['good', 'great', 'excellent', 'amazing', 'wonderful', 'fantastic', 'positive', 'happy', 'satisfied', 'pleased'];
        $negativeWords = ['bad', 'terrible', 'awful', 'horrible', 'negative', 'sad', 'disappointed', 'unsatisfied', 'poor', 'worst'];

        $response = strtolower($actualResponse);
        $positiveCount = 0;
        $negativeCount = 0;

        foreach ($positiveWords as $word) {
            $positiveCount += substr_count($response, $word);
        }
        foreach ($negativeWords as $word) {
            $negativeCount += substr_count($response, $word);
        }

        $status = $positiveCount > $negativeCount;
        return $this->recordAssertion(static::class . '::' . __FUNCTION__, $status, $message, 'positive sentiment', "positive: {$positiveCount}, negative: {$negativeCount}");
    }

    protected function assertResponseIsNotEmpty(string $actualResponse, string $message = 'Response should not be empty.'): array
    {
        $status = !empty(trim($actualResponse));
        return $this->recordAssertion(static::class . '::' . __FUNCTION__, $status, $message, 'non-empty response', $actualResponse ?: 'empty');
    }

    /**
     * Uses an LLM agent as a judge to evaluate the response based on custom criteria.
     */
    protected function assertLlmJudge(
        string $actualResponse,
        string $criteria,
        string $judgeAgentName = 'llm_judge',
        string $expectedOutcome = 'pass',
        string $message = 'LLM judge evaluation failed.'
    ): array {
        $judgePrompt = $this->buildJudgePrompt($actualResponse, $criteria);

        try {
            $judgeResponse = \AaronLumsden\LaravelAgentADK\Facades\Agent::run(
                $judgeAgentName,
                $judgePrompt,
                \Illuminate\Support\Str::uuid()->toString()
            );

            $judgment = $this->parseJudgment($judgeResponse);
            $status = $judgment['outcome'] === $expectedOutcome;

            return $this->recordAssertion(
                static::class . '::' . __FUNCTION__,
                $status,
                $message . " Judge reasoning: " . $judgment['reasoning'],
                $expectedOutcome,
                $judgment['outcome']
            );

        } catch (\Exception $e) {
            return $this->recordAssertion(
                static::class . '::' . __FUNCTION__,
                false,
                "LLM judge failed: " . $e->getMessage(),
                $expectedOutcome,
                'error'
            );
        }
    }

    /**
     * Uses LLM judge to evaluate quality on a scale (e.g., 1-10).
     */
    protected function assertLlmJudgeQuality(
        string $actualResponse,
        string $qualityCriteria,
        int $minScore = 7,
        string $judgeAgentName = 'llm_judge',
        string $message = 'Response quality below threshold.'
    ): array {
        $judgePrompt = $this->buildQualityJudgePrompt($actualResponse, $qualityCriteria);

        try {
            $judgeResponse = \AaronLumsden\LaravelAgentADK\Facades\Agent::run(
                $judgeAgentName,
                $judgePrompt,
                \Illuminate\Support\Str::uuid()->toString()
            );

            $score = $this->parseQualityScore($judgeResponse);
            $status = $score >= $minScore;

            return $this->recordAssertion(
                static::class . '::' . __FUNCTION__,
                $status,
                $message . " Score: {$score}/{$minScore}",
                ">= {$minScore}",
                $score
            );

        } catch (\Exception $e) {
            return $this->recordAssertion(
                static::class . '::' . __FUNCTION__,
                false,
                "LLM quality judge failed: " . $e->getMessage(),
                ">= {$minScore}",
                'error'
            );
        }
    }

    /**
     * Uses LLM judge to compare two responses and determine which is better.
     */
    protected function assertLlmJudgeComparison(
        string $actualResponse,
        string $referenceResponse,
        string $comparisonCriteria,
        string $expectedWinner = 'actual',
        string $judgeAgentName = 'llm_judge',
        string $message = 'Response comparison failed.'
    ): array {
        $judgePrompt = $this->buildComparisonJudgePrompt($actualResponse, $referenceResponse, $comparisonCriteria);

        try {
            $judgeResponse = \AaronLumsden\LaravelAgentADK\Facades\Agent::run(
                $judgeAgentName,
                $judgePrompt,
                \Illuminate\Support\Str::uuid()->toString()
            );

            $comparison = $this->parseComparison($judgeResponse);
            $status = $comparison['winner'] === $expectedWinner;

            return $this->recordAssertion(
                static::class . '::' . __FUNCTION__,
                $status,
                $message . " Judge reasoning: " . $comparison['reasoning'],
                $expectedWinner,
                $comparison['winner']
            );

        } catch (\Exception $e) {
            return $this->recordAssertion(
                static::class . '::' . __FUNCTION__,
                false,
                "LLM comparison judge failed: " . $e->getMessage(),
                $expectedWinner,
                'error'
            );
        }
    }

    /**
     * Builds the prompt for LLM judge evaluation.
     */
    private function buildJudgePrompt(string $response, string $criteria): string
    {
        return "You are an expert evaluator. Your task is to judge the following response based on the given criteria.

RESPONSE TO EVALUATE:
{$response}

EVALUATION CRITERIA:
{$criteria}

Please provide your judgment in the following JSON format:
{
    \"outcome\": \"pass\" or \"fail\",
    \"reasoning\": \"Brief explanation of your decision\",
    \"confidence\": \"High, Medium, or Low\"
}

Be objective and thorough in your evaluation.";
    }

    /**
     * Builds the prompt for quality scoring.
     */
    private function buildQualityJudgePrompt(string $response, string $criteria): string
    {
        return "You are an expert evaluator. Rate the quality of the following response on a scale of 1-10 based on the given criteria.

RESPONSE TO EVALUATE:
{$response}

QUALITY CRITERIA:
{$criteria}

Please provide your assessment in the following JSON format:
{
    \"score\": 8,
    \"reasoning\": \"Explanation of the score\",
    \"strengths\": [\"List of strengths\"],
    \"weaknesses\": [\"List of weaknesses\"]
}

Be objective and consistent in your scoring.";
    }

    /**
     * Builds the prompt for comparing two responses.
     */
    private function buildComparisonJudgePrompt(string $actualResponse, string $referenceResponse, string $criteria): string
    {
        return "You are an expert evaluator. Compare the following two responses based on the given criteria and determine which is better.

RESPONSE A (ACTUAL):
{$actualResponse}

RESPONSE B (REFERENCE):
{$referenceResponse}

COMPARISON CRITERIA:
{$criteria}

Please provide your comparison in the following JSON format:
{
    \"winner\": \"actual\" or \"reference\",
    \"reasoning\": \"Detailed explanation of your decision\",
    \"strengths_actual\": [\"Strengths of Response A\"],
    \"strengths_reference\": [\"Strengths of Response B\"],
    \"overall_assessment\": \"Brief summary\"
}

Be objective and thorough in your comparison.";
    }

    /**
     * Parses the LLM judge response for pass/fail judgment.
     */
    private function parseJudgment(string $judgeResponse): array
    {
        // Try to extract JSON from the response
        if (preg_match('/\{[^}]*"outcome"[^}]*\}/s', $judgeResponse, $matches)) {
            $json = json_decode($matches[0], true);
            if ($json && isset($json['outcome'])) {
                return [
                    'outcome' => strtolower($json['outcome']),
                    'reasoning' => $json['reasoning'] ?? 'No reasoning provided',
                    'confidence' => $json['confidence'] ?? 'Unknown'
                ];
            }
        }

        // Fallback parsing
        $response = strtolower($judgeResponse);
        if (strpos($response, 'pass') !== false && strpos($response, 'fail') === false) {
            return ['outcome' => 'pass', 'reasoning' => 'Extracted from text'];
        }

        return ['outcome' => 'fail', 'reasoning' => 'Could not parse judgment'];
    }

    /**
     * Parses the quality score from LLM judge response.
     */
    private function parseQualityScore(string $judgeResponse): int
    {
        // Try to extract JSON score
        if (preg_match('/\{[^}]*"score"[^}]*\}/s', $judgeResponse, $matches)) {
            $json = json_decode($matches[0], true);
            if ($json && isset($json['score']) && is_numeric($json['score'])) {
                return (int) $json['score'];
            }
        }

        // Fallback: look for score patterns
        if (preg_match('/(?:score|rating).*?(\d+)(?:\/10|out of 10)?/i', $judgeResponse, $matches)) {
            return (int) $matches[1];
        }

        return 0; // Default low score if can't parse
    }

    /**
     * Parses the comparison result from LLM judge response.
     */
    private function parseComparison(string $judgeResponse): array
    {
        // Try to extract JSON from the response
        if (preg_match('/\{[^}]*"winner"[^}]*\}/s', $judgeResponse, $matches)) {
            $json = json_decode($matches[0], true);
            if ($json && isset($json['winner'])) {
                return [
                    'winner' => strtolower($json['winner']),
                    'reasoning' => $json['reasoning'] ?? 'No reasoning provided',
                    'strengths_actual' => $json['strengths_actual'] ?? [],
                    'strengths_reference' => $json['strengths_reference'] ?? []
                ];
            }
        }

        // Fallback parsing
        $response = strtolower($judgeResponse);
        if (strpos($response, 'response a') !== false || strpos($response, 'actual') !== false) {
            return ['winner' => 'actual', 'reasoning' => 'Extracted from text'];
        } elseif (strpos($response, 'response b') !== false || strpos($response, 'reference') !== false) {
            return ['winner' => 'reference', 'reasoning' => 'Extracted from text'];
        }

        return ['winner' => 'reference', 'reasoning' => 'Could not parse comparison'];
    }

    /**
     * Helper method to get the configured prompt CSV column name.
     * Useful for evaluation classes that need to reference this column.
     */
    public function getPromptCsvColumn(): string
    {
        return $this->promptCsvColumn;
    }
}
