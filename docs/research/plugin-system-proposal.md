# Vizra ADK Plugin/Package System - Research & Proposal

## Executive Summary

This document proposes a plugin/package system for Vizra ADK that enables third-party developers to extend the framework with reusable agents, tools, toolboxes, memory providers, workflows, and more — all distributed as Composer packages and integrated through a consistent API.

The design draws from patterns proven in **Filament** (plugin interface + fluent config), **Statamic** (declarative array registration), **WordPress** (hook/filter pipeline), **Symfony** (auto-configuration by interface), and **Laravel** (service provider foundation). The result is a system that feels native to Laravel developers while providing AI-specific extension points.

---

## Part 1: Current Architecture Analysis

### What Vizra ADK Already Has

The current codebase provides several extension mechanisms that a plugin system can build upon:

| Mechanism | Location | How It Works |
|---|---|---|
| **Agent Auto-Discovery** | `AgentDiscovery` → scans `App\Agents` namespace | File-system scan for classes extending `BaseAgent` |
| **Agent Registry** | `AgentRegistry::register()` | Runtime registration of agents by name + class |
| **Tool Interface** | `ToolInterface` with `definition()` + `execute()` | Tools declared in agent's `$tools` array |
| **Toolbox System** | `ToolboxInterface` / `BaseToolbox` | Grouped tools with per-toolbox and per-tool authorization |
| **MCP Integration** | `MCPClientManager` / `MCPToolDiscovery` | External tool servers via STDIO/HTTP transport |
| **Event System** | 12 event classes in `src/Events/` | Standard Laravel event listeners |
| **Service Provider** | `AgentServiceProvider` | Singletons, config merging, route/view/migration loading |
| **Workflow Facade** | `WorkflowManager` with macro support | Factory methods for sequential/parallel/conditional/loop |
| **Configurable Namespaces** | `config('vizra-adk.namespaces')` | Controls where agents/tools/evals are discovered |

### What's Missing for a Plugin Ecosystem

1. **No plugin contract** — No formal interface for declaring "I am a Vizra ADK plugin that provides X, Y, Z"
2. **Single-namespace discovery** — `AgentDiscovery` only scans one namespace (`App\Agents`), so third-party packages can't contribute agents automatically
3. **No tool registry** — Tools are loaded per-agent via class arrays; there's no central registry for tools contributed by packages
4. **No hook/filter pipeline** — Events handle side-effects well, but there's no mechanism to _transform_ data flowing through the system (modify prompts, tool arguments, responses)
5. **No plugin lifecycle** — No install/activate/deactivate/uninstall hooks
6. **No plugin metadata** — No standardized way to declare plugin name, version, description, dependencies
7. **No plugin configuration merging** — Each plugin would need its own config file; no centralized plugin configuration

---

## Part 2: Proposed Plugin Architecture

### 2.1 Core Plugin Interface

Inspired by Filament's `Plugin` contract — simple, with fluent factory:

```php
<?php

namespace Vizra\VizraADK\Contracts;

interface VizraPluginInterface
{
    /**
     * Unique plugin identifier (e.g., 'vizra-crm-tools').
     */
    public function getId(): string;

    /**
     * Human-readable plugin name.
     */
    public function getName(): string;

    /**
     * Semantic version string.
     */
    public function getVersion(): string;

    /**
     * Short description of what the plugin provides.
     */
    public function getDescription(): string;

    /**
     * Register plugin capabilities (agents, tools, etc.).
     * Called during the service provider register() phase.
     */
    public function register(PluginRegistrar $registrar): void;

    /**
     * Boot the plugin after all plugins are registered.
     * Called during the service provider boot() phase.
     */
    public function boot(PluginRegistrar $registrar): void;

    /**
     * Fluent factory method.
     */
    public static function make(): static;
}
```

### 2.2 Plugin Registrar

The central service that plugins interact with to declare their capabilities:

