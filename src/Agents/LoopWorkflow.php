<?php

namespace Vizra\VizraADK\Agents;

use Vizra\VizraADK\System\AgentContext;
use Closure;

/**
 * Loop Workflow Agent
 *
 * Repeats agent execution based on conditions, with support for different loop types:
 * - while: continue while condition is true
 * - until: continue until condition is true
 * - forEach: iterate over a collection
 * - times: repeat a specific number of times
 */
class LoopWorkflow extends BaseWorkflowAgent
{
    protected string $name = 'LoopWorkflow';
    protected string $description = 'Repeats agent execution based on conditions';

    protected string $loopType = 'while';
    protected mixed $condition = null;
    protected int $maxIterations = 100;
    protected int $currentIteration = 0;
    protected array $iterationResults = [];
    protected bool $breakOnError = true;
    protected mixed $collection = null;
    protected ?string $currentKey = null;
    protected mixed $currentValue = null;

    /**
     * Continue while condition is true
     *
     * @param string|Closure $condition
     * @return static
     */
    public function while(string|Closure $condition): static
    {
        $this->loopType = 'while';
        $this->condition = $condition;
        return $this;
    }

    /**
     * Continue until condition is true (opposite of while)
     *
     * @param string|Closure $condition
     * @return static
     */
    public function until(string|Closure $condition): static
    {
        $this->loopType = 'until';
        $this->condition = $condition;
        return $this;
    }

    /**
     * Repeat a specific number of times
     *
     * @param int $times
     * @return static
     */
    public function times(int $times): static
    {
        $this->loopType = 'times';
        $this->maxIterations = $times;
        return $this;
    }

    /**
     * Iterate over a collection
     *
     * @param array|\Traversable $collection
     * @return static
     */
    public function forEach(array|\Traversable $collection): static
    {
        $this->loopType = 'forEach';
        $this->collection = $collection;
        $this->maxIterations = is_countable($collection) ? count($collection) : 1000;
        return $this;
    }

    /**
     * Set maximum number of iterations (safety limit)
     *
     * @param int $max
     * @return static
     */
    public function maxIterations(int $max): static
    {
        $this->maxIterations = $max;
        return $this;
    }

    /**
     * Whether to break the loop on agent execution error
     *
     * @param bool $break
     * @return static
     */
    public function breakOnError(bool $break = true): static
    {
        $this->breakOnError = $break;
        return $this;
    }

    /**
     * Continue loop even if agent fails
     *
     * @return static
     */
    public function continueOnError(): static
    {
        return $this->breakOnError(false);
    }

    /**
     * Execute the loop workflow
     *
     * @param mixed $input
     * @param AgentContext $context
     * @return mixed
     */
    protected function executeWorkflow(mixed $input, AgentContext $context): mixed
    {
        $this->currentIteration = 0;
        $this->iterationResults = [];
        $currentInput = $input;

        while ($this->shouldContinue($currentInput, $context)) {
            $this->currentIteration++;

            // Prepare step for execution
            $step = $this->getStepForIteration($currentInput);

            try {
                $result = $this->executeStep($step, $currentInput, $context);

                $this->iterationResults[$this->currentIteration] = [
                    'iteration' => $this->currentIteration,
                    'input' => $currentInput,
                    'result' => $result,
                    'key' => $this->currentKey,
                    'value' => $this->currentValue,
                    'success' => true,
                ];

                // Update input for next iteration
                $currentInput = $this->prepareNextInput($result, $currentInput);

            } catch (\Throwable $e) {
                $this->iterationResults[$this->currentIteration] = [
                    'iteration' => $this->currentIteration,
                    'input' => $currentInput,
                    'error' => $e->getMessage(),
                    'key' => $this->currentKey,
                    'value' => $this->currentValue,
                    'success' => false,
                ];

                if ($this->breakOnError) {
                    throw $e;
                }
            }

            // Safety check to prevent infinite loops
            if ($this->currentIteration >= $this->maxIterations) {
                break;
            }
        }

        return [
            'iterations' => $this->currentIteration,
            'results' => $this->iterationResults,
            'loop_type' => $this->loopType,
            'completed_normally' => $this->didCompleteNormally(),
            'final_input' => $currentInput
        ];
    }

    /**
     * Determine if the loop should continue
     *
     * @param mixed $input
     * @param AgentContext $context
     * @return bool
     */
    private function shouldContinue(mixed $input, AgentContext $context): bool
    {
        // Check max iterations first
        if ($this->currentIteration >= $this->maxIterations) {
            return false;
        }

        switch ($this->loopType) {
            case 'while':
                return $this->evaluateCondition($this->condition, $input, $context);

            case 'until':
                return !$this->evaluateCondition($this->condition, $input, $context);

            case 'times':
                return $this->currentIteration < $this->maxIterations;

            case 'forEach':
                return $this->setupNextForEachIteration();

            default:
                return false;
        }
    }

