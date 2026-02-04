<?php

use Vizra\VizraADK\Agents\Patterns\Data\Plan;
use Vizra\VizraADK\Agents\Patterns\Data\PlanStep;

describe('Plan', function () {
    it('can be created with goal, steps, and success criteria', function () {
        $plan = new Plan(
            goal: 'Build a REST API',
            steps: [
                new PlanStep(id: 1, action: 'Create database schema', dependencies: [], tools: ['database']),
                new PlanStep(id: 2, action: 'Implement endpoints', dependencies: [1], tools: ['code_generator']),
            ],
            successCriteria: ['All endpoints return valid responses', 'Tests pass']
        );

        expect($plan->goal)->toBe('Build a REST API');
        expect($plan->steps)->toHaveCount(2);
        expect($plan->successCriteria)->toHaveCount(2);
    });

    it('can be created from JSON', function () {
        $json = json_encode([
            'goal' => 'Build a REST API',
            'steps' => [
                ['id' => 1, 'action' => 'Create database schema', 'dependencies' => [], 'tools' => ['database']],
                ['id' => 2, 'action' => 'Implement endpoints', 'dependencies' => [1], 'tools' => ['code_generator']],
            ],
            'success_criteria' => ['All endpoints return valid responses', 'Tests pass'],
        ]);

        $plan = Plan::fromJson($json);

        expect($plan->goal)->toBe('Build a REST API');
        expect($plan->steps)->toHaveCount(2);
        expect($plan->steps[0])->toBeInstanceOf(PlanStep::class);
        expect($plan->steps[0]->id)->toBe(1);
        expect($plan->steps[0]->action)->toBe('Create database schema');
        expect($plan->successCriteria)->toHaveCount(2);
    });

    it('can be serialized to JSON', function () {
        $plan = new Plan(
            goal: 'Build a REST API',
            steps: [
                new PlanStep(id: 1, action: 'Create database schema', dependencies: [], tools: ['database']),
            ],
            successCriteria: ['Tests pass']
        );

        $json = $plan->toJson();
        $decoded = json_decode($json, true);

        expect($decoded['goal'])->toBe('Build a REST API');
        expect($decoded['steps'])->toHaveCount(1);
        expect($decoded['success_criteria'])->toHaveCount(1);
    });

    it('implements JsonSerializable', function () {
        $plan = new Plan(
            goal: 'Test goal',
            steps: [],
            successCriteria: ['Criterion 1']
        );

        $serialized = json_encode($plan);
        $decoded = json_decode($serialized, true);

        expect($decoded['goal'])->toBe('Test goal');
        expect($decoded['success_criteria'])->toContain('Criterion 1');
    });

    it('can create an empty plan', function () {
        $plan = new Plan(
            goal: '',
            steps: [],
            successCriteria: []
        );

        expect($plan->goal)->toBe('');
        expect($plan->steps)->toBeEmpty();
        expect($plan->successCriteria)->toBeEmpty();
    });

    it('can get step by id', function () {
        $plan = new Plan(
            goal: 'Test',
            steps: [
                new PlanStep(id: 1, action: 'First step', dependencies: [], tools: []),
                new PlanStep(id: 2, action: 'Second step', dependencies: [1], tools: []),
                new PlanStep(id: 3, action: 'Third step', dependencies: [1, 2], tools: []),
            ],
            successCriteria: []
        );

        $step = $plan->getStepById(2);

        expect($step)->not->toBeNull();
        expect($step->action)->toBe('Second step');
    });

    it('returns null for non-existent step id', function () {
        $plan = new Plan(
            goal: 'Test',
            steps: [
                new PlanStep(id: 1, action: 'First step', dependencies: [], tools: []),
            ],
            successCriteria: []
        );

        $step = $plan->getStepById(999);

        expect($step)->toBeNull();
    });

    it('can check if all steps are completed', function () {
        $steps = [
            new PlanStep(id: 1, action: 'First', dependencies: [], tools: []),
            new PlanStep(id: 2, action: 'Second', dependencies: [1], tools: []),
        ];

        $plan = new Plan(
            goal: 'Test',
            steps: $steps,
            successCriteria: []
        );

        // Initially no steps are completed
        expect($plan->isCompleted())->toBeFalse();

        // Mark all as completed
        $steps[0]->setCompleted(true);
        $steps[1]->setCompleted(true);

        expect($plan->isCompleted())->toBeTrue();
    });

    it('handles invalid JSON gracefully', function () {
        expect(fn() => Plan::fromJson('invalid json'))
            ->toThrow(\JsonException::class);
    });

    it('handles missing fields in JSON with defaults', function () {
        $json = json_encode([
            'goal' => 'Minimal plan',
        ]);

        $plan = Plan::fromJson($json);

        expect($plan->goal)->toBe('Minimal plan');
        expect($plan->steps)->toBeArray();
        expect($plan->steps)->toBeEmpty();
        expect($plan->successCriteria)->toBeArray();
        expect($plan->successCriteria)->toBeEmpty();
    });
});
