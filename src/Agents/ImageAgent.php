<?php

namespace Vizra\VizraADK\Agents;

use Prism\Prism\Facades\Prism;
use Vizra\VizraADK\Media\Responses\ImageResponse;
use Vizra\VizraADK\System\AgentContext;
use Vizra\VizraADK\Traits\HasLogging;

/**
 * Agent for generating images using AI models.
 *
 * Supports DALL-E 2, DALL-E 3, GPT-Image-1, Gemini Imagen, and other
 * image generation models through Prism PHP.
 *
 * Usage:
 * ```php
 * // Simple generation
 * $image = ImageAgent::run('A sunset over the ocean')
 *     ->quality('hd')
 *     ->go();
 * $image->storeAs('sunset.png');
 *
 * // Queued generation
 * ImageAgent::run('A mountain landscape')
 *     ->size('1792x1024')
 *     ->onQueue('media')
 *     ->then(fn($image) => $image->storeAs('landscape.png'))
 *     ->go();
 *
 * // With all options
 * ImageAgent::run('An oil painting of flowers')
 *     ->using('openai', 'dall-e-3')
 *     ->landscape()
 *     ->hd()
 *     ->style('natural')
 *     ->storeAs('flowers.png')
 *     ->go();
 * ```
 */
class ImageAgent extends BaseMediaAgent
{
    use HasLogging;

    protected string $name = 'image_agent';

    protected string $description = 'Generates images from text descriptions using AI models like DALL-E, Imagen, and others';

    protected string $provider;

    protected string $model;

    public function __construct()
    {
        $this->provider = config('vizra-adk.media.image.provider', 'openai');
        $this->model = config('vizra-adk.media.image.model', 'dall-e-3');
    }

    /**
     * Execute image generation
     */
    public function execute(mixed $input, AgentContext $context): ImageResponse
    {
        $options = $context->getState('media_options', []);

        $this->logInfo('Starting image generation', [
            'prompt' => $input,
            'provider' => $this->provider,
            'model' => $this->model,
            'options' => $options,
        ], 'agents');

        // Build the Prism request
        $request = Prism::image()
            ->using($this->provider, $this->model)
            ->withPrompt($input);

        // Apply provider options
        $providerOptions = $this->buildProviderOptions($options);
        if (!empty($providerOptions)) {
            $request->withProviderOptions($providerOptions);
        }

        // Execute generation
        $prismResponse = $request->generate();

        // Track in context
        $generated = $context->getState('generated_images', []);
        $generated[] = [
            'prompt' => $input,
            'provider' => $this->provider,
            'model' => $this->model,
            'generated_at' => now()->toISOString(),
        ];
        $context->setState('generated_images', $generated);

        $this->logInfo('Image generated successfully', [
            'prompt' => $input,
            'provider' => $this->provider,
            'model' => $this->model,
        ], 'agents');

        return new ImageResponse(
            response: $prismResponse,
            prompt: $input,
            provider: $this->provider,
            model: $this->model
        );
    }

    /**
     * Build provider-specific options array
     */
    protected function buildProviderOptions(array $options): array
    {
        $providerOptions = [];

        // Size/dimensions
        $size = $options['size'] ?? config('vizra-adk.media.image.default_size', '1024x1024');
        if ($size) {
            $providerOptions['size'] = $size;
        }

        // Quality (OpenAI specific)
        $quality = $options['quality'] ?? config('vizra-adk.media.image.default_quality');
        if ($quality) {
            $providerOptions['quality'] = $quality;
        }

        // Style (OpenAI DALL-E 3 specific)
        $style = $options['style'] ?? config('vizra-adk.media.image.default_style');
        if ($style) {
            $providerOptions['style'] = $style;
        }

        // Response format
        $responseFormat = $options['response_format'] ?? config('vizra-adk.media.image.response_format');
        if ($responseFormat) {
            $providerOptions['response_format'] = $responseFormat;
        }

        // Number of images (for providers that support it)
        if (isset($options['n'])) {
            $providerOptions['n'] = $options['n'];
        }

        return array_filter($providerOptions, fn($v) => $v !== null);
    }

    /**
     * Get tool definition for when used as a sub-agent
     */
    public function toToolDefinition(): array
    {
        return [
            'name' => 'generate_image',
            'description' => $this->description,
            'parameters' => [
                'type' => 'object',
                'properties' => [
                    'prompt' => [
                        'type' => 'string',
                        'description' => 'Detailed description of the image to generate',
                    ],
                    'size' => [
                        'type' => 'string',
                        'enum' => ['1024x1024', '1024x1792', '1792x1024', '512x512', '256x256'],
                        'description' => 'Image dimensions (default: 1024x1024)',
                    ],
                    'quality' => [
                        'type' => 'string',
                        'enum' => ['standard', 'hd'],
                        'description' => 'Image quality level (default: standard)',
                    ],
                    'style' => [
                        'type' => 'string',
                        'enum' => ['vivid', 'natural'],
                        'description' => 'Visual style - vivid for dramatic, natural for realistic',
                    ],
                ],
                'required' => ['prompt'],
            ],
        ];
    }

    /**
     * Execute from a tool call (sub-agent delegation)
     */
    public function executeFromToolCall(array $arguments, AgentContext $context): string
    {
        // Set options from arguments
        $options = [];
        if (isset($arguments['size'])) {
            $options['size'] = $arguments['size'];
        }
        if (isset($arguments['quality'])) {
            $options['quality'] = $arguments['quality'];
        }
        if (isset($arguments['style'])) {
            $options['style'] = $arguments['style'];
        }

        $context->setState('media_options', $options);

        try {
            $image = $this->execute($arguments['prompt'], $context);

            // Auto-store when called as tool
            $image->store();

            return json_encode([
                'success' => true,
                'url' => $image->url(),
                'path' => $image->path(),
                'prompt' => $arguments['prompt'],
                'message' => 'Image generated and stored successfully',
            ]);
        } catch (\Exception $e) {
            $this->logError('Image generation failed', [
                'error' => $e->getMessage(),
                'prompt' => $arguments['prompt'],
            ], 'agents');

            return json_encode([
                'success' => false,
                'error' => 'Image generation failed: ' . $e->getMessage(),
            ]);
        }
    }
}
