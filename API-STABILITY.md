# API Stability

This document defines what is public and supported in `middag-io/wordpress`, and
how the public surface may evolve, so consumers — the WordPress plugins built on top of the adapter, and any
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
| `@api` | **Public, supported surface.** Consumer plugins may implement, extend, type-hint, instantiate and catch these. Changes follow [Versioning](#versioning). |
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

**Composition-root concretes are public too.** This adapter ships no container
builder: the consumer's composition root binds the framework contracts itself, so
it *has* to name the host-bridge concretes it registers or instantiates at boot.
Those are `@api` — `Database\WpdbConnectionAdapter`, `Database\WpdbSqlDialect`,
`Config\WpConfigResolver`, `Bus\WpUserContext`,
`Persistence\Query\WpdbConditionCompiler` — alongside the host-context,
event-bridge and component-name seams. Type-hint the **framework** contract in
your own code: the concrete is public because you must *name* it when wiring, not
because you should depend on its shape.

The concretes a consumer never names stay `@internal` wiring:
`Translation\WpTranslator`, `Runtime\WpMaintenanceGate` and
`Runtime\WpBootstrap`.

## Versioning

The support rules of the `middag-php-*` version family (lifecycle, what a
patch, minor and major may carry, the supported surface and the compatibility
matrix) are on
[Versioning and compatibility](https://docs.middag.dev/libraries/reference/versioning-and-compatibility).
The platform and the CI jobs, the family-window check included, are on
[Platform](https://docs.middag.dev/libraries/reference/platform).

What applies in this repository:

- Releases are cut only by
  [release-please](https://github.com/googleapis/release-please) from
  [Conventional Commits](https://www.conventionalcommits.org/). There are no
  manual tags.
- **On `1.x`:** `fix:` cuts a patch and `feat:` cuts a minor. A break of the
  `@api` surface ships as `feat:`, never as `fix:`, `refactor:` or `chore:`,
  and never with `!` or a `BREAKING CHANGE:` footer (release-please would cut
  `2.0.0` in this lib alone, and the family-window check refuses both). The
  1.x line is pre-stable and unsupported, and the move to `2.0.0` is a clean
  cut.
- Every pull request runs the family-window check
  `php-version-family-check / check` (`.github/workflows/family-window.yml`).

## The contracts this adapter fulfils

The adapter does not define the host-bridge contracts — it implements the ones
declared (and frozen) in `middag-io/framework`. Depend on the **framework**
`@api` interface, not on the WordPress concrete:

| Framework contract (`@api` in framework) | WordPress implementation | Stability here |
|---|---|---|
| `ConnectionAdapterInterface` | `Middag\WordPress\Database\WpdbConnectionAdapter` | `@api` |
| `SqlDialectInterface` | `Middag\WordPress\Database\WpdbSqlDialect` | `@api` |
| `ConfigResolverInterface` | `Middag\WordPress\Config\WpConfigResolver` | `@api` |
| `UserContextResolverInterface` | `Middag\WordPress\Bus\WpUserContext` | `@api` |
| `ConditionCompilerInterface` | `Middag\WordPress\Persistence\Query\WpdbConditionCompiler` | `@api` |
| `TranslatorInterface` | `Middag\WordPress\Translation\WpTranslator` | `@internal` |
| `MaintenanceGateInterface` | `Middag\WordPress\Runtime\WpMaintenanceGate` | `@internal` |
| `BootstrapInterface` | `Middag\WordPress\Runtime\WpBootstrap` | `@internal` |

The adapter also implements the framework's host-context, event-bridge and
component-name seams (`WpComponentContext`, `WpHookfileLoader`, …). Those are
`@api` here because a consumer instantiates and registers them at boot — the same
rule that makes the `@api` rows above public. It does not license depending on
their shape: type-hint the framework contract, name the concrete only to wire it.

## Depending on `middag-io/wordpress` safely

- Depend only on `@api` types. If you need behaviour exposed only by an
  `@internal` symbol, open an issue to have it promoted rather than reaching in.
- **On `1.x`:** read the CHANGELOG before crossing a minor, because a `1.x`
  minor may carry a break (see [Versioning](#versioning)). Pin a tilde range
  (for example `~1.15.0`) to receive only patches.
- **From `2.0.0`:** declare one `^2.N` range, with the same `N`, for this
  adapter and every other `middag-php-*` library you depend on.
- The dependency direction only points downward: the adapter depends on the OSS
  framework's published `@api` and never imports the proprietary layer.
