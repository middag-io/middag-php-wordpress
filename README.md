# middag-io/wordpress

[![License: Apache 2.0](https://img.shields.io/badge/License-Apache_2.0-blue.svg)](https://opensource.org/licenses/Apache-2.0)

MIDDAG WordPress adapter — persistence, three routing surfaces (REST / admin /
public rewrite), hooks, cron, Inertia frontend, and email implementations of the
[`middag-io/framework`](https://github.com/middag-io/middag-php-framework)
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

A WordPress plugin owns the composition root that wires the adapter in. The
adapter ships an abstract kernel that formalizes it: subclass
`Middag\WordPress\Runtime\Kernel`, provide the singleton storage and the
compiled container (see the class docblock for the two-method contract), and
call `YourKernel::init()` on `plugins_loaded`. Boot wires `HookRegistrar` and
`CronRegistrar` (`init`, priority 5) and `RestRouteRegistrar`
(`rest_api_init`) whenever your container binds them — everything
product-specific goes in your `onBoot()` override. The kernel holds no static
state; the singleton lives on YOUR subclass, so two MIDDAG plugins in one
request never share a kernel.

Two obligations the host must still satisfy at boot:

- **Inject the component identity.** Build
  `new WpComponentContext($componentName, $assetVersion, $basePath)` once and
  bind it in your own container as the framework
  `HostComponentContextInterface`, so the services that need it (the REST,
  admin and public routing registrars, `CronRegistrar`, `InertiaAdapter`,
  `EmailSender`) receive it by injection. Do **not** register it with the
  framework's `HostContext::set()`: that slot is one per PHP process, and with
  several plugins in one request the last plugin to boot would win.
- **Register your config prefixes.** `WpConfigResolver` defaults to the neutral
  `MIDDAGLIB_` / `middaglib_` prefixes; bind it with your own pair.

See [WP-002](docs/decisions/WP-002-config-resolver-neutral-default-prefix.md)
for the decision and the
[WordPress adapter reference](https://docs.middag.dev/framework/reference/adapters/wordpress/)
for the details.

Logging needs no priming: the adapter's operational sites (cron dispatch,
email send/render) resolve their PSR-3 logger per call through
`Middag\WordPress\Support\LogSupport::resolve($container)` — an explicit
`Psr\Log\LoggerInterface` binding wins, then the framework's channel
`LoggerFactory`, then the `error_log()` fallback.

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
path repository (`../middag-php-framework`, symlinked). Per ADR-013 this path
repository is declared in the **root/consumer** `composer.json` of the monorepo
workspace — **not** in this package's `composer.json`, which carries no
`repositories` entry. Composer only reads the root package's `repositories`, so
a dependency's own path repos would be inert for consumers anyway. This is a
**development-only** convenience for editing the framework and the adapter side
by side. Published releases resolve the dependency through the normal Composer
registry — the path repository has no effect on consumers.

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

The package is on `1.x`. From `2.0.0` it shares MAJOR.MINOR with the other
`middag-php-*` libraries, as one version family: a patch carries only fixes,
and a family minor or major is decided by the product owner. A consumer then
declares one `^2.N` range for every sibling. Every PR runs the family-window
check (`php-version-family-check / check`). See
[`API-STABILITY.md`](API-STABILITY.md) for the policy and the check.

## License

Licensed under the [Apache License 2.0](LICENSE). See [`NOTICE`](NOTICE) for
attribution.