```php
<?php

namespace Vizra\VizraADK\Services;

class PluginRegistrar
{
    protected array $plugins = [];
    protected array $agents = [];       // class-string[]
    protected array $tools = [];        // class-string[]
    protected array $toolboxes = [];    // class-string[]
    protected array $commands = [];     // class-string[]
    protected array $migrations = [];   // path[]
    protected array $routes = [];       // ['web' => path, 'api' => path]
    protected array $views = [];        // ['namespace' => path]
    protected array $embeddingProviders = [];
    protected array $memoryDrivers = [];
    protected array $eventListeners = []; // [event => [listener, ...]]

    // ─── Plugin Registration ─────────────────────────────

    public function registerPlugin(VizraPluginInterface $plugin): void
    {
        $this->plugins[$plugin->getId()] = $plugin;
    }

    public function getPlugin(string $id): ?VizraPluginInterface
    {
        return $this->plugins[$id] ?? null;
    }

    public function getRegisteredPlugins(): array
    {
        return $this->plugins;
    }

    // ─── Capability Registration ─────────────────────────

    public function registerAgents(array $agentClasses): static
    {
        $this->agents = array_merge($this->agents, $agentClasses);
        return $this;
    }

    public function registerTools(array $toolClasses): static
    {
        $this->tools = array_merge($this->tools, $toolClasses);
        return $this;
    }

    public function registerToolboxes(array $toolboxClasses): static
    {
        $this->toolboxes = array_merge($this->toolboxes, $toolboxClasses);
        return $this;
    }

    public function registerCommands(array $commandClasses): static
    {
        $this->commands = array_merge($this->commands, $commandClasses);
        return $this;
    }

    public function registerMigrations(string $path): static
    {
        $this->migrations[] = $path;
        return $this;
    }

    public function registerRoutes(string $type, string $path): static
    {
        $this->routes[$type][] = $path;
        return $this;
    }

    public function registerViews(string $namespace, string $path): static
    {
        $this->views[$namespace] = $path;
        return $this;
    }

    public function registerEmbeddingProvider(string $name, string $class): static
    {
        $this->embeddingProviders[$name] = $class;
        return $this;
    }

    public function registerMemoryDriver(string $name, string $class): static
    {
        $this->memoryDrivers[$name] = $class;
        return $this;
    }

    // ─── Getters (used by AgentServiceProvider to wire everything) ───

    public function getAgents(): array { return $this->agents; }
    public function getTools(): array { return $this->tools; }
    public function getToolboxes(): array { return $this->toolboxes; }
    public function getCommands(): array { return $this->commands; }
    public function getMigrations(): array { return $this->migrations; }
    public function getRoutes(): array { return $this->routes; }
    public function getViews(): array { return $this->views; }
    public function getEmbeddingProviders(): array { return $this->embeddingProviders; }
    public function getMemoryDrivers(): array { return $this->memoryDrivers; }
}
```

### 2.3 Base Plugin Service Provider (Statamic-style Declarative)

A base class that plugin authors extend. Provides both declarative (array) and programmatic registration:

```php
<?php

namespace Vizra\VizraADK\Providers;

use Illuminate\Support\ServiceProvider;
use Vizra\VizraADK\Contracts\VizraPluginInterface;
use Vizra\VizraADK\Services\PluginRegistrar;

abstract class VizraPluginServiceProvider extends ServiceProvider
{
    /**
     * The plugin instance (implement VizraPluginInterface).
     * Override this in your plugin's service provider.
     */
    protected ?string $plugin = null;

    // ─── Declarative Registration (Statamic-style) ─────

    /** @var array<class-string> Agent classes to register */
    protected array $agents = [];

    /** @var array<class-string<ToolInterface>> Tool classes to register */
    protected array $tools = [];

    /** @var array<class-string<ToolboxInterface>> Toolbox classes to register */
    protected array $toolboxes = [];

    /** @var array<class-string> Artisan command classes */
    protected array $commands = [];

    /** @var string|null Path to migrations directory */
    protected ?string $migrationPath = null;

    /** @var array Route files: ['web' => path, 'api' => path] */
    protected array $routes = [];

    /** @var array Views: ['namespace' => path] */
    protected array $views = [];

    /** @var array Event listeners: [EventClass => [ListenerClass, ...]] */
    protected array $listen = [];

    // ─── Lifecycle ──────────────────────────────────────

    public function register(): void
    {
        $registrar = $this->app->make(PluginRegistrar::class);

        // Register the plugin instance if defined
        if ($this->plugin && class_exists($this->plugin)) {
            $plugin = $this->plugin::make();
            $registrar->registerPlugin($plugin);
            $plugin->register($registrar);
        }

        // Register declared capabilities
        if (!empty($this->agents)) {
            $registrar->registerAgents($this->agents);
        }
        if (!empty($this->tools)) {
            $registrar->registerTools($this->tools);
        }
        if (!empty($this->toolboxes)) {
            $registrar->registerToolboxes($this->toolboxes);
        }
        if (!empty($this->commands)) {
            $registrar->registerCommands($this->commands);
        }
        if ($this->migrationPath) {
            $registrar->registerMigrations($this->migrationPath);
        }
        foreach ($this->routes as $type => $path) {
            $registrar->registerRoutes($type, $path);
        }
        foreach ($this->views as $namespace => $path) {
            $registrar->registerViews($namespace, $path);
        }

        // Allow subclasses to register additional services
        $this->registerPlugin();
    }

    public function boot(): void
    {
        $registrar = $this->app->make(PluginRegistrar::class);

        // Boot the plugin instance if defined
        if ($this->plugin && class_exists($this->plugin)) {
            $plugin = $registrar->getPlugin($this->plugin::make()->getId());
            $plugin?->boot($registrar);
        }

        // Register event listeners
        foreach ($this->listen as $event => $listeners) {
            foreach ($listeners as $listener) {
                \Illuminate\Support\Facades\Event::listen($event, $listener);
            }
        }

        // Allow subclasses to perform additional boot logic
        $this->bootPlugin();
    }

    /**
     * Override in subclass for custom registration logic.
     */
    protected function registerPlugin(): void {}

    /**
     * Override in subclass for custom boot logic.
     */
    protected function bootPlugin(): void {}
}
```

