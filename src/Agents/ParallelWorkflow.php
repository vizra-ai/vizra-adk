<?php

namespace Vizra\VizraSdk\Agents;

use Vizra\VizraSdk\System\AgentContext;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Collection;

/**
 * Parallel Workflow Agent
 *
 * Executes multiple agents simultaneously and collects their results.
 * Can wait for all agents to complete or return as soon as any/some complete.
 */
class ParallelWorkflow extends BaseWorkflowAgent
{
    protected string $name = 'ParallelWorkflow';
    protected string $description = 'Executes agents in parallel and collects results';

    protected bool $waitForAll = true;
    protected int $waitForCount = 0;
    protected bool $failFast = true;
    protected bool $useAsync = false;

    /**
     * Add multiple agents to run in parallel
     *
     * @param array $agents Can be array of names or name => params pairs
     * @param array $options
     * @return static
     */
    public function agents(array|string $agents, mixed $params = null, array $options = []): static
    {
        if (is_string($agents)) {
            // Single agent
            return $this->addAgent($agents, $params, $options);
        }

        // Multiple agents
        foreach ($agents as $key => $value) {
            if (is_numeric($key)) {
                // Array of agent names: ['AgentA', 'AgentB']
                $this->addAgent($value, $params, $options);
            } else {
                // Associative array: ['AgentA' => $params, 'AgentB' => $params]
                $this->addAgent($key, $value, $options);
            }
        }

        return $this;
    }

    /**
     * Wait for all agents to complete (default behavior)
     *
     * @return static
     */
    public function waitForAll(): static
    {
        $this->waitForAll = true;
        $this->waitForCount = 0;
        return $this;
    }

    /**
     * Return as soon as any agent completes
     *
     * @return static
     */
    public function waitForAny(): static
    {
        $this->waitForAll = false;
        $this->waitForCount = 1;
        return $this;
    }

    /**
     * Wait for a specific number of agents to complete
     *
     * @param int $count
     * @return static
     */
    public function waitFor(int $count): static
    {
        $this->waitForAll = false;
        $this->waitForCount = $count;
        return $this;
    }

    /**
     * Fail immediately if any agent fails (default: true)
     *
     * @param bool $failFast
     * @return static
     */
    public function failFast(bool $failFast = true): static
    {
        $this->failFast = $failFast;
        return $this;
    }

    /**
     * Use Laravel's queue system for true async execution
     *
     * @param bool $async
     * @return static
     */
    public function async(bool $async = true): static
    {
        $this->useAsync = $async;
        return $this;
    }

    /**
     * Execute agents in parallel
     *
     * @param mixed $input
     * @param AgentContext $context
     * @return mixed
     */
    protected function executeWorkflow(mixed $input, AgentContext $context): mixed
    {
        if ($this->useAsync) {
            return $this->executeAsync($input, $context);
        }

        return $this->executeSync($input, $context);
    }

    /**
     * Execute agents synchronously (using threading/forking simulation)
     *
     * @param mixed $input
     * @param AgentContext $context
     * @return mixed
     */
    private function executeSync(mixed $input, AgentContext $context): mixed
    {
        $results = [];
        $errors = [];
        $completed = 0;
        $targetCount = $this->waitForAll ? count($this->steps) : $this->waitForCount;

        // Use a simple approach - execute all simultaneously using promises/async simulation
        $promises = [];

        foreach ($this->steps as $index => $step) {
            $promises[$index] = function() use ($step, $input, $context) {
                return $this->executeStep($step, $input, $context);
            };
        }

        // Execute all promises
        foreach ($promises as $index => $promise) {
            try {
                $result = $promise();
                $results[$this->steps[$index]['agent']] = $result;
                $completed++;

                // Check if we've completed enough agents
                if (!$this->waitForAll && $completed >= $targetCount) {
                    break;
                }
            } catch (\Throwable $e) {
                $errors[$this->steps[$index]['agent']] = $e;

                if ($this->failFast) {
                    throw $e;
                }
            }
        }

        // Check if we have enough successful completions
        if ($completed < $targetCount) {
            throw new \RuntimeException(
                "Only {$completed} of {$targetCount} required agents completed successfully. Errors: " .
                implode(', ', array_map(fn($e) => $e->getMessage(), $errors))
            );
        }

        return [
            'results' => $results,
            'errors' => $errors,
            'completed_count' => $completed,
            'total_count' => count($this->steps),
            'workflow_type' => 'parallel'
        ];
    }

    /**
     * Execute agents asynchronously using Laravel queues
     *
     * @param mixed $input
     * @param AgentContext $context
     * @return mixed
     */
    private function executeAsync(mixed $input, AgentContext $context): mixed
    {
        // This would use Laravel's queue system
        // For now, we'll implement a simplified version

        $jobIds = [];
        $sessionId = $context->getSessionId() ?: uniqid('workflow_');

        foreach ($this->steps as $step) {
            $job = new \Vizra\VizraSdk\Jobs\AgentJob(
                $step['agent'],
                $this->prepareStepParams($step['params'], $input, $context),
                'execute', // mode
                $sessionId,
                [] // context
            );

            $jobIds[] = Queue::push($job);
        }

        // Return job tracking information
        return [
            'job_ids' => $jobIds,
            'session_id' => $sessionId,
            'workflow_type' => 'parallel_async',
            'status' => 'queued'
        ];
    }

    /**
     * Create a parallel workflow from multiple agents
     *
     * @param array $agents
     * @return static
     */
    public static function create(array $agents): static
    {
        $workflow = new static();
        $workflow->agents($agents);
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

    /**
     * Get results from completed agents (useful for async workflows)
     *
     * @param string $sessionId
     * @return array
     */
    public static function getAsyncResults(string $sessionId): array
    {
        // This would query the job results from cache/database
        // Implementation depends on how you want to store async results
        return \Illuminate\Support\Facades\Cache::get("workflow_results_{$sessionId}", []);
    }
}
