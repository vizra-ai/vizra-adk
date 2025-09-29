<?php

namespace Vizra\VizraADK\Livewire;

use Exception;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use League\Csv\Writer;
use Livewire\Component;
use Livewire\WithFileUploads;
use Vizra\VizraADK\Evaluations\BaseEvaluation;
use Vizra\VizraADK\Facades\Agent;
use Vizra\VizraADK\Services\AgentRegistry;
use Vizra\VizraADK\Traits\HasLogging;

class EvalRunner extends Component
{
    use WithFileUploads, HasLogging;

    // Evaluation discovery and selection
    public array $availableEvaluations = [];

    public ?string $selectedEvaluation = null;

    // Private property that won't be serialized by Livewire
    private ?BaseEvaluation $evaluationInstance = null;

    // UI state
    public bool $isRunning = false;

    public string $currentStatus = '';

    public int $progress = 0;

    public int $totalRows = 0;

    public array $results = [];

    public bool $showResults = false;

    // Real-time processing state
    public array $csvData = [];

    public int $currentRowIndex = 0;

    public int $passCount = 0;

    public int $failCount = 0;

    // Results display
    public array $resultSummary = [];

    public array $detailedResults = [];

    public string $outputPath = '';

    // Expandable row details
    public array $expandedRows = [];

    // CSV upload for custom evaluations
    public $csvFile;

    public bool $showCsvUpload = false;

    public function mount()
    {
        $this->discoverEvaluations();

        // Debug: Log the number of discovered evaluations
        $this->logInfo('EvalRunner: Discovered '.count($this->availableEvaluations).' evaluations', [], 'agents');
    }

    public function refreshEvaluations()
    {
        $this->discoverEvaluations();
        session()->flash('message', 'Refreshed evaluations. Found: '.count($this->availableEvaluations));
    }

    /**
     * Discover all available evaluation classes
     */
    public function discoverEvaluations()
    {
        $this->availableEvaluations = [];
        $counter = 0;

        // Search in app namespace
        $appEvaluationPath = app_path('Evaluations');
        if (File::exists($appEvaluationPath)) {
            $files = File::allFiles($appEvaluationPath);
            foreach ($files as $file) {
                if ($file->getExtension() === 'php') {
                    $className = pathinfo($file->getFilename(), PATHINFO_FILENAME);
                    $fullClassName = "App\\Evaluations\\{$className}";

                    if (class_exists($fullClassName) && is_subclass_of($fullClassName, BaseEvaluation::class)) {
                        try {
                            // Create temporary instance to get properties, then discard it
                            $tempInstance = new $fullClassName;

                            // Use simple array structure with string keys and values
                            $this->availableEvaluations[] = [
                                'key' => (string) $counter,
                                'class' => (string) $fullClassName,
                                'name' => (string) ($tempInstance->name ?? $className),
                                'description' => (string) ($tempInstance->description ?? 'No description available'),
                                'agent_name' => (string) ($tempInstance->agentName ?? 'Unknown'),
                                'csv_path' => (string) ($tempInstance->csvPath ?? ''),
                            ];

                            $counter++;
                            // Explicitly unset the temporary instance
                            unset($tempInstance);
                        } catch (Exception $e) {
                            // Skip evaluations that can't be instantiated
                        }
                    }
                }
            }
        }

        // Search in package namespace
        $packageEvaluationPath = __DIR__.'/../Evaluations';
        if (File::exists($packageEvaluationPath)) {
            $files = File::allFiles($packageEvaluationPath);
            foreach ($files as $file) {
                if ($file->getExtension() === 'php') {
                    $className = pathinfo($file->getFilename(), PATHINFO_FILENAME);
                    $fullClassName = "Vizra\\VizraADK\\Evaluations\\{$className}";

                    if (class_exists($fullClassName) && is_subclass_of($fullClassName, BaseEvaluation::class)) {
                        try {
                            // Create temporary instance to get properties, then discard it
                            $tempInstance = new $fullClassName;

                            // Use simple array structure with string keys and values
                            $this->availableEvaluations[] = [
                                'key' => (string) $counter,
                                'class' => (string) $fullClassName,
                                'name' => (string) ($tempInstance->name ?? $className),
                                'description' => (string) ($tempInstance->description ?? 'No description available'),
                                'agent_name' => (string) ($tempInstance->agentName ?? 'Unknown'),
                                'csv_path' => (string) ($tempInstance->csvPath ?? ''),
                            ];

                            $counter++;
                            // Explicitly unset the temporary instance
                            unset($tempInstance);
                        } catch (Exception $e) {
                            // Skip evaluations that can't be instantiated
                        }
                    }
                }
            }
        }
    }