### 2.4 Hook/Filter Pipeline (WordPress-inspired)

A data transformation pipeline that lets plugins modify data as it flows through the system. This is complementary to Laravel's event system (which handles side-effects) — filters handle transformations:

```php
<?php

namespace Vizra\VizraADK\Services;

class HookManager
{
    /** @var array<string, array<array{callback: callable, priority: int}>> */
    protected array $filters = [];

    /** @var array<string, array<array{callback: callable, priority: int}>> */
    protected array $actions = [];

    // ─── Filters (transform data) ───────────────────────

    /**
     * Register a filter to transform data at a hook point.
     */
    public function addFilter(string $hook, callable $callback, int $priority = 10): void
    {
        $this->filters[$hook][] = [
            'callback' => $callback,
            'priority' => $priority,
        ];
    }

    /**
     * Apply all registered filters to a value.
     * Each filter receives the current value and returns the modified value.
     */
    public function applyFilters(string $hook, mixed $value, mixed ...$args): mixed
    {
        if (!isset($this->filters[$hook])) {
            return $value;
        }

        $filters = $this->filters[$hook];
        usort($filters, fn($a, $b) => $a['priority'] <=> $b['priority']);

        foreach ($filters as $filter) {
            $value = ($filter['callback'])($value, ...$args);
        }

        return $value;
    }

    // ─── Actions (side-effects) ─────────────────────────

    /**
     * Register an action callback at a hook point.
     */
    public function addAction(string $hook, callable $callback, int $priority = 10): void
    {
        $this->actions[$hook][] = [
            'callback' => $callback,
            'priority' => $priority,
        ];
    }

    /**
     * Execute all registered actions for a hook.
     */
    public function doAction(string $hook, mixed ...$args): void
    {
        if (!isset($this->actions[$hook])) {
            return;
        }

        $actions = $this->actions[$hook];
        usort($actions, fn($a, $b) => $a['priority'] <=> $b['priority']);

        foreach ($actions as $action) {
            ($action['callback'])(...$args);
        }
    }

    /**
     * Check if a hook has any registered filters/actions.
     */
    public function hasHook(string $hook): bool
    {
        return isset($this->filters[$hook]) || isset($this->actions[$hook]);
    }

    /**
     * Remove all callbacks for a hook.
     */
    public function removeHook(string $hook): void
    {
        unset($this->filters[$hook], $this->actions[$hook]);
    }
}
```

#### Proposed Hook Points

These are the strategic points where plugins can intercept and transform data:

