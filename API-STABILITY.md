# API Stability

This document defines what is public and supported in `middag-io/wordpress`, and
how the public surface may evolve during the current **`1.x`** line, so
consumers — the WordPress plugins built on top of the adapter, and any
proprietary layer above it — can depend on it without guessing.

`middag-io/wordpress` is a **host adapter**: it implements the host-bridge
contracts defined by `middag-io/framework` against WordPress' native APIs
(`$wpdb`, gettext, the options/user/post stores, WP-Cron, the REST API), so
domain code written against the framework contracts runs unchanged on WordPress.
It is Apache-2.0 OSS and imports no proprietary MIDDAG code.

## Stability levels

Every type carries a class-level annotation that states its stability:

| Annotation | Meaning |
|---|---|
| `@api` | **Public, supported surface.** Consumer plugins may implement, extend, type-hint, instantiate and catch these. Changes follow the versioning policy below. |
| `@internal` | **Implementation detail.** May change or be removed in any release, including patches. Do not depend on these from outside the package. |

If a type has neither annotation, treat it as `@internal`. Every type in `src/`
carries exactly one of the two tags.

The public surface is the set of `@api`-annotated types: the consumer-facing API
of the adapter — the REST controller base and its `Http\Contract\` interfaces,
the `Domain\Post` / `Domain\User` repositories and meta helpers, the
`Domain\Taxonomy`, `Domain\Media`, `Domain\Comment`, and optional
`Domain\WooCommerce` value objects, the adapter-specific `Exception\*` types,
the `Persistence` query builder, the `Settings` and `Definition` builders, the
`Support` façade helpers, the mail helpers, the cron registrar/handler, the hook
registrar and `Hook\Contract\HookInterface`, and the plugin-lifecycle registrar.
The concrete implementations of the framework's host-bridge contracts (the
`$wpdb` connection adapter and SQL dialect, the translator, config resolver,
user-context resolver, maintenance gate and platform bootstrap) are `@internal`
wiring: consumers depend on the **framework** contract these fulfil, and the DI
container binds the WordPress implementation — the concrete adapter is not part
of the supported surface.

## How releases are cut

Releases are cut **exclusively** by
[release-please](https://github.com/googleapis/release-please) from
[Conventional Commits](https://www.conventionalcommits.org/). There are no
manual tags: the version is derived from the commit type (`fix:` → patch,
`feat:` → minor), or set deliberately by a maintainer with a `Release-As:`
footer.

## The `1.x` policy

This mirrors the family-wide policy defined in the framework's
[`API-STABILITY.md`](https://github.com/middag-io/middag-php-framework/blob/main/API-STABILITY.md).
During the `1.x` line the API is **still consolidating**:

- **Patch** (`1.y.Z`) — bug fixes and `@internal`-only changes. Never a breaking
  `@api` change.
- **Minor** (`1.Y.0`) — additive `@api` changes (new helpers, new optional
  parameters, promoting an `@internal` symbol to `@api`). A minor **may also
  carry a breaking `@api` change** while the API consolidates. Every breaking
  change is explicitly marked in the history (`feat!` / a `BREAKING CHANGE:`
  footer) and listed in the CHANGELOG's **⚠ BREAKING CHANGES** section. Such
  releases are cut deliberately by a maintainer with a `Release-As:` footer —
  never as an accidental side effect of merging.

Full strict-semver rigor — breaking changes **only** in major releases — starts
at `2.0`. A major is never cut automatically: only by explicit maintainer
decision, when the break genuinely impacts Composer consumers.

> Historical note: `1.1.1` shipped the audit-consolidation breaking changes (the
> `WpBootstrap` / `WpHookfileLoader` renames and the removal of the dead
> `Lifecycle\Container`) as a patch by explicit maintainer decision, closing the
> OSS audit before external consumers existed. From this document on, a breaking
> `@api` change never lands in a patch.

## The contracts this adapter fulfils

The adapter does not define the host-bridge contracts — it implements the ones
declared (and frozen) in `middag-io/framework`. Depend on the **framework**
`@api` interface, not on the WordPress concrete:

| Framework contract (`@api` in framework) | WordPress implementation (`@internal` here) |
|---|---|
| `ConnectionAdapterInterface` | `Middag\WordPress\Database\WpdbConnectionAdapter` |
| `SqlDialectInterface` | `Middag\WordPress\Database\WpdbSqlDialect` |
| `TranslatorInterface` | `Middag\WordPress\Translation\WpTranslator` |
| `ConfigResolverInterface` | `Middag\WordPress\Config\WpConfigResolver` |
| `UserContextResolverInterface` | `Middag\WordPress\Bus\WpUserContext` |
| `MaintenanceGateInterface` | `Middag\WordPress\Kernel\WpMaintenanceGate` |
| `BootstrapInterface` | `Middag\WordPress\Kernel\WpBootstrap` |

The adapter also implements the framework's host-context, event-bridge and
component-name seams (`WpComponentContext`, `WpHookfileLoader`, …). Those are
`@api` here because a consumer instantiates and registers them at boot.

## Depending on `middag-io/wordpress` safely

- Depend only on `@api` types. If you need behaviour exposed only by an
  `@internal` symbol, open an issue to have it promoted rather than reaching in.
- **Default:** pin a caret range (`^1.0`) and read the CHANGELOG's **⚠ BREAKING
  CHANGES** section before crossing a minor.
- **Zero-surprise upgrades:** pin a tilde patch range (for example `~1.1.1`) to
  receive only patches.
- The dependency direction only points downward: the adapter depends on the OSS
  framework's published `@api` and never imports the proprietary layer.
