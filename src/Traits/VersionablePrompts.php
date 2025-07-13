<?php

namespace Vizra\VizraADK\Traits;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\View;

trait VersionablePrompts
{
    protected ?string $promptVersion = null;

    protected ?string $promptOverride = null;

    /**
     * Get the instructions for this agent, with version support
     */
    public function getInstructions(): string
    {
        $instructions = '';

        // 1. Check for runtime override
        if ($this->promptOverride !== null) {
            $instructions = $this->promptOverride;
        }
        // 2. Check for database override (cached for performance)
        elseif (($dbPrompt = $this->getDatabasePrompt()) !== null) {
            $instructions = $dbPrompt;
        }
        // 3. Check for file-based prompt
        elseif (($filePrompt = $this->getFilePrompt()) !== null) {
            $instructions = $filePrompt;
        }
        // 4. Fall back to class property
        else {
            $instructions = $this->instructions ?? '';
        }

        // Add delegation information if sub-agents are available
        if (property_exists($this, 'loadedSubAgents') && ! empty($this->loadedSubAgents)) {
            $subAgentNames = array_keys($this->loadedSubAgents);
            $subAgentsList = implode(', ', $subAgentNames);

            $delegationInfo = "\n\nDELEGATION CAPABILITIES:\n".
                'You have access to specialized sub-agents for handling specific tasks. '.
                "Available sub-agents: {$subAgentsList}. ".
                "Use the 'delegate_to_sub_agent' tool when a task would be better handled by one of your sub-agents. ".
                'This allows you to leverage specialized expertise and break down complex problems into manageable parts.';

            $instructions .= $delegationInfo;
        }

        return $instructions;
    }

    /**
     * Set a specific prompt version to use
     */
    public function setPromptVersion(?string $version): self
    {
        $this->promptVersion = $version;

        return $this;
    }

    /**
     * Override the prompt entirely (useful for testing)
     */
    public function setPromptOverride(?string $prompt): self
    {
        $this->promptOverride = $prompt;

        return $this;
    }

    /**
     * Get the current prompt version
     */
    public function getPromptVersion(): ?string
    {
        return $this->promptVersion;
    }

    /**
     * Get all available prompt versions
     */
    public function getAvailablePromptVersions(): array
    {
        $versions = [];

        // Check database versions
        if (config('vizra-adk.prompts.use_database', false)) {
            $dbVersions = DB::table('agent_prompt_versions')
                ->where('agent_name', $this->getName())
                ->pluck('version')
                ->toArray();
            $versions = array_merge($versions, $dbVersions);
        }

        // Check file versions
        $promptPath = $this->getPromptPath();
        if (File::exists($promptPath)) {
            // Check for version directories
            $directories = File::directories($promptPath);
            foreach ($directories as $dir) {
                $dirName = basename($dir);
                if (preg_match('/^v\d+$/', $dirName)) {
                    // Add version
                    $versions[] = $dirName;

                    // Add version/variant combinations
                    $variantFiles = File::files($dir);
                    foreach ($variantFiles as $file) {
                        $ext = $file->getExtension();
                        if ($ext === 'md' || ($ext === 'php' && str_ends_with($file->getFilename(), '.blade.php'))) {
                            $filename = $file->getFilename();
                            if (str_ends_with($filename, '.blade.php')) {
                                $variant = substr($filename, 0, -10); // Remove .blade.php
                            } else {
                                $variant = $file->getFilenameWithoutExtension();
                            }
                            $versions[] = $dirName.'/'.$variant;
                        }
                    }
                }
            }

            // Check for direct files (backward compatibility)
            $files = File::files($promptPath);
            foreach ($files as $file) {
                $ext = $file->getExtension();
                if ($ext === 'md' || ($ext === 'php' && str_ends_with($file->getFilename(), '.blade.php'))) {
                    $fullFilename = $file->getFilename();
                    if (str_ends_with($fullFilename, '.blade.php')) {
                        $filename = substr($fullFilename, 0, -10); // Remove .blade.php
                    } else {
                        $filename = $file->getFilenameWithoutExtension();
                    }
                    if (! in_array($filename, $versions) && $filename !== 'default') {
                        $versions[] = $filename;
                    }
                }
            }
        }

        return array_unique($versions);
    }

