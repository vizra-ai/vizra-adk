<?php

namespace Vizra\VizraAdk\Console\Commands; // Updated namespace

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str; // Added for UUID
use Vizra\VizraAdk\Evaluations\BaseEvaluation; // Updated namespace
use Vizra\VizraAdk\Facades\Agent; // Added Agent Facade
use Exception;
use InvalidArgumentException;
use League\Csv\Writer;
use League\Csv\CannotInsertRecord;

class RunEvalCommand extends Command
{
    protected $signature = 'vizra:run:eval {name : The class name of the evaluation (e.g., MyTestEvaluation)}
                                     {--output= : Path to the CSV file to save results (e.g., results.csv)}';
    protected $description = 'Run an LLM evaluation and output the results.';

    // AgentManager removed from constructor
    public function __construct()
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $evaluationNameArgument = $this->argument('name');
        $outputFile = $this->option('output');

        // Resolve evaluation class name (considering App namespace first, then package namespace)
        $studlyEvalName = Str::studly($evaluationNameArgument);
        $evaluationAppNamespace = $this->laravel->getNamespace().'Evaluations\\'.$studlyEvalName;
        $evaluationPackageNamespace = 'Vizra\\VizraAdk\\Evaluations\\'.$studlyEvalName;

        if (class_exists($evaluationAppNamespace)) {
            $evaluationClass = $evaluationAppNamespace;
        } elseif (class_exists($evaluationPackageNamespace)) {
            $evaluationClass = $evaluationPackageNamespace;
        } elseif (class_exists($evaluationNameArgument)) { // Assume fully qualified if not found in typical namespaces
            $evaluationClass = $evaluationNameArgument;
        } else {
            $this->error("Evaluation class '{$evaluationNameArgument}' not found.");
            $this->line("Searched in:");
            $this->line("  - {$evaluationAppNamespace}");
            $this->line("  - {$evaluationPackageNamespace}");
            $this->line("  - As fully qualified: {$evaluationNameArgument}");
            return Command::FAILURE;
        }

        if (!is_subclass_of($evaluationClass, BaseEvaluation::class)) {
            $this->error("Class '{$evaluationClass}' must extend ".BaseEvaluation::class);
            return Command::FAILURE;
        }

        try {
            /** @var BaseEvaluation $evaluation */
            $evaluation = $this->laravel->make($evaluationClass);
        } catch (Exception $e) {
            $this->error("Could not instantiate evaluation class '{$evaluationClass}': " . $e->getMessage());
            return Command::FAILURE;
        }

        $this->info("Running evaluation: " . $evaluation->name);
        $this->line("Description: " . $evaluation->description);

        $agentName = $evaluation->agentName; // Now using agentName
        if (empty($agentName)) {
            $this->error("Agent name property (\$agentName) is not set in '{$evaluationClass}'. This should be the registered alias of the agent.");
            return Command::FAILURE;
        }

        $csvPath = $evaluation->csvPath;
        $fullCsvPath = base_path($csvPath);

        if (!File::exists($fullCsvPath)) {
            $this->error("CSV file not found at: {$fullCsvPath} (defined in {$evaluationClass}::getCsvPath())");
            return Command::FAILURE;
        }

        $csvData = $this->readCsv($fullCsvPath);
        if (empty($csvData)) {
            $this->warn("No data found in CSV file: {$fullCsvPath}");
            return Command::SUCCESS;
        }

        $results = [];
        $this->info("Processing " . count($csvData) . " rows from CSV using agent '{$agentName}'...");
        $progressBar = $this->output->createProgressBar(count($csvData));
        $progressBar->start();

        foreach ($csvData as $index => $row) {
            $sessionId = Str::uuid()->toString();
            try {
                $prompt = $evaluation->preparePrompt($row);

                // Use Agent Facade to run the agent
                $llmResponse = Agent::run($agentName, $prompt, $sessionId);

                $evaluationResult = $evaluation->evaluateRow($row, $llmResponse);
                $results[] = $evaluationResult;
            } catch (InvalidArgumentException $e) {
                $this->error("Skipping row " . ($index + 1) . " due to invalid data/prompt preparation: " . $e->getMessage());
                $results[] = [
                    'row_data' => $row,
                    'llm_response' => 'SKIPPED_DUE_TO_PREPARE_PROMPT_ERROR',
                    'assertions' => [['assertion_method' => 'preparePrompt', 'status' => 'fail', 'message' => $e->getMessage()]],
                    'final_status' => 'fail',
                    'error' => $e->getMessage(),
                ];
            } catch (Exception $e) {
                $this->error("Error processing row " . ($index + 1) . " with agent '{$agentName}': " . $e->getMessage());
                $results[] = [
                    'row_data' => $row,
                    'llm_response' => 'ERROR_DURING_PROCESSING',
                    'assertions' => [],
                    'final_status' => 'error',
                    'error' => $e->getMessage(),
                ];
            }
            $progressBar->advance();
        }
        $progressBar->finish();
        $this->info("\nEvaluation processing complete.");

        if ($outputFile) {
            $this->saveResultsToCsv($results, $outputFile, $evaluation->name);
        } else {
            $this->displayResultsInConsole($results);
        }

