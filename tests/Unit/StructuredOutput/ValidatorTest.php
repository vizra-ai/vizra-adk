<?php

namespace Vizra\VizraADK\Tests\Unit\StructuredOutput;

use Prism\Prism\Schema\ArraySchema;
use Prism\Prism\Schema\BooleanSchema;
use Prism\Prism\Schema\EnumSchema;
use Prism\Prism\Schema\NumberSchema;
use Prism\Prism\Schema\ObjectSchema;
use Prism\Prism\Schema\StringSchema;
use Vizra\VizraADK\StructuredOutput\ValidationError;
use Vizra\VizraADK\StructuredOutput\Validator;
use Vizra\VizraADK\Tests\TestCase;

class ValidatorTest extends TestCase
{
    // ==========================================
    // Basic Validation Tests
    // ==========================================

    public function test_validates_simple_object_with_required_fields(): void
    {
        $schema = new ObjectSchema(
            name: 'user',
            description: 'A user object',
            properties: [
                new StringSchema('name', 'User name'),
                new StringSchema('email', 'User email'),
            ],
            requiredFields: ['name', 'email']
        );

        $data = ['name' => 'John', 'email' => 'john@example.com'];

        $result = Validator::validate($data, $schema);

        $this->assertTrue($result->isValid());
        $this->assertEmpty($result->getErrors());
    }

    public function test_fails_when_required_field_missing(): void
    {
        $schema = new ObjectSchema(
            name: 'user',
            description: 'A user object',
            properties: [
                new StringSchema('name', 'User name'),
                new StringSchema('email', 'User email'),
            ],
            requiredFields: ['name', 'email']
        );

        $data = ['name' => 'John']; // missing email

        $result = Validator::validate($data, $schema);

        $this->assertFalse($result->isValid());
        $this->assertCount(1, $result->getErrors());
        $this->assertEquals('email', $result->getErrors()[0]->field);
        $this->assertEquals('required', $result->getErrors()[0]->type);
    }

    public function test_fails_when_multiple_required_fields_missing(): void
    {
        $schema = new ObjectSchema(
            name: 'user',
            description: 'A user object',
            properties: [
                new StringSchema('name', 'User name'),
                new StringSchema('email', 'User email'),
                new NumberSchema('age', 'User age'),
            ],
            requiredFields: ['name', 'email', 'age']
        );

        $data = ['name' => 'John']; // missing email and age

        $result = Validator::validate($data, $schema);

        $this->assertFalse($result->isValid());
        $this->assertCount(2, $result->getErrors());
    }

    // ==========================================
    // Type Validation Tests
    // ==========================================

    public function test_fails_when_string_field_has_wrong_type(): void
    {
        $schema = new ObjectSchema(
            name: 'user',
            description: 'A user object',
            properties: [
                new StringSchema('name', 'User name'),
            ],
            requiredFields: ['name']
        );

        $data = ['name' => 123]; // should be string

        $result = Validator::validate($data, $schema);

        $this->assertFalse($result->isValid());
        $this->assertEquals('type', $result->getErrors()[0]->type);
        $this->assertStringContainsString('string', $result->getErrors()[0]->message);
    }

    public function test_fails_when_number_field_has_wrong_type(): void
    {
        $schema = new ObjectSchema(
            name: 'product',
            description: 'A product',
            properties: [
                new NumberSchema('price', 'Product price'),
            ],
            requiredFields: ['price']
        );

        $data = ['price' => 'not a number'];

        $result = Validator::validate($data, $schema);

        $this->assertFalse($result->isValid());
        $this->assertEquals('type', $result->getErrors()[0]->type);
    }

    public function test_fails_when_boolean_field_has_wrong_type(): void
    {
        $schema = new ObjectSchema(
            name: 'settings',
            description: 'Settings',
            properties: [
                new BooleanSchema('enabled', 'Is enabled'),
            ],
            requiredFields: ['enabled']
        );

        $data = ['enabled' => 'yes']; // should be boolean

        $result = Validator::validate($data, $schema);

        $this->assertFalse($result->isValid());
        $this->assertEquals('type', $result->getErrors()[0]->type);
    }

    public function test_accepts_integer_for_number_field(): void
    {
        $schema = new ObjectSchema(
            name: 'product',
            description: 'A product',
            properties: [
                new NumberSchema('price', 'Product price'),
            ],
            requiredFields: ['price']
        );

        $data = ['price' => 100]; // integer is valid for number

        $result = Validator::validate($data, $schema);

        $this->assertTrue($result->isValid());
    }

    public function test_accepts_float_for_number_field(): void
    {
        $schema = new ObjectSchema(
            name: 'product',
            description: 'A product',
            properties: [
                new NumberSchema('price', 'Product price'),
            ],
            requiredFields: ['price']
        );

        $data = ['price' => 99.99];

        $result = Validator::validate($data, $schema);

        $this->assertTrue($result->isValid());
    }

    // ==========================================
    // Enum Validation Tests
    // ==========================================

