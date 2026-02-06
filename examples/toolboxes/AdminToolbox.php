<?php

namespace App\Toolboxes;

use Vizra\VizraADK\System\AgentContext;
use Vizra\VizraADK\Toolboxes\BaseToolbox;

/**
 * Administrative tools for system management.
 *
 * This toolbox demonstrates:
 * - Gate-based authorization at toolbox level
 * - Per-tool gate mappings for fine-grained access control
 *
 * Usage in your AppServiceProvider or AuthServiceProvider:
 *
 *     Gate::define('admin-tools', function ($user) {
 *         return $user->hasRole('admin');
 *     });
 *
 *     Gate::define('manage-users', function ($user) {
 *         return $user->hasPermission('users.manage');
 *     });
 */
class AdminToolbox extends BaseToolbox
{
    protected string $name = 'admin';

    protected string $description = 'Administrative tools for system management (requires admin access)';

    /**
     * This gate must pass for the entire toolbox to be accessible.
     */
    protected ?string $gate = 'admin-tools';

    /**
     * Tools in this toolbox (add your admin tool classes).
     */
    protected array $tools = [
        // DatabaseQueryTool::class,
        // SystemStatusTool::class,
        // UserManagementTool::class,
        // CacheManagementTool::class,
    ];

    /**
     * Additional gates required for specific tools.
     * Even if the toolbox gate passes, these tool-specific gates must also pass.
     */
    protected array $toolGates = [
        // UserManagementTool::class => 'manage-users',
        // DatabaseQueryTool::class => 'database-access',
    ];
}
