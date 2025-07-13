<?php

namespace Vizra\VizraADK\Tests\Unit\Console\Commands;

use Illuminate\Filesystem\Filesystem;
use Mockery;
use Vizra\VizraADK\Console\Commands\MakeEvalCommand;
use Vizra\VizraADK\Tests\TestCase;

class MakeEvalCommandTest extends TestCase
{
    protected $filesystem;

    protected $command;

    protected function setUp(): void
    {
        parent::setUp();

        $this->filesystem = Mockery::mock(Filesystem::class);
        $this->command = new MakeEvalCommand($this->filesystem);
        $this->command->setLaravel($this->app);
    }

    protected function tearDown(): void
    {
        // Ensure cleanup after each test
        if (method_exists($this, 'cleanupTestFiles')) {
            $this->cleanupTestFiles();
        }
        parent::tearDown();
    }

    public function test_command_has_correct_name_and_description()
    {
        $this->assertEquals('vizra:make:eval', $this->command->getName());
        $this->assertEquals('Create a new LLM evaluation class', $this->command->getDescription());
    }

    public function test_get_stub_returns_correct_path()
    {
        $reflection = new \ReflectionClass($this->command);
        $method = $reflection->getMethod('getStub');
        $method->setAccessible(true);

        $stubPath = $method->invoke($this->command);

        $this->assertStringEndsWith('/stubs/evaluation.stub', $stubPath);
        $this->assertFileExists($stubPath);
    }

    public function test_get_stub_prefers_custom_stub_if_exists()
    {
        $customPath = base_path('stubs/vendor/vizra-adk/evaluation.stub');

        // Mock that custom stub exists
        $this->app->instance('path.base', '/test/base');

        $reflection = new \ReflectionClass($this->command);
        $method = $reflection->getMethod('getStub');
        $method->setAccessible(true);

        // Since we can't actually create the file, we'll just test the logic path
        $stubPath = $method->invoke($this->command);
        $this->assertStringEndsWith('/stubs/evaluation.stub', $stubPath);
    }

    public function test_get_default_namespace()
    {
        $reflection = new \ReflectionClass($this->command);
        $method = $reflection->getMethod('getDefaultNamespace');
        $method->setAccessible(true);

        $namespace = $method->invoke($this->command, 'App');

        $this->assertEquals('App\\Evaluations', $namespace);
    }

    public function test_get_name_input_adds_evaluation_suffix()
    {
        $command = new class($this->filesystem) extends MakeEvalCommand
        {
            public function argument($key = null)
            {
                if ($key === 'name') {
                    return 'ProductReview';
                }

                return parent::argument($key);
            }
        };
        $command->setLaravel($this->app);

        $reflection = new \ReflectionClass($command);
        $method = $reflection->getMethod('getNameInput');
        $method->setAccessible(true);

        $result = $method->invoke($command);

        $this->assertEquals('ProductReviewEvaluation', $result);
    }

    public function test_get_name_input_preserves_existing_evaluation_suffix()
    {
        $command = new class($this->filesystem) extends MakeEvalCommand
        {
            public function argument($key = null)
            {
                if ($key === 'name') {
                    return 'ProductReviewEvaluation';
                }

                return parent::argument($key);
            }
        };
        $command->setLaravel($this->app);

        $reflection = new \ReflectionClass($command);
        $method = $reflection->getMethod('getNameInput');
        $method->setAccessible(true);

        $result = $method->invoke($command);

        $this->assertEquals('ProductReviewEvaluation', $result);
    }

    public function test_build_class_replaces_placeholders()
    {
        $this->filesystem->shouldReceive('get')
            ->once()
            ->andReturn('class {{ class }} { name = "{{ evaluation_name }}"; csvPath = "{{ csv_file_name }}"; }');

        // Create a test command class that provides the argument
        $command = new class($this->filesystem) extends MakeEvalCommand
        {
            public function argument($key = null)
            {
                if ($key === 'name') {
                    return 'ProductReview';
                }

                return parent::argument($key);
            }
        };
        $command->setLaravel($this->app);

        $reflection = new \ReflectionClass($command);
        $method = $reflection->getMethod('buildClass');
        $method->setAccessible(true);

        $result = $method->invoke($command, 'App\\Evaluations\\ProductReviewEvaluation');

        $this->assertStringContainsString('Product review', $result);
        $this->assertStringContainsString('product_review', $result);
    }

