# Testing

This package uses [Pest PHP](https://pestphp.com/) for testing, built on top of PHPUnit.

## Running Tests

To run all tests:

```bash
composer test
```

To run tests with coverage:

```bash
composer test-coverage
```

To run specific test suites:

```bash
./vendor/bin/pest tests/Unit
./vendor/bin/pest tests/Feature
```

## Test Structure

- `tests/Unit/` - Unit tests for individual classes and methods
- `tests/Feature/` - Integration tests that test multiple components working together
- `tests/TestCase.php` - Base test case with Laravel package testing setup
- `tests/Pest.php` - Pest configuration and global test helpers

## Writing Tests

### Unit Tests

Place unit tests in `tests/Unit/`. These should test individual classes in isolation:

```php
<?php

it('can do something', function () {
    $service = new SomeService();

    expect($service->doSomething())->toBe('expected result');
});
```

### Feature Tests

Place feature tests in `tests/Feature/`. These test the integration of multiple components:

```php
<?php

it('can perform end-to-end operation', function () {
    // Test that uses facades, multiple services, etc.
    $result = Agent::build(SomeAgent::class)->run('test input');

    expect($result)->toBeArray();
});
```

## Test Environment

The test environment is configured to:

- Use SQLite in-memory database
- Load your package's service provider
- Register package aliases
- Use array drivers for cache, sessions, etc.

## Coverage

Coverage reports are generated in the `build/coverage/` directory when running with coverage enabled.
