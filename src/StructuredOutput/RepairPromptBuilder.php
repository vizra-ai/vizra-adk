<?php

namespace Vizra\VizraADK\StructuredOutput;

use Prism\Prism\Contracts\Schema;

/**
 * Builds repair prompts to help the LLM fix validation errors.
 */
class RepairPromptBuilder
{
    /**
     * Build a repair prompt from validation errors.
     *
     * @param  array<ValidationError>  $errors
     */
    public static function build(array $errors, Schema $schema, array $previousData = []): string
    {
        $builder = new static;

        return $builder->buildPrompt($errors, $schema, $previousData);
    }

    /**
     * Build the repair prompt.
     *
     * @param  array<ValidationError>  $errors
     */
    protected function buildPrompt(array $errors, Schema $schema, array $previousData): string
    {
        $lines = [
            'Your previous response did not match the required schema. Please fix the following issues:',
            '',
        ];

        // Group errors by type for clearer instructions
        $errorsByType = $this->groupErrorsByType($errors);

        // Add missing field errors
        if (! empty($errorsByType['required'])) {
            $lines[] = '## Missing Required Fields';
            foreach ($errorsByType['required'] as $error) {
                $lines[] = "- `{$error->field}` is required but was not provided";
            }
            $lines[] = '';
        }

        // Add type errors
        if (! empty($errorsByType['type'])) {
            $lines[] = '## Incorrect Types';
            foreach ($errorsByType['type'] as $error) {
                $lines[] = "- `{$error->field}`: {$error->message}";
            }
            $lines[] = '';
        }

        // Add enum errors
        if (! empty($errorsByType['enum'])) {
            $lines[] = '## Invalid Enum Values';
            foreach ($errorsByType['enum'] as $error) {
                $lines[] = "- `{$error->field}`: {$error->message}";
            }
            $lines[] = '';
        }

        // Add other errors
        $otherTypes = array_diff(array_keys($errorsByType), ['required', 'type', 'enum']);
        if (! empty($otherTypes)) {
            $lines[] = '## Other Issues';
            foreach ($otherTypes as $type) {
                foreach ($errorsByType[$type] as $error) {
                    $lines[] = "- `{$error->field}`: {$error->message}";
                }
            }
            $lines[] = '';
        }

        // Add instruction to complete the response
        $lines[] = 'Please provide a complete response that:';
        $lines[] = '1. Includes ALL required fields';
        $lines[] = '2. Uses the correct data types for each field';
        $lines[] = '3. Uses valid enum values where specified';
        $lines[] = '';
        $lines[] = 'Respond with valid JSON matching the schema.';

        return implode("\n", $lines);
    }

    /**
     * Group errors by their type.
     *
     * @param  array<ValidationError>  $errors
     * @return array<string, array<ValidationError>>
     */
    protected function groupErrorsByType(array $errors): array
    {
        $grouped = [];

        foreach ($errors as $error) {
            $grouped[$error->type][] = $error;
        }

        return $grouped;
    }
}
