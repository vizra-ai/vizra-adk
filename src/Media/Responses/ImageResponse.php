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

    protected mixed $image;

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

        // Extract the first image from Prism response if applicable
        $this->image = $this->extractImage($response);
    }

    /**
     * Extract the image object from the response
     */
    protected function extractImage(mixed $response): mixed
    {
        // Handle Prism\Prism\Images\Response which has firstImage() method
        if (is_object($response) && method_exists($response, 'firstImage')) {
            $image = $response->firstImage();
            if ($image === null) {
                throw new \RuntimeException('No images were generated in the response');
            }
            return $image;
        }

        // If response itself is the image object
        return $response;
    }

    /**
     * Check if the image has data available
     */
    public function hasImage(): bool
    {
        return $this->image !== null;
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
        $providerUrl = $this->providerUrl();
        if ($providerUrl !== null) {
            return $providerUrl;
        }

        // Final fallback: return as data URI for inline display
        try {
            return $this->toDataUri();
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Get the original provider URL (before storage)
     */
    public function providerUrl(): ?string
    {
        if (is_object($this->image)) {
            if (method_exists($this->image, 'url')) {
                return $this->image->url();
            }
            if (property_exists($this->image, 'url')) {
                return $this->image->url;
            }
        }

        if (is_array($this->image)) {
            return $this->image['url'] ?? null;
        }

        return null;
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
        if (is_object($this->image)) {
            if (method_exists($this->image, 'base64')) {
                $b64 = $this->image->base64();
                if ($b64) {
                    return $b64;
                }
            }
            if (property_exists($this->image, 'base64') && $this->image->base64) {
                return $this->image->base64;
            }
        }

        if (is_array($this->image) && isset($this->image['base64'])) {
            return $this->image['base64'];
        }

        // Convert from raw data or URL
        return base64_encode($this->data());
    }

    /**
     * Get raw image binary data
     */
    public function data(): string
    {
        // Try rawContent method first (Prism GeneratedImage)
        if (is_object($this->image)) {
            if (method_exists($this->image, 'rawContent')) {
                $content = $this->image->rawContent();
                if ($content) {
                    return $content;
                }
            }

            // Try base64
            if (method_exists($this->image, 'base64')) {
                $b64 = $this->image->base64();
                if ($b64) {
                    return base64_decode($b64);
                }
            }
            if (property_exists($this->image, 'base64') && $this->image->base64) {
                return base64_decode($this->image->base64);
            }
        }

        if (is_array($this->image) && isset($this->image['base64'])) {
            return base64_decode($this->image['base64']);
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
     * Guess the file extension based on MIME type
     */
    protected function guessExtension(): string
    {
        $mime = $this->mimeType();

        return match ($mime) {
            'image/jpeg', 'image/jpg' => 'jpg',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
            default => 'png',
        };
    }

    /**
     * Get the MIME type
     */
    public function mimeType(): string
    {
        if (is_object($this->image) && method_exists($this->image, 'mimeType')) {
            $mime = $this->image->mimeType();
            if ($mime) {
                return $mime;
            }
        }

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
