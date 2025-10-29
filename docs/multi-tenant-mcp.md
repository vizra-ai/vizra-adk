# Multi-Tenant MCP Configuration

This guide explains how to implement dynamic, per-tenant MCP (Model Context Protocol) server configurations in multi-tenant applications using Vizra ADK.

## Overview

Vizra ADK provides a mechanism to override MCP server configurations at runtime through the `AgentContext`. This allows you to use tenant-specific API keys, tokens, and other credentials without modifying global configuration files.

## How It Works

1. **Base Configuration**: Define your MCP servers in `config/vizra-adk.php` with default or placeholder values
2. **Context Overrides**: Pass tenant-specific configuration overrides through `AgentContext`
3. **Runtime Merging**: The framework merges overrides with base config before connecting to MCP servers
4. **Isolation**: Each agent execution gets a fresh `MCPClientManager` instance, preventing credential leakage

## Basic Usage

### Step 1: Configure Base MCP Servers

In your `config/vizra-adk.php`, set up MCP servers with default values:

```php
'mcp_servers' => [
    'github' => [
        'transport' => 'stdio',
        'command' => env('MCP_NPX_PATH', 'npx'),
        'args' => [
            '@modelcontextprotocol/server-github',
            '--token',
            env('GITHUB_TOKEN', ''), // Default fallback
        ],
        'enabled' => true,
        'timeout' => 45,
    ],

    'github_http' => [
        'transport' => 'http',
        'url' => env('MCP_GITHUB_HTTP_URL', 'http://localhost:8001/api/mcp'),
        'api_key' => env('MCP_GITHUB_HTTP_API_KEY'),
        'enabled' => env('MCP_GITHUB_HTTP_ENABLED', false),
        'timeout' => 45,
        'headers' => [],
    ],

    'slack' => [
        'transport' => 'stdio',
        'command' => env('MCP_NPX_PATH', 'npx'),
        'args' => [
            '@modelcontextprotocol/server-slack',
            '--bot-token',
            env('SLACK_BOT_TOKEN', ''),
        ],
        'enabled' => env('MCP_SLACK_ENABLED', false),
        'timeout' => 30,
    ],
],
```

### Step 2: Store Tenant Credentials

Store tenant-specific credentials in your own database. Here's an example model:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Tenant extends Model
{
    protected $fillable = [
        'name',
        'github_token',
        'slack_bot_token',
    ];

    // Optionally use encryption for sensitive data
    protected $casts = [
        'github_token' => 'encrypted',
        'slack_bot_token' => 'encrypted',
    ];
}
```

### Step 3: Create a Configuration Service

Create a service to build MCP configuration overrides for each tenant:

```php
<?php

namespace App\Services;

use App\Models\Tenant;

class TenantMCPConfigService
{
    /**
     * Get MCP configuration overrides for a specific tenant
     */
    public function getOverridesForTenant(int $tenantId): array
    {
        $tenant = Tenant::findOrFail($tenantId);

        $overrides = [];

        // GitHub STDIO configuration
        if ($tenant->github_token) {
            $overrides['github'] = [
                'args' => [
                    '@modelcontextprotocol/server-github',
                    '--token',
                    $tenant->github_token,
                ],
            ];
        }

        // GitHub HTTP configuration
        if ($tenant->github_http_api_key) {
            $overrides['github_http'] = [
                'api_key' => $tenant->github_http_api_key,
                'headers' => [
                    'X-Tenant-ID' => (string) $tenant->id,
                ],
            ];
        }

        // Slack configuration
        if ($tenant->slack_bot_token) {
            $overrides['slack'] = [
                'args' => [
                    '@modelcontextprotocol/server-slack',
                    '--bot-token',
                    $tenant->slack_bot_token,
                ],
            ];
        }

        return $overrides;
    }
}
```

### Step 4: Execute Agent with Tenant Context

Pass the configuration overrides when executing an agent:

```php
use App\Services\TenantMCPConfigService;
use App\Agents\TeamAgent;

// Get tenant-specific MCP configuration
$mcpConfig = app(TenantMCPConfigService::class)
    ->getOverridesForTenant($tenantId);

