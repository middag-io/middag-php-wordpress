---
title: 'WordPress is an adapter of the framework ports'
status: accepted
date: 2026-11-04
last-updated: 2026-11-04
lang: en
domains: [wordpress, adapters, portability]
deciders: [leadership]
decision: 'This OSS package implements the framework ports with native WordPress APIs, native first; governed concerns stay out.'
related: [WP-001, WP-002, middag-php-framework:FW-011, middag-php-framework:FW-015, middag-php-moodle:MDL-001]
supersedes: []
superseded_by: null
enforced_by:
  mdgstan: []
  docs: []
---

# WP-003: WordPress is an adapter of the framework ports

## Context

The framework reaches a host only through ports, each with an OSS default (FW-015). WordPress already has its own mechanism for much of what those ports describe: action and filter hooks, WP-Cron and Action Scheduler, `$wpdb`, options, `wp_mail`, the uploads directory, nonces and capabilities. This package has to decide how it implements the ports on WordPress, and what it leaves out so that any WordPress plugin, not only a MIDDAG product, can use it.

## Considered Options

1. A WordPress subtree inside the framework, guarded by a whitelist of host calls.
2. An adapter that runs the framework's own in-memory mechanisms inside WordPress (for example host events through the framework hook manager) and ignores the native APIs.
3. An adapter that also ships product infrastructure: token authentication, governed domain concerns and a ready-made composition root.
4. A separate OSS package that implements the framework ports with native WordPress APIs, native first, ships no composition root and leaves token authentication and governed concerns to proprietary packages downstream. ← chosen

## Decision

`middag-io/wordpress` is a separate OSS adapter package and the only place where WordPress APIs meet framework code. It implements framework ports with WordPress's own mechanisms whenever WordPress has one: a host event becomes a WordPress action that any plugin or theme can hear with `add_action()`, async jobs go to Action Scheduler when it is present and to WP-Cron otherwise, and the database port wraps `$wpdb`. Optional host features such as WooCommerce are probed at runtime and never required. The package ships no composition root: the consumer plugin builds the container and decides what to bind (WP-002). Request authentication is a seam with a WordPress-session default; bearer tokens and every governed concern (domain storage, audit, outbox, tenancy, payments and the like) live in proprietary packages downstream, which build on the same ports.

## Consequences

- (+) Sites, themes and third-party plugins integrate through native WordPress hooks without importing MIDDAG code.
- (+) Jobs use the host's own scheduler, with Action Scheduler's persistence and retries when it is installed.
- (+) Any WordPress plugin can use the package, and it can be tested without a product.
- (−) Host events do not pass through the framework hook manager, so the WordPress and Moodle adapters deliver them differently.
- (−) Without Action Scheduler, jobs run on WP-Cron: traffic-driven, with no retry and no claiming.
- (−) A token-authenticated API needs a consumer-supplied authenticator.
- (−) `composer.json` still requires `firebase/php-jwt`, although no file under `src/` may use it.

## Enforcement

| Decision clause | Verification | State |
|---|---|---|
| No proprietary MIDDAG or consumer-plugin namespace and no product table in `src/` | test `tests/AdapterPluginIsolationTest.php` | coded |
| No bundled token authentication | test `tests/Architecture/NoBundledTokenAuthTest.php` | coded |
| The baseline bootstrap binds the framework ports to WordPress implementations | test `tests/Runtime/WpBootstrapTest.php` | coded |
| Host events are native WordPress actions | test `tests/Bus/WpHostEventBridgeTest.php` | coded |
| Port-by-host matrix | doc `docs-middag-dev:docs/framework/reference/bridge-contracts.md` | coded |
| Native bridges, Support seams, cron and job drivers, WooCommerce, `$wpdb` | doc `docs-middag-dev:docs/framework/reference/adapters/wordpress/index.md` | coded |
| No unused token dependency in `composer.json` | dependency check | planned |
