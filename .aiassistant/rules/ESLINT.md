---
apply: always
---

# hCaptcha ESLint Rules

This document mirrors the ESLint configuration actually used in this repository and clarifies how it relates to the default WordPress rules. The canonical source of truth is `.eslintrc.json` in the project root. If this document and `.eslintrc.json` ever disagree, `.eslintrc.json` wins.

## Table of Contents
1. [Overview](#overview)
2. [Extends](#extends)
3. [Environments](#environments)
4. [Parser options](#parser-options)
5. [Ignored files](#ignored-files)
6. [Notes and tips](#notes-and-tips)
7. [Quick reference](#quick-reference)

---

## Overview

We rely on the official WordPress ESLint preset with formatting. No custom rules or additional plugins are defined in this repository beyond what the preset enables.

---

## Extends

- `plugin:@wordpress/eslint-plugin/recommended-with-formatting` — WordPress recommended rules including formatting.

There are no extra rule overrides in `.eslintrc.json` at the moment.

---

## Environments

Enabled environments in `.eslintrc.json`:

- `browser: true` — browser globals are available.
- `jest: true` — Jest testing globals are available.
- `es2017: true` — enables ES2017 globals/features.

Note: The `node` and `jquery` environments are not enabled unless explicitly added in the config.

---

## Parser options

- `sourceType: "module"` — enables ES module syntax (`import`/`export`).

No custom parser is configured; the default ESLint parser is used via the WordPress preset.

---

## Ignored files

The following patterns are ignored by ESLint (from `.eslintrc.json`):

- `assets/js/apps/*.js`
- `assets/js/*.min.js`

These paths typically contain third‑party or compiled/minified assets and should not be linted.

---

## Notes and tips

- Follow the WordPress coding style as enforced by the preset. Formatting rules from the preset are active.
- Use tabs for indentation at the beginning of the line, per project guidelines.
- Prefer concise arrow functions where appropriate.
- When you introduce new ecosystems (e.g., React, TypeScript, JSDoc rules, Node tooling), update `.eslintrc.json` first and then reflect the changes here.

---

## Quick reference

- Base: WordPress recommended with formatting; no extra custom rules.
- Environments: `browser`, `jest`, `es2017`.
- Modules: ES modules are enabled (`sourceType: module`).
- Ignore: do not lint vendor/compiled/minified JS listed above.
