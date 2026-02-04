<?php

declare(strict_types=1);

namespace Vizra\VizraADK\Execution;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Vizra\VizraADK\Agents\BasePlanningAgent;
use Vizra\VizraADK\Jobs\PlanningAgentJob;
use Vizra\VizraADK\Planning\PlanningResponse;
use Vizra\VizraADK\Services\Tracer;
use Vizra\VizraADK\System\AgentContext;

/**
 * Fluent executor for planning agents.
 *
 * Usage:
 * ```php
 * PlanningAgent::run('Build a REST API')
 *     ->maxAttempts(5)
 *     ->threshold(0.9)
 *     ->forUser($user)
 *     ->onQueue('planning')
 *     ->then(fn($response) => $response->result())
 *     ->go();
 * ```
 */
class PlanningAgentExecutor
{
    protected string $agentClass;

    protected mixed $input;

    protected ?Model $user = null;

    protected ?string $sessionId = null;

    protected array $context = [];

    protected bool $async = false;

    protected ?string $queue = null;

    protected ?int $delay = null;

    protected int $tries = 3;

    protected ?int $timeout = null;

    protected ?Closure $thenCallback = null;

    protected ?int $maxAttempts = null;

    protected ?float $threshold = null;

    protected ?string $plannerInstructions = null;

    protected ?string $reflectionInstructions = null;

    protected ?string $model = null;

    public function __construct(string $agentClass, mixed $input)
    {
        $this->agentClass = $agentClass;
        $this->input = $input;
    }

    /**
     * Set the user context for this execution.
     */
    public function forUser(?Model $user): static
    {
        $this->user = $user;
        return $this;
    }

    /**
     * Set a specific session ID for tracking.
     */
    public function withSession(string $sessionId): static
    {
        $this->sessionId = $sessionId;
        return $this;
    }

    /**
     * Add additional context data.
     */
    public function withContext(array $context): static
    {
        $this->context = array_merge($this->context, $context);
        return $this;
    }

    // =========================================
    // PLANNING-SPECIFIC OPTIONS
    // =========================================

    /**
     * Set maximum replan attempts.
     */
    public function maxAttempts(int $attempts): static
    {
        $this->maxAttempts = $attempts;
        return $this;
    }

    /**
     * Set satisfaction threshold (0-1).
     */
    public function threshold(float $threshold): static
    {
        $this->threshold = $threshold;
        return $this;
    }

    /**
     * Set custom planner instructions.
     */
    public function withPlannerInstructions(string $instructions): static
    {
        $this->plannerInstructions = $instructions;
        return $this;
    }

    /**
     * Set custom reflection instructions.
     */
    public function withReflectionInstructions(string $instructions): static
    {
        $this->reflectionInstructions = $instructions;
        return $this;
    }

    /**
     * Set the model to use.
     */
    public function using(string $model): static
    {
        $this->model = $model;
        return $this;
    }

    /**
     * Use high accuracy settings (more attempts, higher threshold).
     */
    public function highAccuracy(): static
    {
        $this->maxAttempts = 5;
        $this->threshold = 0.9;
        return $this;
    }

    /**
     * Use fast settings (fewer attempts, lower threshold).
     */
    public function fast(): static
    {
        $this->maxAttempts = 1;
        $this->threshold = 0.6;
        return $this;
    }

    /**
     * Use balanced settings (default).
     */
    public function balanced(): static
    {
        $this->maxAttempts = 3;
        $this->threshold = 0.8;
        return $this;
    }

    // =========================================
    // QUEUE / ASYNC OPTIONS
    // =========================================

    /**
     * Execute asynchronously using Laravel queues.
     */
    public function async(bool $enabled = true): static
    {
        $this->async = $enabled;
        return $this;
    }

    /**
     * Specify which queue to use.
     */
    public function onQueue(string $queue): static
    {
        $this->queue = $queue;
        $this->async = true;
        return $this;
    }

    /**
     * Delay execution by specified seconds.
     */
    public function delay(int $seconds): static
    {
        $this->delay = $seconds;
        return $this;
    }

    /**
     * Set retry attempts for failed executions.
     */
    public function tries(int $tries): static
    {
        $this->tries = $tries;
        return $this;
    }

    /**
     * Set timeout for execution.
     */
    public function timeout(int $seconds): static
    {
        $this->timeout = $seconds;
        return $this;
    }

    /**
     * Callback to execute after planning completes.
     */
    public function then(Closure $callback): static
    {
        $this->thenCallback = $callback;
        return $this;
    }

