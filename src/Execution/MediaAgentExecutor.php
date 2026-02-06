<?php

namespace Vizra\VizraADK\Execution;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Vizra\VizraADK\Agents\BaseMediaAgent;
use Vizra\VizraADK\Jobs\MediaGenerationJob;
use Vizra\VizraADK\Services\Tracer;
use Vizra\VizraADK\System\AgentContext;

/**
 * Fluent executor for media generation agents.
 *
 * Usage:
 * ImageAgent::run('A sunset over the ocean')
 *     ->size('1024x1024')
 *     ->quality('hd')
 *     ->forUser($user)
 *     ->onQueue('media')
 *     ->then(fn($image) => $image->storeAs('sunset.png'))
 *     ->go();
 */
class MediaAgentExecutor
{
    protected string $agentClass;

    protected mixed $input;

    protected ?Model $user = null;

    protected ?string $sessionId = null;

    protected array $context = [];

    protected array $options = [];

    protected bool $async = false;

    protected ?string $queue = null;

    protected ?int $delay = null;

    protected int $tries = 3;

    protected ?int $timeout = null;

    protected ?Closure $thenCallback = null;

    protected ?string $provider = null;

    protected ?string $model = null;

    public function __construct(string $agentClass, mixed $input)
    {
        $this->agentClass = $agentClass;
        $this->input = $input;
    }

    /**
     * Set the user context for this execution
     */
    public function forUser(?Model $user): static
    {
        $this->user = $user;
        return $this;
    }

    /**
     * Set a specific session ID for tracking
     */
    public function withSession(string $sessionId): static
    {
        $this->sessionId = $sessionId;
        return $this;
    }

    /**
     * Add additional context data
     */
    public function withContext(array $context): static
    {
        $this->context = array_merge($this->context, $context);
        return $this;
    }

    /**
     * Override the provider and model
     */
    public function using(string $provider, string $model): static
    {
        $this->provider = $provider;
        $this->model = $model;
        return $this;
    }

    /**
     * Set a generation option
     */
    public function withOption(string $key, mixed $value): static
    {
        $this->options[$key] = $value;
        return $this;
    }

    /**
     * Set multiple generation options
     */
    public function withOptions(array $options): static
    {
        $this->options = array_merge($this->options, $options);
        return $this;
    }

    // =========================================
    // IMAGE-SPECIFIC OPTIONS
    // =========================================

    /**
     * Set image size/dimensions
     */
    public function size(string $size): static
    {
        return $this->withOption('size', $size);
    }

    /**
     * Set image quality (standard, hd)
     */
    public function quality(string $quality): static
    {
        return $this->withOption('quality', $quality);
    }

    /**
     * Set image style (vivid, natural)
     */
    public function style(string $style): static
    {
        return $this->withOption('style', $style);
    }

    /**
     * Square image (1024x1024)
     */
    public function square(): static
    {
        return $this->size('1024x1024');
    }

    /**
     * Portrait image (1024x1792)
     */
    public function portrait(): static
    {
        return $this->size('1024x1792');
    }

    /**
     * Landscape image (1792x1024)
     */
    public function landscape(): static
    {
        return $this->size('1792x1024');
    }

    /**
     * HD quality
     */
    public function hd(): static
    {
        return $this->quality('hd');
    }

    // =========================================
    // AUDIO-SPECIFIC OPTIONS
    // =========================================

    /**
     * Set voice for TTS
     */
    public function voice(string $voice): static
    {
        return $this->withOption('voice', $voice);
    }

    /**
     * Set audio output format
     */
    public function format(string $format): static
    {
        return $this->withOption('format', $format);
    }

    /**
     * Set speech speed
     */
    public function speed(float $speed): static
    {
        return $this->withOption('speed', $speed);
    }

    // Voice presets
    public function alloy(): static { return $this->voice('alloy'); }
    public function echo(): static { return $this->voice('echo'); }
    public function fable(): static { return $this->voice('fable'); }
    public function onyx(): static { return $this->voice('onyx'); }
    public function nova(): static { return $this->voice('nova'); }
    public function shimmer(): static { return $this->voice('shimmer'); }

    // =========================================
    // QUEUE / ASYNC OPTIONS
    // =========================================

    /**
     * Execute asynchronously using Laravel queues
     */
    public function async(bool $enabled = true): static
    {
        $this->async = $enabled;
        return $this;
    }

