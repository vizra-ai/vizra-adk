<?php

namespace Vizra\VizraADK\Tests\Unit\Evaluations\Assertions;

use Vizra\VizraADK\Evaluations\Assertions\BaseAssertion;
use Vizra\VizraADK\Tests\TestCase;

class BaseAssertionTest extends TestCase
{
    private $assertion;

    protected function setUp(): void
    {
        parent::setUp();

        $this->assertion = new class extends BaseAssertion
        {
            public function assert(string $response, ...$params): array
            {
                return $this->result(true, 'Test passed', 'expected', 'actual');
            }
        };
    }

    public function test_get_name_returns_class_basename()
    {
        // Anonymous classes in PHP return a generated name like "BaseAssertionTest.php:16$xxx"
        $name = $this->assertion->getName();
        $this->assertIsString($name);
        $this->assertNotEmpty($name);
    }

    public function test_result_method_returns_correct_structure()
    {
        $result = $this->assertion->assert('test response');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('status', $result);
        $this->assertArrayHasKey('message', $result);
        $this->assertArrayHasKey('expected', $result);
        $this->assertArrayHasKey('actual', $result);
        $this->assertTrue($result['status']);
        $this->assertEquals('Test passed', $result['message']);
    }

    public function test_result_method_with_null_values()
    {
        $assertion = new class extends BaseAssertion
        {
            public function assert(string $response, ...$params): array
            {
                return $this->result(false, 'Test failed');
            }
        };

        $result = $assertion->assert('test');

        $this->assertArrayHasKey('status', $result);
        $this->assertArrayHasKey('message', $result);
        $this->assertArrayNotHasKey('expected', $result);
        $this->assertArrayNotHasKey('actual', $result);
        $this->assertFalse($result['status']);
    }

    public function test_result_method_with_only_expected()
    {
        $assertion = new class extends BaseAssertion
        {
            public function assert(string $response, ...$params): array
            {
                return $this->result(true, 'Test passed', 'expected value');
            }
        };

        $result = $assertion->assert('test');

        $this->assertArrayHasKey('expected', $result);
        $this->assertArrayNotHasKey('actual', $result);
        $this->assertEquals('expected value', $result['expected']);
    }
}