    /**
     * Get prompt from database
     */
    protected function getDatabasePrompt(): ?string
    {
        if (! config('vizra-adk.prompts.use_database', false)) {
            return null;
        }

        $cacheKey = "agent_prompt:{$this->getName()}:{$this->promptVersion}";

        return Cache::remember($cacheKey, 300, function () {
            $query = DB::table('agent_prompt_versions')
                ->where('agent_name', $this->getName());

            if ($this->promptVersion) {
                $query->where('version', $this->promptVersion);
            } else {
                $query->where('is_active', true);
            }

            $prompt = $query->first();

            return $prompt ? $prompt->instructions : null;
        });
    }

    /**
     * Get prompt from file
     */
    protected function getFilePrompt(): ?string
    {
        $promptPath = $this->getPromptPath();

        // Handle version/variant format (e.g., "v2/formal", "v1/default")
        if ($this->promptVersion && str_contains($this->promptVersion, '/')) {
            [$version, $variant] = explode('/', $this->promptVersion, 2);
            
            // Try Blade template first
            $versionBladeFile = $promptPath.'/'.$version.'/'.$variant.'.blade.php';
            if (File::exists($versionBladeFile)) {
                return $this->renderBladePrompt($versionBladeFile);
            }
            
            // Fall back to markdown
            $versionFile = $promptPath.'/'.$version.'/'.$variant.'.md';
            if (File::exists($versionFile)) {
                return File::get($versionFile);
            }
        }

        // Try specific version/variant first
        if ($this->promptVersion) {
            // Check if it's a version directory (e.g., "v2" -> "v2/default")
            $versionDefaultBladeFile = $promptPath.'/'.$this->promptVersion.'/default.blade.php';
            if (File::exists($versionDefaultBladeFile)) {
                return $this->renderBladePrompt($versionDefaultBladeFile);
            }
            
            $versionDefaultFile = $promptPath.'/'.$this->promptVersion.'/default.md';
            if (File::exists($versionDefaultFile)) {
                return File::get($versionDefaultFile);
            }

            // Check if it's a variant in the latest version
            $latestVersion = $this->getLatestVersion();
            if ($latestVersion) {
                $variantBladeFile = $promptPath.'/'.$latestVersion.'/'.$this->promptVersion.'.blade.php';
                if (File::exists($variantBladeFile)) {
                    return $this->renderBladePrompt($variantBladeFile);
                }
                
                $variantFile = $promptPath.'/'.$latestVersion.'/'.$this->promptVersion.'.md';
                if (File::exists($variantFile)) {
                    return File::get($variantFile);
                }
            }

            // Check if it's a direct file (backward compatibility)
            $versionBladeFile = $promptPath.'/'.$this->promptVersion.'.blade.php';
            if (File::exists($versionBladeFile)) {
                return $this->renderBladePrompt($versionBladeFile);
            }
            
            $versionFile = $promptPath.'/'.$this->promptVersion.'.md';
            if (File::exists($versionFile)) {
                return File::get($versionFile);
            }
        }

        // Try latest version default
        $latestVersion = $this->getLatestVersion();
        if ($latestVersion) {
            $latestDefaultBladeFile = $promptPath.'/'.$latestVersion.'/default.blade.php';
            if (File::exists($latestDefaultBladeFile)) {
                return $this->renderBladePrompt($latestDefaultBladeFile);
            }
            
            $latestDefaultFile = $promptPath.'/'.$latestVersion.'/default.md';
            if (File::exists($latestDefaultFile)) {
                return File::get($latestDefaultFile);
            }
        }

        // Try default file (backward compatibility)
        $defaultBladeFile = $promptPath.'/default.blade.php';
        if (File::exists($defaultBladeFile)) {
            return $this->renderBladePrompt($defaultBladeFile);
        }
        
        $defaultFile = $promptPath.'/default.md';
        if (File::exists($defaultFile)) {
            return File::get($defaultFile);
        }

        return null;
    }

