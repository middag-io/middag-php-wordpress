# Contributing

Thanks for your interest in contributing to `middag-io/wordpress`, the WordPress
host adapter for the [MIDDAG framework](https://github.com/middag-io/middag-php-framework).

## Getting started

Requirements: PHP 8.2+ and Composer 2.

```bash
git clone https://github.com/middag-io/middag-php-wordpress
cd middag-php-wordpress
composer install
```

`composer install` registers the Git hooks (`core.hooksPath .githooks`); the
`commit-msg` hook enforces Conventional Commits.

## Dependency resolution

This adapter depends on the OSS `middag-io/framework` package (which transitively
brings in `middag-io/ui`). All dependencies are OSS or host-provided; no private
infrastructure is required.

- **Local development** can resolve `middag-io/framework` from a sibling path
  repository (`../middag-php-framework`, symlinked) declared in the
  **root/consumer** `composer.json` (per ADR-013) — this package's own
  `composer.json` carries no `repositories` entry. Clone the framework next to
  this repo if you want to edit both together. This is a development-only
  convenience and has no effect on published releases.
- `composer.lock` is **gitignored**. A local lock that references path or dev
  versions of the framework is expected development state — **not** a defect in
  the released package.
- CI and external consumers resolve the `middag-io/*` packages from Packagist —
  no private mirror and no credentials.

## Quality gates

Every change must pass:

```bash
composer check   # PHP-CS-Fixer (dry-run) + Rector (dry-run) + PHPStan
composer test    # PHPUnit (includes the boundary guard test)
```

Auto-fix style and Rector findings with:

```bash
composer fix
```

The `AdapterPluginIsolationTest` guard test (run as part of `composer test`)
enforces that the adapter never imports any non-OSS MIDDAG namespace or package,
and never hard-codes product gold tables. Keep `src/` free of those imports —
the adapter must remain consumable on its own.

## Commit and PR conventions

- [Conventional Commits](https://www.conventionalcommits.org/): `type(scope): description`.
  Types: `feat`, `fix`, `chore`, `docs`, `style`, `refactor`, `perf`, `test`,
  `build`, `ci`, `revert`.
- Keep pull requests focused. Update tests and docs alongside code.
- Releases are automated by [release-please](https://github.com/googleapis/release-please)
  from commits merged to `main`.

### Versioning

Releases are cut **exclusively** by release-please — never by a manual tag.
The full policy is in [`API-STABILITY.md`](API-STABILITY.md), which follows
ADR-032 of `docs-middag-ops` (the version family of the `middag-php-*`
libraries; it amends ADR-003 for them). In short:

- **On `1.x`:** `fix:` cuts a patch and `feat:` cuts a minor. Until 2026-11-30 a
  minor may carry a breaking change, under the exception to `MSR-015.R1(b)` in
  `org-middag` `adoption.yaml`. Ship it as `feat:` or `fix:` and describe the
  break and its migration in the commit body. Never use `!` or a
  `BREAKING CHANGE:` footer: release-please would cut `2.0.0` in this lib alone.
  From 2026-12-01 no break lands on `1.x`.
- **From `2.0.0`:** this lib shares MAJOR.MINOR with the other seven
  `middag-php-*` libraries. A patch carries only fixes. A `feat:` waits for a
  family minor window. A break waits for a family major window, after a
  deprecation, with a runtime notice, in an earlier minor. The product owner
  decides every family minor and major. Each one is a coordinated
  `Release-As: X.Y.0` in every lib.
- **The check.** Every PR runs `php-version-family-check / check`. It checks
  the PR title and every non-merge commit against the organization variable
  `PHP_LIBS_OPEN_RELEASE`, which only the product owner sets. The rules are in
  [`API-STABILITY.md`](API-STABILITY.md#the-family-window-check). If your
  `feat:` PR is waiting for a window, keep it current by merging `main` into it
  (never rebase or force-push). Re-run the check once the window opens.

> Historical note: `1.1.1` shipped the audit-consolidation breaking changes
> (the `WpBootstrap`/`WpHookfileLoader` renames and the removal of the dead
> `Lifecycle\Container`) as a patch by explicit maintainer decision, closing
> the OSS audit before external consumers existed. The policy above applies
> from that release onward.

## Code of conduct

This project has a [Code of Conduct](CODE_OF_CONDUCT.md). By participating you
agree to uphold it; the reporting contact is listed there.
