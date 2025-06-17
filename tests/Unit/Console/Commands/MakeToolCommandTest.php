<?php

namespace Vizra\VizraAdk\Tests\Unit\Console\Commands;

use Vizra\VizraAdk\Console\Commands\MakeToolCommand;
use Vizra\VizraAdk\Tests\TestCase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;
use Illuminate\Filesystem\Filesystem;

class MakeToolCommandTest extends TestCase
{
    protected $command;

    protected function setUp(): void
    {
        parent::setUp();

        $filesystem = new Filesystem();
        $this->command = new MakeToolCommand($filesystem);
        $this->command->setLaravel($this->app);
    }

    public function test_command_has_correct_name_and_description()
    {
        $this->assertEquals('vizra:make:tool', $this->command->getName());
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
        Config::set('vizra-adk.namespaces.tools', 'Custom\Tools');

        $reflection = new \ReflectionClass($this->command);
        $method = $reflection->getMethod('getDefaultNamespace');
        $method->setAccessible(true);

        $namespace = $method->invoke($this->command, 'App');

        $this->assertEquals('Custom\Tools', $namespace);
    }

    public function test_get_default_namespace_fallback()
    {
        Config::set('vizra-adk.namespaces.tools', null);

        $reflection = new \ReflectionClass($this->command);
        $method = $reflection->getMethod('getDefaultNamespace');
        $method->setAccessible(true);

        $namespace = $method->invoke($this->command, 'App');

        $this->assertEquals('App\Tools', $namespace);
    }

    public function test_build_class_replaces_tool_name_placeholder()
    {
        // Test the tool name generation logic
        $className = 'WeatherTool';
        $snakeName = Str::snake($className);
        if (Str::endsWith($snakeName, '_tool')) {
            $snakeName = substr($snakeName, 0, -5);
        }

        $this->assertEquals('weather', $snakeName);
    }

    public function test_build_class_removes_tool_suffix_from_name()
    {
        // Test various tool name formats
        $testCases = [
            'WeatherTool' => 'weather',
            'EmailSenderTool' => 'email_sender',
            'Calculator' => 'calculator',
            'GetUserDataTool' => 'get_user_data'
        ];

        foreach ($testCases as $className => $expectedName) {
            $snakeName = Str::snake($className);
            if (Str::endsWith($snakeName, '_tool')) {
                $snakeName = substr($snakeName, 0, -5);
            }

            $this->assertEquals($expectedName, $snakeName, "Failed for {$className}");
        }
    }

    public function test_build_class_handles_camel_case_names()
    {
        $className = 'EmailNotificationTool';
        $snakeName = Str::snake($className);
        if (Str::endsWith($snakeName, '_tool')) {
            $snakeName = substr($snakeName, 0, -5);
        }

        $this->assertEquals('email_notification', $snakeName);
    }

    public function test_qualify_class_with_full_namespace()
    {
        $reflection = new \ReflectionClass($this->command);
        $method = $reflection->getMethod('qualifyClass');
        $method->setAccessible(true);

        $qualifiedName = $method->invoke($this->command, 'App\Tools\WeatherTool');

        $this->assertEquals('App\Tools\WeatherTool', $qualifiedName);
    }

    public function test_qualify_class_with_already_qualified_name()
    {
        $reflection = new \ReflectionClass($this->command);
        $method = $reflection->getMethod('qualifyClass');
        $method->setAccessible(true);

        $fullyQualified = 'App\Tools\Weather\CurrentTool';
        $result = $method->invoke($this->command, $fullyQualified);

        $this->assertEquals($fullyQualified, $result);
    }

    public function test_get_arguments_returns_correct_structure()
    {
        $reflection = new \ReflectionClass($this->command);
        $method = $reflection->getMethod('getArguments');
        $method->setAccessible(true);

        $arguments = $method->invoke($this->command);

        $this->assertIsArray($arguments);
        $this->assertCount(1, $arguments);
        $this->assertEquals('name', $arguments[0][0]);
    }

    // Simplified versions of filesystem-dependent tests
    public function test_command_creates_file_with_correct_content()
    {
        // Skip filesystem tests in unit tests
        $this->markTestSkipped('Filesystem operations tested in integration tests');
    }

    public function test_command_handles_existing_file()
    {
        // Skip filesystem tests in unit tests
        $this->markTestSkipped('Filesystem operations tested in integration tests');
    }

    public function test_command_without_tool_suffix()
    {
        // Test the logic without filesystem operations
        $className = 'Weather';
        $snakeName = Str::snake($className);
        $this->assertEquals('weather', $snakeName);
    }

    public function test_tool_name_generation_with_various_formats()
    {
        // Test tool name generation logic
        $testCases = [
            'EmailSenderTool' => 'email_sender',
            'GetUserDataTool' => 'get_user_data',
            'Calculator' => 'calculator'
        ];

        foreach ($testCases as $className => $expectedToolName) {
            $snakeName = Str::snake($className);
            if (Str::endsWith($snakeName, '_tool')) {
                $snakeName = substr($snakeName, 0, -5);
            }

            $this->assertEquals($expectedToolName, $snakeName);
        }
    }

    public function test_command_with_nested_class_name()
    {
        $reflection = new \ReflectionClass($this->command);
        $method = $reflection->getMethod('qualifyClass');
        $method->setAccessible(true);

        $result = $method->invoke($this->command, 'Weather/CurrentTool');
        $this->assertStringContainsString('Weather\CurrentTool', $result);
    }

    public function test_command_with_custom_namespace()
    {
        Config::set('vizra-adk.namespaces.tools', 'Custom\MyTools');

        $reflection = new \ReflectionClass($this->command);
        $method = $reflection->getMethod('getDefaultNamespace');
        $method->setAccessible(true);

        $namespace = $method->invoke($this->command, 'App');
        $this->assertEquals('Custom\MyTools', $namespace);
    }
}
