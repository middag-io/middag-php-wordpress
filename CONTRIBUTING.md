# Contributing

Thanks for your interest in contributing to `middag-io/wordpress`, the WordPress
host adapter for the [MIDDAG framework](https://github.com/middag-io/middag-php-framework).

## Getting started

Requirements: PHP 8.3+ and Composer 2.

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
  **root/consumer** `composer.json` — this package's own
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

A change to code that calls WordPress also runs the integration suite, on a
real WordPress and a real MySQL database
([`tests/Integration/README.md`](tests/Integration/README.md)):

```bash
composer test:integration
```

Auto-fix style and Rector findings with:

```bash
composer fix
```

The `AdapterPluginIsolationTest` guard test (run as part of `composer test`)
enforces that `src/` names only the OSS MIDDAG namespaces (`Middag\WordPress`,
`Middag\Framework`, `Middag\Ui`), so the adapter stays consumable on its own.
The other guards are in `tests/Architecture/`.

## Commit and PR conventions

- [Conventional Commits](https://www.conventionalcommits.org/): `type(scope): description`.
  Types: `feat`, `fix`, `chore`, `docs`, `style`, `refactor`, `perf`, `test`,
  `build`, `ci`, `revert`.
- Keep pull requests focused. Update tests and docs alongside code.
- Releases are automated by [release-please](https://github.com/googleapis/release-please)
  from commits merged to `main`.

### Versioning

Releases are cut **exclusively** by release-please, never by a manual tag. On
`1.x`, which is pre-stable and unsupported, `fix:` cuts a patch and `feat:`
cuts a minor; a break ships as `feat:`, never with `!` or a `BREAKING CHANGE:`
footer. From `2.0.0` the library shares the MAJOR version with the other
`middag-php-*` libraries; MINOR and PATCH are per library. `feat:` cuts a MINOR
of this library, `fix:` cuts a PATCH, and `!` or `BREAKING CHANGE:` is for a
MAJOR. Give each commit the type that matches the change. Every PR runs the
version check `php-version-family-check / check`. The rules are in
[`API-STABILITY.md`](API-STABILITY.md#versioning-from-200) and on
[Versioning and compatibility](https://docs.middag.dev/libraries/reference/versioning-and-compatibility).

## Code of conduct

This project has a [Code of Conduct](CODE_OF_CONDUCT.md). By participating you
agree to uphold it; the reporting contact is listed there.
