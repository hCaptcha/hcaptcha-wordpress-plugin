# AGENTS.md

## Project Scope
The project is the hCaptcha WordPress plugin.

Depending on how the workspace is opened, the project root can appear as either:

- `wp-content/plugins/hcaptcha-wordpress-plugin/**`
- the current working directory when `cwd` is already `.../hcaptcha-wordpress-plugin`

This is the primary workspace for all implementation tasks.

When `cwd` is the plugin root, treat paths such as `src/**`, `assets/**`, `tests/**`,
`AGENTS.md`, `composer.json`, and `package.json` as in-scope project files. Do not look
for another nested `wp-content/plugins/hcaptcha-wordpress-plugin` folder inside it.

## File Access Policy
- Files allowed to be modified: only project files inside the hCaptcha plugin root.
  If the workspace is opened from a WordPress root, this means `wp-content/plugins/hcaptcha-wordpress-plugin/**`.
  If the workspace is opened from the plugin root, this means the current working directory (`./**`).
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
- Prefer targeted checks on modified files. Run full-suite checks only when the change scope, release readiness,
  or explicit user request justifies it.
- Use `vendor/bin/phpcs --standard=phpcs.xml <modified PHP files>` for targeted PHP style checks.
- Use `yarn lint <modified JS files>` for targeted JS lint checks.
- Use `composer phpcs` and `yarn lint` for full PHP/JS checks when a full run is needed.
- Keep compatibility targets from the rules (`PHP 7.4+`, `WordPress 6.0+`).
- Follow WordPress security/i18n/escaping/sanitization practices from the rules.
- Do not modify files outside the hCaptcha plugin root.
