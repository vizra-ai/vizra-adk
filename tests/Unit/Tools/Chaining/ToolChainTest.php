<?php

namespace Vizra\VizraADK\Tests\Unit\Tools\Chaining;

use InvalidArgumentException;
use Mockery;
use RuntimeException;
use Vizra\VizraADK\Agents\BaseLlmAgent;
use Vizra\VizraADK\Contracts\ToolInterface;
use Vizra\VizraADK\Memory\AgentMemory;
use Vizra\VizraADK\Services\Tracer;
use Vizra\VizraADK\System\AgentContext;
use Vizra\VizraADK\Tests\Fixtures\Tools\ChainableFetchUserTool;
use Vizra\VizraADK\Tests\Fixtures\Tools\EnrichUserTool;
use Vizra\VizraADK\Tests\Fixtures\Tools\FailingTool;
use Vizra\VizraADK\Tests\Fixtures\Tools\FetchUserTool;
use Vizra\VizraADK\Tests\Fixtures\Tools\ValidateUserTool;
use Vizra\VizraADK\Tests\TestCase;
use Vizra\VizraADK\Tools\Chaining\ToolChain;
use Vizra\VizraADK\Tools\Chaining\ToolChainResult;
use Vizra\VizraADK\Tools\Chaining\ToolChainStep;

class ToolChainTest extends TestCase
{
    protected AgentContext $context;

    protected AgentMemory $memory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->context = new AgentContext(
            sessionId: 'test-session-'.uniqid(),
            userInput: null,
            initialState: []
        );

        $mockAgent = Mockery::mock(BaseLlmAgent::class);
        $mockAgent->shouldReceive('getName')->andReturn('test_agent');

