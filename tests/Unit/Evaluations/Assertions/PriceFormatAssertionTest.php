<?php

namespace Vizra\VizraADK\Tests\Unit\Evaluations\Assertions;

use Vizra\VizraADK\Evaluations\Assertions\PriceFormatAssertion;
use Vizra\VizraADK\Tests\TestCase;

class PriceFormatAssertionTest extends TestCase
{
    private PriceFormatAssertion $assertion;

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertion = new PriceFormatAssertion;
    }

    public function test_assert_passes_with_dollar_price()
    {
        $response = 'The product costs $99.99';
        $result = $this->assertion->assert($response);

        $this->assertTrue($result['status']);
        $this->assertEquals('found: $99.99', $result['actual']);
    }

    public function test_assert_passes_with_comma_separated_price()
    {
        $response = 'Total amount: $1,299.50';
        $result = $this->assertion->assert($response);

        $this->assertTrue($result['status']);
        $this->assertEquals('found: $1,299.50', $result['actual']);
    }

    public function test_assert_passes_with_whole_dollar_amount()
    {
        $response = 'Price: $100';
        $result = $this->assertion->assert($response);

        $this->assertTrue($result['status']);
        $this->assertEquals('found: $100', $result['actual']);
    }

    public function test_assert_passes_with_euro_symbol()
    {
        $response = 'Cost: €50.00';
        $result = $this->assertion->assert($response, '€');

        $this->assertTrue($result['status']);
        $this->assertEquals('found: €50.00', $result['actual']);
    }

    public function test_assert_passes_with_pound_symbol()
    {
        $response = 'Price in UK: £25.99';
        $result = $this->assertion->assert($response, '£');

        $this->assertTrue($result['status']);
        $this->assertEquals('found: £25.99', $result['actual']);
    }

    public function test_assert_passes_with_written_dollar_format()
    {
        $response = 'The cost is 50 dollars';
        $result = $this->assertion->assert($response);

        $this->assertTrue($result['status']);
        $this->assertEquals('found: 50 dollars', $result['actual']);
    }

    public function test_assert_passes_with_written_euro_format()
    {
        $response = 'Price: 100 euros';
        $result = $this->assertion->assert($response, '€');

        $this->assertTrue($result['status']);
        $this->assertEquals('found: 100 euros', $result['actual']);
    }

    public function test_assert_fails_when_no_price_found()
    {
        $response = 'This product has no price information';
        $result = $this->assertion->assert($response);

        $this->assertFalse($result['status']);
        $this->assertEquals('no price found', $result['actual']);
    }

    public function test_assert_with_space_after_currency()
    {
        $response = 'Price: $ 99.99';
        $result = $this->assertion->assert($response);

        $this->assertTrue($result['status']);
        $this->assertEquals('found: $ 99.99', $result['actual']);
    }

    public function test_assert_with_custom_currency()
    {
        $response = 'Price: R$50.00';
        $result = $this->assertion->assert($response, 'R$');

        $this->assertTrue($result['status']);
        $this->assertEquals('found: R$50.00', $result['actual']);
    }

    public function test_assert_message_includes_currency()
    {
        $response = 'No price here';
        $result = $this->assertion->assert($response, '€');

        $this->assertFalse($result['status']);
        $this->assertStringContainsString('€', $result['message']);
    }
}
