# Changelog

## [1.6.1](https://github.com/middag-io/middag-php-wordpress/compare/v1.6.0...v1.6.1) (2026-07-14)


### Bug Fixes

* **autoload:** release the classmap host-stub exclusion ([8ba19e3](https://github.com/middag-io/middag-php-wordpress/commit/8ba19e3f652fed62262f4c39f9fec1300d4a8b91))

## [1.6.0](https://github.com/middag-io/middag-php-wordpress/compare/v1.5.0...v1.6.0) (2026-07-13)


### ⚠ BREAKING CHANGES

* **http:** Middag\WordPress\Http\Middleware\AuthMiddleware is removed and Middag\WordPress\Http\Controller\BaseController is renamed to AbstractWpRestController with a required RequestAuthenticatorInterface constructor argument. App-session JWT tokens are now issued/verified by middag-io/core's WordPressTokenService; controllers receive an injected RequestAuthenticatorInterface (the library ships WpSessionAuthenticator for the WP-session case). No compatibility shim (hard-cutover).
* **routing:** 3 formal REST/ADMIN/PUBLIC surfaces per component (O5-LIB-02)
* **cron:** CronRegistrar API changed (hard-cutover, no shim):
    - `addEvent(string, CronInterval, callable)` -- 2nd arg no longer accepts a string.
    - ctor now requires `(HostComponentContextInterface, TranslatorInterface)`,
      resolved by the plugin's DI (mirrors the per-component pattern of LIB-03).
    - static `addIntervals()` + const `INTERVALS` removed; becomes the instance
      method `registerIntervals()`, with keys `{component}_{case->value}` in the
      `cron_schedules` filter -- two plugins in the same request no longer collide.
* **http:** CsrfGuard + SettingsPageRegistrar emit via ResponseEmitter (O5-LIB-04)
* **inertia:** injectable emitter + edge superglobal in InertiaAdapter (O5-LIB-04)
* **mail:** EmailTemplate renders via isolated $view, no extract() (O5-LIB-04)
* **logging:** LogSupport becomes stateless resolver + per-instance logger (O5-LIB-03)
* **inertia:** per-component InertiaAdapter + derived nonce (O5-LIB-03)

### Features

* **routing:** 3 formal REST/ADMIN/PUBLIC surfaces per component (O5-LIB-02) ([7af6cc6](https://github.com/middag-io/middag-php-wordpress/commit/7af6cc6de5d7c39cc10639d65d2ab42d52b78014))
* **security:** typed catalog of WP + WooCommerce capabilities ([85794ef](https://github.com/middag-io/middag-php-wordpress/commit/85794ef2c3b94dc2f7db18ca563b4aa487777c63))


### Refactoring

* **cron:** type-safe CronInterval enum + i18n + per-component prefix (O5-LIB-06) ([e9283cf](https://github.com/middag-io/middag-php-wordpress/commit/e9283cf1473e5001a5f62e17aeb8149c0f5f8dd2))
* **http:** CsrfGuard + SettingsPageRegistrar emit via ResponseEmitter (O5-LIB-04) ([24de2e9](https://github.com/middag-io/middag-php-wordpress/commit/24de2e9877073bd93475c7719a0641fefbb0ee79))
* **http:** remove static AuthMiddleware; inject request auth into the controller (O5-LIB-05, O5-LIB-01) ([7281777](https://github.com/middag-io/middag-php-wordpress/commit/72817779779e480aab779dad6cc19ed5ce90430e))
* **inertia:** injectable emitter + edge superglobal in InertiaAdapter (O5-LIB-04) ([96ebf37](https://github.com/middag-io/middag-php-wordpress/commit/96ebf37bb47ca987f64cc534e0d684f8dd599ccb))
* **inertia:** per-component InertiaAdapter + derived nonce (O5-LIB-03) ([34514fd](https://github.com/middag-io/middag-php-wordpress/commit/34514fd49ae235e569ddd40a86056f65f8f11501))
* **logging:** LogSupport becomes stateless resolver + per-instance logger (O5-LIB-03) ([db7841a](https://github.com/middag-io/middag-php-wordpress/commit/db7841a0788ada46454cb56e62947daaa7c99b35))
* **mail:** EmailTemplate renders via isolated $view, no extract() (O5-LIB-04) ([12b4553](https://github.com/middag-io/middag-php-wordpress/commit/12b4553846f320e6d6891d6e75f125e86d18904a))


### Miscellaneous

* release middag-io/wordpress as 1.6.0 ([31c86d6](https://github.com/middag-io/middag-php-wordpress/commit/31c86d69e5b3ec0bbebc28b9711ec8452d948b46))

## [1.5.0](https://github.com/middag-io/middag-php-wordpress/compare/v1.4.0...v1.5.0) (2026-07-09)


### ⚠ BREAKING CHANGES

* **logging:** Middag\WordPress\Logging\PhpErrorLogLogger is removed. Use Middag\Framework\Logging\ErrorLogFallbackLogger instead (identical behaviour and constructor signature).

### Bug Fixes

* **tests:** make AuthMiddlewareTest and WpMaintenanceGateTest host-independent ([0d9fa07](https://github.com/middag-io/middag-php-wordpress/commit/0d9fa07b2d5a8c6560a44845efc910981c64ac3b))


### Refactoring

* **logging:** relocate error_log fallback to framework ([1a4c901](https://github.com/middag-io/middag-php-wordpress/commit/1a4c901ffb5fb8cc8d7691aa6a62a8b5161ab332))


### Miscellaneous

* **deps:** require middag-io/framework ^1.7 ([d577ef5](https://github.com/middag-io/middag-php-wordpress/commit/d577ef55b1bcbf5f05a668f931fe4dcc48267fe5))

## [1.4.0](https://github.com/middag-io/middag-php-wordpress/compare/v1.3.0...v1.4.0) (2026-07-09)


### ⚠ BREAKING CHANGES

* **runtime:** Middag\WordPress\Kernel\* FQCNs are gone; import Middag\WordPress\Runtime\* instead. Framework contracts (Middag\Framework\Kernel\*) are unaffected.

### Refactoring

* **runtime:** move Kernel namespace to Runtime ([3b3c5d8](https://github.com/middag-io/middag-php-wordpress/commit/3b3c5d81b47713f801e539b5ade4d2634e7ee9d7))


### Miscellaneous

* release 1.4.0 ([ebbf114](https://github.com/middag-io/middag-php-wordpress/commit/ebbf1148f4934e3d89b9056eac53f0a06ad146c6))

## [1.3.0](https://github.com/middag-io/middag-php-wordpress/compare/v1.2.0...v1.3.0) (2026-07-09)


### ⚠ BREAKING CHANGES

* requires middag-io/framework with PascalCase enum cases.
* **inertia:** Http\Inertia\PageContractNormalizer is removed and InertiaAdapter no longer normalizes the `contract` prop. Callers must pass the canonical PageContract shape directly.
* **logging:** the @api class Middag\WordPress\Logging\ErrorLogLogger is renamed to Middag\WordPress\Logging\PhpErrorLogLogger with no BC alias. Consumers instantiating the old class must update the reference.

### Features

* adopt PascalCase enum cases from middag-io/framework + guard test ([f02321e](https://github.com/middag-io/middag-php-wordpress/commit/f02321ed59ed94803121bc95e7a27fa7d4ca824b))


### Refactoring

* **inertia:** drop PageContractNormalizer, accept only canonical contract ([c7cc37c](https://github.com/middag-io/middag-php-wordpress/commit/c7cc37c8009d4c42719aca6cfee8fa201c482881))
* **logging:** rename ErrorLogLogger to PhpErrorLogLogger ([1ae6803](https://github.com/middag-io/middag-php-wordpress/commit/1ae6803d4b41430430f405808d9bc0e5636e23f4))
* **settings:** move FieldType into Settings\Enum namespace ([6d7e225](https://github.com/middag-io/middag-php-wordpress/commit/6d7e2250d2650f6821ceec33c91de6a1c932d3cd))


### Documentation

* **logging:** position channel factory as canonical path, add channel test ([49e630b](https://github.com/middag-io/middag-php-wordpress/commit/49e630b508f1a1636f9652842f7b133142311e41))
* retire blocked-work backlog ([d33332d](https://github.com/middag-io/middag-php-wordpress/commit/d33332d656acb82231446d56c7504e76df09547d))


### Miscellaneous

* **deps:** drop v prefix from framework constraint ([c5f23e4](https://github.com/middag-io/middag-php-wordpress/commit/c5f23e4cc87fc1f5cebef35ea6910f94f36dff97))
* **deps:** raise framework floor to ^1.5 ([ab93759](https://github.com/middag-io/middag-php-wordpress/commit/ab937598d6581e529e7df801d2ebefb89e4541fb))
* **deps:** raise framework floor to ^1.6 ([481ef1e](https://github.com/middag-io/middag-php-wordpress/commit/481ef1e7928821ace6c199904fb767719eddd740))
* release 1.3.0 ([4e0e021](https://github.com/middag-io/middag-php-wordpress/commit/4e0e0216003444beb95e23ad8c3b22f6b347f1de))
* release wordpress 1.3.0 ([419f1e8](https://github.com/middag-io/middag-php-wordpress/commit/419f1e81cb5542bf5d42aef9ef0157ce3852cff2))

## [1.2.0](https://github.com/middag-io/middag-php-wordpress/compare/v1.1.4...v1.2.0) (2026-07-08)


### ⚠ BREAKING CHANGES

* normalize WordPress adapter structure

### Refactoring

* normalize WordPress adapter structure ([04b9ce0](https://github.com/middag-io/middag-php-wordpress/commit/04b9ce071614d2b428c83196e7dbd5af0ac4e89e))


### Documentation

* note future core/src/WordPress + WP-Cron async binding boundary [LB-0-13/NOVO-WP-A] ([1a7988c](https://github.com/middag-io/middag-php-wordpress/commit/1a7988cb8715134f27b376977297bae6291a6ad2))


### Miscellaneous

* release wordpress 1.2.0 ([0bed1db](https://github.com/middag-io/middag-php-wordpress/commit/0bed1db797e4c739efd354004c2bfb2d517709b5))

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
