<?php

namespace AaronLumsden\LaravelAiADK\Examples;

use AaronLumsden\LaravelAiADK\Agents\BaseLlmAgent;

/**
 * Example parent agent that demonstrates sub-agent delegation capabilities.
 * This customer service agent has specialized sub-agents for different domains.
 */
class CustomerServiceAgent extends BaseLlmAgent
{
    protected string $name = 'customer-service';
    protected string $description = 'A comprehensive customer service agent with specialized sub-agents';
    protected string $instructions = 'You are a helpful customer service agent. You can handle general inquiries, but you have access to specialized sub-agents for more complex or domain-specific issues. Use delegation when appropriate to provide the best possible assistance.';
    protected string $model = 'gpt-4o';

    /**
     * Register tools for this agent (if any).
     */
    protected function registerTools(): array
    {
        return [
            // Add any general customer service tools here
        ];
    }

    /**
     * Register sub-agents for delegation.
     */
    protected function registerSubAgents(): array
    {
        return [
            'technical_support' => TechnicalSupportAgent::class,
            'billing_support' => BillingSupportAgent::class,
            'order_specialist' => OrderSpecialistAgent::class,
        ];
    }
}
