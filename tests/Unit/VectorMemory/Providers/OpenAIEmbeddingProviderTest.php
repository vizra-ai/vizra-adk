<?php

namespace Vizra\VizraADK\Tests\Unit\VectorMemory\Providers;

use Vizra\VizraADK\Tests\TestCase;
use Vizra\VizraADK\Providers\OpenAIEmbeddingProvider;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Config;

class OpenAIEmbeddingProviderTest extends TestCase
{
    protected OpenAIEmbeddingProvider $provider;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('services.openai.key', 'test-api-key');
        Config::set('vizra-adk.vector_memory.embedding_models.openai', 'text-embedding-3-small');

        $this->provider = new OpenAIEmbeddingProvider();
    }

    public function test_can_embed_single_text()
    {
        // Arrange
        $text = 'Test embedding text';
        $mockEmbedding = array_fill(0, 1536, 0.1);

        Http::fake([
            'api.openai.com/v1/embeddings' => Http::response([
                'data' => [
                    ['embedding' => $mockEmbedding]
                ],
                'usage' => [
                    'prompt_tokens' => 4,
                    'total_tokens' => 4
                ]
            ], 200)
        ]);

        // Act
        $result = $this->provider->embed($text);

        // Assert
        $this->assertIsArray($result);
        $this->assertCount(1, $result);
        $this->assertEquals($mockEmbedding, $result[0]);

        Http::assertSent(function ($request) use ($text) {
            return $request->url() === 'https://api.openai.com/v1/embeddings' &&
                   $request['model'] === 'text-embedding-3-small' &&
                   $request['input'] === [$text] &&
                   $request['encoding_format'] === 'float' &&
                   $request->hasHeader('Authorization', 'Bearer test-api-key');
        });
    }

    public function test_can_embed_multiple_texts()
    {
        // Arrange
        $texts = ['First text', 'Second text'];
        $mockEmbeddings = [
            array_fill(0, 1536, 0.1),
            array_fill(0, 1536, 0.2)
        ];

        Http::fake([
            'api.openai.com/v1/embeddings' => Http::response([
                'data' => [
                    ['embedding' => $mockEmbeddings[0]],
                    ['embedding' => $mockEmbeddings[1]]
                ],
                'usage' => [
                    'prompt_tokens' => 6,
                    'total_tokens' => 6
                ]
            ], 200)
        ]);

        // Act
        $result = $this->provider->embed($texts);

        // Assert
        $this->assertIsArray($result);
        $this->assertCount(2, $result);
        $this->assertEquals($mockEmbeddings[0], $result[0]);
        $this->assertEquals($mockEmbeddings[1], $result[1]);

        Http::assertSent(function ($request) use ($texts) {
            return $request['input'] === $texts;
        });
    }

    public function test_throws_exception_for_missing_api_key()
    {
        // Arrange
        Config::set('services.openai.key', null);

        // Act & Assert
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('OpenAI API key is required');

        new OpenAIEmbeddingProvider();
    }

    public function test_throws_exception_for_input_too_long()
    {
        // Arrange
        $longText = str_repeat('a', 35000); // Longer than max input length

        // Act & Assert
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Input text exceeds maximum length');

        $this->provider->embed($longText);
    }

    public function test_throws_exception_for_api_error()
    {
        // Arrange
        Http::fake([
            'api.openai.com/v1/embeddings' => Http::response([
                'error' => [
                    'message' => 'Invalid API key',
                    'type' => 'invalid_request_error'
                ]
            ], 401)
        ]);

        // Act & Assert
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('OpenAI embedding API request failed');

        $this->provider->embed('Test text');
    }

    public function test_throws_exception_for_invalid_response_format()
    {
        // Arrange
        Http::fake([
            'api.openai.com/v1/embeddings' => Http::response([
                'invalid' => 'response'
            ], 200)
        ]);

        // Act & Assert
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Invalid response format from OpenAI embedding API');

        $this->provider->embed('Test text');
    }

    public function test_throws_exception_for_missing_embedding_in_response()
    {
        // Arrange
        Http::fake([
            'api.openai.com/v1/embeddings' => Http::response([
                'data' => [
                    ['no_embedding' => 'field']
                ]
            ], 200)
        ]);

        // Act & Assert
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Missing embedding in API response');

        $this->provider->embed('Test text');
    }

    public function test_get_dimensions_returns_correct_value()
    {
        // Act & Assert
        $this->assertEquals(1536, $this->provider->getDimensions());
    }

    public function test_get_model_returns_configured_model()
    {
        // Act & Assert
        $this->assertEquals('text-embedding-3-small', $this->provider->getModel());
    }

    public function test_get_provider_name_returns_openai()
    {
        // Act & Assert
        $this->assertEquals('openai', $this->provider->getProviderName());
    }

    public function test_get_max_input_length_returns_expected_value()
    {
        // Act & Assert
        $this->assertEquals(30000, $this->provider->getMaxInputLength());
    }

    public function test_estimate_cost_for_small_model()
    {
        // Arrange
        $text = str_repeat('word ', 250); // ~1000 characters, ~250 tokens

        // Act
        $cost = $this->provider->estimateCost($text);

        // Assert
        $this->assertIsFloat($cost);
        $this->assertGreaterThan(0, $cost);
        $this->assertLessThan(0.01, $cost); // Should be very small cost
    }

    public function test_estimate_cost_for_multiple_texts()
    {
        // Arrange
        $texts = [
            str_repeat('word ', 100),
            str_repeat('word ', 150)
        ];

        // Act
        $cost = $this->provider->estimateCost($texts);

        // Assert
        $this->assertIsFloat($cost);
        $this->assertGreaterThan(0, $cost);
    }

    public function test_estimate_cost_for_large_model()
    {
        // Arrange
        Config::set('vizra-adk.vector_memory.embedding_models.openai', 'text-embedding-3-large');
        $provider = new OpenAIEmbeddingProvider();
        $text = str_repeat('word ', 250);

        // Act
        $cost = $provider->estimateCost($text);

        // Assert
        $this->assertIsFloat($cost);
        $this->assertGreaterThan(0, $cost);
        // Large model should cost more than small model
        $smallModelCost = $this->provider->estimateCost($text);
        $this->assertGreaterThan($smallModelCost, $cost);
    }

    public function test_dimensions_configuration_for_different_models()
    {
        // Test text-embedding-3-large
        Config::set('vizra-adk.vector_memory.embedding_models.openai', 'text-embedding-3-large');
        $provider = new OpenAIEmbeddingProvider();
        $this->assertEquals(3072, $provider->getDimensions());

        // Test text-embedding-ada-002
        Config::set('vizra-adk.vector_memory.embedding_models.openai', 'text-embedding-ada-002');
        $provider = new OpenAIEmbeddingProvider();
        $this->assertEquals(1536, $provider->getDimensions());
    }

    public function test_handles_network_timeout()
    {
        // Arrange
        Http::fake(function () {
            throw new \Illuminate\Http\Client\ConnectionException('Connection timeout');
        });

        // Act & Assert
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Failed to generate OpenAI embeddings');

        $this->provider->embed('Test text');
    }

    public function test_uses_custom_base_url_when_configured()
    {
        // Arrange
        Config::set('services.openai.url', 'https://custom.openai.com/v1');
        $provider = new OpenAIEmbeddingProvider();

        Http::fake([
            'custom.openai.com/v1/embeddings' => Http::response([
                'data' => [['embedding' => array_fill(0, 1536, 0.1)]]
            ], 200)
        ]);

        // Act
        $provider->embed('Test text');

        // Assert
        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'custom.openai.com');
        });
    }
}
