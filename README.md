# middag-io/wordpress

[![License: Apache 2.0](https://img.shields.io/badge/License-Apache_2.0-blue.svg)](https://opensource.org/licenses/Apache-2.0)

MIDDAG WordPress adapter — persistence, REST, hooks, cron, Inertia frontend, and
email implementations of the [`middag-io/framework`](https://github.com/middag-io/middag-php-framework)
contracts for WordPress.

> **License:** Apache-2.0.

## What this package is

`middag-io/wordpress` is the WordPress host adapter for the MIDDAG framework. It
provides the WordPress-side implementations of the framework's adapter contracts:
`$wpdb` connection and SQL dialect, REST controllers, hooks, wp-cron, config and
user context, translation, Inertia frontend glue, `wp_mail` email, and native
post/user persistence.

It binds the framework to a WordPress site. A WordPress plugin provides the
composition root that wires it in.

### What it does not include

- No product features or governed MIDDAG domain capabilities (Item/EAV,
  QueryEngine, Audit/ActivityFeed, payments, CRM, and similar). Those are not
  part of this adapter.
- No dependency on any non-OSS MIDDAG package — the adapter builds only on the
  OSS framework and the host platform. Importing any non-OSS MIDDAG namespace or
  package is forbidden and enforced by the `AdapterPluginIsolationTest` guard
  test (part of `composer test`).
- No bundled WordPress plugin. You wire the adapter into your own plugin.

## Requirements

- PHP `^8.2` (tested on 8.2, 8.3, 8.4)
- `ext-json`
- A WordPress site (the adapter targets WordPress runtime APIs)

## Installation

```bash
composer require middag-io/wordpress
```

This pulls `middag-io/framework` automatically, which in turn pulls
[`middag-io/ui`](https://github.com/middag-io/middag-php-ui) — the framework's
frontend toolkit that backs this adapter's Inertia frontend glue.

## Host integration

A WordPress plugin owns the composition root that wires the adapter in. Two
obligations the host must satisfy at boot:

- **Register the host context.** Call
  `HostContext::set(new WpComponentContext($componentName, $assetVersion, $basePath))`
  so the adapter can resolve the component name, asset version, and base path
  (used for Inertia cache-busting and email-template path resolution).
- **Prime the logger.** Call
  `Middag\WordPress\Support\LogSupport::primeFromContainer($container)` once the
  DI container is built. The framework registers a channel-based `LoggerFactory`
  but no shared `Psr\Log\LoggerInterface`, so this wires the adapter's
  operational error sites (cron dispatch, email send/render) to the framework
  PSR-3 logger. Without priming, those sites fall back to PHP's `error_log()`.

## Development

```bash
git clone https://github.com/middag-io/middag-php-wordpress
cd middag-php-wordpress
composer install
```

Run the quality gates and the test suite:

```bash
composer check   # PHP-CS-Fixer + Rector (dry-run) + PHPStan
composer test    # PHPUnit (includes the boundary guard test)
```

Git hooks are configured automatically via `post-install-cmd`. The `commit-msg`
hook enforces [Conventional Commits](https://www.conventionalcommits.org/).

### Working against a sibling framework checkout

During development the adapter can resolve `middag-io/framework` from a sibling
path repository (`../middag-php-framework`, symlinked) declared in
`composer.json`. This is a **development-only** convenience for editing the
framework and the adapter side by side. Published releases resolve the
dependency through the normal Composer registry — the path repository has no
effect on consumers.

### `composer.lock` is gitignored

Like a typical library, this repo does not commit `composer.lock`; consumers pin
versions in their own application. Because the development setup may use a path
repository for the framework, a **local** `composer.lock` can show path or dev
references. That is expected local development state and **not** a defect in the
released package.

See [`CONTRIBUTING.md`](CONTRIBUTING.md) for the full contributor setup,
including the dependency-resolution notes.

### Commit format

```
type(scope): description

Types: feat, fix, chore, docs, style, refactor, perf, test, build, ci, revert
```

### Releases

Releases are managed by [release-please](https://github.com/googleapis/release-please).
Conventional commits merged to `main` open a Release PR automatically.

## License

Licensed under the [Apache License 2.0](LICENSE). See [`NOTICE`](NOTICE) for
attribution.
