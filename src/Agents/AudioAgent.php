<?php

namespace Vizra\VizraADK\Agents;

use Prism\Prism\Facades\Prism;
use Vizra\VizraADK\Media\Responses\AudioResponse;
use Vizra\VizraADK\System\AgentContext;
use Vizra\VizraADK\Traits\HasLogging;

/**
 * Agent for generating audio (text-to-speech) using AI models.
 *
 * Supports OpenAI TTS, and other text-to-speech models through Prism PHP.
 *
 * Usage:
 * ```php
 * // Simple TTS
 * $audio = AudioAgent::run('Welcome to our application')
 *     ->voice('nova')
 *     ->go();
 * $audio->storeAs('welcome.mp3');
 *
 * // Queued generation
 * AudioAgent::run('Your order has been confirmed')
 *     ->shimmer()
 *     ->format('mp3')
 *     ->onQueue('media')
 *     ->then(fn($audio) => $audio->storeAs('confirmation.mp3'))
 *     ->go();
 *
 * // With all options
 * AudioAgent::run('Hello world')
 *     ->using('openai', 'gpt-4o-mini-tts')
 *     ->voice('alloy')
 *     ->format('wav')
 *     ->speed(1.0)
 *     ->storeAs('hello.wav')
 *     ->go();
 * ```
 */
class AudioAgent extends BaseMediaAgent
{
    use HasLogging;

    protected string $name = 'audio_agent';

    protected string $description = 'Converts text to speech audio using AI voices';

    protected string $provider;

    protected string $model;

    public function __construct()
    {
        $this->provider = config('vizra-adk.media.audio.provider', 'openai');
        $this->model = config('vizra-adk.media.audio.model', 'gpt-4o-mini-tts');
    }

    /**
     * Execute audio generation (TTS)
     */
    public function execute(mixed $input, AgentContext $context): AudioResponse
    {
        $options = $context->getState('media_options', []);

        $voice = $options['voice'] ?? config('vizra-adk.media.audio.default_voice', 'alloy');
        $format = $options['format'] ?? config('vizra-adk.media.audio.default_format', 'mp3');

        $this->logInfo('Starting audio generation', [
            'text_length' => strlen($input),
            'provider' => $this->provider,
            'model' => $this->model,
            'voice' => $voice,
            'format' => $format,
        ], 'agents');

        // Build the Prism request
        $prismResponse = Prism::audio()
            ->using($this->provider, $this->model)
            ->withInput($input)
            ->withVoice($voice)
            ->asAudio();

        // Track in context
        $generated = $context->getState('generated_audio', []);
        $generated[] = [
            'text_length' => strlen($input),
            'voice' => $voice,
            'format' => $format,
            'provider' => $this->provider,
            'model' => $this->model,
            'generated_at' => now()->toISOString(),
        ];
        $context->setState('generated_audio', $generated);

        $this->logInfo('Audio generated successfully', [
            'text_length' => strlen($input),
            'voice' => $voice,
        ], 'agents');

        return new AudioResponse(
            response: $prismResponse,
            text: $input,
            voice: $voice,
            format: $format,
            provider: $this->provider,
            model: $this->model
        );
    }

    /**
     * Get tool definition for when used as a sub-agent
     */
    public function toToolDefinition(): array
    {
        return [
            'name' => 'generate_audio',
            'description' => $this->description,
            'parameters' => [
                'type' => 'object',
                'properties' => [
                    'text' => [
                        'type' => 'string',
                        'description' => 'The text to convert to speech',
                    ],
                    'voice' => [
                        'type' => 'string',
                        'enum' => ['alloy', 'echo', 'fable', 'onyx', 'nova', 'shimmer'],
                        'description' => 'Voice to use for speech synthesis (default: alloy)',
                    ],
                    'format' => [
                        'type' => 'string',
                        'enum' => ['mp3', 'wav', 'opus', 'aac', 'flac'],
                        'description' => 'Audio output format (default: mp3)',
                    ],
                ],
                'required' => ['text'],
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
        if (isset($arguments['voice'])) {
            $options['voice'] = $arguments['voice'];
        }
        if (isset($arguments['format'])) {
            $options['format'] = $arguments['format'];
        }

        $context->setState('media_options', $options);

        try {
            $audio = $this->execute($arguments['text'], $context);

            // Auto-store when called as tool
            $audio->store();

            return json_encode([
                'success' => true,
                'url' => $audio->url(),
                'path' => $audio->path(),
                'voice' => $audio->voice(),
                'format' => $audio->format(),
                'message' => 'Audio generated and stored successfully',
            ]);
        } catch (\Exception $e) {
            $this->logError('Audio generation failed', [
                'error' => $e->getMessage(),
                'text_length' => strlen($arguments['text'] ?? ''),
            ], 'agents');

            return json_encode([
                'success' => false,
                'error' => 'Audio generation failed: ' . $e->getMessage(),
            ]);
        }
    }
}
