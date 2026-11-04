---
title: 'Injected per-plugin identity, neutral defaults, no mutable static state'
status: accepted
date: 2026-07-31
last-updated: 2026-11-04
lang: en
domains: [wordpress, config, kernel]
deciders: [leadership]
decision: 'Each plugin injects its own identity and prefixes; adapter defaults stay neutral; no static property in the adapter.'
related: [WP-001, WP-003, middag-php-framework:FW-015, middag-php-framework:FW-016, middag-php-moodle:MDL-001]
supersedes: []
superseded_by: null
enforced_by:
  mdgstan: []
  docs: []
---

# WP-002: Injected per-plugin identity, neutral defaults, no mutable static state

## Context

Several WordPress plugins built on this adapter can run in one PHP process and one request, and some of them are not MIDDAG products. Anything process-wide in the adapter (a static property, the framework's single host-identity slot, a default prefix) is then shared by all of them and the last writer wins. A default that carries a product name also hands that name to every consumer that configures nothing.

## Considered Options

1. A static kernel singleton and the framework's process-wide host-identity slot, as in the Moodle adapter.
2. Product-named defaults (`MIDDAG_` / `middag_`), documented as "override in production".
3. No defaults at all: every identity and prefix argument is required.
4. Identity, prefixes and the kernel singleton belong to each consumer: the singleton lives on the consumer's kernel subclass, identity is an instance injected through the consumer's container, and the adapter's defaults are neutral library markers. ← chosen

## Decision

The adapter holds no static properties. The kernel is abstract and the consumer's subclass owns the singleton, so each plugin has its own kernel under its own namespace. Component identity (name, asset version, base path) is a value the consumer builds and injects through its own container into every service that needs it; WordPress consumers never use the framework's process-wide host-identity slot. Every adapter default is neutral: the config resolver defaults to `MIDDAGLIB_` / `middaglib_`, a marker meaning "this library, not configured", and every per-plugin namespace derives from the injected component name. Each product, MIDDAG's included, passes its real prefixes in its own composition root.

This is the deliberate opposite of the Moodle adapter, whose kernel keeps a static singleton (MDL-001): Moodle loads one component per request and its `lib.php` callbacks, hooks and scheduled tasks cannot receive injected services, while WordPress hosts many plugins per request and each one boots its own container.

## Consequences

- (+) Plugins in one request never share a kernel, identity, prefix, schedule or nonce action.
- (+) A third-party consumer that configures nothing inherits no product's names, and an unconfigured key is recognisable as such.
- (+) Per-plugin state is visible in constructors and containers instead of hidden in globals.
- (−) Every consumer writes a kernel subclass with its own singleton storage and registers its own config prefixes.
- (−) A consumer that forgets to register its prefixes reads library-marked keys, with no error or log.
- (−) Some defaults still carry the `middag` name: the translator's default text domain (`src/Translation/WpTranslator.php:32`), the host event hook prefix (`src/Bus/WpHostEventBridge.php:44`), and the hookfile filter and file name (`src/Runtime/Loader/WpHookfileLoader.php:40,53`). The file name has no override.
- (−) The Inertia adapter still writes the framework's static Inertia configuration on each render, which is safe only because wp-admin serves one component per request.

## Enforcement

| Decision clause | Verification | State |
|---|---|---|
| No static property under `src/` | test `tests/Architecture/NoStaticMutableStateTest.php` | coded |
| The kernel holds no singleton; the consumer subclass owns it and boot is per kernel | test `tests/Runtime/KernelTest.php` | coded |
| Config prefixes default to the neutral marker and are injectable | test `tests/Config/WpConfigResolverTest.php` | coded |
| Routing namespaces derive from the injected component name | test `tests/Architecture/RoutingBrandLiteralTest.php` | coded |
| No product-named default anywhere in `src/` | guard test | planned |
| Kernel, identity injection, config resolution and defaults | doc `docs-middag-dev:docs/framework/reference/adapters/wordpress/configuration.md` | coded |
