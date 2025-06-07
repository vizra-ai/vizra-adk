# 🚀 Getting Started

Welcome to Laravel Ai ADK! If you're familiar with Laravel, you'll feel right at home. If you're new to AI agents, don't worry—we'll have you building intelligent applications in no time.

## 📋 What You'll Need

Before we dive in, make sure you have:

- **PHP 8.1+** (same as modern Laravel)
- **Laravel 10.0+** (fresh install or existing project)
- **Database** (MySQL, PostgreSQL, SQLite)
- **LLM API Access** (OpenAI, Anthropic, Cohere, or local models)

## 🎯 Installation

### Step 1: Install the Package

```bash
composer require aaronlumsden/laravel-agent-adk
```

### Step 2: Publish Configuration & Migrations

```bash
# Publish config file and migrations
php artisan vendor:publish --provider="AaronLumsden\LaravelAiADK\AgentAdkServiceProvider"

# Run the migrations (creates agent sessions, messages, and memory tables)
php artisan migrate
```

### Step 3: Configure Your LLM Provider

Add your API credentials to `.env`:

```env
# OpenAI (recommended for getting started)
OPENAI_API_KEY=your-openai-api-key-here

# Or use other providers
ANTHROPIC_API_KEY=your-anthropic-key
COHERE_API_KEY=your-cohere-key

# Default settings (optional)
AGENT_ADK_DEFAULT_PROVIDER=openai
AGENT_ADK_DEFAULT_MODEL=gpt-4o-mini
AGENT_ADK_DEFAULT_TEMPERATURE=0.7
```

## 🎉 Your First Agent

Let's create a simple weather agent to see how everything works:

### Step 1: Create the Agent

```bash
php artisan agent:make:agent WeatherAgent
```

This creates `app/Agents/WeatherAgent.php`:

```php
<?php

namespace App\Agents;

use AaronLumsden\LaravelAiADK\Agents\BaseLlmAgent;

class WeatherAgent extends BaseLlmAgent
{
    protected string $instructions = "You are a helpful weather assistant.
    Provide current weather information and forecasts in a friendly,
    conversational manner. Always be specific about locations and
    include relevant details like temperature, conditions, and any
    weather warnings.";

    protected array $tools = [
        // We'll add tools in the next step
    ];
}
```

### Step 2: Test Your Agent

```bash
# Start an interactive chat session
php artisan agent:chat weather_agent

# Or test programmatically
php artisan tinker
```

```php
use App\Agents\WeatherAgent;

// Direct agent execution with fluent API
$response = WeatherAgent::ask('What\'s the weather like today?')->forUser(auth()->user());
echo $response;

// Or using the facade (legacy approach)
use AaronLumsden\LaravelAiADK\Facades\Agent;
$response = Agent::run('weather_agent', 'What\'s the weather like today?');
echo $response;
```

## 🛠️ Adding Tools

Agents become really powerful when they can use tools. Let's add a weather API tool:

### Step 1: Create a Weather Tool

```bash
php artisan agent:make:tool WeatherTool
```

This creates `app/Tools/WeatherTool.php`:

```php
<?php

namespace App\Tools;

use AaronLumsden\LaravelAiADK\Contracts\ToolInterface;
use AaronLumsden\LaravelAiADK\System\AgentContext;
use Illuminate\Support\Facades\Http;

class WeatherTool implements ToolInterface
{
    public function definition(): array
    {
        return [
            'name' => 'get_weather',
            'description' => 'Get current weather conditions for a specific location',
            'parameters' => [
                'type' => 'object',
                'properties' => [
                    'location' => [
                        'type' => 'string',
                        'description' => 'The city and country, e.g. "London, UK"',
                    ],
                ],
                'required' => ['location'],
            ],
        ];
    }

    public function execute(array $arguments, AgentContext $context): string
    {
        $location = $arguments['location'];

        // Call a weather API (you'll need an API key)
        $response = Http::get('https://api.openweathermap.org/data/2.5/weather', [
            'q' => $location,
            'appid' => config('services.openweather.key'),
            'units' => 'metric',
        ]);

        if ($response->failed()) {
            return json_encode([
                'error' => 'Unable to fetch weather data for ' . $location
            ]);
        }

        $data = $response->json();

        return json_encode([
            'location' => $data['name'] . ', ' . $data['sys']['country'],
            'temperature' => round($data['main']['temp']) . '°C',
            'condition' => $data['weather'][0]['description'],
            'humidity' => $data['main']['humidity'] . '%',
            'wind_speed' => $data['wind']['speed'] . ' m/s',
        ]);
    }
}
```

### Step 2: Add the Tool to Your Agent

Update your `WeatherAgent`:

```php
use App\Tools\WeatherTool;

class WeatherAgent extends BaseLlmAgent
{
    protected string $instructions = "You are a helpful weather assistant...";

    protected array $tools = [
        WeatherTool::class,
    ];
}
```

### Step 3: Add Weather API Key

Add to your `.env`:

```env
OPENWEATHER_API_KEY=your-openweather-api-key
```

And to `config/services.php`:

```php
'openweather' => [
    'key' => env('OPENWEATHER_API_KEY'),
],
```

## 🚀 Fluent Agent API

The Laravel AI ADK provides a beautiful fluent API that feels right at home in Laravel applications:

### Basic Usage

```php
use App\Agents\CustomerSupportAgent;

// Simple execution
$response = CustomerSupportAgent::ask('Where is my order?');

// With user context
$response = CustomerSupportAgent::ask('Where is my order?')->forUser($user);

// With custom session
$response = CustomerSupportAgent::ask('Help me with my account')
    ->forUser($user)
    ->withSession('custom-session-id');
```

