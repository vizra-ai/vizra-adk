<?php

namespace Vizra\VizraADK\Traits;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

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
    public function setPromptOverride(string $prompt): self
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
            $files = File::files($promptPath);
            foreach ($files as $file) {
                $filename = $file->getFilenameWithoutExtension();
                if ($filename !== 'default' && ! in_array($filename, $versions)) {
                    $versions[] = $filename;
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

        // Try specific version first
        if ($this->promptVersion) {
            $versionFile = $promptPath.'/'.$this->promptVersion.'.md';
            if (File::exists($versionFile)) {
                return File::get($versionFile);
            }
        }

        // Try default file
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
}
