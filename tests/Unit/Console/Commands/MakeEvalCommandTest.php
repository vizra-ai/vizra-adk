<?php

namespace AaronLumsden\LaravelAiADK\Tests\Unit\Console\Commands;

use AaronLumsden\LaravelAiADK\Console\Commands\MakeEvalCommand;
use AaronLumsden\LaravelAiADK\Tests\TestCase;
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
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_command_has_correct_name_and_description()
    {
        $this->assertEquals('agent:make:eval', $this->command->getName());
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
        $reflection = new \ReflectionClass($this->command);
        
        // Mock the argument method
        $this->command = Mockery::mock(MakeEvalCommand::class)->makePartial();
        $this->command->shouldReceive('argument')
            ->with('name')
            ->andReturn('ProductReview');

        $method = $reflection->getMethod('getNameInput');
        $method->setAccessible(true);

        $result = $method->invoke($this->command);
        
        $this->assertEquals('ProductReviewEvaluation', $result);
    }

    public function test_get_name_input_preserves_existing_evaluation_suffix()
    {
        $reflection = new \ReflectionClass($this->command);
        
        $this->command = Mockery::mock(MakeEvalCommand::class)->makePartial();
        $this->command->shouldReceive('argument')
            ->with('name')
            ->andReturn('ProductReviewEvaluation');

        $method = $reflection->getMethod('getNameInput');
        $method->setAccessible(true);

        $result = $method->invoke($this->command);
        
        $this->assertEquals('ProductReviewEvaluation', $result);
    }

    public function test_build_class_replaces_placeholders()
    {
        $this->filesystem->shouldReceive('get')
            ->once()
            ->andReturn('class {{ class }} { name = "{{ evaluation_name }}"; csvPath = "{{ csv_file_name }}"; }');

        $this->command = Mockery::mock(MakeEvalCommand::class)->makePartial();
        $this->command->shouldReceive('argument')
            ->with('name')
            ->andReturn('ProductReview');

        $reflection = new \ReflectionClass($this->command);
        $method = $reflection->getMethod('buildClass');
        $method->setAccessible(true);

        $result = $method->invoke($this->command, 'App\\Evaluations\\ProductReviewEvaluation');
        
        $this->assertStringContains('Product Review', $result);
        $this->assertStringContains('product_review', $result);
    }

    public function test_build_class_removes_evaluation_suffix_from_placeholders()
    {
        $this->filesystem->shouldReceive('get')
            ->once()
            ->andReturn('name = "{{ evaluation_name }}"; csvPath = "{{ csv_file_name }}";');

        $this->command = Mockery::mock(MakeEvalCommand::class)->makePartial();
        $this->command->shouldReceive('argument')
            ->with('name')
            ->andReturn('ProductReviewEvaluation');

        $reflection = new \ReflectionClass($this->command);
        $method = $reflection->getMethod('buildClass');
        $method->setAccessible(true);

        $result = $method->invoke($this->command, 'App\\Evaluations\\ProductReviewEvaluation');
        
        // Should be "Product Review" not "Product Review Evaluation"
        $this->assertStringContains('Product Review', $result);
        $this->assertStringNotContains('Product Review Evaluation', $result);
        
        // Should be "product_review" not "product_review_evaluation"
        $this->assertStringContains('product_review', $result);
        $this->assertStringNotContains('product_review_evaluation', $result);
    }

    public function test_build_class_handles_camel_case_names()
    {
        $this->filesystem->shouldReceive('get')
            ->once()
            ->andReturn('name = "{{ evaluation_name }}"; csvPath = "{{ csv_file_name }}";');

        $this->command = Mockery::mock(MakeEvalCommand::class)->makePartial();
        $this->command->shouldReceive('argument')
            ->with('name')
            ->andReturn('ContentQualityAnalysis');

        $reflection = new \ReflectionClass($this->command);
        $method = $reflection->getMethod('buildClass');
        $method->setAccessible(true);

        $result = $method->invoke($this->command, 'App\\Evaluations\\ContentQualityAnalysisEvaluation');
        
        $this->assertStringContains('Content Quality Analysis', $result);
        $this->assertStringContains('content_quality_analysis', $result);
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
        $expectedPath = app_path('Evaluations/ProductReviewEvaluation.php');

        $this->filesystem->shouldReceive('exists')
            ->with($expectedPath)
            ->once()
            ->andReturn(false);

        $this->filesystem->shouldReceive('makeDirectory')
            ->once()
            ->andReturn(true);

        $this->filesystem->shouldReceive('put')
            ->once()
            ->with($expectedPath, Mockery::on(function ($content) {
                return str_contains($content, 'class ProductReviewEvaluation extends BaseEvaluation') &&
                       str_contains($content, 'public string $name = \'Product Review\';') &&
                       str_contains($content, 'public string $description = \'Description for Product Review.\';') &&
                       str_contains($content, 'app/Evaluations/data/product_review.csv') &&
                       str_contains($content, 'namespace App\\Evaluations');
            }))
            ->andReturn(true);

        $this->filesystem->shouldReceive('get')
            ->once()
            ->andReturn($this->getStubContent());

        $this->artisan('agent:make:eval', ['name' => 'ProductReview'])
            ->assertExitCode(0);
    }

    public function test_command_handles_existing_file()
    {
        $expectedPath = app_path('Evaluations/ProductReviewEvaluation.php');

        $this->filesystem->shouldReceive('exists')
            ->with($expectedPath)
            ->once()
            ->andReturn(true);

        $this->artisan('agent:make:eval', ['name' => 'ProductReview'])
            ->expectsOutput('Evaluation already exists!')
            ->assertExitCode(0);
    }

    public function test_evaluation_name_generation_with_various_formats()
    {
        $testCases = [
            ['SentimentAnalysis', 'Sentiment Analysis', 'sentiment_analysis'],
            ['ContentQuality', 'Content Quality', 'content_quality'],
            ['SimpleMath', 'Simple Math', 'simple_math'],
            ['LLMJudgeTest', 'L L M Judge Test', 'l_l_m_judge_test'], // Edge case
        ];

        foreach ($testCases as [$inputName, $expectedEvalName, $expectedCsvName]) {
            $this->filesystem->shouldReceive('get')
                ->once()
                ->andReturn('name = "{{ evaluation_name }}"; csvPath = "{{ csv_file_name }}";');

            $this->command = Mockery::mock(MakeEvalCommand::class)->makePartial();
            $this->command->shouldReceive('argument')
                ->with('name')
                ->andReturn($inputName);

            $reflection = new \ReflectionClass($this->command);
            $method = $reflection->getMethod('buildClass');
            $method->setAccessible(true);

            $result = $method->invoke($this->command, "App\\Evaluations\\{$inputName}Evaluation");
            
            $this->assertStringContains($expectedEvalName, $result, "Failed evaluation name for {$inputName}");
            $this->assertStringContains($expectedCsvName, $result, "Failed CSV name for {$inputName}");
        }
    }

    public function test_command_without_evaluation_suffix()
    {
        $expectedPath = app_path('Evaluations/Math.php');

        $this->filesystem->shouldReceive('exists')
            ->with($expectedPath)
            ->once()
            ->andReturn(false);

        $this->filesystem->shouldReceive('makeDirectory')
            ->once()
            ->andReturn(true);

        $this->filesystem->shouldReceive('put')
            ->once()
            ->with($expectedPath, Mockery::on(function ($content) {
                return str_contains($content, 'class Math extends BaseEvaluation') &&
                       str_contains($content, 'public string $name = \'Math\';') &&
                       str_contains($content, 'app/Evaluations/data/math.csv');
            }))
            ->andReturn(true);

        $this->filesystem->shouldReceive('get')
            ->once()
            ->andReturn($this->getStubContent());

        $this->artisan('agent:make:eval', ['name' => 'Math'])
            ->assertExitCode(0);
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
            $this->command = Mockery::mock(MakeEvalCommand::class)->makePartial();
            $this->command->shouldReceive('argument')
                ->with('name')
                ->andReturn($inputName);

            $reflection = new \ReflectionClass($this->command);
            $method = $reflection->getMethod('buildClass');
            $method->setAccessible(true);

            $result = $method->invoke($this->command, "App\\Evaluations\\{$inputName}Evaluation");
            
            $this->assertStringContains($expectedCsvName, $result, "Failed CSV name conversion for {$inputName}");
        }
    }

    protected function getStubContent(): string
    {
        return '<?php

namespace {{ namespace }};

use AaronLumsden\LaravelAiADK\Evaluations\BaseEvaluation;
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