```php
// ─── Agent Execution Pipeline ─────────────────────────

// Filter: Modify the system prompt before it's sent to the LLM
// Signature: fn(string $prompt, BaseLlmAgent $agent, AgentContext $context): string
'vizra.agent.system_prompt'

// Filter: Modify user input before the agent processes it
// Signature: fn(string $input, BaseLlmAgent $agent, AgentContext $context): string
'vizra.agent.user_input'

// Filter: Modify/select which tools are available to the agent for this execution
// Signature: fn(array $tools, BaseLlmAgent $agent, AgentContext $context): array
'vizra.agent.available_tools'

// Filter: Modify the LLM response before it's returned
// Signature: fn(string $response, BaseLlmAgent $agent, AgentContext $context): string
'vizra.agent.response'

// Filter: Modify generation parameters (temperature, max_tokens, etc.)
// Signature: fn(array $params, BaseLlmAgent $agent): array
'vizra.agent.generation_params'

// ─── Tool Execution Pipeline ──────────────────────────

// Filter: Modify tool arguments before execution
// Signature: fn(array $args, ToolInterface $tool, AgentContext $context): array
'vizra.tool.arguments'

// Filter: Modify tool result before returning to LLM
// Signature: fn(string $result, ToolInterface $tool, array $args): string
'vizra.tool.result'

// ─── Memory Pipeline ─────────────────────────────────

// Filter: Modify memory context before injection into prompt
// Signature: fn(string $context, string $agentName, string $userId): string
'vizra.memory.context'

// Filter: Modify data before it's stored in memory
// Signature: fn(array $data, string $agentName): array
'vizra.memory.before_store'

// ─── Discovery Pipeline ──────────────────────────────

// Filter: Modify discovered agents list (add/remove/replace)
// Signature: fn(array $agents): array
'vizra.discovery.agents'

// Filter: Modify discovered tools list
// Signature: fn(array $tools, BaseLlmAgent $agent): array
'vizra.discovery.tools'
```

### 2.5 Enhanced Agent Discovery (Multi-Namespace)

Extend `AgentDiscovery` to scan multiple namespaces, including those registered by plugins:

```php
// In AgentDiscovery, modify scanForAgents():

protected function scanForAgents(): array
{
    $agents = [];

    // 1. Scan the application's agent namespace (existing behavior)
    $appNamespace = config('vizra-adk.namespaces.agents', 'App\\Agents');
    $agents = array_merge($agents, $this->scanNamespace($appNamespace));

    // 2. Scan plugin-registered agent namespaces
    $registrar = app(PluginRegistrar::class);
    foreach ($registrar->getAgents() as $agentClass) {
        if ($this->isValidAgentClass($agentClass)) {
            $agentName = $this->getAgentName($agentClass);
            if ($agentName) {
                $agents[$agentClass] = $agentName;
            }
        }
    }

    // 3. Apply discovery filter (let plugins add/modify agents)
    $hookManager = app(HookManager::class);
    $agents = $hookManager->applyFilters('vizra.discovery.agents', $agents);

    return $agents;
}
```

### 2.6 Tool Registry (New)

A central registry for tools contributed by plugins, separate from per-agent tool arrays:

```php
<?php

namespace Vizra\VizraADK\Services;

use Vizra\VizraADK\Contracts\ToolInterface;

class ToolRegistry
{
    /** @var array<string, class-string<ToolInterface>> name => class */
    protected array $tools = [];

    /** @var array<string, string[]> tag => [tool names] */
    protected array $tags = [];

    public function register(string $toolClass): void
    {
        $instance = app($toolClass);
        $definition = $instance->definition();
        $this->tools[$definition['name']] = $toolClass;
    }

    public function registerWithTag(string $toolClass, string $tag): void
    {
        $this->register($toolClass);
        $instance = app($toolClass);
        $name = $instance->definition()['name'];
        $this->tags[$tag][] = $name;
    }

    public function get(string $name): ?string
    {
        return $this->tools[$name] ?? null;
    }

    public function getByTag(string $tag): array
    {
        $names = $this->tags[$tag] ?? [];
        return array_intersect_key($this->tools, array_flip($names));
    }

    public function all(): array
    {
        return $this->tools;
    }

    public function has(string $name): bool
    {
        return isset($this->tools[$name]);
    }
}
```

### 2.7 Composer-Based Plugin Discovery

Plugins declare themselves in their `composer.json`:

