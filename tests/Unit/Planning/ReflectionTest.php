<?php

use Vizra\VizraADK\Planning\Reflection;

describe('Reflection', function () {
    it('can be created with all properties', function () {
        $reflection = new Reflection(
            satisfactory: true,
            score: 0.85,
            strengths: ['Good implementation'],
            weaknesses: ['Could improve performance'],
            suggestions: ['Add caching']
        );

        expect($reflection->satisfactory)->toBeTrue();
        expect($reflection->score)->toBe(0.85);
        expect($reflection->strengths)->toBe(['Good implementation']);
        expect($reflection->weaknesses)->toBe(['Could improve performance']);
        expect($reflection->suggestions)->toBe(['Add caching']);
    });

    it('can be created from JSON', function () {
        $json = json_encode([
            'satisfactory' => false,
            'score' => 0.6,
            'strengths' => ['Clear code'],
            'weaknesses' => ['Missing tests'],
            'suggestions' => ['Add unit tests'],
        ]);

        $reflection = Reflection::fromJson($json);

        expect($reflection->satisfactory)->toBeFalse();
        expect($reflection->score)->toBe(0.6);
        expect($reflection->strengths)->toBe(['Clear code']);
    });

    it('can be serialized to JSON', function () {
        $reflection = new Reflection(
            satisfactory: true,
            score: 0.9,
            strengths: ['Test'],
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
            score: 0.5,
            strengths: [],
            weaknesses: [],
            suggestions: []
        );

        $json = json_encode($reflection);
        $decoded = json_decode($json, true);

        expect($decoded['score'])->toBe(0.5);
    });

    it('validates score is between 0 and 1', function () {
        expect(fn() => new Reflection(
            satisfactory: true,
            score: 1.5,
            strengths: [],
            weaknesses: [],
            suggestions: []
        ))->toThrow(\InvalidArgumentException::class);
    });

    it('validates score is not negative', function () {
        expect(fn() => new Reflection(
            satisfactory: true,
            score: -0.1,
            strengths: [],
            weaknesses: [],
            suggestions: []
        ))->toThrow(\InvalidArgumentException::class);
    });

    it('allows score of exactly 0', function () {
        $reflection = new Reflection(
            satisfactory: false,
            score: 0.0,
            strengths: [],
            weaknesses: ['Complete failure'],
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

    it('can check if requires improvement', function () {
        $good = new Reflection(satisfactory: true, score: 0.9, strengths: [], weaknesses: [], suggestions: []);
        $bad = new Reflection(satisfactory: false, score: 0.4, strengths: [], weaknesses: [], suggestions: []);

        expect($good->requiresImprovement())->toBeFalse();
        expect($bad->requiresImprovement())->toBeTrue();
    });

    it('considers score threshold for improvement', function () {
        // Satisfactory is the determining factor, not score
        $lowScoreButSatisfactory = new Reflection(satisfactory: true, score: 0.5, strengths: [], weaknesses: [], suggestions: []);

        expect($lowScoreButSatisfactory->requiresImprovement())->toBeFalse();
    });

    it('can get summary of feedback', function () {
        $reflection = new Reflection(
            satisfactory: false,
            score: 0.6,
            strengths: ['Good start'],
            weaknesses: ['Missing validation', 'No error handling'],
            suggestions: ['Add input validation', 'Handle edge cases']
        );

        $summary = $reflection->getSummary();

        expect($summary)->toContain('Missing validation');
        expect($summary)->toContain('Add input validation');
    });

    it('handles missing optional fields with defaults from JSON', function () {
        $json = json_encode([
            'satisfactory' => true,
            'score' => 0.8,
        ]);

        $reflection = Reflection::fromJson($json);

        expect($reflection->strengths)->toBeEmpty();
        expect($reflection->weaknesses)->toBeEmpty();
        expect($reflection->suggestions)->toBeEmpty();
    });

    it('handles invalid JSON gracefully', function () {
        expect(fn() => Reflection::fromJson('not valid json'))
            ->toThrow(\JsonException::class);
    });
});
