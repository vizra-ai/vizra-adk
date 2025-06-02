# Laravel Agent ADK 🤖✨ (Agent Development Kit)

**Laravel Agent ADK** is a super cool PHP package that makes building AI agents ridiculously easy! 🚀 Think of it as your AI agent's best friend - it handles all the boring stuff so you can focus on building awesome intelligent systems.

## Table of Contents 📋

- [What's This All About? 🤔](#whats-this-all-about-)
- [Why Choose Laravel Agent ADK? 🌟](#why-choose-laravel-agent-adk-)
- [Cool Features (What You Get Right Now) ✨](#cool-features-what-you-get-right-now-)
- [Getting Started 🚀](#getting-started-)
  - [1. Install This Bad Boy 📦](#1-install-this-bad-boy-)
  - [2. Set Everything Up 🔧](#2-set-everything-up-)
  - [3. Run Those Migrations 📊](#3-run-those-migrations-)
  - [4. Add Your API Keys 🔑](#4-add-your-api-keys-)
- [Let's Build Something Cool! 🛠️](#lets-build-something-cool-️)
  - [1. Create Your First Agent 🤖](#1-create-your-first-agent-)
  - [2. Build a Super Tool 🔨](#2-build-a-super-tool-)
  - [3. Configure Generation Parameters 🎛️](#3-configure-generation-parameters-️)
  - [4. Register Your Agent 📝](#4-register-your-agent-)
  - [5. Chat With Your Agent! 💬](#5-chat-with-your-agent-)
  - [6. Get More Control (If You Want) 🎛️](#6-get-more-control-if-you-want-️)
  - [7. Listen to Events 👂](#7-listen-to-events-)
- [Evaluations](#evaluations)
  - [Generating an Evaluation Class](#generating-an-evaluation-class)
  - [Structure of an Evaluation Class](#structure-of-an-evaluation-class)
  - [Example Concrete Evaluation](#example-concrete-evaluation-sentimentanalysisevaluationphp)
  - [LLM-as-a-Judge Example 🤖⚖️](#llm-as-a-judge-example-)
  - [Available Assertion Methods](#available-assertion-methods)
  - [Running Evaluations](#running-evaluations)
- [What's Coming Next? 🚀](#whats-coming-next-)
- [Troubleshooting 🔧](#troubleshooting-)
- [Want to Contribute? 🤝](#want-to-contribute-)
- [License 📄](#license-)

## What's This All About? 🤔

AI agents are like digital assistants that can think, decide, and take action. Pretty neat, right? 🧠 With Laravel Agent ADK, you get to build these smart little helpers without pulling your hair out over complicated setup.

Here's what makes this package awesome:

- **🎯 Easy Agent Creation:** Build agents with simple PHP classes or use our fluent builder - no PhD required!
- **🛠️ Tool Integration:** Let your agents use custom tools (APIs, databases, whatever!) - they'll figure out when to use them
- **💾 Smart State Management:** We handle all the session stuff and conversation history automatically
- **🌐 LLM Integration:** Talk to any AI model (OpenAI, Gemini, Claude) through the sweet [Prism-PHP](https://prismphp.com/) library
- **💎 Laravel Native:** Built for Laravel developers, by Laravel developers
- **🔧 Super Extensible:** Events, hooks, and overrides everywhere - customize to your heart's content!

Whether you're building a chatbot, data analyzer, or the next AI assistant, this package has your back! 💪

## Why Choose Laravel Agent ADK? 🌟

- **📐 Structured Approach:** No more messy API calls - build proper, stateful agents
- **🎭 LLM Abstraction:** One interface for all the major AI providers
- **⚡ Tool Power:** Your agents can actually DO things, not just chat
- **🏃‍♂️ Rapid Development:** Artisan commands to scaffold everything super fast
- **🏠 Feels Like Home:** Pure Laravel goodness

## Cool Features (What You Get Right Now) ✨

- **📚 Class-Based Agents:** Extend `BaseLlmAgent` and you're golden
- **🎨 Fluent Builder:** `Agent::define('my_agent')->instructions(...)` - so smooth!
- **🔨 Tool System:** Implement `ToolInterface` and watch the magic happen
- **🌟 Prism-PHP Power:** All your favorite LLMs in one place
- **📝 Auto-History:** Conversations remembered automatically
- **🎉 Laravel Events:** Hook into everything with events
- **🤖⚖️ LLM-as-a-Judge:** Use AI to evaluate AI responses with nuanced criteria
- **📊 Smart Evaluations:** Traditional assertions + AI-powered quality assessment
- **⚡ Artisan Commands:**
  - `php artisan agent:install` - Get everything set up
  - `php artisan agent:make:agent <AgentName>` - Scaffold new agents
  - `php artisan agent:make:tool <ToolName>` - Create new tools
  - `php artisan agent:chat <AgentName>` - Chat with your agent in the terminal
  - `php artisan agent:make:eval <EvaluationName>` - Create new evaluation classes
  - `php artisan run:eval <EvaluationName>` - Run evaluations
- **⚙️ Configurable:** Tweak everything in `config/agent-adk.php`

## Getting Started 🚀

### 1. Install This Bad Boy 📦

```bash
composer require aaronlumsden/laravel-agent-adk
```

### 2. Set Everything Up 🔧

```bash
php artisan agent:install
```

This creates your config file and database tables - pretty neat!

### 3. Run Those Migrations 📊

```bash
php artisan migrate
```

### 4. Add Your API Keys 🔑

Pop your LLM API key(s) in your `.env` file:

```dotenv
OPENAI_URL=
GEMINI_API_KEY=
ANTHROPIC_API_KEY=
```

## Let's Build Something Cool! 🛠️

### 1. Create Your First Agent 🤖

```bash
php artisan agent:make:agent WeatherReporterAgent
```

This creates `app/Agents/WeatherReporterAgent.php`:

```php
// app/Agents/WeatherReporterAgent.php
namespace App\Agents;

use AaronLumsden\LaravelAgentADK\Agents\BaseLlmAgent;
use AaronLumsden\LaravelAgentADK\Contracts\ToolInterface;
use AaronLumsden\LaravelAgentADK\System\AgentContext;
use App\Tools\GetCurrentWeatherTool;

class WeatherReporterAgent extends BaseLlmAgent
{
    protected string $name = 'weather_reporter';
    protected string $description = 'Your friendly neighborhood weather bot! 🌤️';

    protected string $instructions = 'You are the coolest weather assistant ever! When someone asks about weather, use the get_current_weather tool. Never make up weather data - that would be super uncool. Keep it fun and concise! ☀️🌧️';

    protected string $model = 'gemini-1.5-pro-latest';
    protected ?float $temperature = 0.7;
    protected ?int $maxTokens = 1000;
    protected ?float $topP = null;

    protected function registerTools(): array
    {
        return [
            GetCurrentWeatherTool::class, // Your awesome tools go here!
        ];
    }

    // Hook into the magic ✨
    public function beforeLlmCall(array $inputMessages, AgentContext $context): array
    {
        // Add some spice to the context
        $context->setState('current_time_zone', config('app.timezone'));
        return parent::beforeLlmCall($inputMessages, $context);
    }

    public function afterLlmResponse(mixed $response, AgentContext $context): mixed
    {
        // Do something with the result if you want ✨
       return parent::afterLlmResponse($response, $context);
    }

    public function beforeToolCall(string $toolName, array $arguments, AgentContext $context): array
    {
        return parent::beforeToolCall($toolName, $arguments, $context);
    }

    public function afterToolResult(string $toolName, string $result, AgentContext $context): string
    {
        return parent::afterToolResult($toolName, $result, $context);
    }



}
```

### 2. Build a Super Tool 🔨

```bash
php artisan agent:make:tool GetCurrentWeatherTool
```

Creates `app/Tools/GetCurrentWeatherTool.php`:

```php
// app/Tools/GetCurrentWeatherTool.php
namespace App\Tools;

use AaronLumsden\LaravelAgentADK\Contracts\ToolInterface;
use AaronLumsden\LaravelAgentADK\System\AgentContext;

class GetCurrentWeatherTool implements ToolInterface
{
    public function definition(): array
    {
        return [
            'name' => 'get_current_weather',
            'description' => 'Gets the current weather - pretty cool, right? 🌡️',
            'parameters' => [
                'type' => 'object',
                'properties' => [
                    'location' => [
                        'type' => 'string',
                        'description' => 'City name like "London" or "San Francisco, CA"',
                    ],
                    'unit' => [
                        'type' => 'string',
                        'enum' => ['celsius', 'fahrenheit'],
                        'description' => 'Temperature unit (because we all have preferences)',
                    ]
                ],
                'required' => ['location'],
            ],
        ];
    }

    public function execute(array $arguments, AgentContext $context): string
    {
        $location = $arguments['location'] ?? 'somewhere mysterious';
        $unit = $arguments['unit'] ?? 'celsius';

        // In real life, you'd hit a weather API here! 🌐
        // But for now, let's fake it till we make it
        $simulatedWeather = [
            'location' => $location,
            'temperature' => $unit === 'celsius' ? '22°C' : '72°F',
            'condition' => 'Absolutely gorgeous! ☀️',
            'humidity' => '55%',
        ];

        // Return JSON because that's how we roll
        return json_encode($simulatedWeather);
    }
}
```

### 3. Configure Generation Parameters 🎛️

You can fine-tune how your agents generate responses using three powerful parameters:

#### Temperature (0.0 - 1.0+) 🌡️

Controls randomness and creativity in responses:

- **0.0-0.3**: Very focused, deterministic responses
- **0.4-0.7**: Balanced creativity and coherence
- **0.8-1.0+**: High creativity, more random responses

#### Max Tokens 📏

Controls the maximum length of generated responses:

- **100-500**: Short responses
- **500-1500**: Medium responses
- **1500+**: Long responses

#### Top-P (0.0 - 1.0) 🎯

Nucleus sampling parameter for probability control:

- **0.1**: Very focused (top 10% probability tokens)
- **0.5**: Moderate filtering (top 50% probability tokens)
- **0.9**: Minimal filtering (top 90% probability tokens)

**⚠️ Important**: Use either `temperature` OR `topP`, not both!

#### Configuration Methods

**Method 1: Set as Class Properties**

```php
class WeatherReporterAgent extends BaseLlmAgent
{
    protected string $model = 'gemini-1.5-pro-latest';

    // Generation parameters
    protected ?float $temperature = 0.7;  // Balanced creativity
    protected ?int $maxTokens = 1000;     // Medium responses
    protected ?float $topP = null;        // Use temperature instead
}
```

**Method 2: Use Fluent Methods**

```php
$agent = new WeatherReporterAgent();
$agent->setTemperature(0.9)    // High creativity
      ->setMaxTokens(2000)     // Longer responses
      ->setTopP(null);         // Don't use topP with temperature
```

**Method 3: Set Global Defaults**

In `config/agent-adk.php`:

```php
'default_generation_params' => [
    'temperature' => 0.7,
    'max_tokens' => 1000,
    'top_p' => null,
],
```

**Method 4: Environment Variables**

```dotenv
AGENT_ADK_DEFAULT_PROVIDER=openai
AGENT_ADK_DEFAULT_MODEL=gpt-4o
AGENT_ADK_DEFAULT_TEMPERATURE=0.7
AGENT_ADK_DEFAULT_MAX_TOKENS=1000
AGENT_ADK_DEFAULT_TOP_P=
```

### 4. Register Your Agent 📝

In your `AppServiceProvider.php`:

```php
// app/Providers/AppServiceProvider.php
use AaronLumsden\LaravelAgentADK\Facades\Agent;
use App\Agents\WeatherReporterAgent;

public function boot(): void
{
    // Register your awesome class-based agent
    Agent::build(WeatherReporterAgent::class)
         // ->withInstructionOverride('Also mention if it\'s good weather for a BBQ! 🍖')
         ->register();

    // Or create a quick agent on the fly!
    Agent::define('greeting_agent')
         ->description('The friendliest greeter in town! 👋')
         ->instructions('Say hi like you mean it! Ask how you can help and keep it under 30 words. Spread those good vibes! ✨')
         ->register();
}
```

### 5. Chat With Your Agent! 💬

#### Try It Out in Your Terminal! 🖥️✨

Want to test your agent super quick? Fire up your terminal and chat with your agent directly - just like Laravel Tinker but with your agent! 🔥

```bash
php artisan agent:chat weather_reporter
```

This opens up an interactive chat session right in your terminal! Type your messages, hit enter, and watch your agent respond. Perfect for testing and debugging your agents without building any frontend. To exit, just type `exit` or press `Ctrl+C`.

Pretty handy for those "does this actually work?" moments! 😄

#### Use Our Built-In API! 🌐✨

Too lazy to write your own controller? (We get it!) Laravel Agent ADK comes with a super convenient built-in API endpoint that's ready to rock! 🎸

**Default endpoint:** `POST /api/agent-adk/interact`

Want to customize it? Just tweak the config in `config/agent-adk.php`:

```php
'routes' => [
    'enabled' => true, // Master switch for package routes
    'prefix' => 'api/agent-adk', // Default prefix for all package API routes
    'middleware' => ['api'], // Default middleware group for package routes
],
```

**POST Request Example:**

```json
{
  "agent_name": "weather_reporter_agent",
  "input": "What's the weather like in Leeds?",
  "session_id": "91" // Optional session ID for tracking conversations
}
```

**Response Example:**

```json
{
  "agent_name": "weather_reporter_agent",
  "session_id": "91",
  "response": "The weather in Leeds is absolutely gorgeous! ☀️ It's currently 22°C with 55% humidity. Perfect weather for a nice walk or maybe even a BBQ! 🍖"
}
```

Pretty sweet, right? Just POST to the endpoint and your agents start doing their thing! 🚀

#### Or Build Your Own Controller 🎛️

In your controller:

```php
// Example Controller
use AaronLumsden\LaravelAgentADK\Facades\Agent;
use Illuminate\Http\Request;

class ChatController extends Controller
{
    public function chatWithWeatherAgent(Request $request)
    {
        $userInput = $request->input('message');
        $sessionId = $request->session()->getId();

        try {
            $response = Agent::run('weather_reporter', $userInput, $sessionId);
            return response()->json(['reply' => $response]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Oops! Something went wrong: ' . $e->getMessage()], 500);
        }
    }

    public function chatWithGreetingAgent(Request $request)
    {
        $userInput = $request->input('message');
        $sessionId = $request->session()->getId();

        $response = Agent::run('greeting_agent', $userInput, $sessionId);
        return response()->json(['reply' => $response]);
    }
}
```

The `Agent::run()` method is like magic - it handles everything! 🪄

### 6. Get More Control (If You Want) 🎛️

```php
use AaronLumsden\LaravelAgentADK\Facades\Agent;

// Grab your agent directly
$weatherAgent = Agent::named('weather_reporter');

// Do your thing with it!
```

### 7. Listen to Events 👂

To hook into the events dispatched by the `Laravel Agent ADK` package, you'll need to register your event listeners. In modern Laravel applications (Laravel 11+), the recommended way to do this for package events is typically within the `boot` method of your application's `AppServiceProvider.php`.

To create a listener

```bash
php artisan make:listener HandleAgentExecutionStarting --event=\\AaronLumsden\\LaravelAgentADK\\Events\\AgentExecutionStarting
```

it should be automatically discovered but if not you can register it manually in your `AppServiceProvider.php`:

```php
namespace App\Providers;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

use AaronLumsden\LaravelAgentADK\Events\AgentExecutionStarting;
use App\Listeners\HandleAgentExecutionStarting;

class AppServiceProvider extends ServiceProvider
{

    public function boot(): void
    {
        Event::listen(
            AgentExecutionStarting::class,
            [
                HandleAgentExecutionStarting::class,
            ]
        );
    }
}
```

Available events you can listen to are:

- AgentExecutionStarting;
- AgentExecutionFinished;
- AgentResponseGenerated;
- LlmCallInitiating;
- LlmResponseReceived;
- StateUpdated;
- ToolCallInitiating;
- ToolCallCompleted;

## Evaluations

Evaluations are a crucial component in developing and maintaining reliable AI agents. They provide a structured framework for systematically testing and validating your agent's responses, ensuring consistent quality and appropriate behavior across various scenarios. By implementing evaluations, you can catch potential issues early, measure improvements over time, and maintain confidence in your agent's performance before deploying to production.

The evaluation system in Laravel Agent ADK combines traditional assertion-based testing with advanced AI-powered quality assessment through its unique "LLM-as-a-Judge" feature. This dual approach allows you to not only verify basic requirements (like response format and content presence) but also assess more nuanced aspects like tone, helpfulness, accuracy, and overall quality of your agent's responses. Whether you're building customer service agents, content generators, or specialized AI tools, robust evaluation helps ensure your agents consistently meet your quality standards.

### Generating an Evaluation Class

To create a new evaluation class, use the `make:eval` Artisan command:

```bash
php artisan make:eval MyAwesomeEvaluation
```

This will generate `app/Evaluations/MyAwesomeEvaluation.php` (or `src/Evaluations/` for package dev), extending `AaronLumsden\LaravelAgentADK\Evaluations\BaseEvaluation`.

### Structure of an Evaluation Class

Key components:

- **`$agentName` (public string property):** Specifies the **registered name/alias** of the LLM agent to be used (e.g., `public string $agentName = 'WeatherReporterAgent';`). This is the name you used when registering the agent (e.g., via `Agent::build(YourAgent::class)->register()` or `Agent::define('your_agent_name')`).
- **`$name` (public string property):** Human-readable name for the evaluation.
- **`$description` (public string property):** Brief description of the test.
- **`$csvPath` (public string property):** Relative path to the CSV data file (e.g., `app/evaluations/data/my_test_data.csv`).
- **`$promptCsvColumn` (public string property):** Defines which column in your CSV contains the main text/prompt for the LLM.
  ```php
  public function getPromptCsvColumn(): string
  {
      return 'user_query'; // Name of the column in your CSV
  }
  ```
- **`preparePrompt(array $csvRowData): string`:** Constructs the full prompt string. The default stub uses `$this->promptCsvColumn` to fetch the base prompt from the CSV. You can customize this to add prefixes, instructions, or combine multiple CSV columns.
- **`evaluateRow(array $csvRowData, string $llmResponse): array`:** Core logic using assertion methods to evaluate the LLM's response against the CSV data.

### Example Concrete Evaluation: `SentimentAnalysisEvaluation.php`

```php
<?php

namespace App\Evaluations; // Or YourVendor\YourPackage\Evaluations

use AaronLumsden\LaravelAgentADK\Evaluations\BaseEvaluation;
use InvalidArgumentException;

class SentimentAnalysisEvaluation extends BaseEvaluation
{
    // Use the agent's registered name/alias.
    // This agent ('MySentimentAnalysisAgent') must be registered in a Service Provider.
    public string $agentName = 'MySentimentAnalysisAgent';

    public string $name = 'Sentiment Analysis Evaluation';

    public string $description = 'Evaluates the LLM\'s ability to correctly classify text sentiment.';

    public string $csvPath = 'evaluations/data/sentiment_analysis_data.csv';

    // Specify which CSV column contains the prompt text
    public string $promptCsvColumn = 'text_input'; // Name of the column in your CSV

    public function preparePrompt(array $csvRowData): string
    {
        if (!isset($csvRowData[$this->promptCsvColumn])) {
            throw new InvalidArgumentException(
                "CSV row must contain a '" . $this->promptCsvColumn . "' column for sentiment analysis."
            );
        }
        // Example: Constructing a more complete prompt
        return "Classify the sentiment of the following text as positive, negative, or neutral: \"" . $csvRowData[$this->promptCsvColumn] . "\"";
    }

    public function evaluateRow(array $csvRowData, string $llmResponse): array
    {
        $this->resetAssertionResults();

        $expectedSentimentColumn = 'expected_sentiment'; // Assuming this column exists
        if (!isset($csvRowData[$expectedSentimentColumn])) {
            throw new InvalidArgumentException("CSV row must contain an '" . $expectedSentimentColumn . "' column.");
        }

        $expectedSentiment = strtolower(trim($csvRowData[$expectedSentimentColumn]));
        $actualSentiment = strtolower(trim($llmResponse)); // Assuming LLM returns only the sentiment category

        $this->assertEquals($expectedSentiment, $actualSentiment,
            "Checking if LLM sentiment ('{$actualSentiment}') matches expected ('{$expectedSentiment}')."
        );
        $this->assertTrue(in_array($actualSentiment, ['positive', 'negative', 'neutral']),
            "Sentiment '{$actualSentiment}' should be one of 'positive', 'negative', or 'neutral'."
        );

        $assertionStatuses = array_column($this->assertionResults, 'status');
        $finalStatus = empty($this->assertionResults) || !in_array('fail', $assertionStatuses, true) ? 'pass' : 'fail';

        return [
            'row_data' => $csvRowData,
            'llm_response' => $llmResponse,
            'assertions' => $this->assertionResults,
            'final_status' => $finalStatus,
        ];
    }
}
```

### LLM-as-a-Judge Example 🤖⚖️

Here's a more advanced example showing how to use LLM-as-a-judge for complex quality assessments:

```php
<?php

namespace App\Evaluations;

use AaronLumsden\LaravelAgentADK\Evaluations\BaseEvaluation;
use InvalidArgumentException;

class ContentQualityEvaluation extends BaseEvaluation
{
    public string $agentName = 'content_writer';
    public string $name = 'Content Quality Assessment';
    public string $description = 'Evaluates content quality using both traditional assertions and LLM judge';
    public string $csvPath = 'app/Evaluations/data/content_quality.csv';
    public string $promptCsvColumn = 'prompt';

    public function preparePrompt(array $csvRowData): string
    {
        if (!isset($csvRowData[$this->promptCsvColumn])) {
            throw new InvalidArgumentException("CSV must contain '{$this->promptCsvColumn}' column.");
        }
        return $csvRowData[$this->promptCsvColumn];
    }

    public function evaluateRow(array $csvRowData, string $llmResponse): array
    {
        $this->resetAssertionResults();

        // Traditional assertions
        $this->assertResponseIsNotEmpty($llmResponse, 'Response should not be empty');
        $this->assertResponseLengthBetween($llmResponse, 50, 1000, 'Response should be appropriately sized');

        // LLM Judge for quality assessment
        $this->assertLlmJudge(
            $llmResponse,
            'The response should be helpful, accurate, well-structured, and appropriate for the given prompt. It should demonstrate clear understanding and provide useful information.',
            'llm_judge',
            'pass',
            'Content should meet overall quality standards'
        );

        // Quality scoring (1-10 scale)
        $this->assertLlmJudgeQuality(
            $llmResponse,
            'Evaluate: 1) Clarity and readability, 2) Factual accuracy, 3) Completeness, 4) Engagement and helpfulness',
            7,
            'llm_judge',
            'Content quality should score 7+ out of 10'
        );

        // Comparison with reference if available
        if (isset($csvRowData['reference_response'])) {
            $this->assertLlmJudgeComparison(
                $llmResponse,
                $csvRowData['reference_response'],
                'Compare accuracy, helpfulness, and clarity',
                'actual',
                'llm_judge',
                'Generated response should be better than reference'
            );
        }

        // Check specific requirements from CSV
        if (isset($csvRowData['must_contain'])) {
            $this->assertResponseContains(
                $llmResponse,
                $csvRowData['must_contain'],
                "Response must contain: '{$csvRowData['must_contain']}'"
            );
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

**CSV Data Example** (`app/Evaluations/data/content_quality.csv`):

```csv
prompt,must_contain,expected_sentiment,reference_response
"What's the weather like in London?","London","positive","The weather in London today is partly cloudy..."
"Tell me a joke about programming","programming","positive","Why do programmers prefer dark mode? Because light attracts bugs!"
"Explain quantum computing in simple terms","quantum","neutral","Quantum computing uses principles of quantum mechanics..."
```

### Available Assertion Methods

The `BaseEvaluation` class provides several assertion methods to help you evaluate LLM responses. Each method records the assertion result internally and returns an array with the test outcome:

#### Basic Response Assertions

#### `assertResponseContains(string $actualResponse, string $expectedSubstring, string $message = 'Response should contain substring.'): array`

Checks if the LLM response contains a specific substring. Useful for verifying that certain keywords or phrases appear in the response.

```php
$this->assertResponseContains($llmResponse, 'positive',
    "Response should contain the word 'positive'");
```

#### `assertResponseDoesNotContain(string $actualResponse, string $unexpectedSubstring, string $message = 'Response should not contain substring.'): array`

Verifies that the LLM response does NOT contain a specific substring. Great for ensuring unwanted content doesn't appear.

```php
$this->assertResponseDoesNotContain($llmResponse, 'error',
    "Response should not contain error messages");
```

#### `assertResponseStartsWith(string $actualResponse, string $expectedPrefix, string $message = 'Response should start with expected prefix.'): array`

Validates that the response begins with a specific prefix.

```php
$this->assertResponseStartsWith($llmResponse, 'Classification:',
    "Response should start with 'Classification:'");
```

#### `assertResponseEndsWith(string $actualResponse, string $expectedSuffix, string $message = 'Response should end with expected suffix.'): array`

Validates that the response ends with a specific suffix.

```php
$this->assertResponseEndsWith($llmResponse, '.',
    "Response should end with a period");
```

#### `assertResponseIsNotEmpty(string $actualResponse, string $message = 'Response should not be empty.'): array`

Ensures the response is not empty or just whitespace.

```php
$this->assertResponseIsNotEmpty($llmResponse,
    "LLM should provide a non-empty response");
```

#### Pattern and Format Assertions

#### `assertResponseMatchesRegex(string $actualResponse, string $pattern, string $message = 'Response should match regex pattern.'): array`

Validates that the response matches a specific regular expression pattern.

```php
$this->assertResponseMatchesRegex($llmResponse, '/^(positive|negative|neutral)$/i',
    "Response should be exactly one of: positive, negative, neutral");
```

#### `assertResponseIsValidJson(string $actualResponse, string $message = 'Response should be valid JSON.'): array`

Checks if the response is valid JSON format.

```php
$this->assertResponseIsValidJson($llmResponse,
    "Response should be properly formatted JSON");
```

#### `assertJsonHasKey(string $actualResponse, string $key, string $message = 'JSON response should contain key.'): array`

Validates that a JSON response contains a specific key.

```php
$this->assertJsonHasKey($llmResponse, 'sentiment',
    "JSON response should have a 'sentiment' field");
```

#### Length and Size Assertions

#### `assertResponseLengthBetween(string $actualResponse, int $minLength, int $maxLength, string $message = 'Response length should be within range.'): array`

Validates that the response length (in characters) falls within a specified range.

```php
$this->assertResponseLengthBetween($llmResponse, 10, 100,
    "Response should be between 10-100 characters");
```

#### `assertWordCountBetween(string $actualResponse, int $minWords, int $maxWords, string $message = 'Word count should be within range.'): array`

Validates that the response word count falls within a specified range.

```php
$this->assertWordCountBetween($llmResponse, 5, 20,
    "Response should contain 5-20 words");
```

#### Content Matching Assertions

#### `assertContainsAnyOf(string $actualResponse, array $expectedSubstrings, string $message = 'Response should contain at least one of the expected substrings.'): array`

Checks if the response contains at least one of the provided substrings.

```php
$this->assertContainsAnyOf($llmResponse, ['happy', 'joy', 'excited', 'pleased'],
    "Response should contain at least one positive emotion word");
```

#### `assertContainsAllOf(string $actualResponse, array $expectedSubstrings, string $message = 'Response should contain all expected substrings.'): array`

Validates that the response contains ALL of the provided substrings.

```php
$this->assertContainsAllOf($llmResponse, ['classification', 'confidence'],
    "Response should contain both classification and confidence information");
```

#### Sentiment Analysis Assertion

#### `assertResponseHasPositiveSentiment(string $actualResponse, string $message = 'Response should have positive sentiment.'): array`

Performs basic keyword-based sentiment analysis to determine if the response has positive sentiment.

```php
$this->assertResponseHasPositiveSentiment($llmResponse,
    "Agent response should maintain a positive tone");
```

#### Tool and Behavior Assertions

#### `assertToolCalled(string $expectedToolName, array $calledTools, string $message = 'Expected tool was not called.'): array`

Validates that a specific tool was called during the agent's execution. Useful for testing agent behavior and tool usage.

```php
$this->assertToolCalled('get_current_weather', $calledTools,
    "Weather tool should have been called");
```

#### Value Comparison Assertions

#### `assertEquals($expected, $actual, string $message = 'Values should be equal.'): array`

Performs a loose equality check between expected and actual values. Perfect for comparing extracted values or classifications.

```php
$this->assertEquals('positive', $extractedSentiment,
    "Extracted sentiment should match expected value");
```

#### `assertGreaterThan($expected, $actual, string $message = 'Actual value should be greater than expected.'): array`

Validates that the actual value is greater than the expected value.

```php
$this->assertGreaterThan(0.7, $confidenceScore,
    "Confidence score should be greater than 0.7");
```

#### `assertLessThan($expected, $actual, string $message = 'Actual value should be less than expected.'): array`

Validates that the actual value is less than the expected value.

```php
$this->assertLessThan(1000, $responseTime,
    "Response time should be under 1000ms");
```

#### Boolean Assertions

#### `assertTrue(bool $condition, string $message = 'Condition should be true.'): array`

Asserts that a given condition evaluates to true. Useful for custom validation logic.

```php
$this->assertTrue(strlen($llmResponse) > 10,
    "Response should be at least 10 characters long");
```

#### `assertFalse(bool $condition, string $message = 'Condition should be false.'): array`

Asserts that a given condition evaluates to false. The opposite of `assertTrue`.

```php
$this->assertFalse(empty($llmResponse),
    "Response should not be empty");
```

**💡 Pro Tip:** Each assertion method returns an array containing:

- `assertion_method`: The method that was called
- `status`: Either 'pass' or 'fail'
- `message`: Your custom message
- `expected`: The expected value (when applicable)
- `actual`: The actual value (when applicable)

All assertion results are automatically collected and can be accessed via the `$this->assertionResults` property in your evaluation's `evaluateRow` method.

### LLM-as-a-Judge Assertions 🤖⚖️

These powerful assertion methods use another LLM agent as a judge to evaluate responses based on complex criteria that traditional string matching can't handle. Perfect for assessing quality, tone, accuracy, and other nuanced aspects of AI responses.

#### `assertLlmJudge(string $actualResponse, string $criteria, string $judgeAgentName = 'llm_judge', string $expectedOutcome = 'pass', string $message = 'LLM judge evaluation failed.'): array`

Uses another LLM agent as a judge to evaluate responses based on custom criteria. Perfect for complex evaluations that require nuanced understanding.

```php
$this->assertLlmJudge(
    $llmResponse,
    'The response should be helpful, accurate, and written in a friendly tone',
    'llm_judge',
    'pass',
    'Response should meet quality standards'
);
```

#### `assertLlmJudgeQuality(string $actualResponse, string $qualityCriteria, int $minScore = 7, string $judgeAgentName = 'llm_judge', string $message = 'Response quality below threshold.'): array`

Uses LLM judge to score response quality on a scale of 1-10. Great for measuring overall response quality with specific criteria.

```php
$this->assertLlmJudgeQuality(
    $llmResponse,
    'Evaluate clarity, accuracy, completeness, and helpfulness',
    8,
    'llm_judge',
    'Response quality should be high'
);
```

#### `assertLlmJudgeComparison(string $actualResponse, string $referenceResponse, string $comparisonCriteria, string $expectedWinner = 'actual', string $judgeAgentName = 'llm_judge', string $message = 'Response comparison failed.'): array`

Compares two responses and determines which is better based on specified criteria. Perfect for A/B testing or comparing against reference responses.

```php
$this->assertLlmJudgeComparison(
    $llmResponse,
    $referenceResponse,
    'Compare accuracy, helpfulness, and clarity',
    'actual',
    'llm_judge',
    'Generated response should be better than reference'
);
```

**🔧 Setting Up LLM Judge**

To use these assertions, you need to:

1. **Create a Judge Agent** (if you haven't already):

   ```bash
   php artisan agent:make:agent LlmJudgeAgent
   ```

2. **Configure the Judge Agent** in `app/Agents/LlmJudgeAgent.php`:

   ```php
   class LlmJudgeAgent extends BaseLlmAgent
   {
       protected string $name = 'llm_judge';
       protected string $description = 'An expert evaluator for judging LLM responses';
       protected string $instructions = 'You are an expert evaluator with years of experience in assessing AI-generated content. Be objective, thorough, and consistent.';
       protected string $model = 'gpt-4o'; // Use a capable model for judging
       protected ?float $temperature = 0.1; // Low temperature for consistency
   }
   ```

3. **Register the Judge Agent** in your `AppServiceProvider.php`:
   ```php
   Agent::build(LlmJudgeAgent::class)->register();
   ```

**💡 Judge Benefits:**

- **Complex Evaluation**: Assess tone, helpfulness, accuracy beyond simple string matching
- **Contextual Understanding**: Judge considers context and nuance
- **Consistent Scoring**: Low temperature ensures reliable evaluations
- **Flexible Criteria**: Define custom evaluation criteria for any use case
- **Comparative Analysis**: Compare responses side-by-side

## Running Evaluations

To run an evaluation, use the `agent:run:eval` Artisan command with the evaluation's class name:

```bash
php artisan agent:run:eval SentimentAnalysisEvaluation
```

You can also save the evaluation results to a CSV file by adding the `--output` parameter:

```bash
php artisan agent:run:eval SentimentAnalysisEvaluation --output=results.csv
```

The results will be automatically saved to Laravel's storage directory (`storage/app/evaluations/`) to ensure proper write permissions. The CSV file will contain detailed information about each evaluation including the LLM responses, assertion results, and final status for each test case.

## What's Coming Next? 🚀

- More advanced examples and tutorials
- Dynamic tool loading
- Multi-provider LLM strategies
- UI integration guides
- And much more cool stuff!

## Troubleshooting 🔧

**Agent Not Found?** 🤷‍♀️  
Make sure you registered it with `Agent::build(...)->register()` in your service provider!

**Tool Issues?** 🛠️  
Double-check your namespaces and make sure it's registered in your agent's `registerTools()` method.

**API Key Problems?** 🔑  
Check your `.env` file - those keys need to be perfect!

**Prism-PHP Acting Up?** 🌟  
Check out the [Prism-PHP docs](https://prismphp.com/) for the latest troubleshooting tips.

## Want to Contribute? 🤝

We'd love your help! Pull requests, issues, and wild ideas are all welcome. Let's make this thing even more awesome together!

## License 📄

MIT license - because sharing is caring! Check out the [full license](https://opensource.org/licenses/MIT) if you're into that sort of thing.
