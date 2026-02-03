<?php

namespace Vizra\VizraADK\Media\Responses;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Response wrapper for generated images.
 *
 * Provides fluent access to generated image data with
 * built-in storage capabilities.
 */
class ImageResponse
{
    protected mixed $response;

    protected string $prompt;

    protected string $provider;

    protected string $model;

    protected ?string $storedPath = null;

    protected ?string $storedUrl = null;

    protected ?string $storedDisk = null;

    public function __construct(
        mixed $response,
        string $prompt,
        string $provider,
        string $model
    ) {
        $this->response = $response;
        $this->prompt = $prompt;
        $this->provider = $provider;
        $this->model = $model;
    }

    /**
     * Store the image with a specific filename
     */
    public function storeAs(string $filename, ?string $disk = null): static
    {
        $disk = $disk ?? config('vizra-adk.media.storage.disk', 'public');
        $basePath = config('vizra-adk.media.storage.path', 'vizra-adk/generated');

        // Ensure filename has extension
        if (!pathinfo($filename, PATHINFO_EXTENSION)) {
            $filename .= '.' . $this->guessExtension();
        }

        $fullPath = rtrim($basePath, '/') . '/images/' . ltrim($filename, '/');

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
        $filename = Str::ulid() . '.' . $this->guessExtension();
        return $this->storeAs($filename, $disk);
    }

    /**
     * Get the image URL (from storage or provider)
     */
    public function url(): ?string
    {
        // Prefer stored URL if available
        if ($this->storedUrl !== null) {
            return $this->storedUrl;
        }

        // Fall back to provider URL
        return $this->providerUrl();
    }

    /**
     * Get the original provider URL (before storage)
     */
    public function providerUrl(): ?string
    {
        if (is_object($this->response)) {
            if (method_exists($this->response, 'url')) {
                return $this->response->url();
            }
            if (property_exists($this->response, 'url')) {
                return $this->response->url;
            }
        }

        return $this->response['url'] ?? null;
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
     * Get base64 encoded image data
     */
    public function base64(): string
    {
        if (is_object($this->response)) {
            if (method_exists($this->response, 'base64')) {
                return $this->response->base64();
            }
            if (property_exists($this->response, 'base64') && $this->response->base64) {
                return $this->response->base64;
            }
        }

        if (isset($this->response['base64'])) {
            return $this->response['base64'];
        }

        // Convert from raw data or URL
        return base64_encode($this->data());
    }

    /**
     * Get raw image binary data
     */
    public function data(): string
    {
        // Try base64 first
        if (is_object($this->response)) {
            if (method_exists($this->response, 'base64')) {
                $b64 = $this->response->base64();
                if ($b64) {
                    return base64_decode($b64);
                }
            }
            if (property_exists($this->response, 'base64') && $this->response->base64) {
                return base64_decode($this->response->base64);
            }
        }

        if (isset($this->response['base64'])) {
            return base64_decode($this->response['base64']);
        }

        // Fetch from URL
        $url = $this->providerUrl();
        if ($url) {
            return file_get_contents($url);
        }

        throw new \RuntimeException('No image data available');
    }

    /**
     * Get as data URI for embedding in HTML/CSS
     */
    public function toDataUri(): string
    {
        $mime = $this->mimeType();
        return "data:{$mime};base64," . $this->base64();
    }

    /**
     * Get the prompt used for generation
     */
    public function prompt(): string
    {
        return $this->prompt;
    }

    /**
     * Get generation metadata
     */
    public function metadata(): array
    {
        return [
            'prompt' => $this->prompt,
            'provider' => $this->provider,
            'model' => $this->model,
            'url' => $this->url(),
            'provider_url' => $this->providerUrl(),
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
     * Check if image has been stored
     */
    public function isStored(): bool
    {
        return $this->storedPath !== null;
    }

    /**
     * Guess the file extension
     */
    protected function guessExtension(): string
    {
        // Most AI image generation returns PNG
        return 'png';
    }

    /**
     * Get the MIME type
     */
    public function mimeType(): string
    {
        return 'image/png';
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