    /**
     * Setup the next iteration for forEach loop
     *
     * @return bool
     */
    private function setupNextForEachIteration(): bool
    {
        if ($this->collection === null) {
            return false;
        }

        // Convert to array if needed
        $array = is_array($this->collection) ? $this->collection : iterator_to_array($this->collection);

        // Get keys and check if we have more items
        $keys = array_keys($array);

        if ($this->currentIteration >= count($keys)) {
            return false;
        }

        $this->currentKey = $keys[$this->currentIteration];
        $this->currentValue = $array[$this->currentKey];

        return true;
    }

    /**
     * Add an agent to the loop
     *
     * @param string $agentName
     * @param mixed $params
     * @param array $options
     * @return static
     */
    public function agent(string $agentName, mixed $params = null, array $options = []): static
    {
        return $this->addAgent($agentName, $params, $options);
    }

    /**
     * Get the step configuration for current iteration
     *
     * @param mixed $input
     * @return array
     */
    private function getStepForIteration(mixed $input): array
    {
        if (empty($this->steps)) {
            throw new \RuntimeException('No agent specified for loop execution. Use run() to set an agent.');
        }

        $step = $this->steps[0]; // Use first step as the template

        // For forEach loops, modify params to include current item
        if ($this->loopType === 'forEach') {
            $originalParams = $step['params'];

            if ($originalParams instanceof Closure) {
                $step['params'] = fn($input, $results, $context) => $originalParams($this->currentValue, $this->currentKey, $input, $results, $context);
            } else {
                $step['params'] = [
                    'item' => $this->currentValue,
                    'key' => $this->currentKey,
                    'iteration' => $this->currentIteration,
                    'original_input' => $input,
                    'original_params' => $originalParams,
                ];
            }
        }

        return $step;
    }

    /**
     * Prepare input for the next iteration
     *
     * @param mixed $result
     * @param mixed $currentInput
     * @return mixed
     */
    private function prepareNextInput(mixed $result, mixed $currentInput): mixed
    {
        // By default, pass the result as input to next iteration
        // This can be customized by overriding this method
        return $result;
    }

    /**
     * Get results from a specific iteration
     *
     * @param int $iteration
     * @return mixed
     */
    public function getIterationResult(int $iteration): mixed
    {
        return $this->iterationResults[$iteration] ?? null;
    }

    /**
     * Get all successful iteration results
     *
     * @return array
     */
    public function getSuccessfulResults(): array
    {
        return array_filter($this->iterationResults, fn($result) => $result['success']);
    }

    /**
     * Get all failed iteration results
     *
     * @return array
     */
    public function getFailedResults(): array
    {
        return array_filter($this->iterationResults, fn($result) => !$result['success']);
    }

    /**
     * Create a simple while loop
     *
     * @param string $agentName
     * @param string|Closure $condition
     * @param int $maxIterations
     * @return static
     */
    public static function createWhile(string $agentName, string|Closure $condition, int $maxIterations = 100): static
    {
        return (new static())
            ->agent($agentName)
            ->while($condition)
            ->maxIterations($maxIterations);
    }

    /**
     * Create a simple times loop
     *
     * @param string $agentName
     * @param int $times
     * @return static
     */
    public static function createTimes(string $agentName, int $times): static
    {
        return (new static())
            ->agent($agentName)
            ->times($times);
    }

    /**
     * Create a forEach loop
     *
     * @param string $agentName
     * @param array|\Traversable $collection
     * @return static
     */
    public static function createForEach(string $agentName, array|\Traversable $collection): static
    {
        return (new static())
            ->agent($agentName)
            ->forEach($collection);
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
        $context = $context ?: new AgentContext('workflow-' . uniqid());
        return $this->run($input, $context);
    }

    /**
     * Determine if the loop completed normally (not due to hitting max iterations safety limit)
     *
     * @return bool
     */
    private function didCompleteNormally(): bool
    {
        switch ($this->loopType) {
            case 'times':
                // Completed normally if we reached exactly the requested number of iterations
                return $this->currentIteration === $this->maxIterations;

            case 'forEach':
                // Completed normally if we iterated through all collection items
                if ($this->collection === null) {
                    return false;
                }
                $array = is_array($this->collection) ? $this->collection : iterator_to_array($this->collection);
                return $this->currentIteration === count($array);

            case 'while':
            case 'until':
                // For conditional loops, completed normally if we didn't hit the safety limit
                // The loop stopped because the condition became false, not because of max iterations
                return $this->currentIteration < $this->maxIterations;

            default:
                return false;
        }
    }
}
