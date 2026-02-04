<?php

declare(strict_types=1);

namespace Vizra\VizraADK\Agents;

use InvalidArgumentException;
use Throwable;
use Vizra\VizraADK\Exceptions\PlanExecutionException;
use Vizra\VizraADK\Execution\PlanningAgentExecutor;
use Vizra\VizraADK\Planning\Plan;
use Vizra\VizraADK\Planning\PlanStep;
use Vizra\VizraADK\Planning\Reflection;
use Vizra\VizraADK\Planning\PlanningResponse;
use Vizra\VizraADK\Services\Tracer;
use Vizra\VizraADK\System\AgentContext;

/**
 * Abstract base class for planning agents implementing Plan-Execute-Reflect pattern.
 *
 * This provides the core planning infrastructure. Extend this class to create
 * custom planning agents with specialized step execution logic.
 *
 * For a ready-to-use planning agent, see the concrete `PlanningAgent` class.
 *
 * @see PlanningAgent For ready-to-use implementation
 */
abstract class BasePlanningAgent extends BaseLlmAgent
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
     * Create a fluent planning agent executor.
     *
     * Usage: PlanningAgent::plan('Build a REST API')->maxAttempts(5)->go()
     *
     * @param mixed $input The task or goal
     */
    public static function plan(mixed $input): PlanningAgentExecutor
    {
        return new PlanningAgentExecutor(static::class, $input);
    }

    /**
     * Execute the planning agent.
     *
     * This method orchestrates the plan-execute-reflect loop.
     *
     * @param mixed $input The task or goal to accomplish
     * @param AgentContext $context The execution context
     * @return PlanningResponse The response containing plan and result
     */
    public function execute(mixed $input, AgentContext $context): PlanningResponse
    {
        // Store context for memory access
        $this->context = $context;
        $context->setState('agent_name', $this->getName());

        /** @var Tracer $tracer */
        $tracer = app(Tracer::class);
        $traceId = $tracer->startTrace($context, $this->getName());

        $plan = null;
        $result = null;
        $reflection = null;
        $attempts = 0;

        try {
            // Step 1: Generate initial plan
            $plan = $this->generatePlan($input, $context);
            $context->setState('current_plan', $plan);

            $this->logPlanGenerated($plan, $context);

            for ($attempt = 0; $attempt < $this->maxReplanAttempts; $attempt++) {
                $attempts = $attempt + 1;

                try {
                    // Step 2: Execute the plan
                    $result = $this->executePlan($plan, $context);

                    // Step 3: Reflect on the result
                    $reflection = $this->reflect($input, $result, $plan, $context);

                    $this->logReflection($reflection, $attempts, $context);

                    // Check if result is satisfactory
                    if ($reflection->satisfactory || $reflection->score >= $this->satisfactionThreshold) {
                        $tracer->endTrace(
                            output: ['response' => $result, 'attempts' => $attempts],
                            status: 'success'
                        );

                        return new PlanningResponse(
                            result: $result,
                            plan: $plan,
                            reflection: $reflection,
                            attempts: $attempts,
                            success: true,
                            input: $input
                        );
                    }

                    // Step 4: Replan based on feedback
                    $plan = $this->replan($input, $result, $reflection, $context);
                    $context->setState('current_plan', $plan);

                    $this->logReplanned($plan, $attempts, $context);

                } catch (PlanExecutionException $e) {
                    $this->logPlanExecutionFailed($e, $attempts, $context);

                    // Replan based on the error
                    $plan = $this->replan($input, null, $e->getMessage(), $context);
                    $context->setState('current_plan', $plan);
                }
            }

            // Return after max attempts
            $finalResult = $result ?? "Unable to complete task after {$this->maxReplanAttempts} attempts.";

            $tracer->endTrace(
                output: [
                    'response' => $finalResult,
                    'attempts' => $this->maxReplanAttempts,
                    'max_attempts_reached' => true,
                ],
                status: 'success'
            );

            return new PlanningResponse(
                result: $finalResult,
                plan: $plan,
                reflection: $reflection,
                attempts: $this->maxReplanAttempts,
                success: false,
                input: $input
            );

        } catch (Throwable $e) {
            $tracer->failTrace($e);
            throw $e;
        }
    }

    /**
     * Generate a plan for the given input.
     */
    protected function generatePlan(mixed $input, AgentContext $context): Plan
    {
        $prompt = "Create a plan for: {$input}";
        $response = $this->callLlmForJson($this->plannerInstructions, $prompt, $context);

        return Plan::fromJson($response);
    }

    /**
     * Execute all steps in the plan, respecting dependencies.
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

                // Store step result in context
                $context->setState("step_{$step->id}_result", $stepResult);

            } catch (Throwable $e) {
                throw PlanExecutionException::forStep($step, $e->getMessage(), $e);
            }
        }

        return $this->synthesizeResults($plan, $results, $context);
    }

    /**
     * Reflect on the execution result.
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
     * Create a new plan based on feedback.
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
     */
    protected function callLlmForJson(string $systemPrompt, string $userPrompt, AgentContext $context): string
    {
        $originalInstructions = $this->instructions;
        $this->instructions = $systemPrompt . "\n\nIMPORTANT: Respond only with valid JSON, no additional text.";

        $context->addMessage(['role' => 'user', 'content' => $userPrompt]);

        $response = parent::execute($userPrompt, $context);

        $this->instructions = $originalInstructions;

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
     */
    protected function extractJson(string $response): string
    {
        if (preg_match('/\{[\s\S]*\}/', $response, $matches)) {
            return $matches[0];
        }
        return $response;
    }

    /**
     * Execute a single step of the plan.
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
     * @param Plan $plan The executed plan
     * @param array<int, string> $results Results from all steps keyed by step ID
     * @param AgentContext $context The execution context
     * @return string The final synthesized result
     */
    abstract protected function synthesizeResults(Plan $plan, array $results, AgentContext $context): string;

    // Getters and setters

    public function getMaxReplanAttempts(): int
    {
        return $this->maxReplanAttempts;
    }

    public function setMaxReplanAttempts(int $attempts): static
    {
        $this->maxReplanAttempts = $attempts;
        return $this;
    }

    public function getSatisfactionThreshold(): float
    {
        return $this->satisfactionThreshold;
    }

    public function setSatisfactionThreshold(float $threshold): static
    {
        if ($threshold < 0 || $threshold > 1) {
            throw new InvalidArgumentException('Satisfaction threshold must be between 0 and 1');
        }
        $this->satisfactionThreshold = $threshold;
        return $this;
    }

    public function getPlannerInstructions(): string
    {
        return $this->plannerInstructions;
    }

    public function setPlannerInstructions(string $instructions): static
    {
        $this->plannerInstructions = $instructions;
        return $this;
    }

    public function getReflectionInstructions(): string
    {
        return $this->reflectionInstructions;
    }

    public function setReflectionInstructions(string $instructions): static
    {
        $this->reflectionInstructions = $instructions;
        return $this;
    }

    /**
     * Get tool definition for when used as a sub-agent.
     */
    public function toToolDefinition(): array
    {
        return [
            'name' => 'planning_agent',
            'description' => $this->description ?? 'Plans and executes complex multi-step tasks with self-reflection',
            'parameters' => [
                'type' => 'object',
                'properties' => [
                    'task' => [
                        'type' => 'string',
                        'description' => 'The task or goal to plan and execute',
                    ],
                    'max_attempts' => [
                        'type' => 'integer',
                        'description' => 'Maximum planning attempts (default: 3)',
                    ],
                    'threshold' => [
                        'type' => 'number',
                        'description' => 'Satisfaction threshold 0-1 (default: 0.8)',
                    ],
                ],
                'required' => ['task'],
            ],
        ];
    }

    /**
     * Execute from a tool call (sub-agent delegation).
     */
    public function executeFromToolCall(array $arguments, AgentContext $context): string
    {
        if (isset($arguments['max_attempts'])) {
            $this->setMaxReplanAttempts((int) $arguments['max_attempts']);
        }
        if (isset($arguments['threshold'])) {
            $this->setSatisfactionThreshold((float) $arguments['threshold']);
        }

        try {
            $response = $this->execute($arguments['task'], $context);

            return json_encode([
                'success' => $response->isSuccess(),
                'result' => $response->result(),
                'attempts' => $response->attempts(),
                'goal' => $response->plan()?->goal,
                'score' => $response->reflection()?->score,
            ]);
        } catch (\Exception $e) {
            return json_encode([
                'success' => false,
                'error' => 'Planning failed: ' . $e->getMessage(),
            ]);
        }
    }

    // Logging methods

    protected function logPlanGenerated(Plan $plan, AgentContext $context): void
    {
        logger()->info('[VizraADK:planning] Plan generated', [
            'agent' => $this->getName(),
            'goal' => $plan->goal,
            'step_count' => count($plan->steps),
            'session_id' => $context->getSessionId(),
        ]);
    }

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
