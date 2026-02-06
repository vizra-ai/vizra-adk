<?php

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;
use Vizra\VizraADK\Console\Commands\MakeToolboxCommand;

beforeEach(function () {
    $filesystem = new Filesystem;
    $this->command = new MakeToolboxCommand($filesystem);
    $this->command->setLaravel($this->app);
});

afterEach(function () {
    Mockery::close();
});

describe('MakeToolboxCommand', function () {
    it('has correct name and description', function () {
        expect($this->command->getName())->toBe('vizra:make:toolbox');
        expect($this->command->getDescription())->toBe('Create a new Toolbox class for grouping tools');
    });

    it('returns correct stub path', function () {
        $reflection = new ReflectionClass($this->command);
        $method = $reflection->getMethod('getStub');
        $method->setAccessible(true);

        $stubPath = $method->invoke($this->command);

        expect($stubPath)->toEndWith('/stubs/toolbox.stub');
        expect(file_exists($stubPath))->toBeTrue();
    });

    it('uses configured namespace', function () {
        Config::set('vizra-adk.namespaces.toolboxes', 'Custom\Toolboxes');

        $reflection = new ReflectionClass($this->command);
        $method = $reflection->getMethod('getDefaultNamespace');
        $method->setAccessible(true);

        $namespace = $method->invoke($this->command, 'App');

        expect($namespace)->toBe('Custom\Toolboxes');
    });

    it('falls back to default namespace', function () {
        Config::set('vizra-adk.namespaces.toolboxes', null);

        $reflection = new ReflectionClass($this->command);
        $method = $reflection->getMethod('getDefaultNamespace');
        $method->setAccessible(true);

        $namespace = $method->invoke($this->command, 'App');

        expect($namespace)->toBe('App\Toolboxes');
    });

    it('generates correct toolbox name from class name', function () {
        $testCases = [
            'AdminToolbox' => 'admin',
            'CustomerSupportToolbox' => 'customer_support',
            'PaymentProcessingToolbox' => 'payment_processing',
            'ApiTools' => 'api_tools',
        ];

        foreach ($testCases as $className => $expectedName) {
            $snakeName = Str::snake($className);
            if (Str::endsWith($snakeName, '_toolbox')) {
                $snakeName = substr($snakeName, 0, -8); // Remove '_toolbox'
            }

            expect($snakeName)->toBe($expectedName, "Failed for {$className}");
        }
    });

    it('handles camel case names correctly', function () {
        $className = 'DatabaseAdministrationToolbox';
        $snakeName = Str::snake($className);
        if (Str::endsWith($snakeName, '_toolbox')) {
            $snakeName = substr($snakeName, 0, -8);
        }

        expect($snakeName)->toBe('database_administration');
    });

    it('qualifies class with full namespace', function () {
        $reflection = new ReflectionClass($this->command);
        $method = $reflection->getMethod('qualifyClass');
        $method->setAccessible(true);

        $qualifiedName = $method->invoke($this->command, 'App\Toolboxes\AdminToolbox');

        expect($qualifiedName)->toBe('App\Toolboxes\AdminToolbox');
    });

    it('returns correct arguments structure', function () {
        $reflection = new ReflectionClass($this->command);
        $method = $reflection->getMethod('getArguments');
        $method->setAccessible(true);

        $arguments = $method->invoke($this->command);

        expect($arguments)->toBeArray();
        expect($arguments)->toHaveCount(1);
        expect($arguments[0][0])->toBe('name');
    });

    it('handles nested class names', function () {
        $reflection = new ReflectionClass($this->command);
        $method = $reflection->getMethod('qualifyClass');
        $method->setAccessible(true);

        $result = $method->invoke($this->command, 'Admin/DatabaseToolbox');
        expect($result)->toContain('Admin\DatabaseToolbox');
    });

    it('generates correct name without Toolbox suffix', function () {
        $className = 'AdminTools';
        $snakeName = Str::snake($className);

        expect($snakeName)->toBe('admin_tools');
    });
});

describe('MakeToolboxCommand Options', function () {
    it('has gate option', function () {
        $reflection = new ReflectionClass($this->command);
        $method = $reflection->getMethod('getOptions');
        $method->setAccessible(true);

        $options = $method->invoke($this->command);
        $optionNames = array_map(fn($opt) => $opt[0], $options);

        expect($optionNames)->toContain('gate');
    });

    it('has policy option', function () {
        $reflection = new ReflectionClass($this->command);
        $method = $reflection->getMethod('getOptions');
        $method->setAccessible(true);

        $options = $method->invoke($this->command);
        $optionNames = array_map(fn($opt) => $opt[0], $options);

        expect($optionNames)->toContain('policy');
    });

    it('has tools option', function () {
        $reflection = new ReflectionClass($this->command);
        $method = $reflection->getMethod('getOptions');
        $method->setAccessible(true);

        $options = $method->invoke($this->command);
        $optionNames = array_map(fn($opt) => $opt[0], $options);

        expect($optionNames)->toContain('tools');
    });
});

describe('MakeToolboxCommand Stub Replacements', function () {
    it('replaces toolbox name placeholder', function () {
        $stubContent = <<<'STUB'
protected string $name = '{{ toolbox_name }}';
STUB;

        $toolboxName = 'admin';
        $result = str_replace('{{ toolbox_name }}', $toolboxName, $stubContent);

        expect($result)->toContain("protected string \$name = 'admin';");
    });

    it('replaces gate placeholder when provided', function () {
        $stubContent = <<<'STUB'
{{ gate_property }}
STUB;

        $gateName = 'admin-access';
        $gateProperty = "protected ?string \$gate = '{$gateName}';";
        $result = str_replace('{{ gate_property }}', $gateProperty, $stubContent);

        expect($result)->toContain("protected ?string \$gate = 'admin-access';");
    });

    it('replaces policy placeholder when provided', function () {
        $stubContent = <<<'STUB'
{{ policy_property }}
STUB;

        $policyClass = 'App\Policies\AdminPolicy';
        $policyProperty = "protected ?string \$policy = {$policyClass}::class;";
        $result = str_replace('{{ policy_property }}', $policyProperty, $stubContent);

        expect($result)->toContain("protected ?string \$policy = App\Policies\AdminPolicy::class;");
    });

    it('replaces tools array placeholder', function () {
        $stubContent = <<<'STUB'
protected array $tools = [
{{ tools_list }}
    ];
STUB;

        $toolsList = "        \\App\\Tools\\DatabaseTool::class,\n        \\App\\Tools\\UserTool::class,";
        $result = str_replace('{{ tools_list }}', $toolsList, $stubContent);

        expect($result)->toContain('DatabaseTool::class');
        expect($result)->toContain('UserTool::class');
    });
});
