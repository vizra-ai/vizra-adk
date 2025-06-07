<?php

namespace AaronLumsden\LaravelAiADK\Agents;

use AaronLumsden\LaravelAiADK\System\AgentContext;
use Closure;

/**
 * Conditional Workflow Agent
 * 
 * Routes execution to different agents based on conditions, similar to if-else logic.
 * Supports complex branching with multiple conditions and fallbacks.
 */
class ConditionalWorkflow extends BaseWorkflowAgent
{
    protected string $name = 'ConditionalWorkflow';
    protected string $description = 'Routes execution to different agents based on conditions';

    protected array $conditions = [];
    protected ?string $defaultAgent = null;
    protected mixed $defaultParams = null;

    /**
     * Add a condition and corresponding agent
     *
     * @param string|Closure $condition
     * @param string $agentName
     * @param mixed $params
     * @param array $options
     * @return static
     */
    public function when(string|Closure $condition, string $agentName, mixed $params = null, array $options = []): static
    {
        $this->conditions[] = [
            'condition' => $condition,
            'agent' => $agentName,
            'params' => $params,
            'options' => $options,
        ];

        return $this;
    }

    /**
     * Add condition using operator-based comparison
     *
     * @param string $key
     * @param string $operator
     * @param mixed $value
     * @param string $agentName
     * @param mixed $params
     * @param array $options
     * @return static
     */
    public function whenEquals(string $key, mixed $value, string $agentName, mixed $params = null, array $options = []): static
    {
        return $this->when(
            fn($input) => $this->getValue($input, $key) === $value,
            $agentName,
            $params,
            $options
        );
    }

    /**
     * Add condition for greater than comparison
     *
     * @param string $key
     * @param mixed $value
     * @param string $agentName
     * @param mixed $params
     * @param array $options
     * @return static
     */
    public function whenGreaterThan(string $key, mixed $value, string $agentName, mixed $params = null, array $options = []): static
    {
        return $this->when(
            fn($input) => $this->getValue($input, $key) > $value,
            $agentName,
            $params,
            $options
        );
    }

    /**
     * Add condition for less than comparison
     *
     * @param string $key
     * @param mixed $value
     * @param string $agentName
     * @param mixed $params
     * @param array $options
     * @return static
     */
    public function whenLessThan(string $key, mixed $value, string $agentName, mixed $params = null, array $options = []): static
    {
        return $this->when(
            fn($input) => $this->getValue($input, $key) < $value,
            $agentName,
            $params,
            $options
        );
    }

    /**
     * Add condition for checking if value exists
     *
     * @param string $key
     * @param string $agentName
     * @param mixed $params
     * @param array $options
     * @return static
     */
    public function whenExists(string $key, string $agentName, mixed $params = null, array $options = []): static
    {
        return $this->when(
            fn($input) => $this->hasValue($input, $key),
            $agentName,
            $params,
            $options
        );
    }

    /**
     * Add condition for checking if value is empty
     *
     * @param string $key
     * @param string $agentName
     * @param mixed $params
     * @param array $options
     * @return static
     */
    public function whenEmpty(string $key, string $agentName, mixed $params = null, array $options = []): static
    {
        return $this->when(
            fn($input) => empty($this->getValue($input, $key)),
            $agentName,
            $params,
            $options
        );
    }

    /**
     * Add condition using regular expression
     *
     * @param string $key
     * @param string $pattern
     * @param string $agentName
     * @param mixed $params
     * @param array $options
     * @return static
     */
    public function whenMatches(string $key, string $pattern, string $agentName, mixed $params = null, array $options = []): static
    {
        return $this->when(
            fn($input) => preg_match($pattern, (string) $this->getValue($input, $key)),
            $agentName,
            $params,
            $options
        );
    }

    /**
     * Set the default/fallback agent if no conditions match
     *
     * @param string $agentName
     * @param mixed $params
     * @param array $options
     * @return static
     */
    public function otherwise(string $agentName, mixed $params = null, array $options = []): static
    {
        $this->defaultAgent = $agentName;
        $this->defaultParams = $params;
        return $this;
    }

    /**
     * Alias for otherwise()
     *
     * @param string $agentName
     * @param mixed $params
     * @param array $options
     * @return static
     */
    public function else(string $agentName, mixed $params = null, array $options = []): static
    {
        return $this->otherwise($agentName, $params, $options);
    }

    /**
     * Execute the conditional workflow
     *
     * @param mixed $input
     * @param AgentContext $context
     * @return mixed
     */
    protected function executeWorkflow(mixed $input, AgentContext $context): mixed
    {
        // Evaluate conditions in order
        foreach ($this->conditions as $condition) {
            if ($this->evaluateCondition($condition['condition'], $input, $context)) {
                $step = [
                    'agent' => $condition['agent'],
                    'params' => $condition['params'],
                    'options' => $condition['options'],
                    'retries' => $condition['options']['retries'] ?? $this->retryAttempts,
                    'timeout' => $condition['options']['timeout'] ?? $this->timeout,
                ];

                $result = $this->executeStep($step, $input, $context);

                return [
                    'result' => $result,
                    'matched_agent' => $condition['agent'],
                    'workflow_type' => 'conditional'
                ];
            }
        }

        // No conditions matched, use default agent
        if ($this->defaultAgent) {
            $step = [
                'agent' => $this->defaultAgent,
                'params' => $this->defaultParams,
                'options' => [],
                'retries' => $this->retryAttempts,
                'timeout' => $this->timeout,
            ];

            $result = $this->executeStep($step, $input, $context);

            return [
                'result' => $result,
                'matched_agent' => $this->defaultAgent,
                'was_default' => true,
                'workflow_type' => 'conditional'
            ];
        }

        throw new \RuntimeException('No conditions matched and no default agent specified');
    }

    /**
     * Get value from input using dot notation
     *
     * @param mixed $input
     * @param string $key
     * @return mixed
     */
    private function getValue(mixed $input, string $key): mixed
    {
        if (is_array($input)) {
            return data_get($input, $key);
        }

        if (is_object($input)) {
            return data_get($input, $key);
        }

        // For scalar values, only support direct access
        return $key === '.' ? $input : null;
    }

    /**
     * Check if value exists in input
     *
     * @param mixed $input
     * @param string $key
     * @return bool
     */
    private function hasValue(mixed $input, string $key): bool
    {
        if (is_array($input)) {
            return data_get($input, $key) !== null;
        }

        if (is_object($input)) {
            return data_get($input, $key) !== null;
        }

        return $key === '.' && $input !== null;
    }

    /**
     * Create a simple conditional workflow
     *
     * @param string|Closure $condition
     * @param string $thenAgent
     * @param string|null $elseAgent
     * @return static
     */
    public static function create(string|Closure $condition, string $thenAgent, ?string $elseAgent = null): static
    {
        $workflow = new static();
        $workflow->when($condition, $thenAgent);
        
        if ($elseAgent) {
            $workflow->otherwise($elseAgent);
        }
        
        return $workflow;
    }

    /**
     * Execute the workflow with simplified syntax
     *
     * @param mixed $input
     * @param AgentContext|null $context
     * @return mixed
     */
    public function execute(mixed $input, ?AgentContext $context = null): mixed
    {
        $context = $context ?: new AgentContext();
        return $this->run($input, $context);
    }
}