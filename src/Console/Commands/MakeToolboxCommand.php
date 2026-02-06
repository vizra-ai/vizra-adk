<?php

namespace Vizra\VizraADK\Console\Commands;

use Illuminate\Console\GeneratorCommand;
use Illuminate\Support\Str;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

class MakeToolboxCommand extends GeneratorCommand
{
    protected $name = 'vizra:make:toolbox';

    protected $description = 'Create a new Toolbox class for grouping tools';

    protected $type = 'Toolbox';

    protected function getStub(): string
    {
        return __DIR__.'/stubs/toolbox.stub';
    }

    protected function getDefaultNamespace($rootNamespace): string
    {
        $configuredNamespace = config('vizra-adk.namespaces.toolboxes');

        if ($configuredNamespace) {
            return $configuredNamespace;
        }

        // Fallback to rootNamespace + \Toolboxes
        $baseNamespace = $rootNamespace ?: 'App';

        return $baseNamespace.'\Toolboxes';
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

        // Generate toolbox name (snake_case without _toolbox suffix)
        $toolboxName = Str::snake($className);
        if (Str::endsWith($toolboxName, '_toolbox')) {
            $toolboxName = substr($toolboxName, 0, -8);
        }

        $stub = str_replace('{{ toolboxName }}', $toolboxName, $stub);
        $stub = str_replace('{{ toolboxDescription }}', Str::headline($toolboxName).' tools', $stub);

        // Handle gate option
        $gate = $this->option('gate');
        if ($gate) {
            $gateProperty = "protected ?string \$gate = '{$gate}';";
        } else {
            $gateProperty = '// protected ?string $gate = null;';
        }
        $stub = str_replace('{{ gateProperty }}', $gateProperty, $stub);

        // Handle policy option
        $policy = $this->option('policy');
        if ($policy) {
            $policyProperty = "protected ?string \$policy = \\{$policy}::class;";
        } else {
            $policyProperty = '// protected ?string $policy = null;';
        }
        $stub = str_replace('{{ policyProperty }}', $policyProperty, $stub);

        // Handle tools option
        $tools = $this->option('tools');
        if ($tools) {
            $toolClasses = array_map('trim', explode(',', $tools));
            $toolsList = '';
            foreach ($toolClasses as $toolClass) {
                $toolsList .= "        \\{$toolClass}::class,\n";
            }
            $toolsList = rtrim($toolsList, "\n");
        } else {
            $toolsList = '        // Add your tool classes here';
        }
        $stub = str_replace('{{ toolsList }}', $toolsList, $stub);

        return $stub;
    }

    protected function getArguments(): array
    {
        return [
            ['name', InputArgument::REQUIRED, 'The name of the toolbox class.'],
        ];
    }

    protected function getOptions(): array
    {
        return [
            ['gate', 'g', InputOption::VALUE_OPTIONAL, 'The Laravel Gate name for authorization'],
            ['policy', 'p', InputOption::VALUE_OPTIONAL, 'The Laravel Policy class for authorization'],
            ['tools', 't', InputOption::VALUE_OPTIONAL, 'Comma-separated list of tool class names to include'],
        ];
    }
}
