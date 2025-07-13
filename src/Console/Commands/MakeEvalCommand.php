<?php

namespace Vizra\VizraADK\Console\Commands;

use Illuminate\Console\GeneratorCommand;
use Illuminate\Support\Str;
use Symfony\Component\Console\Input\InputArgument;
use Illuminate\Support\Facades\File;

class MakeEvalCommand extends GeneratorCommand
{
    protected $name = 'vizra:make:eval';

    protected $description = 'Create a new LLM evaluation class';

    protected $type = 'Evaluation';

    protected function getStub()
    {
        // Check if a custom stub exists in the app's stubs directory first
        $customPath = $this->laravel->basePath('stubs/vendor/vizra-adk/evaluation.stub');
        if (file_exists($customPath)) {
            return $customPath;
        }

        return __DIR__.'/stubs/evaluation.stub';
    }

    protected function getDefaultNamespace($rootNamespace)
    {
        $configuredNamespace = config('vizra-adk.namespaces.evaluations');

        if ($configuredNamespace) {
            return $configuredNamespace;
        }

        // Fallback to rootNamespace + \Evaluations, or just App\Evaluations if no root namespace
        $baseNamespace = $rootNamespace ?: 'App';

        return $baseNamespace.'\\Evaluations';
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
        if (! Str::endsWith($name, 'Evaluation')) {
            $name .= 'Evaluation';
        }

        return $name;
    }

    public function handle()
    {
        // Create the evaluation class using parent's functionality
        $result = parent::handle();

        // If the evaluation class was created successfully, also create the CSV file
        if ($result !== false) {
            $this->createCsvFile();
        }

        return $result;
    }

    protected function createCsvFile(): void
    {
        $rawName = $this->argument('name');
        
        // Remove 'Evaluation' suffix if user typed it, for creating CSV file name
        $evaluationName = Str::studly($rawName);
        if (Str::endsWith($evaluationName, 'Evaluation')) {
            $evaluationName = substr($evaluationName, 0, -10);
        }

        // Generate CSV file name in snake_case
        $csvFileName = Str::snake($evaluationName);
        
        // Determine the data directory path
        $evaluationsPath = app_path('Evaluations');
        $dataPath = $evaluationsPath . '/data';
        $csvFilePath = $dataPath . '/' . $csvFileName . '.csv';

        // Create the data directory if it doesn't exist
        if (!File::exists($dataPath)) {
            File::makeDirectory($dataPath, 0755, true);
        }

        // Only create the CSV file if it doesn't already exist
        if (!File::exists($csvFilePath)) {
            $csvContent = $this->generateCsvContent();
            File::put($csvFilePath, $csvContent);
            
            $this->info("Created CSV file: {$csvFilePath}");
        } else {
            $this->warn("CSV file already exists: {$csvFilePath}");
        }
    }

    protected function generateCsvContent(): string
    {
        // Create CSV with standard headers for evaluation files
        $headers = ['prompt', 'expected_response', 'description'];
        
        return implode(',', $headers) . "\n";
    }
}
