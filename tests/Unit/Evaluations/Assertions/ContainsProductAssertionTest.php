<?php

namespace Vizra\VizraADK\Tests\Unit\Evaluations\Assertions;

use Vizra\VizraADK\Evaluations\Assertions\ContainsProductAssertion;
use Vizra\VizraADK\Tests\TestCase;

class ContainsProductAssertionTest extends TestCase
{
    private ContainsProductAssertion $assertion;

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertion = new ContainsProductAssertion;
    }

    public function test_assert_passes_when_product_found()
    {
        $response = 'The new iPhone 15 is amazing with great features.';
        $result = $this->assertion->assert($response, 'iPhone 15');

        $this->assertTrue($result['status']);
        $this->assertStringContainsString('iPhone 15', $result['message']);
        $this->assertEquals("contains 'iPhone 15'", $result['expected']);
        $this->assertEquals("found 'iPhone 15'", $result['actual']);
    }

    public function test_assert_fails_when_product_not_found()
    {
        $response = 'This is a generic response about smartphones.';
        $result = $this->assertion->assert($response, 'iPhone 15');

        $this->assertFalse($result['status']);
        $this->assertEquals('product not mentioned', $result['actual']);
    }

    public function test_assert_is_case_insensitive()
    {
        $response = 'The new IPHONE 15 is here!';
        $result = $this->assertion->assert($response, 'iPhone 15');

        $this->assertTrue($result['status']);
    }

    public function test_assert_fails_with_empty_product_name()
    {
        $response = 'Some response text';
        $result = $this->assertion->assert($response);

        $this->assertFalse($result['status']);
        $this->assertEquals('Product name parameter is required', $result['message']);
    }

    public function test_assert_with_special_characters()
    {
        $response = 'Check out the Samsung Galaxy S24+!';
        $result = $this->assertion->assert($response, 'Galaxy S24+');

        $this->assertTrue($result['status']);
        $this->assertEquals("found 'Galaxy S24+'", $result['actual']);
    }

    public function test_get_name_returns_correct_name()
    {
        $this->assertEquals('ContainsProductAssertion', $this->assertion->getName());
    }
}
