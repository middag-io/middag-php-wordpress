# CLAUDE.md — middag-io/wordpress

## What this package is

OSS WordPress adapter (Apache-2.0) for the MIDDAG framework. It carries only basic host toil: `$wpdb` connection/dialect, REST base, hooks/cron, config/user context, translation, Inertia frontend glue, `wp_mail` email, uploads filesystem, and native post/user persistence. **It never imports non-OSS MIDDAG namespaces or packages** (Article-I). This boundary is enforced by the source-scan guard test `tests/AdapterPluginIsolationTest.php`, which runs as part of `composer test` — there is no separate `check:boundaries` script.

Governed MIDDAG capabilities (Item/EAV, QueryEngine, `middag_*` schema, Audit/ActivityFeed/ItemRevision/Job, outbox, tenant/organization, rate limit, scopes, Payment/Webhook/WooCommerce/CRM) are **not part of this OSS adapter** — they belong to non-OSS MIDDAG layers.

Future governed WordPress code may live in `middag-io/core` under `src/WordPress/`.
That does not change this adapter's boundary: the OSS adapter stays generic and
never imports `Middag\Core\`. If core/framework binds the neutral `async`
transport on WordPress, name it as a WP-Cron binding owned by the consumer/core
composition root, not as proprietary infrastructure inside this adapter.

### Non-target host surfaces (by design)

Some WordPress host surfaces are **intentionally not targeted** by this adapter: **widgets** (`register_widget` / `WP_Widget`) and **`admin-ajax.php`** action handlers. Consumers reach the front end through the three formal routing surfaces below (REST, admin, public rewrite) plus the Inertia glue (`Http/Inertia`), so classic widget areas and the legacy `admin-ajax` transport carry no consumer and no code here. This is a scope decision, not a gap — do not add widget/`admin-ajax` seams without a real consumer. See the coverage roadmap in `BACKLOG.md` for other by-design-absent surfaces (R1).

- **Depends on** `middag-io/framework`
- **Consumed by:** a WordPress host plugin, through its own composition root

## Structure

| Directory | Contents |
|-----------|----------|
| `src/Admin/` | AdminRouteRegistrar (wp-admin menu tree → Router dispatch) + MenuPage/SubMenuPage value objects — the ADMIN routing surface |
| `src/Bus/` | WpUserContext (UserContextResolverInterface) |
| `src/Config/` | WpConfigResolver (ConfigResolverInterface) |
| `src/Cron/` | CronHandler, CronRegistrar (basic wp-cron) |
| `src/Database/` | WpdbConnectionAdapter (ConnectionAdapterInterface), WpdbSqlDialect |
| `src/Definition/` | PostTypeDefinition, TaxonomyDefinition, CronScheduleDefinition, ShortcodeDefinition + DefinitionRegistrar (declarative registration on the host) |
| `src/Domain/Comment/` | CommentDto (host-neutral shape for WP comments) |
| `src/Domain/Media/` | MediaAttachmentDto (attachments/uploads metadata) |
| `src/Domain/Post/` | PostRepository, PostMetaRepository (wp_posts + wp_postmeta) |
| `src/Domain/Taxonomy/` | TaxonomyDto, TermDto (native taxonomy/term shape) |
| `src/Domain/User/` | UserRepository, UserMeta |
| `src/Domain/WooCommerce/` | Optional WooCommerce value objects, guarded by runtime availability checks; no hard dependency |
| `src/Exception/` | Adapter-specific exception hierarchy for hooks, settings rendering, database failures |
| `src/Filesystem/` | WpUploadsFilesystem (framework FilesystemInterface → uploads dir, via LocalFilesystem) |
| `src/Hook/` | HookRegistrar + `Contract/HookInterface` (requires an explicit, existing hook directory) |
| `src/Http/` | `Client/{HttpClient,HttpResponse}` (wp_remote_* with optional mTLS — see below), `Contract/RestControllerInterface`, `Controller/BaseController`, `Response/RestResponse`, `Routing/{Router, RestRouteRegistrar (REST surface), PublicRouteRegistrar (public rewrite surface)}`, `ControllerResolver`, `Inertia/InertiaAdapter`, `Middleware/AuthMiddleware` (JWT host auth), `Security/CsrfGuard` |
| `src/Runtime/` | WpBootstrap (BootstrapInterface), WpComponentContext (HostComponentContextInterface), WpMaintenanceGate, PluginLifecycle, Loader/WpHookfileLoader |
| `src/Mail/` | WpMailer (framework MailerInterface → wp_mail), EmailSender, EmailTemplate |
| `src/Persistence/` | QueryBuilder (WP_Query/wp_posts) |
| `src/Privacy/` | PrivacyRegistrar + `Contract/PersonalDataProviderInterface` (WordPress personal-data export/erasure glue) |
| `src/Security/` | CapabilityRegistrar (declarative caps per role) |
| `src/Settings/` | Declarative settings framework: Tab→Section→Field (FieldType with default sanitizer), FieldRenderer (escaped), SettingsPageRegistrar over SettingDefinition/SettingsRegistrar |
| `src/Support/` | 24 `*Support` static seams over WordPress functions (hooks, options, meta, cache, transients, uploads, sanitize/escape, logging, rewrite, ...) |
| `src/Translation/` | WpTranslator (framework TranslatorInterface) |

## Routing surfaces

Three formal surfaces, each per-component (namespace/slug/query-var derived from `WpComponentContext::componentName()` — **no brand literal**; the `tests/Architecture/RoutingBrandLiteralTest` guard fails the build if any routing string literal contains `middag`). A host plugin wires them in its own composition root.

| Surface | Class | WordPress mechanism | Namespacing |
|---|---|---|---|
| **REST** | `Http/Routing/RestRouteRegistrar` | `register_rest_route` (via controllers) | `{component}/{version}` (default `v1`) |
| **ADMIN** | `Admin/AdminRouteRegistrar` | `add_menu_page`/`add_submenu_page` + `Router` dispatch | menu slug `{component}`, submenu `{component}-{suffix}` |
| **PUBLIC** | `Http/Routing/PublicRouteRegistrar` | `add_rewrite_rule` + `query_vars` + `template_redirect` | query var `{component}_route` |

- **REST** — controllers implement `RestControllerInterface`; the registrar hands each the derived namespace on `register()` (call from `rest_api_init`).
- **ADMIN** — registers a menu tree whose pages all render through one `renderApp()` dispatch; unmatched routes fall to an injected callable (the lib forces neither Inertia nor a default page). Controllers resolve from the plugin's own PSR-11 container.
- **PUBLIC** — the third surface, formerly absent by design. Maps pretty URLs to handlers via the rewrite system; `RewriteSupport` is the `@internal` seam over `add_rewrite_rule`/`flush_rewrite_rules`/`get_query_var`. Rewrite flushing is expensive and driven from `PluginLifecycle` activate/deactivate, never from `register()`.

## Contracts bridge

| Framework contract | WordPress implementation |
|---|---|
| BootstrapInterface | WpBootstrap |
| ConfigResolverInterface | WpConfigResolver |
| MaintenanceGateInterface | WpMaintenanceGate |
| HostComponentContextInterface | WpComponentContext |
| ConnectionAdapterInterface | WpdbConnectionAdapter |
| SqlDialectInterface | WpdbSqlDialect (factory via connection) |
| UserContextResolverInterface | WpUserContext |
| TranslatorInterface | WpTranslator |
| FilesystemInterface | WpUploadsFilesystem |
| MailerInterface | WpMailer |
| HookfileLoaderInterface | WpHookfileLoader (extends the framework HookfileLoader) |

## HTTP client mTLS (optional capability)

`Http\Client\HttpClient` wraps `wp_remote_request()` and adds optional client-certificate (mTLS) support via the `certPath`/`certPassword`/`keyPath` args. Because WP_Http exposes no certificate arguments, the certificate is applied through an `http_api_curl` action registered right before and detached right after each request (via the `HookSupport::removeAction` seam). If the active transport never hands the action a cURL handle (non-cURL transport), the request fails loudly instead of silently going out without the client certificate. Consumers that prefer their own transport handling can always register their own `http_api_curl` hook instead.

## Boundary enforcement (Article-I)

- `tests/AdapterPluginIsolationTest.php` — source-scans every `src/` file for non-OSS MIDDAG namespaces, consumer-plugin namespaces, and hard-coded product gold tables. Runs with `composer test`.
- `tests/ClassLoadabilityTest.php` — autoload smoke test over the whole `src/` tree.

## Composer scripts

- `composer check` — style → rector → stan (all analysis/dry-run)
- `composer check:style` — PHP CS Fixer dry-run
- `composer check:rector` — Rector dry-run
- `composer check:stan` — PHPStan
- `composer fix` — auto-fix style + rector
- `composer fix:all` — style → rector → style (re-runs style after rector)
- `composer test` — PHPUnit (includes the boundary and loadability guard tests)
- `composer test:cov` — PHPUnit with coverage (`XDEBUG_MODE=coverage`, text report)
- `composer lint:php82` — PHP 8.2 parse-level lint (`bin/lint-php82.sh`)
- `post-install-cmd` / `post-update-cmd` — register `.githooks` (`commit-msg` enforces Conventional Commits)
