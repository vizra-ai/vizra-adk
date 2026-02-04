<?php

declare(strict_types=1);

namespace Vizra\VizraADK\Agents\Patterns;

use InvalidArgumentException;
use Throwable;
use Vizra\VizraADK\Agents\BaseLlmAgent;
use Vizra\VizraADK\Agents\Patterns\Data\Plan;
use Vizra\VizraADK\Agents\Patterns\Data\PlanStep;
use Vizra\VizraADK\Agents\Patterns\Data\Reflection;
use Vizra\VizraADK\Exceptions\PlanExecutionException;
use Vizra\VizraADK\Services\Tracer;
use Vizra\VizraADK\System\AgentContext;

/**
 * Abstract planning agent that implements the Plan-Execute-Reflect pattern.
 *
 * This agent type provides a structured approach to complex tasks:
 * 1. Generate a plan with steps and dependencies
 * 2. Execute each step respecting dependencies
 * 3. Reflect on the results
 * 4. Replan if necessary
 *
 * Extend this class and implement the abstract methods to create
 * specialized planning agents for your use case.
 *
 * @example
 * ```php
 * class MyPlanningAgent extends PlanningAgent
 * {
 *     protected string $name = 'my-planning-agent';
 *     protected string $instructions = 'You are a planning assistant.';
 *
 *     protected function executeStep(PlanStep $step, array $previousResults, AgentContext $context): string
 *     {
 *         // Execute the step using tools or LLM calls
 *         return $this->callLlm("Execute: {$step->action}", $context);
 *     }
 *
 *     protected function synthesizeResults(Plan $plan, array $results, AgentContext $context): string
 *     {
 *         return implode("\n", $results);
 *     }
 * }
 * ```
 */
abstract class PlanningAgent extends BaseLlmAgent
{
    /**
     * Maximum number of times to attempt replanning.
     */
    protected int $maxReplanAttempts = 3;

    /**
     * Score threshold for considering a result satisfactory (0-1).
     */
    protected float $satisfactionThreshold = 0.8;

    /**
     * Instructions for the LLM when generating a plan.
     */
    protected string $plannerInstructions = <<<'PROMPT'
You are a planning assistant. Given a task, create a detailed step-by-step plan.

Output your plan as JSON with the following structure:
{
    "goal": "The main objective to achieve",
    "steps": [
        {"id": 1, "action": "Description of what to do", "dependencies": [], "tools": ["tool_name"]},
        {"id": 2, "action": "Next action", "dependencies": [1], "tools": []}
    ],
    "success_criteria": ["Criterion 1", "Criterion 2"]
}

Rules:
- Each step must have a unique numeric ID
- Dependencies are IDs of steps that must complete before this one
- Steps with no dependencies can run first
- Be specific and actionable in step descriptions
- Include relevant tools if known
PROMPT;

    /**
     * Instructions for the LLM when reflecting on results.
     */
    protected string $reflectionInstructions = <<<'PROMPT'
Evaluate the result against the original goal and success criteria.

Output your evaluation as JSON with the following structure:
{
    "satisfactory": true/false,
    "score": 0.0-1.0,
    "strengths": ["What went well"],
    "weaknesses": ["What could be improved"],
    "suggestions": ["Specific improvements for next attempt"]
}

Be objective and thorough in your evaluation.
PROMPT;

    /**
     * Execute the planning agent.
     *
     * This method orchestrates the plan-execute-reflect loop.
     *
     * @param mixed $input The task or goal to accomplish
     * @param AgentContext $context The execution context
     * @return mixed The final result
     */
    public function execute(mixed $input, AgentContext $context): mixed
    {
        // Store context for memory access
        $this->context = $context;
        $context->setState('agent_name', $this->getName());

        /** @var Tracer $tracer */
        $tracer = app(Tracer::class);
        $traceId = $tracer->startTrace($context, $this->getName());

        try {
            // Step 1: Generate initial plan
            $plan = $this->generatePlan($input, $context);
            $context->setState('current_plan', $plan);

            $this->logPlanGenerated($plan, $context);

            $result = null;

            for ($attempt = 0; $attempt < $this->maxReplanAttempts; $attempt++) {
                try {
                    // Step 2: Execute the plan
                    $result = $this->executePlan($plan, $context);

                    // Step 3: Reflect on the result
                    $reflection = $this->reflect($input, $result, $plan, $context);

                    $this->logReflection($reflection, $attempt + 1, $context);

                    // Check if result is satisfactory
                    if ($reflection->satisfactory || $reflection->score >= $this->satisfactionThreshold) {
                        $tracer->endTrace(
                            output: ['response' => $result, 'attempts' => $attempt + 1],
                            status: 'success'
                        );
                        return $result;
                    }

                    // Step 4: Replan based on feedback
                    $plan = $this->replan($input, $result, $reflection, $context);
                    $context->setState('current_plan', $plan);

                    $this->logReplanned($plan, $attempt + 1, $context);

                } catch (PlanExecutionException $e) {
                    $this->logPlanExecutionFailed($e, $attempt + 1, $context);

                    // Replan based on the error
                    $plan = $this->replan($input, null, $e->getMessage(), $context);
                    $context->setState('current_plan', $plan);
                }
            }

            // Return the last result after max attempts
            $tracer->endTrace(
                output: [
                    'response' => $result ?? "Unable to complete task after {$this->maxReplanAttempts} attempts.",
                    'attempts' => $this->maxReplanAttempts,
                    'max_attempts_reached' => true,
                ],
                status: 'success'
            );

            return $result ?? "Unable to complete task after {$this->maxReplanAttempts} attempts.";

        } catch (Throwable $e) {
            $tracer->failTrace($e);
            throw $e;
        }
    }

