<?php

namespace Vizra\VizraADK\Contracts;

use Vizra\VizraADK\System\AgentContext;

/**
 * Interface ToolboxInterface
 *
 * Defines the contract for toolboxes that group related tools together.
 * Toolboxes can be authorized via Laravel Gates or Policies.
 */
interface ToolboxInterface
{
    /**
     * Get the toolbox's unique name identifier.
     *
     * @return string The toolbox name (snake_case recommended)
     */
    public function name(): string;

    /**
     * Get the toolbox's human-readable description.
     *
     * @return string Description of what tools this toolbox contains
     */
    public function description(): string;

    /**
     * Get the list of tool class names in this toolbox.
     *
     * @return array<class-string<ToolInterface>> Array of tool class names
     */
    public function tools(): array;

    /**
     * Check if the toolbox is authorized for the given context.
     *
     * This method checks toolbox-level authorization using Laravel Gates or Policies.
     * If no gate or policy is defined, returns true by default.
     *
     * @param  AgentContext  $context  The current agent context with user info
     * @return bool True if the toolbox is authorized, false otherwise
     */
    public function authorize(AgentContext $context): bool;

    /**
     * Get the instantiated tools that are authorized for the given context.
     *
     * This method:
     * 1. Checks toolbox-level authorization
     * 2. Filters tools by per-tool gates (if defined)
     * 3. Applies conditional inclusion logic
     * 4. Instantiates and returns authorized tools
     *
     * @param  AgentContext  $context  The current agent context
     * @return array<string, ToolInterface> Array of tool instances keyed by tool name
     */
    public function authorizedTools(AgentContext $context): array;
}
