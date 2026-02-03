<?php

namespace Vizra\VizraADK\Tools\Chaining;

use Closure;
use InvalidArgumentException;
use Vizra\VizraADK\Contracts\ToolInterface;

/**
 * Represents a single step in a ToolChain.
 */
class ToolChainStep
{
    public const TYPE_TOOL = 'tool';

    public const TYPE_TRANSFORM = 'transform';

    public const TYPE_CONDITION = 'condition';

    public const TYPE_TAP = 'tap';

    /**
     * Cached tool instance.
     */
    protected ?ToolInterface $toolInstance = null;

    public function __construct(
        public readonly string $type,
        public readonly string|ToolInterface|null $tool = null,
        public readonly ?Closure $argumentMapper = null,
        public readonly ?Closure $transformer = null,
        public readonly ?Closure $condition = null,
        public readonly ?Closure $otherwise = null,
        public readonly ?Closure $tapCallback = null,
    ) {
        $this->validate();
    }

    /**
     * Validate the step configuration.
     */
    protected function validate(): void
    {
        match ($this->type) {
            self::TYPE_TOOL => $this->validateToolStep(),
            self::TYPE_TRANSFORM => $this->validateTransformStep(),
            self::TYPE_CONDITION => $this->validateConditionStep(),
            self::TYPE_TAP => $this->validateTapStep(),
            default => throw new InvalidArgumentException("Invalid step type: {$this->type}"),
        };
    }

    protected function validateToolStep(): void
    {
        if ($this->tool === null) {
            throw new InvalidArgumentException('Tool step requires a tool class or instance.');
        }

        if (is_string($this->tool) && ! class_exists($this->tool)) {
            throw new InvalidArgumentException("Tool class does not exist: {$this->tool}");
        }

        if (is_string($this->tool) && ! is_subclass_of($this->tool, ToolInterface::class)) {
            throw new InvalidArgumentException("Tool class must implement ToolInterface: {$this->tool}");
        }

        if ($this->tool instanceof ToolInterface === false && is_object($this->tool)) {
            throw new InvalidArgumentException('Tool must be a class string or ToolInterface instance.');
        }
    }

    protected function validateTransformStep(): void
    {
        if ($this->transformer === null) {
            throw new InvalidArgumentException('Transform step requires a transformer closure.');
        }
    }

    protected function validateConditionStep(): void
    {
        if ($this->condition === null) {
            throw new InvalidArgumentException('Condition step requires a condition closure.');
        }
    }

    protected function validateTapStep(): void
    {
        if ($this->tapCallback === null) {
            throw new InvalidArgumentException('Tap step requires a callback closure.');
        }
    }

    /**
     * Get the tool instance for tool steps.
     */
    public function getToolInstance(): ToolInterface
    {
        if ($this->type !== self::TYPE_TOOL) {
            throw new InvalidArgumentException('Cannot get tool instance for non-tool step.');
        }

        if ($this->toolInstance !== null) {
            return $this->toolInstance;
        }

        if ($this->tool instanceof ToolInterface) {
            $this->toolInstance = $this->tool;
        } else {
            // Resolve from container to support dependency injection
            $this->toolInstance = app($this->tool);
        }

        return $this->toolInstance;
    }

    /**
     * Get the tool class name (if applicable).
     */
    public function getToolClass(): ?string
    {
        if ($this->type !== self::TYPE_TOOL) {
            return null;
        }

        if (is_string($this->tool)) {
            return $this->tool;
        }

        return $this->tool::class;
    }

    /**
     * Get a human-readable description of this step.
     */
    public function describe(): string
    {
        return match ($this->type) {
            self::TYPE_TOOL => 'Tool: '.($this->getToolClass() ?? 'unknown'),
            self::TYPE_TRANSFORM => 'Transform',
            self::TYPE_CONDITION => 'Condition'.($this->otherwise ? ' (with otherwise)' : ''),
            self::TYPE_TAP => 'Tap',
            default => 'Unknown',
        };
    }

    /**
     * Check if this step is a tool step.
     */
    public function isTool(): bool
    {
        return $this->type === self::TYPE_TOOL;
    }

    /**
     * Check if this step is a transform step.
     */
    public function isTransform(): bool
    {
        return $this->type === self::TYPE_TRANSFORM;
    }

    /**
     * Check if this step is a condition step.
     */
    public function isCondition(): bool
    {
        return $this->type === self::TYPE_CONDITION;
    }

    /**
     * Check if this step is a tap step.
     */
    public function isTap(): bool
    {
        return $this->type === self::TYPE_TAP;
    }
}
