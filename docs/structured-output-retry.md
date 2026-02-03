# Structured Output Retry

When using structured outputs with LLMs, the model may occasionally return data that doesn't match the expected schema. This feature automatically validates responses and retries with helpful error context to get properly formatted data.

## The Problem

Without validation, an LLM might return:

```json
{"name": "John", "age": "thirty"}  // age should be a number
```

Or miss required fields entirely. Prism PHP doesn't validate responses against schemas - it just parses JSON.

## The Solution

Vizra ADK adds a validation and retry layer:

1. **Validate** the response against your Prism schema
2. **Build repair prompt** explaining what's wrong
3. **Retry** with the error context
4. **Repeat** up to N times until valid or exhausted

## Basic Usage

### Using the Trait

Add `HandlesStructuredOutput` to your agent:

```php
use Vizra\VizraADK\Agents\BaseLlmAgent;
use Vizra\VizraADK\StructuredOutput\HandlesStructuredOutput;
use Prism\Prism\Schema\ObjectSchema;
use Prism\Prism\Schema\StringSchema;
use Prism\Prism\Schema\NumberSchema;

class MyAgent extends BaseLlmAgent
{
    use HandlesStructuredOutput;

    protected int $structuredOutputMaxRetries = 3;

    public function getSchema(): ?Schema
    {
        return new ObjectSchema(
            name: 'analysis',
            description: 'Analysis result',
            properties: [
                new StringSchema('summary', 'Brief summary'),
                new NumberSchema('confidence', 'Confidence score 0-1'),
                new StringSchema('recommendation', 'Recommendation'),
            ],
            requiredFields: ['summary', 'confidence', 'recommendation']
        );
    }
}
```

### Manual Validation

Validate data without retry:

```php
use Vizra\VizraADK\StructuredOutput\Validator;

$schema = new ObjectSchema(...);
$data = ['name' => 'John', 'age' => 30];

$result = Validator::validate($data, $schema);

if ($result->isValid()) {
    // Use the data
} else {
    foreach ($result->getErrors() as $error) {
        echo "{$error->field}: {$error->message}\n";
    }
}
```

### Manual Retry Handler

For more control over the retry process:

```php
use Vizra\VizraADK\StructuredOutput\RetryHandler;

$handler = new RetryHandler(
    schema: $schema,
    responseGenerator: function (?string $repairPrompt = null) use ($prismRequest) {
        if ($repairPrompt) {
            // Add repair instructions to messages
        }
        return $prismRequest->asStructured();
    },
    maxRetries: 3
);

$handler->onRetry(function (int $attempt, array $errors) {
    logger()->warning("Retry attempt {$attempt}", ['errors' => $errors]);
});

$handler->onSuccess(function (array $data, int $retryCount) {
    logger()->info("Valid after {$retryCount} retries");
});

$handler->onFailure(function (array $errors, int $totalAttempts) {
    logger()->error("Failed after {$totalAttempts} attempts");
});

$result = $handler->execute();

if ($result->isValid()) {
    $data = $result->getData();
} else {
    $errors = $result->getValidationErrors();
}
```

## Validation Errors

The validator checks:

| Check | Error Type | Example |
|-------|------------|---------|
| Required fields | `required` | Missing `email` field |
| Type mismatch | `type` | String instead of number |
| Enum values | `enum` | Invalid status value |
| Nested objects | `required`/`type` | Missing nested field |
| Array items | `type` | Wrong type in array |

### Error Object

```php
$error = $result->getErrors()[0];

$error->field;    // "user.email" or "items[0].name"
$error->type;     // "required", "type", "enum"
$error->message;  // "Missing required field: email"
$error->expected; // "string"
$error->actual;   // "integer"
```

## Repair Prompts

When validation fails, a repair prompt is automatically generated:

```
Your previous response did not match the required schema. Please fix the following issues:

## Missing Required Fields
- `email` is required but was not provided
- `age` is required but was not provided

## Incorrect Types
- `name`: Expected string, got integer

Please provide a complete response that:
1. Includes ALL required fields
2. Uses the correct data types for each field
3. Uses valid enum values where specified

Respond with valid JSON matching the schema.
```

## Result Object

The `RetryResult` provides detailed information:

