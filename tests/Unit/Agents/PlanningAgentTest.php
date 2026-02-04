<?php

use Prism\Prism\PrismManager;
use Vizra\VizraADK\Agents\BaseLlmAgent;
use Vizra\VizraADK\Agents\BasePlanningAgent;
use Vizra\VizraADK\Agents\PlanningAgent;
use Vizra\VizraADK\Execution\PlanningAgentExecutor;
use Vizra\VizraADK\Exceptions\PlanExecutionException;
use Vizra\VizraADK\Planning\Plan;
use Vizra\VizraADK\Planning\PlanningResponse;
use Vizra\VizraADK\Planning\PlanStep;
use Vizra\VizraADK\Planning\Reflection;
use Vizra\VizraADK\System\AgentContext;

beforeEach(function () {
    $this->app[PrismManager::class]->extend('mock', function () {
        return new class extends \Prism\Prism\Providers\Provider {};
    });
});

describe('PlanningAgent', function () {
    it('extends BasePlanningAgent', function () {
        $agent = new PlanningAgent();
        expect($agent)->toBeInstanceOf(BasePlanningAgent::class);
    });

    it('extends BaseLlmAgent', function () {
        $agent = new PlanningAgent();
        expect($agent)->toBeInstanceOf(BaseLlmAgent::class);
    });

    it('has a static plan method that returns executor', function () {
        $executor = PlanningAgent::plan('Test task');
        expect($executor)->toBeInstanceOf(PlanningAgentExecutor::class);
    });

    it('has configurable max replan attempts', function () {
        $agent = new PlanningAgent();
        expect($agent->getMaxReplanAttempts())->toBe(3);

        $agent->setMaxReplanAttempts(5);
        expect($agent->getMaxReplanAttempts())->toBe(5);
    });

    it('has configurable satisfaction threshold', function () {
        $agent = new PlanningAgent();
        expect($agent->getSatisfactionThreshold())->toBe(0.8);

        $agent->setSatisfactionThreshold(0.9);
        expect($agent->getSatisfactionThreshold())->toBe(0.9);
    });

    it('validates satisfaction threshold is between 0 and 1', function () {
        $agent = new PlanningAgent();

        expect(fn() => $agent->setSatisfactionThreshold(1.5))
            ->toThrow(\InvalidArgumentException::class);
    });

    it('provides default planner instructions', function () {
        $agent = new PlanningAgent();
        $instructions = $agent->getPlannerInstructions();

        expect($instructions)->toContain('plan');
        expect($instructions)->toContain('JSON');
    });

    it('provides default reflection instructions', function () {
        $agent = new PlanningAgent();
        $instructions = $agent->getReflectionInstructions();

        expect($instructions)->toContain('Evaluate');
        expect($instructions)->toContain('JSON');
    });

    it('allows custom planner instructions', function () {
        $agent = new PlanningAgent();
        $agent->setPlannerInstructions('Custom planning instructions');

        expect($agent->getPlannerInstructions())->toBe('Custom planning instructions');
    });

    it('allows custom reflection instructions', function () {
        $agent = new PlanningAgent();
        $agent->setReflectionInstructions('Custom reflection instructions');

        expect($agent->getReflectionInstructions())->toBe('Custom reflection instructions');
    });

    it('has toToolDefinition method', function () {
        $agent = new PlanningAgent();
        $definition = $agent->toToolDefinition();

        expect($definition)->toHaveKey('name');
        expect($definition)->toHaveKey('description');
        expect($definition)->toHaveKey('parameters');
        expect($definition['parameters']['properties'])->toHaveKey('task');
    });
});

