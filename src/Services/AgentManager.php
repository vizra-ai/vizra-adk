<?php

namespace Vizra\VizraADK\Services;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Event;
use InvalidArgumentException;
use Vizra\VizraADK\Agents\BaseAgent;
use Vizra\VizraADK\Agents\BaseLlmAgent;
use Vizra\VizraADK\Events\AgentExecutionFinished; // For AgentResponseGenerated
use Vizra\VizraADK\Events\AgentExecutionStarting; // For AgentResponseGenerated
use Vizra\VizraADK\Events\AgentResponseGenerated;
use Vizra\VizraADK\Exceptions\AgentConfigurationException;
use Vizra\VizraADK\Models\AgentMessage;

class AgentManager
{
    protected Application $app;

    protected AgentRegistry $registry;

    protected AgentBuilder $builder;

    protected StateManager $stateManager;

    public function __construct(
        Application $app,
        AgentRegistry $registry,
        AgentBuilder $builder,
        StateManager $stateManager
    ) {
        $this->app = $app;
        $this->registry = $registry;
        $this->builder = $builder;
        $this->stateManager = $stateManager;
    }

    /**
     * Start building an agent from a class.
     */
    public function build(string $agentClass): AgentBuilder
    {
        return $this->builder->build($agentClass);
    }

    /**
     * Start defining an ad-hoc agent.
     */
    public function define(string $name): AgentBuilder
    {
        return $this->builder->define($name);
    }

    /**
     * Get an instance of a registered agent.
     *
     * @param  string  $agentName  The name of the agent.
     * @return BaseAgent The agent instance.
     *
     * @throws \Vizra\VizraADK\Exceptions\AgentNotFoundException
     * @throws \Vizra\VizraADK\Exceptions\AgentConfigurationException
     */
    public function named(string $agentName): BaseAgent
    {
        return $this->registry->getAgent($agentName);
    }

    /**
     * Get an instance of a registered agent by class name.
     *
     * @param  string  $agentClass  The class name of the agent.
     * @return BaseAgent The agent instance.
     *
     * @throws \Vizra\VizraADK\Exceptions\AgentNotFoundException
     * @throws \Vizra\VizraADK\Exceptions\AgentConfigurationException
     */
    public function byClass(string $agentClass): BaseAgent
    {
        $agentName = $this->registry->resolveAgentName($agentClass);

        return $this->registry->getAgent($agentName);
    }

    /**
     * Run an agent with the given input and session ID.
     *
     * @param  string  $agentNameOrClass  The name or class of the agent to run.
     * @param  mixed  $input  The input for the agent.
     * @param  string|null  $sessionId  Optional session ID. If null, a new session is created/managed.
     * @return mixed The final response from the agent.
     *
     * @throws \Vizra\VizraADK\Exceptions\AgentNotFoundException
     * @throws \Vizra\VizraADK\Exceptions\AgentConfigurationException
     * @throws \Throwable
     */
    public function run(string $agentNameOrClass, mixed $input, ?string $sessionId = null): mixed
    {
        // Resolve to agent name first
        $agentName = $this->registry->resolveAgentName($agentNameOrClass);
        $agent = $this->named($agentName);

        if (! ($agent instanceof BaseLlmAgent)) { // For MVP, assume all runnable agents are LLM based
            throw new AgentConfigurationException("Agent '{$agentName}' is not an LLM agent and cannot be run directly via this method in MVP.");
        }

        // Load or create context
        // The StateManager's loadContext now takes agentName first.
        $context = $this->stateManager->loadContext($agentName, $sessionId, $input);


        Event::dispatch(new AgentExecutionStarting($context, $agentName, $input));

        $finalResponse = null;
        try {
            $finalResponse = $agent->execute($input, $context); // BaseLlmAgent::execute handles its own history additions for input

            // The AgentResponseGenerated event should ideally be dispatched from within BaseLlmAgent
            // after the final response is determined but before it's returned from its execute method.
            // This is handled by the modification in step 5 of this subtask.
            // Event::dispatch(new AgentResponseGenerated($context, $agentName, $finalResponse));

        } catch (\Throwable $e) {
            // Log error, dispatch failure event, etc.
            // For now, rethrow.
            Event::dispatch(new AgentExecutionFinished($context, $agentName)); // Dispatch finished even on error
            throw $e;
        } finally {
            // Save context (state and full conversation history)
            $this->stateManager->saveContext($context, $agentName);
        }

        Event::dispatch(new AgentExecutionFinished($context, $agentName));

        return $finalResponse;
    }

    /**
     * Check if an agent is registered.
     */
    public function hasAgent(string $name): bool
    {
        return $this->registry->hasAgent($name);
    }

    /**
     * Get all registered agent configurations.
     */
    public function getAllRegisteredAgents(): array
    {
        return $this->registry->getAllRegisteredAgents();
    }

    /**
     * Persist feedback for the specified assistant message.
     */
    public function setMessageFeedback(int $messageId, ?string $feedback): AgentMessage
    {
        $allowed = ['like', 'dislike'];

        if ($feedback !== null && ! in_array($feedback, $allowed, true)) {
            throw new InvalidArgumentException(
                sprintf("Feedback must be null, 'like', or 'dislike'. '%s' given.", (string) $feedback)
            );
        }

        /** @var AgentMessage $message */
        $message = AgentMessage::findOrFail($messageId);

        if ($message->role !== 'assistant') {
            throw new InvalidArgumentException('Feedback can only be applied to assistant messages.');
        }

        $message->feedback = $feedback;
        $message->save();

        return $message;
    }
}