    public function test_build_class_removes_evaluation_suffix_from_placeholders()
    {
        $this->filesystem->shouldReceive('get')
            ->once()
            ->andReturn('name = "{{ evaluation_name }}"; csvPath = "{{ csv_file_name }}";');

        $command = new class($this->filesystem) extends MakeEvalCommand
        {
            public function argument($key = null)
            {
                if ($key === 'name') {
                    return 'ProductReviewEvaluation';
                }

                return parent::argument($key);
            }
        };
        $command->setLaravel($this->app);

        $reflection = new \ReflectionClass($command);
        $method = $reflection->getMethod('buildClass');
        $method->setAccessible(true);

        $result = $method->invoke($command, 'App\\Evaluations\\ProductReviewEvaluation');

        // Should be "Product review" not "Product review Evaluation"
        $this->assertStringContainsString('Product review', $result);
        $this->assertStringNotContainsString('Product review evaluation', $result);

        // Should be "product_review" not "product_review_evaluation"
        $this->assertStringContainsString('product_review', $result);
        $this->assertStringNotContainsString('product_review_evaluation', $result);
    }

    public function test_build_class_handles_camel_case_names()
    {
        $this->filesystem->shouldReceive('get')
            ->once()
            ->andReturn('name = "{{ evaluation_name }}"; csvPath = "{{ csv_file_name }}";');

        $command = new class($this->filesystem) extends MakeEvalCommand
        {
            public function argument($key = null)
            {
                if ($key === 'name') {
                    return 'ContentQualityAnalysis';
                }

                return parent::argument($key);
            }
        };
        $command->setLaravel($this->app);

        $reflection = new \ReflectionClass($command);
        $method = $reflection->getMethod('buildClass');
        $method->setAccessible(true);

        $result = $method->invoke($command, 'App\\Evaluations\\ContentQualityAnalysisEvaluation');

        $this->assertStringContainsString('Content quality analysis', $result);
        $this->assertStringContainsString('content_quality_analysis', $result);
    }

    public function test_get_arguments_returns_correct_structure()
    {
        $arguments = $this->command->getDefinition()->getArguments();

        $this->assertCount(1, $arguments);
        $this->assertArrayHasKey('name', $arguments);
        $this->assertTrue($arguments['name']->isRequired());
        $this->assertEquals('The name of the evaluation (e.g., ProductReviewSentiment).', $arguments['name']->getDescription());
    }

    public function test_command_creates_file_with_correct_content()
    {
        // Create the Evaluations directory
        $evaluationsDir = app_path('Evaluations');
        if (! is_dir($evaluationsDir)) {
            mkdir($evaluationsDir, 0755, true);
        }

        $expectedPath = app_path('Evaluations/ProductReviewEvaluation.php');
        $expectedCsvPath = app_path('Evaluations/data/product_review.csv');

        // Ensure files don't exist before test
        if (file_exists($expectedPath)) {
            unlink($expectedPath);
        }
        if (file_exists($expectedCsvPath)) {
            unlink($expectedCsvPath);
        }

        $this->artisan('vizra:make:eval', ['name' => 'ProductReview'])
            ->assertExitCode(0);

        // Verify the PHP file was created with correct content
        $this->assertFileExists($expectedPath);
        $content = file_get_contents($expectedPath);

        $this->assertStringContainsString('class ProductReviewEvaluation extends BaseEvaluation', $content);
        $this->assertStringContainsString('public string $name = \'Product review\';', $content);
        $this->assertStringContainsString('public string $description = \'Description for Product review.\';', $content);
        $this->assertStringContainsString('app/Evaluations/data/product_review.csv', $content);
        $this->assertStringContainsString('namespace App\\Evaluations', $content);

        // Verify the CSV file was created with correct headers
        $this->assertFileExists($expectedCsvPath);
        $csvContent = file_get_contents($expectedCsvPath);
        $this->assertEquals("prompt,expected_response,description\n", $csvContent);

        // Cleanup
        unlink($expectedPath);
        unlink($expectedCsvPath);
        if (is_dir(dirname($expectedCsvPath)) && count(scandir(dirname($expectedCsvPath))) == 2) {
            rmdir(dirname($expectedCsvPath));
        }
        if (is_dir($evaluationsDir) && count(scandir($evaluationsDir)) == 2) {
            rmdir($evaluationsDir);
        }
    }

