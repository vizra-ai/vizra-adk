<?php

namespace Vizra\VizraADK\Agents;

use Vizra\VizraADK\System\AgentContext;

/**
 * Sequential Workflow Agent
 *
 * Executes agents one after another in a predefined order.
 * Each step receives the output from the previous step as input.
 */
class SequentialWorkflow extends BaseWorkflowAgent
{
    protected string $name = 'SequentialWorkflow';

    protected string $description = 'Executes agents sequentially, passing results between steps';

    /**
     * Add the next agent in the sequence
     *
     * @param  string  $agentClass  The agent class name (e.g., DataCollector::class)
     */
    public function then(string $agentClass, mixed $params = null, array $options = []): static
    {
        return $this->addAgent($agentClass, $params, $options);
    }

    /**
     * Add the first agent in the sequence
     *
     * @param  string  $agentClass  The agent class name (e.g., DataCollector::class)
     */
    public function start(string $agentClass, mixed $params = null, array $options = []): static
    {
        return $this->addAgent($agentClass, $params, $options);
    }

    /**
     * Add a final step that always runs (like finally in try-catch)
     *
     * @param  string  $agentClass  The agent class name (e.g., CleanupAgent::class)
     */
    public function finally(string $agentClass, mixed $params = null, array $options = []): static
    {
        $options['finally'] = true;

        return $this->addAgent($agentClass, $params, $options);
    }

    /**
     * Execute agents sequentially
     */
    protected function executeWorkflow(mixed $input, AgentContext $context): mixed
    {
        $currentInput = $input;
        $finalResults = [];
        $finallySteps = [];
        $regularSteps = [];

        // First pass: separate finally steps from regular steps
        foreach ($this->steps as $step) {
            // Separate finally steps for later execution
            if ($step['options']['finally'] ?? false) {
                $finallySteps[] = $step;
            } else {
                $regularSteps[] = $step;
            }
        }

        // Second pass: execute regular steps
        foreach ($regularSteps as $step) {
            try {
                $result = $this->executeStep($step, $currentInput, $context);

                if ($result !== null) {
                    $finalResults[$step['agent']] = $result;
                    $currentInput = $result; // Pass result to next step
                }
            } catch (\Throwable $e) {
                // Execute finally steps before re-throwing
                $this->executeFinallySteps($finallySteps, $currentInput, $context);
                throw $e;
            }
        }

        // Execute finally steps
        $this->executeFinallySteps($finallySteps, $currentInput, $context);

        return [
            'final_result' => $currentInput,
            'step_results' => $finalResults,
            'workflow_type' => 'sequential',
        ];
    }

    /**
     * Execute finally steps (cleanup, notifications, etc.)
     */
    private function executeFinallySteps(array $finallySteps, mixed $input, AgentContext $context): void
    {
        foreach ($finallySteps as $step) {
            try {
                $this->executeStep($step, $input, $context);
            } catch (\Throwable $e) {
                // Log but don't throw - finally steps shouldn't break the main flow
                logger()->warning('Finally step failed: '.$e->getMessage(), [
                    'agent' => $step['agent'],
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Add conditional step that only runs if condition is met
     *
     * @param  string  $agentClass  The agent class name
     */
    public function when(string $agentClass, \Closure $condition, mixed $params = null, array $options = []): static
    {
        $options['condition'] = $condition;

        return $this->then($agentClass, $params, $options);
    }

    /**
     * Add step that only runs if previous step failed
     *
     * @param  string  $agentClass  The agent class name
     */
    public function onError(string $agentClass, mixed $params = null, array $options = []): static
    {
        $options['on_error'] = true;

        return $this->then($agentClass, $params, $options);
    }

    /**
     * Create a quick sequential workflow from agent classes
     *
     * @param  string  ...$agentClasses  Agent class names (e.g., DataCollector::class)
     */
    public static function create(string ...$agentClasses): static
    {
        $workflow = new static;

        foreach ($agentClasses as $agentClass) {
            $workflow->then($agentClass);
        }

        return $workflow;
    }

    /**
     * Execute the workflow with simplified syntax
     */
    public function execute(mixed $input, ?AgentContext $context = null): mixed
    {
        $context = $context ?: new AgentContext('workflow-'.uniqid());

        return $this->run($input, $context);
    }
}
