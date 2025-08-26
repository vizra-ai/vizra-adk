<?php

namespace Vizra\VizraADK\Examples;

use Vizra\VizraADK\Agents\BaseLlmAgent;

/**
 * Example agent using OpenRouter as the model provider.
 * 
 * OpenRouter provides access to 100+ models through a unified API,
 * including models from OpenAI, Anthropic, Google, Meta, and more.
 * 
 * To use this agent:
 * 1. Get an API key from https://openrouter.ai/settings
 * 2. Add to your .env file: OPENROUTER_API_KEY=your-key-here
 * 3. Run: php artisan vizra:chat openrouter_example
 */
class OpenRouterAgent extends BaseLlmAgent
{
    protected string $name = 'openrouter_example';
    
    protected string $description = 'Example agent demonstrating OpenRouter integration';
    
    protected string $instructions = 'You are a helpful assistant powered by OpenRouter. 
        You can access multiple AI models through a single API endpoint.';
    
    // Explicitly set the provider to OpenRouter
    protected ?string $provider = 'openrouter';
    
    /**
     * OpenRouter model format: "provider/model"
     * 
     * Popular options:
     * - "openai/gpt-4" - OpenAI GPT-4
     * - "openai/gpt-4-turbo" - GPT-4 Turbo
     * - "openai/gpt-3.5-turbo" - GPT-3.5 Turbo
     * - "anthropic/claude-3-opus" - Claude 3 Opus
     * - "anthropic/claude-3-sonnet" - Claude 3 Sonnet
     * - "google/gemini-pro" - Google Gemini Pro
     * - "meta-llama/llama-3-70b-instruct" - Llama 3 70B
     * - "mistralai/mixtral-8x7b-instruct" - Mixtral 8x7B
     * 
     * See full list at: https://openrouter.ai/models
     */
    protected string $model = 'openai/gpt-4-turbo';
    
    // Optional: Configure generation parameters
    protected ?float $temperature = 0.7;
    protected ?int $maxTokens = 1000;
}