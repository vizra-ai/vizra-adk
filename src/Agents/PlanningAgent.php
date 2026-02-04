<?php

declare(strict_types=1);

namespace Vizra\VizraADK\Agents;

use Vizra\VizraADK\Planning\Plan;
use Vizra\VizraADK\Planning\PlanStep;
use Vizra\VizraADK\System\AgentContext;
use Vizra\VizraADK\Traits\HasLogging;

/**
 * Ready-to-use planning agent for complex multi-step tasks.
 *
 * Implements the Plan-Execute-Reflect pattern:
 * 1. Generates a structured plan with steps and dependencies
 * 2. Executes each step using LLM
 * 3. Reflects on results and replans if needed
 *
 * Usage:
 * ```php
 * // Simple execution
 * $result = PlanningAgent::plan('Build a REST API for user management')
 *     ->go();
 *
 * // With configuration
 * $result = PlanningAgent::plan('Research quantum computing')
 *     ->maxAttempts(5)
 *     ->threshold(0.9)
 *     ->forUser($user)
 *     ->go();
 *
 * // Queued execution
 * PlanningAgent::plan('Generate comprehensive report')
 *     ->onQueue('planning')
 *     ->then(fn($response) => notify($response->result()))
 *     ->go();
 *
 * // Access results
 * $response = PlanningAgent::plan('Analyze data')->go();
 * echo $response->result();
 * echo $response->plan()->goal;
 * echo $response->reflection()->score;
 * ```
 */
class PlanningAgent extends BasePlanningAgent
{
    use HasLogging;

    protected string $name = 'planning_agent';

    protected string $description = 'Plans and executes complex multi-step tasks with self-reflection and iterative improvement';

    protected string $instructions = <<<'PROMPT'
You are an intelligent planning and execution agent. Your role is to:
1. Break down complex tasks into manageable steps
2. Execute each step thoroughly
3. Reflect on your work and improve if needed

Be thorough, accurate, and focused on delivering high-quality results.
PROMPT;

    protected string $model = 'gpt-4o';

    /**
     * Instructions for executing individual steps.
     */
    protected string $stepExecutionInstructions = <<<'PROMPT'
Execute the following step as part of a larger plan.

Be thorough and provide detailed output. Consider:
- The specific action required
- Any context from previous steps
- How this step contributes to the overall goal

Provide a complete, actionable result for this step.
PROMPT;

    /**
     * Instructions for synthesizing final results.
     */
    protected string $synthesisInstructions = <<<'PROMPT'
Synthesize the results from all completed steps into a coherent final output.

Consider:
- The original goal
- What each step accomplished
- How the pieces fit together
- Key insights and conclusions

Provide a comprehensive, well-organized result.
PROMPT;

    public function __construct()
    {
        parent::__construct();

        // Load defaults from config
        $this->model = config('vizra-adk.planning.model', 'gpt-4o');
        $this->maxReplanAttempts = config('vizra-adk.planning.max_attempts', 3);
        $this->satisfactionThreshold = config('vizra-adk.planning.threshold', 0.8);
    }

    /**
     * Execute a single step using LLM.
     */
    protected function executeStep(PlanStep $step, array $previousResults, AgentContext $context): string
    {
        // Build context from previous steps
        $previousContext = '';
        if (!empty($previousResults)) {
            $previousContext = "\n\nContext from previous steps:\n";
            foreach ($previousResults as $stepId => $result) {
                $previousContext .= "- Step {$stepId}: " . substr($result, 0, 500) . "\n";
            }
        }

        // Build tools context if step specifies tools
        $toolsContext = '';
        if (!empty($step->tools)) {
            $toolsContext = "\n\nAvailable tools for this step: " . implode(', ', $step->tools);
        }

        $prompt = <<<PROMPT
## Step to Execute
{$step->action}

## Step ID
{$step->id}
{$previousContext}
{$toolsContext}

Execute this step thoroughly and provide the result.
PROMPT;

        // Use parent's LLM call mechanism
        $originalInstructions = $this->instructions;
        $this->instructions = $this->stepExecutionInstructions;

        $result = parent::execute($prompt, $context);

        $this->instructions = $originalInstructions;

        // Handle streaming response
        if ($result instanceof \Generator) {
            $text = '';
            foreach ($result as $chunk) {
                if (is_object($chunk) && property_exists($chunk, 'text')) {
                    $text .= $chunk->text;
                }
            }
            return $text;
        }

        return (string) $result;
    }

    /**
     * Synthesize results from all steps into final output.
     */
    protected function synthesizeResults(Plan $plan, array $results, AgentContext $context): string
    {
        // Build step results summary
        $stepSummary = '';
        foreach ($plan->steps as $step) {
            $stepResult = $results[$step->id] ?? 'Not completed';
            $stepSummary .= "### Step {$step->id}: {$step->action}\n";
            $stepSummary .= "{$stepResult}\n\n";
        }

        $prompt = <<<PROMPT
## Original Goal
{$plan->goal}

## Success Criteria
{$this->formatSuccessCriteria($plan->successCriteria)}

## Step Results
{$stepSummary}

Synthesize these results into a comprehensive final output that achieves the goal.
PROMPT;

        // Use parent's LLM call mechanism
        $originalInstructions = $this->instructions;
        $this->instructions = $this->synthesisInstructions;

        $result = parent::execute($prompt, $context);

        $this->instructions = $originalInstructions;

        // Handle streaming response
        if ($result instanceof \Generator) {
            $text = '';
            foreach ($result as $chunk) {
                if (is_object($chunk) && property_exists($chunk, 'text')) {
                    $text .= $chunk->text;
                }
            }
            return $text;
        }

        return (string) $result;
    }

    /**
     * Format success criteria for prompt.
     */
    protected function formatSuccessCriteria(array $criteria): string
    {
        if (empty($criteria)) {
            return 'None specified';
        }
        return implode("\n", array_map(fn($c) => "- {$c}", $criteria));
    }

    /**
     * Set custom step execution instructions.
     */
    public function setStepExecutionInstructions(string $instructions): static
    {
        $this->stepExecutionInstructions = $instructions;
        return $this;
    }

    /**
     * Get step execution instructions.
     */
    public function getStepExecutionInstructions(): string
    {
        return $this->stepExecutionInstructions;
    }

    /**
     * Set custom synthesis instructions.
     */
    public function setSynthesisInstructions(string $instructions): static
    {
        $this->synthesisInstructions = $instructions;
        return $this;
    }

    /**
     * Get synthesis instructions.
     */
    public function getSynthesisInstructions(): string
    {
        return $this->synthesisInstructions;
    }
}
