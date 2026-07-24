---
id: WP-001
title: 'Page-Controller Base and REST Divergence'
status: accepted
date: 2026-07-24
lang: en
domains: [wordpress, http]
deciders: ['Michael Meneses']
related: []
supersedes: []
superseded_by: null
enforced_by:
  mdgstan: []
  docs: []
decision: 'The WordPress adapter provides a page-controller base, `Http\Controller\AbstractWpController`, that extends the framework `AbstractController` and adds Inertia rendering plus a WordPress login/capability gate (with the instance id mapped onto the meta-capability object id). The REST base, `AbstractWpRestController`, stays on its own `RestControllerInterface` and does NOT share an inheritance chain with the page base, because the WordPress REST lifecycle does not match the kernel request → handle() cycle.'
---

# WP-001: Page-Controller Base and REST Divergence

## Context

The adapter shipped only the REST controller stack (`RestControllerInterface` + `AbstractWpRestController`) and had no page-controller base, unlike the framework and the Moodle adapter. A consumer rendering Inertia pages had nothing to extend and had to call `InertiaAdapter` by hand and wire container/auth/render itself (wordpress#39). `AbstractWpController` has never existed in this repository — this is a gap to fill, not a regression.

The framework contract `Middag\Framework\Http\Contract\ControllerInterface` is host-neutral (no Moodle/WordPress types); host-specific concerns live only inside each adapter's own base. So a WordPress page base can implement it cleanly. This ADR also records the question wordpress#39 raised: should the REST base relate to the shared contract too, for parity with framework/Moodle (`AbstractApiController extends AbstractController` in both)?

De/para of controller bases across the ecosystem at the time of this decision:

| Layer | Page-controller base | REST/API base | REST ↔ Page |
|---|---|---|---|
| Framework | `AbstractController implements ControllerInterface` | `AbstractApiController extends AbstractController` | REST extends page |
| Moodle adapter | `AbstractController implements MoodleControllerInterface` (extends `ControllerInterface` + `CapabilityRequirementAwareInterface`) | `AbstractApiController extends AbstractController` | REST extends page |
| WordPress adapter | **absent → this decision adds `AbstractWpController`** | `AbstractWpRestController implements RestControllerInterface` (standalone) | REST isolated |
| Core | N/A (domain lib, no HTTP) | N/A | — |

## Considered Options

1. **No page base; consumers keep calling `InertiaAdapter` statically.** Rejected: that is the gap — no parity, no auth/render wiring, every consumer reinvents it.
2. **One base for both page and REST** (force `AbstractWpRestController` to relate to `ControllerInterface`, mirroring framework/Moodle). Rejected: the WordPress REST lifecycle is a registration model (`register_rest_route` + a `permission_callback` per route, dispatched over `WP_REST_Request`, returning `WP_Error`). There is no single `handle()` / `setRequest(Request)` cycle, and a permission failure must be a `WP_Error`, not a halt. A shared base would inherit dead lifecycle methods and a page-shaped `setRequireCapabilities()` whose enforcement is wrong for REST.
3. **Page base extending the framework `AbstractController`; REST stays isolated** ← chosen. `AbstractWpController extends Framework\AbstractController` and adds `render()` (via the container-bound `InertiaAdapter`) plus the WordPress auth gate. `AbstractWpRestController` keeps its own `RestControllerInterface`.

## Decision

`AbstractWpController` (option 3) is the Inertia page base. It inherits the full `ControllerInterface` surface and the framework helpers (`redirect`/`redirectToRoute`/`redirectBack`/`flash`/`response`/`getService`) and adds:

- `render(string $component, array $props)` — delegates to the per-request `InertiaAdapter` from the container.
- `setRequireLogin()` — `is_user_logged_in()` / `auth_redirect()`.
- `setRequireCapabilities($capabilities, $context, $instanceId)` — `current_user_can()` per capability. WordPress has no Moodle-style context/instance model, so: `$instanceId > 0` is passed as the meta-capability object id (`current_user_can($cap, $instanceId)`, resolved by `map_meta_cap`); `$instanceId === 0` is a global check; `$context` (string) has no native equivalent and is advisory only, accepted for cross-adapter signature parity. A denial halts with `wp_die(403)`.

It carries no Moodle-only concerns (sesskey, `context` objects, page layout/heading/navbar).

The REST base stays independent (option 2 rejected). The framework separately segregated its monolithic `ControllerInterface` into role interfaces (framework FW-014: `ContainerAwareInterface` / `RequestHandlingInterface` / `AuthorizationAwareInterface`), which makes it *possible* for `AbstractWpRestController` to later compose a single role (e.g. `ContainerAwareInterface`) without faking the lifecycle. That retrofit is **deferred**: WP REST controllers receive their dependencies by constructor injection today, no consumer needs `setContainer`, and this adapter does not add seams without a real consumer.

## Consequences

- A page controller `extends AbstractWpController` and calls `$this->render('Component', $props)` instead of static `InertiaAdapter::render(...)`.
- Surface parity with Moodle's `AbstractController` holds minus the Moodle-only methods; the auth gate is mapped onto native WordPress functions.
- No static mutable state is introduced (the adapter's `NoStaticMutableStateTest` guard holds), and no non-OSS namespace is imported (Article-I boundary holds).
- If a future consumer needs a container-aware REST base, the framework role interfaces are already in place to compose against — a non-breaking retrofit.
