# Laravel Agent ADK - CI/CD Documentation

This document explains the Continuous Integration and Continuous Deployment setup for the Laravel Agent ADK package.

## GitHub Actions Workflows

### Main Test Workflow (`.github/workflows/tests.yml`)

This workflow runs comprehensive tests on every push to `main` and on all pull requests targeting `main`.

#### Test Matrix
- **PHP Versions**: 8.1, 8.2, 8.3
- **Laravel Versions**: 11.*, 12.*
- **Test Framework**: Pest with Orchestra Testbench

#### Jobs

1. **Test Job**
   - Runs on Ubuntu Latest
   - Tests all PHP/Laravel combinations
   - Installs dependencies and runs Pest tests
   - Must pass for all matrix combinations

2. **Coverage Job**
   - Generates test coverage reports
   - Requires minimum 80% coverage
   - Uploads results to Codecov
   - Uses Xdebug for coverage collection

3. **Security Audit Job**
   - Checks for known security vulnerabilities
   - Uses Composer's built-in audit command
   - Scans all dependencies

4. **Code Quality Job**
   - Enforces PSR-12 coding standards
   - Runs PHPStan static analysis (level 5)
   - Ensures code quality standards

## Branch Protection Rules

The `main` branch is protected with the following rules:

- ✅ Require pull request reviews (1 approval minimum)
- ✅ Require status checks to pass before merging
- ✅ Require branches to be up to date before merging
- ✅ Dismiss stale reviews when new commits are pushed
- ✅ Require conversation resolution before merging

### Required Status Checks

All of these checks must pass before a PR can be merged:
- `test (8.1, 11.*)`
- `test (8.1, 12.*)`
- `test (8.2, 11.*)`
- `test (8.2, 12.*)`
- `test (8.3, 11.*)`
- `test (8.3, 12.*)`
- `Coverage`
- `Security Audit`
- `Code Quality`

## Running Tests Locally

### Package Tests
```bash
cd packages/AaronLumsden/LaravelAgentADK
./vendor/bin/pest
```

### With Coverage
```bash
cd packages/AaronLumsden/LaravelAgentADK
./vendor/bin/pest --coverage --min=80
```

### Code Quality Checks
```bash
cd packages/AaronLumsden/LaravelAgentADK

# PSR-12 Code Standards
composer global require squizlabs/php_codesniffer
~/.composer/vendor/bin/phpcs --standard=PSR12 src/

# Static Analysis
composer global require phpstan/phpstan
~/.composer/vendor/bin/phpstan analyse src/ --level=5
```

## Development Workflow

1. **Create Feature Branch**
   ```bash
   git checkout -b feature/your-feature-name
   ```

2. **Make Changes & Test Locally**
   ```bash
   cd packages/AaronLumsden/LaravelAgentADK
   ./vendor/bin/pest
   ```

3. **Push Branch & Create PR**
   ```bash
   git push origin feature/your-feature-name
   ```

4. **Wait for CI Checks**
   - All tests must pass
   - Coverage must be ≥80%
   - No security vulnerabilities
   - Code quality checks must pass

5. **Review & Merge**
   - Get required approvals
   - Ensure branch is up to date
   - Merge when all checks pass

## Troubleshooting

### Test Failures
- Check the Actions tab for detailed logs
- Run tests locally to reproduce issues
- Ensure all dependencies are installed

### Coverage Issues
- Add tests for uncovered code
- Check coverage report in Actions artifacts
- Aim for meaningful tests, not just coverage

### Code Quality Issues
- Fix PSR-12 violations using PHP CS Fixer
- Address PHPStan issues by improving type hints
- Follow Laravel best practices

### Security Vulnerabilities
- Update dependencies using `composer update`
- Check Composer audit output for specific issues
- Consider alternative packages if needed

## Configuration Files

- **`.github/workflows/tests.yml`** - Main CI workflow
- **`.github/BRANCH_PROTECTION.md`** - Branch protection setup guide
- **`packages/AaronLumsden/LaravelAgentADK/phpunit.xml`** - PHPUnit configuration
- **`packages/AaronLumsden/LaravelAgentADK/tests/Pest.php`** - Pest configuration
- **`packages/AaronLumsden/LaravelAgentADK/composer.json`** - Package dependencies and scripts

## Best Practices

1. **Write Tests First**: Follow TDD principles
2. **Keep Coverage High**: Aim for >80% code coverage
3. **Follow Standards**: Use PSR-12 coding standards
4. **Security First**: Regular dependency updates
5. **Clean History**: Use conventional commits
6. **Document Changes**: Update docs with new features

This CI/CD setup ensures code quality, security, and compatibility across multiple PHP and Laravel versions while maintaining the high standards expected from a Laravel package.
