<?php

use Vizra\VizraADK\Planning\PlanStep;

describe('PlanStep', function () {
    it('can be created with all properties', function () {
        $step = new PlanStep(
            id: 1,
            action: 'Create database schema',
            dependencies: [2, 3],
            tools: ['database', 'migration']
        );

        expect($step->id)->toBe(1);
        expect($step->action)->toBe('Create database schema');
        expect($step->dependencies)->toBe([2, 3]);
        expect($step->tools)->toBe(['database', 'migration']);
    });

    it('can be created from array', function () {
        $step = PlanStep::fromArray([
            'id' => 1,
            'action' => 'Implement user authentication',
            'dependencies' => [2],
            'tools' => ['auth_tool'],
        ]);

        expect($step->id)->toBe(1);
        expect($step->action)->toBe('Implement user authentication');
        expect($step->dependencies)->toBe([2]);
        expect($step->tools)->toBe(['auth_tool']);
    });

    it('can be serialized to array', function () {
        $step = new PlanStep(
            id: 5,
            action: 'Test action',
            dependencies: [1, 2],
            tools: ['tool1']
        );

        $array = $step->toArray();

        expect($array)->toBe([
            'id' => 5,
            'action' => 'Test action',
            'dependencies' => [1, 2],
            'tools' => ['tool1'],
            'completed' => false,
            'result' => null,
        ]);
    });

    it('implements JsonSerializable', function () {
        $step = new PlanStep(
            id: 1,
            action: 'Test',
            dependencies: [],
            tools: []
        );

        $json = json_encode($step);
        $decoded = json_decode($json, true);

        expect($decoded['id'])->toBe(1);
        expect($decoded['action'])->toBe('Test');
    });

    it('has completed flag defaulting to false', function () {
        $step = new PlanStep(
            id: 1,
            action: 'Test',
            dependencies: [],
            tools: []
        );

        expect($step->isCompleted())->toBeFalse();
    });

    it('can be marked as completed', function () {
        $step = new PlanStep(
            id: 1,
            action: 'Test',
            dependencies: [],
            tools: []
        );

        $step->setCompleted(true);

        expect($step->isCompleted())->toBeTrue();
    });

    it('can store result', function () {
        $step = new PlanStep(
            id: 1,
            action: 'Test',
            dependencies: [],
            tools: []
        );

        $step->setResult('Step completed successfully');

        expect($step->getResult())->toBe('Step completed successfully');
    });

    it('handles empty dependencies', function () {
        $step = new PlanStep(
            id: 1,
            action: 'Independent step',
            dependencies: [],
            tools: ['tool1']
        );

        expect($step->dependencies)->toBeEmpty();
        expect($step->hasDependencies())->toBeFalse();
    });

    it('detects when step has dependencies', function () {
        $step = new PlanStep(
            id: 2,
            action: 'Dependent step',
            dependencies: [1],
            tools: []
        );

        expect($step->hasDependencies())->toBeTrue();
    });

    it('handles missing optional fields with defaults', function () {
        $step = PlanStep::fromArray([
            'id' => 1,
            'action' => 'Minimal step',
        ]);

        expect($step->id)->toBe(1);
        expect($step->action)->toBe('Minimal step');
        expect($step->dependencies)->toBeEmpty();
        expect($step->tools)->toBeEmpty();
    });

    it('can check if dependencies are satisfied', function () {
        $step = new PlanStep(
            id: 3,
            action: 'Third step',
            dependencies: [1, 2],
            tools: []
        );

        // No completed steps
        expect($step->areDependenciesSatisfied([]))->toBeFalse();

        // Only one dependency completed
        expect($step->areDependenciesSatisfied([1]))->toBeFalse();

        // All dependencies completed
        expect($step->areDependenciesSatisfied([1, 2]))->toBeTrue();

        // More than needed completed
        expect($step->areDependenciesSatisfied([1, 2, 4, 5]))->toBeTrue();
    });

    it('step with no dependencies is always satisfied', function () {
        $step = new PlanStep(
            id: 1,
            action: 'First step',
            dependencies: [],
            tools: []
        );

        expect($step->areDependenciesSatisfied([]))->toBeTrue();
    });
});
