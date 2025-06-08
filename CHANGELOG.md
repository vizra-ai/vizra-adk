# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

- Initial release of Laravel AI ADK
- Agent system with BaseLlmAgent base class
- Tool system with ToolInterface for declarative tools
- Evaluation framework with BaseEvaluation classes
- Multi-LLM support (OpenAI, Anthropic, Gemini via Prism-PHP)
- Context management and conversation history
- Streaming responses for real-time interactions
- Vector Memory & RAG capabilities
- Web interface for agent interaction
- LLM-as-a-Judge evaluation system
- Tracing and debugging capabilities
- Comprehensive Artisan commands for development
- Laravel integration with service providers and facades

### Features

- **Agent Development**: Class-based agents extending BaseLlmAgent
- **Tool System**: Declarative tools implementing ToolInterface
- **Evaluation**: BaseEvaluation classes for testing agent quality
- **Memory Management**: Persistent conversation history and state
- **Streaming**: Real-time streaming conversations
- **Vector Memory**: Semantic search and RAG implementation
- **Web Interface**: Clean, modern dashboard for agent interaction
- **Multi-LLM**: Support for OpenAI, Anthropic, and Gemini
- **Laravel Native**: Built using Laravel patterns and conventions

### Artisan Commands

- `agent:install` - Package setup and configuration
- `agent:make:agent` - Generate new agent classes
- `agent:make:tool` - Generate new tool classes
- `agent:make:eval` - Generate new evaluation classes
- `agent:chat` - Interactive chat interface with agents
- `agent:eval` - Run evaluation suites

### Requirements

- PHP 8.2+
- Laravel 11.0+ | 12.0+
- Prism-PHP ^0.60.0
- League CSV ^9.23
- Livewire ^3.0

### Documentation

- Comprehensive README with setup and usage examples
- Streaming implementation guide
- EvalRunner implementation documentation
- Vector Memory setup guide
- Workflow agents guide
- Configuration documentation
- Multiple example implementations

## [0.1.0] - Initial Development

### Added

- Core architecture and foundation
- Basic agent and tool system
- Initial evaluation framework
- Laravel service provider integration
- Database migrations for agent sessions
- Configuration system

---

**Note**: This project is currently in active development. Version numbers will be assigned once the initial stable release is ready.
