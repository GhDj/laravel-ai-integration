# Contributing to Laravel AI Integration

Thank you for considering contributing to Laravel AI Integration! This document outlines the process for contributing to this project.

## Code of Conduct

Please be respectful and constructive in all interactions. We're all here to build something great together.

## How to Contribute

### Reporting Bugs

Before creating a bug report, please check existing issues to avoid duplicates. When creating a bug report, include:

- A clear, descriptive title
- Steps to reproduce the issue
- Expected behavior vs actual behavior
- PHP version, Laravel version, and package version
- Any relevant code snippets or error messages

### Suggesting Features

Feature requests are welcome! Please include:

- A clear description of the feature
- The problem it solves or use case it addresses
- Any implementation ideas you have

### Pull Requests

1. Fork the repository
2. Create a feature branch from `develop`:
   ```bash
   git checkout -b feature/your-feature-name develop
   ```
3. Make your changes following our coding standards
4. Write or update tests as needed
5. Run the test suite:
   ```bash
   composer test
   ```
6. Run code style checks:
   ```bash
   composer check-style
   ```
7. Run static analysis:
   ```bash
   composer analyse
   ```
8. Commit your changes with a clear message
9. Push to your fork and create a Pull Request to `develop`

## Development Setup

1. Clone the repository:
   ```bash
   git clone https://github.com/GhDj/laravel-ai-integration.git
   cd laravel-ai-integration
   ```

2. Install dependencies:
   ```bash
   composer install
   ```

3. Run tests:
   ```bash
   composer test
   ```

## Coding Standards

- Follow PSR-12 coding style
- Use strict types (`declare(strict_types=1)`)
- Add type hints to all parameters and return types
- Write meaningful commit messages
- Keep methods focused and concise
- Add PHPDoc blocks for public methods

### Running Code Style Fixes

```bash
# Check for style issues
composer check-style

# Automatically fix style issues
composer format
```

### Running Static Analysis

```bash
composer analyse
```

## Testing

- Write tests for new features
- Ensure existing tests pass
- Aim for meaningful test coverage
- Use descriptive test method names

```bash
# Run all tests
composer test

# Run specific test file
vendor/bin/phpunit tests/Unit/YourTest.php
```

## Git Workflow

We use a Git Flow-like workflow:

- `main` - Production-ready releases
- `develop` - Integration branch for features
- `feature/*` - Feature branches
- `fix/*` - Bug fix branches

All PRs should target the `develop` branch unless they're hotfixes.

## Adding a New Provider

To add support for a new AI provider:

1. Create a new provider class extending `AbstractProvider`
2. Implement all required interface methods
3. Create a streaming response class if the provider supports streaming
4. Create a provider-specific exception class
5. Register the provider in `AIManager::createProvider()`
6. Add configuration in `config/ai.php`
7. Write comprehensive tests
8. Update the README with usage examples

## Questions?

If you have questions, feel free to open an issue for discussion.

Thank you for contributing!