describe('BasePlanningAgent', function () {
    it('can execute a plan', function () {
        $agent = new MockedPlanningAgent();
        $context = new AgentContext('test-session');

        $plan = new Plan(
            goal: 'Test goal',
            steps: [
                new PlanStep(id: 1, action: 'First step', dependencies: [], tools: []),
                new PlanStep(id: 2, action: 'Second step', dependencies: [1], tools: []),
            ],
            successCriteria: ['All steps completed']
        );

        $result = $agent->publicExecutePlan($plan, $context);

        expect($result)->toBeString();
        expect($result)->not->toBeEmpty();
    });

    it('respects step dependencies when executing plan', function () {
        $agent = new MockedPlanningAgent();
        $context = new AgentContext('test-session');

        $executionOrder = [];
        $agent->setStepCallback(function ($step) use (&$executionOrder) {
            $executionOrder[] = $step->id;
            return "Completed step {$step->id}";
        });

        $plan = new Plan(
            goal: 'Test dependencies',
            steps: [
                new PlanStep(id: 1, action: 'First', dependencies: [], tools: []),
                new PlanStep(id: 2, action: 'Second', dependencies: [1], tools: []),
                new PlanStep(id: 3, action: 'Third', dependencies: [1, 2], tools: []),
            ],
            successCriteria: []
        );

        $agent->publicExecutePlan($plan, $context);

        expect($executionOrder)->toBe([1, 2, 3]);
    });

    it('can reflect on execution results', function () {
        $agent = new MockedPlanningAgent();
        $context = new AgentContext('test-session');

        $plan = new Plan(
            goal: 'Test goal',
            steps: [
                new PlanStep(id: 1, action: 'Step', dependencies: [], tools: []),
            ],
            successCriteria: ['Test criterion']
        );

        $reflection = $agent->publicReflect('Test input', 'Test result', $plan, $context);

        expect($reflection)->toBeInstanceOf(Reflection::class);
        expect($reflection->score)->toBeGreaterThanOrEqual(0);
        expect($reflection->score)->toBeLessThanOrEqual(1);
    });

    it('can replan based on reflection feedback', function () {
        $agent = new MockedPlanningAgent();
        $context = new AgentContext('test-session');

        $reflection = new Reflection(
            satisfactory: false,
            score: 0.5,
            strengths: ['Good start'],
            weaknesses: ['Missing validation'],
            suggestions: ['Add input validation']
        );

        $newPlan = $agent->publicReplan('Test input', 'Previous result', $reflection, $context);

        expect($newPlan)->toBeInstanceOf(Plan::class);
    });

    it('stores current plan in context', function () {
        $agent = new MockedPlanningAgent();
        $context = new AgentContext('test-session');

        // Execute will store the plan in context
        $agent->execute('Test task', $context);

        $currentPlan = $context->getState('current_plan');
        expect($currentPlan)->not->toBeNull();
    });

    it('returns PlanningResponse from execute', function () {
        $agent = new MockedPlanningAgent();
        $agent->setMockedReflectionScore(0.9);
        $context = new AgentContext('test-session');

        $response = $agent->execute('Test task', $context);

        expect($response)->toBeInstanceOf(PlanningResponse::class);
        expect($response->isSuccess())->toBeTrue();
        expect($response->attempts())->toBe(1);
    });

    it('stops execution when satisfaction threshold is met', function () {
        $agent = new MockedPlanningAgent();
        $agent->setSatisfactionThreshold(0.7);
        $agent->setMockedReflectionScore(0.8); // Above threshold

        $context = new AgentContext('test-session');
        $response = $agent->execute('Test task', $context);

        expect($agent->getReplanCount())->toBe(0);
        expect($response->isSuccess())->toBeTrue();
    });

    it('replans when satisfaction threshold is not met', function () {
        $agent = new MockedPlanningAgent();
        $agent->setSatisfactionThreshold(0.8);
        $agent->setMaxReplanAttempts(2);

        // First reflection is below threshold, second is above
        $agent->setMockedReflectionScores([0.5, 0.9]);

        $context = new AgentContext('test-session');
        $response = $agent->execute('Test task', $context);

        expect($agent->getReplanCount())->toBe(1);
    });

    it('stops after max replan attempts', function () {
        $agent = new MockedPlanningAgent();
        $agent->setMaxReplanAttempts(3);
        $agent->setMockedReflectionScore(0.3); // Always below threshold

        $context = new AgentContext('test-session');
        $response = $agent->execute('Test task', $context);

        // Should have replanned exactly max times
        expect($agent->getReplanCount())->toBe(3);
        expect($response->isFailed())->toBeTrue();
    });

    it('handles plan execution exceptions', function () {
        $agent = new MockedPlanningAgent();
        $agent->setStepCallback(function ($step) {
            throw new PlanExecutionException("Step {$step->id} failed");
        });
        $agent->setMaxReplanAttempts(1);

        $context = new AgentContext('test-session');

        // Should recover and try to replan
        $response = $agent->execute('Test task', $context);

        expect($response)->toBeInstanceOf(PlanningResponse::class);
    });
});

