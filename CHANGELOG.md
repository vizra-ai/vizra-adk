# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]
## [0.0.40] - 2025-11-11

Add handling for Generator objects in Tracer
Update event handling for Prism v0.92+ compatibility

## [0.0.39] - 2025-11-11

PR #80 (e13b2e7) - Streaming Response Bug Fix

  Fixed: Streaming text accumulation issues
  - Fixed access to Chunk.text property instead of non-existent delta
  - Removed AgentMessage mutator that was causing double-encoding of content
  - Changed to use property_exists() instead of method_exists() for Chunk detection
  - Files changed: BaseLlmAgent.php, AgentMessage.php (39 lines removed)

  PR #81 (126d2d5) - Dependency Update

  Updated: Prism-php dependency version alignment
  - Bumped prism-php from v0.84 to v0.92 to align with changelog
  - Minor adjustments to BaseLlmAgent.php to accommodate dependency changes
  - Files changed: composer.json, composer.lock, BaseLlmAgent.php

  PR #82 (11ea505) - Vector Memory Search Improvements

  Fixed: Vector memory search command
  - Updated VectorMemorySearch command implementation
  - Documentation updates in CLAUDE.md
  - Files changed: VectorMemorySearch.php, CLAUDE.md

  PR #69 (eecb5ab) - User ID Type Refactoring & Vector Memory Enhancements

  Changed: User ID fields from integer to string/mixed type across the system
  - Added migration to convert user_id columns to string type
  - Updated models: AgentMemory, AgentSession, VectorMemory
  - Updated services: AgentManager, MemoryManager, StateManager, VectorMemoryManager
  - Fixed: pgvector embedding column handling with native bindings
  - Added: Validation for embeddings before saving vector memories
  - Added: Error handling - throw exception when embedding provider returns empty data
  - Added: Comprehensive test coverage for all changes
  - Files changed: 15 files (209 additions, 47 deletions)

## [0.0.38] - 2025-10-29

 Added

  - Dynamic Agent Name Resolution: Agents can now be registered and executed with runtime-determined identities for multi-tenant applications
  (e.g., team_agent_24, team_agent_42)
  - Prism v0.92.0 Streaming Support: Full support for Prism's new streaming event system with type-safe StreamEvent objects
  - Streaming UI in ChatInterface: Real-time streaming responses in Vizra ADK chat UI using Livewire 3's wire:stream pattern
  - Lightweight User Context: New withUserContext(array) method for passing minimal user data without serializing entire User models
  - Multi-tenant MCP Config Overrides: Per-tenant MCP server configuration via AgentContext with deep-merge support
  - Comprehensive Documentation: Added docs for lightweight user context and multi-tenant MCP configuration

  Fixed

  - Image/Document Persistence: Prism Image and Document objects now properly filtered before database save to prevent serialization errors
  - Session Association: Fixed user_id extraction to check both 'user_id' and 'id' keys in context
  - Artisan Command: Corrected artisan command reference in eval runner blade template

  Changed

  - Agent Name Resolution Strategy: Implemented three-tier resolution system (explicit context → registry → fallback)
  - User Context Priority System: Three-tier priority for user data (explicit withUserContext() → toAgentContext() → toArray())
  - MCPClientManager Binding: Changed from singleton to non-singleton for proper multi-tenant isolation
  - Message Persistence Architecture: AgentChat handles UI display while BaseLlmAgent handles all persistence

  Improved

  - Streaming Event Handling: Rewrote buffer accumulation to handle both snake_case and kebab-case event types
  - Template Variable Clarity: Prefixed template variables with '@' in agent, evaluation, and tool creation templates
  - Test Coverage: Added tests for multi-tenant MCP behavior, config overrides, and image/document persistence

## [0.0.37] - 2025-10-15

Add multi-tenant MCP config overrides support
Implements per-tenant MCP server configuration overrides via AgentContext, enabling dynamic API keys and settings for multi-tenant scenarios. MCPClientManager is now bound as non-singleton for isolation, and context overrides are deep-merged with base config. Adds documentation and comprehensive tests for override logic and multi-tenant behavior.

## [0.0.36] - 2025-10-09



## [0.0.35] - 2025-10-09

