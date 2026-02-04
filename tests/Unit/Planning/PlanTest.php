<?php

use Vizra\VizraADK\Planning\Plan;
use Vizra\VizraADK\Planning\PlanStep;

describe('Plan', function () {
    it('can be created with goal, steps, and success criteria', function () {
        $steps = [
            new PlanStep(id: 1, action: 'First step', dependencies: [], tools: []),
            new PlanStep(id: 2, action: 'Second step', dependencies: [1], tools: []),
        ];

        $plan = new Plan(
            goal: 'Complete the project',
            steps: $steps,
            successCriteria: ['All tests pass', 'Documentation complete']
        );

        expect($plan->goal)->toBe('Complete the project');
        expect($plan->steps)->toHaveCount(2);
        expect($plan->successCriteria)->toHaveCount(2);
    });

    it('can be created from JSON', function () {
        $json = json_encode([
            'goal' => 'Build a REST API',
            'steps' => [
                ['id' => 1, 'action' => 'Design schema', 'dependencies' => [], 'tools' => []],
                ['id' => 2, 'action' => 'Implement endpoints', 'dependencies' => [1], 'tools' => ['api_tool']],
            ],
            'success_criteria' => ['API responds correctly'],
        ]);

        $plan = Plan::fromJson($json);

        expect($plan->goal)->toBe('Build a REST API');
        expect($plan->steps)->toHaveCount(2);
        expect($plan->steps[0])->toBeInstanceOf(PlanStep::class);
        expect($plan->successCriteria)->toBe(['API responds correctly']);
    });

    it('can be serialized to JSON', function () {
        $plan = new Plan(
            goal: 'Test goal',
            steps: [new PlanStep(id: 1, action: 'Test', dependencies: [], tools: [])],
            successCriteria: ['Criterion']
        );

        $json = $plan->toJson();
        $decoded = json_decode($json, true);

        expect($decoded['goal'])->toBe('Test goal');
        expect($decoded['steps'])->toHaveCount(1);
        expect($decoded['success_criteria'])->toBe(['Criterion']);
    });

    it('implements JsonSerializable', function () {
        $plan = new Plan(
            goal: 'Test',
            steps: [],
            successCriteria: []
        );

        $json = json_encode($plan);
        $decoded = json_decode($json, true);

        expect($decoded['goal'])->toBe('Test');
    });

    it('can get step by id', function () {
        $plan = new Plan(
            goal: 'Test',
            steps: [
                new PlanStep(id: 1, action: 'First', dependencies: [], tools: []),
                new PlanStep(id: 5, action: 'Fifth', dependencies: [], tools: []),
                new PlanStep(id: 10, action: 'Tenth', dependencies: [], tools: []),
            ],
            successCriteria: []
        );

        $step = $plan->getStepById(5);

        expect($step)->not->toBeNull();
        expect($step->action)->toBe('Fifth');
    });

    it('returns null for non-existent step id', function () {
        $plan = new Plan(
            goal: 'Test',
            steps: [new PlanStep(id: 1, action: 'First', dependencies: [], tools: [])],
            successCriteria: []
        );

        expect($plan->getStepById(999))->toBeNull();
    });

    it('can check if all steps are completed', function () {
        $step1 = new PlanStep(id: 1, action: 'First', dependencies: [], tools: []);
        $step2 = new PlanStep(id: 2, action: 'Second', dependencies: [], tools: []);

        $plan = new Plan(goal: 'Test', steps: [$step1, $step2], successCriteria: []);

        expect($plan->isCompleted())->toBeFalse();

        $step1->setCompleted(true);
        expect($plan->isCompleted())->toBeFalse();

        $step2->setCompleted(true);
        expect($plan->isCompleted())->toBeTrue();
    });

    it('can create an empty plan', function () {
        $plan = new Plan(goal: 'Empty plan', steps: [], successCriteria: []);

        expect($plan->goal)->toBe('Empty plan');
        expect($plan->steps)->toBeEmpty();
        expect($plan->isCompleted())->toBeTrue(); // Empty plan is considered complete
    });

    it('handles missing fields in JSON with defaults', function () {
        $json = json_encode(['goal' => 'Minimal']);

        $plan = Plan::fromJson($json);

        expect($plan->goal)->toBe('Minimal');
        expect($plan->steps)->toBeEmpty();
        expect($plan->successCriteria)->toBeEmpty();
    });

    it('handles invalid JSON gracefully', function () {
        expect(fn() => Plan::fromJson('not valid json'))
            ->toThrow(\JsonException::class);
    });
});
