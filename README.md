# 🤖 Laravel Ai ADK

> **Build, test, and deploy intelligent AI agents with Laravel's elegant syntax**

Transform your Laravel applications into intelligent, conversation-driven experiences. The Laravel Ai ADK brings the power of AI agents to your favorite framework with the elegance and simplicity you already love.

[![Latest Version](https://img.shields.io/packagist/v/aaronlumsden/laravel-agent-adk)](https://packagist.org/packages/aaronlumsden/laravel-agent-adk)
[![Total Downloads](https://img.shields.io/packagist/dt/aaronlumsden/laravel-agent-adk)](https://packagist.org/packages/aaronlumsden/laravel-agent-adk)
[![Tests](https://github.com/aaronlumsden/laravel-agent-adk/workflows/tests/badge.svg)](https://github.com/aaronlumsden/laravel-agent-adk/actions)
[![License](https://img.shields.io/packagist/l/aaronlumsden/laravel-agent-adk)](https://packagist.org/packages/aaronlumsden/laravel-agent-adk)

## ✨ What Makes It Special?

**For Laravel Developers, By Laravel Developers**. We took everything you love about Laravel—eloquent syntax, powerful tooling, and developer happiness—and applied it to AI agent development.

```php
// Create an intelligent customer support agent in minutes
class CustomerSupportAgent extends BaseLlmAgent
{
    protected string $instructions = "You're a helpful customer support agent...";

    protected array $tools = [
        OrderLookupTool::class,
        RefundTool::class,
        KnowledgeBaseTool::class,
    ];
}

// That's it! Your agent is ready to help customers
$response = CustomerSupportAgent::ask('My order is late, can you help?')->forUser($user);
```

## 🚀 Quick Start

Get your first AI agent running in under 5 minutes:

```bash
# Install the package
composer require aaronlumsden/laravel-agent-adk

# Publish the config and run migrations
php artisan vendor:publish --provider="AaronLumsden\LaravelAiADK\AgentAdkServiceProvider"
php artisan migrate

# Create your first agent
php artisan agent:make:agent WeatherAgent

# Start chatting!
php artisan agent:chat weather_agent
```

## 🎯 Perfect For

- **Customer Support** - Intelligent, context-aware support agents
- **Content Creation** - AI writers that understand your brand
- **Data Analysis** - Agents that can query, analyze, and explain your data
- **Personal Assistants** - Helpful agents for productivity and organization
- **Educational Tools** - Tutors and learning companions
- **Process Automation** - Smart workflows that adapt and learn

## 🔥 Core Features

### 🧠 **Intelligent Agents**

Build agents that remember, reason, and react. Each agent has persistent memory, can use tools, and maintains conversation context across sessions.

### 🛠️ **Powerful Tools**

Agents can interact with your Laravel application, databases, APIs, and external services through a simple tool interface.

### 📊 **LLM-as-a-Judge Evaluation**

Test your agents at scale with AI-powered quality assessment. No more manual testing—let AI judge AI performance.

### 💾 **Vector Memory & RAG**

Give your agents long-term memory with semantic search. Store documents, conversations, and knowledge for intelligent retrieval.

### 🎨 **Beautiful Web Interface**

A clean, modern dashboard for chatting with agents, running evaluations, and monitoring performance.

### ⚡ **Streaming Responses**

Real-time, streaming conversations that feel natural and responsive.

### 🔧 **Laravel-Native**

Built for Laravel developers. Uses Eloquent, Jobs, Events, and all the Laravel patterns you know and love.

## 🏗️ Architecture Overview

```
┌─────────────────┐    ┌─────────────────┐    ┌─────────────────┐
│   Your Agent    │    │  Tools System   │    │ Vector Memory   │
│                 │    │                 │    │                 │
│ • Instructions  │◄──►│ • Database      │    │ • Semantic      │
│ • Memory        │    │ • APIs          │    │   Search        │
│ • Tools         │    │ • Files         │    │ • RAG Context   │
│ • Context       │    │ • Custom Logic  │    │ • Long-term     │
└─────────────────┘    └─────────────────┘    └─────────────────┘
         │                       │                       │
         └───────────────────────┼───────────────────────┘
                                 │
                    ┌─────────────────┐
                    │  Laravel ADK    │
                    │                 │
                    │ • Sessions      │
                    │ • Streaming     │
                    │ • Evaluation    │
                    │ • Web Interface │
                    └─────────────────┘
```

## 📚 Documentation

**Just Getting Started?**

- [🚀 Installation & Setup](docs/getting-started.md)
- [🤖 Creating Your First Agent](docs/your-first-agent.md)
- [🎯 Quick Examples](docs/examples/README.md)

**Building Powerful Agents**

- [🧠 Agent Development Guide](docs/agents.md)
- [🛠️ Tools & Capabilities](docs/tools.md)
- [💾 Vector Memory & RAG](docs/vector-memory.md)
- [📊 Evaluation & Testing](docs/evaluation.md)

**Advanced Topics**

- [⚙️ Configuration](docs/configuration.md)
- [🚀 Deployment](docs/deployment.md)
- [🔧 Extending the ADK](docs/extending.md)
- [📈 Performance & Scaling](docs/performance.md)

## 🎮 Try It Now

Want to see it in action? Check out these live examples:

```php
// A weather agent that can check current conditions
$weather = WeatherAgent::ask('What\'s the weather like in Tokyo?')->forUser($user);

// A code reviewer that understands Laravel
$review = CodeReviewerAgent::ask('Review this controller method')
    ->withContext(['code' => file_get_contents('app/Http/Controllers/UserController.php')])
    ->forUser($user);

// A data analyst that can query your database
$analysis = DataAnalystAgent::ask('How many users signed up last month?')->forUser($user);
```

## 🤝 Community & Support

- **[GitHub Discussions](https://github.com/aaronlumsden/laravel-agent-adk/discussions)** - Ask questions, share ideas
- **[Issues](https://github.com/aaronlumsden/laravel-agent-adk/issues)** - Report bugs, request features
- **[Examples Repository](https://github.com/aaronlumsden/laravel-agent-adk-examples)** - Real-world examples and templates

## 🔧 Requirements

- **PHP 8.1+**
- **Laravel 10.0+**
- **OpenAI API key** (or other LLM provider)
- **MySQL/PostgreSQL** for agent sessions and memory

## 💡 What's Different?

**Other AI Libraries:**

```python
# Complex setup, multiple configs, non-Laravel patterns
from some_ai_lib import Agent, Tools, Memory
agent = Agent(
    model="gpt-4",
    tools=[DatabaseTool(), APITool()],
    memory=VectorMemory(provider="pinecone")
)
```

**Laravel AI ADK:**

```php
// Pure Laravel elegance
class MyAgent extends BaseLlmAgent
{
    protected string $instructions = "You're a helpful assistant";
    protected array $tools = [DatabaseTool::class, ApiTool::class];
}

MyAgent::ask('Help me with something')->forUser($user);
```

## 🎉 What Developers Are Saying

> _"Finally, an AI framework that feels like Laravel. The tool system is brilliant!"_  
> — **Sarah Chen**, Senior Laravel Developer

> _"The evaluation system saved us weeks of testing. LLM-as-a-judge is a game changer."_  
> — **Marcus Rodriguez**, Technical Lead

> _"Vector memory just works. No complex setup, no vendor lock-in."_  
> — **Emily Watson**, Founder at StartupCo

## 🚧 Roadmap

- [ ] **Multi-modal Support** - Images, audio, and file processing
- [ ] **Agent Marketplace** - Share and discover community agents
- [ ] **Visual Flow Builder** - Drag-and-drop agent creation
- [ ] **Enterprise Features** - Advanced security, audit logs, and team management
- [ ] **Mobile SDK** - Native iOS and Android agent integration

## 🤝 Contributing

We love contributions! Whether it's:

- 🐛 **Bug reports** - Help us squash those pesky issues
- 💡 **Feature requests** - Share your ideas for new capabilities
- 📖 **Documentation** - Help other developers get started
- 🔧 **Code contributions** - Submit PRs for new features or fixes

Check out our [Contributing Guide](CONTRIBUTING.md) to get started.

## 📄 License

The Laravel Ai ADK is open-sourced software licensed under the [MIT license](LICENSE.md).

## 🙏 Credits

Built with ❤️ by [Aaron Lumsden](https://github.com/aaronlumsden) and the Laravel community.

Special thanks to:

- The Laravel team for creating such an elegant framework
- The open-source AI community for pushing boundaries
- All our contributors and users who make this project better

---

<p align="center">
<strong>Ready to build the future with AI agents?</strong><br>
<a href="docs/getting-started.md">Get Started →</a>
</p>
