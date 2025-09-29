<?php

namespace Vizra\VizraADK\Scheduling;

use Illuminate\Console\Scheduling\Schedule;
use Vizra\VizraADK\Traits\HasLogging;

/**
 * Helper class for scheduling agent tasks
 */
class AgentScheduler
{
    use HasLogging;
    protected Schedule $schedule;

    public function __construct(Schedule $schedule)
    {
        $this->schedule = $schedule;
    }

    /**
     * Schedule an agent to run daily
     */
    public function daily(string $agentClass, mixed $input = null): AgentScheduleBuilder
    {
        return new AgentScheduleBuilder($this->schedule, $agentClass, $input, 'daily');
    }

    /**
     * Schedule an agent to run hourly
     */
    public function hourly(string $agentClass, mixed $input = null): AgentScheduleBuilder
    {
        return new AgentScheduleBuilder($this->schedule, $agentClass, $input, 'hourly');
    }

    /**
     * Schedule an agent to run weekly
     */
    public function weekly(string $agentClass, mixed $input = null): AgentScheduleBuilder
    {
        return new AgentScheduleBuilder($this->schedule, $agentClass, $input, 'weekly');
    }

    /**
     * Schedule an agent to run monthly
     */
    public function monthly(string $agentClass, mixed $input = null): AgentScheduleBuilder
    {
        return new AgentScheduleBuilder($this->schedule, $agentClass, $input, 'monthly');
    }

    /**
     * Schedule an agent with a custom cron expression
     */
    public function cron(string $expression, string $agentClass, mixed $input = null): AgentScheduleBuilder
    {
        return new AgentScheduleBuilder($this->schedule, $agentClass, $input, 'cron', $expression);
    }

    /**
     * Schedule an agent to run every N minutes
     */
    public function everyMinutes(int $minutes, string $agentClass, mixed $input = null): AgentScheduleBuilder
    {
        return new AgentScheduleBuilder($this->schedule, $agentClass, $input, 'everyMinutes', $minutes);
    }

    /**
     * Schedule an agent to run at a specific time
     */
    public function at(string $time, string $agentClass, mixed $input = null): AgentScheduleBuilder
    {
        return new AgentScheduleBuilder($this->schedule, $agentClass, $input, 'at', $time);
    }
}

/**
 * Fluent builder for agent scheduling
 */
class AgentScheduleBuilder
{
    use HasLogging;
    protected Schedule $schedule;

    protected string $agentClass;

    protected mixed $input;

    protected string $frequency;

    protected mixed $frequencyParam;

    protected array $context = [];

    protected ?string $queue = null;

    protected bool $async = false;

    protected ?string $name = null;

    protected ?string $description = null;

    protected array $environments = [];

    public function __construct(
        Schedule $schedule,
        string $agentClass,
        mixed $input,
        string $frequency,
        mixed $frequencyParam = null
    ) {
        $this->schedule = $schedule;
        $this->agentClass = $agentClass;
        $this->input = $input;
        $this->frequency = $frequency;
        $this->frequencyParam = $frequencyParam;
    }

    /**
     * Add context data for the agent execution
     */
    public function withContext(array $context): self
    {
        $this->context = array_merge($this->context, $context);

        return $this;
    }

    /**
     * Execute the agent asynchronously
     */
    public function async(bool $enabled = true): self
    {
        $this->async = $enabled;

        return $this;
    }

    /**
     * Specify the queue for async execution
     */
    public function onQueue(string $queue): self
    {
        $this->queue = $queue;
        $this->async = true; // Auto-enable async

        return $this;
    }

    /**
     * Set a name for the scheduled task
     */
    public function name(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    /**
     * Set a description for the scheduled task
     */
    public function description(string $description): self
    {
        $this->description = $description;

        return $this;
    }

    /**
     * Limit to specific environments
     */
    public function environments(array $environments): self
    {
        $this->environments = $environments;

        return $this;
    }

    /**
     * Register the scheduled task
     */
    public function register(): void
    {
        $callback = function () {
            try {
                $this->logInfo('Executing scheduled agent', [
                    'agent_class' => $this->agentClass,
                    'scheduled_name' => $this->name,
                ], 'agents');

                // Prepare agent execution
                $executor = $this->agentClass::run($this->input);

                // Add context
                if (! empty($this->context)) {
                    $executor->withContext($this->context);
                }

                // Configure async execution
                if ($this->async) {
                    $executor->async();
                }

                if ($this->queue) {
                    $executor->onQueue($this->queue);
                }

                // Execute
                $result = $executor->go();

                $this->logInfo('Scheduled agent completed', [
                    'agent_class' => $this->agentClass,
                    'scheduled_name' => $this->name,
                    'result_type' => gettype($result),
                ], 'agents');

            } catch (\Exception $e) {
                $this->logError('Scheduled agent failed', [
                    'agent_class' => $this->agentClass,
                    'scheduled_name' => $this->name,
                    'error' => $e->getMessage(),
                ], 'agents');

                throw $e;
            }
        };

        // Create the scheduled event
        $event = $this->schedule->call($callback);

        // Apply frequency
        match ($this->frequency) {
            'daily' => $event->daily(),
            'hourly' => $event->hourly(),
            'weekly' => $event->weekly(),
            'monthly' => $event->monthly(),
            'cron' => $event->cron($this->frequencyParam),
            'everyMinutes' => $event->everyMinutes($this->frequencyParam),
            'at' => $event->dailyAt($this->frequencyParam),
        };

        // Apply additional configurations
        if ($this->name) {
            $event->name($this->name);
        }

        if ($this->description) {
            $event->description($this->description);
        }

        if (! empty($this->environments)) {
            $event->environments($this->environments);
        }

        // Prevent overlapping
        $event->withoutOverlapping();

        // Send output to logs
        $event->sendOutputTo(storage_path('logs/scheduled-agents.log'));
    }
}

/**
 * Helper function to access the scheduler
 */
function agent_scheduler(Schedule $schedule): AgentScheduler
{
    return new AgentScheduler($schedule);
}
