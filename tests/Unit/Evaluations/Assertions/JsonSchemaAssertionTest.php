<?php

namespace Vizra\VizraADK\Tests\Unit\Evaluations\Assertions;

use Vizra\VizraADK\Evaluations\Assertions\JsonSchemaAssertion;
use Vizra\VizraADK\Tests\TestCase;

class JsonSchemaAssertionTest extends TestCase
{
    private JsonSchemaAssertion $assertion;

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertion = new JsonSchemaAssertion;
    }

    public function test_assert_passes_with_valid_json()
    {
        $response = '{"name": "Test Product", "price": 99.99}';
        $result = $this->assertion->assert($response);

        $this->assertTrue($result['status']);
        $this->assertEquals('Response is valid JSON', $result['message']);
    }

    public function test_assert_fails_with_invalid_json()
    {
        $response = '{invalid json}';
        $result = $this->assertion->assert($response);

        $this->assertFalse($result['status']);
        $this->assertEquals('Response is not valid JSON', $result['message']);
        $this->assertStringContainsString('invalid JSON', $result['actual']);
    }

    public function test_assert_validates_object_type()
    {
        $response = '{"name": "Test"}';
        $schema = ['type' => 'object'];
        $result = $this->assertion->assert($response, $schema);

        $this->assertTrue($result['status']);
    }

    public function test_assert_validates_string_type()
    {
        $response = '"hello world"';
        $schema = ['type' => 'string'];
        $result = $this->assertion->assert($response, $schema);

        $this->assertTrue($result['status']);
    }

    public function test_assert_validates_number_type()
    {
        $response = '42.5';
        $schema = ['type' => 'number'];
        $result = $this->assertion->assert($response, $schema);

        $this->assertTrue($result['status']);
    }

    public function test_assert_validates_array_type()
    {
        $response = '[1, 2, 3]';
        $schema = ['type' => 'array'];
        $result = $this->assertion->assert($response, $schema);

        $this->assertTrue($result['status']);
    }

    public function test_assert_validates_required_properties()
    {
        $response = '{"name": "Test", "price": 99}';
        $schema = [
            'type' => 'object',
            'properties' => [
                'name' => ['type' => 'string'],
                'price' => ['type' => 'number'],
            ],
        ];
        $result = $this->assertion->assert($response, $schema);

        $this->assertTrue($result['status']);
    }

    public function test_assert_fails_with_missing_properties()
    {
        $response = '{"name": "Test"}';
        $schema = [
            'type' => 'object',
            'properties' => [
                'name' => ['type' => 'string'],
                'price' => ['type' => 'number'],
            ],
        ];
        $result = $this->assertion->assert($response, $schema);

        $this->assertFalse($result['status']);
        $this->assertStringContainsString("Missing required property 'price'", $result['actual']);
    }

    public function test_assert_validates_array_items()
    {
        $response = '[{"name": "Item1"}, {"name": "Item2"}]';
        $schema = [
            'type' => 'array',
            'items' => [
                'type' => 'object',
                'properties' => ['name' => ['type' => 'string']],
            ],
        ];
        $result = $this->assertion->assert($response, $schema);

        $this->assertTrue($result['status']);
    }

    public function test_assert_fails_with_invalid_array_items()
    {
        $response = '[{"name": "Item1"}, {"name": 123}]';
        $schema = [
            'type' => 'array',
            'items' => [
                'type' => 'object',
                'properties' => ['name' => ['type' => 'string']],
            ],
        ];
        $result = $this->assertion->assert($response, $schema);

        $this->assertFalse($result['status']);
        $this->assertStringContainsString('Array item [1]', $result['actual']);
    }

    public function test_assert_handles_type_mismatch()
    {
        $response = '123';
        $schema = ['type' => 'string'];
        $result = $this->assertion->assert($response, $schema);

        $this->assertFalse($result['status']);
        $this->assertStringContainsString("Expected type 'string', got 'integer'", $result['actual']);
    }
}