describe('PlanningAgentExecutor', function () {
    it('can set max attempts', function () {
        $executor = PlanningAgent::plan('Test')->maxAttempts(5);
        expect($executor)->toBeInstanceOf(PlanningAgentExecutor::class);
    });

    it('can set threshold', function () {
        $executor = PlanningAgent::plan('Test')->threshold(0.9);
        expect($executor)->toBeInstanceOf(PlanningAgentExecutor::class);
    });

    it('can use high accuracy preset', function () {
        $executor = PlanningAgent::plan('Test')->highAccuracy();
        expect($executor)->toBeInstanceOf(PlanningAgentExecutor::class);
    });

    it('can use fast preset', function () {
        $executor = PlanningAgent::plan('Test')->fast();
        expect($executor)->toBeInstanceOf(PlanningAgentExecutor::class);
    });

    it('can use balanced preset', function () {
        $executor = PlanningAgent::plan('Test')->balanced();
        expect($executor)->toBeInstanceOf(PlanningAgentExecutor::class);
    });

    it('can set async execution', function () {
        $executor = PlanningAgent::plan('Test')->async();
        expect($executor)->toBeInstanceOf(PlanningAgentExecutor::class);
    });

    it('can set queue', function () {
        $executor = PlanningAgent::plan('Test')->onQueue('planning');
        expect($executor)->toBeInstanceOf(PlanningAgentExecutor::class);
    });

    it('supports fluent chaining', function () {
        $executor = PlanningAgent::plan('Test')
            ->maxAttempts(5)
            ->threshold(0.9)
            ->withContext(['key' => 'value']);

        expect($executor)->toBeInstanceOf(PlanningAgentExecutor::class);
    });
});

describe('PlanningResponse', function () {
    it('provides access to result', function () {
        $response = new PlanningResponse(
            result: 'Test result',
            plan: null,
            reflection: null,
            attempts: 1,
            success: true,
            input: 'Test input'
        );

        expect($response->result())->toBe('Test result');
    });

    it('provides access to plan', function () {
        $plan = new Plan(goal: 'Test', steps: [], successCriteria: []);
        $response = new PlanningResponse(
            result: 'Result',
            plan: $plan,
            reflection: null,
            attempts: 1,
            success: true,
            input: 'Input'
        );

        expect($response->plan())->toBe($plan);
        expect($response->goal())->toBe('Test');
    });

    it('provides access to reflection', function () {
        $reflection = new Reflection(
            satisfactory: true,
            score: 0.9,
            strengths: ['Good'],
            weaknesses: [],
            suggestions: []
        );
        $response = new PlanningResponse(
            result: 'Result',
            plan: null,
            reflection: $reflection,
            attempts: 1,
            success: true,
            input: 'Input'
        );

        expect($response->reflection())->toBe($reflection);
        expect($response->score())->toBe(0.9);
    });

    it('can check success status', function () {
        $success = new PlanningResponse(
            result: 'Result',
            plan: null,
            reflection: null,
            attempts: 1,
            success: true,
            input: 'Input'
        );

        $failed = new PlanningResponse(
            result: 'Result',
            plan: null,
            reflection: null,
            attempts: 3,
            success: false,
            input: 'Input'
        );

        expect($success->isSuccess())->toBeTrue();
        expect($success->isFailed())->toBeFalse();
        expect($failed->isSuccess())->toBeFalse();
        expect($failed->isFailed())->toBeTrue();
    });

    it('can be serialized to JSON', function () {
        $response = new PlanningResponse(
            result: 'Test result',
            plan: new Plan(goal: 'Test', steps: [], successCriteria: []),
            reflection: null,
            attempts: 2,
            success: true,
            input: 'Input'
        );

        $json = $response->toJson();
        $decoded = json_decode($json, true);

        expect($decoded['result'])->toBe('Test result');
        expect($decoded['success'])->toBeTrue();
        expect($decoded['attempts'])->toBe(2);
    });

    it('converts to string as result', function () {
        $response = new PlanningResponse(
            result: 'String result',
            plan: null,
            reflection: null,
            attempts: 1,
            success: true,
            input: 'Input'
        );

        expect((string) $response)->toBe('String result');
    });
});

