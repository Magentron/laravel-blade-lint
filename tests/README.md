# Laravel Blade Lint - Test Suite

This directory contains comprehensive PHPUnit tests for the Laravel Blade Lint package.

## Running Tests

### Install Dependencies
```bash
composer install
```

### Run All Tests
```bash
./vendor/bin/phpunit
```

### Run with Coverage
```bash
./vendor/bin/phpunit --coverage-html build/coverage
```

### Run Specific Test Suite
```bash
./vendor/bin/phpunit tests/Providers
./vendor/bin/phpunit tests/Console
```

### Run Specific Test
```bash
./vendor/bin/phpunit --filter test_handle_with_no_errors
```

## Test Structure

- `TestCase.php` - Base test class using Orchestra Testbench
- `Providers/ServiceProviderTest.php` - Service provider registration tests
- `Console/Commands/BladeLintTest.php` - Comprehensive command tests
- `fixtures/views/` - Temporary test blade files (gitignored)

## Coverage Goal

The test suite aims for 100% line coverage of all code in `./src`.

## Requirements

- PHP 7.4+
- Laravel 6.x - 11.x (via Orchestra Testbench)
- PHPUnit 9.x - 11.x
- Mockery 1.6+

### Optional Extensions
- `pcntl` - For multi-process tests
- `posix` - For signal handling tests

Tests will automatically skip when optional extensions are not available.

## Notes

- Test fixtures are automatically created and cleaned up
- Some tests use reflection to access protected methods
- Extensive mocking ensures tests don't depend on external state