Refactor Tracer to use HasLogging trait
Replaced direct calls to the logger with HasLogging trait methods (logInfo, logWarning) throughout the Tracer service. This centralizes logging logic and allows for more consistent logging behavior, with all logs now tagged under the 'traces' channel.

fix: Handle plain string content properly in setContentAttribute
The previous fix caused plain strings to become NULL when read from database.
Now properly encodes all value types (plain strings, JSON strings, arrays/objects)
so Laravel's JSON cast can decode them correctly.

fix: Prevent double-encoding of JSON content in AgentMessage
When AI providers return tool results as JSON strings, the model's JSON
cast was double-encoding the content, resulting in triple-encoded data.

This adds a setContentAttribute() mutator that:
- Detects if incoming value is already a valid JSON string
- Decodes once and re-encodes to prevent double-encoding
- Maintains backwards compatibility with arrays/objects
- Works seamlessly with existing JSON cast behavior

Fixes triple-encoding issue with MCP tools and AI provider responses.

## [0.0.35] - 2025-10-06

Add SSE response parsing for HTTP MCP servers
Some MCP servers (like Context7) return responses in Server-Sent Events (SSE) format instead of plain JSON. This commit adds support for parsing SSE responses while maintaining backward compatibility.

crease agent_messages.content column size to support large MCP to...

fix: Call afterLlmResponse hook for streaming responses


## [0.0.34] - 2025-10-03

Add streaming state check in BaseLlmAgent
BaseLlmAgent now checks for a 'streaming' state in the context and sets the streaming property accordingly. This allows agents to be configured for streaming behavior based on context state.

END\
NED

## [0.0.33] - 2025-10-02

Add HTTP transport support for MCP clients
Introduces MCPHttpClient for HTTP/SSE transport and refactors MCPClientManager to support both STDIO and HTTP transports via MCPTransport enum. MCPClient is now deprecated in favor of MCPStdioClient, and a common MCPClientInterface is added. Configuration and tests are updated to support and verify HTTP transport functionality.

## [0.0.32] - 2025-10-01

Persist user messages with images and documents
Adds user messages, including images and documents, to the context for persistence after message preparation. This ensures that all relevant user input is retained for future reference.

## [0.0.31] - 2025-09-29

 Fixed issue where user ID wasn't assigned to session creation

Add configurable logging and global enable/disable support
Introduces a HasLogging trait for unified, configurable logging across the package, with support for log levels and component-specific toggles. Adds a global 'enabled' flag to the config to allow disabling the entire package, and updates all relevant services, providers, and tools to respect these settings. Includes comprehensive tests for package disabling and logging behavior.

Remove duplicate user message addition in BaseLlmAgent
Eliminates redundant addition of the user message with attachments in BaseLlmAgent, preventing duplicate user messages during execution. Adds unit tests to verify correct message deduplication and proper handling of conversation history and input.


## [0.0.30] - 2025-09-26

Add embedder and semanticRatio to Meilisearch driver
Introduces 'embedder' and 'semantic_ratio' configuration options for the Meilisearch vector driver. These are now included in search requests as part of the 'hybrid' parameter, and corresponding test coverage has been added.

Make MCPClient timeout configurable and add tests
Changed MCPClient to use a dynamic timeout based on configuration, replacing the fixed maxAttempts value. Added unit tests to verify correct timeout handling and calculation for various scenarios.

Make web route prefix configurable
Replaces the hardcoded 'vizra' route prefix with a value from the 'vizra-adk.routes.web.prefix' config, defaulting to 'vizra'. This allows customization of the route prefix via configuration.

## [0.0.29] - 2025-09-15

Add support for OpenAI stateful responses

## [0.0.28] - 2025-09-11

Update namespace conversion to use DIRECTORY_SEPARATOR for cross-platfor

Support array config for provider tools in BaseLlmAgent

## [0.0.27] - 2025-09-04

Fix provider enum handling in BaseLlmAgent


## [0.0.27] - 2025-09-04

### Fixed
- **OpenRouter Authentication Error**: Fixed issue where using `Provider::OpenRouter` enum directly in agent class properties caused authentication failures. The `getProvider()` method now properly handles both Provider enum instances and string values, allowing developers to use either approach.