/**
 * Mocked implementation for testing BasePlanningAgent execution flow
 */
class MockedPlanningAgent extends BasePlanningAgent
{
    protected string $name = 'mocked-planning-agent';
    protected string $description = 'A mocked planning agent for testing';
    protected string $instructions = 'You are a planning agent';
    protected string $model = 'gpt-4';

    private ?\Closure $stepCallback = null;
    private float $mockedReflectionScore = 0.9;
    private array $mockedReflectionScores = [];
    private int $reflectionCallCount = 0;
    private int $replanCount = 0;

    public function setStepCallback(\Closure $callback): void
    {
        $this->stepCallback = $callback;
    }

    public function setMockedReflectionScore(float $score): void
    {
        $this->mockedReflectionScore = $score;
    }

    public function setMockedReflectionScores(array $scores): void
    {
        $this->mockedReflectionScores = $scores;
    }

    public function getReplanCount(): int
    {
        return $this->replanCount;
    }

    protected function executeStep(PlanStep $step, array $previousResults, AgentContext $context): string
    {
        if ($this->stepCallback) {
            return ($this->stepCallback)($step, $previousResults, $context);
        }
        return "Mocked result for step {$step->id}";
    }

    protected function synthesizeResults(Plan $plan, array $results, AgentContext $context): string
    {
        return implode("\n", $results);
    }

    protected function generatePlan(mixed $input, AgentContext $context): Plan
    {
        return new Plan(
            goal: "Plan for: {$input}",
            steps: [
                new PlanStep(id: 1, action: 'Step 1', dependencies: [], tools: []),
                new PlanStep(id: 2, action: 'Step 2', dependencies: [1], tools: []),
            ],
            successCriteria: ['Criterion 1']
        );
    }

    protected function reflect(mixed $input, string $result, Plan $plan, AgentContext $context): Reflection
    {
        $score = $this->mockedReflectionScore;

        if (!empty($this->mockedReflectionScores)) {
            $index = min($this->reflectionCallCount, count($this->mockedReflectionScores) - 1);
            $score = $this->mockedReflectionScores[$index];
        }

        $this->reflectionCallCount++;

        return new Reflection(
            satisfactory: $score >= $this->satisfactionThreshold,
            score: $score,
            strengths: ['Mocked strength'],
            weaknesses: $score < $this->satisfactionThreshold ? ['Mocked weakness'] : [],
            suggestions: $score < $this->satisfactionThreshold ? ['Mocked suggestion'] : []
        );
    }

    protected function replan(mixed $input, ?string $previousResult, mixed $feedback, AgentContext $context): Plan
    {
        $this->replanCount++;

        return new Plan(
            goal: "Improved plan for: {$input}",
            steps: [
                new PlanStep(id: 1, action: 'Improved Step 1', dependencies: [], tools: []),
            ],
            successCriteria: ['Improved criterion']
        );
    }

    // Public wrappers for testing protected methods
    public function publicGeneratePlan(mixed $input, AgentContext $context): Plan
    {
        return $this->generatePlan($input, $context);
    }

    public function publicExecutePlan(Plan $plan, AgentContext $context): string
    {
        return $this->executePlan($plan, $context);
    }

    public function publicReflect(mixed $input, string $result, Plan $plan, AgentContext $context): Reflection
    {
        return $this->reflect($input, $result, $plan, $context);
    }

    public function publicReplan(mixed $input, ?string $previousResult, mixed $feedback, AgentContext $context): Plan
    {
        return $this->replan($input, $previousResult, $feedback, $context);
    }
}