    /**
     * Generate a plan for the given input.
     *
     * @param mixed $input The task or goal
     * @param AgentContext $context The execution context
     * @return Plan The generated plan
     */
    protected function generatePlan(mixed $input, AgentContext $context): Plan
    {
        $prompt = "Create a plan for: {$input}";

        $response = $this->callLlmForJson($this->plannerInstructions, $prompt, $context);

        return Plan::fromJson($response);
    }

    /**
     * Execute all steps in the plan, respecting dependencies.
     *
     * @param Plan $plan The plan to execute
     * @param AgentContext $context The execution context
     * @return string The synthesized result
     * @throws PlanExecutionException If a step fails
     */
    protected function executePlan(Plan $plan, AgentContext $context): string
    {
        $results = [];

        // Sort steps by ID to ensure consistent execution order
        $steps = $plan->steps;
        usort($steps, fn(PlanStep $a, PlanStep $b) => $a->id <=> $b->id);

        foreach ($steps as $step) {
            // Verify dependencies are satisfied
            $completedIds = array_keys($results);
            if (!$step->areDependenciesSatisfied($completedIds)) {
                $missing = array_diff($step->dependencies, $completedIds);
                throw PlanExecutionException::unsatisfiedDependencies($step, $missing);
            }

            try {
                // Execute the step
                $stepResult = $this->executeStep($step, $results, $context);
                $results[$step->id] = $stepResult;

                // Mark step as completed
                $step->setCompleted(true);
                $step->setResult($stepResult);

                // Store step result in context for potential use by other steps
                $context->setState("step_{$step->id}_result", $stepResult);

            } catch (Throwable $e) {
                throw PlanExecutionException::forStep($step, $e->getMessage(), $e);
            }
        }

        return $this->synthesizeResults($plan, $results, $context);
    }

    /**
     * Reflect on the execution result.
     *
     * @param mixed $input The original input
     * @param string $result The execution result
     * @param Plan $plan The executed plan
     * @param AgentContext $context The execution context
     * @return Reflection The reflection
     */
    protected function reflect(mixed $input, string $result, Plan $plan, AgentContext $context): Reflection
    {
        $prompt = <<<PROMPT
Original Task: {$input}

Plan: {$plan->toJson()}

Result: {$result}

Evaluate the result against the goal and success criteria.
PROMPT;

        $response = $this->callLlmForJson($this->reflectionInstructions, $prompt, $context);

        return Reflection::fromJson($response);
    }

    /**
     * Create a new plan based on feedback from reflection.
     *
     * @param mixed $input The original input
     * @param string|null $previousResult The previous result (if any)
     * @param mixed $feedback The reflection or error message
     * @param AgentContext $context The execution context
     * @return Plan The new plan
     */
    protected function replan(mixed $input, ?string $previousResult, mixed $feedback, AgentContext $context): Plan
    {
        $feedbackText = $feedback instanceof Reflection
            ? "Weaknesses: " . implode(", ", $feedback->weaknesses) .
              "\nSuggestions: " . implode(", ", $feedback->suggestions)
            : (string) $feedback;

        $prompt = <<<PROMPT
Original Task: {$input}

Previous Result: {$previousResult}

Feedback: {$feedbackText}

Create an improved plan that addresses the feedback.
PROMPT;

        $response = $this->callLlmForJson($this->plannerInstructions, $prompt, $context);

        return Plan::fromJson($response);
    }

    /**
     * Call the LLM and expect a JSON response.
     *
     * @param string $systemPrompt The system prompt
     * @param string $userPrompt The user prompt
     * @param AgentContext $context The execution context
     * @return string The JSON response
     */
    protected function callLlmForJson(string $systemPrompt, string $userPrompt, AgentContext $context): string
    {
        // Create a temporary instance with JSON-focused instructions
        $originalInstructions = $this->instructions;
        $this->instructions = $systemPrompt . "\n\nIMPORTANT: Respond only with valid JSON, no additional text.";

        // Add user message to context
        $context->addMessage(['role' => 'user', 'content' => $userPrompt]);

        // Call parent execute to get LLM response
        $response = parent::execute($userPrompt, $context);

        // Restore original instructions
        $this->instructions = $originalInstructions;

        // Handle streaming response
        if ($response instanceof \Generator) {
            $text = '';
            foreach ($response as $chunk) {
                if (is_object($chunk) && property_exists($chunk, 'text')) {
                    $text .= $chunk->text;
                }
            }
            return $this->extractJson($text);
        }

        return $this->extractJson((string) $response);
    }

