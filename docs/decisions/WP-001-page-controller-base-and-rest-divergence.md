---
title: 'WordPress HTTP integration'
status: accepted
date: 2026-07-24
last-updated: 2026-11-04
lang: en
domains: [wordpress, http]
deciders: [leadership]
decision: 'Three per-component routing surfaces; page controllers follow the framework lifecycle; REST keeps a WP-native kernel.'
related: [WP-002, WP-003, middag-php-framework:FW-002, middag-php-framework:FW-014, middag-php-framework:FW-015]
supersedes: []
superseded_by: null
enforced_by:
  mdgstan: []
  docs: []
---

# WP-001: WordPress HTTP integration

## Context

WordPress reaches plugin code through three request paths with different lifecycles: the REST API (route registration with a permission callback, over `WP_REST_Request`), wp-admin menu pages, and public pretty URLs through the rewrite system. Several plugins built on this adapter can share one request, so they must not collide on a route namespace, a menu slug or a query var. The framework offers one HTTP lifecycle (PSR-15 kernel, controller base, declarative attributes), and the adapter has to decide how much of it each WordPress path adopts.

## Considered Options

1. Route every path, REST included, through the framework HTTP kernel and its PSR-15 pipeline.
2. One controller base for pages and REST, with REST extending the page base as in the framework and the Moodle adapter.
3. REST only; consumers hand-roll admin pages and the Inertia wiring.
4. Three surfaces (REST, admin, public rewrite), each namespaced by the injected component name. Page controllers extend the framework controller base and run through the framework kernel. REST keeps WordPress's registration model with its own WP-native kernel and shares only the framework's declarative attributes. ← chosen

## Decision

The adapter exposes three routing surfaces and derives every namespace, slug and query var from the component name the consumer injects, never from a literal. Admin pages run through the framework HTTP kernel, so page controllers extend the framework controller base and inherit its middleware pipeline, `#[Auth]` and helpers; the adapter adds only the WordPress login, capability and nonce gates. REST stays on WordPress's own model: a REST controller does not extend the page base, and attribute-routed REST actions are dispatched by a WP-native kernel that reads the same `#[Route]`, `#[Auth]` and `#[Middleware]` attributes but answers with WordPress REST responses and errors. The public surface hands a matched route to the consumer's handler.

## Consequences

- (+) Two plugins in one request expose disjoint REST roots, menus and query vars.
- (+) Controllers declare routes, auth and middleware with one attribute vocabulary on every surface.
- (+) A REST denial or failure stays inside the WordPress REST envelope instead of halting the request.
- (+) Page controllers keep parity with the framework and Moodle bases, minus Moodle-only concerns.
- (−) One `#[Middleware]` attribute maps to two middleware contracts: the framework's on admin pages and a WP-REST-native one on REST.
- (−) The REST base is not container-aware. Composing a framework controller role into it is possible but deferred until a consumer needs it.
- (−) Two REST registrars, imperative and attribute-driven, coexist while consumers migrate.
- (−) On the REST surface a capability list in `#[Auth]` is enforced as an administrator check, not capability by capability (`src/Http/WpRestKernel.php:88`).

## Enforcement

| Decision clause | Verification | State |
|---|---|---|
| Every surface is namespaced by the component name, with no brand literal | test `tests/Architecture/RoutingBrandLiteralTest.php` | coded |
| Page controllers extend the framework controller base and map the WordPress capability gate | test `tests/Http/Controller/AbstractWpControllerTest.php` | coded |
| Admin pages run through the framework kernel with the WordPress nonce gate | test `tests/Http/WpHttpKernelTest.php` | coded |
| REST keeps a WP-native kernel that reads the shared attributes | test `tests/Http/WpRestKernelTest.php` | coded |
| Surfaces, registrars and dispatch | doc `docs-middag-dev:docs/framework/reference/adapters/wordpress/routing-surfaces.md` | coded |
| Controller bases and why they do not share a parent | doc `docs-middag-dev:docs/framework/reference/adapters/wordpress/controller-bases.md` | coded |
