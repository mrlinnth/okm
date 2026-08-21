# Project Constraints
Generated: 2026-08-21
Confirmed by developer: yes

## Project
Rewrite of Outline Key Manager as a CodeIgniter 4 monolith, using Cockpit CMS as the only datastore (no local database). Built on the `ci4-cockpit-starter` template.

## Stack & Versions

| Package / Framework | Version | Notes |
|---|---|---|
| PHP | 8.5 | current stable, GA Nov 2025 |
| CodeIgniter 4 | 4.7.4 | current stable |
| BladeOne | 4.19.1 | Blade syntax templating, no Laravel dependency |
| Tailwind CSS | 4.1.18 | current stable |
| daisyUI | 5.7.x | current major, Tailwind v4 compatible |
| Alpine.js | 3.16.x | current stable |
| htmx | 2.0.10 | current stable — htmx 4 is still in beta, not used |
| Cockpit CMS | v2 | existing instance at `cms.hiyan.xyz`, Content API |

## Language Standards

- **PHP**: 8.5, `strict_types` declared in every file. Nullable types written explicitly (`?Type`), avoid `mixed` except at API response boundaries where Cockpit/Outline payload shape isn't fixed.
- **CSS**: Tailwind v4 utility classes plus daisyUI components. Mobile-first breakpoints — design for small screens first, expand up.

## Coding Conventions

- Controllers: thin — validation and delegation only, no business logic
- Business logic: service classes for the Cockpit API client and the Outline API client (ported from the current app's SSRF-safe implementation: DNS resolution before connect, blocked-range checks, IP pinning, HTTPS-only)
- No ORM — there is no local database; Cockpit is accessed directly over HTTP
- Abstractions: only when there is clear justification — no speculative abstraction
- Naming: CodeIgniter 4 standard conventions
- File structure: CodeIgniter 4 defaults, following the `ci4-cockpit-starter` layout (Blade views, `Services::cockpit()` pattern)

## Verification

Command: `vendor/bin/phpunit`
Expected: all tests pass. CI4's default test runner, already configured in the starter's `phpunit.xml.dist`.

If a plan file specifies its own verification, use that instead for those tasks.

## Explicit Exclusions

- No client-side framework (React, Vue, etc.) — interactivity is Alpine.js and htmx on top of server-rendered Blade views
- No QR code library — replaced by copy-link buttons throughout
- No recipient secret/password mechanism
- No user account system
- No TLS certificate pinning for Outline connections

## Plan File Format

Plan files live in `ai/plans/<feature-name>/`, sorted by filename
(e.g. `01-setup.md`, `02-auth.md`). Each feature gets its own subdirectory.

Tasks use this heading format:
### Task [N.N]: [Title]

When a task is completed, append ` [DONE]` to the heading:
### Task [N.N]: [Title] [DONE]
