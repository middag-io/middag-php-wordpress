# CLAUDE.md — middag-io/wordpress

## O que é este pacote

Adapter WordPress OSS (Apache-2.0) para o framework MIDDAG. Carrega apenas toil básico de host: conexão `$wpdb`/dialect, REST base, hooks/cron, config/user context, tradução, Inertia frontend glue, email `wp_mail`, e persistência nativa de posts/users. **Nunca importa namespaces ou pacotes MIDDAG não-OSS** (Article-I; `composer check:boundaries`).

Capacidades governadas MIDDAG (Item/EAV, QueryEngine, schema `middag_*`, Audit/ActivityFeed/ItemRevision/Job, outbox, tenant/organization, rate limit, scopes, Payment/Webhook/WooCommerce/CRM) **não fazem parte deste adapter OSS** — pertencem a camadas MIDDAG não-OSS, fora deste adapter.

- **Depende de** `middag-io/framework`
- **Consumido por:** um plugin WordPress host, via composition root própria

## Estrutura

| Diretorio | Conteudo |
|-----------|----------|
| `src/Config/` | WpConfigResolver (ConfigResolverInterface) |
| `src/Cron/` | CronHandler, CronRegistrar (wp-cron básico) |
| `src/Database/` | WpdbConnectionAdapter (ConnectionAdapterInterface), WpdbSqlDialect |
| `src/Email/` | EmailSender, EmailTemplate (wp_mail glue) |
| `src/Frontend/` | Container, InertiaAdapter, Router, PageContractNormalizer |
| `src/Hook/` | HookInterface, HookRegistrar |
| `src/Http/` | BaseController (auth-only), RestControllerInterface, RestResponse, RouteRegistrar |
| `src/Infrastructure/Bus/` | WpUserContext (UserContextResolverInterface) |
| `src/Kernel/` | WordPressBootstrap (BootstrapInterface), WpMaintenanceGate, Loader/WordPressHookfileLoader |
| `src/Middleware/` | AuthMiddleware (JWT host auth) |
| `src/Persistence/` | QueryBuilder (WP_Query/wp_posts) |
| `src/Persistence/Post/` | PostRepository, PostMetaRepository (wp_posts + wp_postmeta) |
| `src/Translation/` | WpTranslator (TranslatorInterface) |
| `src/User/` | UserRepository, UserMeta |

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
