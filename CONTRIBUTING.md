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
  repository (`../middag-php-framework`, symlinked) declared in `composer.json`.
  Clone the framework next to this repo if you want to edit both together. This
  is a development-only convenience and has no effect on published releases.
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
The package is on the **`1.x`** line and follows the family policy defined in
the framework's [`API-STABILITY.md`](https://github.com/middag-io/middag-php-framework/blob/main/API-STABILITY.md):
during `1.x` a breaking change may ship in a minor — always explicitly marked
(`!` / `BREAKING CHANGE:`) and cut deliberately by a maintainer with a
`Release-As:` footer, never in a patch. A major release is never cut
automatically: it happens only by explicit maintainer decision, when the break
genuinely impacts Composer consumers — a release PR proposing a major bump is
not merged without that sign-off.

## Code of conduct

This project has a [Code of Conduct](CODE_OF_CONDUCT.md). By participating you
agree to uphold it; the reporting contact is listed there.
