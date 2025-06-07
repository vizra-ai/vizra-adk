<?php

namespace AaronLumsden\LaravelAgentADK\Agents;

use AaronLumsden\LaravelAgentADK\System\AgentContext;

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
     * @param string $agentName
     * @param mixed $params
     * @param array $options
     * @return static
     */
    public function then(string $agentName, mixed $params = null, array $options = []): static
    {
        return $this->addAgent($agentName, $params, $options);
    }

    /**
     * Add the first agent in the sequence
     *
     * @param string $agentName
     * @param mixed $params
     * @param array $options
     * @return static
     */
    public function start(string $agentName, mixed $params = null, array $options = []): static
    {
        return $this->addAgent($agentName, $params, $options);
    }

    /**
     * Add a final step that always runs (like finally in try-catch)
     *
     * @param string $agentName
     * @param mixed $params
     * @param array $options
     * @return static
     */
    public function finally(string $agentName, mixed $params = null, array $options = []): static
    {
        $options['finally'] = true;
        return $this->addAgent($agentName, $params, $options);
    }

    /**
     * Execute agents sequentially
     *
     * @param mixed $input
     * @param AgentContext $context
     * @return mixed
     */
    protected function executeWorkflow(mixed $input, AgentContext $context): mixed
    {
        $currentInput = $input;
        $finalResults = [];
        $finallySteps = [];

        foreach ($this->steps as $step) {
            // Separate finally steps for later execution
            if ($step['options']['finally'] ?? false) {
                $finallySteps[] = $step;
                continue;
            }

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
            'workflow_type' => 'sequential'
        ];
    }

    /**
     * Execute finally steps (cleanup, notifications, etc.)
     *
     * @param array $finallySteps
     * @param mixed $input
     * @param AgentContext $context
     * @return void
     */
    private function executeFinallySteps(array $finallySteps, mixed $input, AgentContext $context): void
    {
        foreach ($finallySteps as $step) {
            try {
                $this->executeStep($step, $input, $context);
            } catch (\Throwable $e) {
                // Log but don't throw - finally steps shouldn't break the main flow
                logger()->warning('Finally step failed: ' . $e->getMessage(), [
                    'agent' => $step['agent'],
                    'error' => $e->getMessage()
                ]);
            }
        }
    }

    /**
     * Add conditional step that only runs if condition is met
     *
     * @param string $agentName
     * @param \Closure $condition
     * @param mixed $params
     * @param array $options
     * @return static
     */
    public function when(string $agentName, \Closure $condition, mixed $params = null, array $options = []): static
    {
        $options['condition'] = $condition;
        return $this->then($agentName, $params, $options);
    }

    /**
     * Add step that only runs if previous step failed
     *
     * @param string $agentName
     * @param mixed $params
     * @param array $options
     * @return static
     */
    public function onError(string $agentName, mixed $params = null, array $options = []): static
    {
        $options['on_error'] = true;
        return $this->then($agentName, $params, $options);
    }

    /**
     * Create a quick sequential workflow from agent names
     *
     * @param string ...$agentNames
     * @return static
     */
    public static function create(string ...$agentNames): static
    {
        $workflow = new static();
        
        foreach ($agentNames as $agentName) {
            $workflow->then($agentName);
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