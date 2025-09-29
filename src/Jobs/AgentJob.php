<?php

namespace Vizra\VizraADK\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;
use Vizra\VizraADK\Services\AgentManager;
use Vizra\VizraADK\Services\StateManager;
use Vizra\VizraADK\Traits\HasLogging;

class AgentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, HasLogging;

    protected string $agentClass;

    protected mixed $input;

    protected string $sessionId;

    protected array $context;

    protected string $jobId;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The number of seconds the job can run before timing out.
     */
    public int $timeout = 300; // 5 minutes default

    /**
     * Create a new job instance.
     */
    public function __construct(
        string $agentClass,
        mixed $input,
        string $sessionId,
        array $context = []
    ) {
        $this->agentClass = $agentClass;
        $this->input = $input;
        $this->sessionId = $sessionId;
        $this->context = $context;
        $this->jobId = Str::uuid()->toString();
    }

    /**
     * Execute the job.
     */
    public function handle(AgentManager $agentManager, StateManager $stateManager): void
    {
        try {
            $this->logInfo('Starting agent job execution', [
                'job_id' => $this->jobId,
                'agent_class' => $this->agentClass,
                'session_id' => $this->sessionId,
            ], 'agents');

            // Get agent name
            $agentName = $this->getAgentName();

            // Load or create agent context
            $agentContext = $stateManager->loadContext($agentName, $this->sessionId, $this->input, $this->context['user']['id'] ?? null);

            // Restore context from job data
            $this->restoreContext($agentContext);

            // Execute the agent
            $result = $agentManager->run($agentName, $this->input, $this->sessionId);

            // Log successful completion
            $this->logInfo('Agent job completed successfully', [
                'job_id' => $this->jobId,
                'agent_class' => $this->agentClass,
                'result_type' => gettype($result),
                'result_length' => is_string($result) ? strlen($result) : null,
            ], 'agents');

            // Store result in cache for retrieval (optional)
            $this->storeResult($result);

            // Dispatch any follow-up events
            $this->dispatchCompletionEvents($result);

        } catch (\Exception $e) {
            $this->logError('Agent job failed', [
                'job_id' => $this->jobId,
                'agent_class' => $this->agentClass,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ], 'agents');

            // Re-throw to trigger Laravel's retry mechanism
            throw $e;
        }
    }

    /**
     * Get the agent name from the class
     */
    protected function getAgentName(): string
    {
        $agent = app($this->agentClass);

        if (method_exists($agent, 'getName')) {
            return $agent->getName();
        }

        // Fallback to class name transformation
        $className = class_basename($this->agentClass);

        return Str::snake(str_replace('Agent', '', $className));
    }

    /**
     * Restore context data to the agent context
     */
    protected function restoreContext($agentContext): void
    {
        // Removed execution mode setting - no longer needed

        // Restore user context
        if (isset($this->context['user'])) {
            $userContext = $this->context['user'];
            $agentContext->setState('user_id', $userContext['id']);
            $agentContext->setState('user_model', $userContext['model']);
            $agentContext->setState('user_data', $userContext['data']);

            if (isset($userContext['email'])) {
                $agentContext->setState('user_email', $userContext['email']);
            }
            if (isset($userContext['name'])) {
                $agentContext->setState('user_name', $userContext['name']);
            }
        }

        // Restore additional context data
        if (isset($this->context['context_data'])) {
            foreach ($this->context['context_data'] as $key => $value) {
                $agentContext->setState($key, $value);
            }
        }

        // Restore parameters
        if (isset($this->context['parameters']) && ! empty($this->context['parameters'])) {
            $agentContext->setState('agent_parameters', $this->context['parameters']);
        }

        // Restore streaming setting
        if (isset($this->context['streaming']) && $this->context['streaming']) {
            $agentContext->setState('streaming', true);
        }

        // Mark as background job
        $agentContext->setState('background_job', true);
        $agentContext->setState('job_id', $this->jobId);
    }

    /**
     * Store the result for later retrieval
     */
    protected function storeResult($result): void
    {
        // Store result in cache for 1 hour
        $cacheKey = "agent_job_result:{$this->jobId}";
        cache()->put($cacheKey, $result, now()->addHour());

        // Also store metadata
        $metaKey = "agent_job_meta:{$this->jobId}";
        cache()->put($metaKey, [
            'agent_class' => $this->agentClass,
            'session_id' => $this->sessionId,
            'completed_at' => now()->toISOString(),
            'result_type' => gettype($result),
        ], now()->addHour());
    }

    /**
     * Dispatch events after successful completion
     */
    protected function dispatchCompletionEvents($result): void
    {
        // Dispatch a generic agent job completed event
        event('agent.job.completed', [
            'job_id' => $this->jobId,
            'agent_class' => $this->agentClass,
            'result' => $result,
        ]);

        // Dispatch agent-specific events
        $agentName = $this->getAgentName();
        event("agent.{$agentName}.completed", [
            'job_id' => $this->jobId,
            'result' => $result,
            'session_id' => $this->sessionId,
        ]);
    }

    /**
     * Get the unique job ID
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
        $this->logError('Agent job permanently failed', [
            'job_id' => $this->jobId,
            'agent_class' => $this->agentClass,
            'error' => $exception->getMessage(),
            'attempts' => $this->attempts(),
        ], 'agents');

        // Store failure information
        $failureKey = "agent_job_failure:{$this->jobId}";
        cache()->put($failureKey, [
            'agent_class' => $this->agentClass,
            'error' => $exception->getMessage(),
            'failed_at' => now()->toISOString(),
            'attempts' => $this->attempts(),
        ], now()->addHours(24));

        // Dispatch failure events
        event('agent.job.failed', [
            'job_id' => $this->jobId,
            'agent_class' => $this->agentClass,
            'error' => $exception->getMessage(),
        ]);
    }

    /**
     * Get the tags that should be assigned to the job.
     */
    public function tags(): array
    {
        return [
            'vizra:'.$this->getAgentName(),
            'session:'.$this->sessionId,
        ];
    }
}
