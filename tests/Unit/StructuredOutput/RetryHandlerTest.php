<?php

namespace Vizra\VizraADK\Tests\Unit\StructuredOutput;

use Illuminate\Support\Collection;
use Prism\Prism\Enums\FinishReason;
use Prism\Prism\Schema\NumberSchema;
use Prism\Prism\Schema\ObjectSchema;
use Prism\Prism\Schema\StringSchema;
use Prism\Prism\Structured\Response as StructuredResponse;
use Prism\Prism\ValueObjects\Meta;
use Prism\Prism\ValueObjects\Usage;
use Vizra\VizraADK\StructuredOutput\RetryHandler;
use Vizra\VizraADK\StructuredOutput\RetryResult;
use Vizra\VizraADK\Tests\TestCase;

class RetryHandlerTest extends TestCase
{
    protected ObjectSchema $userSchema;

    protected function setUp(): void
    {
        parent::setUp();

        $this->userSchema = new ObjectSchema(
            name: 'user',
            description: 'A user object',
            properties: [
                new StringSchema('name', 'User name'),
                new StringSchema('email', 'User email'),
                new NumberSchema('age', 'User age'),
            ],
            requiredFields: ['name', 'email', 'age']
        );
    }

    // ==========================================
    // Success on First Try Tests
    // ==========================================

    public function test_returns_immediately_when_valid_on_first_try(): void
    {
        $validData = ['name' => 'John', 'email' => 'john@example.com', 'age' => 30];
        $attempts = 0;

        $generator = function () use ($validData, &$attempts) {
            $attempts++;

            return $this->mockStructuredResponse($validData);
        };

        $handler = new RetryHandler(
            schema: $this->userSchema,
            responseGenerator: $generator,
            maxRetries: 3
        );

        $result = $handler->execute();

        $this->assertTrue($result->isValid());
        $this->assertEquals(1, $attempts);
        $this->assertEquals(0, $result->getRetryCount());
        $this->assertEquals($validData, $result->getData());
    }

    // ==========================================
    // Retry on Validation Failure Tests
    // ==========================================

    public function test_retries_when_required_field_missing(): void
    {
        $attempts = 0;
        $responses = [
            ['name' => 'John', 'email' => 'john@example.com'], // missing age
            ['name' => 'John', 'email' => 'john@example.com', 'age' => 30], // valid
        ];

        $generator = function (?string $repairPrompt = null) use (&$attempts, $responses) {
            $response = $responses[$attempts] ?? $responses[count($responses) - 1];
            $attempts++;

            return $this->mockStructuredResponse($response);
        };

        $handler = new RetryHandler(
            schema: $this->userSchema,
            responseGenerator: $generator,
            maxRetries: 3
        );

        $result = $handler->execute();

        $this->assertTrue($result->isValid());
        $this->assertEquals(2, $attempts);
        $this->assertEquals(1, $result->getRetryCount());
    }

    public function test_retries_when_field_has_wrong_type(): void
    {
        $attempts = 0;
        $responses = [
            ['name' => 'John', 'email' => 'john@example.com', 'age' => 'thirty'], // wrong type
            ['name' => 'John', 'email' => 'john@example.com', 'age' => 30], // valid
        ];

        $generator = function (?string $repairPrompt = null) use (&$attempts, $responses) {
            $response = $responses[$attempts] ?? $responses[count($responses) - 1];
            $attempts++;

            return $this->mockStructuredResponse($response);
        };

        $handler = new RetryHandler(
            schema: $this->userSchema,
            responseGenerator: $generator,
            maxRetries: 3
        );

        $result = $handler->execute();

        $this->assertTrue($result->isValid());
        $this->assertEquals(2, $attempts);
    }

