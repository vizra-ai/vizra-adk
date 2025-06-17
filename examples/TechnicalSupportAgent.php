<?php

namespace Vizra\VizraAdk\Examples;

use Vizra\VizraAdk\Agents\BaseLlmAgent;

/**
 * Technical support sub-agent specialized in handling technical issues.
 */
class TechnicalSupportAgent extends BaseLlmAgent
{
    protected string $name = 'technical-support';
    protected string $description = 'Specialized agent for technical support and troubleshooting';
    protected string $instructions = 'You are a technical support specialist. You excel at diagnosing technical problems, providing step-by-step troubleshooting guides, and explaining technical concepts in simple terms. Focus on practical solutions and clear instructions.';
    protected string $model = 'gpt-4o';

    /**
     * Tools this agent can use.
     *
     * @var array<class-string<ToolInterface>>
     */
    protected array $tools = [
        // Add technical support specific tools here
        // e.g., SystemDiagnosticTool::class, LogAnalyzerTool::class
    ];

    protected function registerSubAgents(): array
    {
        return [
            // Technical support could have its own sub-agents for specialized areas
            // e.g., 'network_specialist' => NetworkSpecialistAgent::class,
        ];
    }
}
