# Coding Standard

By default, this project uses **WordPress Coding Standards (WPCS)** for PHP code style and linting, with additional tools and specific exceptions described below.

The purpose of this document is to tell humans and AI tools:

- What base standard to follow (WPCS + PHPCompatibility).
- Which deviations from plain WPCS are allowed.
- How to write code expected to pass this project’s `phpcs.xml` configuration.

---

## 1. Base Standards

1. **WordPress Coding Standards (WPCS)**

    - All relevant WPCS rules apply, unless explicitly relaxed or overridden in this document.

2. **PHPCompatibility / PHPCompatibilityWP**

    - The full `PHPCompatibility` ruleset is enabled.
    - The full `PHPCompatibilityWP` ruleset is enabled.

3. **Target versions**

    - PHP: `7.2` and above (configured with `testVersion = 7.2-`).
    - WordPress: minimum supported version `5.3`.

All generated PHP code must be compatible with these versions.

---

## 2. Scope of Analysis

PHPCS is configured to scan **PHP files only** and to ignore certain directories.

- Included:
    - All `*.php` files under the project, **except** the excluded paths below.

- Excluded directories (not scanned by PHPCS and not part of the enforced coding standard):
    - `.codeception/`
    - `.github/`
    - `.php-scoper/vendor/`
    - `.wordpress-org/`
    - `.yarn/`
    - `assets/`
    - `coverage/`
    - `languages/`
    - `node_modules/`
    - `src/js/`
    - `vendor/`
    - `vendors/`

When generating PHP code, assume that files under these excluded directories are **not** validated by PHPCS and are outside the primary style contract of this project.

---

## 3. PHPCompatibility Requirements

The `PHPCompatibility` and `PHPCompatibilityWP` rulesets enforce that:

- Features unavailable before **PHP 7.2** must not be used (e.g. typed properties, union types, etc.).
- Deprecated/removed functions, language constructs, and behaviors for the configured PHP/WordPress versions should be avoided.
- Code should be compatible with WordPress **5.3+**.

**AI behavior:**

- Generate PHP code that is valid on PHP 7.2.
- Avoid relying on newer language features or APIs that would trigger PHPCompatibility or PHPCompatibilityWP warnings.

---

## 4. Deviations from Plain WPCS

The following deviations and relaxations are applied on top of standard WPCS. These are important when generating or reviewing code.

### 4.1. Unused Function Parameters

Disabled sniffs:

- `Generic.CodeAnalysis.UnusedFunctionParameter.Found`
- `Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed`

**Allowed:**

- Leaving unused parameters in function/method signatures is allowed.
- This is especially useful for:
    - WordPress hooks and filters,
    - Interface implementations,
    - Callbacks where the signature is fixed by external code.

**AI guidance:**

- Do not try to "optimize away" parameters from signatures just because they are unused.
- Do not add artificial usage such as `$unused = $param;` solely to satisfy PHPCS.

---

### 4.2. Short Array Syntax

Disabled sniff:

- `Universal.Arrays.DisallowShortArraySyntax.Found`

**Allowed:**

- Short array syntax `[]` is allowed and preferred.

**Example:**

```php
$data = [];
```

---

### 4.3. Short Ternary Operator

Disabled sniff:

- `Universal.Operators.DisallowShortTernary.Found`

**Allowed:**

- The short ternary operator `?:` is allowed.

**Example:**

```php
$value = $maybe_value ?: 'default';
```

**AI guidance:**

- Use short ternary where it improves clarity.
- Avoid deeply nested or overly complex ternaries; readability is more important than compactness.

---

### 4.4. File Naming Rules (Relaxed in Favor of PSR-4)

Disabled sniffs:

- `WordPress.Files.FileName.InvalidClassFileName`
- `WordPress.Files.FileName.NotHyphenatedLowercase`

**Allowed:**

- File names may follow **PSR-4-style naming** (e.g. `Main.php`, `General.php`, `SomeServiceProvider.php`) rather than strict WPCS conventions like `class-plugin-name.php` or `plugin-name-admin.php`.
- This project favors a PSR-4-like organization for class files.

