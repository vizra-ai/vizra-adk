<?php

namespace AaronLumsden\LaravelAiADK\Tests\Unit\Console\Commands;

use AaronLumsden\LaravelAiADK\Console\Commands\MakeToolCommand;
use AaronLumsden\LaravelAiADK\Tests\TestCase;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Config;
use Mockery;

class MakeToolCommandTest extends TestCase
{
    protected $filesystem;
    protected $command;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->filesystem = Mockery::mock(Filesystem::class);
        $this->command = new MakeToolCommand($this->filesystem);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_command_has_correct_name_and_description()
    {
        $this->assertEquals('agent:make:tool', $this->command->getName());
        $this->assertEquals('Create a new Tool class for an Agent', $this->command->getDescription());
    }

    public function test_get_stub_returns_correct_path()
    {
        $reflection = new \ReflectionClass($this->command);
        $method = $reflection->getMethod('getStub');
        $method->setAccessible(true);

        $stubPath = $method->invoke($this->command);
        
        $this->assertStringEndsWith('/stubs/tool.stub', $stubPath);
        $this->assertFileExists($stubPath);
    }

    public function test_get_default_namespace_uses_config()
    {
        Config::set('agent-adk.namespaces.tools', 'Custom\Tools');
        
        $reflection = new \ReflectionClass($this->command);
        $method = $reflection->getMethod('getDefaultNamespace');
        $method->setAccessible(true);

        $namespace = $method->invoke($this->command, 'App');
        
        $this->assertEquals('Custom\Tools', $namespace);
    }

    public function test_get_default_namespace_fallback()
    {
        Config::set('agent-adk.namespaces.tools', null);
        
        $reflection = new \ReflectionClass($this->command);
        $method = $reflection->getMethod('getDefaultNamespace');
        $method->setAccessible(true);

        $namespace = $method->invoke($this->command, 'App');
        
        $this->assertEquals('App\Tools', $namespace);
    }

    public function test_qualify_class_with_full_namespace()
    {
        Config::set('agent-adk.namespaces.tools', 'App\Tools');
        
        $reflection = new \ReflectionClass($this->command);
        $method = $reflection->getMethod('qualifyClass');
        $method->setAccessible(true);

        $qualifiedClass = $method->invoke($this->command, 'WeatherTool');
        
        $this->assertEquals('App\Tools\WeatherTool', $qualifiedClass);
    }

    public function test_qualify_class_with_already_qualified_name()
    {
        Config::set('agent-adk.namespaces.tools', 'App\Tools');
        
        $reflection = new \ReflectionClass($this->command);
        $method = $reflection->getMethod('qualifyClass');
        $method->setAccessible(true);

        $qualifiedClass = $method->invoke($this->command, 'App\Tools\WeatherTool');
        
        $this->assertEquals('App\Tools\WeatherTool', $qualifiedClass);
    }

    public function test_build_class_replaces_tool_name_placeholder()
    {
        $this->filesystem->shouldReceive('get')
            ->once()
            ->andReturn('class {{ class }} { \'name\' => \'{{ toolName }}\', \'description\' => \'{{ toolDescription }}\' }');

        $reflection = new \ReflectionClass($this->command);
        $method = $reflection->getMethod('buildClass');
        $method->setAccessible(true);

        $result = $method->invoke($this->command, 'App\Tools\WeatherTool');
        
        $this->assertStringContains('weather', $result);
        $this->assertStringContains('Weather', $result);
    }

    public function test_build_class_removes_tool_suffix_from_name()
    {
        $this->filesystem->shouldReceive('get')
            ->once()
            ->andReturn('\'name\' => \'{{ toolName }}\'');

        $reflection = new \ReflectionClass($this->command);
        $method = $reflection->getMethod('buildClass');
        $method->setAccessible(true);

        $result = $method->invoke($this->command, 'App\Tools\WeatherTool');
        
        // Should be "weather" not "weather_tool"
        $this->assertStringContains('weather', $result);
        $this->assertStringNotContains('weather_tool', $result);
    }

    public function test_build_class_handles_camel_case_names()
    {
        $this->filesystem->shouldReceive('get')
            ->once()
            ->andReturn('\'name\' => \'{{ toolName }}\', \'description\' => \'{{ toolDescription }}\'');

        $reflection = new \ReflectionClass($this->command);
        $method = $reflection->getMethod('buildClass');
        $method->setAccessible(true);

        $result = $method->invoke($this->command, 'App\Tools\GetCurrentWeatherTool');
        
        $this->assertStringContains('get_current_weather', $result);
        $this->assertStringContains('Get Current Weather', $result);
    }

    public function test_get_arguments_returns_correct_structure()
    {
        $arguments = $this->command->getDefinition()->getArguments();
        
        $this->assertCount(1, $arguments);
        $this->assertArrayHasKey('name', $arguments);
        $this->assertTrue($arguments['name']->isRequired());
        $this->assertEquals('The name of the tool class.', $arguments['name']->getDescription());
    }

