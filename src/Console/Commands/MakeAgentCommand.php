<?php

namespace Vizra\VizraADK\Console\Commands;

use Illuminate\Console\GeneratorCommand;
use Illuminate\Support\Str;
use Symfony\Component\Console\Input\InputArgument;

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
        $stub = str_replace('{{ agentName }}', Str::snake(class_basename($name)), $stub);

        return $stub;
    }

    protected function getArguments(): array
    {
        return [
            ['name', InputArgument::REQUIRED, 'The name of the agent class.'],
        ];
    }
}