        $this->memory = new AgentMemory($mockAgent);
    }

    // ==========================================
    // Basic Chain Creation Tests
    // ==========================================

    public function test_can_create_empty_chain(): void
    {
        $chain = ToolChain::create();

        $this->assertTrue($chain->isEmpty());
        $this->assertEquals(0, $chain->count());
    }

    public function test_can_create_named_chain(): void
    {
        $chain = ToolChain::create('user-processing');

        $this->assertEquals('user-processing', $chain->getName());
    }

    public function test_can_add_tool_to_chain(): void
    {
        $chain = ToolChain::create()
            ->pipe(FetchUserTool::class);

        $this->assertFalse($chain->isEmpty());
        $this->assertEquals(1, $chain->count());
    }

    public function test_can_add_multiple_tools_to_chain(): void
    {
        $chain = ToolChain::create()
            ->pipe(FetchUserTool::class)
            ->pipe(EnrichUserTool::class)
            ->pipe(ValidateUserTool::class);

        $this->assertEquals(3, $chain->count());
    }

    public function test_can_add_tool_instance_to_chain(): void
    {
        $tool = new FetchUserTool;

        $chain = ToolChain::create()
            ->pipe($tool);

        $this->assertEquals(1, $chain->count());
    }

    // ==========================================
    // Chain Execution Tests
    // ==========================================

    public function test_executes_single_tool(): void
    {
        $chain = ToolChain::create()
            ->pipe(FetchUserTool::class);

        $result = $chain->execute(['user_id' => 123], $this->context, $this->memory);

        $this->assertInstanceOf(ToolChainResult::class, $result);
        $this->assertTrue($result->successful());

        $value = json_decode($result->value(), true);
        $this->assertEquals(123, $value['id']);
        $this->assertEquals('User 123', $value['name']);
    }

    public function test_executes_multiple_tools_in_sequence(): void
    {
        $chain = ToolChain::create()
            ->pipe(FetchUserTool::class)
            ->transform(fn ($result) => json_decode($result, true))
            ->pipe(EnrichUserTool::class, fn ($data) => [
                'user_id' => $data['id'],
                'name' => $data['name'],
            ]);

        $result = $chain->execute(['user_id' => 42], $this->context, $this->memory);

        $this->assertTrue($result->successful());

        $value = json_decode($result->value(), true);
        $this->assertEquals(42, $value['user_id']);
        $this->assertTrue($value['enriched']);
    }

    public function test_passes_initial_arguments_to_first_tool(): void
    {
        $chain = ToolChain::create()
            ->pipe(FetchUserTool::class);

        $result = $chain->execute(['user_id' => 999], $this->context, $this->memory);

        $value = json_decode($result->value(), true);
        $this->assertEquals(999, $value['id']);
    }

    // ==========================================
    // Transform Step Tests
    // ==========================================

    public function test_transform_step_modifies_value(): void
    {
        $chain = ToolChain::create()
            ->pipe(FetchUserTool::class)
            ->transform(fn ($result) => json_decode($result, true))
            ->transform(fn ($data) => $data['name']);

        $result = $chain->execute(['user_id' => 5], $this->context, $this->memory);

        $this->assertEquals('User 5', $result->value());
    }

    public function test_multiple_transforms_chain_correctly(): void
    {
        $chain = ToolChain::create()
            ->pipe(FetchUserTool::class)
            ->transform(fn ($result) => json_decode($result, true))
            ->transform(fn ($data) => $data['email'])
            ->transform(fn ($email) => strtoupper($email));

        $result = $chain->execute(['user_id' => 1], $this->context, $this->memory);

        $this->assertEquals('USER1@EXAMPLE.COM', $result->value());
    }

    // ==========================================
    // Argument Mapper Tests
    // ==========================================

    public function test_argument_mapper_transforms_input_for_tool(): void
    {
        $chain = ToolChain::create()
            ->pipe(FetchUserTool::class)
            ->transform(fn ($result) => json_decode($result, true))
            ->pipe(ValidateUserTool::class, fn ($data, $initial) => [
                'user_id' => $data['id'],
                'email' => $data['email'],
            ]);

        $result = $chain->execute(['user_id' => 10], $this->context, $this->memory);

        $value = json_decode($result->value(), true);
        $this->assertTrue($value['valid']);
        $this->assertEquals(10, $value['user_id']);
    }

    public function test_argument_mapper_receives_initial_arguments(): void
    {
        $receivedInitial = null;

        $chain = ToolChain::create()
            ->pipe(FetchUserTool::class)
            ->transform(fn ($r) => json_decode($r, true))
            ->pipe(ValidateUserTool::class, function ($data, $initial) use (&$receivedInitial) {
                $receivedInitial = $initial;

                return ['user_id' => $data['id']];
            });

        $chain->execute(['user_id' => 77, 'extra' => 'value'], $this->context, $this->memory);

        $this->assertEquals(['user_id' => 77, 'extra' => 'value'], $receivedInitial);
    }

    // ==========================================
    // Condition Step Tests
    // ==========================================

    public function test_when_condition_continues_if_true(): void
    {
        $chain = ToolChain::create()
            ->pipe(FetchUserTool::class)
            ->transform(fn ($r) => json_decode($r, true))
            ->when(fn ($data) => $data['status'] === 'active')
            ->transform(fn ($data) => $data['name'].' is active');

        $result = $chain->execute(['user_id' => 1], $this->context, $this->memory);

        $this->assertTrue($result->successful());
        $this->assertEquals('User 1 is active', $result->value());
    }

    public function test_when_condition_skips_remaining_if_false(): void
    {
        $executed = false;

        $chain = ToolChain::create()
            ->pipe(FetchUserTool::class)
            ->transform(fn ($r) => json_decode($r, true))
            ->when(fn ($data) => $data['status'] === 'inactive')
            ->tap(function () use (&$executed) {
                $executed = true;
            });

        $result = $chain->execute(['user_id' => 1], $this->context, $this->memory);

        $this->assertTrue($result->successful());
        $this->assertFalse($executed);
        $this->assertGreaterThan(0, $result->getSkippedStepCount());
    }

    public function test_when_with_otherwise_executes_alternative(): void
    {
        $chain = ToolChain::create()
            ->pipe(FetchUserTool::class)
            ->transform(fn ($r) => json_decode($r, true))
            ->when(
                fn ($data) => $data['status'] === 'inactive',
                fn ($data) => 'User is not inactive'
            );

        $result = $chain->execute(['user_id' => 1], $this->context, $this->memory);

        $this->assertEquals('User is not inactive', $result->value());
    }

    // ==========================================
    // Tap Step Tests
    // ==========================================

    public function test_tap_executes_without_modifying_value(): void
    {
        $tappedValue = null;

        $chain = ToolChain::create()
            ->pipe(FetchUserTool::class)
            ->tap(function ($value) use (&$tappedValue) {
                $tappedValue = $value;
            });

        $result = $chain->execute(['user_id' => 1], $this->context, $this->memory);

        $this->assertNotNull($tappedValue);
        $this->assertEquals($tappedValue, $result->value());
    }

    public function test_tap_receives_step_index(): void
    {
        $receivedIndex = null;

        $chain = ToolChain::create()
            ->pipe(FetchUserTool::class)
            ->transform(fn ($r) => $r)
            ->tap(function ($value, $index) use (&$receivedIndex) {
                $receivedIndex = $index;
            });

        $chain->execute(['user_id' => 1], $this->context, $this->memory);

        $this->assertEquals(2, $receivedIndex);
    }

    // ==========================================
    // Error Handling Tests
    // ==========================================

    public function test_stops_on_error_by_default(): void
    {
        $executed = false;

        $chain = ToolChain::create()
            ->pipe(FailingTool::class)
            ->tap(function () use (&$executed) {
                $executed = true;
            });

        $result = $chain->execute([], $this->context, $this->memory);

        $this->assertTrue($result->failed());
        $this->assertFalse($executed);
        $this->assertTrue($result->hasErrors());
    }

    public function test_continues_on_error_when_configured(): void
    {
        $executed = false;

        $chain = ToolChain::create()
            ->continueOnError()
            ->pipe(FailingTool::class)
            ->tap(function () use (&$executed) {
                $executed = true;
            });

        $result = $chain->execute([], $this->context, $this->memory);

        $this->assertTrue($result->failed());
        $this->assertTrue($executed);
    }

    public function test_get_first_error_returns_exception(): void
    {
        $chain = ToolChain::create()
            ->pipe(FailingTool::class);

        $result = $chain->execute([], $this->context, $this->memory);

        $error = $result->getFirstError();
        $this->assertInstanceOf(RuntimeException::class, $error);
        $this->assertEquals('Tool execution failed intentionally', $error->getMessage());
    }

    public function test_throw_rethrows_error(): void
    {
        $chain = ToolChain::create()
            ->pipe(FailingTool::class);

        $result = $chain->execute([], $this->context, $this->memory);

        $this->expectException(RuntimeException::class);
        $result->throw();
    }

    public function test_value_or_throw_returns_value_on_success(): void
    {
        $chain = ToolChain::create()
            ->pipe(FetchUserTool::class);

        $result = $chain->execute(['user_id' => 1], $this->context, $this->memory);

        $value = $result->valueOrThrow();
        $this->assertNotNull($value);
    }

    public function test_value_or_throw_throws_on_failure(): void
    {
        $chain = ToolChain::create()
            ->pipe(FailingTool::class);

        $result = $chain->execute([], $this->context, $this->memory);

        $this->expectException(RuntimeException::class);
        $result->valueOrThrow();
    }

    // ==========================================
    // Callbacks Tests
    // ==========================================

    public function test_before_each_step_callback(): void
    {
        $steps = [];

        $chain = ToolChain::create()
            ->beforeEachStep(function ($step, $index) use (&$steps) {
                $steps[] = ['index' => $index, 'type' => $step->type];
            })
            ->pipe(FetchUserTool::class)
            ->transform(fn ($r) => $r);

        $chain->execute(['user_id' => 1], $this->context, $this->memory);

        $this->assertCount(2, $steps);
        $this->assertEquals(0, $steps[0]['index']);
        $this->assertEquals(1, $steps[1]['index']);
    }

    public function test_after_each_step_callback(): void
    {
        $results = [];

        $chain = ToolChain::create()
            ->afterEachStep(function ($step, $index, $result) use (&$results) {
                $results[] = ['index' => $index, 'result' => $result];
            })
            ->pipe(FetchUserTool::class)
            ->transform(fn ($r) => json_decode($r, true));

        $chain->execute(['user_id' => 1], $this->context, $this->memory);

        $this->assertCount(2, $results);
        $this->assertIsArray($results[1]['result']);
    }

    // ==========================================
    // ChainableToolInterface Tests
    // ==========================================

    public function test_chainable_tool_auto_transforms_output(): void
    {
        $chain = ToolChain::create()
            ->pipe(ChainableFetchUserTool::class);

        $result = $chain->execute(['user_id' => 50], $this->context, $this->memory);

        // ChainableTool should auto-decode JSON
        $this->assertIsArray($result->value());
        $this->assertEquals(50, $result->value()['id']);
    }

    public function test_chainable_tools_auto_map_arguments(): void
    {
        // Create a simple chainable enricher for this test
        $chain = ToolChain::create()
            ->pipe(ChainableFetchUserTool::class)
            ->pipe(EnrichUserTool::class, fn ($data) => [
                'user_id' => $data['id'],
                'name' => $data['name'],
            ]);

        $result = $chain->execute(['user_id' => 25], $this->context, $this->memory);

        $value = json_decode($result->value(), true);
        $this->assertEquals(25, $value['user_id']);
        $this->assertTrue($value['enriched']);
    }

    // ==========================================
    // Result Object Tests
    // ==========================================

    public function test_result_tracks_step_count(): void
    {
        $chain = ToolChain::create()
            ->pipe(FetchUserTool::class)
            ->transform(fn ($r) => $r)
            ->transform(fn ($r) => $r);

        $result = $chain->execute(['user_id' => 1], $this->context, $this->memory);

        $this->assertEquals(3, $result->getExecutedStepCount());
    }

    public function test_result_tracks_duration(): void
    {
        $chain = ToolChain::create()
            ->pipe(FetchUserTool::class);

        $result = $chain->execute(['user_id' => 1], $this->context, $this->memory);

        $this->assertGreaterThan(0, $result->getDuration());
        $this->assertGreaterThan(0, $result->getDurationMs());
    }

    public function test_result_provides_step_values(): void
    {
        $chain = ToolChain::create()
            ->pipe(FetchUserTool::class)
            ->transform(fn ($r) => json_decode($r, true))
            ->transform(fn ($data) => $data['name']);

        $result = $chain->execute(['user_id' => 1], $this->context, $this->memory);

        $this->assertNotNull($result->getStepResult(0));
        $this->assertIsArray($result->getStepValue(1));
        $this->assertEquals('User 1', $result->getStepValue(2));
    }

    public function test_result_to_array(): void
    {
        $chain = ToolChain::create('test-chain')
            ->pipe(FetchUserTool::class);

        $result = $chain->execute(['user_id' => 1], $this->context, $this->memory);

        $array = $result->toArray();

        $this->assertEquals('test-chain', $array['chain_name']);
        $this->assertTrue($array['successful']);
        $this->assertArrayHasKey('duration_ms', $array);
        $this->assertArrayHasKey('steps', $array);
    }

    public function test_result_to_json(): void
    {
        $chain = ToolChain::create()
            ->pipe(FetchUserTool::class);

        $result = $chain->execute(['user_id' => 1], $this->context, $this->memory);

        $json = $result->toJson();

        $this->assertJson($json);
        $decoded = json_decode($json, true);
        $this->assertTrue($decoded['successful']);
    }

    // ==========================================
    // Step Validation Tests
    // ==========================================

    public function test_invalid_tool_class_throws_exception(): void
    {
        $this->expectException(InvalidArgumentException::class);

        ToolChain::create()
            ->pipe('NonExistentClass');
    }

    public function test_non_tool_class_throws_exception(): void
    {
        $this->expectException(InvalidArgumentException::class);

        ToolChain::create()
            ->pipe(\stdClass::class);
    }

    // ==========================================
    // Step Introspection Tests
    // ==========================================

    public function test_get_steps_returns_all_steps(): void
    {
        $chain = ToolChain::create()
            ->pipe(FetchUserTool::class)
            ->transform(fn ($r) => $r)
            ->when(fn ($r) => true)
            ->tap(fn ($r) => null);

        $steps = $chain->getSteps();

        $this->assertCount(4, $steps);
        $this->assertTrue($steps[0]->isTool());
        $this->assertTrue($steps[1]->isTransform());
        $this->assertTrue($steps[2]->isCondition());
        $this->assertTrue($steps[3]->isTap());
    }

    public function test_step_describe_returns_readable_string(): void
    {
        $chain = ToolChain::create()
            ->pipe(FetchUserTool::class)
            ->transform(fn ($r) => $r);

        $steps = $chain->getSteps();

        $this->assertStringContainsString('Tool:', $steps[0]->describe());
        $this->assertStringContainsString('FetchUserTool', $steps[0]->describe());
        $this->assertEquals('Transform', $steps[1]->describe());
    }

    // ==========================================
    // Tracing Tests
    // ==========================================

    public function test_tracing_creates_spans_when_trace_active(): void
    {
        // Create a mock tracer with an active trace
        $tracer = Mockery::mock(Tracer::class);
        $tracer->shouldReceive('isEnabled')->andReturn(true);
        $tracer->shouldReceive('getCurrentTraceId')->andReturn('trace-123');

        // Expect startSpan to be called for each step (2 steps: tool + transform)
        $tracer->shouldReceive('startSpan')
            ->with(
                'chain_step',
                Mockery::pattern('/^test-chain\.step_0:FetchUserTool$/'),
                Mockery::type('array'),
                Mockery::type('array'),
                Mockery::type(AgentContext::class)
            )
            ->once()
            ->andReturn('span-1');

        $tracer->shouldReceive('startSpan')
            ->with(
                'chain_step',
                Mockery::pattern('/^test-chain\.step_1:transform$/'),
                Mockery::type('array'),
                Mockery::type('array'),
                Mockery::type(AgentContext::class)
            )
            ->once()
            ->andReturn('span-2');

        // Expect endSpan to be called for each step
        $tracer->shouldReceive('endSpan')
            ->with('span-1', Mockery::type('array'), 'success')
            ->once();

        $tracer->shouldReceive('endSpan')
            ->with('span-2', Mockery::type('array'), 'success')
            ->once();

        $this->app->instance(Tracer::class, $tracer);

        $chain = ToolChain::create('test-chain')
            ->pipe(FetchUserTool::class)
            ->transform(fn ($r) => json_decode($r, true));

        $result = $chain->execute(['user_id' => 1], $this->context, $this->memory);

        $this->assertTrue($result->successful());
    }

    public function test_failed_step_creates_error_span(): void
    {
        // Create a mock tracer with an active trace
        $tracer = Mockery::mock(Tracer::class);
        $tracer->shouldReceive('isEnabled')->andReturn(true);
        $tracer->shouldReceive('getCurrentTraceId')->andReturn('trace-123');

        // Expect startSpan to be called
        $tracer->shouldReceive('startSpan')
            ->once()
            ->andReturn('span-1');

        // Expect failSpan to be called with the exception
        $tracer->shouldReceive('failSpan')
            ->with('span-1', Mockery::type(RuntimeException::class))
            ->once();

        $this->app->instance(Tracer::class, $tracer);

        $chain = ToolChain::create('failing-chain')
            ->pipe(FailingTool::class);

        $result = $chain->execute([], $this->context, $this->memory);

        $this->assertTrue($result->failed());
    }

    public function test_skipped_steps_not_traced(): void
    {
        // Create a mock tracer with an active trace
        $tracer = Mockery::mock(Tracer::class);
        $tracer->shouldReceive('isEnabled')->andReturn(true);
        $tracer->shouldReceive('getCurrentTraceId')->andReturn('trace-123');

        // Expect startSpan to be called for 3 steps (tool + transform + condition)
        // The fourth step (tap) should be skipped and not traced
        $tracer->shouldReceive('startSpan')
            ->times(3)
            ->andReturn('span-1', 'span-2', 'span-3');

        // Expect endSpan to be called for the executed steps
        $tracer->shouldReceive('endSpan')
            ->times(3);

        $this->app->instance(Tracer::class, $tracer);

        $executed = false;

        $chain = ToolChain::create('skip-chain')
            ->pipe(FetchUserTool::class)
            ->transform(fn ($r) => json_decode($r, true))
            ->when(fn ($data) => $data['status'] === 'inactive')  // Will be false, skip remaining
            ->tap(function () use (&$executed) {
                $executed = true;
            });

        $result = $chain->execute(['user_id' => 1], $this->context, $this->memory);

        $this->assertTrue($result->successful());
        $this->assertFalse($executed);
        $this->assertGreaterThan(0, $result->getSkippedStepCount());
    }

    public function test_no_tracing_when_no_active_trace(): void
    {
        // Create a mock tracer with no active trace
        $tracer = Mockery::mock(Tracer::class);
        $tracer->shouldReceive('isEnabled')->andReturn(true);
        $tracer->shouldReceive('getCurrentTraceId')->andReturn(null);

        // Should not create any spans
        $tracer->shouldNotReceive('startSpan');
        $tracer->shouldNotReceive('endSpan');
        $tracer->shouldNotReceive('failSpan');

        $this->app->instance(Tracer::class, $tracer);

        $chain = ToolChain::create('no-trace-chain')
            ->pipe(FetchUserTool::class)
            ->transform(fn ($r) => json_decode($r, true));

        $result = $chain->execute(['user_id' => 1], $this->context, $this->memory);

        $this->assertTrue($result->successful());
    }

    public function test_no_tracing_when_tracer_disabled(): void
    {
        // Create a mock tracer that is disabled
        $tracer = Mockery::mock(Tracer::class);
        $tracer->shouldReceive('isEnabled')->andReturn(false);

        // Should not create any spans
        $tracer->shouldNotReceive('getCurrentTraceId');
        $tracer->shouldNotReceive('startSpan');
        $tracer->shouldNotReceive('endSpan');
        $tracer->shouldNotReceive('failSpan');

        $this->app->instance(Tracer::class, $tracer);

        $chain = ToolChain::create('disabled-trace-chain')
            ->pipe(FetchUserTool::class)
            ->transform(fn ($r) => json_decode($r, true));

        $result = $chain->execute(['user_id' => 1], $this->context, $this->memory);

        $this->assertTrue($result->successful());
    }
}
