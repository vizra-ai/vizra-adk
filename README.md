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
</p>

Vizra ADK is a comprehensive Laravel package for building autonomous AI agents that can reason, use tools, and maintain persistent memory. Create intelligent, inertactive agents that integrate seamlessly with your Laravel application.

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
- **🎨 Web Dashboard** - Beautiful Livewire-powered interface for testing and monitoring
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
$response = CustomerSupportAgent::ask('I need help with my order')
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

## 📚 Full Documentation

For comprehensive documentation, tutorials, and API reference, visit:

**[📖 https://vizra.ai/docs](https://vizra.ai/docs)**

## 🌟 Why Vizra ADK?

- **Laravel First** - Built specifically for Laravel developers with familiar patterns
- **No Vendor Lock-in** - Switch between AI providers without changing your code
- **Developer Experience** - Elegant API, helpful error messages, and extensive documentation
- **Community Driven** - Open source with active development and support

## 🚀 Vizra Cloud Platform (Coming Soon!)

Take your agents to production with **Vizra Cloud** - our managed hosting platform designed specifically for AI agents built with Vizra ADK.

### What's Coming:

- **🌐 One-Click Deployment** - Deploy agents directly from GitHub
- **⚡ Auto-Scaling** - Handle any load with automatic scaling
- **🔒 Enterprise Security** - SOC2 compliant infrastructure
- **📊 Analytics Dashboard** - Monitor usage, costs, and performance
- **🤝 Team Collaboration** - Manage agents and deployments with your team
- **🌍 Global Edge Network** - Low latency worldwide
- **💾 Managed Vector Database** - Built-in memory storage
- **🔧 Zero Configuration** - We handle the infrastructure

**[Join the waitlist at vizra.ai →](https://vizra.ai/cloud)**

## 🤝 Sponsorship

Vizra ADK is open source and free to use. If you find it valuable, please consider sponsoring the project to help us maintain and improve it.

### 💖 Become a Sponsor

Your sponsorship helps us:

- 🚀 Develop new features faster
- 🐛 Provide better support and bug fixes
- 📚 Improve documentation and examples
- 🌟 Keep the project sustainable

**[Sponsor Vizra ADK on GitHub →](https://github.com/sponsors/vizra-ai)**

Every contribution, no matter the size, makes a difference! Sponsors get:

- 🏆 Recognition in our README and website
- 🎯 Priority support for issues
- 🗳️ Influence on the roadmap

## 🔧 Requirements

- PHP 8.2+
- Laravel 11.0+
- MySQL/PostgreSQL
- At least one LLM API key (OpenAI, Anthropic, or Google)

## 🤝 Community & Support

- **[GitHub Discussions](https://github.com/vizra-ai/vizra-adk/discussions)** - Ask questions, share ideas
- **[GitHub Issues](https://github.com/vizra-ai/vizra-adk/issues)** - Report bugs, request features
- **[Twitter/X](https://twitter.com/aaronlumsden)** - Latest updates and tips

## 📄 License

Vizra ADK is open-sourced software licensed under the [MIT license](https://github.com/vizra-ai/vizra-adk/blob/master/license.md).

## 🙏 Credits

Built with ❤️ by the Vizra team and contributors.

Special thanks to:

- [Laravel](https://github.com/laravel) for creating an amazing framework
- [Prism PHP](https://github.com/prism-php/prism) for the powerful LLM integration library
- [Livewire](https://livewire.laravel.com/) for making our web dashboard reactive and beautiful
- [League CSV](https://csv.thephpleague.com/) for handling CSV in our evaluation framework
- The AI/ML community for pushing boundaries

---

<p align="center">
<strong>Ready to build intelligent AI agents?</strong><br>
<a href="https://vizra.ai/docs">Get Started →</a> • 
<a href="https://github.com/sponsors/vizra-ai">Sponsor →</a> • 
<a href="https://vizra.ai">Join Cloud Waitlist →</a>
</p>
