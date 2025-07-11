<?php

namespace Tests\Unit\Traits;

use Illuminate\Support\Facades\File;
use Vizra\VizraADK\Agents\BaseLlmAgent;

it('can set and get prompt version', function () {
    $agent = new class extends BaseLlmAgent
    {
        protected string $name = 'test_agent';

        protected string $instructions = 'Default instructions';
    };

    $agent->setPromptVersion('v2');
    expect($agent->getPromptVersion())->toBe('v2');
});

it('can override prompt at runtime', function () {
    $agent = new class extends BaseLlmAgent
    {
        protected string $name = 'test_agent';

        protected string $instructions = 'Default instructions';
    };

    $agent->setPromptOverride('Override instructions');
    expect($agent->getInstructions())->toBe('Override instructions');
});

it('falls back to class property when no version specified', function () {
    $agent = new class extends BaseLlmAgent
    {
        protected string $name = 'test_agent';

        protected string $instructions = 'Default instructions';
    };

    expect($agent->getInstructions())->toBe('Default instructions');
});

it('loads prompt from file when version is set', function () {
    // Create test prompt file
    $promptPath = resource_path('prompts/test_agent');
    File::makeDirectory($promptPath, 0755, true, true);
    File::put($promptPath.'/test_version.md', 'Test version instructions');

    $agent = new class extends BaseLlmAgent
    {
        protected string $name = 'test_agent';

        protected string $instructions = 'Default instructions';
    };

    $agent->setPromptVersion('test_version');
    expect($agent->getInstructions())->toBe('Test version instructions');

    // Cleanup
    File::deleteDirectory(resource_path('prompts/test_agent'));
});

it('loads default.md when no specific version is set', function () {
    // Create test prompt file
    $promptPath = resource_path('prompts/test_agent');
    File::makeDirectory($promptPath, 0755, true, true);
    File::put($promptPath.'/default.md', 'Default file instructions');

    $agent = new class extends BaseLlmAgent
    {
        protected string $name = 'test_agent';

        protected string $instructions = 'Default class instructions';
    };

    expect($agent->getInstructions())->toBe('Default file instructions');

    // Cleanup
    File::deleteDirectory(resource_path('prompts/test_agent'));
});

it('returns available prompt versions', function () {
    // Create test prompt files
    $promptPath = resource_path('prompts/test_agent');
    File::makeDirectory($promptPath, 0755, true, true);
    File::put($promptPath.'/v1.md', 'Version 1');
    File::put($promptPath.'/v2.md', 'Version 2');
    File::put($promptPath.'/default.md', 'Default');

    $agent = new class extends BaseLlmAgent
    {
        protected string $name = 'test_agent';

        protected string $instructions = 'Default instructions';
    };

    $versions = $agent->getAvailablePromptVersions();
    expect($versions)->toContain('v1', 'v2');
    expect($versions)->not->toContain('default'); // default is filtered out

    // Cleanup
    File::deleteDirectory(resource_path('prompts/test_agent'));
});

it('priority order is correct: override > database > file > class', function () {
    // Create test prompt file
    $promptPath = resource_path('prompts/test_agent');
    File::makeDirectory($promptPath, 0755, true, true);
    File::put($promptPath.'/default.md', 'File instructions');

    $agent = new class extends BaseLlmAgent
    {
        protected string $name = 'test_agent';

        protected string $instructions = 'Class instructions';
    };

    // Test class property (lowest priority)
    expect($agent->getInstructions())->toBe('File instructions');

    // Test runtime override (highest priority)
    $agent->setPromptOverride('Override instructions');
    expect($agent->getInstructions())->toBe('Override instructions');

    // Cleanup
    File::deleteDirectory(resource_path('prompts/test_agent'));
});

it('uses default prompt version from class property', function () {
    // Create test prompt files
    $promptPath = resource_path('prompts/test_agent');
    File::makeDirectory($promptPath, 0755, true, true);
    File::put($promptPath.'/default.md', 'Default instructions');
    File::put($promptPath.'/professional.md', 'Professional instructions');

    $agent = new class extends BaseLlmAgent
    {
        protected string $name = 'test_agent';

        // Set default prompt version
        protected ?string $promptVersion = 'professional';

        protected string $instructions = 'Class instructions';
    };

    // Should use the professional version by default
    expect($agent->getInstructions())->toBe('Professional instructions');
    expect($agent->getPromptVersion())->toBe('professional');

    // Can still override at runtime
    $agent->setPromptVersion('default');
    expect($agent->getInstructions())->toBe('Default instructions');

    // Cleanup
    File::deleteDirectory(resource_path('prompts/test_agent'));
});
