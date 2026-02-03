<?php

namespace App\Toolboxes;

use Vizra\VizraADK\Toolboxes\BaseToolbox;

/**
 * Customer support tools for service agents.
 *
 * This toolbox demonstrates:
 * - Policy-based authorization
 * - Grouping support-specific tools
 *
 * Usage:
 * 1. Create a policy: php artisan make:policy ToolboxPolicy
 * 2. Register it in AuthServiceProvider:
 *
 *     Gate::policy(CustomerSupportToolbox::class, ToolboxPolicy::class);
 *
 * 3. Implement the 'use' ability in your ToolboxPolicy:
 *
 *     public function use($user, $toolbox): bool
 *     {
 *         return $user->hasRole('support_agent');
 *     }
 */
class CustomerSupportToolbox extends BaseToolbox
{
    protected string $name = 'customer_support';

    protected string $description = 'Customer support tools for ticket and order management';

    /**
     * Policy class for authorization.
     * The policy's 'use' method will be called to check access.
     */
    // protected ?string $policy = \App\Policies\ToolboxPolicy::class;
    // protected ?string $policyAbility = 'use';

    protected array $tools = [
        // TicketLookupTool::class,
        // OrderStatusTool::class,
        // CustomerHistoryTool::class,
        // RefundProcessingTool::class,
    ];

    /**
     * Refund processing requires additional authorization.
     */
    protected array $toolGates = [
        // RefundProcessingTool::class => 'process-refunds',
    ];
}
