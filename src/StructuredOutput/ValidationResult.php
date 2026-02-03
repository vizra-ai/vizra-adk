<?php

namespace Vizra\VizraADK\StructuredOutput;

/**
 * Result of validating data against a schema.
 */
class ValidationResult
{
    /**
     * @param  array<ValidationError>  $errors
     */
    public function __construct(
        protected bool $valid,
        protected array $errors = [],
    ) {}

    public function isValid(): bool
    {
        return $this->valid;
    }

    /**
     * @return array<ValidationError>
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * @return array<string>
     */
    public function getErrorMessages(): array
    {
        return array_map(fn (ValidationError $e) => (string) $e, $this->errors);
    }

    /**
     * Get errors grouped by field name.
     *
     * @return array<string, array<ValidationError>>
     */
    public function getErrorsByField(): array
    {
        $grouped = [];

        foreach ($this->errors as $error) {
            $grouped[$error->field][] = $error;
        }

        return $grouped;
    }

    public function toArray(): array
    {
        return [
            'valid' => $this->valid,
            'errors' => array_map(fn (ValidationError $e) => $e->toArray(), $this->errors),
        ];
    }

    public static function success(): static
    {
        return new static(true, []);
    }

    /**
     * @param  array<ValidationError>  $errors
     */
    public static function failure(array $errors): static
    {
        return new static(false, $errors);
    }
}