### Changed
- Enhanced provider handling in `BaseLlmAgent` to support both string values (e.g., `'openrouter'`) and Provider enum instances (e.g., `Provider::OpenRouter`)

## [0.0.26] - 2025-09-04

🚀 Enhanced Streaming Response Handling

  - Improved streaming functionality: Added intelligent buffering system that
  captures the complete response text while streaming
  - Context persistence: Streaming responses now properly update the agent
  context with the complete assistant message after streaming completes
  - Event dispatching: Added AgentResponseGenerated event firing for streaming
  responses
  - Trace completion: Proper trace span closure for streaming operations with
  success/error status

  📚 Laravel Boost Integration

  - New Boost guidelines system: Added comprehensive ADK documentation as Laravel
   Boost guidelines
  - Guidelines included:
    - Agent creation guide
    - Best practices documentation
    - Evaluation framework guide
    - Memory usage patterns
    - Sub-agents implementation
    - Tool creation guide
    - Troubleshooting guide
    - Workflow patterns documentation
  - New command: Added BoostInstallCommand for installing ADK guidelines into
  Laravel Boost

  🔧 Bug Fixes

  - Fixed Prism PHP version constraint: Corrected composer.json version format
  from ^0.84 to ^0.84.0 for proper dependency resolution

  Files Modified

  - composer.json - Fixed Prism PHP version constraint
  - src/Agents/BaseLlmAgent.php - Enhanced streaming response handling
  - src/Providers/AgentServiceProvider.php - Added Boost command registration
  - src/Console/Commands/BoostInstallCommand.php - New Boost installation command

## [0.0.25] - 2025-08-29

Merge vizra-adk providers into prism config
Adds logic to merge providers from the vizra-adk configuration into the prism.providers config array if any are present. This ensures that all relevant providers are registered in the prism configuration.

## [0.0.24] - 2025-08-27

Optimize message persistence in StateManager
Refactored StateManager to only insert new messages instead of deleting and reinserting all messages on each save. Added tests to verify message persistence, prevent duplicates, handle long histories, and ensure correct incremental saving across sessions.

## [0.0.23] - 2025-08-26

Add tests for fileToClassName with Windows and Unix paths

Fix misplaced closing tag in trace-span partial

Enhanced ⁠afterLLmResponse Hook
- Added request parameter to the ⁠afterLLmResponse method 
- Now receives both the original request and response objects 
- Enables complete request-response pair logging for better debugging and monitoring

New ⁠onToolException Hook
- Introduced a new hook to handle exceptions during tool execution 
- Provides centralized error handling for tool-related failures 
- Allows for custom error recovery strategies and logging

New Event Types

⁠ToolCallFailed Event
- Emitted when a tool call encounters an error 
- Includes error details, tool name, and input parameters 
- Enables tracking of tool reliability and failure patterns
⁠LLmCallFailed Event
- Emitted when an LLM API call fails 
- Contains error information, request details, and failure context 
- Facilitates monitoring of LLM service health and error rates

Add OpenRouter provider support


## [0.0.22] - 2025-08-23

encapsulate prims request creation as well as tool creation into their own methods

## [0.0.21] - 2025-08-20

Bug fix: Refactor VectorMemoryStore command parameter structure

## [0.0.20] - 2025-08-14

Add HTTP timeout config for LLM API requests
Introduces 'http' configuration options in vizra-adk.php for controlling timeout and connection timeout for LLM API calls. BaseLlmAgent now applies these settings to Prism requests to prevent premature timeouts.

## [0.0.19] - 2025-08-14



## [0.0.18] - 2025-08-14

Provider Tool Support
Added a providerTools property to BaseLlmAgent and a getProviderToolsForPrism() method to convert provider tool types to ProviderTool objects for use with Prism. [1] [2]
Updated the agent execution flow to include provider tools in the Prism request if any are configured.
Agent Configuration Improvements
Made the maximum number of agent steps (maxSteps) configurable via a class property, instead of hardcoding the value. [1] [2]
Conversation History Handling
Simplified the retrieval of conversation history by always converting collections to arrays, improving reliability.
Dependency Updates

dd CLAUDE.md and GitHub star prompt to install command

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
