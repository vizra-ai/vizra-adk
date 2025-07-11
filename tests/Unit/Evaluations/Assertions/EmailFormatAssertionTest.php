<?php

namespace Vizra\VizraADK\Tests\Unit\Evaluations\Assertions;

use Vizra\VizraADK\Evaluations\Assertions\EmailFormatAssertion;
use Vizra\VizraADK\Tests\TestCase;

class EmailFormatAssertionTest extends TestCase
{
    private EmailFormatAssertion $assertion;

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertion = new EmailFormatAssertion;
    }

    public function test_assert_passes_with_single_email()
    {
        $response = 'Contact us at support@example.com';
        $result = $this->assertion->assert($response);

        $this->assertTrue($result['status']);
        $this->assertStringContainsString('support@example.com', $result['actual']);
    }

    public function test_assert_passes_with_multiple_emails()
    {
        $response = 'Email john@company.com or jane@company.com for help';
        $result = $this->assertion->assert($response, 2);

        $this->assertTrue($result['status']);
        $this->assertStringContainsString('found 2', $result['actual']);
        $this->assertStringContainsString('john@company.com', $result['actual']);
        $this->assertStringContainsString('jane@company.com', $result['actual']);
    }

    public function test_assert_fails_when_no_email_found()
    {
        $response = 'No contact information available';
        $result = $this->assertion->assert($response);

        $this->assertFalse($result['status']);
        $this->assertEquals('no email found', $result['actual']);
    }

    public function test_assert_fails_when_insufficient_emails()
    {
        $response = 'Only one email: test@example.com';
        $result = $this->assertion->assert($response, 2);

        $this->assertFalse($result['status']);
        $this->assertEquals('found only 1', $result['actual']);
    }

    public function test_assert_validates_email_format()
    {
        $response = 'Invalid: test@, valid: test@example.com';
        $result = $this->assertion->assert($response);

        $this->assertTrue($result['status']);
        $this->assertStringContainsString('test@example.com', $result['actual']);
        // The result contains the full email, not just 'test@'
    }

    public function test_assert_handles_duplicate_emails()
    {
        $response = 'Email admin@site.com or admin@site.com for support';
        $result = $this->assertion->assert($response);

        $this->assertTrue($result['status']);
        // Should show unique emails
        $this->assertEquals(1, substr_count($result['actual'], 'admin@site.com'));
    }

    public function test_assert_with_complex_email_formats()
    {
        $response = 'Contact first.last+tag@sub.example.com';
        $result = $this->assertion->assert($response);

        $this->assertTrue($result['status']);
        $this->assertStringContainsString('first.last+tag@sub.example.com', $result['actual']);
    }

    public function test_assert_message_reflects_minimum_requirement()
    {
        $response = 'No emails here';
        $result = $this->assertion->assert($response, 3);

        $this->assertFalse($result['status']);
        $this->assertStringContainsString('at least 3 email addresses', $result['message']);
    }

    public function test_assert_with_uppercase_emails()
    {
        $response = 'Email SUPPORT@EXAMPLE.COM';
        $result = $this->assertion->assert($response);

        $this->assertTrue($result['status']);
        $this->assertStringContainsString('SUPPORT@EXAMPLE.COM', $result['actual']);
    }

    public function test_get_name_returns_correct_name()
    {
        $this->assertEquals('EmailFormatAssertion', $this->assertion->getName());
    }
}
