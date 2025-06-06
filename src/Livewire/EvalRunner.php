<?php

namespace AaronLumsden\LaravelAgentADK\Livewire;

use Livewire\Component;
use Livewire\WithFileUploads;
use AaronLumsden\LaravelAgentADK\Evaluations\BaseEvaluation;
use AaronLumsden\LaravelAgentADK\Services\AgentRegistry;
use AaronLumsden\LaravelAgentADK\Facades\Agent;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Exception;
use League\Csv\Writer;
use League\Csv\Reader;

class EvalRunner extends Component
{
    use WithFileUploads;

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

    // Results display
    public array $resultSummary = [];
    public array $detailedResults = [];
    public string $outputPath = '';

    // CSV upload for custom evaluations
    public $csvFile;
    public bool $showCsvUpload = false;

    public function mount()
    {
        $this->discoverEvaluations();
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
                            $tempInstance = new $fullClassName();

                            // Use simple array structure with string keys and values
                            $this->availableEvaluations[] = [
                                'key' => (string)$counter,
                                'class' => (string)$fullClassName,
                                'name' => (string)($tempInstance->name ?? $className),
                                'description' => (string)($tempInstance->description ?? 'No description available'),
                                'agent_name' => (string)($tempInstance->agentName ?? 'Unknown'),
                                'csv_path' => (string)($tempInstance->csvPath ?? ''),
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
        $packageEvaluationPath = __DIR__ . '/../Evaluations';
        if (File::exists($packageEvaluationPath)) {
            $files = File::allFiles($packageEvaluationPath);
            foreach ($files as $file) {
                if ($file->getExtension() === 'php') {
                    $className = pathinfo($file->getFilename(), PATHINFO_FILENAME);
                    $fullClassName = "AaronLumsden\\LaravelAgentADK\\Evaluations\\{$className}";

                    if (class_exists($fullClassName) && is_subclass_of($fullClassName, BaseEvaluation::class)) {
                        try {
                            // Create temporary instance to get properties, then discard it
                            $tempInstance = new $fullClassName();

                            // Use simple array structure with string keys and values
                            $this->availableEvaluations[] = [
                                'key' => (string)$counter,
                                'class' => (string)$fullClassName,
                                'name' => (string)($tempInstance->name ?? $className),
                                'description' => (string)($tempInstance->description ?? 'No description available'),
                                'agent_name' => (string)($tempInstance->agentName ?? 'Unknown'),
                                'csv_path' => (string)($tempInstance->csvPath ?? ''),
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
            if ($eval['key'] === (string)$evaluationKey) {
                $evaluation = $eval;
                break;
            }
        }

        if (!$evaluation) {
            $this->currentStatus = "Evaluation not found";
            return;
        }

        $this->selectedEvaluation = $evaluation['class'];

        try {
            $this->evaluationInstance = app($evaluation['class']);
            $this->resetResults();
            $this->currentStatus = "Evaluation selected: " . $evaluation['name'];
        } catch (Exception $e) {
            $this->currentStatus = "Error loading evaluation: " . $e->getMessage();
        }
    }

    /**
     * Run the selected evaluation
     */
    public function runEvaluation()
    {
        if (!$this->hasValidEvaluation()) {
            $this->currentStatus = "No evaluation selected";
            return;
        }

        $this->isRunning = true;
        $this->currentStatus = "Loading CSV data...";
        $this->progress = 0;
        $this->results = [];

        try {
            // Get CSV data
            $csvPath = base_path($this->evaluationInstance->csvPath);

            if (!File::exists($csvPath)) {
                throw new Exception("CSV file not found at: {$csvPath}");
            }

            $csvData = $this->readCsv($csvPath);

            if (empty($csvData)) {
                throw new Exception("No data found in CSV file");
            }

            $this->totalRows = count($csvData);
            $this->currentStatus = "Processing {$this->totalRows} rows...";

            $results = [];
            $passCount = 0;
            $failCount = 0;

            foreach ($csvData as $index => $row) {
                $this->currentStatus = "Processing row " . ($index + 1) . " of {$this->totalRows}";
                $this->progress = (int)(($index / $this->totalRows) * 100);

                // Emit progress update
                $this->dispatch('evaluation-progress', [
                    'progress' => $this->progress,
                    'status' => $this->currentStatus
                ]);

                $sessionId = Str::uuid()->toString();

                try {
                    $prompt = $this->evaluationInstance->preparePrompt($row);
                    $llmResponse = Agent::run($this->evaluationInstance->agentName, $prompt, $sessionId);

                    $evaluationResult = $this->evaluationInstance->evaluateRow($row, $llmResponse);

                    // Determine if this row passed
                    $rowPassed = $evaluationResult['final_status'] ?? ($evaluationResult['passed'] ?? true);
                    if ($rowPassed) {
                        $passCount++;
                    } else {
                        $failCount++;
                    }

                    $results[] = [
                        'row_index' => $index + 1,
                        'row_data' => $row,
                        'llm_response' => $llmResponse,
                        'evaluation_result' => $evaluationResult,
                        'passed' => $rowPassed,
                    ];

                } catch (Exception $e) {
                    $failCount++;
                    $results[] = [
                        'row_index' => $index + 1,
                        'row_data' => $row,
                        'llm_response' => 'ERROR',
                        'evaluation_result' => ['error' => $e->getMessage()],
                        'passed' => false,
                    ];
                }
            }

            // Store results
            $this->results = $results;
            $this->resultSummary = [
                'total_rows' => $this->totalRows,
                'passed' => $passCount,
                'failed' => $failCount,
                'pass_rate' => $this->totalRows > 0 ? round(($passCount / $this->totalRows) * 100, 2) : 0,
            ];

            // Save results to file
            $this->saveResults();

            $this->showResults = true;
            $this->currentStatus = "Evaluation completed! Pass rate: {$this->resultSummary['pass_rate']}%";
            $this->progress = 100;

        } catch (Exception $e) {
            $this->currentStatus = "Error: " . $e->getMessage();
        } finally {
            $this->isRunning = false;
        }
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
        if (!File::isDirectory($outputDir)) {
            File::makeDirectory($outputDir, 0755, true);
        }

        $this->outputPath = $outputDir . '/' . $filename;

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
        if (!empty($this->results[0]['row_data'])) {
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
                substr($result['llm_response'], 0, 100) . (strlen($result['llm_response']) > 100 ? '...' : ''),
                $result['evaluation_result']['error'] ?? '',
                $this->resultSummary['pass_rate'] . '%',
            ];

            // Add CSV row data
            foreach ($result['row_data'] as $value) {
                $row[] = $value;
            }

            // Add assertion results
            $assertions = $result['evaluation_result']['details'] ?? $result['evaluation_result']['assertions'] ?? [];
            $assertionSummary = '';
            if (is_array($assertions)) {
                $assertionSummary = count($assertions) . ' assertions';
            }
            $row[] = $assertionSummary;

            $csv->insertOne($row);
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
        return $this->evaluationInstance !== null && $this->selectedEvaluation !== null;
    }

    public function render()
    {
        return view('agent-adk::livewire.eval-runner');
    }
}
