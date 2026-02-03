<?php

namespace Vizra\VizraADK\Media\Responses;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Response wrapper for generated audio.
 *
 * Provides fluent access to generated audio data with
 * built-in storage capabilities.
 */
class AudioResponse
{
    protected mixed $response;

    protected string $text;

    protected string $voice;

    protected string $format;

    protected string $provider;

    protected string $model;

    protected ?string $storedPath = null;

    protected ?string $storedUrl = null;

    protected ?string $storedDisk = null;

    public function __construct(
        mixed $response,
        string $text,
        string $voice,
        string $format,
        string $provider,
        string $model
    ) {
        $this->response = $response;
        $this->text = $text;
        $this->voice = $voice;
        $this->format = $format;
        $this->provider = $provider;
        $this->model = $model;
    }

    /**
     * Store the audio with a specific filename
     */
    public function storeAs(string $filename, ?string $disk = null): static
    {
        $disk = $disk ?? config('vizra-adk.media.storage.disk', 'public');
        $basePath = config('vizra-adk.media.storage.path', 'vizra-adk/generated');

        // Ensure filename has extension
        if (!pathinfo($filename, PATHINFO_EXTENSION)) {
            $filename .= '.' . $this->format;
        }

        $fullPath = rtrim($basePath, '/') . '/audio/' . ltrim($filename, '/');

        Storage::disk($disk)->put($fullPath, $this->data());

        $this->storedPath = $fullPath;
        $this->storedUrl = Storage::disk($disk)->url($fullPath);
        $this->storedDisk = $disk;

        return $this;
    }

    /**
     * Store with auto-generated filename
     */
    public function store(?string $disk = null): static
    {
        $filename = Str::ulid() . '.' . $this->format;
        return $this->storeAs($filename, $disk);
    }

    /**
     * Get the audio URL
     */
    public function url(): ?string
    {
        return $this->storedUrl;
    }

    /**
     * Get the stored path
     */
    public function path(): ?string
    {
        return $this->storedPath;
    }

    /**
     * Get the storage disk used
     */
    public function disk(): ?string
    {
        return $this->storedDisk;
    }

    /**
     * Get base64 encoded audio data
     */
    public function base64(): string
    {
        return base64_encode($this->data());
    }

    /**
     * Get raw audio binary data
     */
    public function data(): string
    {
        if (is_object($this->response)) {
            if (method_exists($this->response, 'audio')) {
                return $this->response->audio();
            }
            if (property_exists($this->response, 'audio')) {
                return $this->response->audio;
            }
        }

        if (isset($this->response['audio'])) {
            return $this->response['audio'];
        }

        throw new \RuntimeException('No audio data available');
    }

    /**
     * Get as data URI for embedding
     */
    public function toDataUri(): string
    {
        $mime = $this->mimeType();
        return "data:{$mime};base64," . $this->base64();
    }

    /**
     * Get the original text
     */
    public function text(): string
    {
        return $this->text;
    }

    /**
     * Get the voice used
     */
    public function voice(): string
    {
        return $this->voice;
    }

    /**
     * Get the audio format
     */
    public function format(): string
    {
        return $this->format;
    }

    /**
     * Get generation metadata
     */
    public function metadata(): array
    {
        return [
            'text' => $this->text,
            'voice' => $this->voice,
            'format' => $this->format,
            'provider' => $this->provider,
            'model' => $this->model,
            'url' => $this->url(),
            'path' => $this->path(),
            'disk' => $this->disk(),
            'generated_at' => now()->toISOString(),
        ];
    }

    /**
     * Get the raw response object
     */
    public function raw(): mixed
    {
        return $this->response;
    }

    /**
     * Check if audio has been stored
     */
    public function isStored(): bool
    {
        return $this->storedPath !== null;
    }

    /**
     * Get the MIME type
     */
    public function mimeType(): string
    {
        return match ($this->format) {
            'mp3' => 'audio/mpeg',
            'wav' => 'audio/wav',
            'opus' => 'audio/opus',
            'aac' => 'audio/aac',
            'flac' => 'audio/flac',
            'ogg' => 'audio/ogg',
            default => 'audio/mpeg',
        };
    }

    /**
     * Convert to array
     */
    public function toArray(): array
    {
        return $this->metadata();
    }

    /**
     * Convert to JSON
     */
    public function toJson(): string
    {
        return json_encode($this->toArray());
    }

    /**
     * String representation returns URL
     */
    public function __toString(): string
    {
        return $this->url() ?? '';
    }
}