    /**
     * Specify which queue to use
     */
    public function onQueue(string $queue): static
    {
        $this->queue = $queue;
        $this->async = true;
        return $this;
    }

    /**
     * Delay execution by specified seconds
     */
    public function delay(int $seconds): static
    {
        $this->delay = $seconds;
        return $this;
    }

    /**
     * Set retry attempts for failed executions
     */
    public function tries(int $tries): static
    {
        $this->tries = $tries;
        return $this;
    }

    /**
     * Set timeout for execution
     */
    public function timeout(int $seconds): static
    {
        $this->timeout = $seconds;
        return $this;
    }

    /**
     * Callback to execute after generation completes
     */
    public function then(Closure $callback): static
    {
        $this->thenCallback = $callback;
        return $this;
    }

    // =========================================
    // STORAGE OPTIONS
    // =========================================

    /**
     * Auto-store to a specific path after generation
     */
    public function store(?string $path = null, ?string $disk = null): static
    {
        $this->options['auto_store'] = true;
        if ($path !== null) {
            $this->options['store_path'] = $path;
        }
        if ($disk !== null) {
            $this->options['store_disk'] = $disk;
        }
        return $this;
    }

    /**
     * Store with specific filename
     */
    public function storeAs(string $filename, ?string $disk = null): static
    {
        $this->options['auto_store'] = true;
        $this->options['store_filename'] = $filename;
        if ($disk !== null) {
            $this->options['store_disk'] = $disk;
        }
        return $this;
    }

    // =========================================
    // EXECUTION
    // =========================================

    /**
     * Execute the media generation and return the response
     */
    public function go(): mixed
    {
        if ($this->async) {
            return $this->dispatchAsync();
        }

        return $this->executeSynchronously();
    }

    /**
     * Execute synchronously
     */
    protected function executeSynchronously(): mixed
    {
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
        $context = $this->buildContext();

        // Store options in context
        $context->setState('media_options', $this->options);

        // Start tracing
        $tracer = app(Tracer::class);
        $spanId = $tracer->startSpan(
            name: $agent->getName(),
            type: 'media_generation',
            input: ['prompt' => $this->input, 'options' => $this->options],
            metadata: [
                'provider' => $agent->getProvider(),
                'model' => $agent->getModel(),
                'agent_class' => $this->agentClass,
            ]
        );

        try {
            // Execute the media agent
            $response = $agent->execute($this->input, $context);

            // Handle auto-storage
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
                ($this->thenCallback)($response);
            }

            // End trace successfully
            $tracer->endSpan($spanId, output: [
                'success' => true,
                'url' => method_exists($response, 'url') ? $response->url() : null,
                'path' => method_exists($response, 'path') ? $response->path() : null,
            ]);

            return $response;

        } catch (\Exception $e) {
            $tracer->failSpan($spanId, $e);
            throw $e;
        }
    }

    /**
     * Dispatch to queue for async execution
     */
    protected function dispatchAsync(): array
    {
        $job = new MediaGenerationJob(
            agentClass: $this->agentClass,
            input: $this->input,
            sessionId: $this->resolveSessionId(),
            options: $this->options,
            provider: $this->provider,
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
            'prompt' => $this->input,
        ];
    }

    /**
     * Build the agent context
     */
    protected function buildContext(): AgentContext
    {
        $context = new AgentContext(
            sessionId: $this->resolveSessionId(),
            userInput: $this->input
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
     * Get the agent name
     */
    protected function getAgentName(): string
    {
        $agent = app($this->agentClass);
        return $agent->getName() ?: Str::snake(class_basename($this->agentClass));
    }

    /**
     * Resolve the session ID
     */
    protected function resolveSessionId(): string
    {
        if ($this->sessionId) {
            return $this->sessionId;
        }

        if ($this->user) {
            return 'media_user_' . $this->user->getKey() . '_' . Str::random(8);
        }

        return 'media_' . Str::random(12);
    }

    /**
     * Magic method to auto-execute when cast to string
     */
    public function __toString(): string
    {
        try {
            $result = $this->go();
            return method_exists($result, 'url') ? $result->url() : (string) $result;
        } catch (\Exception $e) {
            return 'Error generating media: ' . $e->getMessage();
        }
    }

    /**
     * Magic method to execute when invoked
     */
    public function __invoke(): mixed
    {
        return $this->go();
    }
}