### Advanced Options

```php
// Add custom context
$response = CustomerSupportAgent::ask('Process this return')
    ->forUser($user)
    ->withContext([
        'order_id' => 'ORD-12345',
        'return_reason' => 'defective_item'
    ]);

// Control model parameters
$response = CustomerSupportAgent::ask('Creative writing task')
    ->forUser($user)
    ->temperature(0.9)
    ->maxTokens(2000);

// Enable streaming
$stream = CustomerSupportAgent::ask('Tell me a long story')
    ->forUser($user)
    ->streaming()
    ->execute();
```

### Multiple Execution Patterns

```php
// Method 1: Fluent API (Recommended)
$response = WeatherAgent::ask('Weather in Tokyo?')->forUser($user);

// Method 2: Traditional Facade
$response = Agent::run('weather_agent', 'Weather in Tokyo?', 'session-id');

// Method 3: Direct Instantiation
$agent = Agent::named('weather_agent');
$response = $agent->run('Weather in Tokyo?', $context);
```

### User Context Benefits

When you use `->forUser($user)`, the agent automatically gets:

- **User identification**: Access to user ID and basic profile
- **Personalized sessions**: Automatic session management per user
- **Context memory**: Remembers previous conversations with this user
- **Permissions**: User-based access controls (if implemented)

```php
// The agent can access user information in tools and instructions
class CustomerSupportAgent extends BaseLlmAgent
{
    protected string $instructions = "
    You are a customer support agent. When helping users:
    - Always address them by name if available
    - Reference their previous orders and preferences
    - Provide personalized recommendations
    ";

    // In your tools, access user context:
    public function beforeProcessing(string $input, AgentContext $context): void
    {
        $userId = $context->getState('user_id');
        $userName = $context->getState('user_name');
        $userEmail = $context->getState('user_email');

        // Load user-specific data, preferences, order history, etc.
    }
}
```

## 🎨 Web Interface

The Laravel Ai ADK comes with a beautiful web interface for testing and monitoring your agents.

### Step 1: Add Routes

In your `routes/web.php`:

```php
use AaronLumsden\LaravelAiADK\AgentAdkRouteServiceProvider;

// The service provider automatically registers routes under /ai-adk
// Visit: http://your-app.test/ai-adk
```

### Step 2: Explore the Interface

Navigate to `http://your-app.test/ai-adk` and you'll see:

- **Dashboard** - Overview of your agents and quick commands
- **Chat Interface** - Interactive conversations with your agents
- **Evaluation Runner** - Test your agents with automated evaluations
- **Analytics** - Performance metrics and usage statistics

## 🔧 Configuration Deep Dive

### Model Configuration

Different models for different needs:

```php
// In your agent class
class FastAgent extends BaseLlmAgent
{
    protected string $model = 'gpt-4o-mini';      // Fast and cheap
    protected float $temperature = 0.3;           // More focused
}

class CreativeAgent extends BaseLlmAgent
{
    protected string $model = 'gpt-4o';           // More capable
    protected float $temperature = 0.9;           // More creative
    protected int $maxTokens = 2000;              // Longer responses
}
```

### Global Defaults

In `config/agent-adk.php`:

```php
'default_generation_params' => [
    'temperature' => 0.7,
    'max_tokens' => 1000,
    'top_p' => null,
],

'default_provider' => 'openai',
'default_model' => 'gpt-4o-mini',
```

## 🎯 What's Next?

Now that you have a basic agent running, here's what to explore next:

### 🧠 **Make Agents Smarter**

- [Agent Development Guide](agents.md) - Advanced instructions, memory, and context
- [Tools & Capabilities](tools.md) - Database queries, API calls, file operations

### 📊 **Test & Evaluate**

- [Evaluation & Testing](evaluation.md) - LLM-as-a-judge, automated testing
- Create evaluation datasets to measure agent performance

### 💾 **Add Memory**

- [Vector Memory & RAG](vector-memory.md) - Long-term memory and knowledge retrieval
- Give agents persistent knowledge and context

### 🚀 **Deploy & Scale**

- [Configuration](configuration.md) - Production settings and optimization
- [Deployment](deployment.md) - Laravel Forge, Docker, and cloud deployment

## 🆘 Troubleshooting

### Common Issues

**"Agent not found" error:**

```bash
# Make sure you've registered your agent
php artisan agent:list
```

**API key errors:**

```bash
# Check your .env file and test the connection
php artisan agent:test-connection openai
```

**Database errors:**

```bash
# Ensure migrations are run
php artisan migrate:status
php artisan migrate
```

**Tool not working:**

```php
// Test tools individually
$tool = new WeatherTool();
$result = $tool->execute(['location' => 'London'], new AgentContext());
echo $result;
```

## 🤝 Getting Help

Stuck? We're here to help:

- 📚 **[Documentation](../README.md#-documentation)** - Comprehensive guides
- 💬 **[GitHub Discussions](https://github.com/aaronlumsden/laravel-agent-adk/discussions)** - Community Q&A
- 🐛 **[Issues](https://github.com/aaronlumsden/laravel-agent-adk/issues)** - Bug reports and feature requests
- 📧 **[Email Support](mailto:support@laravel-agent-adk.com)** - Direct help

---

<p align="center">
<strong>Ready to build something amazing?</strong><br>
<a href="agents.md">Next: Agent Development Guide →</a>
</p>