**AI guidance:**

- When generating new class files, choose file names consistent with PSR-4 and the existing project structure.
- Do not rename files or enforce WPCS file naming rules against PSR-4 usage in this codebase.

---

### 4.5. Deprecated Sniffs

Disabled sniff:

- `Generic.Functions.CallTimePassByReference`

The sniff itself is deprecated, but style-wise:

- **Call-time pass-by-reference** (e.g. `some_function( &$var );`) **must still be avoided**, regardless of the disabled sniff.

**AI guidance:**

- Do not use `&$var` at call sites. Use references only where appropriate in function signatures and following modern PHP best practices.

---

## 5. Code Complexity Rules

Enabled rules:

- `Generic.Metrics.CyclomaticComplexity`
- `Generic.Metrics.NestingLevel`

These rules ensure that:

- Functions and methods do not become excessively complex (too many decision points).
- Nesting depth (`if`, `foreach`, `for`, `while`, `switch`, `try/catch`, etc.) remains under control.

**AI guidance:**

- Keep functions/methods reasonably small and focused.
- If a function starts to accumulate:
    - Many `if/elseif/else` branches,
    - Nested loops,
    - Nested conditionals inside conditionals,

  then refactor by extracting parts into separate private methods.

**Example of preferred structure:**

```php
class Example {

	/**
	 * Handle value.
	 *
	 * @param mixed $value Value to handle.
	 *
	 * @return void
	 */
	public function handle( $value ) {
		if ( $this->should_skip( $value ) ) {
			return;
		}

		if ( $this->is_special( $value ) ) {
			$this->handle_special( $value );

			return;
		}

		$this->handle_default( $value );
	}

	/**
	 * Determine whether processing should be skipped.
	 *
	 * @param mixed $value Value to check.
	 *
	 * @return bool
	 */
	private function should_skip( $value ) {
		// ...
	}

	/**
	 * Determine whether the value should be handled as special.
	 *
	 * @param mixed $value Value to check.
	 *
	 * @return bool
	 */
	private function is_special( $value ) {
		// ...
	}

	/**
	 * Handle special value.
	 *
	 * @param mixed $value Value to handle.
	 *
	 * @return void
	 */
	private function handle_special( $value ) {
		// ...
	}

	/**
	 * Handle default value.
	 *
	 * @param mixed $value Value to handle.
	 *
	 * @return void
	 */
	private function handle_default( $value ) {
		// ...
	}
}
```

---

## 6. General AI Guidelines for This Project

When generating PHP code for this project:

1. **Default to WPCS:**
    - Use standard WordPress Coding Standards for:
        - Indentation (tabs, not spaces),
        - Spacing around operators,
        - Braces placement,
        - Internationalization (`__()`, `_e()`, `_x()`, etc.),
        - Escaping (`esc_html()`, `esc_attr()`, `esc_url()`, `wp_kses()`, etc.),
        - Naming and prefixing of functions, methods, and hooks,
        - Input validation and sanitization.

2. **Respect the exceptions listed above:**
    - Short array syntax `[]` is allowed and preferred.
    - Short ternary `?:` is allowed.
    - Unused parameters in function signatures are allowed (do not force artificial use).
    - File naming is PSR-4-like for class files, not strict WPCS file naming.

3. **Target environment:**
    - Code must be valid and safe on PHP 7.2+.
    - Code must be compatible with WordPress 5.3+.
    - Avoid features that would trigger PHPCompatibility or PHPCompatibilityWP warnings.

4. **Complexity:**
    - Keep cyclomatic complexity and nesting levels reasonable.
    - Prefer small, focused methods and a clear control flow.

---

## 7. When in Doubt

If a specific behavior is not explicitly described in this document, assume:

> The default applies: follow **WordPress Coding Standards (WPCS)** and **PHPCompatibility / PHPCompatibilityWP** for the configured PHP and WordPress versions.
