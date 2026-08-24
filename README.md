> ## ⚠️ This package is no longer maintained
>
> **Vizra ADK is retired as of August 2026. `v0.0.48` is the final release.**
> There will be no further releases, bug fixes, or security patches. Issues and
> pull requests are not being monitored or responded to.
>
> **It still works.** Nothing has been taken down — `v0.0.48` runs on PHP 8.2+
> and Laravel 11, 12, and 13, and Packagist will keep serving it. If the ADK
> does what you need, pin it and carry on:
>
> ```json
> "vizra/vizra-adk": "0.0.48"
> ```
>
> Be aware that its dependencies will age out from under it — `prism-php/prism`
> is pinned at `^0.99.20`, and future Laravel versions are untested.
>
> ### What happened
>
> Vizra has moved to [**vizra/evals**](https://github.com/vizra-ai/vizra-evals),
> an evaluation framework for AI agents built on the official Laravel AI SDK and
> Pest.
>
> **Evals is not a drop-in replacement for the ADK.** It is a different tool: the
> ADK builds agents, evals tests them. There is no migration path, because there
> is nothing to migrate to.
>
> ### If you need to move off the ADK
>
> - **To build agents** — use the official [Laravel AI SDK](https://laravel.com/docs/ai)
>   directly. It is the foundation Vizra now builds on.
> - **To evaluate agents** — see [vizra/evals](https://github.com/vizra-ai/vizra-evals).
> - **To keep the ADK alive** — fork it. This is MIT licensed and forks are
>   genuinely welcome. If you maintain a community fork, open a discussion and it
>   can be linked from here.
>
> Thank you to everyone who used, reported, and contributed to this package.

---
<p align="center">
  <img src="https://vizra.ai/img/vizra-logo.svg" alt="Vizra Logo" width="200">
</p>

<h1 align="center">Vizra ADK - AI Agent Development Kit for Laravel</h1>

<p align="center">
  <strong>Build intelligent AI agents with Laravel's elegant syntax</strong>
</p>

<p align="center">
  <a href="https://packagist.org/packages/vizra/vizra-adk"><img src="https://img.shields.io/packagist/v/vizra/vizra-adk" alt="Latest Version"></a>
  <a href="https://packagist.org/packages/vizra/vizra-adk"><img src="https://img.shields.io/packagist/dt/vizra/vizra-adk" alt="Total Downloads"></a>
  <a href="LICENSE.md"><img src="https://img.shields.io/badge/license-MIT-blue.svg" alt="MIT License"></a>
  <a href="https://www.php.net"><img src="https://img.shields.io/badge/PHP-8.2%2B-777BB4.svg" alt="PHP"></a>
  <a href="https://laravel.com"><img src="https://img.shields.io/badge/Laravel-11.0%2B-FF2D20.svg" alt="Laravel"></a>
  <a href="https://discord.gg/CRRzmvS5MK"><img src="https://img.shields.io/badge/Discord-Join%20Community-5865F2.svg?logo=discord&logoColor=white" alt="Discord"></a>
</p>

Vizra ADK is a comprehensive Laravel package for building autonomous AI agents that can reason, use tools, and maintain persistent memory. Create intelligent, interactive agents that integrate seamlessly with your Laravel application.

## ✨ Key Features

- **🤖 Multi-Model AI Support** - Works with OpenAI, Anthropic, and Google Gemini + more, thanks to prism PHP
- **🎯 Sub-Agent Delegation** - Agents can delegate tasks to specialized sub-agents
- **🛠️ Extensible Tool System** - Give agents abilities to interact with databases, APIs, and external services
- **🧠 Persistent Memory** - Agents remember conversations and learn from interactions across sessions
- **🔄 Agent Workflows** - Build complex processes with sequential, parallel, conditional flows and loops
- **⚡ Execution Modes** - Multiple trigger modes: conversational, scheduled, webhook, event-driven, and queue jobs
- **📊 Evaluation Framework** - Automated quality testing framework for agents at scale with LLM-as-a-Judge
- **💬 Streaming Responses** - Real-time, token-by-token streaming for responsive user experiences
- **📈 Comprehensive Tracing** - Debug and monitor agent execution with detailed traces
- **🎨 Web Dashboard** - Beautiful Livewire v4-powered interface for testing and monitoring
- **🔧 Laravel Native** - Built with Laravel patterns: Artisan commands, Eloquent models, service providers

## 🚀 Quick Start

```bash
# Install via Composer
composer require vizra/vizra-adk

# Publish config and run migrations
php artisan vizra:install

# Create your first agent
php artisan vizra:make:agent CustomerSupportAgent

# Start chatting!
php artisan vizra:chat customer_support
```

## 💻 Basic Usage

```php
<?php

use Vizra\VizraADK\Agents\BaseLlmAgent;
use Vizra\VizraADK\Facades\Agent;

// Define your agent
class CustomerSupportAgent extends BaseLlmAgent
{
    protected string $name = 'customer_support';
    protected string $description = 'Helps customers with inquiries';
    protected string $instructions = 'You are a helpful customer support assistant.';
    protected string $model = 'gpt-4o';

    protected array $tools = [
        OrderLookupTool::class,
        RefundProcessorTool::class,
    ];
}

// That's it! No registration needed - agents are auto-discovered

// Use your agent immediately
$response = CustomerSupportAgent::run('I need help with my order')
    ->forUser($user)
    ->go();
```

## 🛠️ Creating Tools

Tools extend your agent's capabilities:

```php
use Vizra\VizraADK\Contracts\ToolInterface;
use Vizra\VizraADK\System\AgentContext;

class OrderLookupTool implements ToolInterface
{
    public function definition(): array
    {
        return [
            'name' => 'order_lookup',
            'description' => 'Look up order information',
            'parameters' => [
                'type' => 'object',
                'properties' => [
                    'order_id' => [
                        'type' => 'string',
                        'description' => 'The order ID',
                    ],
                ],
                'required' => ['order_id'],
            ],
        ];
    }

    public function execute(array $arguments, AgentContext $context): string
    {
        $order = Order::find($arguments['order_id']);

        return json_encode([
            'status' => 'success',
            'order' => $order->toArray(),
        ]);
    }
}
```

## 🔧 Extending with Macros

Vizra ADK supports Laravel's powerful macro pattern, allowing you to add custom methods to core classes without modifying the package:

```php
use Vizra\VizraADK\Services\AgentBuilder;
use Vizra\VizraADK\Facades\Agent;
use Illuminate\Database\Eloquent\Model;

// Register a macro in your AppServiceProvider::boot()
AgentBuilder::macro('track', function (Model $model) {
    $this->trackedModel = $model;
    return $this;
});

// Step 1: Use the macro when registering the agent
Agent::build(CustomerSupportAgent::class)
    ->track(Unit::find(12))  // Track token usage for analytics
    ->register();

// Step 2: Run the agent using the executor API
$response = CustomerSupportAgent::run('I need help')
    ->forUser($user)
    ->go();
```

Learn more in the [Macros Documentation](docs/MACROS.md).

## 📚 Full Documentation

For comprehensive documentation, tutorials, and API reference, visit:

**[📖 https://vizra.ai/docs](https://vizra.ai/docs)**

## 🌟 Why Vizra ADK?

- **Laravel First** - Built specifically for Laravel developers with familiar patterns
- **No Vendor Lock-in** - Switch between AI providers without changing your code
- **Developer Experience** - Elegant API, helpful error messages, and extensive documentation
- **Community Driven** - Open source with active development and support

## 🚀 Vizra Cloud Platform (Coming Soon!)

Take your agents to the next level with **Vizra Cloud** - our professional evaluation and trace analysis platform designed specifically for AI agents built with Vizra ADK.

### What's Coming:

- **📊 Cloud Evaluation Runs** - Run comprehensive evaluations at scale in the cloud
- **🔍 Interactive Trace Visualization** - Debug and understand agent behavior with visual traces
- **📈 Performance Analytics** - Track response times, token usage, and quality metrics
- **🔄 Regression Detection** - Automatically catch when changes break existing functionality
- **🤝 Team Collaboration** - Share evaluation results and insights with your team
- **📜 Evaluation History** - Track agent performance over time and across versions
- **🎯 CI/CD Integration** - Run evaluations automatically in your deployment pipeline
- **💾 Centralized Results** - All evaluation data and traces in one searchable platform

**[Join the waitlist at vizra.ai →](https://vizra.ai/cloud)**

## 🔧 Requirements

- PHP 8.2+
- Laravel 11.0+
- MySQL/PostgreSQL
- Livewire ^4.0 (for dashboard UI)
- At least one LLM API key (OpenAI, Anthropic, or Google)

## 🤝 Community & Support

- **[Discord Community](https://discord.gg/CRRzmvS5MK)** - Join our Discord for help, discussions, and updates
- **[GitHub Discussions](https://github.com/vizra-ai/vizra-adk/discussions)** - Ask questions, share ideas
- **[GitHub Issues](https://github.com/vizra-ai/vizra-adk/issues)** - Report bugs, request features
- **[Twitter/X](https://twitter.com/aaronlumsden)** - Latest updates and tips

## 💖 Sponsorship

### Support Vizra ADK's Development

Vizra ADK is an open-source project that takes significant time and effort to maintain and improve. If you find this package valuable for your projects, please consider sponsoring its development!

**[🎯 Become a Sponsor on GitHub](https://github.com/sponsors/aaronlumsden)**

### Why Sponsor?

- 🚀 **Accelerate Development** - Your support helps us dedicate more time to new features
- 📚 **Better Documentation** - Fund comprehensive tutorials and examples
- 🛠️ **Priority Support** - Get faster responses to your issues and questions
- ☁️ **Shape the Future** - Have a direct say in our roadmap and upcoming features
- 💙 **Support Open Source** - Keep AI development tools accessible to everyone

### Sponsor Benefits

- **$5+/month** - 🎖️ Get a Sponsor badge on your GitHub profile
- **$25+/month** - 📝 Your logo or name featured in our project README
- **$100+/month** - 🌐 Logo placement on the Vizra website + 🚀 Access to pre-release builds
- **$1,000+/month** - 💬 Direct support via your company chat app + All previous benefits

Every contribution, no matter the size, makes a real difference in sustaining this project. Thank you for your support! 🙏

## 📄 License

Vizra ADK is open-sourced software licensed under the [MIT license](https://github.com/vizra-ai/vizra-adk/blob/master/license.md).

## 🙏 Credits

Built with ❤️ by the Vizra team and contributors.

Special thanks to:

- [Laravel](https://github.com/laravel) for creating an amazing framework
- [Prism PHP](https://github.com/prism-php/prism) for the powerful LLM integration library
- [Livewire](https://livewire.laravel.com/) v4 for making our web dashboard reactive and beautiful
- [League CSV](https://csv.thephpleague.com/) for handling CSV in our evaluation framework
- The AI/ML community for pushing boundaries

---

<p align="center">
<strong>Ready to build intelligent AI agents?</strong><br>
<a href="https://vizra.ai/docs">Get Started →</a> • 
<a href="https://vizra.ai/cloud">Join Cloud Waitlist →</a> • 
<a href="https://github.com/sponsors/aaronlumsden">Become a Sponsor →</a>
</p>