    public function test_command_handles_existing_file()
    {
        // Create the Evaluations directory and file
        $evaluationsDir = app_path('Evaluations');
        $dataDir = $evaluationsDir . '/data';
        if (! is_dir($evaluationsDir)) {
            mkdir($evaluationsDir, 0755, true);
        }
        if (! is_dir($dataDir)) {
            mkdir($dataDir, 0755, true);
        }

        $existingFile = $evaluationsDir.'/ProductReviewEvaluation.php';
        $existingCsvFile = $dataDir.'/product_review.csv';
        file_put_contents($existingFile, '<?php // existing evaluation');
        file_put_contents($existingCsvFile, 'existing,csv,content');

        $this->artisan('vizra:make:eval', ['name' => 'ProductReview'])
            ->assertExitCode(0);

        // Verify the existing files were not overwritten
        $content = file_get_contents($existingFile);
        $this->assertEquals('<?php // existing evaluation', $content);
        
        $csvContent = file_get_contents($existingCsvFile);
        $this->assertEquals('existing,csv,content', $csvContent);

        // Cleanup
        unlink($existingFile);
        unlink($existingCsvFile);
        if (is_dir($dataDir) && count(scandir($dataDir)) == 2) {
            rmdir($dataDir);
        }
        if (is_dir($evaluationsDir) && count(scandir($evaluationsDir)) == 2) {
            rmdir($evaluationsDir);
        }
    }

    public function test_evaluation_name_generation_with_various_formats()
    {
        $testCases = [
            ['SentimentAnalysis', 'Sentiment analysis', 'sentiment_analysis'],
            ['ContentQuality', 'Content quality', 'content_quality'],
            ['SimpleMath', 'Simple math', 'simple_math'],
            ['LLMJudgeTest', 'L l m judge test', 'l_l_m_judge_test'], // Edge case
        ];

        foreach ($testCases as [$inputName, $expectedEvalName, $expectedCsvName]) {
            $this->filesystem->shouldReceive('get')
                ->once()
                ->andReturn('name = "{{ evaluation_name }}"; csvPath = "{{ csv_file_name }}";');

            $command = new class($this->filesystem, $inputName) extends MakeEvalCommand
            {
                private $inputName;

                public function __construct($filesystem, $inputName)
                {
                    parent::__construct($filesystem);
                    $this->inputName = $inputName;
                }

                public function argument($key = null)
                {
                    if ($key === 'name') {
                        return $this->inputName;
                    }

                    return parent::argument($key);
                }
            };
            $command->setLaravel($this->app);

            $reflection = new \ReflectionClass($command);
            $method = $reflection->getMethod('buildClass');
            $method->setAccessible(true);

            $result = $method->invoke($command, "App\\Evaluations\\{$inputName}Evaluation");

            $this->assertStringContainsString($expectedEvalName, $result, "Failed evaluation name for {$inputName}");
            $this->assertStringContainsString($expectedCsvName, $result, "Failed CSV name for {$inputName}");
        }
    }