    /**
     * Get the prompt storage path for this agent
     */
    protected function getPromptPath(): string
    {
        $basePath = config('vizra-adk.prompts.storage_path', resource_path('prompts'));

        return $basePath.'/'.$this->getName();
    }

    /**
     * Get the latest version directory
     */
    protected function getLatestVersion(): ?string
    {
        $promptPath = $this->getPromptPath();

        if (! File::exists($promptPath)) {
            return null;
        }

        // Look for version directories (v1, v2, v3, etc.)
        $directories = File::directories($promptPath);
        $versionDirs = [];

        foreach ($directories as $dir) {
            $dirName = basename($dir);
            if (preg_match('/^v(\d+)$/', $dirName, $matches)) {
                $versionDirs[(int) $matches[1]] = $dirName;
            }
        }

        if (empty($versionDirs)) {
            return null;
        }

        // Sort by version number and get the highest
        ksort($versionDirs);

        return end($versionDirs);
    }

    /**
     * Log prompt usage for analytics
     */
    protected function logPromptUsage(string $sessionId): void
    {
        if (! config('vizra-adk.prompts.track_usage', false)) {
            return;
        }

        DB::table('agent_prompt_usage')->insert([
            'agent_name' => $this->getName(),
            'version' => $this->promptVersion ?? 'default',
            'session_id' => $sessionId,
            'created_at' => now(),
        ]);
    }

    /**
     * Render a Blade template prompt with context
     */
    protected function renderBladePrompt(string $path): string
    {
        // Prepare the data for the Blade template
        $data = [
            'agent' => [
                'name' => $this->getName(),
                'description' => $this->getDescription(),
            ],
        ];

        // Add user context if available
        if (property_exists($this, 'context') && $this->context) {
            $allState = $this->context->getAllState();
            
            // Add user-specific data
            if (isset($allState['user_name'])) {
                $data['user_name'] = $allState['user_name'];
            }
            if (isset($allState['user_email'])) {
                $data['user_email'] = $allState['user_email'];
            }
            if (isset($allState['user_data'])) {
                $data['user_data'] = $allState['user_data'];
            }
            
            // Add all context state
            $data = array_merge($data, $allState);
        }

        // Add tools information
        if (property_exists($this, 'loadedTools') && !empty($this->loadedTools)) {
            $data['tools'] = collect($this->loadedTools)->map(function ($tool) {
                return $tool->definition();
            });
        }

        // Add sub-agents information
        if (property_exists($this, 'loadedSubAgents') && !empty($this->loadedSubAgents)) {
            $data['subAgents'] = collect($this->loadedSubAgents)->map(function ($agent, $name) {
                return [
                    'name' => $name,
                    'description' => $agent->getDescription(),
                ];
            });
        }

        // Check if the agent has a getPromptData method for custom data
        if (method_exists($this, 'getPromptData')) {
            $context = property_exists($this, 'context') ? $this->context : null;
            
            // Use reflection to check if the method parameter is nullable
            $reflection = new \ReflectionMethod($this, 'getPromptData');
            $parameters = $reflection->getParameters();
            
            if (!empty($parameters)) {
                $firstParam = $parameters[0];
                $paramType = $firstParam->getType();
                
                // If context is null and parameter doesn't allow null, skip calling the method
                if ($context === null && $paramType && !$paramType->allowsNull()) {
                    // Skip calling getPromptData if we don't have a context and it's required
                } else {
                    $customData = $this->getPromptData($context);
                    if (is_array($customData)) {
                        $data = array_merge($data, $customData);
                    }
                }
            } else {
                // No parameters, just call it
                $customData = $this->getPromptData();
                if (is_array($customData)) {
                    $data = array_merge($data, $customData);
                }
            }
        }

        // Use Laravel's Blade compiler to render the template
        $blade = app('blade.compiler');
        $compiled = $blade->compileString(File::get($path));
        
        ob_start();
        extract($data, EXTR_SKIP);
        eval('?>' . $compiled);
        $rendered = ob_get_clean();

        return $rendered ?: '';
    }
}
