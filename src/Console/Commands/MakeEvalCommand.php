<?php

namespace Vizra\VizraAdk\Console\Commands;

use Illuminate\Console\GeneratorCommand;
use Symfony\Component\Console\Input\InputArgument;
use Illuminate\Support\Str;

class MakeEvalCommand extends GeneratorCommand
{
    protected $name = 'vizra:make:eval';
    protected $description = 'Create a new LLM evaluation class';
    protected $type = 'Evaluation';

    protected function getStub()
    {
        // Check if a custom stub exists in the app's stubs directory first
        $customPath = $this->laravel->basePath('stubs/vendor/agent-adk/evaluation.stub');
        if (file_exists($customPath)) {
            return $customPath;
        }
        return __DIR__.'/stubs/evaluation.stub';
    }

    protected function getDefaultNamespace($rootNamespace)
    {
        $configuredNamespace = config('agent-adk.namespaces.evaluations');
        
        if ($configuredNamespace) {
            return $configuredNamespace;
        }
        
        // Fallback to rootNamespace + \Evaluations, or just App\Evaluations if no root namespace
        $baseNamespace = $rootNamespace ?: 'App';
        return $baseNamespace . '\\Evaluations';
    }

    protected function rootNamespace()
    {
        try {
            return $this->laravel ? $this->laravel->getNamespace() : 'App\\';
        } catch (\Exception $e) {
            return 'App\\';
        }
    }

    protected function buildClass($name)
    {
        $stub = parent::buildClass($name);

        $rawName = $this->argument('name');
        // Remove 'Evaluation' suffix if user typed it, for creating a more natural 'evaluation_name'
        $evaluationName = Str::studly($rawName);
        if (Str::endsWith($evaluationName, 'Evaluation')) {
            $evaluationName = substr($evaluationName, 0, -10);
        }

        // For '{{ evaluation_name }}' placeholder (e.g., "Product Review Sentiment")
        $humanReadableName = trim(Str::ucfirst(Str::snake($evaluationName, ' ')));

        // For '{{ csv_file_name }}' placeholder (e.g., "product_review_sentiment")
        $csvFileName = Str::snake($evaluationName);

        $stub = str_replace('{{ evaluation_name }}', $humanReadableName, $stub);
        $stub = str_replace('{{ csv_file_name }}', $csvFileName, $stub);

        return $stub;
    }

    protected function getArguments()
    {
        return [
            ['name', InputArgument::REQUIRED, 'The name of the evaluation (e.g., ProductReviewSentiment).'],
        ];
    }

    protected function getNameInput()
    {
        $name = Str::studly(trim($this->argument('name')));
        if (!Str::endsWith($name, 'Evaluation')) {
            $name .= 'Evaluation';
        }
        return $name;
    }
}