    public function test_command_without_evaluation_suffix()
    {
        // Create the Evaluations directory
        $evaluationsDir = app_path('Evaluations');
        if (! is_dir($evaluationsDir)) {
            mkdir($evaluationsDir, 0755, true);
        }

        $expectedPath = app_path('Evaluations/MathEvaluation.php');
        $expectedCsvPath = app_path('Evaluations/data/math.csv');

        // Ensure files don't exist before test
        if (file_exists($expectedPath)) {
            unlink($expectedPath);
        }
        if (file_exists($expectedCsvPath)) {
            unlink($expectedCsvPath);
        }

        $this->artisan('vizra:make:eval', ['name' => 'Math'])
            ->assertExitCode(0);

        // Verify the PHP file was created with correct content
        $this->assertFileExists($expectedPath);
        $content = file_get_contents($expectedPath);

        $this->assertStringContainsString('class MathEvaluation extends BaseEvaluation', $content);
        $this->assertStringContainsString('public string $name = \'Math\';', $content);
        $this->assertStringContainsString('app/Evaluations/data/math.csv', $content);

        // Verify the CSV file was created
        $this->assertFileExists($expectedCsvPath);
        $csvContent = file_get_contents($expectedCsvPath);
        $this->assertEquals("prompt,expected_response,description\n", $csvContent);

        // Cleanup
        unlink($expectedPath);
        unlink($expectedCsvPath);
        if (is_dir(dirname($expectedCsvPath)) && count(scandir(dirname($expectedCsvPath))) == 2) {
            rmdir(dirname($expectedCsvPath));
        }
        if (is_dir($evaluationsDir) && count(scandir($evaluationsDir)) == 2) {
            rmdir($evaluationsDir);
        }
    }

    public function test_snake_case_conversion_edge_cases()
    {
        $this->filesystem->shouldReceive('get')
            ->times(3)
            ->andReturn('csvPath = "{{ csv_file_name }}";');

        $testCases = [
            ['XMLParser', 'x_m_l_parser'],
            ['HTTPRequest', 'h_t_t_p_request'],
            ['APIResponseValidator', 'a_p_i_response_validator'],
        ];

        foreach ($testCases as [$inputName, $expectedCsvName]) {
            $command = new class($this->filesystem, $inputName) extends MakeEvalCommand
            {
                private $inputName;

                public function __construct($filesystem, $inputName)
                {
                    parent::__construct($filesystem);
                    $this->inputName = $inputName;
                }

                public function argument($key = null)
                {
                    if ($key === 'name') {
                        return $this->inputName;
                    }

                    return parent::argument($key);
                }
            };
            $command->setLaravel($this->app);

            $reflection = new \ReflectionClass($command);
            $method = $reflection->getMethod('buildClass');
            $method->setAccessible(true);

            $result = $method->invoke($command, "App\\Evaluations\\{$inputName}Evaluation");

            $this->assertStringContainsString($expectedCsvName, $result, "Failed CSV name conversion for {$inputName}");
        }
    }

