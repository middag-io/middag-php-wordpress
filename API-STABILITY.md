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
| `@api` | **Public, supported surface.** Consumer plugins may implement, extend, type-hint, instantiate and catch these. Changes follow [Versioning from `2.0.0`](#versioning-from-200). |
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

## Lifecycle

- **`2.0.0` is the first supported release.** Support starts there.
- **The `1.x` line is pre-stable and unsupported.** It carries no
  compatibility promise, no deprecation cycle, no backports and no security
  fixes. A breaking change may ship in a `1.x` minor.
- **The cut to `2.0.0` is clean.** There is no upgrade path from `1.x`. The
  `CHANGELOG` continues across the cut.

## Versioning from `2.0.0`

This adapter belongs to the `middag-php-*` library version family: every
library of the family shares MAJOR.MINOR, and PATCH is free per library. A
patch carries fixes only, a family minor carries additive `@api` changes, and
a family major carries breaking `@api` changes. **Every library of the family
is required with the same `^2.N`**, with the same `N`.

The full rules (lifecycle, what a patch, minor and major may carry, the
supported surface and the compatibility matrix) are on
[Versioning and compatibility](https://docs.middag.dev/libraries/reference/versioning-and-compatibility).
The platform and the CI jobs, the family window check included, are on
[Platform](https://docs.middag.dev/libraries/reference/platform).

## How releases are cut

- Releases are cut only by
  [release-please](https://github.com/googleapis/release-please) from
  [Conventional Commits](https://www.conventionalcommits.org/). There are no
  manual tags.
- Every pull request runs the family window check
  `php-version-family-check / check` (`.github/workflows/family-window.yml`).
  `!` before the colon, or a `BREAKING CHANGE:` footer, fails unless a family
  major window is open. Give each commit the type that matches the change.

## The contracts this adapter fulfils

The adapter does not define the host-bridge contracts — it implements the ones
declared in `middag-io/framework`. Depend on the **framework**
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
- **On `1.x`:** the line is pre-stable and unsupported (see
  [Lifecycle](#lifecycle)).
- **From `2.0.0`:** declare one `^2.N` range, with the same `N`, for this
  adapter and every other `middag-php-*` library you depend on.
- The dependency direction only points downward: the adapter depends on the OSS
  framework's published `@api` and never imports the proprietary layer.

## The public surface

Every type tagged `@api` in `src/`, by namespace.

### Admin

- `Middag\WordPress\Admin\AdminRouteRegistrar`
- `Middag\WordPress\Admin\MenuPage`
- `Middag\WordPress\Admin\SubMenuPage`

### Bus

- `Middag\WordPress\Bus\WpHostEventBridge`
- `Middag\WordPress\Bus\WpUserContext`

### Config

- `Middag\WordPress\Config\WpConfigResolver`

### Cron

- `Middag\WordPress\Cron\ActionSchedulerDriver`
- `Middag\WordPress\Cron\Contract\JobSchedulerInterface`
- `Middag\WordPress\Cron\CronHandler`
- `Middag\WordPress\Cron\CronRegistrar`
- `Middag\WordPress\Cron\Enum\CronInterval`
- `Middag\WordPress\Cron\JobScheduler`
- `Middag\WordPress\Cron\WpCronDriver`

### Database

- `Middag\WordPress\Database\WpdbConnectionAdapter`
- `Middag\WordPress\Database\WpdbSqlDialect`

### Definition

- `Middag\WordPress\Definition\CronScheduleDefinition`
- `Middag\WordPress\Definition\DefinitionRegistrar`
- `Middag\WordPress\Definition\PostTypeDefinition`
- `Middag\WordPress\Definition\ShortcodeDefinition`
- `Middag\WordPress\Definition\TaxonomyDefinition`

### Domain

- `Middag\WordPress\Domain\Comment\CommentDto`
- `Middag\WordPress\Domain\Media\MediaAttachmentDto`
- `Middag\WordPress\Domain\Post\PostMetaRepository`
- `Middag\WordPress\Domain\Post\PostRepository`
- `Middag\WordPress\Domain\Taxonomy\TaxonomyDto`
- `Middag\WordPress\Domain\Taxonomy\TermDto`
- `Middag\WordPress\Domain\User\UserMeta`
- `Middag\WordPress\Domain\User\UserRepository`
- `Middag\WordPress\Domain\WooCommerce\OrderReference`
- `Middag\WordPress\Domain\WooCommerce\ProductReference`
- `Middag\WordPress\Domain\WooCommerce\WooCommerceAvailability`

### Exception

- `Middag\WordPress\Exception\HookRegistrationException`
- `Middag\WordPress\Exception\SettingsRenderException`
- `Middag\WordPress\Exception\WordPressAdapterException`
- `Middag\WordPress\Exception\WordPressDatabaseException`

### Filesystem

- `Middag\WordPress\Filesystem\WpUploadsFilesystem`

### Hook

- `Middag\WordPress\Hook\Contract\HookInterface`
- `Middag\WordPress\Hook\HookRegistrar`

### Http

- `Middag\WordPress\Http\Auth\WpSessionAuthenticator`
- `Middag\WordPress\Http\Client\HttpClient`
- `Middag\WordPress\Http\Client\HttpResponse`
- `Middag\WordPress\Http\Contract\RequestAuthenticatorInterface`
- `Middag\WordPress\Http\Contract\ResponseEmitterInterface`
- `Middag\WordPress\Http\Contract\RestControllerInterface`
- `Middag\WordPress\Http\Contract\RestRouteMiddlewareInterface`
- `Middag\WordPress\Http\Contract\RouterInterface`
- `Middag\WordPress\Http\Controller\AbstractWpController`
- `Middag\WordPress\Http\Controller\AbstractWpRestController`
- `Middag\WordPress\Http\Inertia\InertiaAdapter`
- `Middag\WordPress\Http\Response\RestResponse`
- `Middag\WordPress\Http\Routing\PublicRouteRegistrar`
- `Middag\WordPress\Http\Routing\RestRouteAttributeRegistrar`
- `Middag\WordPress\Http\Routing\RestRouteRegistrar`
- `Middag\WordPress\Http\Routing\WpRouter`
- `Middag\WordPress\Http\Security\CsrfGuard`
- `Middag\WordPress\Http\WpHttpKernel`
- `Middag\WordPress\Http\WpRestKernel`

### Mail

- `Middag\WordPress\Mail\EmailSender`
- `Middag\WordPress\Mail\EmailTemplate`
- `Middag\WordPress\Mail\WpMailer`

### Persistence

- `Middag\WordPress\Persistence\QueryBuilder`
- `Middag\WordPress\Persistence\Query\WpdbConditionCompiler`
- `Middag\WordPress\Persistence\VersionTracker`

### Privacy

- `Middag\WordPress\Privacy\Contract\PersonalDataProviderInterface`

### Runtime

- `Middag\WordPress\Runtime\Kernel`
- `Middag\WordPress\Runtime\Loader\WpHookfileLoader`
- `Middag\WordPress\Runtime\PluginLifecycle`
- `Middag\WordPress\Runtime\WpComponentContext`

### Security

- `Middag\WordPress\Security\Attribute\Nonce`
- `Middag\WordPress\Security\CapabilityRegistrar`
- `Middag\WordPress\Security\Enum\CapabilityInterface`
- `Middag\WordPress\Security\Enum\WooCommerceCapability`
- `Middag\WordPress\Security\Enum\WpCapability`

### Settings

- `Middag\WordPress\Settings\Enum\FieldType`
- `Middag\WordPress\Settings\Field`
- `Middag\WordPress\Settings\FieldRenderer`
- `Middag\WordPress\Settings\Section`
- `Middag\WordPress\Settings\SettingsPageRegistrar`
- `Middag\WordPress\Settings\Tab`

### Support

- `Middag\WordPress\Support\AdminSupport`
- `Middag\WordPress\Support\CacheSupport`
- `Middag\WordPress\Support\CacheSupportPsr16`
- `Middag\WordPress\Support\MetaSupport`
- `Middag\WordPress\Support\OptionSupport`
- `Middag\WordPress\Support\PostTypeSupport`
- `Middag\WordPress\Support\ShortcodeSupport`
- `Middag\WordPress\Support\TransientSupport`
- `Middag\WordPress\Support\UploadSupport`
