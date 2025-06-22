<?php

namespace Vizra\VizraADK\Agents;

use Vizra\VizraADK\Execution\AgentExecutor;
use Vizra\VizraADK\System\AgentContext;

/**
 * Abstract Class BaseAgent
 * Establishes the common contract for all agents.
 */
abstract class BaseAgent
{
    /**
     * The unique name of the agent.
     * Used for registration and identification.
     */
    protected string $name = '';

    /**
     * A brief description of what the agent does.
     */
    protected string $description = '';

    /**
     * Get the name of the agent.
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Get the description of the agent.
     */
    public function getDescription(): string
    {
        return $this->description;
    }

    /**
     * Execute the agent's primary logic.
     *
     * @param  mixed  $input  The input for the agent.
     * @param  AgentContext  $context  The context for this execution.
     * @return mixed The result of the agent's execution.
     */
    abstract public function run(mixed $input, AgentContext $context): mixed;

    /**
     * Create a fluent agent executor for conversational interaction.
     *
     * Usage: CustomerSupportAgent::ask('Where is my order?')->forUser($user)
     *
     * @param  mixed  $input  The input for the agent.
     */
    public static function ask(mixed $input): AgentExecutor
    {
        return new AgentExecutor(static::class, $input, 'ask');
    }

    /**
     * Trigger the agent with an event or data.
     *
     * Usage: NotificationAgent::trigger($orderCreatedEvent)->forUser($user)
     *
     * @param  mixed  $event  The event or data to process.
     */
    public static function trigger(mixed $event): AgentExecutor
    {
        return new AgentExecutor(static::class, $event, 'trigger');
    }

    /**
     * Ask the agent to analyze data or events.
     *
     * Usage: FraudDetectionAgent::analyze($paymentData)->withContext($context)
     *
     * @param  mixed  $data  The data to analyze.
     */
    public static function analyze(mixed $data): AgentExecutor
    {
        return new AgentExecutor(static::class, $data, 'analyze');
    }

    /**
     * Process data or perform batch operations.
     *
     * Usage: DataProcessorAgent::process($largeDataset)->async()
     *
     * @param  mixed  $data  The data to process.
     */
    public static function process(mixed $data): AgentExecutor
    {
        return new AgentExecutor(static::class, $data, 'process');
    }

    /**
     * Monitor metrics or conditions continuously.
     *
     * Usage: SystemMonitorAgent::monitor($metrics)->onQueue('monitoring')
     *
     * @param  mixed  $data  The data to monitor.
     */
    public static function monitor(mixed $data): AgentExecutor
    {
        return new AgentExecutor(static::class, $data, 'monitor');
    }

    /**
     * Generate reports or summaries.
     *
     * Usage: ReportAgent::generate('daily_sales')->withContext(['date' => today()])
     *
     * @param  mixed  $data  The data for report generation.
     */
    public static function generate(mixed $data): AgentExecutor
    {
        return new AgentExecutor(static::class, $data, 'generate');
    }
}
