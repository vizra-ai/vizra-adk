<?php

use Vizra\VizraADK\Agents\BaseLlmAgent;
use Vizra\VizraADK\Memory\AgentMemory;
use Vizra\VizraADK\System\AgentContext;
use Vizra\VizraADK\Tools\DelegateToSubAgentTool;

describe('Sub-Agent Delegation Hooks', function () {
    beforeEach(function () {
        // Create test agents with delegation hooks
        $this->parentAgent = new class extends BaseLlmAgent
        {
            public string $beforeHookCalled = '';

            public string $afterHookCalled = '';

            public array $beforeHookArgs = [];

            public array $afterHookArgs = [];

            public function getName(): string
            {
                return 'parent-agent';
            }

            public function getInstructions(): string
            {
                return 'Parent agent';
            }

            public function getTools(): array
            {
                return [];
            }

            public function beforeSubAgentDelegation(string $subAgentName, string $taskInput, string $contextSummary, AgentContext $parentContext): array
            {
                $this->beforeHookCalled = 'called';
                $this->beforeHookArgs = [$subAgentName, $taskInput, $contextSummary];

                // Modify the task input to test hook functionality
                $modifiedTaskInput = "MODIFIED: {$taskInput}";

                return [$subAgentName, $modifiedTaskInput, $contextSummary];
            }

            public function afterSubAgentDelegation(string $subAgentName, string $taskInput, string $subAgentResult, AgentContext $parentContext, AgentContext $subAgentContext): string
            {
                $this->afterHookCalled = 'called';
                $this->afterHookArgs = [$subAgentName, $taskInput, $subAgentResult];

                // Process the result to test hook functionality
                return "PROCESSED: {$subAgentResult}";
            }
        };

        $this->subAgent = new class extends BaseLlmAgent
        {
            public function getName(): string
            {
                return 'sub-agent';
            }

            public function getInstructions(): string
            {
                return 'Sub agent';
            }

            public function getTools(): array
            {
                return [];
            }

            public function execute(mixed $input, AgentContext $context): mixed
            {
                return "Sub-agent response to: {$input}";
            }
        };

        // Create a mock agent for AgentMemory
        $this->mockAgent = Mockery::mock(BaseLlmAgent::class);
        $this->mockAgent->shouldReceive('getName')->andReturn('test-agent');
    });

    afterEach(function () {
        Mockery::close();
    });

    it('calls beforeSubAgentDelegation hook with correct parameters', function () {
        // Mock the parent agent's methods
        $parentAgent = $this->mock(BaseLlmAgent::class, function ($mock) {
            $mock->shouldReceive('getName')->andReturn('parent-agent');
            $mock->shouldReceive('getLoadedSubAgents')->andReturn(['sub-agent' => $this->subAgent]);
            $mock->shouldReceive('getSubAgent')->with('sub-agent')->andReturn($this->subAgent);
            $mock->shouldReceive('beforeSubAgentDelegation')
                ->once()
                ->with('sub-agent', 'original task', 'context summary', \Mockery::type(AgentContext::class))
                ->andReturn(['sub-agent', 'MODIFIED: original task', 'context summary']);
            $mock->shouldReceive('afterSubAgentDelegation')
                ->once()
                ->andReturn('PROCESSED: Sub-agent response to: MODIFIED: original task');
        });

        $tool = new DelegateToSubAgentTool($parentAgent);
        $context = new AgentContext('test-session', 'test input');

        $arguments = [
            'sub_agent_name' => 'sub-agent',
            'task_input' => 'original task',
            'context_summary' => 'context summary',
        ];

        $memory = new AgentMemory($this->mockAgent);
        $result = $tool->execute($arguments, $context, $memory);
        $decodedResult = json_decode($result, true);

        expect($decodedResult['success'])->toBe(true);
        expect($decodedResult['result'])->toBe('PROCESSED: Sub-agent response to: MODIFIED: original task');
    });

    it('calls afterSubAgentDelegation hook with correct parameters', function () {
        $parentAgent = $this->mock(BaseLlmAgent::class, function ($mock) {
            $mock->shouldReceive('getName')->andReturn('parent-agent');
            $mock->shouldReceive('getLoadedSubAgents')->andReturn(['sub-agent' => $this->subAgent]);
            $mock->shouldReceive('getSubAgent')->with('sub-agent')->andReturn($this->subAgent);
            $mock->shouldReceive('beforeSubAgentDelegation')
                ->andReturn(['sub-agent', 'task input', '']);
            $mock->shouldReceive('afterSubAgentDelegation')
                ->once()
                ->with(
                    'sub-agent',
                    'task input',
                    'Sub-agent response to: task input',
                    \Mockery::type(AgentContext::class),
                    \Mockery::type(AgentContext::class)
                )
                ->andReturn('PROCESSED: Sub-agent response to: task input');
        });

        $tool = new DelegateToSubAgentTool($parentAgent);
        $context = new AgentContext('test-session', 'test input');

        $arguments = [
            'sub_agent_name' => 'sub-agent',
            'task_input' => 'task input',
        ];

        $memory = new AgentMemory($this->mockAgent);
        $result = $tool->execute($arguments, $context, $memory);
        $decodedResult = json_decode($result, true);

        expect($decodedResult['success'])->toBe(true);
        expect($decodedResult['result'])->toBe('PROCESSED: Sub-agent response to: task input');
    });

    it('allows beforeSubAgentDelegation hook to modify delegation parameters', function () {
        $parentAgent = new class extends BaseLlmAgent
        {
            public function getName(): string
            {
                return 'parent-agent';
            }

            public function getInstructions(): string
            {
                return 'Parent agent';
            }

            public function getTools(): array
            {
                return [];
            }

            public function beforeSubAgentDelegation(string $subAgentName, string $taskInput, string $contextSummary, AgentContext $parentContext): array
            {
                // Modify all parameters
                return [
                    'modified-sub-agent',
                    'ENHANCED: '.$taskInput,
                    'ENRICHED: '.$contextSummary,
                ];
            }

            public function afterSubAgentDelegation(string $subAgentName, string $taskInput, string $subAgentResult, AgentContext $parentContext, AgentContext $subAgentContext): string
            {
                return $subAgentResult;
            }

            public function getLoadedSubAgents(): array
            {
                return ['modified-sub-agent' => new class extends BaseLlmAgent
                {
                    public function getName(): string
                    {
                        return 'modified-sub-agent';
                    }

                    public function getInstructions(): string
                    {
                        return 'Modified sub agent';
                    }

                    public function getTools(): array
                    {
                        return [];
                    }

                    public function execute(mixed $input, AgentContext $context): mixed
                    {
                        return "Modified response to: {$input}";
                    }
                }];
            }

            public function getSubAgent(string $name): ?BaseLlmAgent
            {
                return $this->getLoadedSubAgents()[$name] ?? null;
            }
        };

        $tool = new DelegateToSubAgentTool($parentAgent);
        $context = new AgentContext('test-session', 'test input');

        $arguments = [
            'sub_agent_name' => 'original-sub-agent', // This will be modified by the hook
            'task_input' => 'original task',
            'context_summary' => 'original context',
        ];

        $memory = new AgentMemory($this->mockAgent);
        $result = $tool->execute($arguments, $context, $memory);
        $decodedResult = json_decode($result, true);

        expect($decodedResult['success'])->toBe(true);
        expect($decodedResult['sub_agent'])->toBe('modified-sub-agent');
        expect($decodedResult['task_input'])->toBe('ENHANCED: original task');
        expect($decodedResult['result'])->toBe('Modified response to: ENHANCED: original task');
    });

    it('allows afterSubAgentDelegation hook to process and modify results', function () {
        $parentAgent = new class extends BaseLlmAgent
        {
            public function getName(): string
            {
                return 'parent-agent';
            }

            public function getInstructions(): string
            {
                return 'Parent agent';
            }

            public function getTools(): array
            {
                return [];
            }

            public function beforeSubAgentDelegation(string $subAgentName, string $taskInput, string $contextSummary, AgentContext $parentContext): array
            {
                return [$subAgentName, $taskInput, $contextSummary];
            }

            public function afterSubAgentDelegation(string $subAgentName, string $taskInput, string $subAgentResult, AgentContext $parentContext, AgentContext $subAgentContext): string
            {
                // Add metadata and processing
                return json_encode([
                    'original_result' => $subAgentResult,
                    'processed_by' => $this->getName(),
                    'delegation_timestamp' => now()->toISOString(),
                    'sub_agent_session' => $subAgentContext->getSessionId(),
                ]);
            }

            public function getLoadedSubAgents(): array
            {
                return ['sub-agent' => new class extends BaseLlmAgent
                {
                    public function getName(): string
                    {
                        return 'sub-agent';
                    }

                    public function getInstructions(): string
                    {
                        return 'Sub agent';
                    }

                    public function getTools(): array
                    {
                        return [];
                    }

                    public function execute(mixed $input, AgentContext $context): mixed
                    {
                        return 'Simple response';
                    }
                }];
            }

            public function getSubAgent(string $name): ?BaseLlmAgent
            {
                return $this->getLoadedSubAgents()[$name] ?? null;
            }
        };

        $tool = new DelegateToSubAgentTool($parentAgent);
        $context = new AgentContext('test-session', 'test input');

        $arguments = [
            'sub_agent_name' => 'sub-agent',
            'task_input' => 'test task',
        ];

        $memory = new AgentMemory($this->mockAgent);
        $result = $tool->execute($arguments, $context, $memory);
        $decodedResult = json_decode($result, true);

        expect($decodedResult['success'])->toBe(true);

        $processedResult = json_decode($decodedResult['result'], true);
        expect($processedResult['original_result'])->toBe('Simple response');
        expect($processedResult['processed_by'])->toBe('parent-agent');
        expect($processedResult)->toHaveKey('delegation_timestamp');
        expect($processedResult)->toHaveKey('sub_agent_session');
    });

    it('handles exceptions in delegation hooks gracefully', function () {
        $parentAgent = new class extends BaseLlmAgent
        {
            public function getName(): string
            {
                return 'parent-agent';
            }

            public function getInstructions(): string
            {
                return 'Parent agent';
            }

            public function getTools(): array
            {
                return [];
            }

            public function beforeSubAgentDelegation(string $subAgentName, string $taskInput, string $contextSummary, AgentContext $parentContext): array
            {
                throw new \Exception('Hook error');
            }

            public function getLoadedSubAgents(): array
            {
                return ['sub-agent' => new class extends BaseLlmAgent
                {
                    public function getName(): string
                    {
                        return 'sub-agent';
                    }

                    public function getInstructions(): string
                    {
                        return 'Sub agent';
                    }

                    public function getTools(): array
                    {
                        return [];
                    }

                    public function execute(mixed $input, AgentContext $context): mixed
                    {
                        return 'Response';
                    }
                }];
            }

            public function getSubAgent(string $name): ?BaseLlmAgent
            {
                return $this->getLoadedSubAgents()[$name] ?? null;
            }
        };

        $tool = new DelegateToSubAgentTool($parentAgent);
        $context = new AgentContext('test-session', 'test input');

        $arguments = [
            'sub_agent_name' => 'sub-agent',
            'task_input' => 'test task',
        ];

        $memory = new AgentMemory($this->mockAgent);
        $result = $tool->execute($arguments, $context, $memory);
        $decodedResult = json_decode($result, true);

        expect($decodedResult['success'])->toBe(false);
        expect($decodedResult['error'])->toContain('Hook error');
    });

    it('has default hook implementations that return unmodified values', function () {
        $agent = new class extends BaseLlmAgent
        {
            public function getName(): string
            {
                return 'test-agent';
            }

            public function getInstructions(): string
            {
                return 'Test agent';
            }

            public function getTools(): array
            {
                return [];
            }
        };

        $parentContext = new AgentContext('parent-session', 'parent input');
        $subAgentContext = new AgentContext('sub-session', 'sub input');

        // Test beforeSubAgentDelegation default implementation
        $beforeResult = $agent->beforeSubAgentDelegation('sub-agent', 'task', 'context', $parentContext);
        expect($beforeResult)->toBe(['sub-agent', 'task', 'context']);

        // Test afterSubAgentDelegation default implementation
        $afterResult = $agent->afterSubAgentDelegation('sub-agent', 'task', 'result', $parentContext, $subAgentContext);
        expect($afterResult)->toBe('result');
    });
});
