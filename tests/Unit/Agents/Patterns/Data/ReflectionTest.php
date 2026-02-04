<?php

use Vizra\VizraADK\Agents\Patterns\Data\Reflection;

describe('Reflection', function () {
    it('can be created with all properties', function () {
        $reflection = new Reflection(
            satisfactory: true,
            score: 0.85,
            strengths: ['Clear implementation', 'Good error handling'],
            weaknesses: ['Missing edge cases'],
            suggestions: ['Add more tests']
        );

        expect($reflection->satisfactory)->toBeTrue();
        expect($reflection->score)->toBe(0.85);
        expect($reflection->strengths)->toHaveCount(2);
        expect($reflection->weaknesses)->toHaveCount(1);
        expect($reflection->suggestions)->toHaveCount(1);
    });

    it('can be created from JSON', function () {
        $json = json_encode([
            'satisfactory' => false,
            'score' => 0.65,
            'strengths' => ['Good structure'],
            'weaknesses' => ['Missing validation', 'Poor documentation'],
            'suggestions' => ['Add input validation', 'Write documentation'],
        ]);

        $reflection = Reflection::fromJson($json);

        expect($reflection->satisfactory)->toBeFalse();
        expect($reflection->score)->toBe(0.65);
        expect($reflection->strengths)->toBe(['Good structure']);
        expect($reflection->weaknesses)->toHaveCount(2);
        expect($reflection->suggestions)->toHaveCount(2);
    });

    it('can be serialized to JSON', function () {
        $reflection = new Reflection(
            satisfactory: true,
            score: 0.9,
            strengths: ['Complete'],
            weaknesses: [],
            suggestions: []
        );

        $json = $reflection->toJson();
        $decoded = json_decode($json, true);

        expect($decoded['satisfactory'])->toBeTrue();
        expect($decoded['score'])->toBe(0.9);
    });

    it('implements JsonSerializable', function () {
        $reflection = new Reflection(
            satisfactory: true,
            score: 0.8,
            strengths: [],
            weaknesses: [],
            suggestions: []
        );

        $serialized = json_encode($reflection);
        $decoded = json_decode($serialized, true);

        expect($decoded['satisfactory'])->toBeTrue();
        expect($decoded['score'])->toBe(0.8);
    });

    it('validates score is between 0 and 1', function () {
        expect(fn() => new Reflection(
            satisfactory: true,
            score: 1.5,
            strengths: [],
            weaknesses: [],
            suggestions: []
        ))->toThrow(\InvalidArgumentException::class, 'Score must be between 0 and 1');
    });

    it('validates score is not negative', function () {
        expect(fn() => new Reflection(
            satisfactory: true,
            score: -0.1,
            strengths: [],
            weaknesses: [],
            suggestions: []
        ))->toThrow(\InvalidArgumentException::class, 'Score must be between 0 and 1');
    });

    it('allows score of exactly 0', function () {
        $reflection = new Reflection(
            satisfactory: false,
            score: 0.0,
            strengths: [],
            weaknesses: ['Everything failed'],
            suggestions: ['Start over']
        );

        expect($reflection->score)->toBe(0.0);
    });

    it('allows score of exactly 1', function () {
        $reflection = new Reflection(
            satisfactory: true,
            score: 1.0,
            strengths: ['Perfect'],
            weaknesses: [],
            suggestions: []
        );

        expect($reflection->score)->toBe(1.0);
    });

    it('handles missing optional fields with defaults from JSON', function () {
        $json = json_encode([
            'satisfactory' => true,
            'score' => 0.75,
        ]);

        $reflection = Reflection::fromJson($json);

        expect($reflection->satisfactory)->toBeTrue();
        expect($reflection->score)->toBe(0.75);
        expect($reflection->strengths)->toBeEmpty();
        expect($reflection->weaknesses)->toBeEmpty();
        expect($reflection->suggestions)->toBeEmpty();
    });

    it('can check if requires improvement', function () {
        $goodReflection = new Reflection(
            satisfactory: true,
            score: 0.9,
            strengths: ['Great'],
            weaknesses: [],
            suggestions: []
        );

        $poorReflection = new Reflection(
            satisfactory: false,
            score: 0.4,
            strengths: [],
            weaknesses: ['Many issues'],
            suggestions: ['Improve']
        );

        expect($goodReflection->requiresImprovement())->toBeFalse();
        expect($poorReflection->requiresImprovement())->toBeTrue();
    });

    it('considers score threshold for improvement', function () {
        // Score above default threshold (0.8) but not satisfactory
        $reflection = new Reflection(
            satisfactory: false,
            score: 0.85,
            strengths: ['Good score'],
            weaknesses: ['But not satisfactory'],
            suggestions: []
        );

        expect($reflection->requiresImprovement())->toBeTrue();
    });

    it('can get summary of feedback', function () {
        $reflection = new Reflection(
            satisfactory: false,
            score: 0.6,
            strengths: ['Strength 1'],
            weaknesses: ['Weakness 1', 'Weakness 2'],
            suggestions: ['Suggestion 1']
        );

        $summary = $reflection->getSummary();

        expect($summary)->toContain('Weakness 1');
        expect($summary)->toContain('Weakness 2');
        expect($summary)->toContain('Suggestion 1');
    });

    it('handles invalid JSON gracefully', function () {
        expect(fn() => Reflection::fromJson('not valid json'))
            ->toThrow(\JsonException::class);
    });
});
