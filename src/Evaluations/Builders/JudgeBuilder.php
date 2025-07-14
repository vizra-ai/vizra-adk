<?php

namespace Vizra\VizraADK\Evaluations\Builders;

use InvalidArgumentException;
use Vizra\VizraADK\Evaluations\BaseEvaluation;
use Vizra\VizraADK\Facades\Agent;
use Vizra\VizraADK\Services\AgentRegistry;
use Illuminate\Support\Str;

class JudgeBuilder
{
    protected string $response;
    protected BaseEvaluation $evaluation;
    protected ?string $agentClass = null;
    
    public function __construct(string $response, BaseEvaluation $evaluation)
    {
        $this->response = $response;
        $this->evaluation = $evaluation;
    }
    
    /**
     * Specify which judge agent to use
     * 
     * @param string $agentClass The fully qualified class name of the judge agent
     * @return $this
     */
    public function using(string $agentClass): self
    {
        $this->agentClass = $agentClass;
        return $this;
    }
    
    /**
     * Expect a pass/fail judgment
     * 
     * @param string $message Optional custom message for the assertion
     * @return array The assertion result
     */
    public function expectPass(string $message = 'Judge evaluation should pass'): array
    {
        if (!$this->agentClass) {
            throw new InvalidArgumentException('No judge agent specified. Use ->using(AgentClass::class) first.');
        }
        
        $judgePrompt = json_encode([
            'task' => 'evaluate_pass_fail',
            'content' => $this->response
        ]);
        
        try {
            // Get the agent name from the class
            $agentName = $this->getAgentName();
            
            $judgeResponse = Agent::run(
                $agentName,
                $judgePrompt,
                Str::uuid()->toString()
            );
            
            $judgment = $this->parsePassFailJudgment($judgeResponse);
            $status = $judgment['pass'] === true;
            
            return $this->evaluation->recordAssertion(
                'judge()->expectPass',
                $status,
                $message . ' Judge reasoning: ' . $judgment['reasoning'],
                'pass',
                $judgment['pass'] ? 'pass' : 'fail'
            );
            
        } catch (\Exception $e) {
            return $this->evaluation->recordAssertion(
                'judge()->expectPass',
                false,
                'Judge evaluation failed: ' . $e->getMessage(),
                'pass',
                'error'
            );
        }
    }
    
    /**
     * Expect a minimum score or set of scores
     * 
     * @param float|array $minScore Minimum score (0-10) or array of dimension => score pairs
     * @param string $message Optional custom message
     * @return array The assertion result
     */
    public function expectMinimumScore($minScore, string $message = ''): array
    {
        if (!$this->agentClass) {
            throw new InvalidArgumentException('No judge agent specified. Use ->using(AgentClass::class) first.');
        }
        
        try {
            $agentName = $this->getAgentName();
            
            if (is_numeric($minScore)) {
                // Single score evaluation
                return $this->expectSingleScore((float)$minScore, $message ?: 'Quality score should meet minimum threshold');
            } else if (is_array($minScore)) {
                // Multi-dimensional evaluation
                return $this->expectMultipleScores($minScore, $message ?: 'All dimensions should meet minimum thresholds');
            } else {
                throw new InvalidArgumentException('expectMinimumScore accepts either a number or an array of scores');
            }
        } catch (\Exception $e) {
            return $this->evaluation->recordAssertion(
                'judge()->expectMinimumScore',
                false,
                'Judge evaluation failed: ' . $e->getMessage(),
                $minScore,
                'error'
            );
        }
    }
    
    /**
     * Handle single score evaluation
     */
    protected function expectSingleScore(float $minScore, string $message): array
    {
        $judgePrompt = json_encode([
            'task' => 'evaluate_quality',
            'content' => $this->response
        ]);
        
        $agentName = $this->getAgentName();
        $judgeResponse = Agent::run(
            $agentName,
            $judgePrompt,
            Str::uuid()->toString()
        );
        
        $score = $this->parseQualityScore($judgeResponse);
        $status = $score >= $minScore;
        
        return $this->evaluation->recordAssertion(
            'judge()->expectMinimumScore',
            $status,
            $message . " Score: {$score}/{$minScore}",
            ">= {$minScore}",
            $score
        );
    }
    
    /**
     * Handle multi-dimensional score evaluation
     */
    protected function expectMultipleScores(array $minScores, string $message): array
    {
        $dimensions = array_keys($minScores);
        $judgePrompt = json_encode([
            'task' => 'evaluate_dimensions',
            'content' => $this->response,
            'dimensions' => $dimensions
        ]);
        
        $agentName = $this->getAgentName();
        $judgeResponse = Agent::run(
            $agentName,
            $judgePrompt,
            Str::uuid()->toString()
        );
        
        $scores = $this->parseMultiDimensionalScores($judgeResponse);
        
        // Check if all dimensions meet minimum requirements
        $allPass = true;
        $failedDimensions = [];
        
        foreach ($minScores as $dimension => $minScore) {
            if (!isset($scores[$dimension]) || $scores[$dimension] < $minScore) {
                $allPass = false;
                $failedDimensions[] = "{$dimension}: " . ($scores[$dimension] ?? 'N/A') . " < {$minScore}";
            }
        }
        
        $actualScoresStr = json_encode($scores);
        $expectedScoresStr = json_encode($minScores);
        
        if (!$allPass) {
            $message .= ' Failed: ' . implode(', ', $failedDimensions);
        }
        
        return $this->evaluation->recordAssertion(
            'judge()->expectMinimumScore',
            $allPass,
            $message,
            $expectedScoresStr,
            $actualScoresStr
        );
    }
    