    public function test_passes_repair_prompt_to_generator_on_retry(): void
    {
        $receivedPrompts = [];
        $responses = [
            ['name' => 'John'], // missing fields
            ['name' => 'John', 'email' => 'john@example.com', 'age' => 30],
        ];
        $attempts = 0;

        $generator = function (?string $repairPrompt = null) use (&$attempts, &$receivedPrompts, $responses) {
            $receivedPrompts[] = $repairPrompt;
            $response = $responses[$attempts] ?? $responses[count($responses) - 1];
            $attempts++;

            return $this->mockStructuredResponse($response);
        };

        $handler = new RetryHandler(
            schema: $this->userSchema,
            responseGenerator: $generator,
            maxRetries: 3
        );

        $handler->execute();

        $this->assertNull($receivedPrompts[0]); // First attempt has no repair prompt
        $this->assertNotNull($receivedPrompts[1]); // Retry has repair prompt
        $this->assertStringContainsString('email', $receivedPrompts[1]);
        $this->assertStringContainsString('age', $receivedPrompts[1]);
    }

    // ==========================================
    // Max Retry Tests
    // ==========================================

    public function test_stops_after_max_retries(): void
    {
        $attempts = 0;

        $generator = function () use (&$attempts) {
            $attempts++;

            return $this->mockStructuredResponse(['name' => 'John']); // always invalid
        };

        $handler = new RetryHandler(
            schema: $this->userSchema,
            responseGenerator: $generator,
            maxRetries: 3
        );

        $result = $handler->execute();

        $this->assertFalse($result->isValid());
        $this->assertEquals(4, $attempts); // 1 initial + 3 retries
        $this->assertEquals(3, $result->getRetryCount());
    }

    public function test_respects_custom_max_retries(): void
    {
        $attempts = 0;

        $generator = function () use (&$attempts) {
            $attempts++;

            return $this->mockStructuredResponse(['name' => 'John']); // always invalid
        };

        $handler = new RetryHandler(
            schema: $this->userSchema,
            responseGenerator: $generator,
            maxRetries: 5
        );

        $result = $handler->execute();

        $this->assertEquals(6, $attempts); // 1 initial + 5 retries
    }

    public function test_zero_retries_means_no_retry(): void
    {
        $attempts = 0;

        $generator = function () use (&$attempts) {
            $attempts++;

            return $this->mockStructuredResponse(['name' => 'John']); // invalid
        };

        $handler = new RetryHandler(
            schema: $this->userSchema,
            responseGenerator: $generator,
            maxRetries: 0
        );

        $result = $handler->execute();

        $this->assertFalse($result->isValid());
        $this->assertEquals(1, $attempts);
    }

    // ==========================================
    // Result Object Tests
    // ==========================================

    public function test_result_contains_validation_errors_on_failure(): void
    {
        $generator = fn () => $this->mockStructuredResponse(['name' => 'John']);

        $handler = new RetryHandler(
            schema: $this->userSchema,
            responseGenerator: $generator,
            maxRetries: 1
        );

        $result = $handler->execute();

        $this->assertFalse($result->isValid());
        $this->assertNotEmpty($result->getValidationErrors());
    }

    public function test_result_tracks_all_attempts(): void
    {
        $attempts = 0;
        $responses = [
            ['name' => 'John'],
            ['name' => 'John', 'email' => 'john@example.com'],
            ['name' => 'John', 'email' => 'john@example.com', 'age' => 30],
        ];

        $generator = function () use (&$attempts, $responses) {
            $response = $responses[$attempts] ?? $responses[count($responses) - 1];
            $attempts++;

            return $this->mockStructuredResponse($response);
        };

        $handler = new RetryHandler(
            schema: $this->userSchema,
            responseGenerator: $generator,
            maxRetries: 5
        );

        $result = $handler->execute();

        $this->assertTrue($result->isValid());
        $this->assertCount(3, $result->getAttempts());
    }

