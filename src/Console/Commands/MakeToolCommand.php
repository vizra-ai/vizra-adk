<?php

namespace AaronLumsden\LaravelAiADK\Console\Commands;

use Illuminate\Console\GeneratorCommand;
use Illuminate\Support\Str;
use Symfony\Component\Console\Input\InputArgument;

class MakeToolCommand extends GeneratorCommand
{
    protected $name = 'agent:make:tool';
    protected $description = 'Create a new Tool class for an Agent';
    protected $type = 'Tool';

    protected function getStub(): string
    {
        return __DIR__.'/stubs/tool.stub';
    }

    protected function getDefaultNamespace($rootNamespace): string
    {
        $configuredNamespace = config('agent-adk.namespaces.tools');
        
        if ($configuredNamespace) {
            return $configuredNamespace;
        }
        
        // Fallback to rootNamespace + \Tools, or just App\Tools if no root namespace
        $baseNamespace = $rootNamespace ?: 'App';
        return $baseNamespace . '\Tools';
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
        $className = class_basename($name);
        $toolName = Str::snake($className);
        if (Str::endsWith($toolName, '_tool')) {
            $toolName = substr($toolName, 0, -5);
        }
        $stub = str_replace('{{ toolName }}', $toolName, $stub);
        $stub = str_replace('{{ toolDescription }}', Str::headline($toolName), $stub);
        return $stub;
    }

    protected function getArguments(): array
    {
        return [
            ['name', InputArgument::REQUIRED, 'The name of the tool class.'],
        ];
    }
}