    /**
     * Get the agent name from the class
     */
    protected function getAgentName(): string
    {
        // Try to get name directly from class property using reflection
        try {
            $reflection = new \ReflectionClass($this->agentClass);
            
            // Check if it has a name property
            if ($reflection->hasProperty('name')) {
                $nameProperty = $reflection->getProperty('name');
                $nameProperty->setAccessible(true);
                
                // Get default value if it's set
                if ($nameProperty->hasDefaultValue()) {
                    $name = $nameProperty->getDefaultValue();
                    if (!empty($name)) {
                        return $name;
                    }
                }
                
                // Try to read from an instance without constructor
                try {
                    $instance = $reflection->newInstanceWithoutConstructor();
                    $name = $nameProperty->getValue($instance);
                    if (!empty($name)) {
                        return $name;
                    }
                } catch (\Exception $e) {
                    // Continue to next method
                }
            }
        } catch (\Exception $e) {
            // Continue to fallback
        }
        
        // Check the registry as a fallback
        try {
            $registry = app(AgentRegistry::class);
            $registeredAgents = $registry->getAllRegisteredAgents();
            
            foreach ($registeredAgents as $name => $class) {
                if ($class === $this->agentClass) {
                    return $name;
                }
            }
        } catch (\Exception $e) {
            // Continue to final fallback
        }
        
        // Final fallback: derive name from class name
        $className = class_basename($this->agentClass);
        return Str::snake(str_replace('Agent', '', $className));
    }
    
    /**
     * Parse pass/fail judgment from judge response
     */
    protected function parsePassFailJudgment(string $judgeResponse): array
    {
        // Try to extract JSON from the response
        if (preg_match('/\{[^}]*"pass"[^}]*\}/s', $judgeResponse, $matches)) {
            $json = json_decode($matches[0], true);
            if ($json && isset($json['pass'])) {
                return [
                    'pass' => (bool)$json['pass'],
                    'reasoning' => $json['reasoning'] ?? 'No reasoning provided'
                ];
            }
        }
        
        // Fallback parsing
        $response = strtolower($judgeResponse);
        if (strpos($response, '"pass":true') !== false || strpos($response, '"pass": true') !== false) {
            return ['pass' => true, 'reasoning' => 'Extracted from response'];
        }
        
        return ['pass' => false, 'reasoning' => 'Could not parse judgment'];
    }
    
    /**
     * Parse quality score from judge response
     */
    protected function parseQualityScore(string $judgeResponse): float
    {
        // Try to extract JSON score
        if (preg_match('/\{[^}]*"score"[^}]*\}/s', $judgeResponse, $matches)) {
            $json = json_decode($matches[0], true);
            if ($json && isset($json['score']) && is_numeric($json['score'])) {
                return (float)$json['score'];
            }
        }
        
        // Fallback: look for score patterns
        if (preg_match('/"score"\s*:\s*(\d+(?:\.\d+)?)/i', $judgeResponse, $matches)) {
            return (float)$matches[1];
        }
        
        return 0.0; // Default low score if can't parse
    }
    
    /**
     * Parse multi-dimensional scores from judge response
     */
    protected function parseMultiDimensionalScores(string $judgeResponse): array
    {
        // Try to extract JSON with scores object
        if (preg_match('/\{[^}]*"scores"[^}]*\}/s', $judgeResponse, $matches)) {
            // Need to capture the full JSON including nested objects
            $startPos = strpos($judgeResponse, '{');
            if ($startPos !== false) {
                $bracketCount = 0;
                $endPos = $startPos;
                for ($i = $startPos; $i < strlen($judgeResponse); $i++) {
                    if ($judgeResponse[$i] === '{') $bracketCount++;
                    if ($judgeResponse[$i] === '}') $bracketCount--;
                    if ($bracketCount === 0) {
                        $endPos = $i;
                        break;
                    }
                }
                
                $jsonStr = substr($judgeResponse, $startPos, $endPos - $startPos + 1);
                $json = json_decode($jsonStr, true);
                
                if ($json && isset($json['scores']) && is_array($json['scores'])) {
                    // Ensure all scores are numeric
                    $scores = [];
                    foreach ($json['scores'] as $key => $value) {
                        $scores[$key] = is_numeric($value) ? (float)$value : 0.0;
                    }
                    return $scores;
                }
            }
        }
        
        return []; // Return empty array if can't parse
    }
}