    public function test_validates_enum_field_with_valid_value(): void
    {
        $schema = new ObjectSchema(
            name: 'order',
            description: 'An order',
            properties: [
                new EnumSchema('status', 'Order status', ['pending', 'shipped', 'delivered']),
            ],
            requiredFields: ['status']
        );

        $data = ['status' => 'shipped'];

        $result = Validator::validate($data, $schema);

        $this->assertTrue($result->isValid());
    }

    public function test_fails_enum_field_with_invalid_value(): void
    {
        $schema = new ObjectSchema(
            name: 'order',
            description: 'An order',
            properties: [
                new EnumSchema('status', 'Order status', ['pending', 'shipped', 'delivered']),
            ],
            requiredFields: ['status']
        );

        $data = ['status' => 'cancelled']; // not in enum

        $result = Validator::validate($data, $schema);

        $this->assertFalse($result->isValid());
        $this->assertEquals('enum', $result->getErrors()[0]->type);
        $this->assertStringContainsString('pending', $result->getErrors()[0]->message);
    }

    // ==========================================
    // Array Validation Tests
    // ==========================================

    public function test_validates_array_of_strings(): void
    {
        $schema = new ObjectSchema(
            name: 'tags',
            description: 'Tags container',
            properties: [
                new ArraySchema(
                    'items',
                    'List of tags',
                    new StringSchema('tag', 'A tag')
                ),
            ],
            requiredFields: ['items']
        );

        $data = ['items' => ['php', 'laravel', 'ai']];

        $result = Validator::validate($data, $schema);

        $this->assertTrue($result->isValid());
    }

    public function test_fails_when_array_contains_wrong_type(): void
    {
        $schema = new ObjectSchema(
            name: 'tags',
            description: 'Tags container',
            properties: [
                new ArraySchema(
                    'items',
                    'List of tags',
                    new StringSchema('tag', 'A tag')
                ),
            ],
            requiredFields: ['items']
        );

        $data = ['items' => ['php', 123, 'ai']]; // 123 is not a string

        $result = Validator::validate($data, $schema);

        $this->assertFalse($result->isValid());
        $this->assertStringContainsString('items[1]', $result->getErrors()[0]->field);
    }

    public function test_validates_array_of_objects(): void
    {
        $schema = new ObjectSchema(
            name: 'users',
            description: 'Users container',
            properties: [
                new ArraySchema(
                    'users',
                    'List of users',
                    new ObjectSchema(
                        'user',
                        'A user',
                        [
                            new StringSchema('name', 'Name'),
                            new NumberSchema('age', 'Age'),
                        ],
                        requiredFields: ['name', 'age']
                    )
                ),
            ],
            requiredFields: ['users']
        );

        $data = [
            'users' => [
                ['name' => 'John', 'age' => 30],
                ['name' => 'Jane', 'age' => 25],
            ],
        ];

        $result = Validator::validate($data, $schema);

        $this->assertTrue($result->isValid());
    }

    public function test_fails_when_nested_object_in_array_invalid(): void
    {
        $schema = new ObjectSchema(
            name: 'users',
            description: 'Users container',
            properties: [
                new ArraySchema(
                    'users',
                    'List of users',
                    new ObjectSchema(
                        'user',
                        'A user',
                        [
                            new StringSchema('name', 'Name'),
                            new NumberSchema('age', 'Age'),
                        ],
                        requiredFields: ['name', 'age']
                    )
                ),
            ],
            requiredFields: ['users']
        );

        $data = [
            'users' => [
                ['name' => 'John', 'age' => 30],
                ['name' => 'Jane'], // missing age
            ],
        ];

        $result = Validator::validate($data, $schema);

        $this->assertFalse($result->isValid());
        $this->assertStringContainsString('users[1].age', $result->getErrors()[0]->field);
    }

    // ==========================================
    // Nested Object Validation Tests
    // ==========================================

    public function test_validates_nested_objects(): void
    {
        $schema = new ObjectSchema(
            name: 'order',
            description: 'An order',
            properties: [
                new StringSchema('id', 'Order ID'),
                new ObjectSchema(
                    'customer',
                    'Customer info',
                    [
                        new StringSchema('name', 'Name'),
                        new StringSchema('email', 'Email'),
                    ],
                    requiredFields: ['name', 'email']
                ),
            ],
            requiredFields: ['id', 'customer']
        );

        $data = [
            'id' => 'ORD-123',
            'customer' => [
                'name' => 'John',
                'email' => 'john@example.com',
            ],
        ];

        $result = Validator::validate($data, $schema);

        $this->assertTrue($result->isValid());
    }

    public function test_fails_when_nested_object_field_missing(): void
    {
        $schema = new ObjectSchema(
            name: 'order',
            description: 'An order',
            properties: [
                new StringSchema('id', 'Order ID'),
                new ObjectSchema(
                    'customer',
                    'Customer info',
                    [
                        new StringSchema('name', 'Name'),
                        new StringSchema('email', 'Email'),
                    ],
                    requiredFields: ['name', 'email']
                ),
            ],
            requiredFields: ['id', 'customer']
        );

        $data = [
            'id' => 'ORD-123',
            'customer' => [
                'name' => 'John',
                // missing email
            ],
        ];

        $result = Validator::validate($data, $schema);

        $this->assertFalse($result->isValid());
        $this->assertStringContainsString('customer.email', $result->getErrors()[0]->field);
    }

