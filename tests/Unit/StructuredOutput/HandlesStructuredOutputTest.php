<?php

namespace Vizra\VizraADK\Tests\Unit\StructuredOutput;

use Prism\Prism\Schema\NumberSchema;
use Prism\Prism\Schema\ObjectSchema;
use Prism\Prism\Schema\StringSchema;
use Vizra\VizraADK\StructuredOutput\HandlesStructuredOutput;
use Vizra\VizraADK\StructuredOutput\ValidationResult;
use Vizra\VizraADK\Tests\TestCase;

class HandlesStructuredOutputTest extends TestCase
{
    public function test_validate_structured_output_returns_valid_result(): void
    {
        $agent = new class
        {
            use HandlesStructuredOutput;

            public function getName(): string
            {
                return 'test_agent';
            }

            public function validate(array $data, $schema): ValidationResult
            {
                return $this->validateStructuredOutput($data, $schema);
            }
        };

        $schema = new ObjectSchema(
            name: 'user',
            description: 'A user',
            properties: [
                new StringSchema('name', 'Name'),
            ],
            requiredFields: ['name']
        );

        $result = $agent->validate(['name' => 'John'], $schema);

        $this->assertInstanceOf(ValidationResult::class, $result);
        $this->assertTrue($result->isValid());
    }

    public function test_validate_structured_output_returns_invalid_result(): void
    {
        $agent = new class
        {
            use HandlesStructuredOutput;

            public function getName(): string
            {
                return 'test_agent';
            }

            public function validate(array $data, $schema): ValidationResult
            {
                return $this->validateStructuredOutput($data, $schema);
            }
        };

        $schema = new ObjectSchema(
            name: 'user',
            description: 'A user',
            properties: [
                new StringSchema('name', 'Name'),
                new NumberSchema('age', 'Age'),
            ],
            requiredFields: ['name', 'age']
        );

        $result = $agent->validate(['name' => 'John'], $schema);

        $this->assertFalse($result->isValid());
        $this->assertNotEmpty($result->getErrors());
    }

    public function test_get_and_set_max_retries(): void
    {
        $agent = new class
        {
            use HandlesStructuredOutput;
        };

        $this->assertEquals(3, $agent->getStructuredOutputMaxRetries());

        $agent->setStructuredOutputMaxRetries(5);

        $this->assertEquals(5, $agent->getStructuredOutputMaxRetries());
    }

    public function test_set_max_retries_is_fluent(): void
    {
        $agent = new class
        {
            use HandlesStructuredOutput;
        };

        $result = $agent->setStructuredOutputMaxRetries(5);

        $this->assertSame($agent, $result);
    }
}
