<?php

namespace Vizra\VizraADK\Console\Commands;

use Illuminate\Console\GeneratorCommand;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

class MakeAgentCommand extends GeneratorCommand
{
    protected $name = 'vizra:make:agent';

    protected $description = 'Create a new Agent class';

    protected $type = 'Agent';

    protected function getStub(): string
    {
        return __DIR__.'/stubs/agent.stub';
    }

    protected function getDefaultNamespace($rootNamespace): string
    {
        $configuredNamespace = config('vizra-adk.namespaces.agents');

        if ($configuredNamespace) {
            return $configuredNamespace;
        }

        // Fallback to rootNamespace + \Agents, or just App\Agents if no root namespace
        $baseNamespace = $rootNamespace ?: 'App';

        return $baseNamespace.'\Agents';
    }

    protected function rootNamespace()
    {
        try {
            return $this->laravel ? $this->laravel->getNamespace() : 'App\\';
        } catch (\Exception $e) {
            return 'App\\';
        }
    }

    protected function qualifyClass($name): string
    {
        $name = ltrim($name, '\\/');
        $name = str_replace('/', '\\', $name);

        $rootNamespace = $this->rootNamespace();

        if (Str::startsWith($name, $rootNamespace)) {
            return $name;
        }

        return $this->getDefaultNamespace(trim($rootNamespace, '\\')).'\\'.$name;
    }

    protected function buildClass($name): string
    {
        $stub = parent::buildClass($name);
        $agentName = Str::snake(class_basename($name));
        $stub = str_replace('{{ agentName }}', $agentName, $stub);
        $stub = str_replace('{{ agentNameHuman }}', Str::headline(class_basename($name)), $stub);

        return $stub;
    }

    /**
     * Execute the console command.
     *
     * @return bool|null
     *
     * @throws \Illuminate\Contracts\Filesystem\FileNotFoundException
     */
    public function handle()
    {
        // First, create the agent class
        $result = parent::handle();

        // The parent handle() returns false when file exists and --force is not used
        // This is not an error, it's expected behavior
        if ($result === false) {
            // Still create prompt files if they don't exist
            $this->createPromptFiles();

            return 0; // Return 0 for success - not overwriting is expected behavior
        }

        // Create the prompt directory and file
        $this->createPromptFiles();

        return 0; // Return 0 for success
    }

    /**
     * Create the prompt directory and default prompt file for the agent.
     */
    protected function createPromptFiles(): void
    {
        $agentName = Str::snake(class_basename($this->argument('name')));
        $promptsPath = config('vizra-adk.prompts.storage_path', resource_path('prompts'));
        $agentPromptPath = $promptsPath.'/'.$agentName;

        // Create the directory if it doesn't exist
        if (! File::exists($agentPromptPath)) {
            File::makeDirectory($agentPromptPath, 0755, true);
            $this->info('Created prompt directory: '.$agentPromptPath);
        }

        // Create the default.blade.php file
        $defaultPromptFile = $agentPromptPath.'/default.blade.php';

        if (! File::exists($defaultPromptFile) || $this->option('force')) {
            $promptContent = $this->buildPromptContent($agentName);
            File::put($defaultPromptFile, $promptContent);
            $this->info('Created default prompt file: '.$defaultPromptFile);
        } else {
            $this->warn('Prompt file already exists: '.$defaultPromptFile);
        }
    }

    /**
     * Build the content for the default prompt file.
     */
    protected function buildPromptContent(string $agentName): string
    {
        $agentNameHuman = Str::headline(Str::replace('_', ' ', $agentName));

        $stubPath = __DIR__.'/stubs/agent-prompt.stub';

        if (File::exists($stubPath)) {
            $content = File::get($stubPath);
            $content = str_replace('{{ agentName }}', $agentName, $content);
            $content = str_replace('{{ agentNameHuman }}', $agentNameHuman, $content);

            return $content;
        }

        // Fallback content if stub doesn't exist
        return <<<PROMPT
You are {$agentNameHuman}, an AI assistant specialized in helping users with their tasks.

## Core Responsibilities

1. Provide helpful and accurate responses
2. Be concise and clear in your communication
3. Ask for clarification when needed

## Instructions

- Maintain a professional and friendly tone
- Focus on solving the user's specific needs
- Use the available tools when appropriate to accomplish tasks

## Guidelines

Remember to:
- Think step by step when solving complex problems
- Verify information before providing it
- Admit when you don't know something rather than guessing
PROMPT;
    }

    protected function getArguments(): array
    {
        return [
            ['name', InputArgument::REQUIRED, 'The name of the agent class.'],
        ];
    }

    /**
     * Get the console command options.
     */
    protected function getOptions(): array
    {
        return [
            ['force', 'f', InputOption::VALUE_NONE, 'Create the class even if the agent already exists'],
        ];
    }
}
