<?php

namespace Vizra\VizraADK\Console\Commands;

use Illuminate\Console\GeneratorCommand;

class MakeAssertionCommand extends GeneratorCommand
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $signature = 'vizra:make:assertion {name : The name of the assertion class}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new custom assertion class';

    /**
     * The type of class being generated.
     *
     * @var string
     */
    protected $type = 'Assertion';

    /**
     * Get the stub file for the generator.
     *
     * @return string
     */
    protected function getStub()
    {
        return __DIR__.'/stubs/assertion.stub';
    }

    /**
     * Get the default namespace for the class.
     *
     * @param  string  $rootNamespace
     * @return string
     */
    protected function getDefaultNamespace($rootNamespace)
    {
        return $rootNamespace.'\Evaluations\Assertions';
    }

    /**
     * Get the console command options.
     *
     * @return array
     */
    protected function getOptions()
    {
        return [];
    }
}
