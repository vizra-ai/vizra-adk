<?php

namespace Vizra\VizraADK\Listeners;

use Vizra\VizraADK\Traits\HasLogging;

/**
 * Base class for creating event listeners that trigger agents
 */
abstract class AgentEventListener
{
    use HasLogging;
    /**
     * The agent class to trigger
     */
    protected string $agentClass;

    // Removed mode property - no longer needed with simplified API

    /**
     * Whether to execute asynchronously
     */
    protected bool $async = false;

    /**
     * Queue to use for async execution
     */
    protected ?string $queue = null;

    /**
     * Handle the event
     */
    public function handle($event): void
    {
        try {
            // Prepare the agent executor
            $executor = $this->prepareAgentExecution($event);

            // Configure async execution if needed
            if ($this->async) {
                $executor->async();
            }

            if ($this->queue) {
                $executor->onQueue($this->queue);
            }

            // Execute the agent
            $result = $executor->go();

            // Handle the result
            $this->handleResult($result, $event);

        } catch (\Exception $e) {
            $this->logError('Agent event listener failed', [
                'listener' => static::class,
                'agent_class' => $this->agentClass,
                'event' => get_class($event),
                'error' => $e->getMessage(),
            ], 'agents');

            // Allow subclasses to handle failures
            $this->handleFailure($e, $event);
        }
    }

    /**
     * Prepare the agent execution based on the event
     */
    protected function prepareAgentExecution($event)
    {
        // Create agent executor
        $executor = $this->agentClass::run($event);

        // Add context from the event
        $context = $this->buildContext($event);
        if (! empty($context)) {
            $executor->withContext($context);
        }

        // Set user context if available
        $user = $this->extractUser($event);
        if ($user) {
            $executor->forUser($user);
        }

        return $executor;
    }

    /**
     * Build context data from the event
     * Subclasses should override this to extract relevant data
     */
    protected function buildContext($event): array
    {
        return [
            'event_class' => get_class($event),
            'event_time' => now()->toISOString(),
        ];
    }

    /**
     * Extract user from the event if available
     * Subclasses should override this to extract the relevant user
     */
    protected function extractUser($event)
    {
        // Try common user property names
        if (property_exists($event, 'user')) {
            return $event->user;
        }

        if (property_exists($event, 'customer')) {
            return $event->customer;
        }

        if (method_exists($event, 'getUser')) {
            return $event->getUser();
        }

        return null;
    }

    /**
     * Handle the result of agent execution
     * Subclasses can override this for custom result handling
     */
    protected function handleResult($result, $event): void
    {
        // Default: log the result
        $this->logInfo('Agent triggered by event', [
            'listener' => static::class,
            'agent_class' => $this->agentClass,
            'event' => get_class($event),
            'result_type' => gettype($result),
        ], 'agents');
    }

    /**
     * Handle execution failures
     * Subclasses can override this for custom error handling
     */
    protected function handleFailure(\Exception $exception, $event): void
    {
        // Default: just log the error (already logged above)
        // Subclasses can implement custom failure handling like:
        // - Sending alerts
        // - Triggering fallback agents
        // - Storing failure information
    }
}
