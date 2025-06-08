<?php

namespace AaronLumsden\LaravelAiADK\Tests\Unit\Console\Commands;

use AaronLumsden\LaravelAiADK\Console\Commands\MakeAgentCommand;
use AaronLumsden\LaravelAiADK\Tests\TestCase;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Config;
use Mockery;

class MakeAgentCommandTest extends TestCase
{
    protected $filesystem;
    protected $command;

    protected function setUp(): void
    {
        parent::setUp();

        $this->filesystem = Mockery::mock(Filesystem::class);
        $this->command = new MakeAgentCommand($this->filesystem);
        $this->command->setLaravel($this->app);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
    }

    public function test_command_has_correct_name_and_description()
    {
        $this->assertEquals('agent:make:agent', $this->command->getName());
        $this->assertEquals('Create a new Agent class', $this->command->getDescription());
    }

    public function test_get_stub_returns_correct_path()
    {
        $reflection = new \ReflectionClass($this->command);
        $method = $reflection->getMethod('getStub');
        $method->setAccessible(true);

        $stubPath = $method->invoke($this->command);

        $this->assertStringEndsWith('/stubs/agent.stub', $stubPath);
        $this->assertFileExists($stubPath);
    }

    public function test_get_default_namespace_uses_config()
    {
        Config::set('agent-adk.namespaces.agents', 'Custom\Agents');

        $reflection = new \ReflectionClass($this->command);
        $method = $reflection->getMethod('getDefaultNamespace');
        $method->setAccessible(true);

        $namespace = $method->invoke($this->command, 'App');

        $this->assertEquals('Custom\Agents', $namespace);
    }

    public function test_get_default_namespace_fallback()
    {
        Config::set('agent-adk.namespaces.agents', null);

        $reflection = new \ReflectionClass($this->command);
        $method = $reflection->getMethod('getDefaultNamespace');
        $method->setAccessible(true);

        $namespace = $method->invoke($this->command, 'App');

        $this->assertEquals('App\Agents', $namespace);
    }

    public function test_qualify_class_with_full_namespace()
    {
        Config::set('agent-adk.namespaces.agents', 'App\Agents');

        $reflection = new \ReflectionClass($this->command);
        $method = $reflection->getMethod('qualifyClass');
        $method->setAccessible(true);

        $qualifiedClass = $method->invoke($this->command, 'TestAgent');

        $this->assertEquals('App\Agents\TestAgent', $qualifiedClass);
    }

    public function test_qualify_class_with_already_qualified_name()
    {
        Config::set('agent-adk.namespaces.agents', 'App\Agents');

        $reflection = new \ReflectionClass($this->command);
        $method = $reflection->getMethod('qualifyClass');
        $method->setAccessible(true);

        $qualifiedClass = $method->invoke($this->command, 'App\Agents\TestAgent');

        $this->assertEquals('App\Agents\TestAgent', $qualifiedClass);
    }

    public function test_qualify_class_with_slash_separators()
    {
        Config::set('agent-adk.namespaces.agents', 'App\Agents');

        $reflection = new \ReflectionClass($this->command);
        $method = $reflection->getMethod('qualifyClass');
        $method->setAccessible(true);

        $qualifiedClass = $method->invoke($this->command, 'SubFolder/TestAgent');

        $this->assertEquals('App\Agents\SubFolder\TestAgent', $qualifiedClass);
    }

    public function test_build_class_replaces_agent_name_placeholder()
    {
        // Mock the parent buildClass method behavior
        $this->filesystem->shouldReceive('get')
            ->once()
            ->andReturn('class {{ class }} { protected string $name = \'{{ agentName }}\'; }');

        $reflection = new \ReflectionClass($this->command);
        $method = $reflection->getMethod('buildClass');
        $method->setAccessible(true);

        $result = $method->invoke($this->command, 'App\Agents\CustomerSupportAgent');

        $this->assertStringContainsString('customer_support_agent', $result);
    }

    public function test_get_arguments_returns_correct_structure()
    {
        $arguments = $this->command->getDefinition()->getArguments();

        $this->assertCount(1, $arguments);
        $this->assertArrayHasKey('name', $arguments);
        $this->assertTrue($arguments['name']->isRequired());
        $this->assertEquals('The name of the agent class.', $arguments['name']->getDescription());
    }

    public function test_command_creates_file_with_correct_content()
    {
        // Create a temporary directory for this test
        $tempDir = sys_get_temp_dir() . '/agent-test-' . uniqid();
        mkdir($tempDir, 0755, true);

        // Mock the app_path to return our temp directory
        $this->app->bind('path', fn() => $tempDir);

        $this->artisan('agent:make:agent', ['name' => 'TestAgent'])
            ->assertExitCode(0);

        // Verify the file was created
        $expectedPath = $tempDir . '/Agents/TestAgent.php';
        $this->assertFileExists($expectedPath);

        // Verify the file content
        $content = file_get_contents($expectedPath);
        $this->assertStringContainsString('class TestAgent extends BaseLlmAgent', $content);
        $this->assertStringContainsString("protected string \$name = 'test_agent'", $content);
        $this->assertStringContainsString('namespace App\Agents', $content);

        // Cleanup
        unlink($expectedPath);
        rmdir(dirname($expectedPath));
        rmdir($tempDir);
    }