```json
{
    "name": "vizra/crm-plugin",
    "description": "CRM tools and agents for Vizra ADK",
    "type": "vizra-adk-plugin",
    "require": {
        "vizra/vizra-adk": "^0.1.0"
    },
    "extra": {
        "laravel": {
            "providers": [
                "Vizra\\CRM\\CRMPluginServiceProvider"
            ]
        },
        "vizra-adk": {
            "plugin-class": "Vizra\\CRM\\CRMPlugin",
            "provides": {
                "agents": ["crm_assistant", "lead_scorer"],
                "tools": ["lead_lookup", "deal_update", "contact_search"],
                "toolboxes": ["crm_toolbox"]
            }
        }
    }
}
```

The `extra.vizra-adk` section serves as metadata — it allows the ADK to enumerate what's available before instantiation and can be used for the dashboard to show plugin info.

---

## Part 3: What a Plugin Looks Like

### 3.1 Complete Plugin Example

Here's what a full CRM plugin package would look like:

```
vizra-crm-plugin/
├── composer.json
├── config/
│   └── vizra-crm.php
├── database/
│   └── migrations/
│       └── 2025_01_01_create_crm_leads_table.php
├── routes/
│   └── api.php
├── src/
│   ├── CRMPlugin.php                  # Plugin manifest
│   ├── CRMPluginServiceProvider.php   # Service provider
│   ├── Agents/
│   │   ├── CRMAssistantAgent.php
│   │   └── LeadScorerAgent.php
│   ├── Tools/
│   │   ├── LeadLookupTool.php
│   │   ├── DealUpdateTool.php
│   │   └── ContactSearchTool.php
│   ├── Toolboxes/
│   │   └── CRMToolbox.php
│   ├── Memory/
│   │   └── CRMContextProvider.php
│   └── Listeners/
│       └── EnrichAgentContext.php
└── tests/
    └── ...
```

#### `CRMPlugin.php` — The Plugin Manifest

```php
<?php

namespace Vizra\CRM;

use Vizra\VizraADK\Contracts\VizraPluginInterface;
use Vizra\VizraADK\Services\PluginRegistrar;

class CRMPlugin implements VizraPluginInterface
{
    protected bool $leadScoringEnabled = true;
    protected string $defaultModel = 'gpt-4o';

    public function getId(): string
    {
        return 'vizra-crm';
    }

    public function getName(): string
    {
        return 'Vizra CRM Plugin';
    }

    public function getVersion(): string
    {
        return '1.0.0';
    }

    public function getDescription(): string
    {
        return 'CRM integration tools and agents for Vizra ADK';
    }

    // ─── Fluent Configuration ───────────────────────────

    public static function make(): static
    {
        return new static();
    }

    public function enableLeadScoring(bool $enabled = true): static
    {
        $this->leadScoringEnabled = $enabled;
        return $this;
    }

    public function model(string $model): static
    {
        $this->defaultModel = $model;
        return $this;
    }

    public function isLeadScoringEnabled(): bool
    {
        return $this->leadScoringEnabled;
    }

    public function getDefaultModel(): string
    {
        return $this->defaultModel;
    }

    // ─── Registration ───────────────────────────────────

    public function register(PluginRegistrar $registrar): void
    {
        $registrar->registerAgents([
            Agents\CRMAssistantAgent::class,
        ]);

        if ($this->leadScoringEnabled) {
            $registrar->registerAgents([
                Agents\LeadScorerAgent::class,
            ]);
        }

        $registrar->registerTools([
            Tools\LeadLookupTool::class,
            Tools\DealUpdateTool::class,
            Tools\ContactSearchTool::class,
        ]);

        $registrar->registerToolboxes([
            Toolboxes\CRMToolbox::class,
        ]);
    }

    public function boot(PluginRegistrar $registrar): void
    {
        // Register hooks/filters
        $hooks = app(\Vizra\VizraADK\Services\HookManager::class);

        // Enrich agent context with CRM data
        $hooks->addFilter('vizra.agent.system_prompt', function (string $prompt, $agent, $context) {
            if ($context->has('crm_lead_id')) {
                $lead = $this->fetchLeadContext($context->get('crm_lead_id'));
                $prompt .= "\n\nCRM Lead Context:\n" . $lead;
            }
            return $prompt;
        });
    }
}
```

#### `CRMPluginServiceProvider.php` — The Declarative Service Provider

