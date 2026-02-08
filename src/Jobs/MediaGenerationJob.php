<?php

namespace Vizra\VizraADK\Jobs;

use Closure;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;
use Laravel\SerializableClosure\SerializableClosure;
use Vizra\VizraADK\Agents\BaseMediaAgent;
use Vizra\VizraADK\System\AgentContext;
use Vizra\VizraADK\Traits\HasLogging;

/**
 * Queued job for media generation (images, audio, etc.)
 *
 * Handles asynchronous media generation with callback support.
 */
class MediaGenerationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, HasLogging;

    protected string $agentClass;

    protected mixed $input;

    protected string $sessionId;

    protected array $options;

    protected ?string $provider;

    protected ?string $model;

    protected array $context;

    protected ?int $userId;

    protected ?SerializableClosure $thenCallback;

    protected string $jobId;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The number of seconds the job can run before timing out.
     */
    public int $timeout = 120; // 2 minutes default for media generation

    /**
     * Create a new job instance.
     */
    public function __construct(
        string $agentClass,
        mixed $input,
        string $sessionId,
        array $options = [],
        ?string $provider = null,
        ?string $model = null,
        array $context = [],
        ?int $userId = null,
        ?Closure $thenCallback = null
    ) {
        $this->agentClass = $agentClass;
        $this->input = $input;
        $this->sessionId = $sessionId;
        $this->options = $options;
        $this->provider = $provider;
        $this->model = $model;
        $this->context = $context;
        $this->userId = $userId;
        $this->thenCallback = $thenCallback ? new SerializableClosure($thenCallback) : null;
        $this->jobId = Str::uuid()->toString();
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            $this->logInfo('Starting media generation job', [
                'job_id' => $this->jobId,
                'agent_class' => $this->agentClass,
                'session_id' => $this->sessionId,
                'prompt_length' => is_string($this->input) ? strlen($this->input) : null,
            ], 'agents');

            /** @var BaseMediaAgent $agent */
            $agent = app($this->agentClass);

            // Apply provider/model overrides
            if ($this->provider !== null) {
                $agent->setProvider($this->provider);
            }
            if ($this->model !== null) {
                $agent->setModel($this->model);
            }

            // Build context
            $agentContext = $this->buildContext();

            // Execute generation
            $response = $agent->execute($this->input, $agentContext);

            // Handle auto-storage options
            if ($this->options['auto_store'] ?? false) {
                if (isset($this->options['store_filename'])) {
                    $response->storeAs(
                        $this->options['store_filename'],
                        $this->options['store_disk'] ?? null
                    );
                } else {
                    $response->store($this->options['store_disk'] ?? null);
                }
            }

            // Execute callback if provided
            if ($this->thenCallback !== null) {
                $callback = $this->thenCallback->getClosure();
                $callback($response);
            }

            // Log successful completion
            $this->logInfo('Media generation job completed', [
                'job_id' => $this->jobId,
                'agent_class' => $this->agentClass,
                'url' => method_exists($response, 'url') ? $response->url() : null,
                'path' => method_exists($response, 'path') ? $response->path() : null,
            ], 'agents');

            // Store result for retrieval
            $this->storeResult($response);

            // Dispatch completion events
            $this->dispatchCompletionEvents($response);

        } catch (\Exception $e) {
            $this->logError('Media generation job failed', [
                'job_id' => $this->jobId,
                'agent_class' => $this->agentClass,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ], 'agents');

            throw $e;
        }
    }

    /**
     * Build the agent context
     */
    protected function buildContext(): AgentContext
    {
        $context = new AgentContext(
            sessionId: $this->sessionId,
            input: $this->input
        );

        // Set media options
        $context->setState('media_options', $this->options);

        // Add user info
        if ($this->userId !== null) {
            $context->setState('user_id', $this->userId);
        }

        // Add additional context
        foreach ($this->context as $key => $value) {
            $context->setState($key, $value);
        }

        // Mark as background job
        $context->setState('background_job', true);
        $context->setState('job_id', $this->jobId);

        return $context;
    }

    /**
     * Store the result for later retrieval
     */
    protected function storeResult($response): void
    {
        $cacheKey = "media_job_result:{$this->jobId}";

        $resultData = [
            'agent_class' => $this->agentClass,
            'session_id' => $this->sessionId,
            'completed_at' => now()->toISOString(),
        ];

        if (method_exists($response, 'metadata')) {
            $resultData['metadata'] = $response->metadata();
        }
        if (method_exists($response, 'url')) {
            $resultData['url'] = $response->url();
        }
        if (method_exists($response, 'path')) {
            $resultData['path'] = $response->path();
        }

        cache()->put($cacheKey, $resultData, now()->addHour());
    }

    /**
     * Dispatch events after successful completion
     */
    protected function dispatchCompletionEvents($response): void
    {
        $agentName = $this->getAgentName();

        // Generic media job completed event
        event('media.job.completed', [
            'job_id' => $this->jobId,
            'agent_class' => $this->agentClass,
            'response' => $response,
        ]);

        // Agent-specific event
        event("media.{$agentName}.completed", [
            'job_id' => $this->jobId,
            'response' => $response,
            'session_id' => $this->sessionId,
        ]);
    }

    /**
     * Get the agent name
     */
    protected function getAgentName(): string
    {
        $agent = app($this->agentClass);

        if (method_exists($agent, 'getName')) {
            return $agent->getName();
        }

        return Str::snake(class_basename($this->agentClass));
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
        $this->logError('Media generation job permanently failed', [
            'job_id' => $this->jobId,
            'agent_class' => $this->agentClass,
            'error' => $exception->getMessage(),
            'attempts' => $this->attempts(),
        ], 'agents');

        // Store failure information
        $failureKey = "media_job_failure:{$this->jobId}";
        cache()->put($failureKey, [
            'agent_class' => $this->agentClass,
            'input' => $this->input,
            'error' => $exception->getMessage(),
            'failed_at' => now()->toISOString(),
            'attempts' => $this->attempts(),
        ], now()->addHours(24));

        // Dispatch failure events
        event('media.job.failed', [
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
            'vizra:media',
            'vizra:' . $this->getAgentName(),
            'session:' . $this->sessionId,
        ];
    }
}