```php
$result = $handler->execute();

// Status
$result->isValid();           // bool
$result->getRetryCount();     // int (0 if valid on first try)

// Data
$result->getData();           // array - the validated data
$result->getResponse();       // Prism StructuredResponse

// Errors (if failed)
$result->getValidationErrors(); // ValidationError[]

// History
$result->getAttempts();       // Array of all attempts with data/errors

// Export
$result->toArray();
```

## Configuration

### Max Retries

Set per-agent:

```php
class MyAgent extends BaseLlmAgent
{
    use HandlesStructuredOutput;

    protected int $structuredOutputMaxRetries = 5;
}
```

Or at runtime:

```php
$agent->setStructuredOutputMaxRetries(2);
```

### Logging

All retries are automatically logged:

- `debug` - Success on first try
- `info` - Success after retries
- `warning` - Each retry attempt with errors
- `error` - Failure after all retries exhausted

## Supported Schema Types

| Prism Schema | Validation |
|--------------|------------|
| `ObjectSchema` | Required fields, nested validation |
| `ArraySchema` | Item type validation |
| `StringSchema` | Type check, nullable |
| `NumberSchema` | Type check (int/float), nullable |
| `BooleanSchema` | Type check, nullable |
| `EnumSchema` | Value in options list |

## Best Practices

1. **Start with 3 retries** - Usually sufficient for most cases
2. **Use specific schemas** - More specific = better error messages
3. **Mark optional fields** - Don't require fields that aren't critical
4. **Monitor retry rates** - High retry rates indicate prompt issues
5. **Use nullable for optional** - `new StringSchema('bio', 'Bio', nullable: true)`

## Example: Complete Agent

```php
class ProductAnalyzerAgent extends BaseLlmAgent
{
    use HandlesStructuredOutput;

    protected string $name = 'product_analyzer';
    protected string $model = 'gpt-4o';
    protected int $structuredOutputMaxRetries = 3;

    protected string $instructions = <<<PROMPT
    Analyze the given product and provide structured insights.
    Always include all required fields in your response.
    PROMPT;

    public function getSchema(): ?Schema
    {
        return new ObjectSchema(
            name: 'product_analysis',
            description: 'Product analysis result',
            properties: [
                new StringSchema('product_name', 'Name of the product'),
                new StringSchema('category', 'Product category'),
                new NumberSchema('price_score', 'Price competitiveness 1-10'),
                new NumberSchema('quality_score', 'Quality assessment 1-10'),
                new ArraySchema(
                    'pros',
                    'List of advantages',
                    new StringSchema('pro', 'An advantage')
                ),
                new ArraySchema(
                    'cons',
                    'List of disadvantages',
                    new StringSchema('con', 'A disadvantage')
                ),
                new StringSchema('recommendation', 'Buy/Skip/Wait recommendation'),
            ],
            requiredFields: [
                'product_name',
                'category',
                'price_score',
                'quality_score',
                'pros',
                'cons',
                'recommendation'
            ]
        );
    }
}
```

## API Reference

### Validator

```php
Validator::validate(mixed $data, Schema $schema): ValidationResult
```

### ValidationResult

| Method | Returns | Description |
|--------|---------|-------------|
| `isValid()` | `bool` | Whether validation passed |
| `getErrors()` | `ValidationError[]` | All errors |
| `getErrorMessages()` | `string[]` | Error messages |
| `getErrorsByField()` | `array<string, ValidationError[]>` | Errors grouped by field |
| `toArray()` | `array` | Export as array |

### RetryHandler

| Method | Description |
|--------|-------------|
| `onRetry(Closure)` | Callback on each retry |
| `onSuccess(Closure)` | Callback on success |
| `onFailure(Closure)` | Callback on final failure |
| `execute()` | Run with retry logic |

### RetryResult

| Method | Returns | Description |
|--------|---------|-------------|
| `isValid()` | `bool` | Whether finally valid |
| `getData()` | `array` | The validated data |
| `getResponse()` | `StructuredResponse` | Prism response |
| `getRetryCount()` | `int` | Number of retries |
| `getAttempts()` | `array` | All attempt details |
| `getValidationErrors()` | `ValidationError[]` | Final errors if failed |
| `toArray()` | `array` | Export as array |
