<?php

namespace Vizra\VizraADK\Evaluations\Assertions;

/**
 * Assertion to validate JSON responses against a schema.
 *
 * This assertion checks if the response is valid JSON and optionally
 * validates it against a simple schema structure.
 */
class JsonSchemaAssertion extends BaseAssertion
{
    /**
     * Assert that the response is valid JSON and matches a schema.
     *
     * @param  string  $response  The LLM response to evaluate
     * @param  mixed  ...$params  The schema should be the first parameter (array)
     */
    public function assert(string $response, ...$params): array
    {
        $schema = $params[0] ?? [];

        // First check if it's valid JSON
        $data = json_decode($response, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return $this->result(
                false,
                'Response is not valid JSON',
                'valid JSON',
                'invalid JSON: '.json_last_error_msg()
            );
        }

        // If no schema provided, just check for valid JSON
        if (empty($schema)) {
            return $this->result(
                true,
                'Response is valid JSON',
                'valid JSON',
                'valid JSON'
            );
        }

        // Simple schema validation
        $valid = $this->validateSchema($data, $schema);

        return $this->result(
            $valid['valid'],
            $valid['message'] ?? 'Response should match JSON schema',
            'matching schema',
            $valid['valid'] ? 'matches schema' : ($valid['error'] ?? 'does not match')
        );
    }

    /**
     * Simple schema validation.
     *
     * @param  mixed  $data  The decoded JSON data
     * @param  array  $schema  The schema to validate against
     * @return array{valid: bool, message?: string, error?: string}
     */
    protected function validateSchema($data, array $schema): array
    {
        // Check type
        if (isset($schema['type'])) {
            $actualType = gettype($data);
            $expectedType = $schema['type'];

            // Map JSON types to PHP types
            $typeMap = [
                'object' => 'array',
                'array' => 'array',
                'string' => 'string',
                'number' => ['integer', 'double'],
                'integer' => 'integer',
                'boolean' => 'boolean',
                'null' => 'NULL',
            ];

            $phpType = $typeMap[$expectedType] ?? $expectedType;
            $isValidType = is_array($phpType)
                ? in_array($actualType, $phpType)
                : $actualType === $phpType;

            if (! $isValidType) {
                return [
                    'valid' => false,
                    'error' => "Expected type '{$expectedType}', got '{$actualType}'",
                ];
            }
        }

        // Check required properties for objects
        if (isset($schema['properties']) && is_array($data)) {
            foreach ($schema['properties'] as $property => $propertySchema) {
                if (! array_key_exists($property, $data)) {
                    return [
                        'valid' => false,
                        'error' => "Missing required property '{$property}'",
                    ];
                }

                // Recursively validate nested properties
                if (is_array($propertySchema)) {
                    $result = $this->validateSchema($data[$property], $propertySchema);
                    if (! $result['valid']) {
                        return [
                            'valid' => false,
                            'error' => "Property '{$property}': ".($result['error'] ?? 'invalid'),
                        ];
                    }
                }
            }
        }

        // Check array items
        if (isset($schema['items']) && is_array($data)) {
            foreach ($data as $index => $item) {
                $result = $this->validateSchema($item, $schema['items']);
                if (! $result['valid']) {
                    return [
                        'valid' => false,
                        'error' => "Array item [{$index}]: ".($result['error'] ?? 'invalid'),
                    ];
                }
            }
        }

        return ['valid' => true];
    }
}
