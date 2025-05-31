# Laravel Agent ADK 🤖✨ (Agent Development Kit)

**Laravel Agent ADK** is a super cool PHP package that makes building AI agents ridiculously easy! 🚀 Think of it as your AI agent's best friend - it handles all the boring stuff so you can focus on building awesome intelligent systems.

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
- **⚡ Artisan Commands:**
  - `php artisan agent:install` - Get everything set up
  - `php artisan agent:make:agent <AgentName>` - Scaffold new agents
  - `php artisan agent:make:tool <ToolName>` - Create new tools
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

    protected function registerTools(): array
    {
        return [
            GetCurrentWeatherTool::class, // Your awesome tool goes here!
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

### 3. Register Your Agent 📝

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

### 4. Chat With Your Agent! 💬

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

### 5. Get More Control (If You Want) 🎛️

```php
use AaronLumsden\LaravelAgentADK\Facades\Agent;

// Grab your agent directly
$weatherAgent = Agent::named('weather_reporter');

// Do your thing with it!
```

### 6. Listen to Events 👂

Hook into events in your `EventServiceProvider.php`:

```php
// app/Providers/EventServiceProvider.php
protected $listen = [
    \AaronLumsden\LaravelAgentADK\Events\AgentExecutionStarting::class => [
        // Your event listeners here
    ],
    \AaronLumsden\LaravelAgentADK\Events\LlmCallInitiating::class => [
        // More listeners
    ],
    \AaronLumsden\LaravelAgentADK\Events\ToolCallCompleted::class => [
        // Even more listeners!
    ],
];
```

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
