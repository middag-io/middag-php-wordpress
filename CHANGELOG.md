# Changelog

## [1.1.4](https://github.com/middag-io/middag-php-wordpress/compare/v1.1.3...v1.1.4) (2026-07-07)


### Miscellaneous

* **deps:** bump middag-io/framework to ^1.2.0 ([ceff642](https://github.com/middag-io/middag-php-wordpress/commit/ceff64288fbd2d97da0de4999f62a25503923b2d))
* release 1.1.4 ([192a324](https://github.com/middag-io/middag-php-wordpress/commit/192a324ea453755cd226ab92b12033ce18033ef0))
* release 1.1.4 ([befa55c](https://github.com/middag-io/middag-php-wordpress/commit/befa55c362dac9c5ec91854edcbe66476669eb51))

## [1.1.3](https://github.com/middag-io/middag-php-wordpress/compare/v1.1.2...v1.1.3) (2026-07-06)


### Bug Fixes

* **settings:** reject invalid HTML attribute names in FieldRenderer ([2f32755](https://github.com/middag-io/middag-php-wordpress/commit/2f32755984c6a07addfd2a575a076b487995f7e5))
* **settings:** sanitize RawHtml field values by default ([3d675b7](https://github.com/middag-io/middag-php-wordpress/commit/3d675b78ec56cadd7074caa118bb60570e48540f))


### Refactoring

* **persistence:** narrow QueryBuilder::find guard to instanceof WP_Post ([b257b23](https://github.com/middag-io/middag-php-wordpress/commit/b257b2341b432eefdb09adbc8b98a8744c2d9117))


### Documentation

* close QG-WP-03/04/05/07 and relocate coverage tail to BACKLOG ([71f6982](https://github.com/middag-io/middag-php-wordpress/commit/71f698221cb760556b3de5ffbc475dab428e2750))

## [1.1.2](https://github.com/middag-io/middag-php-wordpress/compare/v1.1.1...v1.1.2) (2026-07-05)


### Documentation

* **api:** add API-STABILITY.md and link it from CONTRIBUTING ([48eeb56](https://github.com/middag-io/middag-php-wordpress/commit/48eeb5662b5713fa70ea0fe722ec3264f1da4300))
* **api:** tag remaining src types @api/[@internal](https://github.com/internal) ([e91f0c8](https://github.com/middag-io/middag-php-wordpress/commit/e91f0c8ec552b4f0c59aa096901586ea790dd337))


### Miscellaneous

* **composer:** add keywords, homepage and support metadata ([7017dbe](https://github.com/middag-io/middag-php-wordpress/commit/7017dbeb4302aed8c22c18815b49b26206282df5))
* **hooks:** accept the breaking-change marker in commit-msg ([ab301da](https://github.com/middag-io/middag-php-wordpress/commit/ab301da1b7da7af6e1aa4751e889ba6889ed3822))
* release 1.1.2 ([f1b359d](https://github.com/middag-io/middag-php-wordpress/commit/f1b359dfad0a2c46fed6c23c44d3730eee32ceb6))

## [1.1.1](https://github.com/middag-io/middag-php-wordpress/compare/v1.1.0...v1.1.1) (2026-07-03)


### ⚠ BREAKING CHANGES

* **lifecycle:** the @api class Middag\WordPress\Lifecycle\Container was removed; consumers must boot through the framework ContainerFactory / BootstrapInterface instead.
* **kernel:** Middag\WordPress\Kernel\WordPressBootstrap is now Middag\WordPress\Kernel\WpBootstrap and Middag\WordPress\Kernel\Loader\WordPressHookfileLoader is now Middag\WordPress\Kernel\Loader\WpHookfileLoader.

### Bug Fixes

* **hook:** require an explicit hook directory and derive fqcn from it alone ([93076d5](https://github.com/middag-io/middag-php-wordpress/commit/93076d5755f82a41284949c2ec2eb1723f211774))
* **http:** detach mtls curl action after each request and fail loudly when unapplied ([a89db85](https://github.com/middag-io/middag-php-wordpress/commit/a89db85de1b5992244ba53a5216760a45dad7774))


### Refactoring

* **kernel:** unify host prefix on wp bootstrap classes ([c3c2031](https://github.com/middag-io/middag-php-wordpress/commit/c3c203178bb3645c4971175eedceb732a05b6863))
* **lifecycle:** remove dead static container facade ([609785f](https://github.com/middag-io/middag-php-wordpress/commit/609785f972bfb439ae9eda1505e1c97b4cd42fd2))


### Documentation

* align adapter docs with lote b (bridge, boundary guard, scripts) ([b8d00aa](https://github.com/middag-io/middag-php-wordpress/commit/b8d00aabbdcabd28a30df3e64cc63dd4acc30ed3))
* **contributing:** record the audit-consolidation patch exception ([285479c](https://github.com/middag-io/middag-php-wordpress/commit/285479ce806d95461099fb9ca52de98332c26bcc))
* **versioning:** adopt the family 1.x policy and drop inert pre-major flags ([cfaa359](https://github.com/middag-io/middag-php-wordpress/commit/cfaa3596902469adbd614fb4e7396bcb796ecf42))


### Miscellaneous

* **composer:** align scripts with the canonical baseline ([c652ca3](https://github.com/middag-io/middag-php-wordpress/commit/c652ca393157614579607fddb7d9f0d63c1e812e))
* **dev:** add php 8.2 parse-level lint script ([5e8318d](https://github.com/middag-io/middag-php-wordpress/commit/5e8318daf642270d54a97ec1decc06ffcbc13752))
* release 1.1.1 ([6ac8b4e](https://github.com/middag-io/middag-php-wordpress/commit/6ac8b4e29bcaf01e9e335717f077b005a77f05ea))

## [1.1.0](https://github.com/middag-io/middag-php-wordpress/compare/v1.0.1...v1.1.0) (2026-07-03)


### ⚠ BREAKING CHANGES

* **structure:** class namespaces moved — Infrastructure\Bus, Persistence, User, Email, Frontend and Middleware no longer exist; consumers must update imports.

### Features

* **definition:** add declarative post type, taxonomy, cron schedule and shortcode definitions ([9b1fca9](https://github.com/middag-io/middag-php-wordpress/commit/9b1fca9462e6c900ff3cc5e3de2fdaa98b116917))
* **filesystem:** add WpUploadsFilesystem wiring the framework Filesystem port over uploads dir ([bbf038f](https://github.com/middag-io/middag-php-wordpress/commit/bbf038fa5c285ba26884d3e6a754b88976b04835))
* **http:** add HttpClient/HttpResponse over WP_Http with one-shot mTLS via http_api_curl ([fb2b3b9](https://github.com/middag-io/middag-php-wordpress/commit/fb2b3b9ef3fc352230009f6217bb496cebbb3b40))
* **logging:** add PSR-3 ErrorLogLogger over error_log ([9ec3cdb](https://github.com/middag-io/middag-php-wordpress/commit/9ec3cdb936838bf4e3832cc4b782b5fb27cba1b7))
* **mail:** add WpMailer wiring the framework Mail port over wp_mail ([15cbf35](https://github.com/middag-io/middag-php-wordpress/commit/15cbf35abe3b04e7b1a8b2e1696c4c8e95037902))
* **security:** add CapabilityRegistrar for role capability registration ([401d169](https://github.com/middag-io/middag-php-wordpress/commit/401d1698d06c53efb18d7bf561020df31afc26a4))
* **settings:** add declarative Settings API (Tab/Section/Field, escaped renderer, registrar) ([34a06b2](https://github.com/middag-io/middag-php-wordpress/commit/34a06b25d676122f1f04962d7438216a60c166a4))
* **support:** add Support seams (admin, cache, meta, post type, shortcode, transient, upload) ([3ec25e7](https://github.com/middag-io/middag-php-wordpress/commit/3ec25e7662bbddb37b7f4b5b0ec9345e3a666d4b))


### Refactoring

* **structure:** reorganize namespaces to host adapter layout ([266c954](https://github.com/middag-io/middag-php-wordpress/commit/266c95422ddfb55e5481fb05f1a4a6b38470c7f7))


### Miscellaneous

* **deps:** require middag-io/framework ^1.0.2 and firebase/php-jwt ^7.0 ([1186a53](https://github.com/middag-io/middag-php-wordpress/commit/1186a530defb527c05c1a7749997413709cefe15))

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
