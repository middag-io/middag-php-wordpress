# CLAUDE.md — middag-io/wordpress

## O que é este pacote

Adapter WordPress OSS (Apache-2.0) para o framework MIDDAG. Carrega apenas toil básico de host: conexão `$wpdb`/dialect, REST base, hooks/cron, config/user context, tradução, Inertia frontend glue, email `wp_mail`, e persistência nativa de posts/users. **Nunca importa namespaces ou pacotes MIDDAG não-OSS** (Article-I; `composer check:boundaries`).

Capacidades governadas MIDDAG (Item/EAV, QueryEngine, schema `middag_*`, Audit/ActivityFeed/ItemRevision/Job, outbox, tenant/organization, rate limit, scopes, Payment/Webhook/WooCommerce/CRM) **não fazem parte deste adapter OSS** — pertencem a camadas MIDDAG não-OSS, fora deste adapter.

- **Depende de** `middag-io/framework`
- **Consumido por:** um plugin WordPress host, via composition root própria

## Estrutura

| Diretorio | Conteudo |
|-----------|----------|
| `src/Bus/` | WpUserContext (UserContextResolverInterface) |
| `src/Config/` | WpConfigResolver (ConfigResolverInterface) |
| `src/Cron/` | CronHandler, CronRegistrar (wp-cron básico) |
| `src/Database/` | WpdbConnectionAdapter (ConnectionAdapterInterface), WpdbSqlDialect |
| `src/Definition/` | PostTypeDefinition, TaxonomyDefinition, CronScheduleDefinition, ShortcodeDefinition + DefinitionRegistrar (registro declarativo no host) |
| `src/Domain/Post/` | PostRepository, PostMetaRepository (wp_posts + wp_postmeta) |
| `src/Domain/User/` | UserRepository, UserMeta |
| `src/Filesystem/` | WpUploadsFilesystem (framework FilesystemInterface → uploads dir, via LocalFilesystem) |
| `src/Hook/` | HookRegistrar + `Contract/HookInterface` |
| `src/Http/` | `Client/{HttpClient,HttpResponse}` (wp_remote_* + mTLS via http_api_curl), `Contract/RestControllerInterface`, `Controller/BaseController`, `Response/RestResponse`, `Routing/{Router,RouteRegistrar}`, `Inertia/{InertiaAdapter,PageContractNormalizer}`, `Middleware/AuthMiddleware` (JWT host auth), `Security/CsrfGuard` |
| `src/Kernel/` | WordPressBootstrap (BootstrapInterface), WpMaintenanceGate, Loader/WordPressHookfileLoader |
| `src/Lifecycle/` | PluginLifecycle |
| `src/Logging/` | ErrorLogLogger (PSR-3 → error_log) |
| `src/Mail/` | WpMailer (framework MailerInterface → wp_mail), EmailSender, EmailTemplate |
| `src/Persistence/` | QueryBuilder (WP_Query/wp_posts) |
| `src/Security/` | CapabilityRegistrar (caps declarativas por role) |
| `src/Settings/` | Framework declarativo: Tab→Section→Field (FieldType c/ sanitizer default), FieldRenderer (escapado), SettingsPageRegistrar sobre SettingDefinition/SettingsRegistrar |
| `src/Translation/` | WpTranslator (TranslatorInterface) |

## Contracts bridge

| Framework Contract | WordPress Implementation |
|---|---|
| BootstrapInterface | WordPressBootstrap |
| ConfigResolverInterface | WpConfigResolver |
| MaintenanceGateInterface | WpMaintenanceGate |
| ConnectionAdapterInterface | WpdbConnectionAdapter |
| SqlDialectInterface | WpdbSqlDialect (factory via connection) |
| UserContextResolverInterface | WpUserContext |
| TranslatorInterface | WpTranslator |
| HookfileLoaderInterface | WordPressHookfileLoader |

## Composer scripts

- `composer check` — boundaries + PHPStan + style + rector (dry-runs)
- `composer check:boundaries` — guard Article-I (sem namespaces MIDDAG não-OSS em src/)
- `composer check:style` — PHP CS Fixer dry-run
- `composer check:rector` — Rector dry-run
- `composer fix` — Auto-fix style + rector
- `composer test` — PHPUnit