    /**
     * Select an evaluation to run
     */
    public function selectEvaluation($evaluationKey)
    {
        // Find evaluation by key since we're using sequential array
        $evaluation = null;
        foreach ($this->availableEvaluations as $eval) {
            if ($eval['key'] === (string) $evaluationKey) {
                $evaluation = $eval;
                break;
            }
        }

        if (! $evaluation) {
            $this->currentStatus = 'Evaluation not found - Key: '.$evaluationKey;

            return;
        }

        $this->selectedEvaluation = $evaluation['class'];

        try {
            $this->evaluationInstance = app($evaluation['class']);
            $this->resetResults();
            $this->currentStatus = 'Evaluation selected: '.$evaluation['name'].' (Class: '.$evaluation['class'].')';
            
            // Verify the CSV file exists
            if ($this->evaluationInstance && !empty($this->evaluationInstance->csvPath)) {
                $csvPath = base_path($this->evaluationInstance->csvPath);
                if (!File::exists($csvPath)) {
                    $this->currentStatus = 'Warning: CSV file not found at: ' . $csvPath;
                } else {
                    $testCount = count(file($csvPath)) - 1; // Subtract header row
                    $this->currentStatus = "Ready to run {$testCount} test cases";
                }
            }
        } catch (Exception $e) {
            $this->currentStatus = 'Error loading evaluation: '.$e->getMessage();
            $this->selectedEvaluation = null;
            $this->evaluationInstance = null;
        }
    }

    /**
     * Run the selected evaluation
     */
    public function runEvaluation()
    {
        if (! $this->hasValidEvaluation()) {
            $instanceStatus = $this->evaluationInstance ? 'OK' : 'NULL';
            $selectedStatus = $this->selectedEvaluation ? $this->selectedEvaluation : 'NULL';
            
            // Try to recreate the instance and provide detailed error info
            if ($this->selectedEvaluation) {
                try {
                    $this->evaluationInstance = app($this->selectedEvaluation);
                    $this->currentStatus = "Recreated evaluation instance successfully for: {$this->selectedEvaluation}";
                } catch (Exception $e) {
                    $this->currentStatus = "Error recreating evaluation instance: " . $e->getMessage() . " (Class: {$this->selectedEvaluation})";
                    return;
                }
            } else {
                $this->currentStatus = "No evaluation selected. Instance: {$instanceStatus}, Selected: {$selectedStatus}";
                return;
            }
        }

        $this->isRunning = true;
        $this->currentStatus = 'Loading CSV data...';
        $this->progress = 0;
        $this->results = [];
        $this->currentRowIndex = 0;
        $this->passCount = 0;
        $this->failCount = 0;

        try {
            // Get CSV data
            $csvPath = base_path($this->evaluationInstance->csvPath);

            if (! File::exists($csvPath)) {
                throw new Exception("CSV file not found at: {$csvPath}");
            }

            $this->csvData = $this->readCsv($csvPath);

            if (empty($this->csvData)) {
                throw new Exception('No data found in CSV file');
            }

            $this->totalRows = count($this->csvData);
            $this->currentStatus = "Starting evaluation of {$this->totalRows} rows...";

            // Start processing the first row
            $this->processNextRow();

        } catch (Exception $e) {
            $this->currentStatus = 'Error: '.$e->getMessage();
            $this->isRunning = false;
        }
    }

