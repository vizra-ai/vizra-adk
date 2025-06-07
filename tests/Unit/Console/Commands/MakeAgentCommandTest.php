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
        Mockery::close();
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
        $expectedPath = app_path('Agents/TestAgent.php');
        $expectedContent = $this->getExpectedAgentContent();

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
                return str_contains($content, 'class TestAgent extends BaseLlmAgent') &&
                       str_contains($content, "protected string \$name = 'test_agent'") &&
                       str_contains($content, 'namespace App\Agents');
            }))
            ->andReturn(true);

        $this->filesystem->shouldReceive('get')
            ->once()
            ->andReturn($this->getStubContent());

        $this->artisan('agent:make:agent', ['name' => 'TestAgent'])
            ->assertExitCode(0);
    }

    public function test_command_handles_existing_file()
    {
        $expectedPath = app_path('Agents/TestAgent.php');

        $this->filesystem->shouldReceive('exists')
            ->with($expectedPath)
            ->once()
            ->andReturn(true);

        $this->artisan('agent:make:agent', ['name' => 'TestAgent'])
            ->expectsOutput('Agent [App/Agents/TestAgent.php] already exists.')
            ->assertExitCode(0);
    }

    public function test_command_with_custom_namespace()
    {
        Config::set('agent-adk.namespaces.agents', 'Custom\MyAgents');
        
        $expectedPath = base_path('Custom/MyAgents/TestAgent.php');

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
                return str_contains($content, 'namespace Custom\MyAgents');
            }))
            ->andReturn(true);

        $this->filesystem->shouldReceive('get')
            ->once()
            ->andReturn($this->getStubContent());

        $this->artisan('agent:make:agent', ['name' => 'TestAgent'])
            ->assertExitCode(0);
    }

    public function test_command_with_nested_class_name()
    {
        // In the test environment, we're using a mock filesystem, so we need to be more flexible
        $this->filesystem->shouldReceive('exists')
            ->once()
            ->andReturn(false);

        $this->filesystem->shouldReceive('makeDirectory')
            ->once()
            ->andReturn(true);

        $this->filesystem->shouldReceive('put')
            ->once()
            ->withArgs(function ($path, $content) {
                return str_contains($path, 'Support/CustomerAgent.php') &&
                       str_contains($content, 'namespace App\Agents\Support') &&
                       str_contains($content, 'class CustomerAgent extends BaseLlmAgent') &&
                       str_contains($content, "protected string \$name = 'customer_agent'");
            })
            ->andReturn(true);

        $this->filesystem->shouldReceive('get')
            ->once()
            ->andReturn($this->getStubContent());

        $this->artisan('agent:make:agent', ['name' => 'Support/CustomerAgent'])
            ->assertExitCode(0);
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

    protected function registerTools(): array
    {
        return [
            // Example: YourTool::class,
        ];
    }

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

    protected function registerTools(): array
    {
        return [
            // Example: YourTool::class,
        ];
    }

    public function beforeLlmCall(array $inputMessages, AgentContext $context): array
    {
        return parent::beforeLlmCall($inputMessages, $context);
    }
}';
    }
}