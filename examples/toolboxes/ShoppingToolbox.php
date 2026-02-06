<?php

namespace App\Toolboxes;

use App\Tools\CartManagerTool;
use Vizra\VizraADK\System\AgentContext;
use Vizra\VizraADK\Toolboxes\BaseToolbox;

/**
 * Shopping-related tools for e-commerce agents.
 *
 * This toolbox demonstrates:
 * - Grouping related tools together
 * - Conditional tool inclusion based on user context
 */
class ShoppingToolbox extends BaseToolbox
{
    protected string $name = 'shopping';

    protected string $description = 'E-commerce shopping tools for cart and product management';

    protected array $tools = [
        CartManagerTool::class,
        // ProductSearchTool::class,
        // WishlistTool::class,
    ];

    /**
     * Only include premium features for premium users.
     */
    protected function shouldIncludeTool(string $toolClass, AgentContext $context): bool
    {
        // Example: Wishlist might be a premium feature
        // if ($toolClass === WishlistTool::class) {
        //     return $context->getState('user_tier') === 'premium';
        // }

        return true;
    }
}
