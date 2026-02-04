<?php

declare(strict_types=1);

namespace Vizra\VizraADK\Planning;

use InvalidArgumentException;
use JsonException;
use JsonSerializable;

/**
 * Represents the result of reflecting on an execution's output.
 *
 * A Reflection captures the evaluation of how well an execution met its goals,
 * including a satisfaction flag, a numeric score, and detailed feedback about
 * strengths, weaknesses, and suggestions for improvement.
 */
class Reflection implements JsonSerializable
{
    /**
     * Create a new Reflection instance.
     *
     * @param bool $satisfactory Whether the result is satisfactory
     * @param float $score Numeric score between 0 and 1
     * @param array<string> $strengths List of strengths identified
     * @param array<string> $weaknesses List of weaknesses identified
     * @param array<string> $suggestions List of suggestions for improvement
     * @throws InvalidArgumentException If score is not between 0 and 1
     */
    public function __construct(
        public readonly bool $satisfactory,
        public readonly float $score,
        public readonly array $strengths = [],
        public readonly array $weaknesses = [],
        public readonly array $suggestions = [],
    ) {
        if ($score < 0 || $score > 1) {
            throw new InvalidArgumentException('Score must be between 0 and 1');
        }
    }

    /**
     * Create a Reflection from a JSON string.
     *
     * @param string $json JSON string representation of the reflection
     * @return static
     * @throws JsonException If the JSON is invalid
     */
    public static function fromJson(string $json): static
    {
        $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

        return new static(
            satisfactory: $data['satisfactory'] ?? false,
            score: (float) ($data['score'] ?? 0.0),
            strengths: $data['strengths'] ?? [],
            weaknesses: $data['weaknesses'] ?? [],
            suggestions: $data['suggestions'] ?? [],
        );
    }

    /**
     * Convert the reflection to a JSON string.
     *
     * @return string
     */
    public function toJson(): string
    {
        return json_encode($this, JSON_THROW_ON_ERROR);
    }

    /**
     * Check if improvement is required based on the satisfactory flag.
     *
     * @return bool True if improvement is required
     */
    public function requiresImprovement(): bool
    {
        return !$this->satisfactory;
    }

    /**
     * Get a summary of the feedback (weaknesses and suggestions).
     *
     * @return string A formatted summary
     */
    public function getSummary(): string
    {
        $parts = [];

        if (!empty($this->weaknesses)) {
            $parts[] = 'Weaknesses: ' . implode(', ', $this->weaknesses);
        }

        if (!empty($this->suggestions)) {
            $parts[] = 'Suggestions: ' . implode(', ', $this->suggestions);
        }

        return implode("\n", $parts);
    }

    /**
     * Specify data which should be serialized to JSON.
     *
     * @return array{satisfactory: bool, score: float, strengths: array<string>, weaknesses: array<string>, suggestions: array<string>}
     */
    public function jsonSerialize(): array
    {
        return [
            'satisfactory' => $this->satisfactory,
            'score' => $this->score,
            'strengths' => $this->strengths,
            'weaknesses' => $this->weaknesses,
            'suggestions' => $this->suggestions,
        ];
    }
}
