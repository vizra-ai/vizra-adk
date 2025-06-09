<?php

namespace Vizra\VizraSdk\Agents;

use Vizra\VizraSdk\System\AgentContext;
use Vizra\VizraSdk\Exceptions\AgentNotFoundException;
use Vizra\VizraSdk\Facades\Agent;
use Closure;
use Illuminate\Support\Collection;

/**
 * Abstract base class for workflow agents that orchestrate other agents
 * without using LLM for the flow control itself.
 */
abstract class BaseWorkflowAgent extends BaseAgent
{
    protected array $steps = [];
    protected array $results = [];
    protected ?Closure $onSuccess = null;
    protected ?Closure $onFailure = null;
    protected ?Closure $onComplete = null;
    protected int $timeout = 300; // 5 minutes default
    protected int $retryAttempts = 0;
    protected int $retryDelay = 1000; // 1 second in milliseconds

    /**
     * Add an agent step to the workflow
     *
     * @param string $agentName
     * @param mixed $params
     * @param array $options
     * @return static
     */
    public function addAgent(string $agentName, mixed $params = null, array $options = []): static
    {
        $this->steps[] = [
            'agent' => $agentName,
            'params' => $params,
            'options' => $options,
            'retries' => $options['retries'] ?? $this->retryAttempts,
            'timeout' => $options['timeout'] ?? $this->timeout,
            'condition' => $options['condition'] ?? null,
        ];

        return $this;
    }

    /**
     * Set success callback
     *
     * @param Closure $callback
     * @return static
     */
    public function onSuccess(Closure $callback): static
    {
        $this->onSuccess = $callback;
        return $this;
    }

    /**
     * Set failure callback
     *
     * @param Closure $callback
     * @return static
     */
    public function onFailure(Closure $callback): static
    {
        $this->onFailure = $callback;
        return $this;
    }

    /**
     * Set completion callback (runs regardless of success/failure)
     *
     * @param Closure $callback
     * @return static
     */
    public function onComplete(Closure $callback): static
    {
        $this->onComplete = $callback;
        return $this;
    }

    /**
     * Set timeout for the entire workflow
     *
     * @param int $seconds
     * @return static
     */
    public function timeout(int $seconds): static
    {
        $this->timeout = $seconds;
        return $this;
    }

    /**
     * Set retry attempts for failed steps
     *
     * @param int $attempts
     * @param int $delayMs
     * @return static
     */
    public function retryOnFailure(int $attempts, int $delayMs = 1000): static
    {
        $this->retryAttempts = $attempts;
        $this->retryDelay = $delayMs;
        return $this;
    }

    /**
     * Execute a single agent step with error handling and retries
     *
     * @param array $step
     * @param mixed $input
     * @param AgentContext $context
     * @return mixed
     * @throws AgentNotFoundException
     */
    protected function executeStep(array $step, mixed $input, AgentContext $context): mixed
    {
        $attempts = 0;
        $maxAttempts = $step['retries'] + 1;
        $lastException = null;

        while ($attempts < $maxAttempts) {
            try {
                // Check if step has a condition
                if (isset($step['condition']) && $step['condition'] && !$this->evaluateCondition($step['condition'], $input, $context)) {
                    return null; // Skip this step
                }

                // Prepare parameters
                $params = $this->prepareStepParams($step['params'] ?? null, $input, $context);

                // Execute the agent
                $result = Agent::run($step['agent'], $params, $context->getSessionId());

                // Store result for future steps
                $this->results[$step['agent']] = $result;

                return $result;

            } catch (\Throwable $e) {
                $lastException = $e;
                $attempts++;

                if ($attempts < $maxAttempts) {
                    usleep($this->retryDelay * 1000); // Convert to microseconds
                }
            }
        }

        throw $lastException;
    }

    /**
     * Prepare step parameters, supporting closures for dynamic params
     *
     * @param mixed $params
     * @param mixed $input
     * @param AgentContext $context
     * @return mixed
     */
    protected function prepareStepParams(mixed $params, mixed $input, AgentContext $context): mixed
    {
        if ($params instanceof Closure) {
            return $params($input, $this->results, $context);
        }

        return $params ?? $input;
    }

    /**
     * Evaluate a condition for conditional execution
     *
     * @param mixed $condition
     * @param mixed $input
     * @param AgentContext $context
     * @return bool
     */
    protected function evaluateCondition(mixed $condition, mixed $input, AgentContext $context): bool
    {
        if ($condition instanceof Closure) {
            return $condition($input, $this->results, $context);
        }

        return (bool) $condition;
    }

    /**
     * Handle workflow completion callbacks
     *
     * @param mixed $result
     * @param bool $success
     * @param \Throwable|null $exception
     * @return void
     */
    protected function handleCompletion(mixed $result, bool $success, ?\Throwable $exception = null): void
    {
        try {
            if ($success && $this->onSuccess) {
                ($this->onSuccess)($result, $this->results);
            }

            if (!$success && $this->onFailure) {
                ($this->onFailure)($exception, $this->results);
            }

            if ($this->onComplete) {
                ($this->onComplete)($result, $success, $this->results);
            }
        } catch (\Throwable $e) {
            // Log callback errors but don't let them affect the main result
            logger()->error('Workflow callback error: ' . $e->getMessage());
        }
    }

    /**
     * Get all step results
     *
     * @return array
     */
    public function getResults(): array
    {
        return $this->results;
    }

    /**
     * Get result from a specific step
     *
     * @param string $agentName
     * @return mixed
     */
    public function getStepResult(string $agentName): mixed
    {
        return $this->results[$agentName] ?? null;
    }

    /**
     * Clear workflow state for reuse
     *
     * @return static
     */
    public function reset(): static
    {
        $this->steps = [];
        $this->results = [];
        return $this;
    }

    /**
     * Abstract method that each workflow type must implement
     *
     * @param mixed $input
     * @param AgentContext $context
     * @return mixed
     */
    abstract protected function executeWorkflow(mixed $input, AgentContext $context): mixed;

    /**
     * Main execution method that orchestrates the workflow
     * Required by BaseAgent interface
     *
     * @param mixed $input
     * @param AgentContext $context
     * @return mixed
     */
    final public function run(mixed $input, AgentContext $context): mixed
    {
        $startTime = microtime(true);
        $result = null;
        $exception = null;
        $success = false;

        try {
            $result = $this->executeWorkflow($input, $context);
            $success = true;
        } catch (\Throwable $e) {
            $exception = $e;
            $result = $e;
        } finally {
            $this->handleCompletion($result, $success, $exception);
        }

        if (!$success) {
            throw $exception;
        }

        return $result;
    }

    /**
     * Execute the workflow with optional context
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
