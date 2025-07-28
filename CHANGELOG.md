# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]
## [0.0.17] - 2025-07-28



## [0.0.16] - 2025-07-21

Added support for pseudo-terminal (PTY) mode and improved environment handling in MCPClient. Updated configuration to allow custom npx path and app directory access. Improved client connection management in MCPClientManager to handle stale clients and added use_pty option to server configs.

## [0.0.15] - 2025-07-14

Enhances agent tracing to support sub-agent delegation with parent trace context preservation and restoration. Updates the chat interface UI to display sub-agent info, improves typing indicator and send button logic, and adds polling for running traces. Refactors prompt versioning to support Blade templates, and adds feature/browser tests for chat interface behaviors.

Introduces JudgeBuilder to enable fluent, agent-based assertion syntax in evaluations. Updates BaseEvaluation to expose judge() and make recordAssertion public. Adds comprehensive unit tests for JudgeBuilder and its integration with BaseEvaluation. Removes obsolete MCPMakeAgentCommand.

Corrects the logic for determining the 'days' value in AgentTraceCleanupCommand. Now, the command only uses the config default if the 'days' option is not provided, ensuring explicit zero values are respected.

Qdrant and in-memory vector drivers have been removed from configuration, validation, and setup logic. Only 'pgvector' and 'meilisearch' are now supported for vector storage. Also, AgentTraceCommand now includes 'input', 'output', and 'metadata' in span output.

Introduces AgentVectorProxy to simplify agent vector memory operations, making vector and RAG methods public and context-aware. Adds GeminiEmbeddingProvider for Google Gemini embeddings. Updates VectorMemoryManager and related tools/tests to use agent class instead of agent name, streamlining method signatures and usage. Enhances Meilisearch driver with fallback similarity calculation. Updates configuration for vector memory and RAG features.


## [0.0.14] - 2025-07-13

Updates

## [0.0.13] - 2025-07-11

Adds granular control over agent conversation history via new context strategy and filtering in BaseLlmAgent. Refactors Livewire ChatInterface to provide separate context state, session, and long-term memory data for improved UI display. Updates JSON viewer and chat interface Blade templates for better layout, scrolling, and modal handling. Renames attachment docs and updates README and example usage to use run() instead of ask()

## [0.0.12] - 2025-07-11



## [0.0.11] - 2025-06-24



## [0.0.10] - 2025-06-23



## [0.0.9] - 2025-06-22



## [0.0.8] - 2025-06-22



## [0.0.7] - 2025-06-22



## [0.0.6] - 2025-06-22



## [0.0.5] - 2025-06-22




### Added

- Initial release of Vizra SDK
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

- `vizra:install` - Package setup and configuration
- `vizra:make:agent` - Generate new agent classes
- `vizra:make:tool` - Generate new tool classes
- `vizra:make:eval` - Generate new evaluation classes
- `vizra:chat` - Interactive chat interface with agents
- `vizra:eval` - Run evaluation suites

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
