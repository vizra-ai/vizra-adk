<?php

namespace AaronLumsden\LaravelAiADK\Examples;

use AaronLumsden\LaravelAiADK\Agents\BaseLlmAgent;

/**
 * Order specialist sub-agent focused on order management and tracking.
 */
class OrderSpecialistAgent extends BaseLlmAgent
{
    protected string $name = 'order-specialist';
    protected string $description = 'Specialized agent for order management, tracking, and fulfillment';
    protected string $instructions = 'You are an order management specialist. You help customers with order placement, tracking, modifications, cancellations, and delivery issues. You have expertise in logistics, shipping, and order fulfillment processes. Provide accurate order status information and helpful guidance for order-related concerns.';
    protected string $model = 'gpt-4o';

    /**
     * Tools this agent can use.
     *
     * @var array<class-string<ToolInterface>>
     */
    protected array $tools = [
        // Add order management specific tools here
        // e.g., OrderLookupTool::class, ShippingTrackerTool::class, InventoryTool::class
    ];

    protected function registerSubAgents(): array
    {
        return [
            // Order specialist could have sub-agents for specialized areas
            // e.g., 'shipping_specialist' => ShippingSpecialistAgent::class,
        ];
    }
}
