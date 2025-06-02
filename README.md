# Laravel Agent ADK 🤖 (Agent Development Kit)

**Laravel Agent ADK** is a powerful PHP package that simplifies building AI agents with Laravel. Think of it as your AI agent's foundation - it handles the complex infrastructure so you can focus on building intelligent systems that actually work.

## Table of Contents 📋

- [Quick Start](#quick-start-)
- [What's This All About?](#whats-this-all-about-)
- [Why Choose Laravel Agent ADK?](#why-choose-laravel-agent-adk-)
- [Requirements](#requirements-)
- [Installation & Setup](#installation--setup-)
- [Core Features](#core-features-)
- [Building Your First Agent](#building-your-first-agent-)
- [Advanced Features](#advanced-features-)
  - [Tool System](#tool-system)
  - [Generation Parameters](#generation-parameters)
  - [Event System](#event-system)
  - [Error Handling](#error-handling)
- [Evaluations](#evaluations)
- [Configuration](#configuration)
- [Security Best Practices](#security-best-practices)
- [Performance Considerations](#performance-considerations)
- [Testing](#testing)
- [Deployment](#deployment)
- [Troubleshooting](#troubleshooting-)
- [Contributing](#contributing-)

## Quick Start ⚡

Get up and running in 5 minutes:

```bash
# Install the package
composer require aaronlumsden/laravel-agent-adk

# Set up everything
php artisan agent:install

# Run migrations
php artisan migrate

# Add your API key to .env
echo "OPENAI_API_KEY=your_key_here" >> .env

# Create your first agent
php artisan agent:make:agent ChatBot

# Test it immediately
php artisan agent:chat chat_bot
```

## What's This All About? 🤔

AI agents are autonomous digital assistants that can think, decide, and take action. With Laravel Agent ADK, you get to build these intelligent helpers using familiar Laravel patterns and without the typical AI integration headaches.

**Key Capabilities:**

- **Smart Conversation Management**: Automatic context and history tracking
- **Tool Integration**: Let agents use APIs, databases, and external services
- **Multi-LLM Support**: OpenAI, Anthropic, Google Gemini through [Prism-PHP](https://prismphp.com/)
- **Quality Assurance**: Built-in evaluation system with LLM-as-a-Judge
- **Laravel Native**: Events, service providers, Artisan commands - it all just works

## Why Choose Laravel Agent ADK? 🌟

| Feature                 | Laravel Agent ADK | DIY Approach       |
| ----------------------- | ----------------- | ------------------ |
| **Setup Time**          | 5 minutes         | Hours/Days         |
| **State Management**    | Automatic         | Manual complexity  |
| **Multi-LLM Support**   | Built-in          | Custom integration |
| **Tool System**         | Declarative       | Imperative coding  |
| **Quality Testing**     | LLM evaluations   | Manual testing     |
| **Laravel Integration** | Native            | Custom glue code   |

## Requirements 📋

- **PHP**: 8.1 or higher
- **Laravel**: 10.0 or higher
- **LLM Provider**: At least one API key for:
  - OpenAI (GPT models)
  - Anthropic (Claude models)
  - Google (Gemini models)

## Installation & Setup 🚀

### 1. Install the Package

```bash
composer require aaronlumsden/laravel-agent-adk
```

### 2. Initialize the Package

```bash
php artisan agent:install
```

This command:

- Publishes the configuration file to `config/agent-adk.php`
- Creates database migrations for sessions and context storage
- Sets up the directory structure

### 3. Run Migrations

```bash
php artisan migrate
```

### 4. Configure Your Environment

Add your LLM API keys to `.env`:

```dotenv
# OpenAI Configuration
OPENAI_API_KEY=sk-your-openai-key-here
OPENAI_URL=https://api.openai.com/v1

# Anthropic Configuration
ANTHROPIC_API_KEY=sk-ant-your-anthropic-key

# Google Gemini Configuration
GEMINI_API_KEY=your-gemini-key-here

# Package Defaults
AGENT_ADK_DEFAULT_PROVIDER=openai
AGENT_ADK_DEFAULT_MODEL=gpt-4o
AGENT_ADK_DEFAULT_TEMPERATURE=0.7
```

## Core Features ✨

- **🏗️ Class-Based Agents**: Extend `BaseLlmAgent` with full IDE support
- **🎨 Fluent Builder**: Quick agent creation with `Agent::define()`
- **🔧 Tool System**: Declarative tool definitions with automatic parameter validation
- **📚 Conversation Memory**: Automatic context and history management
- **🌐 Multi-Provider**: OpenAI, Anthropic, Gemini support via Prism-PHP
- **🎯 Smart Routing**: Automatic tool selection and execution
- **📊 Quality Assurance**: Built-in evaluation framework
- **⚡ Performance**: Optimized for production workloads
- **🔒 Security**: Input validation and sanitization built-in

## Building Your First Agent 🛠️

### 1. Create the Agent Class

```bash
php artisan agent:make:agent CustomerSupportAgent
```

This generates `app/Agents/CustomerSupportAgent.php`:

```php
namespace App\Agents;

use AaronLumsden\LaravelAgentADK\Agents\BaseLlmAgent;
use AaronLumsden\LaravelAgentADK\System\AgentContext;

class CustomerSupportAgent extends BaseLlmAgent
{
    protected string $name = 'customer_support';
    protected string $description = 'Helpful customer service assistant';

    protected string $instructions = 'You are a friendly customer service agent. Be helpful, professional, and concise. Always ask clarifying questions when needed.';

    protected string $model = 'gpt-4o';
    protected ?float $temperature = 0.3; // Lower temperature for consistency
    protected ?int $maxTokens = 500;

    protected function registerTools(): array
    {
        return [
            \App\Tools\OrderLookupTool::class,
            \App\Tools\RefundProcessorTool::class,
        ];
    }

    // Optional: Customize behavior with hooks
    public function beforeLlmCall(array $inputMessages, AgentContext $context): array
    {
        // Add customer context if available
        if ($customerId = $context->getState('customer_id')) {
            $context->setState('customer_tier', $this->getCustomerTier($customerId));
        }

        return $inputMessages;
    }

    private function getCustomerTier(string $customerId): string
    {
        // Your business logic here
        return 'premium';
    }
}
```

### 2. Register Your Agent

In `app/Providers/AppServiceProvider.php`:

```php
use AaronLumsden\LaravelAgentADK\Facades\Agent;
use App\Agents\CustomerSupportAgent;

public function boot(): void
{
    Agent::build(CustomerSupportAgent::class)->register();

    // Or create simple agents on-the-fly
    Agent::define('greeter')
         ->description('Friendly greeting agent')
         ->instructions('Greet users warmly and ask how you can help. Keep it under 30 words.')
         ->model('gpt-4o-mini')
         ->temperature(0.8)
         ->register();
}
```

### 3. Use Your Agent

```php
use AaronLumsden\LaravelAgentADK\Facades\Agent;

// In a controller
public function chat(Request $request)
{
    $input = $request->validated()['message'];
    $sessionId = $request->session()->getId();

    try {
        $response = Agent::run('customer_support', $input, $sessionId);
        return response()->json(['reply' => $response]);
    } catch (\Exception $e) {
        return response()->json(['error' => 'Service temporarily unavailable'], 503);
    }
}

// Or test directly in terminal
// php artisan agent:chat customer_support
```

## Advanced Features 🚀

### Tool System

Tools extend your agent's capabilities beyond text generation. Here's a real-world example:

```bash
php artisan agent:make:tool WeatherApiTool
```

```php
namespace App\Tools;

use AaronLumsden\LaravelAgentADK\Contracts\ToolInterface;
use AaronLumsden\LaravelAgentADK\System\AgentContext;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WeatherApiTool implements ToolInterface
{
    public function definition(): array
    {
        return [
            'name' => 'get_weather',
            'description' => 'Get current weather conditions for any city worldwide',
            'parameters' => [
                'type' => 'object',
                'properties' => [
                    'city' => [
                        'type' => 'string',
                        'description' => 'City name (e.g., "London" or "New York, NY")',
                    ],
                    'country' => [
                        'type' => 'string',
                        'description' => 'Country code (optional, e.g., "GB", "US")',
                    ],
                    'units' => [
                        'type' => 'string',
                        'enum' => ['metric', 'imperial'],
                        'description' => 'Temperature units',
                        'default' => 'metric'
                    ]
                ],
                'required' => ['city'],
            ],
        ];
    }

    public function execute(array $arguments, AgentContext $context): string
    {
        $city = $arguments['city'];
        $country = $arguments['country'] ?? '';
        $units = $arguments['units'] ?? 'metric';

        // Build location string
        $location = $country ? "{$city},{$country}" : $city;

        try {
            $response = Http::timeout(10)->get('https://api.openweathermap.org/data/2.5/weather', [
                'q' => $location,
                'appid' => config('services.openweather.key'),
                'units' => $units
            ]);

            if (!$response->successful()) {
                return "Sorry, I couldn't fetch weather data for {$city}. Please check the city name.";
            }

            $data = $response->json();

            return json_encode([
                'location' => $data['name'] . ', ' . $data['sys']['country'],
                'temperature' => $data['main']['temp'] . '°' . ($units === 'metric' ? 'C' : 'F'),
                'description' => ucfirst($data['weather'][0]['description']),
                'humidity' => $data['main']['humidity'] . '%',
                'wind_speed' => $data['wind']['speed'] . ($units === 'metric' ? ' m/s' : ' mph')
            ]);

        } catch (\Exception $e) {
            Log::error('Weather API error', ['error' => $e->getMessage(), 'city' => $city]);
            return "I'm having trouble accessing weather data right now. Please try again later.";
        }
    }
}
```

### Generation Parameters

Fine-tune your agent's response style with these parameters:

| Parameter       | Range    | Best For           | Example Use Case                                          |
| --------------- | -------- | ------------------ | --------------------------------------------------------- |
| **Temperature** | 0.0-1.0+ | Creativity control | 0.1 (factual Q&A), 0.7 (balanced), 0.9 (creative writing) |
| **Max Tokens**  | 1-4000+  | Response length    | 100 (concise), 1000 (detailed), 2000+ (comprehensive)     |
| **Top-P**       | 0.0-1.0  | Token diversity    | 0.1 (focused), 0.5 (balanced), 0.9 (diverse)              |

**⚠️ Important**: Use either `temperature` OR `topP`, not both simultaneously.

**Configuration Examples:**

```php
// Method 1: Agent class properties
protected ?float $temperature = 0.3;  // Consistent responses
protected ?int $maxTokens = 500;      // Concise answers
protected ?float $topP = null;        // Use temperature instead

// Method 2: Fluent configuration
$agent->setTemperature(0.8)
      ->setMaxTokens(1500)
      ->setTopP(null);

// Method 3: Global defaults in config/agent-adk.php
'default_generation_params' => [
    'temperature' => 0.7,
    'max_tokens' => 1000,
    'top_p' => null,
],

// Method 4: Environment variables
AGENT_ADK_DEFAULT_TEMPERATURE=0.7
AGENT_ADK_DEFAULT_MAX_TOKENS=1000
AGENT_ADK_DEFAULT_TOP_P=
```

### Agent Lifecycle Hooks

Customize your agent's behavior at key points in the execution cycle with these built-in hooks:

```php
class YourAgent extends BaseLlmAgent
{
    /**
     * Called before sending messages to the LLM.
     * Use this to modify messages, add context, or validate input.
     */
    public function beforeLlmCall(array $inputMessages, AgentContext $context): array
    {
        // Example: Add a system note or modify user input
        $context->setState('last_interaction_time', now());
        // $inputMessages[] = ['role' => 'system', 'content' => 'User is on a mobile device.'];
        return $inputMessages;
    }

    /**
     * Called after receiving a response from the LLM.
     * Use this to process, validate, or transform the LLM response.
     */
    public function afterLlmResponse(Response $response, AgentContext $context): mixed
    {
        // Example: Log responses, validate output structure, apply post-processing
        return $response;
    }

    /**
     * Called before executing any tool.
     * Use this to modify arguments, add authentication, or validate permissions.
     */
    public function beforeToolCall(string $toolName, array $arguments, AgentContext $context): array
    {
        // Example: Validate tool permissions, inject API keys into arguments, log tool call attempts
        return $arguments;
    }

    /**
     * Called after tool execution completes.
     * Use this to process results, handle errors, or format output before it's sent back to the LLM.
     */
    public function afterToolResult(string $toolName, string $result, AgentContext $context): string
    {
        // Example: Format tool results into a specific string, handle tool errors gracefully, log tool usage
        return $result;
    }
}
```

These hooks provide powerful customization points for logging, validation, authentication, and result processing without modifying the core agent logic.

### Event System

Hook into agent execution with Laravel events:

```bash
php artisan make:listener LogAgentInteractions --event="AaronLumsden\LaravelAgentADK\Events\AgentResponseGenerated"
```

```php
namespace App\Listeners;

use AaronLumsden\LaravelAgentADK\Events\AgentResponseGenerated;
use Illuminate\Support\Facades\Log;

class LogAgentInteractions
{
    public function handle(AgentResponseGenerated $event): void
    {
        Log::info('Agent response generated', [
            'agent' => $event->agentName,
            'session_id' => $event->context->getSessionId(),
            'response_length' => strlen($event->response),
            'user_input' => $event->context->getUserInput(),
        ]);

        // Track metrics, send to analytics, etc.
    }
}
```

**Available Events:**

- `AgentExecutionStarting` - Before agent processing begins
- `AgentExecutionFinished` - After agent completes
- `LlmCallInitiating` - Before LLM API call
- `LlmResponseReceived` - After LLM responds
- `ToolCallInitiating` - Before tool execution
- `ToolCallCompleted` - After tool execution
- `AgentResponseGenerated` - Final response ready
- `StateUpdated` - When context state changes

### Error Handling

Robust error handling for production environments:

```php
use AaronLumsden\LaravelAgentADK\Facades\Agent;
use AaronLumsden\LaravelAgentADK\Exceptions\ToolExecutionException;

public function handleChat(Request $request)
{
    try {
        $response = Agent::run('support_agent', $request->input('message'), $request->session()->getId());

        return response()->json([
            'success' => true,
            'response' => $response
        ]);

    } catch (ToolExecutionException $e) {
        // Tool-specific errors
        Log::warning('Tool execution failed', [
            'tool' => $e->getToolName(),
            'error' => $e->getMessage()
        ]);

        return response()->json([
            'success' => false,
            'error' => 'I encountered an issue with one of my tools. Please try again.',
            'code' => 'TOOL_ERROR'
        ], 500);

    } catch (\RuntimeException $e) {
        // LLM API errors
        Log::error('LLM API error', ['error' => $e->getMessage()]);

        return response()->json([
            'success' => false,
            'error' => 'I\'m temporarily unavailable. Please try again in a moment.',
            'code' => 'LLM_ERROR'
        ], 503);

    } catch (\Exception $e) {
        // Unexpected errors
        Log::error('Unexpected agent error', [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);

        return response()->json([
            'success' => false,
            'error' => 'Something went wrong. Please try again.',
            'code' => 'UNKNOWN_ERROR'
        ], 500);
    }
}
```

## Evaluations

The evaluation system helps ensure your agents perform consistently and meet quality standards. It combines traditional assertions with AI-powered quality assessment.

### Creating an Evaluation

```bash
php artisan agent:make:eval CustomerServiceEvaluation
```

### Example: Customer Service Quality Evaluation

```php
namespace App\Evaluations;

use AaronLumsden\LaravelAgentADK\Evaluations\BaseEvaluation;

class CustomerServiceEvaluation extends BaseEvaluation
{
    public string $agentName = 'customer_support';
    public string $name = 'Customer Service Quality Assessment';
    public string $description = 'Evaluates customer service responses for helpfulness and professionalism';
    public string $csvPath = 'storage/evaluations/customer_service_scenarios.csv';
    public string $promptCsvColumn = 'customer_query';

    public function preparePrompt(array $csvRowData): string
    {
        return $csvRowData[$this->promptCsvColumn];
    }

    public function evaluateRow(array $csvRowData, string $llmResponse): array
    {
        $this->resetAssertionResults();

        // Basic response validation
        $this->assertResponseIsNotEmpty($llmResponse, 'Agent must provide a response');
        $this->assertResponseLengthBetween($llmResponse, 20, 500, 'Response should be appropriately sized');

        // Professional tone check
        $this->assertResponseDoesNotContain($llmResponse, 'sorry, I can\'t help',
            'Agent should not give up easily');

        // Helpfulness assessment using LLM judge
        $this->assertLlmJudge(
            $llmResponse,
            'The response should be helpful, professional, empathetic, and provide actionable guidance. It should address the customer\'s concern directly.',
            'llm_judge',
            'pass',
            'Response should meet customer service standards'
        );

        // Quality scoring
        $this->assertLlmJudgeQuality(
            $llmResponse,
            'Rate based on: 1) Helpfulness and problem-solving, 2) Professional and empathetic tone, 3) Clarity and completeness, 4) Appropriate next steps provided',
            7,
            'llm_judge',
            'Customer service quality should be high'
        );

        // Check for required elements if specified in CSV
        if (isset($csvRowData['must_include'])) {
            $requiredElements = explode(',', $csvRowData['must_include']);
            $this->assertContainsAllOf($llmResponse, $requiredElements,
                'Response must include all required elements');
        }

        $assertionStatuses = array_column($this->assertionResults, 'status');
        $finalStatus = !in_array('fail', $assertionStatuses, true) ? 'pass' : 'fail';

        return [
            'row_data' => $csvRowData,
            'llm_response' => $llmResponse,
            'assertions' => $this->assertionResults,
            'final_status' => $finalStatus,
        ];
    }
}
```

### Available Assertion Methods

The `BaseEvaluation` class provides a comprehensive set of assertion methods to validate agent responses:

**Basic Content Assertions:**

- `assertResponseContains()` - Checks if the response contains a specific substring
- `assertResponseDoesNotContain()` - Checks if the response does not contain a specific substring
- `assertResponseStartsWith()` - Checks if the response starts with a specific prefix
- `assertResponseEndsWith()` - Checks if the response ends with a specific suffix
- `assertResponseMatchesRegex()` - Checks if the response matches a given regular expression
- `assertResponseIsNotEmpty()` - Checks if the response is not empty after trimming whitespace

**Length and Format Assertions:**

- `assertResponseLengthBetween()` - Checks if the response character length is within a specified range
- `assertWordCountBetween()` - Checks if the response word count is within a specified range
- `assertResponseIsValidJson()` - Checks if the response is a valid JSON string
- `assertJsonHasKey()` - Checks if the decoded JSON response contains a specific key
- `assertResponseIsValidXml()` - Checks if the response is a valid XML string
- `assertXmlHasValidTag()` - Checks if the XML response contains a specific tag

**Content Analysis Assertions:**

- `assertContainsAnyOf()` - Checks if the response contains at least one of the provided substrings
- `assertContainsAllOf()` - Checks if the response contains all of the provided substrings
- `assertResponseHasPositiveSentiment()` - Performs a basic keyword-based check for positive sentiment

**Tool and Logic Assertions:**

- `assertToolCalled()` - Checks if a specific tool was called by the agent
- `assertEquals()` - Checks if two values are equal using loose comparison
- `assertTrue()` - Checks if a given condition is true
- `assertFalse()` - Checks if a given condition is false
- `assertGreaterThan()` - Checks if the actual value is greater than the expected value
- `assertLessThan()` - Checks if the actual value is less than the expected value

**AI-Powered Judge Assertions:**

- `assertLlmJudge()` - Uses another LLM agent to judge the response based on given criteria for a pass/fail outcome
- `assertLlmJudgeQuality()` - Uses an LLM agent to rate the response quality on a numeric scale against given criteria
- `assertLlmJudgeComparison()` - Uses an LLM agent to compare the actual response against a reference response based on criteria

### Running Evaluations

```bash
# Run evaluation with console output
php artisan agent:run:eval CustomerServiceEvaluation

# Save results to CSV file
php artisan agent:run:eval CustomerServiceEvaluation --output=service_quality_results.csv
```

## Configuration

Key configuration options in `config/agent-adk.php`:

```php
return [
    // Default LLM provider and model
    'default_provider' => env('AGENT_ADK_DEFAULT_PROVIDER', 'openai'),
    'default_model' => env('AGENT_ADK_DEFAULT_MODEL', 'gemini-pro'),

    // Generation parameters
    'default_generation_params' => [
        'temperature' => env('AGENT_ADK_DEFAULT_TEMPERATURE', null),
        'max_tokens' => env('AGENT_ADK_DEFAULT_MAX_TOKENS', null),
        'top_p' => env('AGENT_ADK_DEFAULT_TOP_P', null),
    ],

    // Database table names
    'tables' => [
        'agent_sessions' => 'agent_sessions',
        'agent_messages' => 'agent_messages',
    ],

    // Namespaces for generated classes
    'namespaces' => [
        'agents' => 'App\Agents',
        'tools'  => 'App\Tools',
    ],

    // Built-in API routes
    'routes' => [
        'enabled' => true,
        'prefix' => 'api/agent-adk',
        'middleware' => ['api'],
    ],

    // Prism-PHP configuration
    'prism' => [
        'api_key' => env('PRISM_API_KEY'),
        'client_options' => [],
    ],
];
```

## Security Best Practices

### Input Validation and Sanitization

```php
// In your agent class
public function beforeLlmCall(array $inputMessages, AgentContext $context): array
{
    $userInput = $context->getUserInput();

    // Length validation
    if (strlen($userInput) > 4000) {
        throw new \InvalidArgumentException('Input too long');
    }

    // Content filtering
    if ($this->containsProhibitedContent($userInput)) {
        throw new \InvalidArgumentException('Input contains prohibited content');
    }

    // Sanitize HTML if needed
    $sanitizedInput = strip_tags($userInput);
    $context->setUserInput($sanitizedInput);

    return $inputMessages;
}

private function containsProhibitedContent(string $input): bool
{
    $prohibitedPatterns = [
        '/\b(exec|eval|system|shell_exec)\s*\(/i',
        '/<script\b[^<]*(?:(?!<\/script>)<[^<]*)*<\/script>/mi',
        // Add your patterns
    ];

    foreach ($prohibitedPatterns as $pattern) {
        if (preg_match($pattern, $input)) {
            return true;
        }
    }

    return false;
}
```

### API Key Management

```php
// Use Laravel's encrypted configuration for sensitive data
// .env
OPENAI_API_KEY=sk-your-key-here

// In production, consider using:
// - Laravel Vault
// - AWS Secrets Manager
// - Azure Key Vault
// - HashiCorp Vault
```

### Rate Limiting

```php
// Apply rate limiting to your agent endpoints
Route::middleware(['throttle:agent-chat'])->group(function () {
    Route::post('/chat', [ChatController::class, 'handle']);
});

// In RouteServiceProvider.php
protected function configureRateLimiting()
{
    RateLimiter::for('agent-chat', function (Request $request) {
        return Limit::perMinute(30)->by($request->ip());
    });
}
```

## Performance Considerations

### Optimization Strategies

1. **Response Caching**

```php
// Cache frequent responses
public function run(mixed $input, AgentContext $context): mixed
{
    $cacheKey = 'agent_response:' . md5($this->name . $input);

    return Cache::remember($cacheKey, 300, function() use $input, $context) {
        return parent::run($input, $context);
    });
}
```

2. **Tool Result Caching**

```php
// In your tool's execute method
public function execute(array $arguments, AgentContext $context): string
{
    $cacheKey = 'tool_result:' . md5(json_encode($arguments));

    return Cache::remember($cacheKey, 600, function() use ($arguments) {
        return $this->performApiCall($arguments);
    });
}
```

3. **Database Optimization**

```php
// Index your agent tables
Schema::table('agent_sessions', function (Blueprint $table) {
    $table->index(['session_id', 'created_at']);
    $table->index('agent_name');
});
```

### Memory Management

- Set appropriate `max_tokens` values (typically 500-2000)
- Clean up old conversation contexts regularly
- Use pagination for large tool result sets
- Monitor memory usage with tools like Telescope

## Testing

### Unit Testing Your Agents

```php
namespace Tests\Unit\Agents;

use Tests\TestCase;
use App\Agents\CustomerSupportAgent;
use AaronLumsden\LaravelAgentADK\System\AgentContext;
use AaronLumsden\LaravelAgentADK\Facades\Agent;

class CustomerSupportAgentTest extends TestCase
{
    public function test_agent_registration()
    {
        $agent = Agent::named('customer_support');
        $this->assertInstanceOf(CustomerSupportAgent::class, $agent);
    }

    public function test_agent_responds_to_greeting()
    {
        $response = Agent::run('customer_support', 'Hello', 'test-session');

        $this->assertIsString($response);
        $this->assertNotEmpty($response);
        $this->assertStringContainsString('hello', strtolower($response));
    }

    public function test_agent_handles_context()
    {
        $sessionId = 'test-session-' . uniqid();

        // First interaction
        Agent::run('customer_support', 'My name is John', $sessionId);

        // Second interaction should remember context
        $response = Agent::run('customer_support', 'What is my name?', $sessionId);

        $this->assertStringContainsString('John', $response);
    }
}
```

### Integration Testing

```php
public function test_agent_api_endpoint()
{
    $response = $this->postJson('/api/agent-adk/interact', [
        'agent_name' => 'customer_support',
        'input' => 'I need help with my order',
        'session_id' => 'test-session'
    ]);

    $response->assertStatus(200)
             ->assertJsonStructure([
                 'agent_name',
                 'session_id',
                 'response'
             ]);
}
```

## Deployment

### Production Checklist

- [ ] Set proper environment variables
- [ ] Configure rate limiting
- [ ] Set up monitoring and logging
- [ ] Test error handling scenarios
- [ ] Verify API key security
- [ ] Configure caching strategy
- [ ] Set up context cleanup jobs
- [ ] Test agent evaluations

### Environment Configuration

```bash
# Production .env additions
AGENT_ADK_DEFAULT_TEMPERATURE=0.3  # Lower for consistency
AGENT_ADK_DEFAULT_MODEL=gpt-4o     # Reliable model
LOG_LEVEL=warning                   # Reduce log noise

# Optional: Use Redis for caching
CACHE_DRIVER=redis
SESSION_DRIVER=redis
```

### Context Cleanup Job

```php
// Create a scheduled job to clean up old contexts
php artisan make:command CleanupAgentContexts

// In the command
public function handle()
{
    $cutoff = now()->subHours(24); // Clean up contexts older than 24 hours

    DB::table(config('agent-adk.tables.agent_sessions'))
      ->where('updated_at', '<', $cutoff)
      ->delete();

    DB::table(config('agent-adk.tables.agent_messages'))
      ->where('created_at', '<', $cutoff)
      ->delete();

    $this->info('Cleaned up old agent contexts');
}

// In app/Console/Kernel.php
protected function schedule(Schedule $schedule)
{
    $schedule->command('agent:cleanup-contexts')->daily();
}
```

## Troubleshooting 🔧

### Common Issues

**"Agent not found" Error**

```php
// Ensure agent is registered in AppServiceProvider
Agent::build(YourAgent::class)->register();
```

**"Tool execution failed" Error**

```php
// Check tool namespace and registration
protected function registerTools(): array
{
    return [
        \App\Tools\YourTool::class, // Correct namespace
    ];
}
```

**Memory Issues**

```php
// Reduce max_tokens or implement response caching
protected ?int $maxTokens = 500; // Instead of 2000
```

**API Rate Limits**

```php
// Implement exponential backoff in your tools
try {
    $response = Http::retry(3, 1000)->get($url);
} catch (Exception $e) {
    // Handle rate limiting
}
```

### Debug Mode

Enable detailed logging by setting your Laravel log level:

```php
// In .env
LOG_LEVEL=debug
```

## Contributing 🤝

We welcome contributions! Here's how you can help:

1. **Report Issues**: Use GitHub issues for bugs and feature requests
2. **Submit PRs**: Follow PSR-12 coding standards
3. **Add Tests**: Include tests for new features
4. **Update Docs**: Keep documentation current
5. **Share Examples**: Contribute real-world use cases

### Development Setup

```bash
git clone https://github.com/aaronlumsden/laravel-agent-adk.git
cd laravel-agent-adk
composer install
cp .env.example .env
php artisan key:generate
```

## License 📄

MIT License - see [LICENSE](LICENSE) for details.

---

**Ready to build something amazing?** Start with the [Quick Start](#quick-start-) guide and join our community of Laravel AI developers! 🚀
