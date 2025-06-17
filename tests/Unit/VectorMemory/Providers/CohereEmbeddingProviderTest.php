<?php

namespace Vizra\VizraADK\Tests\Unit\VectorMemory\Providers;

use Vizra\VizraADK\Tests\TestCase;
use Vizra\VizraADK\Providers\CohereEmbeddingProvider;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Config;

class CohereEmbeddingProviderTest extends TestCase
{
    protected CohereEmbeddingProvider $provider;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('services.cohere.key', 'test-cohere-key');
        Config::set('vizra-adk.vector_memory.embedding_models.cohere', 'embed-english-v3.0');

        $this->provider = new CohereEmbeddingProvider();
    }

    public function test_can_embed_single_text()
    {
        // Arrange
        $text = 'Test embedding text';
        $mockEmbedding = array_fill(0, 1024, 0.1);

        Http::fake([
            'api.cohere.ai/v1/embed' => Http::response([
                'embeddings' => [
                    'float' => [$mockEmbedding]
                ],
                'meta' => [
                    'billed_units' => ['input_tokens' => 4]
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
            return $request->url() === 'https://api.cohere.ai/v1/embed' &&
                   $request['model'] === 'embed-english-v3.0' &&
                   $request['texts'] === [$text] &&
                   $request['input_type'] === 'search_document' &&
                   $request['embedding_types'] === ['float'] &&
                   $request->hasHeader('Authorization', 'Bearer test-cohere-key');
        });
    }

    public function test_can_embed_multiple_texts()
    {
        // Arrange
        $texts = ['First text', 'Second text'];
        $mockEmbeddings = [
            array_fill(0, 1024, 0.1),
            array_fill(0, 1024, 0.2)
        ];

        Http::fake([
            'api.cohere.ai/v1/embed' => Http::response([
                'embeddings' => [
                    'float' => $mockEmbeddings
                ],
                'meta' => [
                    'billed_units' => ['input_tokens' => 6]
                ]
            ], 200)
        ]);

        // Act
        $result = $this->provider->embed($texts);

        // Assert
        $this->assertIsArray($result);
        $this->assertCount(2, $result);
        $this->assertEquals($mockEmbeddings, $result);
    }

    public function test_throws_exception_for_missing_api_key()
    {
        // Arrange
        Config::set('services.cohere.key', null);

        // Act & Assert
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Cohere API key is required');

        new CohereEmbeddingProvider();
    }

    public function test_get_dimensions_returns_correct_value()
    {
        // Act & Assert
        $this->assertEquals(1024, $this->provider->getDimensions());
    }

    public function test_get_provider_name_returns_cohere()
    {
        // Act & Assert
        $this->assertEquals('cohere', $this->provider->getProviderName());
    }

    public function test_get_max_input_length_returns_expected_value()
    {
        // Act & Assert
        $this->assertEquals(2000, $this->provider->getMaxInputLength());
    }

    public function test_estimate_cost_returns_fixed_rate()
    {
        // Arrange
        $text = str_repeat('word ', 100);

        // Act
        $cost = $this->provider->estimateCost($text);

        // Assert
        $this->assertIsFloat($cost);
        $this->assertGreaterThan(0, $cost);
    }
}