```php
<?php

namespace Vizra\CRM;

use Vizra\VizraADK\Providers\VizraPluginServiceProvider;

class CRMPluginServiceProvider extends VizraPluginServiceProvider
{
    protected ?string $plugin = CRMPlugin::class;

    protected array $agents = [
        Agents\CRMAssistantAgent::class,
    ];

    protected array $tools = [
        Tools\LeadLookupTool::class,
        Tools\DealUpdateTool::class,
        Tools\ContactSearchTool::class,
    ];

    protected array $toolboxes = [
        Toolboxes\CRMToolbox::class,
    ];

    protected array $commands = [
        // Console\SyncCRMCommand::class,
    ];

    protected ?string $migrationPath = __DIR__ . '/../database/migrations';

    protected array $routes = [
        'api' => __DIR__ . '/../routes/api.php',
    ];

    protected array $listen = [
        \Vizra\VizraADK\Events\AgentExecutionStarting::class => [
            Listeners\EnrichAgentContext::class,
        ],
    ];
}
```

### 3.2 Consumer Configuration

In the host Laravel application's `config/vizra-adk.php`:

```php
return [
    // ... existing config ...

    'plugins' => [
        \Vizra\CRM\CRMPlugin::make()
            ->enableLeadScoring()
            ->model('gpt-4o-mini'),

        \Vizra\RAG\DocumentPlugin::make()
            ->chunkSize(512)
            ->embeddingProvider('openai'),

        \Vizra\Slack\SlackPlugin::make()
            ->channel('#ai-agents'),
    ],
];
```

---

## Part 4: Changes to Core Vizra ADK

### 4.1 New Files to Create

| File | Purpose |
|---|---|
| `src/Contracts/VizraPluginInterface.php` | Core plugin contract |
| `src/Services/PluginRegistrar.php` | Central capability registry |
| `src/Services/PluginManager.php` | Plugin lifecycle management |
| `src/Services/HookManager.php` | Hook/filter pipeline |
| `src/Services/ToolRegistry.php` | Central tool registry |
| `src/Providers/VizraPluginServiceProvider.php` | Base class for plugin service providers |
| `src/Console/Commands/PluginListCommand.php` | `vizra:plugins` — list installed plugins |
| `src/Console/Commands/MakePluginCommand.php` | `vizra:make:plugin` — scaffold a new plugin |

### 4.2 Modifications to Existing Files

#### `AgentServiceProvider.php`

- Register `PluginRegistrar` and `HookManager` as singletons
- Register `ToolRegistry` as a singleton
- In `boot()`, iterate config `plugins` array and call `register()` then `boot()` on each
- In `discoverAgents()`, also register agents from `PluginRegistrar`
- Load migrations, routes, views, commands from all plugins

#### `AgentDiscovery.php`

- Accept additional namespaces from `PluginRegistrar::getAgents()`
- Apply `vizra.discovery.agents` filter after scanning

#### `BaseLlmAgent.php`

- In `loadTools()`, also load globally-registered tools from `ToolRegistry` that match agent config
- Apply `vizra.agent.available_tools` filter before returning tools
- Apply `vizra.agent.system_prompt` filter before sending to LLM
- Apply `vizra.agent.response` filter before returning response

#### `config/vizra-adk.php`

- Add `'plugins' => []` configuration key
- Add `'hooks' => ['enabled' => true]` configuration key

### 4.3 Integration Points in Agent Execution

```
User Input
    │
    ▼
┌─────────────────────────────────────────┐
│  Filter: vizra.agent.user_input         │  ← Plugins can modify input
└─────────────────────────────────────────┘
    │
    ▼
┌─────────────────────────────────────────┐
│  Filter: vizra.agent.system_prompt      │  ← Plugins can enrich prompt
└─────────────────────────────────────────┘
    │
    ▼
┌─────────────────────────────────────────┐
│  Filter: vizra.agent.available_tools    │  ← Plugins can add/remove tools
└─────────────────────────────────────────┘
    │
    ▼
┌─────────────────────────────────────────┐
│  Filter: vizra.agent.generation_params  │  ← Plugins can tune LLM params
└─────────────────────────────────────────┘
    │
    ▼
┌─────────────────────────────────────────┐
│  LLM Call (Prism PHP)                   │
│  Event: LlmCallInitiating              │
│  Event: LlmResponseReceived            │
└─────────────────────────────────────────┘
    │
    ▼
┌─────────────────────────────────────────┐  (if tool calls)
│  Filter: vizra.tool.arguments           │  ← Plugins can modify args
│  Tool Execution                         │
│  Filter: vizra.tool.result              │  ← Plugins can modify result
│  Event: ToolCallCompleted               │
└─────────────────────────────────────────┘
    │
    ▼
┌─────────────────────────────────────────┐
│  Filter: vizra.agent.response           │  ← Plugins can modify response
└─────────────────────────────────────────┘
    │
    ▼
┌─────────────────────────────────────────┐
│  Filter: vizra.memory.before_store      │  ← Plugins can modify stored data
│  Memory Persistence                     │
│  Event: MemoryUpdated                   │
└─────────────────────────────────────────┘
    │
    ▼
Final Response to User
```

