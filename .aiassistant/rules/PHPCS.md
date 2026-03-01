---
apply: always
---

# hCaptcha PHPCS Rules

This document describes the PHPCS configuration actually used in this repository and clarifies how it differs from the default WordPress Coding Standards. It is a human‑friendly mirror of `phpcs.xml` located at the project root.

## Table of Contents

1. [Overview](#overview)
2. [What is scanned](#what-is-scanned)
3. [How PHPCS runs](#how-phpcs-runs)
4. [Standards and versions](#standards-and-versions)
5. [Adjustments to WordPress rules (global)](#adjustments-to-wordpress-rules-global)
6. [Complexity rules](#complexity-rules)
7. [Path-specific relaxations](#path-specific-relaxations)
8. [Practical examples](#practical-examples)
9. [Tips](#tips)

---

## Overview

We use PHPCS to enforce:

- WordPress Coding Standards (WPCS) with a few targeted exclusions.
- PHPCompatibility/PHPCompatibilityWP to ensure compatibility with supported PHP/WordPress versions.

The canonical configuration is in `phpcs.xml`. If this document and `phpcs.xml` ever disagree, `phpcs.xml` wins.

---

## What is scanned

PHPCS is pointed at the current directory (`<file>.`), but with the following path rules from `phpcs.xml`:

- Only these top-level paths are considered; everything else is excluded by a negative lookahead rule:
  - `*/Projects/hcaptcha-wordpress-plugin/`
- Additional directories are excluded everywhere:
  - `*/.codeception/*`, `*/.githooks/*`, `*/.github/*`, `*/.php-scoper/vendor/*`, `*/.wordpress-org/*`, `*/.yarn/*`, `*/assets/*`, `*/build/*`, `*/coverage/*`, `*/languages/*`, `*/node_modules/*`, `*/vendor/*`, `*/vendors/*`.

This keeps scans fast and relevant to our PHP source.

---

## How PHPCS runs

Configuration flags used (as seen in `phpcs.xml`):

- `sp` output: show sniff codes and progress.
- `basepath=./`: shorten file paths in output.
- `extensions=php`: only PHP files are scanned.
- `parallel=12`: run up to 12 processes in parallel.

Installed standards paths are configured to include PHPCompatibility, PHPCSExtra/Utils, and WPCS from `vendor/`.

---

## Standards and versions

- PHP compatibility: `PHPCompatibility` and `PHPCompatibilityWP` are enabled with `testVersion="7.4-"`. Code must be compatible with PHP 7.4 and newer.
- WordPress Coding Standards: `WordPress` standard is enabled with `minimum_supported_wp_version=6.0`.

Unless explicitly excluded below, default WPCS rules apply.

---

## Adjustments to WordPress rules (global)

The following sniffs are disabled globally to match our conventions:

1. Unused function parameters are allowed (often needed for hooks/interfaces):
   - `Generic.CodeAnalysis.UnusedFunctionParameter.Found`
   - `Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed`
2. Short array syntax and short ternary are allowed:
   - `Universal.Arrays.DisallowShortArraySyntax.Found` (disabled)
   - `Universal.Operators.DisallowShortTernary.Found` (disabled)
   - Use `[]` and the short ternary `?:` when appropriate.
3. PSR‑4 filenames are allowed for classes (we do not enforce WPCS file naming for classes):
   - `WordPress.Files.FileName.InvalidClassFileName` (disabled)
   - `WordPress.Files.FileName.NotHyphenatedLowercase` (disabled)
4. The deprecated call-time pass-by-reference check is disabled:
   - `Generic.Functions.CallTimePassByReference` (disabled)
   - Even with this sniff disabled, avoid call-time pass-by-reference usage like `some_function( &$var )`.

Everything else from the `WordPress` ruleset remains in effect.

---

## Complexity rules

In addition to `WordPress`, PHPCS also applies these generic complexity sniffs:

- `Generic.Metrics.CyclomaticComplexity`
- `Generic.Metrics.NestingLevel`

No custom thresholds are set in `phpcs.xml`, so default sniff behavior applies.

Practical guidance:

- Keep methods small and focused to reduce branching.
- Prefer early returns to deep nesting.
- Extract private helper methods when `if/else` and loop nesting grows.

---

## Practical examples

### Short array and short ternary are OK

```php
$items = [ 'foo', 'bar' ];
$title = $maybe_title ?: 'Untitled';
```

### Unused parameter (e.g., to satisfy a hook signature)

```php
add_action( 'init', function ( $unused ) {
	// Intentionally unused parameter is acceptable.
	// Initialization logic here.
} );
```

### PSR‑4 class filenames are acceptable

Class files may use PSR‑4 naming instead of the default WPCS hyphenated lowercase convention. The WPCS filename sniffs for classes are disabled.

---

## Tips

- Default WPCS rules still apply for sanitization, escaping, i18n, Yoda conditions, etc., unless explicitly excluded in `phpcs.xml`.
- Target PHP 7.4+: avoid deprecated/removed features and prefer modern language constructs supported by 7.4.
- When changing the ruleset, update both `phpcs.xml` and this document to keep them in sync.
