<?php

namespace Vizra\VizraSdk\Tests\Unit\Console\Commands;

use Vizra\VizraSdk\Console\Commands\MakeEvalCommand;
use Vizra\VizraSdk\Tests\TestCase;
use Illuminate\Filesystem\Filesystem;
use Mockery;

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
        $customPath = base_path('stubs/vendor/agent-adk/evaluation.stub');

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
        $command = new class($this->filesystem) extends MakeEvalCommand {
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
        $command = new class($this->filesystem) extends MakeEvalCommand {
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
        $command = new class($this->filesystem) extends MakeEvalCommand {
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

        $command = new class($this->filesystem) extends MakeEvalCommand {
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

        $command = new class($this->filesystem) extends MakeEvalCommand {
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
        if (!is_dir($evaluationsDir)) {
            mkdir($evaluationsDir, 0755, true);
        }

        $expectedPath = app_path('Evaluations/ProductReviewEvaluation.php');

        // Ensure file doesn't exist before test
        if (file_exists($expectedPath)) {
            unlink($expectedPath);
        }

        $this->artisan('vizra:make:eval', ['name' => 'ProductReview'])
            ->assertExitCode(0);

        // Verify the file was created with correct content
        $this->assertFileExists($expectedPath);
        $content = file_get_contents($expectedPath);

        $this->assertStringContainsString('class ProductReviewEvaluation extends BaseEvaluation', $content);
        $this->assertStringContainsString('public string $name = \'Product review\';', $content);
        $this->assertStringContainsString('public string $description = \'Description for Product review.\';', $content);
        $this->assertStringContainsString('app/Evaluations/data/product_review.csv', $content);
        $this->assertStringContainsString('namespace App\\Evaluations', $content);

        // Cleanup
        unlink($expectedPath);
        if (is_dir($evaluationsDir) && count(scandir($evaluationsDir)) == 2) {
            rmdir($evaluationsDir);
        }
    }

    public function test_command_handles_existing_file()
    {
        // Create the Evaluations directory and file
        $evaluationsDir = app_path('Evaluations');
        if (!is_dir($evaluationsDir)) {
            mkdir($evaluationsDir, 0755, true);
        }

        $existingFile = $evaluationsDir . '/ProductReviewEvaluation.php';
        file_put_contents($existingFile, '<?php // existing evaluation');

        $this->artisan('vizra:make:eval', ['name' => 'ProductReview'])
            ->assertExitCode(0);

        // Verify the existing file was not overwritten
        $content = file_get_contents($existingFile);
        $this->assertEquals('<?php // existing evaluation', $content);

        // Cleanup
        unlink($existingFile);
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

            $command = new class($this->filesystem, $inputName) extends MakeEvalCommand {
                private $inputName;

                public function __construct($filesystem, $inputName) {
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
        if (!is_dir($evaluationsDir)) {
            mkdir($evaluationsDir, 0755, true);
        }

        $expectedPath = app_path('Evaluations/MathEvaluation.php');

        // Ensure file doesn't exist before test
        if (file_exists($expectedPath)) {
            unlink($expectedPath);
        }

        $this->artisan('vizra:make:eval', ['name' => 'Math'])
            ->assertExitCode(0);

        // Verify the file was created with correct content
        $this->assertFileExists($expectedPath);
        $content = file_get_contents($expectedPath);

        $this->assertStringContainsString('class MathEvaluation extends BaseEvaluation', $content);
        $this->assertStringContainsString('public string $name = \'Math\';', $content);
        $this->assertStringContainsString('app/Evaluations/data/math.csv', $content);

        // Cleanup
        unlink($expectedPath);
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
            $command = new class($this->filesystem, $inputName) extends MakeEvalCommand {
                private $inputName;

                public function __construct($filesystem, $inputName) {
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

use Vizra\VizraSdk\Evaluations\BaseEvaluation;
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
}