    // ==========================================
    // Nullable Tests
    // ==========================================

    public function test_accepts_null_for_nullable_field(): void
    {
        $schema = new ObjectSchema(
            name: 'user',
            description: 'A user',
            properties: [
                new StringSchema('name', 'Name'),
                new StringSchema('nickname', 'Nickname', nullable: true),
            ],
            requiredFields: ['name', 'nickname']
        );

        $data = ['name' => 'John', 'nickname' => null];

        $result = Validator::validate($data, $schema);

        $this->assertTrue($result->isValid());
    }

    public function test_fails_null_for_non_nullable_field(): void
    {
        $schema = new ObjectSchema(
            name: 'user',
            description: 'A user',
            properties: [
                new StringSchema('name', 'Name', nullable: false),
            ],
            requiredFields: ['name']
        );

        $data = ['name' => null];

        $result = Validator::validate($data, $schema);

        $this->assertFalse($result->isValid());
        $this->assertEquals('type', $result->getErrors()[0]->type);
    }

    // ==========================================
    // Optional Fields Tests
    // ==========================================

    public function test_accepts_missing_optional_field(): void
    {
        $schema = new ObjectSchema(
            name: 'user',
            description: 'A user',
            properties: [
                new StringSchema('name', 'Name'),
                new StringSchema('bio', 'Bio'),
            ],
            requiredFields: ['name'] // bio is optional
        );

        $data = ['name' => 'John']; // no bio

        $result = Validator::validate($data, $schema);

        $this->assertTrue($result->isValid());
    }

    public function test_validates_optional_field_when_present(): void
    {
        $schema = new ObjectSchema(
            name: 'user',
            description: 'A user',
            properties: [
                new StringSchema('name', 'Name'),
                new StringSchema('bio', 'Bio'),
            ],
            requiredFields: ['name']
        );

        $data = ['name' => 'John', 'bio' => 123]; // bio wrong type

        $result = Validator::validate($data, $schema);

        $this->assertFalse($result->isValid());
    }

    // ==========================================
    // ValidationResult API Tests
    // ==========================================

    public function test_result_provides_error_messages(): void
    {
        $schema = new ObjectSchema(
            name: 'user',
            description: 'A user',
            properties: [
                new StringSchema('name', 'Name'),
            ],
            requiredFields: ['name']
        );

        $data = [];

        $result = Validator::validate($data, $schema);

        $messages = $result->getErrorMessages();

        $this->assertIsArray($messages);
        $this->assertNotEmpty($messages);
        $this->assertIsString($messages[0]);
    }

    public function test_result_provides_errors_by_field(): void
    {
        $schema = new ObjectSchema(
            name: 'user',
            description: 'A user',
            properties: [
                new StringSchema('name', 'Name'),
                new StringSchema('email', 'Email'),
            ],
            requiredFields: ['name', 'email']
        );

        $data = [];

        $result = Validator::validate($data, $schema);

        $byField = $result->getErrorsByField();

        $this->assertArrayHasKey('name', $byField);
        $this->assertArrayHasKey('email', $byField);
    }

    public function test_result_to_array(): void
    {
        $schema = new ObjectSchema(
            name: 'user',
            description: 'A user',
            properties: [
                new StringSchema('name', 'Name'),
            ],
            requiredFields: ['name']
        );

        $data = [];

        $result = Validator::validate($data, $schema);

        $array = $result->toArray();

        $this->assertArrayHasKey('valid', $array);
        $this->assertArrayHasKey('errors', $array);
        $this->assertFalse($array['valid']);
    }

    // ==========================================
    // Edge Cases
    // ==========================================

    public function test_handles_empty_data(): void
    {
        $schema = new ObjectSchema(
            name: 'user',
            description: 'A user',
            properties: [
                new StringSchema('name', 'Name'),
            ],
            requiredFields: ['name']
        );

        $result = Validator::validate([], $schema);

        $this->assertFalse($result->isValid());
    }

    public function test_handles_non_array_data(): void
    {
        $schema = new ObjectSchema(
            name: 'user',
            description: 'A user',
            properties: [
                new StringSchema('name', 'Name'),
            ],
            requiredFields: ['name']
        );

        $result = Validator::validate('not an array', $schema);

        $this->assertFalse($result->isValid());
        $this->assertEquals('type', $result->getErrors()[0]->type);
    }

    public function test_handles_null_data(): void
    {
        $schema = new ObjectSchema(
            name: 'user',
            description: 'A user',
            properties: [
                new StringSchema('name', 'Name'),
            ],
            requiredFields: ['name']
        );

        $result = Validator::validate(null, $schema);

        $this->assertFalse($result->isValid());
    }
}
