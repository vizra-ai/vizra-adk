<?php

namespace Vizra\VizraSdk\Contracts;

interface EmbeddingProviderInterface
{
    /**
     * Generate embeddings for the given text or texts.
     *
     * @param string|array $input Single text string or array of texts
     * @return array Array of embedding vectors
     */
    public function embed(string|array $input): array;

    /**
     * Get the dimensions of embeddings produced by this provider.
     *
     * @return int
     */
    public function getDimensions(): int;

    /**
     * Get the model name used by this provider.
     *
     * @return string
     */
    public function getModel(): string;

    /**
     * Get the provider name.
     *
     * @return string
     */
    public function getProviderName(): string;

    /**
     * Get the maximum input length supported by this provider.
     *
     * @return int
     */
    public function getMaxInputLength(): int;

    /**
     * Estimate the cost for embedding the given input.
     *
     * @param string|array $input
     * @return float Cost in USD
     */
    public function estimateCost(string|array $input): float;
}