<?php

namespace Vizra\VizraADK\Toolboxes;

use Illuminate\Support\Facades\Gate;
use Vizra\VizraADK\Contracts\ToolboxInterface;
use Vizra\VizraADK\Contracts\ToolInterface;
use Vizra\VizraADK\System\AgentContext;

/**
 * Abstract base class for Toolboxes.
 *
 * Toolboxes group related tools together and provide authorization
 * via Laravel Gates or Policies at both the toolbox and tool level.
 *
 * @example
 * class AdminToolbox extends BaseToolbox
 * {
 *     protected string $name = 'admin';
 *     protected string $description = 'Administrative tools';
 *     protected ?string $gate = 'admin-access';
 *     protected array $tools = [
 *         DatabaseTool::class,
 *         UserManagementTool::class,
 *     ];
 * }
 */
abstract class BaseToolbox implements ToolboxInterface
{
    /**
     * The unique identifier for this toolbox.
     */
    protected string $name = '';

    /**
     * Human-readable description of this toolbox.
     */
    protected string $description = '';

    /**
     * Array of tool class names that belong to this toolbox.
     *
     * @var array<class-string<ToolInterface>>
     */
    protected array $tools = [];

    /**
     * Optional Laravel Gate name for toolbox-level authorization.
     * If set, the gate must return true for the toolbox to be accessible.
     */
    protected ?string $gate = null;

    /**
     * Optional Laravel Policy class for toolbox-level authorization.
     * Used with $policyAbility to check authorization.
     */
    protected ?string $policy = null;

    /**
     * The policy ability to check when using policy-based authorization.
     */
    protected ?string $policyAbility = 'use';

    /**
     * Optional per-tool gate mappings.
     * Keys are tool class names, values are gate names.
     *
     * @var array<class-string<ToolInterface>, string>
     */
    protected array $toolGates = [];

    /**
     * Optional per-tool policy mappings.
     * Keys are tool class names, values are [policy class, ability] arrays.
     *
     * @var array<class-string<ToolInterface>, array{0: string, 1: string}>
     */
    protected array $toolPolicies = [];

    /**
     * Cache of authorized tools by context session ID.
     *
     * @var array<string, array<string, ToolInterface>>
     */
    protected array $authorizedToolsCache = [];

    /**
     * Get the toolbox name.
     */
    public function name(): string
    {
        return $this->name;
    }

    /**
     * Get the toolbox description.
     */
    public function description(): string
    {
        return $this->description;
    }

    /**
     * Get the array of tool class names.
     *
     * @return array<class-string<ToolInterface>>
     */
    public function tools(): array
    {
        return $this->tools;
    }

    /**
     * Check if the toolbox is authorized for the given context.
     *
     * Checks authorization in the following order:
     * 1. If a policy is defined, check using the policy
     * 2. If a gate is defined, check using the gate
     * 3. If neither is defined, return true (open access)
     */
    public function authorize(AgentContext $context): bool
    {
        $user = $this->getUserFromContext($context);

        // Check policy-based authorization first
        if ($this->policy !== null) {
            return $this->checkPolicyAuthorization($user);
        }

        // Check gate-based authorization
        if ($this->gate !== null) {
            return $this->checkGateAuthorization($user);
        }

        // No authorization configured - allow by default
        return true;
    }

    /**
     * Get the instantiated tools that are authorized for the given context.
     */
    public function authorizedTools(AgentContext $context): array
    {
        $cacheKey = $context->getSessionId() ?? 'no-session';

        // Return cached results if available
        if (isset($this->authorizedToolsCache[$cacheKey])) {
            return $this->authorizedToolsCache[$cacheKey];
        }

        // Check toolbox-level authorization first
        if (! $this->authorize($context)) {
            $this->authorizedToolsCache[$cacheKey] = [];

            return [];
        }

        $user = $this->getUserFromContext($context);
        $authorizedTools = [];

        foreach ($this->tools as $toolClass) {
            // Check conditional inclusion
            if (! $this->shouldIncludeTool($toolClass, $context)) {
                continue;
            }

            // Check per-tool gate authorization
            if (isset($this->toolGates[$toolClass])) {
                if (! $this->checkToolGateAuthorization($toolClass, $user)) {
                    continue;
                }
            }

            // Check per-tool policy authorization
            if (isset($this->toolPolicies[$toolClass])) {
                if (! $this->checkToolPolicyAuthorization($toolClass, $user)) {
                    continue;
                }
            }

            // Instantiate the tool
            $tool = $this->instantiateTool($toolClass);
            if ($tool !== null) {
                $toolName = $tool->definition()['name'];
                $authorizedTools[$toolName] = $tool;
            }
        }

        $this->authorizedToolsCache[$cacheKey] = $authorizedTools;

        return $authorizedTools;
    }

