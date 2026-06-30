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
composer check   # import boundaries + PHPStan + PHP-CS-Fixer (dry-run) + Rector (dry-run)
composer test    # PHPUnit
```

Auto-fix style and Rector findings with:

```bash
composer fix
```

`composer check:boundaries` enforces that the adapter never imports any non-OSS
MIDDAG namespace or package. Keep `src/` free of those imports — the adapter
must remain consumable on its own.

## Commit and PR conventions

- [Conventional Commits](https://www.conventionalcommits.org/): `type(scope): description`.
  Types: `feat`, `fix`, `chore`, `docs`, `style`, `refactor`, `perf`, `test`,
  `build`, `ci`, `revert`.
- Keep pull requests focused. Update tests and docs alongside code.
- Releases are automated by [release-please](https://github.com/googleapis/release-please)
  from commits merged to `main`.

## Code of conduct

This project has a [Code of Conduct](CODE_OF_CONDUCT.md). By participating you
agree to uphold it; the reporting contact is listed there.
