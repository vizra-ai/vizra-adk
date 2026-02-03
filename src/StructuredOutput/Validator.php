<?php

namespace Vizra\VizraADK\StructuredOutput;

use Prism\Prism\Contracts\Schema;
use Prism\Prism\Schema\ArraySchema;
use Prism\Prism\Schema\BooleanSchema;
use Prism\Prism\Schema\EnumSchema;
use Prism\Prism\Schema\NumberSchema;
use Prism\Prism\Schema\ObjectSchema;
use Prism\Prism\Schema\StringSchema;

/**
 * Validates data against Prism schemas.
 */
class Validator
{
    /**
     * Validate data against a schema.
     */
    public static function validate(mixed $data, Schema $schema, string $path = ''): ValidationResult
    {
        $validator = new static;

        return $validator->validateSchema($data, $schema, $path);
    }

    /**
     * Validate data against any schema type.
     */
    protected function validateSchema(mixed $data, Schema $schema, string $path): ValidationResult
    {
        return match (true) {
            $schema instanceof ObjectSchema => $this->validateObject($data, $schema, $path),
            $schema instanceof ArraySchema => $this->validateArray($data, $schema, $path),
            $schema instanceof StringSchema => $this->validateString($data, $schema, $path),
            $schema instanceof NumberSchema => $this->validateNumber($data, $schema, $path),
            $schema instanceof BooleanSchema => $this->validateBoolean($data, $schema, $path),
            $schema instanceof EnumSchema => $this->validateEnum($data, $schema, $path),
            default => ValidationResult::success(),
        };
    }

    /**
     * Validate an object against ObjectSchema.
     */
    protected function validateObject(mixed $data, ObjectSchema $schema, string $path): ValidationResult
    {
        $errors = [];

        // Check if data is an array/object
        if (! is_array($data)) {
            if ($data === null && $schema->nullable) {
                return ValidationResult::success();
            }

            return ValidationResult::failure([
                new ValidationError(
                    field: $path ?: $schema->name,
                    type: 'type',
                    message: 'Expected object, got '.gettype($data),
                    expected: 'object',
                    actual: gettype($data)
                ),
            ]);
        }

        // Check required fields
        foreach ($schema->requiredFields as $field) {
            if (! array_key_exists($field, $data)) {
                $fieldPath = $this->buildPath($path, $field);
                $errors[] = new ValidationError(
                    field: $fieldPath,
                    type: 'required',
                    message: "Missing required field: {$field}",
                    expected: 'present',
                    actual: 'missing'
                );
            }
        }

        // Validate each property
        foreach ($schema->properties as $propSchema) {
            $fieldName = $propSchema->name();
            $fieldPath = $this->buildPath($path, $fieldName);

            if (! array_key_exists($fieldName, $data)) {
                // Skip optional fields that are missing
                continue;
            }

            $propResult = $this->validateSchema($data[$fieldName], $propSchema, $fieldPath);

            if (! $propResult->isValid()) {
                $errors = array_merge($errors, $propResult->getErrors());
            }
        }

        if (! empty($errors)) {
            return ValidationResult::failure($errors);
        }

        return ValidationResult::success();
    }

    /**
     * Validate an array against ArraySchema.
     */
    protected function validateArray(mixed $data, ArraySchema $schema, string $path): ValidationResult
    {
        $errors = [];

        if (! is_array($data)) {
            if ($data === null && $schema->nullable) {
                return ValidationResult::success();
            }

            return ValidationResult::failure([
                new ValidationError(
                    field: $path ?: $schema->name,
                    type: 'type',
                    message: 'Expected array, got '.gettype($data),
                    expected: 'array',
                    actual: gettype($data)
                ),
            ]);
        }

        // Validate each item
        foreach ($data as $index => $item) {
            $itemPath = "{$path}[{$index}]";
            $itemResult = $this->validateSchema($item, $schema->items, $itemPath);

            if (! $itemResult->isValid()) {
                $errors = array_merge($errors, $itemResult->getErrors());
            }
        }

        if (! empty($errors)) {
            return ValidationResult::failure($errors);
        }

        return ValidationResult::success();
    }

    /**
     * Validate a string value.
     */
    protected function validateString(mixed $data, StringSchema $schema, string $path): ValidationResult
    {
        if ($data === null && $schema->nullable) {
            return ValidationResult::success();
        }

        if (! is_string($data)) {
            return ValidationResult::failure([
                new ValidationError(
                    field: $path ?: $schema->name,
                    type: 'type',
                    message: 'Expected string, got '.gettype($data),
                    expected: 'string',
                    actual: gettype($data)
                ),
            ]);
        }

        return ValidationResult::success();
    }

    /**
     * Validate a number value.
     */
    protected function validateNumber(mixed $data, NumberSchema $schema, string $path): ValidationResult
    {
        if ($data === null && $schema->nullable) {
            return ValidationResult::success();
        }

        if (! is_int($data) && ! is_float($data)) {
            return ValidationResult::failure([
                new ValidationError(
                    field: $path ?: $schema->name,
                    type: 'type',
                    message: 'Expected number, got '.gettype($data),
                    expected: 'number',
                    actual: gettype($data)
                ),
            ]);
        }

        return ValidationResult::success();
    }

    /**
     * Validate a boolean value.
     */
    protected function validateBoolean(mixed $data, BooleanSchema $schema, string $path): ValidationResult
    {
        if ($data === null && $schema->nullable) {
            return ValidationResult::success();
        }

        if (! is_bool($data)) {
            return ValidationResult::failure([
                new ValidationError(
                    field: $path ?: $schema->name,
                    type: 'type',
                    message: 'Expected boolean, got '.gettype($data),
                    expected: 'boolean',
                    actual: gettype($data)
                ),
            ]);
        }

        return ValidationResult::success();
    }

    /**
     * Validate an enum value.
     */
    protected function validateEnum(mixed $data, EnumSchema $schema, string $path): ValidationResult
    {
        if ($data === null && $schema->nullable) {
            return ValidationResult::success();
        }

        if (! in_array($data, $schema->options, true)) {
            return ValidationResult::failure([
                new ValidationError(
                    field: $path ?: $schema->name,
                    type: 'enum',
                    message: 'Value must be one of: '.implode(', ', $schema->options),
                    expected: $schema->options,
                    actual: $data
                ),
            ]);
        }

        return ValidationResult::success();
    }

    /**
     * Build a dot-notation path for nested fields.
     */
    protected function buildPath(string $basePath, string $field): string
    {
        if ($basePath === '') {
            return $field;
        }

        return "{$basePath}.{$field}";
    }
}
