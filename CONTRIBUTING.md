# Contributing to Laravel Blade Lint

Thank you for considering contributing to Laravel Blade Lint! This document outlines the guidelines for contributing to this project.

## Getting Started

1. Fork the repository
2. Clone your fork locally
3. Install dependencies: `composer install`
4. Create a new branch for your feature or bugfix

## Coding Standards

All contributions must adhere to the project's coding standards. The following tools are used to enforce code quality:

| Tool | Purpose | Command |
|------|---------|---------|
| PHP-CS-Fixer | Code style formatting (PSR-12) | `make php-cs-fixer` |
| PHPCPD | Copy/paste detection | `make phpcpd` |
| PHPMD | Mess detection (code smells) | `make phpmd` |
| PHPStan | Static analysis (level 9) | `make phpstan` |
| Psalm | Static analysis | `make psalm` |

### Running All Checks

Before submitting a pull request, run all static analysis tools:

```bash
make static-analysis
```

Or run individual tools:

```bash
make php-cs-fixer-dry  # Check code style without changes
make php-cs-fixer      # Fix code style issues automatically
make phpcpd            # Check for copy/paste code
make phpmd             # Run mess detector
make phpstan           # Run PHPStan
make psalm             # Run Psalm
```

Use `make help` to see all available commands.

## Testing

All changes must include appropriate tests and pass the existing test suite:

```bash
make test              # Run all tests
make test-coverage     # Run tests with coverage report
make test-stop         # Run tests, stop on first failure
```

### Test Requirements

- New features must include tests
- Bug fixes should include a test that reproduces the issue
- All tests must pass before submitting a pull request
- Aim for high code coverage

## Pull Request Process

1. **Ensure all checks pass**: Run `make all` to execute tests and static analysis
2. **Update documentation**: If your change affects usage, update the README
3. **Write clear commit messages**: Describe what and why, not how
4. **One feature per PR**: Keep pull requests focused on a single change
5. **Reference issues**: Link to any related issues in your PR description

### PR Checklist

- [ ] All tests pass (`make test`)
- [ ] Static analysis passes (`make static-analysis`)
- [ ] No code duplication introduced (`make phpcpd`)
- [ ] Code follows project style (`make php-cs-fixer-dry`)
- [ ] Documentation updated if needed
- [ ] Commit messages are clear and descriptive

## Reporting Issues

When reporting issues, please include:

- PHP version
- Laravel version
- Steps to reproduce
- Expected vs actual behavior
- Relevant error messages or logs

## Development Setup

### Requirements

Development of this package requires:

- PHP 8.3 or higher
- Composer
- ext-pcntl (optional, for multi-process support)
- ext-posix (optional, for multi-process support)

Note: The package itself supports PHP 5.6+ and Laravel 5.4+ (including Laravel 12) at runtime.

### Useful Commands

```bash
make help              # Show all available make targets
make all               # Run tests and static analysis
make test-debug        # Run tests with debug output
make psalm-clear-cache # Clear Psalm cache
```

## Code of Conduct

Please be respectful and constructive in all interactions. We welcome contributors of all experience levels.

## License

By contributing, you agree that your contributions will be licensed under the GPL-3.0-or-later license.