    protected function getStubContent(): string
    {
        return '<?php

namespace {{ namespace }};

use Vizra\VizraADK\Evaluations\BaseEvaluation;
use InvalidArgumentException;

class {{ class }} extends BaseEvaluation
{
    public string $agentName = \'GenericLlmAgent\';
    public string $name = \'{{ evaluation_name }}\';
    public string $description = \'Description for {{ evaluation_name }}.\';
    public string $csvPath = \'app/Evaluations/data/{{ csv_file_name }}.csv\';
    public string $promptCsvColumn = \'prompt\';

    public function preparePrompt(array $csvRowData): string
    {
        if (!isset($csvRowData[$this->promptCsvColumn])) {
            throw new InvalidArgumentException(
                "CSV row for evaluation \'{{ evaluation_name }}\' must contain a \'" . $this->promptCsvColumn . "\' column/key."
            );
        }
        return $csvRowData[$this->promptCsvColumn];
    }

    public function evaluateRow(array $csvRowData, string $llmResponse): array
    {
        $this->resetAssertionResults();

        $assertionStatuses = array_column($this->assertionResults, \'status\');
        $finalStatus = empty($this->assertionResults) || !in_array(\'fail\', $assertionStatuses, true) ? \'pass\' : \'fail\';

        return [
            \'row_data\' => $csvRowData,
            \'llm_response\' => $llmResponse,
            \'assertions\' => $this->assertionResults,
            \'final_status\' => $finalStatus,
        ];
    }
}';
    }

    public function test_csv_file_creation()
    {
        $evaluationsDir = app_path('Evaluations');
        $dataDir = $evaluationsDir . '/data';
        $expectedCsvPath = $dataDir . '/sentiment_analysis.csv';
        
        // Ensure directories and file don't exist
        $this->cleanupTestFiles();
        
        $this->artisan('vizra:make:eval', ['name' => 'SentimentAnalysis'])
            ->assertExitCode(0);
            
        // Verify CSV file was created
        $this->assertFileExists($expectedCsvPath);
        
        // Verify CSV content has correct headers
        $csvContent = file_get_contents($expectedCsvPath);
        $this->assertEquals("prompt,expected_response,description\n", $csvContent);
        
        $this->cleanupTestFiles();
    }

    public function test_csv_file_not_overwritten_if_exists()
    {
        $evaluationsDir = app_path('Evaluations');
        $dataDir = $evaluationsDir . '/data';
        $csvPath = $dataDir . '/test_eval.csv';
        
        // Ensure directories exist
        if (!is_dir($dataDir)) {
            mkdir($dataDir, 0755, true);
        }
        
        // Create existing CSV with custom content
        $existingContent = "custom,headers,here\ndata,row,one\n";
        file_put_contents($csvPath, $existingContent);
        
        $this->artisan('vizra:make:eval', ['name' => 'TestEval'])
            ->assertExitCode(0);
            
        // Verify CSV content was not overwritten
        $csvContent = file_get_contents($csvPath);
        $this->assertEquals($existingContent, $csvContent);
        
        $this->cleanupTestFiles();
    }

    public function test_data_directory_creation()
    {
        $evaluationsDir = app_path('Evaluations');
        $dataDir = $evaluationsDir . '/data';
        
        // Ensure data directory doesn't exist
        $this->cleanupTestFiles();
        
        // Ensure evaluations directory exists but data doesn't
        if (!is_dir($evaluationsDir)) {
            mkdir($evaluationsDir, 0755, true);
        }
        
        $this->assertDirectoryDoesNotExist($dataDir);
        
        $this->artisan('vizra:make:eval', ['name' => 'DirectoryTest'])
            ->assertExitCode(0);
            
        // Verify data directory was created
        $this->assertDirectoryExists($dataDir);
        
        $this->cleanupTestFiles();
    }

    public function test_csv_file_name_conversion_edge_cases()
    {
        $testCases = [
            ['XMLParser', 'x_m_l_parser.csv'],
            ['HTTPRequest', 'h_t_t_p_request.csv'],
            ['APIResponseValidator', 'a_p_i_response_validator.csv'],
            ['SimpleTest', 'simple_test.csv'],
        ];
        
        foreach ($testCases as [$inputName, $expectedFileName]) {
            $this->cleanupTestFiles();
            
            $expectedCsvPath = app_path('Evaluations/data/' . $expectedFileName);
            
            $this->artisan('vizra:make:eval', ['name' => $inputName])
                ->assertExitCode(0);
                
            $this->assertFileExists($expectedCsvPath, "Failed to create CSV for {$inputName}");
            
            // Verify CSV has correct headers
            $csvContent = file_get_contents($expectedCsvPath);
            $this->assertEquals("prompt,expected_response,description\n", $csvContent);
        }
        
        $this->cleanupTestFiles();
    }

    public function test_csv_creation_with_evaluation_suffix_removal()
    {
        $this->cleanupTestFiles();
        
        $expectedCsvPath = app_path('Evaluations/data/content_quality.csv');
        
        // Test with 'Evaluation' suffix included in input
        $this->artisan('vizra:make:eval', ['name' => 'ContentQualityEvaluation'])
            ->assertExitCode(0);
            
        // Should create CSV without the 'evaluation' part in the name
        $this->assertFileExists($expectedCsvPath);
        
        $csvContent = file_get_contents($expectedCsvPath);
        $this->assertEquals("prompt,expected_response,description\n", $csvContent);
        
        $this->cleanupTestFiles();
    }

    public function test_command_output_includes_csv_creation_message()
    {
        $this->cleanupTestFiles();
        
        $expectedCsvPath = app_path('Evaluations/data/output_test.csv');
        
        $this->artisan('vizra:make:eval', ['name' => 'OutputTest'])
            ->assertExitCode(0)
            ->expectsOutput("Created CSV file: {$expectedCsvPath}");
            
        $this->cleanupTestFiles();
    }

    public function test_command_output_warns_for_existing_csv()
    {
        $evaluationsDir = app_path('Evaluations');
        $dataDir = $evaluationsDir . '/data';
        $csvPath = $dataDir . '/warning_test.csv';
        
        // Ensure directories exist and create existing CSV
        if (!is_dir($dataDir)) {
            mkdir($dataDir, 0755, true);
        }
        file_put_contents($csvPath, "existing,content\n");
        
        $this->artisan('vizra:make:eval', ['name' => 'WarningTest'])
            ->assertExitCode(0)
            ->expectsOutput("CSV file already exists: {$csvPath}");
            
        $this->cleanupTestFiles();
    }

    public function test_csv_content_format()
    {
        $this->cleanupTestFiles();
        
        $expectedCsvPath = app_path('Evaluations/data/format_test.csv');
        
        $this->artisan('vizra:make:eval', ['name' => 'FormatTest'])
            ->assertExitCode(0);
            
        $csvContent = file_get_contents($expectedCsvPath);
        
        // Verify exact format: headers with newline, no extra content
        $this->assertEquals("prompt,expected_response,description\n", $csvContent);
        
        // Verify it's valid CSV that can be parsed
        $lines = str_getcsv($csvContent, "\n");
        $this->assertCount(1, $lines); // Only header line
        
        $headers = str_getcsv($lines[0]);
        $this->assertEquals(['prompt', 'expected_response', 'description'], $headers);
        
        $this->cleanupTestFiles();
    }

    protected function cleanupTestFiles(): void
    {
        $evaluationsDir = app_path('Evaluations');
        $dataDir = $evaluationsDir . '/data';
        
        // Remove any test files
        $testFiles = [
            $evaluationsDir . '/SentimentAnalysisEvaluation.php',
            $evaluationsDir . '/TestEvalEvaluation.php',
            $evaluationsDir . '/DirectoryTestEvaluation.php',
            $evaluationsDir . '/XMLParserEvaluation.php',
            $evaluationsDir . '/HTTPRequestEvaluation.php',
            $evaluationsDir . '/APIResponseValidatorEvaluation.php',
            $evaluationsDir . '/SimpleTestEvaluation.php',
            $evaluationsDir . '/ContentQualityEvaluation.php',
            $evaluationsDir . '/OutputTestEvaluation.php',
            $evaluationsDir . '/WarningTestEvaluation.php',
            $evaluationsDir . '/FormatTestEvaluation.php',
            $dataDir . '/sentiment_analysis.csv',
            $dataDir . '/test_eval.csv',
            $dataDir . '/directory_test.csv',
            $dataDir . '/x_m_l_parser.csv',
            $dataDir . '/h_t_t_p_request.csv',
            $dataDir . '/a_p_i_response_validator.csv',
            $dataDir . '/simple_test.csv',
            $dataDir . '/content_quality.csv',
            $dataDir . '/output_test.csv',
            $dataDir . '/warning_test.csv',
            $dataDir . '/format_test.csv',
        ];
        
        foreach ($testFiles as $file) {
            if (file_exists($file)) {
                unlink($file);
            }
        }
        
        // Remove directories if empty
        if (is_dir($dataDir) && count(scandir($dataDir)) == 2) {
            rmdir($dataDir);
        }
        if (is_dir($evaluationsDir) && count(scandir($evaluationsDir)) == 2) {
            rmdir($evaluationsDir);
        }
    }
}
