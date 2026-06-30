# Changelog

## [1.0.1](https://github.com/middag-io/middag-php-wordpress/compare/v1.0.0...v1.0.1) (2026-06-30)


### Documentation

* align CONTRIBUTING + CI dependency guidance with Packagist publication ([66dc7aa](https://github.com/middag-io/middag-php-wordpress/commit/66dc7aae36c326c9388c64c28cf9ba585f18544d))

## [1.0.0](https://github.com/middag-io/middag-php-wordpress/compare/v0.3.1...v1.0.0) (2026-06-27)


### Bug Fixes

* **deps:** require middag-io/framework ^1.0 ([b334d4c](https://github.com/middag-io/middag-php-wordpress/commit/b334d4cde431b237d81dec316305c9c3fdd34526))


### Miscellaneous Chores

* release 1.0.0 ([bfb8a98](https://github.com/middag-io/middag-php-wordpress/commit/bfb8a989f45609978a65d95481434ad8fe78fa1a))

## [0.3.1](https://github.com/middag-io/middag-php-wordpress/compare/v0.3.0...v0.3.1) (2026-06-26)


### Bug Fixes

* **i18n:** replace PT-BR strings with English and neutral config prefixes ([3debcba](https://github.com/middag-io/middag-php-wordpress/commit/3debcba7d07f46d01212e135012b0c05dc185a10))

## [0.3.0](https://github.com/middag-io/middag-php-wordpress/compare/v0.2.1...v0.3.0) (2026-06-26)


### Miscellaneous Chores

* initial public release ([18b17de](https://github.com/middag-io/middag-php-wordpress/commit/18b17dec40ddd30b4fc2d37a0123324eecff7d3c))

## [0.2.1](https://github.com/middag-io/middag-php-wordpress/compare/v0.2.0...v0.2.1) (2026-06-06)


### Miscellaneous Chores

* release 0.2.1 ([efb110a](https://github.com/middag-io/middag-php-wordpress/commit/efb110a8ea9e0691e561644735bfcb52aeeb13bc))

## [0.2.0](https://github.com/middag-io/middag-php-wordpress/compare/v1.0.1...v0.2.0) (2026-05-25)


### ⚠ BREAKING CHANGES

* RouteRegistrar now requires ContainerInterface in constructor. HookRegistrar constructor params are optional (backward compatible). CronHandler::dispatch() signature changed.

### Features

* **dto:** add PaymentEvent + CrmProviderInterface from framework (B-144, PD-043 A) ([c6e551e](https://github.com/middag-io/middag-php-wordpress/commit/c6e551ee96772e9d8870b1ebec61efa743b6aaa6))
* extract 23 WP adapter files from the upstream MIDDAG codebase ([c430244](https://github.com/middag-io/middag-php-wordpress/commit/c43024468dedbe841a25a2b1bb1feef79924b5ec))
* extract plugin components and implement framework adapter contracts ([6c46056](https://github.com/middag-io/middag-php-wordpress/commit/6c4605635568daf7fd22babdef6a4f55f369f422))
* **kernel:** A2.0.7 WordPressBootstrap — stub getProjectRoot/getOptions (D-024) ([7e40beb](https://github.com/middag-io/middag-php-wordpress/commit/7e40bebdb5ffe5a5b6312e5468b4c0553aac70e4))
* **transport:** add WpCronTransport for JobBus (B-152) ([fce1f60](https://github.com/middag-io/middag-php-wordpress/commit/fce1f60db0f9bd5b0f602e9dd343887e5557da2a))
* **wordpress:** WordPressHookfileLoader with 3 discovery sources ([f22cef6](https://github.com/middag-io/middag-php-wordpress/commit/f22cef684df670e7e58772b18ef8fdf5db091c14))


### Bug Fixes

* **imports:** A3.7.2 update Bus contracts to Contract\Bus\* (framework D-045) ([6c564d7](https://github.com/middag-io/middag-php-wordpress/commit/6c564d7ff879509aabcf34d7dfe183dc27fd14e7))
* **imports:** A3.7.6 update Core cross-cutting contracts to Contract\Core\* (framework D-045) ([e36bdaa](https://github.com/middag-io/middag-php-wordpress/commit/e36bdaa2db1b194ca4e36655abf035648d393673))
* pass --config=.php-rector.php so rector picks up the project config ([5b4a67a](https://github.com/middag-io/middag-php-wordpress/commit/5b4a67a52f093f014dda450bdf40a592e13999ef))
* replace a product-specific env helper with framework-native resolveEnv ([b6a8767](https://github.com/middag-io/middag-php-wordpress/commit/b6a87675eb01df67363182eecda0bd47128156b8))
* **test:** add framework interface stubs for standalone testing ([0c11d63](https://github.com/middag-io/middag-php-wordpress/commit/0c11d63d9059c79c366ca8afe4da72e949e0e83e))
* use semver constraints for internal deps ([5900544](https://github.com/middag-io/middag-php-wordpress/commit/5900544ce5e054960c46d4f312e6e3d4587c7111))
* **wordpress:** BL-P0-WP-FRONTEND-MISSING — restore HookfileLoader + Frontend subsystem ([4508a74](https://github.com/middag-io/middag-php-wordpress/commit/4508a7402f2d2f18734e044590dca4dd8126eb38))


### Miscellaneous Chores

* **release:** force next cut at v0.2.0 (BREAKING→MINOR pre-major) ([863ff9b](https://github.com/middag-io/middag-php-wordpress/commit/863ff9b7f7f0eeec1e48bd09546a53f76a7a44f8))
* **release:** re-force v0.2.0 cut ([db14c8d](https://github.com/middag-io/middag-php-wordpress/commit/db14c8d5588ca4c62a2f7c198dbf560143ce0b96))


### Code Refactoring

* replace static Container calls with constructor injection ([42414fd](https://github.com/middag-io/middag-php-wordpress/commit/42414fd42c012a6259efcc2f29fe69ed144bbaa9))

## [1.0.1](https://github.com/middag-io/middag-wordpress/compare/v1.0.0...v1.0.1) (2026-05-14)


### Bug Fixes

* replace a product-specific env helper with framework-native resolveEnv ([b6a8767](https://github.com/middag-io/middag-wordpress/commit/b6a87675eb01df67363182eecda0bd47128156b8))

## [1.0.0](https://github.com/middag-io/middag-wordpress/compare/v0.1.1...v1.0.0) (2026-05-14)


### ⚠ BREAKING CHANGES

* RouteRegistrar now requires ContainerInterface in constructor. HookRegistrar constructor params are optional (backward compatible). CronHandler::dispatch() signature changed.

### Code Refactoring

* replace static Container calls with constructor injection ([42414fd](https://github.com/middag-io/middag-wordpress/commit/42414fd42c012a6259efcc2f29fe69ed144bbaa9))

## [0.1.1](https://github.com/middag-io/middag-wordpress/compare/v0.1.0...v0.1.1) (2026-05-13)


### Bug Fixes

* use semver constraints for internal deps ([5900544](https://github.com/middag-io/middag-wordpress/commit/5900544ce5e054960c46d4f312e6e3d4587c7111))