    public function processNextRow()
    {
        if ($this->currentRowIndex >= count($this->csvData)) {
            // All rows processed, finalize
            $this->finalizeEvaluation();

            return;
        }

        // Ensure evaluation instance exists (recreate if needed due to Livewire serialization)
        if (! $this->evaluationInstance && $this->selectedEvaluation) {
            try {
                $this->evaluationInstance = app($this->selectedEvaluation);
            } catch (Exception $e) {
                $this->currentStatus = 'Error recreating evaluation instance: '.$e->getMessage();
                $this->isRunning = false;

                return;
            }
        }

        if (! $this->evaluationInstance) {
            $this->currentStatus = 'Error: No evaluation instance available';
            $this->isRunning = false;

            return;
        }

        $row = $this->csvData[$this->currentRowIndex];
        $rowNumber = $this->currentRowIndex + 1;

        $this->currentStatus = "Processing row {$rowNumber} of {$this->totalRows}...";
        $this->progress = (int) (($this->currentRowIndex / $this->totalRows) * 100);

        $sessionId = Str::uuid()->toString();

        try {
            $prompt = $this->evaluationInstance->preparePrompt($row);
            $llmResponse = Agent::run($this->evaluationInstance->agentName, $prompt, $sessionId);
            $evaluationResult = $this->evaluationInstance->evaluateRow($row, $llmResponse);

            // Determine if this row passed
            $rowPassed = ($evaluationResult['final_status'] ?? 'fail') === 'pass';
            if ($rowPassed) {
                $this->passCount++;
            } else {
                $this->failCount++;
            }

            // Add result to results array
            $this->results[] = [
                'row_index' => $rowNumber,
                'row_data' => $row,
                'llm_response' => $llmResponse,
                'evaluation_result' => $evaluationResult,
                'passed' => $rowPassed,
            ];

        } catch (Exception $e) {
            $this->failCount++;
            $this->results[] = [
                'row_index' => $rowNumber,
                'row_data' => $row,
                'llm_response' => 'ERROR',
                'evaluation_result' => ['error' => $e->getMessage()],
                'passed' => false,
            ];
        }

        // Move to next row
        $this->currentRowIndex++;

        // Emit progress update
        $this->dispatch('evaluation-progress', [
            'progress' => $this->progress,
            'current_row' => $rowNumber,
            'total_rows' => $this->totalRows,
            'passed' => $this->passCount,
            'failed' => $this->failCount,
        ]);

        // Continue with next row after a small delay for UI updates
        $this->dispatch('process-next-row');
    }

    public function finalizeEvaluation()
    {
        // Store final results
        $this->resultSummary = [
            'total_rows' => $this->totalRows,
            'passed' => $this->passCount,
            'failed' => $this->failCount,
            'pass_rate' => $this->totalRows > 0 ? round(($this->passCount / $this->totalRows) * 100, 2) : 0,
        ];

        // Save results to file
        $this->saveResults();

        $this->showResults = true;
        $this->currentStatus = "Evaluation completed! Pass rate: {$this->resultSummary['pass_rate']}%";
        $this->progress = 100;
        $this->isRunning = false;

        $this->dispatch('evaluation-completed', [
            'summary' => $this->resultSummary,
        ]);
    }

    /**
     * Read CSV file and return data array
     */
    private function readCsv(string $filePath): array
    {
        $data = [];

        if (($handle = fopen($filePath, 'r')) !== false) {
            $header = fgetcsv($handle);

            if ($header) {
                while (($row = fgetcsv($handle)) !== false) {
                    if (count($row) === count($header)) {
                        $data[] = array_combine($header, $row);
                    }
                }
            }
            fclose($handle);
        }

        return $data;
    }