    // =========================================
    // EXECUTION
    // =========================================

    /**
     * Execute the planning agent and return the response.
     */
    public function go(): PlanningResponse|array
    {
        if ($this->async) {
            return $this->dispatchAsync();
        }

        return $this->executeSynchronously();
    }

    /**
     * Execute synchronously.
     */
    protected function executeSynchronously(): PlanningResponse
    {
        /** @var BasePlanningAgent $agent */
        $agent = app($this->agentClass);

        // Apply configuration
        if ($this->maxAttempts !== null) {
            $agent->setMaxReplanAttempts($this->maxAttempts);
        }
        if ($this->threshold !== null) {
            $agent->setSatisfactionThreshold($this->threshold);
        }
        if ($this->plannerInstructions !== null) {
            $agent->setPlannerInstructions($this->plannerInstructions);
        }
        if ($this->reflectionInstructions !== null) {
            $agent->setReflectionInstructions($this->reflectionInstructions);
        }
        if ($this->model !== null) {
            $agent->setModel($this->model);
        }

        // Build context
        $context = $this->buildContext();

        // Start tracing
        $tracer = app(Tracer::class);
        $spanId = $tracer->startSpan(
            name: $agent->getName(),
            type: 'planning_execution',
            input: ['task' => $this->input],
            metadata: [
                'max_attempts' => $agent->getMaxReplanAttempts(),
                'threshold' => $agent->getSatisfactionThreshold(),
                'agent_class' => $this->agentClass,
            ]
        );

        try {
            // Execute the planning agent
            $response = $agent->execute($this->input, $context);

            // Execute callback if provided
            if ($this->thenCallback !== null) {
                ($this->thenCallback)($response);
            }

            // End trace successfully
            $tracer->endSpan(output: [
                'success' => $response->isSuccess(),
                'attempts' => $response->attempts(),
                'score' => $response->reflection()?->score,
            ]);

            return $response;

        } catch (\Exception $e) {
            $tracer->failSpan($e);
            throw $e;
        }
    }

    /**
     * Dispatch to queue for async execution.
     */
    protected function dispatchAsync(): array
    {
        $job = new PlanningAgentJob(
            agentClass: $this->agentClass,
            input: $this->input,
            sessionId: $this->resolveSessionId(),
            maxAttempts: $this->maxAttempts,
            threshold: $this->threshold,
            plannerInstructions: $this->plannerInstructions,
            reflectionInstructions: $this->reflectionInstructions,
            model: $this->model,
            context: $this->context,
            userId: $this->user?->getKey(),
            thenCallback: $this->thenCallback
        );

        if ($this->queue) {
            $job->onQueue($this->queue);
        }

        if ($this->delay) {
            $job->delay($this->delay);
        }

        $job->tries = $this->tries;

        if ($this->timeout) {
            $job->timeout = $this->timeout;
        }

        dispatch($job);

        return [
            'job_dispatched' => true,
            'job_id' => $job->getJobId(),
            'queue' => $this->queue ?: 'default',
            'agent' => $this->getAgentName(),
            'task' => $this->input,
        ];
    }

    /**
     * Build the agent context.
     */
    protected function buildContext(): AgentContext
    {
        $context = new AgentContext(
            sessionId: $this->resolveSessionId(),
            input: $this->input
        );

        // Add user info
        if ($this->user) {
            $context->setState('user_id', $this->user->getKey());
            $context->setState('user_model', get_class($this->user));
        }

        // Add additional context
        foreach ($this->context as $key => $value) {
            $context->setState($key, $value);
        }

        return $context;
    }

    /**
     * Get the agent name.
     */
    protected function getAgentName(): string
    {
        $agent = app($this->agentClass);
        return $agent->getName() ?: Str::snake(class_basename($this->agentClass));
    }

    /**
     * Resolve the session ID.
     */
    protected function resolveSessionId(): string
    {
        if ($this->sessionId) {
            return $this->sessionId;
        }

        if ($this->user) {
            return 'planning_user_' . $this->user->getKey() . '_' . Str::random(8);
        }

        return 'planning_' . Str::random(12);
    }

    /**
     * Magic method to auto-execute when cast to string.
     */
    public function __toString(): string
    {
        try {
            $result = $this->go();
            return $result instanceof PlanningResponse ? $result->result() : json_encode($result);
        } catch (\Exception $e) {
            return 'Error executing planning agent: ' . $e->getMessage();
        }
    }

    /**
     * Magic method to execute when invoked.
     */
    public function __invoke(): PlanningResponse|array
    {
        return $this->go();
    }
}