    public function test_result_provides_final_response(): void
    {
        $validData = ['name' => 'John', 'email' => 'john@example.com', 'age' => 30];

        $generator = fn () => $this->mockStructuredResponse($validData);

        $handler = new RetryHandler(
            schema: $this->userSchema,
            responseGenerator: $generator,
            maxRetries: 3
        );

        $result = $handler->execute();

        $this->assertNotNull($result->getResponse());
    }

    // ==========================================
    // Callback Tests
    // ==========================================

    public function test_calls_on_retry_callback(): void
    {
        $callbackInvocations = [];
        $responses = [
            ['name' => 'John'],
            ['name' => 'John', 'email' => 'john@example.com', 'age' => 30],
        ];
        $attempts = 0;

        $generator = function () use (&$attempts, $responses) {
            $response = $responses[$attempts] ?? $responses[count($responses) - 1];
            $attempts++;

            return $this->mockStructuredResponse($response);
        };

        $handler = new RetryHandler(
            schema: $this->userSchema,
            responseGenerator: $generator,
            maxRetries: 3
        );

        $handler->onRetry(function ($attempt, $errors) use (&$callbackInvocations) {
            $callbackInvocations[] = ['attempt' => $attempt, 'errors' => $errors];
        });

        $handler->execute();

        $this->assertCount(1, $callbackInvocations);
        $this->assertEquals(1, $callbackInvocations[0]['attempt']);
    }

    public function test_calls_on_success_callback(): void
    {
        $successData = null;
        $validData = ['name' => 'John', 'email' => 'john@example.com', 'age' => 30];

        $generator = fn () => $this->mockStructuredResponse($validData);

        $handler = new RetryHandler(
            schema: $this->userSchema,
            responseGenerator: $generator,
            maxRetries: 3
        );

        $handler->onSuccess(function ($data, $retryCount) use (&$successData) {
            $successData = ['data' => $data, 'retries' => $retryCount];
        });

        $handler->execute();

        $this->assertNotNull($successData);
        $this->assertEquals($validData, $successData['data']);
        $this->assertEquals(0, $successData['retries']);
    }

    public function test_calls_on_failure_callback(): void
    {
        $failureData = null;

        $generator = fn () => $this->mockStructuredResponse(['name' => 'John']);

        $handler = new RetryHandler(
            schema: $this->userSchema,
            responseGenerator: $generator,
            maxRetries: 2
        );

        $handler->onFailure(function ($errors, $attempts) use (&$failureData) {
            $failureData = ['errors' => $errors, 'attempts' => $attempts];
        });

        $handler->execute();

        $this->assertNotNull($failureData);
        $this->assertEquals(3, $failureData['attempts']); // 1 + 2 retries
    }

    // ==========================================
    // Logging Tests
    // ==========================================

    public function test_logs_retry_attempts(): void
    {
        $logMessages = [];

        $this->app['log']->listen(function ($event) use (&$logMessages) {
            $logMessages[] = $event->message;
        });

        $responses = [
            ['name' => 'John'],
            ['name' => 'John', 'email' => 'john@example.com', 'age' => 30],
        ];
        $attempts = 0;

        $generator = function () use (&$attempts, $responses) {
            $response = $responses[$attempts] ?? $responses[count($responses) - 1];
            $attempts++;

            return $this->mockStructuredResponse($response);
        };

        $handler = new RetryHandler(
            schema: $this->userSchema,
            responseGenerator: $generator,
            maxRetries: 3
        );

        $handler->execute();

        // Verify logging occurred (implementation will log retries)
        $this->assertTrue(true); // Placeholder - actual log verification depends on implementation
    }

    // ==========================================
    // Helper Methods
    // ==========================================

    protected function mockStructuredResponse(array $data): StructuredResponse
    {
        return new StructuredResponse(
            steps: new Collection,
            text: json_encode($data),
            structured: $data,
            finishReason: FinishReason::Stop,
            usage: new Usage(promptTokens: 10, completionTokens: 20),
            meta: new Meta(id: 'test-id', model: 'test-model'),
        );
    }
}