    /**
     * Extract JSON from a response that may contain additional text.
     *
     * @param string $response The response text
     * @return string The extracted JSON
     */
    protected function extractJson(string $response): string
    {
        // Try to find JSON in the response
        if (preg_match('/\{[\s\S]*\}/', $response, $matches)) {
            return $matches[0];
        }

        // If no JSON found, return the original response
        return $response;
    }

    /**
     * Execute a single step of the plan.
     *
     * Implement this method to define how each step is executed.
     * This could involve calling tools, making LLM requests, or
     * performing any other actions needed.
     *
     * @param PlanStep $step The step to execute
     * @param array<int, string> $previousResults Results from previously completed steps
     * @param AgentContext $context The execution context
     * @return string The result of executing the step
     */
    abstract protected function executeStep(PlanStep $step, array $previousResults, AgentContext $context): string;

    /**
     * Synthesize the results from all steps into a final result.
     *
     * Implement this method to combine step results into the final output.
     *
     * @param Plan $plan The executed plan
     * @param array<int, string> $results Results from all steps keyed by step ID
     * @param AgentContext $context The execution context
     * @return string The final synthesized result
     */
    abstract protected function synthesizeResults(Plan $plan, array $results, AgentContext $context): string;

    /**
     * Get the maximum number of replan attempts.
     */
    public function getMaxReplanAttempts(): int
    {
        return $this->maxReplanAttempts;
    }

    /**
     * Set the maximum number of replan attempts.
     */
    public function setMaxReplanAttempts(int $attempts): static
    {
        $this->maxReplanAttempts = $attempts;
        return $this;
    }

    /**
     * Get the satisfaction threshold.
     */
    public function getSatisfactionThreshold(): float
    {
        return $this->satisfactionThreshold;
    }

    /**
     * Set the satisfaction threshold.
     *
     * @throws InvalidArgumentException If threshold is not between 0 and 1
     */
    public function setSatisfactionThreshold(float $threshold): static
    {
        if ($threshold < 0 || $threshold > 1) {
            throw new InvalidArgumentException('Satisfaction threshold must be between 0 and 1');
        }

        $this->satisfactionThreshold = $threshold;
        return $this;
    }

    /**
     * Get the planner instructions.
     */
    public function getPlannerInstructions(): string
    {
        return $this->plannerInstructions;
    }

    /**
     * Set custom planner instructions.
     */
    public function setPlannerInstructions(string $instructions): static
    {
        $this->plannerInstructions = $instructions;
        return $this;
    }

    /**
     * Get the reflection instructions.
     */
    public function getReflectionInstructions(): string
    {
        return $this->reflectionInstructions;
    }

    /**
     * Set custom reflection instructions.
     */
    public function setReflectionInstructions(string $instructions): static
    {
        $this->reflectionInstructions = $instructions;
        return $this;
    }

    /**
     * Log when a plan is generated.
     */
    protected function logPlanGenerated(Plan $plan, AgentContext $context): void
    {
        logger()->info('[VizraADK:planning] Plan generated', [
            'agent' => $this->getName(),
            'goal' => $plan->goal,
            'step_count' => count($plan->steps),
            'session_id' => $context->getSessionId(),
        ]);
    }

    /**
     * Log reflection results.
     */
    protected function logReflection(Reflection $reflection, int $attempt, AgentContext $context): void
    {
        logger()->info('[VizraADK:planning] Reflection completed', [
            'agent' => $this->getName(),
            'attempt' => $attempt,
            'score' => $reflection->score,
            'satisfactory' => $reflection->satisfactory,
            'session_id' => $context->getSessionId(),
        ]);
    }

    /**
     * Log when replanning occurs.
     */
    protected function logReplanned(Plan $plan, int $attempt, AgentContext $context): void
    {
        logger()->info('[VizraADK:planning] Replanned', [
            'agent' => $this->getName(),
            'attempt' => $attempt,
            'new_goal' => $plan->goal,
            'new_step_count' => count($plan->steps),
            'session_id' => $context->getSessionId(),
        ]);
    }

    /**
     * Log when plan execution fails.
     */
    protected function logPlanExecutionFailed(PlanExecutionException $e, int $attempt, AgentContext $context): void
    {
        logger()->warning('[VizraADK:planning] Plan execution failed', [
            'agent' => $this->getName(),
            'attempt' => $attempt,
            'error' => $e->getMessage(),
            'failed_step' => $e->getFailedStep()?->id,
            'session_id' => $context->getSessionId(),
        ]);
    }
}
