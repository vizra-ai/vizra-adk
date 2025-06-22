# Vizra ADK Example Agents

This directory contains example agents that demonstrate how to use the Vizra ADK framework. These agents are designed to be simple, practical examples that you can learn from and adapt for your own use cases.

## Available Example Agents

### DateTimeAgent

A practical agent that helps with date, time, and timezone-related questions.

**Features:**
- Get current time in any timezone
- Calculate days between dates
- Convert times between timezones
- Format dates in various formats
- Determine what day of the week a date falls on

**Real-world use cases:**
- Customer support: "When will my order arrive if shipped today?"
- Scheduling: "What time is 3pm EST in London?"
- Project planning: "How many days until the deadline?"
- Event planning: "What day of the week is December 25th?"

## Using Example Agents

### Step 1: Register the Agent

In your application's `AppServiceProvider.php`:

```php
<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Vizra\VizraADK\Facades\Agent;
use Vizra\VizraADK\Examples\agents\DateTimeAgent;

class AppServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // Register example agents
        Agent::build(DateTimeAgent::class)->register();
    }
}
```

### Step 2: Use the Agent

You can use the agent in several ways:

#### Using the Agent Facade

```php
use Vizra\VizraADK\Facades\Agent;

// Simple query
$response = Agent::named('datetime')
    ->ask('What time is it in Tokyo?')
    ->execute();

// With session for conversation history
$response = Agent::named('datetime')
    ->ask('How many days until Christmas?')
    ->withSession('holiday-planning')
    ->execute();
```

#### Direct Usage

```php
use Vizra\VizraADK\Examples\agents\DateTimeAgent;

$response = DateTimeAgent::ask('Convert 3pm EST to London time')
    ->execute();
```

#### In a Controller

```php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Vizra\VizraADK\Facades\Agent;

class DateController extends Controller
{
    public function query(Request $request)
    {
        $question = $request->input('question');
        
        $response = Agent::named('datetime')
            ->ask($question)
            ->execute();
        
        return response()->json([
            'question' => $question,
            'answer' => $response
        ]);
    }
}
```

## Example Queries

Here are some example queries you can try with the DateTimeAgent:

```php
// Current time queries
"What time is it in New York?"
"Show me the current time in Tokyo, London, and Sydney"

// Date calculations
"How many days until December 31st?"
"How many days have passed since January 1st?"
"How many days are between March 15 and June 20?"

// Timezone conversions
"What time is 3pm EST in PST?"
"If it's 9am in London, what time is it in Singapore?"
"Convert 14:30 UTC to Eastern Time"

// Date information
"What day of the week is July 4th, 2024?"
"Is December 25th, 2024 a weekend?"
"What day of the week was January 1, 2000?"

// Formatting
"Format today's date as month/day/year"
"What's today's date in long format?"
```

## Creating Your Own Agents

Use these examples as templates for creating your own agents:

1. **Extend BaseLlmAgent**: All agents should extend the base class
2. **Define properties**: Set name, description, instructions, and model
3. **Add tools**: Include any tools your agent needs in the `$tools` array
4. **Register the agent**: Add it to your AppServiceProvider

### Tips for Creating Agents

- Keep agents focused on a specific domain
- Write clear instructions that explain the agent's capabilities
- Use appropriate temperature settings (lower for factual tasks, higher for creative tasks)
- Include relevant tools to extend the agent's capabilities
- Test your agents with various queries to ensure they behave as expected

## Example Tools

The example agents use custom tools to perform specific operations. Check the `/examples/tools/` directory for tool implementations:

- `DateCalculatorTool.php` - Performs date/time calculations for the DateTimeAgent

Tools must implement the `ToolInterface` and return JSON-encoded responses.