<?php

namespace Vizra\VizraADK\Tools;

use Vizra\VizraADK\Agents\BaseMediaAgent;
use Vizra\VizraADK\Contracts\ToolInterface;
use Vizra\VizraADK\Memory\AgentMemory;
use Vizra\VizraADK\System\AgentContext;
use Vizra\VizraADK\Traits\HasLogging;

/**
 * Tool that allows LLM agents to delegate to media generation agents.
 *
 * This bridges the gap between LLM agents and media agents, allowing
 * an LLM agent to use ImageAgent or AudioAgent as sub-agents.
 *
 * Usage in an LLM agent:
 * ```php
 * class CreativeAgent extends BaseLlmAgent
 * {
 *     protected array $tools = [
 *         DelegateToMediaAgentTool::class,
 *     ];
 *
 *     protected array $mediaAgents = [
 *         ImageAgent::class,
 *         AudioAgent::class,
 *     ];
 * }
 * ```
 */
class DelegateToMediaAgentTool implements ToolInterface
{
    use HasLogging;

    protected BaseMediaAgent $mediaAgent;

    protected string $toolName;

    public function __construct(BaseMediaAgent $mediaAgent, ?string $toolName = null)
    {
        $this->mediaAgent = $mediaAgent;
        $this->toolName = $toolName ?? $this->mediaAgent->getName();
    }

    /**
     * Create an instance for ImageAgent
     */
    public static function forImage(): static
    {
        return new static(
            app(\Vizra\VizraADK\Agents\ImageAgent::class),
            'generate_image'
        );
    }

    /**
     * Create an instance for AudioAgent
     */
    public static function forAudio(): static
    {
        return new static(
            app(\Vizra\VizraADK\Agents\AudioAgent::class),
            'generate_audio'
        );
    }

    /**
     * Get the tool definition from the media agent
     */
    public function definition(): array
    {
        if (method_exists($this->mediaAgent, 'toToolDefinition')) {
            $definition = $this->mediaAgent->toToolDefinition();
            // Override name if custom name provided
            if ($this->toolName) {
                $definition['name'] = $this->toolName;
            }
            return $definition;
        }

        // Fallback generic definition
        return [
            'name' => $this->toolName ?? 'delegate_to_media_agent',
            'description' => $this->mediaAgent->getDescription(),
            'parameters' => [
                'type' => 'object',
                'properties' => [
                    'input' => [
                        'type' => 'string',
                        'description' => 'The input for the media agent (prompt for images, text for audio)',
                    ],
                ],
                'required' => ['input'],
            ],
        ];
    }

    /**
     * Execute the media agent
     */
    public function execute(array $arguments, AgentContext $context, AgentMemory $memory): string
    {
        $this->logInfo('Delegating to media agent', [
            'media_agent' => get_class($this->mediaAgent),
            'arguments' => $arguments,
        ], 'tools');

        if (method_exists($this->mediaAgent, 'executeFromToolCall')) {
            return $this->mediaAgent->executeFromToolCall($arguments, $context);
        }

        // Fallback: direct execution
        try {
            $input = $arguments['input'] ?? $arguments['prompt'] ?? $arguments['text'] ?? '';
            $context->setState('media_options', $arguments);
            $response = $this->mediaAgent->execute($input, $context);

            // Auto-store
            if (method_exists($response, 'store')) {
                $response->store();
            }

            return json_encode([
                'success' => true,
                'url' => method_exists($response, 'url') ? $response->url() : null,
                'path' => method_exists($response, 'path') ? $response->path() : null,
            ]);
        } catch (\Exception $e) {
            $this->logError('Media agent delegation failed', [
                'error' => $e->getMessage(),
            ], 'tools');

            return json_encode([
                'success' => false,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