    public function test_command_creates_file_with_correct_content()
    {
        $expectedPath = app_path('Tools/WeatherTool.php');

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
                return str_contains($content, 'class WeatherTool implements ToolInterface') &&
                       str_contains($content, "'name' => 'weather'") &&
                       str_contains($content, "'description' => 'Weather.'") &&
                       str_contains($content, 'namespace App\Tools');
            }))
            ->andReturn(true);

        $this->filesystem->shouldReceive('get')
            ->once()
            ->andReturn($this->getStubContent());

        $this->artisan('agent:make:tool', ['name' => 'WeatherTool'])
            ->assertExitCode(0);
    }

    public function test_command_handles_existing_file()
    {
        $expectedPath = app_path('Tools/WeatherTool.php');

        $this->filesystem->shouldReceive('exists')
            ->with($expectedPath)
            ->once()
            ->andReturn(true);

        $this->artisan('agent:make:tool', ['name' => 'WeatherTool'])
            ->expectsOutput('Tool already exists!')
            ->assertExitCode(0);
    }

    public function test_command_with_custom_namespace()
    {
        Config::set('agent-adk.namespaces.tools', 'Custom\MyTools');
        
        $expectedPath = base_path('Custom/MyTools/WeatherTool.php');

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
                return str_contains($content, 'namespace Custom\MyTools');
            }))
            ->andReturn(true);

        $this->filesystem->shouldReceive('get')
            ->once()
            ->andReturn($this->getStubContent());

        $this->artisan('agent:make:tool', ['name' => 'WeatherTool'])
            ->assertExitCode(0);
    }

    public function test_command_with_nested_class_name()
    {
        $expectedPath = app_path('Tools/Weather/CurrentTool.php');

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
                return str_contains($content, 'namespace App\Tools\Weather') &&
                       str_contains($content, 'class CurrentTool implements ToolInterface') &&
                       str_contains($content, "'name' => 'current'") &&
                       str_contains($content, "'description' => 'Current.'");
            }))
            ->andReturn(true);

        $this->filesystem->shouldReceive('get')
            ->once()
            ->andReturn($this->getStubContent());

        $this->artisan('agent:make:tool', ['name' => 'Weather/CurrentTool'])
            ->assertExitCode(0);
    }

    public function test_tool_name_generation_with_various_formats()
    {
        $reflection = new \ReflectionClass($this->command);
        $method = $reflection->getMethod('buildClass');
        $method->setAccessible(true);

        $testCases = [
            ['EmailSenderTool', 'email_sender', 'Email Sender'],
            ['GetUserDataTool', 'get_user_data', 'Get User Data'],
            ['Calculator', 'calculator', 'Calculator'],
            ['HTTPRequestTool', 'h_t_t_p_request', 'H T T P Request'], // Edge case
        ];

        foreach ($testCases as [$className, $expectedToolName, $expectedDescription]) {
            $this->filesystem->shouldReceive('get')
                ->once()
                ->andReturn('\'name\' => \'{{ toolName }}\', \'description\' => \'{{ toolDescription }}\'');

            $result = $method->invoke($this->command, "App\\Tools\\{$className}");
            
            $this->assertStringContains($expectedToolName, $result, "Failed for {$className}");
            $this->assertStringContains($expectedDescription, $result, "Failed for {$className}");
        }
    }

    public function test_command_without_tool_suffix()
    {
        $expectedPath = app_path('Tools/Weather.php');

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
                return str_contains($content, 'class Weather implements ToolInterface') &&
                       str_contains($content, "'name' => 'weather'") &&
                       str_contains($content, "'description' => 'Weather.'");
            }))
            ->andReturn(true);

        $this->filesystem->shouldReceive('get')
            ->once()
            ->andReturn($this->getStubContent());

        $this->artisan('agent:make:tool', ['name' => 'Weather'])
            ->assertExitCode(0);
    }

    protected function getStubContent(): string
    {
        return '<?php

namespace {{ namespace }};

use AaronLumsden\LaravelAiADK\Contracts\ToolInterface;
use AaronLumsden\LaravelAiADK\System\AgentContext;

class {{ class }} implements ToolInterface
{
    public function definition(): array
    {
        return [
            \'name\' => \'{{ toolName }}\',
            \'description\' => \'{{ toolDescription }}.\',
            \'parameters\' => [
                \'type\' => \'object\',
                \'properties\' => [
                    // Define your parameters here
                ],
            ],
        ];
    }

    public function execute(array $arguments, AgentContext $context): string
    {
        $result = [
            \'status\' => \'success\',
            \'message\' => \'Tool {{ toolName }} executed with arguments: \' . json_encode($arguments),
        ];

        return json_encode($result);
    }
}';
    }
}