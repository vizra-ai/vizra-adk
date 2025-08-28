# Contributing to Vizra ADK

First off, thank you for considering contributing to Vizra ADK! It's people like you that make Vizra ADK such a great tool for the Laravel community.

## 📋 Table of Contents
- [Code of Conduct](#code-of-conduct)
- [How Can I Contribute?](#how-can-i-contribute)
- [Development Setup](#development-setup)
- [Development Workflow](#development-workflow)
- [Coding Standards](#coding-standards)
- [Testing](#testing)
- [Pull Request Process](#pull-request-process)
- [Reporting Bugs](#reporting-bugs)
- [Suggesting Features](#suggesting-features)
- [Documentation](#documentation)

## 📜 Code of Conduct

### Our Pledge
We are committed to providing a friendly, safe, and welcoming environment for all contributors.

### Expected Behavior
- Be respectful and inclusive
- Accept constructive criticism gracefully
- Focus on what's best for the community
- Show empathy towards other community members

### Unacceptable Behavior
- Harassment, discrimination, or offensive comments
- Personal attacks or trolling
- Publishing others' private information
- Other conduct that could be considered inappropriate

## 🤝 How Can I Contribute?

### Ways to Contribute
- 🐛 Report bugs
- 💡 Suggest new features
- 📝 Improve documentation
- 🔧 Submit bug fixes
- ⚡ Add new features
- 🧪 Write tests
- 💬 Help others in discussions
- ⭐ Star the repository

## 🛠️ Development Setup

### Prerequisites
- PHP 8.2 or higher
- Composer
- Laravel 11.0 or higher
- Git

### Local Setup
```bash
# Fork the repository on GitHub

# Clone your fork
git clone https://github.com/YOUR_USERNAME/vizra-adk.git
cd vizra-adk

# Add upstream remote
git remote add upstream https://github.com/vizra-ai/vizra-adk.git

# Install dependencies
composer install

# Create a test Laravel app (optional)
composer create-project laravel/laravel test-app
cd test-app
composer require vizra/vizra-adk:@dev --prefer-source
```

### Environment Setup
```bash
# Copy .env.example if it exists
cp .env.example .env

# Set up your API keys for testing
OPENAI_API_KEY=your_key
ANTHROPIC_API_KEY=your_key
GEMINI_API_KEY=your_key
```

## 🔄 Development Workflow

### 1. Start from the Right Branch
```bash
# Sync your fork with upstream
git fetch upstream
git checkout develop
git merge upstream/develop

# Create a feature branch
git checkout -b feature/your-feature-name
```

### 2. Make Your Changes
- Write clear, concise code
- Follow the coding standards
- Add tests for new functionality
- Update documentation as needed

### 3. Test Your Changes
```bash
# Run the test suite
composer test

# Run specific tests
./vendor/bin/pest tests/Unit/YourTest.php

# Run with coverage
composer test:coverage
```

### 4. Commit Your Changes
```bash
# Stage your changes
git add .

# Commit with a descriptive message
git commit -m "feat: add new vector memory provider"

# Use conventional commits:
# feat: New feature
# fix: Bug fix
# docs: Documentation changes
# test: Test additions or fixes
# refactor: Code refactoring
# style: Code style changes
# chore: Maintenance tasks
```

### 5. Push and Create PR
```bash
# Push to your fork
git push origin feature/your-feature-name

# Go to GitHub and create a Pull Request
```

## 📏 Coding Standards

### PHP Standards
We follow PSR-12 coding standards with some additions:

```php
<?php

namespace Vizra\VizraADK\Agents;

use Vizra\VizraADK\Contracts\AgentInterface;

/**
 * Class description
 */
class ExampleAgent extends BaseLlmAgent
{
    /**
     * Agent configuration
     */
    protected string $name = 'example_agent';
    protected string $description = 'Clear, concise description';
    protected string $model = 'gpt-4o';
    
    /**
     * Method description
     *
     * @param array $input
     * @return array
     */
    public function process(array $input): array
    {
        // Implementation
        return $result;
    }
}
```

### Naming Conventions
- **Classes**: PascalCase (e.g., `CustomerSupportAgent`)
- **Methods**: camelCase (e.g., `processMessage()`)
- **Properties**: camelCase (e.g., `$messageHistory`)
- **Constants**: UPPER_SNAKE_CASE (e.g., `MAX_TOKENS`)
- **Files**: Match class name (e.g., `CustomerSupportAgent.php`)

### Best Practices
- Keep methods small and focused (< 20 lines preferred)
- Use type hints and return types
- Document complex logic with comments
- Prefer composition over inheritance
- Use dependency injection
- Avoid magic numbers, use constants

## 🧪 Testing

### Writing Tests
```php
<?php

use Vizra\VizraADK\Agents\ExampleAgent;

it('processes input correctly', function () {
    $agent = new ExampleAgent();
    
    $result = $agent->process(['input' => 'test']);
    
    expect($result)->toBeArray()
        ->toHaveKey('output');
});

test('agent handles errors gracefully', function () {
    // Test error handling
});
```

### Test Requirements
- All new features must have tests
- Bug fixes should include a regression test
- Maintain or improve code coverage
- Tests should be deterministic
- Mock external services

### Running Tests
```bash
# Full test suite
composer test

# With coverage
composer test:coverage

# Parallel execution
./vendor/bin/pest --parallel

# Watch mode (if available)
./vendor/bin/pest --watch
```

## 🔄 Pull Request Process

### Before Submitting
- [ ] Tests pass locally
- [ ] Code follows coding standards
- [ ] Documentation is updated
- [ ] Commit messages are clear
- [ ] Branch is up-to-date with develop

### PR Title Format
Use conventional commit format:
- `feat: add OpenRouter provider support`
- `fix: memory leak in vector storage`
- `docs: update installation guide`
- `test: add integration tests for workflows`

### PR Description Template
```markdown
## Description
Brief description of changes

## Type of Change
- [ ] Bug fix
- [ ] New feature
- [ ] Breaking change
- [ ] Documentation update

## Testing
- [ ] Tests pass locally
- [ ] Added new tests
- [ ] Tested manually

## Checklist
- [ ] Code follows style guidelines
- [ ] Self-review completed
- [ ]  Documentation updated
- [ ] No breaking changes (or documented)
```

### Review Process
1. Automated tests run via GitHub Actions
2. Code review by maintainers
3. Discussion and refinement
4. Approval and merge to develop
5. Will be included in next release

## 🐛 Reporting Bugs

### Before Reporting
- Check existing issues
- Ensure you're on the latest version
- Try to reproduce in a clean environment

### Bug Report Template
```markdown
**Describe the bug**
Clear description of the issue

**To Reproduce**
1. Step one
2. Step two
3. See error

**Expected behavior**
What should happen

**Environment**
- Vizra ADK version: 
- Laravel version:
- PHP version:
- OS:

**Code Example**
```php
// Minimal code to reproduce
```

**Error Messages**
```
Full error message or stack trace
```

**Additional context**
Any other relevant information
```

## 💡 Suggesting Features

### Feature Request Template
```markdown
**Problem**
What problem does this solve?

**Proposed Solution**
How would this work?

**Alternatives**
Other approaches considered

**Additional Context**
Examples, mockups, or references
```

### Good Feature Requests
- Solve a common problem
- Align with project goals
- Include use cases
- Consider backwards compatibility

## 📚 Documentation

### Documentation Lives In
- `README.md` - Project overview and quick start
- `CLAUDE.md` - Technical reference for Claude Code
- `docs/` - Detailed documentation (if applicable)
- Code comments - Implementation details
- Examples folder - Working examples

### Writing Documentation
- Use clear, simple language
- Include code examples
- Explain the "why" not just "how"
- Keep it up-to-date with code changes
- Test all examples

### Documentation PRs
Documentation improvements are always welcome! Even small fixes like typos help.

## 🎯 Areas Needing Contributions

### Current Priorities
- 🧪 Test coverage improvements
- 📝 Documentation examples
- 🔧 Bug fixes from issues
- 🌍 Additional LLM provider integrations
- ⚡ Performance optimizations
- 🛠️ New tool implementations

### Good First Issues
Look for issues labeled `good-first-issue` on GitHub.

## 💬 Getting Help

### Resources
- [GitHub Issues](https://github.com/vizra-ai/vizra-adk/issues)
- [Discord Community](https://discord.gg/vizra) (when available)
- [Documentation](https://vizra.ai/docs) (when available)

### Questions?
- Check existing issues and discussions
- Ask in Discord
- Create a discussion on GitHub

## 🏆 Recognition

### Contributors
We value all contributions! Contributors are:
- Listed in release notes
- Added to CONTRIBUTORS.md (when created)
- Thanked in our Discord community

### Types of Recognition
- 🐛 Bug Hunter - Finding and reporting bugs
- 🔧 Code Contributor - Submitting PRs
- 📝 Documentation Hero - Improving docs
- 💡 Idea Generator - Suggesting features
- 🧪 Test Warrior - Writing tests

## 📝 License

By contributing, you agree that your contributions will be licensed under the MIT License.

## 🔄 Updating This Guide

This guide is a living document. If you find something confusing or have suggestions for improvement, please submit a PR!

---

**Thank you for contributing to Vizra ADK! Your efforts help make AI agent development more accessible to the Laravel community. 🚀**

*Last updated: 2025-08-28*