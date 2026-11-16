# middag-io/wordpress

[![CI](https://github.com/middag-io/middag-php-wordpress/actions/workflows/ci.yml/badge.svg)](https://github.com/middag-io/middag-php-wordpress/actions/workflows/ci.yml) [![License: Apache 2.0](https://img.shields.io/badge/License-Apache_2.0-blue.svg)](https://opensource.org/licenses/Apache-2.0) [![PHP](https://img.shields.io/badge/php-%5E8.3-777BB4.svg)](https://www.php.net/)

MIDDAG WordPress adapter — persistence, three routing surfaces (REST / admin /
public rewrite), hooks, cron, Inertia frontend, and email implementations of the
[`middag-io/framework`](https://github.com/middag-io/middag-php-framework)
contracts for WordPress.

**Documentation:** [Reference — WordPress Adapter](https://docs.middag.dev/framework/reference/adapters/wordpress/)
on docs.middag.dev.

## What this package is

`middag-io/wordpress` is the WordPress host adapter for the MIDDAG framework. It
provides the WordPress-side implementations of the framework's adapter contracts:
`$wpdb` connection and SQL dialect, REST controllers, hooks, wp-cron, config and
user context, translation, Inertia frontend glue, `wp_mail` email, and native
post/user persistence.

It binds the framework to a WordPress site. A WordPress plugin provides the
composition root that wires it in.

### What it does not include

- No product features or governed domain capabilities (item storage, query
  engine, audit and activity feeds, payments, CRM and similar).
- No dependency on a MIDDAG package other than the OSS `middag-io/framework`
  and `middag-io/ui`. The `AdapterPluginIsolationTest` guard test (part of
  `composer test`) allows only the OSS MIDDAG namespaces in `src/`.
- No bearer-token or JWT code: a consumer that accepts tokens injects its own
  `RequestAuthenticatorInterface`.
- No bundled WordPress plugin. You wire the adapter into your own plugin.

## Requirements

| Item | Supported |
|---|---|
| PHP | `^8.3`: 8.3, 8.4 and 8.5, with `ext-json` |
| Symfony | `^7.4 \|\| ^8.1` for every Symfony component it requires. PHP 8.3 resolves Symfony 7.4; PHP 8.4 and 8.5 can resolve 8.x |
| `middag-io/framework` | `^1.13` on the `1.x` line (it brings `middag-io/ui`) |
| WordPress | 7.0 and 7.1 (the latest major and the previous one) |

CI runs the test suite on PHP 8.3, 8.4 and 8.5 at the lowest platform, a
`highest` job on PHP 8.4 and 8.5 that asserts the Symfony 8 major, PHPStan
against the WordPress 7.0 and 7.1 stubs, and the integration suite against a
real WordPress 7.0 and 7.1 (the newest patch release of each) on MySQL 8.4.
The platform rules are on
[Platform](https://docs.middag.dev/libraries/reference/platform).

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
state; the singleton lives on YOUR subclass, so two plugins built on the
adapter never share a kernel in one request.

What the host must still do at boot:

- **Inject the component identity.** Build
  `new WpComponentContext($componentName, $assetVersion, $basePath)` once and
  bind it in your own container as the framework
  `HostComponentContextInterface`, so the services that need it (the REST,
  admin and public routing registrars, `CronRegistrar`, `InertiaAdapter`,
  `EmailSender`) receive it by injection. Do **not** register it with the
  framework's `HostContext::set()`: that slot is one per PHP process, and with
  several plugins in one request the last plugin to boot would win.
- **Pass your own names.** Every adapter default uses the neutral `middaglib`
  marker, which names no product. Bind the classes with your plugin's own
  values:

  | Class | Default | Constructor argument |
  |---|---|---|
  | `WpConfigResolver` | `MIDDAGLIB_` / `middaglib_` | `$envPrefix`, `$optionPrefix` |
  | `WpHostEventBridge` | hook prefix `middaglib/` | `$hookPrefix` |
  | `WpHookfileLoader` | filter `middaglib_hookfiles`, file `middaglib_hooks.php` | `$filterName`, `$hookfileName` |
  | `WpTranslator` | text domain `middaglib` | `$defaultDomain` |
  | `CacheSupportPsr16` | `wp_cache` group `middaglib_{area}` | `$groupPrefix` |

  `$hookfileName` comes after `$logger`, so set it with a named argument:
  `new WpHookfileLoader(filterName: 'acme_hookfiles', hookfileName: 'acme_hooks.php')`.

REST routes declared with `#[Auth]` require login by default. Every capability
an `#[Auth]` lists must pass `user_can()` for the user the injected
`RequestAuthenticatorInterface` resolved (a token user included), with the
`instanceId` as the object id of a meta capability. Grant your plugin's
capabilities to the roles that need them, administrators included.

See [WP-002](docs/decisions/WP-002-config-resolver-neutral-default-prefix.md),
[Configuration & Identity](https://docs.middag.dev/framework/reference/adapters/wordpress/configuration)
and [Routing Surfaces](https://docs.middag.dev/framework/reference/adapters/wordpress/routing-surfaces)
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
composer test    # PHPUnit (includes the boundary and architecture guards)
```

The integration suite (`tests/Integration`) runs on a real WordPress and a real
MySQL database; see [`tests/Integration/README.md`](tests/Integration/README.md).

`composer.json` pins `config.platform.php` to `8.3.0`, so Composer resolves the
lowest PHP of the range locally. `composer.lock` is gitignored, like a typical
library. Git hooks are configured automatically via `post-install-cmd`; the
`commit-msg` hook enforces [Conventional Commits](https://www.conventionalcommits.org/).
See [`CONTRIBUTING.md`](CONTRIBUTING.md) for the contributor setup.

### Releases

Releases are managed by [release-please](https://github.com/googleapis/release-please).
The package is on `1.x`, which is pre-stable and unsupported; from `2.0.0` it
joins the `middag-php-*` version family. See [`API-STABILITY.md`](API-STABILITY.md)
and [Versioning and compatibility](https://docs.middag.dev/libraries/reference/versioning-and-compatibility).

## Help and status

- **Status:** the `1.x` line is pre-stable and unsupported; support starts at
  `2.0.0`. See
  [Versioning and compatibility](https://docs.middag.dev/libraries/reference/versioning-and-compatibility).
- **Documentation:** [Reference — WordPress Adapter](https://docs.middag.dev/framework/reference/adapters/wordpress/)
  on docs.middag.dev.
- **Questions and bugs:** [GitHub issues](https://github.com/middag-io/middag-php-wordpress/issues).
- **Security reports:** see [`SECURITY.md`](SECURITY.md). Do not open a public
  issue.
- **Changes:** [`CHANGELOG.md`](CHANGELOG.md).

## License

Licensed under the [Apache License 2.0](LICENSE). See [`NOTICE`](NOTICE) for
attribution.
