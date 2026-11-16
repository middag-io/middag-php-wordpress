# AGENTS.md — middag-io/wordpress

Claude Code and Codex read this file; there is no `CLAUDE.md` in this repository.

## What this package is

OSS WordPress adapter (Apache-2.0) of `middag-io/framework`: it implements the framework ports with native WordPress APIs and ships no composition root; a WordPress plugin wires it in. It builds only on the OSS MIDDAG libraries (`middag-io/framework`, `middag-io/ui`) and never imports any other MIDDAG package.

## Architecture (on docs.middag.dev)

The architecture lives on the hub, not in this repository. The code is the source of truth for file lists.

- Scope, requirements, port table, Support layer, cron and jobs, WooCommerce, `$wpdb` and the surfaces out of scope: [Reference — WordPress Adapter](https://docs.middag.dev/framework/reference/adapters/wordpress/).
- REST, admin and public rewrite surfaces, the `#[Auth]` capability rule and the authentication seam: [Routing Surfaces](https://docs.middag.dev/framework/reference/adapters/wordpress/routing-surfaces).
- Page-controller and REST bases: [Controller Bases](https://docs.middag.dev/framework/reference/adapters/wordpress/controller-bases).
- Consumer kernel, component identity, config prefixes and neutral defaults: [Configuration & Identity](https://docs.middag.dev/framework/reference/adapters/wordpress/configuration).
- Platform, CI jobs and guards of the library family: [Platform](https://docs.middag.dev/libraries/reference/platform) and [Guards](https://docs.middag.dev/libraries/reference/guards).
- HTTP client mTLS: the `Http\Client\HttpClient` class docblock.

## Boundary enforcement

- `tests/AdapterPluginIsolationTest.php` — every `Middag\` namespace named in `src/` is one of the OSS namespaces `Middag\WordPress`, `Middag\Framework` or `Middag\Ui` (an allowlist: no other package is named in this repository). Runs with `composer test`.
- `tests/ClassLoadabilityTest.php` — autoload smoke test over the whole `src/` tree.
- `tests/Integration/` — integration suite on a real WordPress of each supported major and a real MySQL database (`composer test:integration`, own `phpunit.integration.xml` and bootstrap, never `tests/stubs`). The unit suite excludes it. Setup in [`tests/Integration/README.md`](tests/Integration/README.md).

### Architecture guards (`tests/Architecture/`)

Enforced policies (all run under `composer test`):

- **No static mutable state** (`NoStaticMutableStateTest`) — no `src/` class declares a static property. PHP has no readonly static, so a static property is process-wide state that collides between two plugins in one request. Per-request state is an injected instance dependency. Constants and static methods are fine.
- **No bundled token auth** (`NoBundledTokenAuthTest`) — no `src/` file references `Firebase\JWT`. App-token/JWT logic (issue/verify/refresh) is consumer code; the library exposes only the `RequestAuthenticatorInterface` seam (default `WpSessionAuthenticator`, WP-session only).
- **No unused runtime dependency** (`NoUnusedRuntimeDependencyTest`) — every package in `require` is used by `src/` (one of its PSR-4 namespaces appears there). A package only the tests need goes to `require-dev`.
- **No product-named default** (`NoProductNamedDefaultTest`) — no string literal, comment or docblock under `src/` names the product in lowercase. Neutral defaults use the `middaglib` marker; examples use `acme`.
- **No routing brand literal** (`RoutingBrandLiteralTest`) — routing sources never hard-code the lowercase `middag` literal; namespaces/slugs derive from `componentName()`.
- **Enum case PascalCase** (`EnumCasePascalCaseTest`).

## Decision records

Architecture decisions live in [`docs/decisions/`](docs/decisions/) as `WP-NNN` records in the
MIDDAG-MADR compact format: frontmatter, then Context → Considered Options
→ Decision → Consequences → Enforcement. A record states **why** (the choice, the alternatives, the
consequences); specification (class lists, catalogs, pipelines, naming tables) lives in the reference
its Enforcement table points to, at docs.middag.dev (`docs-middag-dev`,
`docs/framework/reference/adapters/wordpress/`). Ids are never reused. OSS records never cite
proprietary record ids. `deciders` is a role id (`leadership`), never a person.

**`domains` vocabulary** (a record uses only these): `wordpress`, `adapters`, `portability`, `http`,
`config`, `kernel`, `persistence`, `bus`.

## Versioning

Releases come only from release-please. The family rules (lifecycle, supported surface, compatibility matrix) are on [Versioning and compatibility](https://docs.middag.dev/libraries/reference/versioning-and-compatibility); the lifecycle and the family-window check of this repository are in [`API-STABILITY.md`](API-STABILITY.md#how-releases-are-cut).

- **`1.x` (now).** `fix:` cuts a patch and `feat:` cuts a minor. A break ships as `feat:` (never `fix:`). Never use `!` or `BREAKING CHANGE:`, which would cut `2.0.0` in this lib alone. The 1.x line is pre-stable and unsupported, and the move to 2.0.0 is a clean cut.
- **From `2.0.0`.** The library joins the `middag-php-*` version family: `feat:` waits for a family minor window, and `!` or `BREAKING CHANGE:` for a family major window. Consumers require every family library with the same `^2.N`.
- A type tagged `@api` is listed in `## The public surface` of [`API-STABILITY.md`](API-STABILITY.md#the-public-surface); a new, promoted or demoted `@api` type updates that list in the same change.
- **Do not** change `release-please-config.json` versioning or the sibling ranges in `composer.json` outside the coordinated family release.

## Composer scripts

- `composer check` — style → rector → stan (all analysis/dry-run)
- `composer check:style` — PHP CS Fixer dry-run
- `composer check:rector` — Rector dry-run
- `composer check:stan` — PHPStan
- `composer fix` — auto-fix style + rector
- `composer fix:all` — style → rector → style (re-runs style after rector)
- `composer test` — PHPUnit (includes the boundary and loadability guard tests)
- `composer test:cov` — PHPUnit with coverage (`XDEBUG_MODE=coverage`, text report)
- `composer lint:php83` — PHP 8.3 parse-level lint (`bin/lint-php83.sh`)
- `post-install-cmd` / `post-update-cmd` — register `.githooks` (`commit-msg` enforces Conventional Commits)
