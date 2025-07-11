<?php

namespace Vizra\VizraADK\Agents;

use Illuminate\Support\Facades\Queue;
use Vizra\VizraADK\System\AgentContext;

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
     * @param  array  $agents  Can be array of names or name => params pairs
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
     */
    public function waitForAll(): static
    {
        $this->waitForAll = true;
        $this->waitForCount = 0;

        return $this;
    }

    /**
     * Return as soon as any agent completes
     */
    public function waitForAny(): static
    {
        $this->waitForAll = false;
        $this->waitForCount = 1;

        return $this;
    }

    /**
     * Wait for a specific number of agents to complete
     */
    public function waitFor(int $count): static
    {
        $this->waitForAll = false;
        $this->waitForCount = $count;

        return $this;
    }

    /**
     * Fail immediately if any agent fails (default: true)
     */
    public function failFast(bool $failFast = true): static
    {
        $this->failFast = $failFast;

        return $this;
    }

    /**
     * Use Laravel's queue system for true async execution
     */
    public function async(bool $async = true): static
    {
        $this->useAsync = $async;

        return $this;
    }

    /**
     * Execute agents in parallel
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
            $promises[$index] = function () use ($step, $input, $context) {
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
                if (! $this->waitForAll && $completed >= $targetCount) {
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
                "Only {$completed} of {$targetCount} required agents completed successfully. Errors: ".
                implode(', ', array_map(fn ($e) => $e->getMessage(), $errors))
            );
        }

        return [
            'results' => $results,
            'errors' => $errors,
            'completed_count' => $completed,
            'total_count' => count($this->steps),
            'workflow_type' => 'parallel',
        ];
    }

    /**
     * Execute agents asynchronously using Laravel queues
     */
    private function executeAsync(mixed $input, AgentContext $context): mixed
    {
        // This would use Laravel's queue system
        // For now, we'll implement a simplified version

        $jobIds = [];
        $sessionId = $context->getSessionId() ?: uniqid('workflow_');

        foreach ($this->steps as $step) {
            $job = new \Vizra\VizraADK\Jobs\AgentJob(
                $step['agent'],
                $this->prepareStepParams($step['params'], $input, $context),
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
            'status' => 'queued',
        ];
    }

    /**
     * Create a parallel workflow from multiple agents
     */
    public static function create(array $agents): static
    {
        $workflow = new static;
        $workflow->agents($agents);

        return $workflow;
    }

    /**
     * Get results from completed agents (useful for async workflows)
     */
    public static function getAsyncResults(string $sessionId): array
    {
        // This would query the job results from cache/database
        // Implementation depends on how you want to store async results
        return \Illuminate\Support\Facades\Cache::get("workflow_results_{$sessionId}", []);
    }
}
