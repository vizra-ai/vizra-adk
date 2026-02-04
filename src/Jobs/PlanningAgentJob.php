<?php

declare(strict_types=1);

namespace Vizra\VizraADK\Jobs;

use Closure;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;
use Vizra\VizraADK\Agents\BasePlanningAgent;
use Vizra\VizraADK\System\AgentContext;
use Vizra\VizraADK\Traits\HasLogging;

/**
 * Queue job for asynchronous planning agent execution.
 */
class PlanningAgentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, HasLogging;

    protected string $agentClass;

    protected mixed $input;

    protected string $sessionId;

    protected ?int $maxAttempts;

    protected ?float $threshold;

    protected ?string $plannerInstructions;

    protected ?string $reflectionInstructions;

    protected ?string $model;

    protected array $context;

    protected mixed $userId;

    protected ?Closure $thenCallback;

    protected string $jobId;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The number of seconds the job can run before timing out.
     */
    public int $timeout = 600; // 10 minutes for planning tasks

    /**
     * Create a new job instance.
     */
    public function __construct(
        string $agentClass,
        mixed $input,
        string $sessionId,
        ?int $maxAttempts = null,
        ?float $threshold = null,
        ?string $plannerInstructions = null,
        ?string $reflectionInstructions = null,
        ?string $model = null,
        array $context = [],
        mixed $userId = null,
        ?Closure $thenCallback = null
    ) {
        $this->agentClass = $agentClass;
        $this->input = $input;
        $this->sessionId = $sessionId;
        $this->maxAttempts = $maxAttempts;
        $this->threshold = $threshold;
        $this->plannerInstructions = $plannerInstructions;
        $this->reflectionInstructions = $reflectionInstructions;
        $this->model = $model;
        $this->context = $context;
        $this->userId = $userId;
        $this->thenCallback = $thenCallback;
        $this->jobId = Str::uuid()->toString();
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            $this->logInfo('Starting planning agent job', [
                'job_id' => $this->jobId,
                'agent_class' => $this->agentClass,
                'session_id' => $this->sessionId,
            ], 'planning');

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
            $context = new AgentContext(
                sessionId: $this->sessionId,
                input: $this->input
            );

            // Add user info
            if ($this->userId) {
                $context->setState('user_id', $this->userId);
            }

            // Add additional context
            foreach ($this->context as $key => $value) {
                $context->setState($key, $value);
            }

            // Mark as background job
            $context->setState('background_job', true);
            $context->setState('job_id', $this->jobId);

            // Execute the planning agent
            $response = $agent->execute($this->input, $context);

            $this->logInfo('Planning agent job completed', [
                'job_id' => $this->jobId,
                'success' => $response->isSuccess(),
                'attempts' => $response->attempts(),
                'score' => $response->score(),
            ], 'planning');

            // Store result
            $this->storeResult($response);

            // Execute callback if provided
            if ($this->thenCallback !== null) {
                ($this->thenCallback)($response);
            }

            // Dispatch completion events
            $this->dispatchCompletionEvents($response);

        } catch (\Exception $e) {
            $this->logError('Planning agent job failed', [
                'job_id' => $this->jobId,
                'agent_class' => $this->agentClass,
                'error' => $e->getMessage(),
            ], 'planning');

            throw $e;
        }
    }

    /**
     * Store the result for later retrieval.
     */
    protected function storeResult($response): void
    {
        $cacheKey = "planning_job_result:{$this->jobId}";
        cache()->put($cacheKey, $response->toArray(), now()->addHours(24));

        $metaKey = "planning_job_meta:{$this->jobId}";
        cache()->put($metaKey, [
            'agent_class' => $this->agentClass,
            'session_id' => $this->sessionId,
            'completed_at' => now()->toISOString(),
            'success' => $response->isSuccess(),
            'attempts' => $response->attempts(),
            'score' => $response->score(),
        ], now()->addHours(24));
    }

    /**
     * Dispatch events after completion.
     */
    protected function dispatchCompletionEvents($response): void
    {
        event('planning.job.completed', [
            'job_id' => $this->jobId,
            'agent_class' => $this->agentClass,
            'response' => $response,
        ]);

        $agentName = $this->getAgentName();
        event("planning.{$agentName}.completed", [
            'job_id' => $this->jobId,
            'response' => $response,
            'session_id' => $this->sessionId,
        ]);
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
     * Get the unique job ID.
     */
    public function getJobId(): string
    {
        return $this->jobId;
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        $this->logError('Planning agent job permanently failed', [
            'job_id' => $this->jobId,
            'agent_class' => $this->agentClass,
            'error' => $exception->getMessage(),
            'attempts' => $this->attempts(),
        ], 'planning');

        $failureKey = "planning_job_failure:{$this->jobId}";
        cache()->put($failureKey, [
            'agent_class' => $this->agentClass,
            'error' => $exception->getMessage(),
            'failed_at' => now()->toISOString(),
            'attempts' => $this->attempts(),
        ], now()->addHours(24));

        event('planning.job.failed', [
            'job_id' => $this->jobId,
            'agent_class' => $this->agentClass,
            'error' => $exception->getMessage(),
        ]);
    }

    /**
     * Get the tags for the job.
     */
    public function tags(): array
    {
        return [
            'vizra:planning',
            'agent:' . $this->getAgentName(),
            'session:' . $this->sessionId,
        ];
    }
}
