---
apply: always
---

# Core Rules

## Rule precedence
Apply rules in this order:
1. Project config files: `phpcs.xml`, `.eslintrc.json`.
2. Specialized docs: `.aiassistant/rules/PHPCS.md`, `.aiassistant/rules/ESLINT.md`.
3. This file (`.aiassistant/rules/00-CORE.md`).

If rules conflict, higher-priority sources win.

## General principles
- Follow existing project architecture and naming conventions.
- Prefer modular, reusable code and avoid duplication.
- Use descriptive names for classes, methods, variables, hooks, and files.
- Keep comments concise, in English, and end sentences with punctuation.

## PHP and WordPress
- Use WordPress core APIs where applicable.
- Validate, sanitize, and escape all external input/output.
- Verify nonces for state-changing requests.
- Use `$wpdb->prepare()` for dynamic SQL.
- Use `WP_Query`/core query APIs where they fit better than raw SQL.
- Use hooks (actions/filters) for extensibility.
- Apply i18n and proper escaping for user-facing strings.

## JavaScript and TypeScript
- Follow `.eslintrc.json` and `.aiassistant/rules/ESLINT.md`.
- Respect configured ignore patterns and environments.
- Prefer concise arrow functions when readability improves.

## Target compatibility
- PHP: 7.4+.
- WordPress: 6.0+.

## Build, lint, and test
- Dependency/build tooling: `composer` (PHP) and `yarn` (JS).
- PHP code style check: `composer phpcs`.
- JS lint: `yarn lint`.
- PHP tests:
  - Unit: `composer unit`.
  - Integration: `composer integration`.
- JS tests: `yarn test`.

## Verification environment
- Use the local test site `https://test.test` for manual verification and browser-based debugging.
- Do not store local URLs, credentials, or environment secrets in repository-tracked rules files.

## Operational notes
- Keep the indentation style consistent with project linting rules.
- Prefer small, focused functions/methods with a clear control flow.