    /**
     * Determine if a tool should be included based on context conditions.
     *
     * Override this method in subclasses to implement conditional tool inclusion.
     *
     * @param  class-string<ToolInterface>  $toolClass
     */
    protected function shouldIncludeTool(string $toolClass, AgentContext $context): bool
    {
        return true;
    }

    /**
     * Extract user data from the agent context.
     *
     * @return mixed The user data for authorization checks
     */
    protected function getUserFromContext(AgentContext $context): mixed
    {
        // Try to get user data in order of preference
        $userData = $context->getState('user_data');
        if ($userData !== null) {
            return $userData;
        }

        // Fallback to constructing user array from individual fields
        $userId = $context->getState('user_id');
        if ($userId !== null) {
            return [
                'id' => $userId,
                'email' => $context->getState('user_email'),
                'name' => $context->getState('user_name'),
            ];
        }

        return null;
    }

    /**
     * Check authorization using the configured gate.
     */
    protected function checkGateAuthorization(mixed $user): bool
    {
        // Use empty array as anonymous user if no user provided
        // This allows gates that don't require user info to still be checked
        $effectiveUser = $user ?? [];

        try {
            return Gate::forUser($effectiveUser)->allows($this->gate);
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Check authorization using the configured policy.
     */
    protected function checkPolicyAuthorization(mixed $user): bool
    {
        if ($user === null) {
            return false;
        }

        // Register the policy if not already registered
        if (! Gate::getPolicyFor($this)) {
            Gate::policy(static::class, $this->policy);
        }

        return Gate::forUser($user)->check($this->policyAbility, $this);
    }

    /**
     * Check per-tool gate authorization.
     *
     * @param  class-string<ToolInterface>  $toolClass
     */
    protected function checkToolGateAuthorization(string $toolClass, mixed $user): bool
    {
        $gate = $this->toolGates[$toolClass];

        // Use empty array as anonymous user if no user provided
        $effectiveUser = $user ?? [];

        try {
            return Gate::forUser($effectiveUser)->allows($gate);
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Check per-tool policy authorization.
     *
     * @param  class-string<ToolInterface>  $toolClass
     */
    protected function checkToolPolicyAuthorization(string $toolClass, mixed $user): bool
    {
        if ($user === null) {
            return false;
        }

        [$policyClass, $ability] = $this->toolPolicies[$toolClass];

        return Gate::forUser($user)->check($ability, $toolClass);
    }

    /**
     * Instantiate a tool from its class name.
     *
     * @param  class-string<ToolInterface>  $toolClass
     */
    protected function instantiateTool(string $toolClass): ?ToolInterface
    {
        if (! class_exists($toolClass)) {
            return null;
        }

        if (! is_subclass_of($toolClass, ToolInterface::class)) {
            return null;
        }

        // Resolve from container for dependency injection support
        return app($toolClass);
    }

    /**
     * Clear the authorized tools cache.
     */
    public function clearCache(): void
    {
        $this->authorizedToolsCache = [];
    }

    /**
     * Get the gate name if configured.
     */
    public function getGate(): ?string
    {
        return $this->gate;
    }

    /**
     * Get the policy class if configured.
     */
    public function getPolicy(): ?string
    {
        return $this->policy;
    }

    /**
     * Get the per-tool gate mappings.
     *
     * @return array<class-string<ToolInterface>, string>
     */
    public function getToolGates(): array
    {
        return $this->toolGates;
    }
}
