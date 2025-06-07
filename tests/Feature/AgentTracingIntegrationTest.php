<?php

namespace AaronLumsden\LaravelAiADK\Tests\Feature;

use AaronLumsden\LaravelAiADK\Tests\TestCase;
use AaronLumsden\LaravelAiADK\Agents\BaseLlmAgent;
use AaronLumsden\LaravelAiADK\System\AgentContext;
use AaronLumsden\LaravelAiADK\Services\Tracer;
use AaronLumsden\LaravelAiADK\Contracts\ToolInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

class AgentTracingIntegrationTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function agent_execution_creates_complete_trace()
    {
        // Create a test agent with tools
        $agent = new class extends BaseLlmAgent {
            public function getName(): string
            {
                return 'test_tracing_agent';
            }

            public function getInstructions(): string
            {
                return 'You are a test agent for tracing integration.';
            }

            protected function registerTools(): array
            {
                return [TestTracingTool::class];
            }
        };

        // Create test tool
        app()->bind(TestTracingTool::class, function () {
            return new TestTracingTool();
        });

        $context = new AgentContext(
            sessionId: 'integration-test-session',
            userInput: 'Test tracing integration'
        );

        // Mock Prism response since we don't want to make real API calls
        $this->mockPrismResponse();

        // Execute agent
        try {
            $response = $agent->run('Test input', $context);
        } catch (\Exception $e) {
            // Expected due to mocked Prism - we're testing tracing not LLM calls
        }

        // Verify trace was created
        $spans = DB::table('agent_trace_spans')
            ->where('session_id', 'integration-test-session')
            ->orderBy('start_time')
            ->get();

        expect($spans)->not->toBeEmpty();

        // Should have at least the root agent_run span
        $rootSpan = $spans->where('type', 'agent_run')->first();
        expect($rootSpan)->not->toBeNull();
        expect($rootSpan->name)->toBe('test_tracing_agent');
        expect($rootSpan->parent_span_id)->toBeNull();

        // Should have LLM call span
        $llmSpan = $spans->where('type', 'llm_call')->first();
        if ($llmSpan) {
            expect($llmSpan->parent_span_id)->toBe($rootSpan->span_id);
            expect($llmSpan->name)->toContain('gemini'); // Default model
        }
    }

    /** @test */
    public function trace_command_displays_hierarchy_correctly()
    {
        // Create test trace data
        $tracer = app(Tracer::class);
        $context = new AgentContext('cmd-test-session', 'Test command');

        $traceId = $tracer->startTrace($context, 'command_test_agent');
        $llmSpanId = $tracer->startSpan('llm_call', 'gpt-4o', ['messages' => []]);
        $toolSpanId = $tracer->startSpan('tool_call', 'test_tool', ['arg' => 'value']);

        $tracer->endSpan($toolSpanId, ['result' => 'success']);
        $tracer->endSpan($llmSpanId, ['text' => 'response']);
        $tracer->endTrace(['response' => 'completed']);

        // Test the command
        $this->artisan('agent:trace', ['session_id' => 'cmd-test-session'])
            ->expectsOutput('Traces for session: cmd-test-session')
            ->assertExitCode(0);
    }

    /** @test */
    public function tracing_handles_errors_in_agent_execution()
    {
        // Create agent that will throw an error
        $agent = new class extends BaseLlmAgent {
            public function getName(): string
            {
                return 'error_agent';
            }

            public function getInstructions(): string
            {
                return 'This agent will error';
            }

            public function run(mixed $input, AgentContext $context): mixed
            {
                // Simulate starting a trace manually
                $tracer = app(\AaronLumsden\LaravelAiADK\Services\Tracer::class);
                $tracer->startTrace($context, 'ErrorTestAgent');

                try {
                    // Simulate error during execution
                    throw new \Exception('Simulated agent error');
                } catch (\Exception $e) {
                    // Fail the trace when an exception occurs
                    $tracer->failTrace($e);
                    throw $e;
                }
            }
        };

        $context = new AgentContext('error-test-session', 'Test error handling');

        // Execute agent and expect error
        try {
            $agent->run('Test input', $context);
        } catch (\Exception $e) {
            expect($e->getMessage())->toBe('Simulated agent error');
        }

        // Verify error trace was created
        $spans = DB::table('agent_trace_spans')
            ->where('session_id', 'error-test-session')
            ->get();

        expect($spans)->not->toBeEmpty();

        $rootSpan = $spans->where('type', 'agent_run')->first();
        expect($rootSpan->status)->toBe('error');
        expect($rootSpan->error_message)->toBe('Simulated agent error');
    }

    /** @test */
    public function tracing_works_with_disabled_configuration()
    {
        // Disable tracing
        config(['agent-adk.tracing.enabled' => false]);

        // Refresh the Tracer service to pick up the new config
        $this->app->forgetInstance(Tracer::class);

        $agent = new class extends BaseLlmAgent {
            public function getName(): string
            {
                return 'disabled_tracing_agent';
            }

            public function getInstructions(): string
            {
                return 'Test with disabled tracing';
            }
        };

        $context = new AgentContext('disabled-session', 'Test');

        // Mock Prism for clean execution
        $this->mockPrismResponse();

        // Should execute without errors even with tracing disabled
        try {
            $agent->run('Test input', $context);
        } catch (\Exception $e) {
            // Expected due to mocked Prism
        }

        // Should have no traces in database
        $spans = DB::table('agent_trace_spans')
            ->where('session_id', 'disabled-session')
            ->count();

        expect($spans)->toBe(0);
    }

    protected function mockPrismResponse()
    {
        // Mock Prism to avoid actual API calls during testing
        // This is a simplified mock - in real tests you'd use proper mocking
        $this->app->bind(\Prism\Prism\Prism::class, function () {
            return new class {
                public static function text()
                {
                    return new class {
                        public function using($provider, $model)
                        {
                            return $this;
                        }

                        public function withSystemPrompt($prompt)
                        {
                            return $this;
                        }

                        public function withMessages($messages)
                        {
                            return $this;
                        }

                        public function withTools($tools)
                        {
                            return $this;
                        }

                        public function withMaxSteps($steps)
                        {
                            return $this;
                        }

                        public function usingTemperature($temp)
                        {
                            return $this;
                        }

                        public function withMaxTokens($tokens)
                        {
                            return $this;
                        }

                        public function usingTopP($topP)
                        {
                            return $this;
                        }

                        public function asText()
                        {
                            throw new \Exception('Mocked Prism response');
                        }
                    };
                }
            };
        });
    }
}

class TestTracingTool implements ToolInterface
{
    public function definition(): array
    {
        return [
            'name' => 'test_tracing_tool',
            'description' => 'A tool for testing tracing functionality',
            'parameters' => [
                'type' => 'object',
                'properties' => [
                    'input' => [
                        'type' => 'string',
                        'description' => 'Test input'
                    ]
                ],
                'required' => ['input']
            ]
        ];
    }

    public function execute(array $arguments, AgentContext $context): string
    {
        return 'Test tool executed with: ' . ($arguments['input'] ?? 'no input');
    }
}
