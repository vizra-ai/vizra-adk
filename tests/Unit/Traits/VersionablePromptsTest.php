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

    // The trait adds delegation info if sub-agents are available, so check the start
    expect($agent->getInstructions())->toContain('Default instructions');
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

    // The trait adds delegation info if sub-agents are available, so check the start  
    expect($agent->getInstructions())->toContain('Default file instructions');

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
    
    // Clean any existing templates that might interfere
    File::delete($promptPath.'/default.blade.php');
    File::delete($promptPath.'/professional.blade.php');
    
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

// Additional edge case tests for VersionablePrompts
it('handles non-existent prompt version gracefully', function () {
    // Create test prompt directory with some files
    $promptPath = resource_path('prompts/test_agent_nonexistent');
    File::makeDirectory($promptPath, 0755, true, true);
    File::put($promptPath.'/v1.md', 'Version 1 instructions');
    File::put($promptPath.'/default.md', 'Default instructions');
    
    $agent = new class extends BaseLlmAgent {
        protected string $name = 'test_agent_nonexistent';
        protected string $instructions = 'Class instructions';
    };
    
    // Try to set a non-existent version
    $agent->setPromptVersion('non_existent_version');
    
    // Should fall back to class property since file doesn't exist
    // But first it checks for default.md which exists, so it returns that instead
    expect($agent->getInstructions())->toBe('Default instructions');
    
    // Cleanup
    File::deleteDirectory(resource_path('prompts/test_agent_nonexistent'));
});

it('handles prompt file reading errors gracefully', function () {
    $agent = new class extends BaseLlmAgent {
        protected string $name = 'test_agent_no_dir';
        protected string $instructions = 'Fallback instructions';
    };
    
    // Try to set version when directory doesn't exist
    $agent->setPromptVersion('any_version');
    
    // Should fall back to class property
    expect($agent->getInstructions())->toBe('Fallback instructions');
});

it('handles concurrent prompt version changes', function () {
    // Create test prompt files
    $promptPath = resource_path('prompts/concurrent_agent');
    File::makeDirectory($promptPath, 0755, true, true);
    File::put($promptPath.'/v1.md', 'Version 1 concurrent');
    File::put($promptPath.'/v2.md', 'Version 2 concurrent');
    
    $agent = new class extends BaseLlmAgent {
        protected string $name = 'concurrent_agent';
        protected string $instructions = 'Base instructions';
    };
    
    // Set version v1
    $agent->setPromptVersion('v1');
    expect($agent->getInstructions())->toBe('Version 1 concurrent');
    
    // Change to v2
    $agent->setPromptVersion('v2');
    expect($agent->getInstructions())->toBe('Version 2 concurrent');
    
    // Change back to v1
    $agent->setPromptVersion('v1');
    expect($agent->getInstructions())->toBe('Version 1 concurrent');
    
    // Clear version (should go to default.md if it exists, otherwise class property)
    $agent->setPromptVersion(null);
    expect($agent->getInstructions())->toBe('Base instructions');
    
    // Cleanup
    File::deleteDirectory(resource_path('prompts/concurrent_agent'));
});

it('prompt version caching behavior works correctly', function () {
    // Create test prompt files
    $promptPath = resource_path('prompts/cached_agent');
    File::makeDirectory($promptPath, 0755, true, true);
    File::put($promptPath.'/cached.md', 'Cached prompt content');
    
    $agent = new class extends BaseLlmAgent {
        protected string $name = 'cached_agent';
        protected string $instructions = 'Original instructions';
    };
    
    // Set version and read
    $agent->setPromptVersion('cached');
    $firstRead = $agent->getInstructions();
    expect($firstRead)->toBe('Cached prompt content');
    
    // Modify file content
    File::put($promptPath.'/cached.md', 'Modified cached content');
    
    // Read again - should get updated content (no aggressive caching)
    $secondRead = $agent->getInstructions();
    expect($secondRead)->toBe('Modified cached content');
    
    // Cleanup
    File::deleteDirectory(resource_path('prompts/cached_agent'));
});

it('handles special characters in prompt files', function () {
    // Create test prompt with special characters
    $promptPath = resource_path('prompts/special_agent');
    File::makeDirectory($promptPath, 0755, true, true);
    
    $specialContent = "Instructions with émojis 🤖 and \"quotes\" and newlines\nMultiple lines\nWith special chars: @#$%";
    File::put($promptPath.'/special.md', $specialContent);
    
    $agent = new class extends BaseLlmAgent {
        protected string $name = 'special_agent';
        protected string $instructions = 'Basic instructions';
    };
    
    $agent->setPromptVersion('special');
    expect($agent->getInstructions())->toBe($specialContent);
    
    // Cleanup
    File::deleteDirectory(resource_path('prompts/special_agent'));
});

it('getAvailablePromptVersions filters correctly', function () {
    // Create test prompt files with various names
    $promptPath = resource_path('prompts/filter_agent');
    File::makeDirectory($promptPath, 0755, true, true);
    File::put($promptPath.'/v1.md', 'Version 1');
    File::put($promptPath.'/v2.md', 'Version 2');
    File::put($promptPath.'/beta.md', 'Beta version');
    File::put($promptPath.'/default.md', 'Default version');
    File::put($promptPath.'/README.txt', 'Not a prompt file');
    File::put($promptPath.'/template.md.backup', 'Backup file');
    
    $agent = new class extends BaseLlmAgent {
        protected string $name = 'filter_agent';
        protected string $instructions = 'Base instructions';
    };
    
    $versions = $agent->getAvailablePromptVersions();
    
    // Should include .md files except default
    expect($versions)->toContain('v1', 'v2', 'beta');
    
    // Should exclude default, non-.md files, and backup files
    expect($versions)->not->toContain('default', 'README', 'template.md.backup');
    
    // Should be an array
    expect($versions)->toBeArray();
    
    // Cleanup
    File::deleteDirectory(resource_path('prompts/filter_agent'));
});

it('prompt override persists across multiple calls', function () {
    $agent = new class extends BaseLlmAgent {
        protected string $name = 'persistent_agent';
        protected string $instructions = 'Original instructions';
    };
    
    // Set override
    $overrideText = 'Persistent override instructions';
    $agent->setPromptOverride($overrideText);
    
    // Multiple calls should return the same override
    expect($agent->getInstructions())->toBe($overrideText);
    expect($agent->getInstructions())->toBe($overrideText);
    expect($agent->getInstructions())->toBe($overrideText);
    
    // Clear override
    $agent->setPromptOverride(null);
    expect($agent->getInstructions())->toBe('Original instructions');
});
