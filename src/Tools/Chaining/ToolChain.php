<?php

namespace Vizra\VizraADK\Tools\Chaining;

use Closure;
use InvalidArgumentException;
use Vizra\VizraADK\Contracts\ChainableToolInterface;
use Vizra\VizraADK\Contracts\ToolInterface;
use Vizra\VizraADK\Memory\AgentMemory;
use Vizra\VizraADK\System\AgentContext;

/**
 * ToolChain - Compose multiple tools into a sequential pipeline.
 *
 * Allows chaining tools together where the output of one tool
 * can be transformed and passed as input to the next tool.
 *
 * @example
 * ```php
 * $result = ToolChain::create()
 *     ->pipe(FetchUserTool::class)
 *     ->transform(fn($result) => json_decode($result, true))
 *     ->pipe(EnrichUserTool::class, fn($data) => ['user_id' => $data['id']])
 *     ->pipe(ValidateUserTool::class)
 *     ->execute(['user_id' => 123], $context, $memory);
 * ```
 */
class ToolChain
{
    /**
     * @var array<ToolChainStep>
     */
    protected array $steps = [];

    /**
     * Whether to stop execution on first error.
     */
    protected bool $stopOnError = true;

    /**
     * Optional name for this chain (for tracing/debugging).
     */
    protected ?string $name = null;

    /**
     * Callback to execute before each step.
     */
    protected ?Closure $beforeStep = null;

    /**
     * Callback to execute after each step.
     */
    protected ?Closure $afterStep = null;

    /**
     * Create a new ToolChain instance.
     */
    public static function create(?string $name = null): static
    {
        $chain = new static;
        $chain->name = $name;

        return $chain;
    }

    /**
     * Add a tool to the chain.
     *
     * @param  class-string<ToolInterface>|ToolInterface  $tool  The tool class or instance.
     * @param  Closure|null  $argumentMapper  Optional function to map previous result to tool arguments.
     *                                        Signature: fn(mixed $previousResult, array $initialArgs): array
     */
    public function pipe(string|ToolInterface $tool, ?Closure $argumentMapper = null): static
    {
        $this->steps[] = new ToolChainStep(
            type: ToolChainStep::TYPE_TOOL,
            tool: $tool,
            argumentMapper: $argumentMapper
        );

        return $this;
    }

    /**
     * Add a transformation step between tools.
     *
     * @param  Closure  $transformer  Function to transform the result.
     *                                Signature: fn(mixed $previousResult): mixed
     */
    public function transform(Closure $transformer): static
    {
        $this->steps[] = new ToolChainStep(
            type: ToolChainStep::TYPE_TRANSFORM,
            transformer: $transformer
        );

        return $this;
    }

    /**
     * Add a conditional branch - only execute next step if condition is true.
     *
     * @param  Closure  $condition  Signature: fn(mixed $previousResult): bool
     * @param  Closure|null  $otherwise  Optional alternative transformation if condition is false.
     */
    public function when(Closure $condition, ?Closure $otherwise = null): static
    {
        $this->steps[] = new ToolChainStep(
            type: ToolChainStep::TYPE_CONDITION,
            condition: $condition,
            otherwise: $otherwise
        );

        return $this;
    }

    /**
     * Add a tap step - execute callback without modifying the result.
     * Useful for logging, debugging, or side effects.
     *
     * @param  Closure  $callback  Signature: fn(mixed $currentResult, int $stepIndex): void
     */
    public function tap(Closure $callback): static
    {
        $this->steps[] = new ToolChainStep(
            type: ToolChainStep::TYPE_TAP,
            tapCallback: $callback
        );

        return $this;
    }

    /**
     * Set whether to stop execution on first error.
     */
    public function stopOnError(bool $stop = true): static
    {
        $this->stopOnError = $stop;

        return $this;
    }

    /**
     * Continue execution even if a step fails.
     */
    public function continueOnError(): static
    {
        return $this->stopOnError(false);
    }

    /**
     * Set a callback to run before each step.
     *
     * @param  Closure  $callback  Signature: fn(ToolChainStep $step, int $index, mixed $currentValue): void
     */
    public function beforeEachStep(Closure $callback): static
    {
        $this->beforeStep = $callback;

        return $this;
    }

    /**
     * Set a callback to run after each step.
     *
     * @param  Closure  $callback  Signature: fn(ToolChainStep $step, int $index, mixed $result): void
     */
    public function afterEachStep(Closure $callback): static
    {
        $this->afterStep = $callback;

        return $this;
    }