// Execute agent with tenant-specific configuration
$response = TeamAgent::run($userMessage)
    ->withContext([
        'agent_name' => "team_agent_{$tenantId}", // Isolate sessions per tenant
        'mcp_config_overrides' => $mcpConfig,
        'tenant_id' => $tenantId, // Additional context if needed
    ])
    ->go();
```

## Advanced Examples

### HTTP Transport with Custom Headers

```php
public function getOverridesForTenant(int $tenantId): array
{
    $tenant = Tenant::findOrFail($tenantId);

    return [
        'github_http' => [
            'api_key' => $tenant->github_api_key,
            'headers' => [
                'X-Tenant-ID' => (string) $tenant->id,
                'X-Organization-ID' => $tenant->organization_id,
                'X-Custom-Auth' => $tenant->custom_auth_token,
            ],
        ],
    ];
}
```

### Conditional MCP Server Enabling

```php
public function getOverridesForTenant(int $tenantId): array
{
    $tenant = Tenant::findOrFail($tenantId);

    $overrides = [];

    // Only enable GitHub if tenant has a token
    if ($tenant->github_token) {
        $overrides['github'] = [
            'enabled' => true,
            'args' => [
                '@modelcontextprotocol/server-github',
                '--token',
                $tenant->github_token,
            ],
        ];
    } else {
        $overrides['github'] = [
            'enabled' => false,
        ];
    }

    return $overrides;
}
```

### Multiple MCP Servers

```php
public function getOverridesForTenant(int $tenantId): array
{
    $tenant = Tenant::findOrFail($tenantId);

    return [
        'github' => [
            'args' => [
                '@modelcontextprotocol/server-github',
                '--token',
                $tenant->github_token,
            ],
        ],
        'slack' => [
            'args' => [
                '@modelcontextprotocol/server-slack',
                '--bot-token',
                $tenant->slack_bot_token,
            ],
        ],
        'postgres' => [
            'args' => [
                '@modelcontextprotocol/server-postgres',
                '--connection-string',
                $tenant->database_connection_string,
            ],
        ],
    ];
}
```

### Using in Controllers

```php
<?php

namespace App\Http\Controllers;

use App\Agents\TeamAgent;
use App\Services\TenantMCPConfigService;
use Illuminate\Http\Request;

class AgentController extends Controller
{
    public function chat(Request $request)
    {
        $tenantId = auth()->user()->tenant_id;

        $mcpConfig = app(TenantMCPConfigService::class)
            ->getOverridesForTenant($tenantId);

        $response = TeamAgent::run($request->input('message'))
            ->forUser(auth()->user())
            ->withContext([
                'agent_name' => "team_agent_{$tenantId}",
                'mcp_config_overrides' => $mcpConfig,
            ])
            ->go();

        return response()->json(['response' => $response]);
    }
}
```

### Using with Queued Jobs

```php
<?php

namespace App\Jobs;

use App\Agents\TeamAgent;
use App\Services\TenantMCPConfigService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\SerializesModels;

class ProcessAgentTask implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        private int $tenantId,
        private string $message
    ) {}

    public function handle(): void
    {
        $mcpConfig = app(TenantMCPConfigService::class)
            ->getOverridesForTenant($this->tenantId);

        TeamAgent::run($this->message)
            ->withContext([
                'agent_name' => "team_agent_{$this->tenantId}",
                'mcp_config_overrides' => $mcpConfig,
            ])
            ->async()
            ->go();
    }
}
```

## Configuration Override Format

The `mcp_config_overrides` array uses the same structure as `config/vizra-adk.php`:

```php
[
    'server_name' => [
        // For STDIO transport
        'command' => 'npx',
        'args' => ['@package/server', '--token', 'tenant-token'],
        'timeout' => 30,
        'enabled' => true,

        // For HTTP transport
        'url' => 'https://api.example.com/mcp',
        'api_key' => 'tenant-api-key',
        'headers' => [
            'X-Custom-Header' => 'value',
        ],
    ],
]
```

Overrides are deep-merged with base configuration using `array_replace_recursive()`, so you only need to specify the values you want to change.
