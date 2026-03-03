# AGENTS.md

## Project Scope
The project is located in:

- `wp-content/plugins/hcaptcha-wordpress-plugin/**`

This is the primary workspace for all implementation tasks.

## File Access Policy
- Files allowed to be modified: only files inside `wp-content/plugins/hcaptcha-wordpress-plugin/**`.
- Reading/searching outside this folder is allowed only when strictly necessary for integration context
  (for example, WordPress core behavior, hooks, or third-party plugin integration points).
- Any out-of-scope search must be minimal and targeted.
- `.aiassistant/rules/*.md` may be read as rules source.

## Rules Source
For all work in the project scope, always apply all rules from:

- `.aiassistant/rules/00-CORE.md`
- `.aiassistant/rules/ESLINT.md`
- `.aiassistant/rules/PHPCS.md`

## Rule Precedence
When rules conflict, use this order:

1. Project config files in project scope (`phpcs.xml`, `.eslintrc.json`).
2. `.aiassistant/rules/PHPCS.md` and `.aiassistant/rules/ESLINT.md`.
3. `.aiassistant/rules/00-CORE.md`.
4. This `AGENTS.md`.

## Execution Notes
- Use `composer phpcs` for PHP style checks.
- Use `yarn lint` for JS lint checks.
- Keep compatibility targets from the rules (`PHP 7.4+`, `WordPress 6.0+`).
- Follow WordPress security/i18n/escaping/sanitization practices from the rules.
- Do not modify files outside `wp-content/plugins/hcaptcha-wordpress-plugin/**`.