        return Command::SUCCESS;
    }

    protected function readCsv(string $path): array
    {
        $header = null;
        $data = [];
        if (($handle = fopen($path, 'r')) !== false) {
            while (($row = fgetcsv($handle)) !== false) {
                if (!$header) {
                    $header = array_map('trim', $row); // Trim headers
                } else {
                    if (count($header) !== count($row)) {
                        $this->warn("Skipping inconsistent row (column count mismatch): " . implode(',', $row) . " (expected " . count($header) . " columns, got " . count($row) . ")");
                        continue;
                    }
                    $data[] = array_combine($header, $row);
                }
            }
            fclose($handle);
        }
        return $data;
    }

    protected function saveResultsToCsv(array $results, string $filePath, string $evaluationName)
    {
        if (empty($results)) {
            $this->info("No results to save.");
            return;
        }
        try {
            // Always use storage path to ensure we have write permissions
            // Extract just the filename and any subdirectories from the original path
            $pathInfo = pathinfo($filePath);
            $filename = $pathInfo['basename'];

            // If the path includes subdirectories after the last slash, preserve them
            if (str_contains($filePath, '/')) {
                $relativePath = basename(dirname($filePath)) . '/' . $filename;
            } else {
                $relativePath = $filename;
            }

            $filePath = storage_path('app/evaluations/' . $relativePath);
            $outputDir = dirname($filePath);


            // Ensure the directory exists, create it recursively if it doesn't
            if (!File::isDirectory($outputDir)) {
                $this->info("Directory does not exist, creating: {$outputDir}");

                if (!File::makeDirectory($outputDir, 0755, true, true)) {
                    // Fallback to native PHP mkdir
                    if (!mkdir($outputDir, 0755, true)) {
                        throw new Exception("Failed to create directory: {$outputDir}. Please check permissions.");
                    }
                }

            }

            // Ensure we can write to the directory
            if (!is_writable($outputDir)) {
                throw new Exception("Directory is not writable: {$outputDir}. Current permissions: " . substr(sprintf('%o', fileperms($outputDir)), -4));
            }

            $csv = Writer::createFromPath($filePath, 'w+');
            $headers = ['Evaluation Name', 'Row Index', 'Final Status', 'LLM Response', 'Error'];

            /** @var array<string> $sampleRowDataKeys */
            $sampleRowDataKeys = [];
            if (!empty($results) && isset($results[0]['row_data']) && is_array($results[0]['row_data'])) {
                $sampleRowDataKeys = array_keys($results[0]['row_data']);
            }

            foreach($sampleRowDataKeys as $key) {
                $headers[] = 'Row Data: ' . $key;
            }
            $headers[] = 'Assertions (JSON)';

            $csv->insertOne($headers);

            foreach ($results as $index => $result) {
                $rowDataValues = [];
                foreach($sampleRowDataKeys as $key) {
                    $rowDataValues[] = $result['row_data'][$key] ?? '';
                }

                $row = [
                    $evaluationName,
                    $index + 1,
                    $result['final_status'] ?? 'N/A',
                    Str::limit($result['llm_response'] ?? 'N/A', 32000), // Limit response length for CSV cell compatibility
                    $result['error'] ?? '',
                ];
                $row = array_merge($row, $rowDataValues);
                $row[] = json_encode($result['assertions'] ?? []);

                try {
                    $csv->insertOne($row);
                } catch (CannotInsertRecord $e) {
                    $this->error("Failed to write row to CSV: " . $e->getMessage() . " - Data: " . json_encode($row));
                    // Optionally, add the problematic row to a separate error log or skip
                }
            }
            $this->info("Results saved to: {$filePath}");
        } catch (Exception $e) { // Catch broader exceptions for file writing
            $this->error("Failed to save results to CSV file '{$filePath}': " . $e->getMessage());
        }
    }

    protected function displayResultsInConsole(array $results)
    {
        if (empty($results)) {
            $this->info("No results to display.");
            return;
        }
        $this->table(
            ['Row', 'Final Status', 'LLM Response Summary', 'Assertions Count', 'Error'],
            array_map(function ($result, $index) {
                return [
                    $index + 1,
                    $result['final_status'] ?? 'N/A',
                    Str::limit($result['llm_response'] ?? 'N/A', 50),
                    count($result['assertions'] ?? []),
                    $result['error'] ?? '',
                ];
            }, $results, array_keys($results))
        );

        $summary = ['pass' => 0, 'fail' => 0, 'error' => 0, 'total' => count($results)];
        foreach ($results as $result) {
            $status = $result['final_status'] ?? 'error'; // Default to 'error' if status is missing
            if (array_key_exists($status, $summary)) {
                $summary[$status]++;
            } else {
                $this->warn("Unknown status '{$status}' found in results for row.");
                $summary['error']++; // Count unknown statuses as errors
            }
        }
        $this->info(sprintf(
            "Summary: Total Rows: %d, Passed: %d, Failed: %d, Errors: %d",
            $summary['total'],
            $summary['pass'],
            $summary['fail'],
            $summary['error']
        ));
    }
}
