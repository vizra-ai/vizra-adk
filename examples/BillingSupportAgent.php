<?php

namespace AaronLumsden\LaravelAiADK\Examples;

use AaronLumsden\LaravelAiADK\Agents\BaseLlmAgent;

/**
 * Billing support sub-agent specialized in handling billing and payment issues.
 */
class BillingSupportAgent extends BaseLlmAgent
{
    protected string $name = 'billing-support';
    protected string $description = 'Specialized agent for billing, payments, and account management';
    protected string $instructions = 'You are a billing support specialist. You handle all billing-related inquiries including payment issues, subscription changes, refunds, and account billing questions. You are knowledgeable about payment processing, billing cycles, and financial policies. Always be helpful and clear when explaining billing matters.';
    protected string $model = 'gpt-4o';

    /**
     * Tools this agent can use.
     *
     * @var array<class-string<ToolInterface>>
     */
    protected array $tools = [
        // Add billing specific tools here
        // e.g., PaymentProcessorTool::class, RefundTool::class, AccountLookupTool::class
    ];

    protected function registerSubAgents(): array
    {
        return [
            // Billing could have sub-agents for specialized areas
            // e.g., 'refund_specialist' => RefundSpecialistAgent::class,
        ];
    }
}
