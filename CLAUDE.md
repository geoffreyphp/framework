# Geoffrey Framework

AI agent framework. Provides bootstrap, routing, contracts, base agent, first-party channels, conversation/identity layer, and the service provider that wires it all together. The consuming app supplies config and agent classes; the framework handles everything else.

## Tech Stack

- **Language**: PHP 8.4
- **Framework**: Laravel 13 + `laravel/ai`
- **Testing**: Pest PHP 4 (100% code coverage, 100% type coverage)
- **Linting**: Laravel Pint
- **Static Analysis**: PHPStan (level max), Rector, Peck

## Project Structure

- `src/` — Framework source (`Geoffrey\` namespace)
  - `Contracts/` — Interfaces (Channel, HasSkills)
  - `Channels/` — First-party channel implementations (Slack, etc.)
- `config/geoffrey.php` — Config schema (orchestrator, channels)
- `database/migrations/` — Conversation table migration
- `tests/` — Pest tests (`Tests\` namespace)

## Commands

```bash
# Run full test suite (lint, type-coverage, typos, unit, types, refactor)
composer test

# Run unit tests with coverage
composer test:unit

# Run tests in parallel
./vendor/bin/pest --parallel

# Lint (check / auto-fix)
composer test:lint / composer lint

# Static analysis
composer test:types

# Rector (check / apply)
composer test:refactor / composer refactor

# Typo checking
composer test:typos

# Type coverage
composer test:type-coverage
```

## Key Rules

- 100% test coverage and 100% type coverage are enforced — no exceptions
- PHPStan level max — all code must pass strict static analysis
- All source files use `declare(strict_types=1)`
- PSR-4 autoloading: `Geoffrey\` → `src/`, `Tests\` → `tests/`
- This is a Laravel package — no standalone app, uses provider auto-discovery
- The framework is channel-agnostic; orchestrator never knows about channels
- Channels are self-contained: each bundles routes, controller, API client, verification
- Driver/Manager pattern for channel resolution from config
- Program to contracts/interfaces — `Channel`, `HasSkills`

## Detailed Configuration

Project configuration files are in `.claude/`:
- `architecture.md` — Technical patterns and structure
- `testing.md` — Test configuration and commands
- `code-standards.md` — Coding conventions
- `pipeline.md` — Workflow agent configuration