    /**
     * Execute the tool chain.
     *
     * @param  array  $initialArguments  Initial arguments for the first tool.
     * @param  AgentContext  $context  The agent context.
     * @param  AgentMemory  $memory  The agent memory.
     * @return ToolChainResult The result of the chain execution.
     */
    public function execute(array $initialArguments, AgentContext $context, AgentMemory $memory): ToolChainResult
    {
        $result = new ToolChainResult($this->name);
        $currentValue = $initialArguments;
        $skipRemaining = false;

        foreach ($this->steps as $index => $step) {
            if ($skipRemaining) {
                $result->addSkippedStep($index, $step);

                continue;
            }

            // Before step callback
            if ($this->beforeStep) {
                ($this->beforeStep)($step, $index, $currentValue);
            }

            $startTime = microtime(true);

            try {
                $stepResult = $this->executeStep(
                    $step,
                    $currentValue,
                    $initialArguments,
                    $context,
                    $memory,
                    $index
                );

                $duration = microtime(true) - $startTime;

                // Handle condition step results
                if ($step->type === ToolChainStep::TYPE_CONDITION) {
                    if ($stepResult['skip']) {
                        $skipRemaining = true;
                        $currentValue = $stepResult['value'];
                        $result->addStep($index, $step, $currentValue, $duration, true);

                        continue;
                    }
                    $currentValue = $stepResult['value'];
                } else {
                    $currentValue = $stepResult;
                }

                $result->addStep($index, $step, $currentValue, $duration);

                // After step callback
                if ($this->afterStep) {
                    ($this->afterStep)($step, $index, $currentValue);
                }
            } catch (\Throwable $e) {
                $duration = microtime(true) - $startTime;
                $result->addError($index, $step, $e, $duration);

                if ($this->stopOnError) {
                    break;
                }
            }
        }

        $result->setFinalValue($currentValue);

        return $result;
    }

    /**
     * Execute a single step in the chain.
     */
    protected function executeStep(
        ToolChainStep $step,
        mixed $currentValue,
        array $initialArguments,
        AgentContext $context,
        AgentMemory $memory,
        int $index
    ): mixed {
        return match ($step->type) {
            ToolChainStep::TYPE_TOOL => $this->executeToolStep($step, $currentValue, $initialArguments, $context, $memory),
            ToolChainStep::TYPE_TRANSFORM => $this->executeTransformStep($step, $currentValue),
            ToolChainStep::TYPE_CONDITION => $this->executeConditionStep($step, $currentValue),
            ToolChainStep::TYPE_TAP => $this->executeTapStep($step, $currentValue, $index),
            default => throw new InvalidArgumentException("Unknown step type: {$step->type}"),
        };
    }

    /**
     * Execute a tool step.
     *
     * If the tool implements ChainableToolInterface and no custom argument mapper
     * is provided, it will use the tool's built-in chain methods for input/output
     * transformation.
     */
    protected function executeToolStep(
        ToolChainStep $step,
        mixed $currentValue,
        array $initialArguments,
        AgentContext $context,
        AgentMemory $memory
    ): mixed {
        $tool = $step->getToolInstance();
        $isChainable = $tool instanceof ChainableToolInterface;

        // Determine arguments for this tool
        if ($step->argumentMapper) {
            // Custom argument mapper takes precedence
            $arguments = ($step->argumentMapper)($currentValue, $initialArguments);
        } elseif ($isChainable) {
            // Use chainable tool's input acceptance
            $arguments = $tool->acceptChainInput($currentValue, $initialArguments);
        } else {
            // Default: use previous result if array, otherwise initial args
            $arguments = is_array($currentValue) ? $currentValue : $initialArguments;
        }

        $rawOutput = $tool->execute($arguments, $context, $memory);

        // Transform output for chaining if tool is chainable
        if ($isChainable) {
            return $tool->transformOutputForChain($rawOutput);
        }

        return $rawOutput;
    }

    /**
     * Execute a transform step.
     */
    protected function executeTransformStep(ToolChainStep $step, mixed $currentValue): mixed
    {
        return ($step->transformer)($currentValue);
    }

    /**
     * Execute a condition step.
     */
    protected function executeConditionStep(ToolChainStep $step, mixed $currentValue): array
    {
        $conditionMet = ($step->condition)($currentValue);

        if (! $conditionMet && $step->otherwise) {
            return [
                'skip' => true,
                'value' => ($step->otherwise)($currentValue),
            ];
        }

        return [
            'skip' => ! $conditionMet,
            'value' => $currentValue,
        ];
    }

    /**
     * Execute a tap step.
     */
    protected function executeTapStep(ToolChainStep $step, mixed $currentValue, int $index): mixed
    {
        ($step->tapCallback)($currentValue, $index);

        return $currentValue;
    }

    /**
     * Get all steps in the chain.
     *
     * @return array<ToolChainStep>
     */
    public function getSteps(): array
    {
        return $this->steps;
    }

    /**
     * Get the chain name.
     */
    public function getName(): ?string
    {
        return $this->name;
    }

    /**
     * Check if the chain has any steps.
     */
    public function isEmpty(): bool
    {
        return empty($this->steps);
    }

    /**
     * Get the number of steps in the chain.
     */
    public function count(): int
    {
        return count($this->steps);
    }
}