---

## Part 5: Plugin Types & Use Cases

### 5.1 Tool Pack Plugins

Packages of reusable tools that any agent can use:

```php
// vizra/database-tools
class DatabaseToolsPlugin implements VizraPluginInterface
{
    public function register(PluginRegistrar $registrar): void
    {
        $registrar->registerTools([
            QueryDatabaseTool::class,
            InsertRecordTool::class,
            UpdateRecordTool::class,
            SchemaInspectorTool::class,
        ]);

        $registrar->registerToolboxes([
            DatabaseToolbox::class, // Grouped with authorization
        ]);
    }
}
```

### 5.2 Agent Pack Plugins

Pre-built agents for specific domains:

```php
// vizra/customer-support-agents
class CustomerSupportPlugin implements VizraPluginInterface
{
    public function register(PluginRegistrar $registrar): void
    {
        $registrar->registerAgents([
            TriageAgent::class,       // Routes inquiries
            FAQAgent::class,          // Answers common questions
            EscalationAgent::class,   // Escalates to human
            SentimentAgent::class,    // Analyzes customer sentiment
        ]);
    }
}
```

### 5.3 Infrastructure Plugins

Extend core capabilities like memory, embeddings, or observability:

```php
// vizra/pinecone-memory
class PineconeMemoryPlugin implements VizraPluginInterface
{
    public function register(PluginRegistrar $registrar): void
    {
        $registrar->registerMemoryDriver('pinecone', PineconeVectorDriver::class);
        $registrar->registerEmbeddingProvider('voyage', VoyageEmbeddingProvider::class);
    }
}
```

### 5.4 Integration Plugins

Connect agents to external services:

```php
// vizra/slack-integration
class SlackPlugin implements VizraPluginInterface
{
    public function boot(PluginRegistrar $registrar): void
    {
        $hooks = app(HookManager::class);

        // Forward agent responses to Slack
        $hooks->addAction('vizra.agent.after_execute', function ($agent, $response, $context) {
            if ($context->has('slack_channel')) {
                SlackNotifier::send($context->get('slack_channel'), $response);
            }
        });
    }
}
```

### 5.5 Middleware/Guard Plugins

Modify agent behavior across the board:

```php
// vizra/content-safety
class ContentSafetyPlugin implements VizraPluginInterface
{
    public function boot(PluginRegistrar $registrar): void
    {
        $hooks = app(HookManager::class);

        // Screen user input
        $hooks->addFilter('vizra.agent.user_input', function (string $input) {
            return ContentModerator::screen($input);
        }, priority: 1); // Run first

        // Screen agent responses
        $hooks->addFilter('vizra.agent.response', function (string $response) {
            return ContentModerator::screen($response);
        }, priority: 99); // Run last
    }
}
```

---

## Part 6: Implementation Phases

### Phase 1: Foundation

- [ ] Create `VizraPluginInterface` contract
- [ ] Create `PluginRegistrar` service
- [ ] Create `HookManager` service
- [ ] Create `ToolRegistry` service
- [ ] Register new services in `AgentServiceProvider`
- [ ] Add `plugins` key to config
- [ ] Write tests for all new services

### Phase 2: Integration

- [ ] Create `VizraPluginServiceProvider` base class
- [ ] Modify `AgentDiscovery` to accept plugin-registered agents
- [ ] Modify `BaseLlmAgent` to apply hook filters at key points
- [ ] Load plugin migrations, routes, views, commands
- [ ] Wire `ToolRegistry` into agent tool loading
- [ ] Write integration tests

### Phase 3: Developer Experience