    public function test_command_handles_existing_file()
    {
        // Create the Agents directory in the actual app path
        $agentsDir = app_path('Agents');
        if (!is_dir($agentsDir)) {
            mkdir($agentsDir, 0755, true);
        }

        // Create an existing file
        $existingFile = $agentsDir . '/TestAgent.php';
        file_put_contents($existingFile, '<?php // existing file');

        $this->artisan('agent:make:agent', ['name' => 'TestAgent'])
            ->assertExitCode(0);

        // Check if the existing file was NOT overwritten (file should still contain original content)
        $content = file_get_contents($existingFile);
        $this->assertEquals('<?php // existing file', $content);

        // Cleanup
        unlink($existingFile);
        if (is_dir($agentsDir) && count(scandir($agentsDir)) == 2) { // only . and ..
            rmdir($agentsDir);
        }
    }

    public function test_command_with_custom_namespace()
    {
        // Use a custom namespace that doesn't start with App
        Config::set('agent-adk.namespaces.agents', 'Custom\MyAgents');

        // The command should create the file in app_path following the namespace structure
        // Since it's Custom\MyAgents, it should create app_path/Custom/MyAgents/TestAgent.php
        $customDir = app_path('Custom/MyAgents');
        if (!is_dir($customDir)) {
            mkdir($customDir, 0755, true);
        }

        $this->artisan('agent:make:agent', ['name' => 'TestAgent'])
            ->assertExitCode(0);

        // Verify the file was created in the custom namespace path
        $expectedPath = $customDir . '/TestAgent.php';
        $this->assertFileExists($expectedPath);

        // Verify the content has the custom namespace (as configured, not with App prefix)
        $content = file_get_contents($expectedPath);
        $this->assertStringContainsString('namespace Custom\MyAgents', $content);

        // Cleanup
        unlink($expectedPath);
        $this->removeDirectoryRecursively($customDir);
    }

    private function removeDirectoryRecursively($dir)
    {
        if (is_dir($dir)) {
            $objects = scandir($dir);
            foreach ($objects as $object) {
                if ($object != "." && $object != "..") {
                    if (is_dir($dir . "/" . $object)) {
                        $this->removeDirectoryRecursively($dir . "/" . $object);
                    } else {
                        unlink($dir . "/" . $object);
                    }
                }
            }
            rmdir($dir);
        }
    }

    public function test_command_with_nested_class_name()
    {
        // Create a temporary directory for this test
        $tempDir = sys_get_temp_dir() . '/agent-test-' . uniqid();
        mkdir($tempDir, 0755, true);

        // Mock the app_path to return our temp directory
        $this->app->bind('path', fn() => $tempDir);

        $this->artisan('agent:make:agent', ['name' => 'Support/CustomerAgent'])
            ->assertExitCode(0);

        // Verify the file was created in the nested directory
        $expectedPath = $tempDir . '/Agents/Support/CustomerAgent.php';
        $this->assertFileExists($expectedPath);

        // Verify the content has the nested namespace and correct class
        $content = file_get_contents($expectedPath);
        $this->assertStringContainsString('namespace App\Agents\Support', $content);
        $this->assertStringContainsString('class CustomerAgent extends BaseLlmAgent', $content);
        $this->assertStringContainsString("protected string \$name = 'customer_agent'", $content);

        // Cleanup
        unlink($expectedPath);
        rmdir(dirname($expectedPath));
        rmdir(dirname(dirname($expectedPath)));
        rmdir($tempDir);
    }

    protected function getStubContent(): string
    {
        return '<?php

namespace {{ namespace }};

use AaronLumsden\LaravelAiADK\Agents\BaseLlmAgent;
use AaronLumsden\LaravelAiADK\Contracts\ToolInterface;
use AaronLumsden\LaravelAiADK\System\AgentContext;

class {{ class }} extends BaseLlmAgent
{
    protected string $name = \'{{ agentName }}\';
    protected string $description = \'Describe what this agent does.\';

    protected string $instructions = \'You are a helpful assistant. Your name is {{ agentName }}.\';

    protected string $model = \'\';

    /**
     * @var array<class-string<ToolInterface>>
     */
    protected array $tools = [
        // Example: YourTool::class,
    ];

    public function beforeLlmCall(array $inputMessages, AgentContext $context): array
    {
        return parent::beforeLlmCall($inputMessages, $context);
    }
}';
    }

    protected function getExpectedAgentContent(): string
    {
        return '<?php

namespace App\Agents;

use AaronLumsden\LaravelAiADK\Agents\BaseLlmAgent;
use AaronLumsden\LaravelAiADK\Contracts\ToolInterface;
use AaronLumsden\LaravelAiADK\System\AgentContext;

class TestAgent extends BaseLlmAgent
{
    protected string $name = \'test_agent\';
    protected string $description = \'Describe what this agent does.\';

    protected string $instructions = \'You are a helpful assistant. Your name is test_agent.\';

    protected string $model = \'\';

    /**
     * @var array<class-string<ToolInterface>>
     */
    protected array $tools = [
        // Example: YourTool::class,
    ];

    public function beforeLlmCall(array $inputMessages, AgentContext $context): array
    {
        return parent::beforeLlmCall($inputMessages, $context);
    }
}';
    }
}