    /**
     * Save evaluation results to CSV file
     */
    private function saveResults()
    {
        if (empty($this->results)) {
            return;
        }

        $timestamp = now()->format('Y-m-d_H-i-s');
        $evaluationName = str_replace('\\', '_', $this->selectedEvaluation);
        $filename = "evaluation_results_{$evaluationName}_{$timestamp}.csv";

        $outputDir = storage_path('app/evaluations');
        if (! File::isDirectory($outputDir)) {
            File::makeDirectory($outputDir, 0755, true);
        }

        $this->outputPath = $outputDir.'/'.$filename;

        $csv = Writer::createFromPath($this->outputPath, 'w+');

        // Headers
        $headers = [
            'Row Index',
            'Final Status',
            'LLM Response',
            'Error',
            'Pass Rate',
        ];

        // Add CSV row data headers
        if (! empty($this->results[0]['row_data'])) {
            foreach (array_keys($this->results[0]['row_data']) as $key) {
                $headers[] = "CSV: {$key}";
            }
        }

        // Add assertion details headers
        $headers[] = 'Assertion Results';

        $csv->insertOne($headers);

        // Data rows
        foreach ($this->results as $result) {
            $row = [
                $result['row_index'],
                $result['passed'] ? 'PASS' : 'FAIL',
                substr($result['llm_response'], 0, 100).(strlen($result['llm_response']) > 100 ? '...' : ''),
                $result['evaluation_result']['error'] ?? '',
                $this->resultSummary['pass_rate'].'%',
            ];

            // Add CSV row data
            foreach ($result['row_data'] as $value) {
                $row[] = $value;
            }

            // Add assertion results
            $assertions = $result['evaluation_result']['details'] ?? $result['evaluation_result']['assertions'] ?? [];
            $assertionSummary = '';
            if (is_array($assertions)) {
                $assertionSummary = count($assertions).' assertions';
            }
            $row[] = $assertionSummary;

            $csv->insertOne($row);
        }
    }

    /**
     * Toggle expanded view for a specific result row
     */
    public function toggleRowExpansion($rowIndex)
    {
        if (in_array($rowIndex, $this->expandedRows)) {
            $this->expandedRows = array_diff($this->expandedRows, [$rowIndex]);
        } else {
            $this->expandedRows[] = $rowIndex;
        }
    }

    /**
     * Reset all results and status
     */
    public function resetResults()
    {
        $this->results = [];
        $this->resultSummary = [];
        $this->showResults = false;
        $this->currentStatus = '';
        $this->progress = 0;
        $this->totalRows = 0;
        $this->outputPath = '';
        $this->expandedRows = [];
    }

    /**
     * Download results file
     */
    public function downloadResults()
    {
        if (File::exists($this->outputPath)) {
            return response()->download($this->outputPath);
        }
    }

    /**
     * Get available registered agents
     */
    public function getRegisteredAgents(): array
    {
        $registry = app(AgentRegistry::class);

        return $registry->getAllRegisteredAgents();
    }

    /**
     * Get the current evaluation instance
     */
    private function getEvaluationInstance(): ?BaseEvaluation
    {
        return $this->evaluationInstance;
    }

    /**
     * Check if an evaluation is currently selected and ready
     */
    private function hasValidEvaluation(): bool
    {
        if ($this->selectedEvaluation === null) {
            return false;
        }

        // Try to recreate the instance if it's null (Livewire serialization issue)
        if ($this->evaluationInstance === null && $this->selectedEvaluation) {
            try {
                $this->evaluationInstance = app($this->selectedEvaluation);
            } catch (Exception $e) {
                // Log the error for debugging
                $this->logError('Failed to recreate evaluation instance', [
                    'class' => $this->selectedEvaluation,
                    'error' => $e->getMessage()
                ], 'agents');
                return false;
            }
        }

        return $this->evaluationInstance !== null && $this->selectedEvaluation !== null;
    }

    public function render()
    {
        return view('vizra-adk::livewire.eval-runner')
            ->layout('vizra-adk::layouts.app', [
                'title' => 'Evaluation Runner',
            ]);
    }
}