- [ ] Create `vizra:make:plugin` command (scaffolds a plugin package)
- [ ] Create `vizra:plugins` command (lists installed plugins and their capabilities)
- [ ] Add plugin info to the web dashboard
- [ ] Write documentation and examples
- [ ] Create a starter plugin template repository

### Phase 4: Ecosystem

- [ ] Define plugin quality standards and testing requirements
- [ ] Create a plugin compatibility matrix
- [ ] Establish versioning and compatibility conventions
- [ ] Consider a plugin marketplace/directory

---

## Part 7: Design Decisions & Trade-offs

### Why Both Events and Hooks?

Laravel events and the HookManager serve different purposes:

| Feature | Laravel Events | HookManager Filters |
|---|---|---|
| **Purpose** | Side-effects (logging, notifications) | Data transformation |
| **Return value** | Ignored | Required — data flows through |
| **Pattern** | Observer | Pipeline |
| **Existing usage** | 12 events already in codebase | New capability |
| **Priority control** | Via listener order in `EventServiceProvider` | Explicit numeric priority |

Both are needed. Events tell you _something happened_. Filters let you _change what happens_.

### Why Not Just Laravel Package Auto-Discovery?

Laravel's auto-discovery (via `composer.json` `extra.laravel.providers`) already works for registering service providers. The plugin system builds _on top of_ this — it adds:

1. A formal capability declaration (what agents/tools does this package provide?)
2. A central registry (what's installed? what's available?)
3. Fluent configuration (configure plugin behavior in one place)
4. Hook/filter pipeline (modify data flows, not just observe them)
5. Dashboard visibility (see all plugins and their status)

### Why Composer `type: vizra-adk-plugin`?

The custom Composer package type enables:
- Querying installed plugins via `InstalledVersions::getInstalledPackagesByType('vizra-adk-plugin')`
- Differentiating Vizra plugins from regular Laravel packages
- Future: custom Composer plugin for post-install scaffolding

### Performance Considerations

- **HookManager**: Minimal overhead. Filters only execute when hook points are defined. Empty hooks return immediately. Priority sorting is O(n log n) but n is typically < 10.
- **PluginRegistrar**: Populated at boot time only. All subsequent access is array lookups.
- **ToolRegistry**: One-time registration at boot. Tool instantiation is lazy (via container).
- **Agent Discovery**: Already cached in production. Plugin agents are added to the same cache.

---

## Part 8: Comparison with Existing MCP

MCP (Model Context Protocol) and the plugin system serve different purposes:

| Aspect | MCP | Plugin System |
|---|---|---|
| **Scope** | External tool servers | Full framework extension |
| **Transport** | STDIO / HTTP processes | In-process PHP |
| **What it provides** | Tools, Resources, Prompts | Agents, Tools, Toolboxes, Memory, Workflows, Routes, Commands, Views |
| **Configuration** | Per-MCP-server config | Per-plugin fluent API |
| **Lifecycle** | Process start/stop | Composer install/remove |
| **Authorization** | None (trusts the server) | Laravel gates/policies via Toolbox |
| **Performance** | IPC overhead | Native PHP performance |
| **Language** | Any (Python, Node, etc.) | PHP only |

MCP is for connecting to external tool ecosystems. The plugin system is for extending Vizra ADK itself with reusable PHP packages. They complement each other — a plugin could even register MCP server configurations.

---

## Part 9: Open Questions

1. **Should plugins be able to override core agents?** If two plugins register an agent with the same name, who wins? Options: last-registered wins, throw an error, or use a priority system.

2. **Should there be a plugin dependency system?** If Plugin B requires Plugin A, should the registrar enforce this? Or rely on Composer's dependency resolution?

3. **How should plugin configuration be persisted?** The current proposal uses in-code fluent config (`CRMPlugin::make()->enableLeadScoring()`). Should there also be a database-backed config for runtime changes?

4. **Should tools from plugins be opt-in per agent?** Currently the proposal registers tools globally. Should agents explicitly include plugin tools, or should all registered tools be available to all agents?

5. **Dashboard integration depth** — Should plugins be able to contribute custom Livewire components to the dashboard? This adds complexity but would enable plugin-specific UIs.

6. **Versioning and compatibility** — How should plugin API versions be managed as Vizra ADK evolves? Semantic versioning of the plugin API contract?
