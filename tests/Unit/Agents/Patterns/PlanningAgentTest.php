<?php

use Prism\Prism\PrismManager;
use Vizra\VizraADK\Agents\BaseLlmAgent;
use Vizra\VizraADK\Agents\Patterns\Data\Plan;
use Vizra\VizraADK\Agents\Patterns\Data\PlanStep;
use Vizra\VizraADK\Agents\Patterns\Data\Reflection;
use Vizra\VizraADK\Agents\Patterns\PlanningAgent;
use Vizra\VizraADK\Exceptions\PlanExecutionException;
use Vizra\VizraADK\System\AgentContext;

beforeEach(function () {
    $this->app[PrismManager::class]->extend('mock', function () {
        return new class extends \Prism\Prism\Providers\Provider {};
    });
});

describe('PlanningAgent', function () {
    it('extends BaseLlmAgent', function () {
        $agent = new ConcretePlanningAgent();
        expect($agent)->toBeInstanceOf(BaseLlmAgent::class);
    });

    it('has configurable max replan attempts', function () {
        $agent = new ConcretePlanningAgent();
        expect($agent->getMaxReplanAttempts())->toBe(3);

        $agent->setMaxReplanAttempts(5);
        expect($agent->getMaxReplanAttempts())->toBe(5);
    });

    it('has configurable satisfaction threshold', function () {
        $agent = new ConcretePlanningAgent();
        expect($agent->getSatisfactionThreshold())->toBe(0.8);

        $agent->setSatisfactionThreshold(0.9);
        expect($agent->getSatisfactionThreshold())->toBe(0.9);
    });

    it('validates satisfaction threshold is between 0 and 1', function () {
        $agent = new ConcretePlanningAgent();

        expect(fn() => $agent->setSatisfactionThreshold(1.5))
            ->toThrow(\InvalidArgumentException::class);
    });

    it('can generate a plan from input', function () {
        $agent = new MockedPlanningAgent();
        $context = new AgentContext('test-session');

        $plan = $agent->publicGeneratePlan('Create a user management system', $context);

        expect($plan)->toBeInstanceOf(Plan::class);
        expect($plan->goal)->not->toBeEmpty();
        expect($plan->steps)->not->toBeEmpty();
    });

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

    it('stops execution when satisfaction threshold is met', function () {
        $agent = new MockedPlanningAgent();
        $agent->setSatisfactionThreshold(0.7);
        $agent->setMockedReflectionScore(0.8); // Above threshold

        $context = new AgentContext('test-session');
        $result = $agent->execute('Test task', $context);

        expect($agent->getReplanCount())->toBe(0);
        expect($result)->toBeString();
    });

    it('replans when satisfaction threshold is not met', function () {
        $agent = new MockedPlanningAgent();
        $agent->setSatisfactionThreshold(0.8);
        $agent->setMaxReplanAttempts(2);

        // First reflection is below threshold, second is above
        $agent->setMockedReflectionScores([0.5, 0.9]);

        $context = new AgentContext('test-session');
        $result = $agent->execute('Test task', $context);

        expect($agent->getReplanCount())->toBe(1);
    });

    it('stops after max replan attempts', function () {
        $agent = new MockedPlanningAgent();
        $agent->setMaxReplanAttempts(3);
        $agent->setMockedReflectionScore(0.3); // Always below threshold

        $context = new AgentContext('test-session');
        $result = $agent->execute('Test task', $context);

        // Should have replanned exactly max times
        expect($agent->getReplanCount())->toBe(3);
    });

    it('handles plan execution exceptions', function () {
        $agent = new MockedPlanningAgent();
        $agent->setStepCallback(function ($step) {
            throw new PlanExecutionException("Step {$step->id} failed");
        });
        $agent->setMaxReplanAttempts(1);

        $context = new AgentContext('test-session');

        // Should recover and try to replan
        $result = $agent->execute('Test task', $context);

        expect($result)->toBeString();
    });

    it('provides default planner instructions', function () {
        $agent = new ConcretePlanningAgent();
        $instructions = $agent->getPlannerInstructions();

        expect($instructions)->toContain('plan');
        expect($instructions)->toContain('JSON');
    });

    it('provides default reflection instructions', function () {
        $agent = new ConcretePlanningAgent();
        $instructions = $agent->getReflectionInstructions();

        expect($instructions)->toContain('Evaluate');
        expect($instructions)->toContain('JSON');
    });

    it('allows custom planner instructions', function () {
        $agent = new ConcretePlanningAgent();
        $agent->setPlannerInstructions('Custom planning instructions');

        expect($agent->getPlannerInstructions())->toBe('Custom planning instructions');
    });

    it('allows custom reflection instructions', function () {
        $agent = new ConcretePlanningAgent();
        $agent->setReflectionInstructions('Custom reflection instructions');

        expect($agent->getReflectionInstructions())->toBe('Custom reflection instructions');
    });
});

/**
 * Concrete implementation for testing abstract PlanningAgent
 */
class ConcretePlanningAgent extends PlanningAgent
{
    protected string $name = 'concrete-planning-agent';
    protected string $description = 'A concrete planning agent for testing';
    protected string $instructions = 'You are a planning agent';
    protected string $model = 'gpt-4';

    protected function executeStep(PlanStep $step, array $previousResults, AgentContext $context): string
    {
        return "Executed step {$step->id}: {$step->action}";
    }

    protected function synthesizeResults(Plan $plan, array $results, AgentContext $context): string
    {
        return implode("\n", $results);
    }
}

/**
 * Mocked implementation for testing PlanningAgent execution flow
 */
class MockedPlanningAgent extends PlanningAgent
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
