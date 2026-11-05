# API Stability

This document defines what is public and supported in `middag-io/wordpress`, and
how the public surface may evolve on the current **`1.x`** line and in the
version family from `2.0.0`, so
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

## How releases are cut

Releases are cut **exclusively** by
[release-please](https://github.com/googleapis/release-please) from
[Conventional Commits](https://www.conventionalcommits.org/). There are no
manual tags.

This adapter is one of the eight `middag-php-*` libraries (framework, core, ui,
moodle, wordpress, licensing, dev-tools, demo-standalone) that form one
**version family** from `2.0.0`. The decision is ADR-032 in `docs-middag-ops`,
which amends ADR-003 for these libraries. The scheme itself is in
`docs-middag-ops` versioning-scheme §5.

- **On `1.x`**, the version comes from the commit type: `fix:` → patch,
  `feat:` → minor.
- **From `2.0.0`**, a commit type only ever cuts a patch. A family minor or
  major is a coordinated `Release-As: X.Y.0` commit in every lib of the family,
  and the product owner decides it.

## The `1.x` policy

During the `1.x` line the API is **still consolidating**:

- **Patch** (`1.y.Z`) — bug fixes and `@internal`-only changes. Never a breaking
  `@api` change.
- **Minor** (`1.Y.0`) — additive `@api` changes (new helpers, new optional
  parameters, promoting an `@internal` symbol to `@api`). **Until 2026-11-30** a
  minor may also carry a breaking `@api` change. This is allowed only under the
  exception to `MSR-015.R1(b)` recorded in `org-middag` `adoption.yaml`, which
  ends on that date and is not renewed.
- A break on `1.x` ships as `feat:` or `fix:`, with the break and the migration
  described in the commit body, so the CHANGELOG still lists it. Never mark it
  with `!` or a `BREAKING CHANGE:` footer. release-please would cut `2.0.0` in
  this lib alone, and the family-window check (below) refuses both markers.
- **From 2026-12-01 no break lands on `1.x`.** The family is planned to ship
  `2.0.0` before that date. If the cut slips, `1.x` takes only non-breaking
  changes until the cut.
- There is no per-lib major. This adapter reaches `2.0.0` together with the
  rest of the family, through a coordinated `Release-As: 2.0.0`, when the product
  owner decides the cut.

## From `2.0.0`: the version family

- **Numbers.** Every lib of the family shares MAJOR.MINOR; PATCH is free per
  lib. A family minor or major goes out in every lib, changed or not.
- **Patch** (`2.y.Z`) — fixes only. release-please cuts only patches.
- **Minor** (`2.Y.0`) — a family minor, which never breaks. New features wait
  for it. A removal is first deprecated in a minor, with a runtime notice.
- **Major** (`X.0.0`) — a family major, the only release that breaks. It removes
  what an earlier minor deprecated. Each family major publishes a compatibility
  matrix (Symfony, PHP, Moodle, WordPress).
- **Who decides.** The product owner decides every family major. They also
  decide every family minor, which goes out when a new Moodle version needs
  support, a MIDDAG release needs a feature, a deprecation or a new range, or a
  new Symfony minor needs absorbing.
- **Previous major.** The last minor of a family major gets security fixes
  only, for 6 months after the next major ships. The last `1.x` minor gets them
  for 6 months after `2.0.0`.

## The family-window check

Every pull request runs the check `php-version-family-check / check`
(`.github/workflows/family-window.yml`). It reads the PR title and every
non-merge commit of the PR: the header type, `!`, and any
`BREAKING CHANGE:` / `BREAKING-CHANGE:` footer. It compares them with the
**family window**, the organization variable `PHP_LIBS_OPEN_RELEASE`. Only the
product owner sets or clears that variable:

- empty — no window is open;
- `X.Y` with `Y > 0` — a family minor window;
- `X.0` — a family major window (the `2.0.0` cut is window `2.0`).

| Change | Passes when |
|---|---|
| `!` or a `BREAKING CHANGE:` footer | a family major window is open |
| `feat:` | this lib is below `2.0.0` (per `.release-please-manifest.json` on the base branch), or any window is open |
| `Release-As:` footer | a window is open and the footer names its `X.Y.0` |
| any other type (`fix:`, `docs:`, `chore:`, …) | always |

On `2.x`, a feature PR waits open until its window. Keep it current by
merging `main` into it, never by rebase or force-push. Merge commits are not
checked. Re-run the check once the window opens. A PR into a branch other than
`main` (such as a `release/X.Y` maintenance branch) sees the window as closed.

> Historical note: `1.1.1` shipped the audit-consolidation breaking changes (the
> `WpBootstrap` / `WpHookfileLoader` renames and the removal of the dead
> `Lifecycle\Container`) as a patch by explicit maintainer decision, closing the
> OSS audit before external consumers existed. From this document on, a breaking
> `@api` change never lands in a patch.

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
- **On `1.x`:** pin a caret range (`^1.0`). Read the CHANGELOG before crossing
  a minor, because a `1.x` minor may carry a break (see the `1.x` policy). For
  zero-surprise upgrades, pin a tilde patch range (for example `~1.1.1`) to
  receive only patches.
- **From `2.0.0`:** declare one `^2.N` range, with the same `N`, for this
  adapter and every other `middag-php-*` library you depend on. Family minors
  are non-breaking. Read their deprecations before the next family major.
- The dependency direction only points downward: the adapter depends on the OSS
  framework's published `@api` and never imports the proprietary layer.
