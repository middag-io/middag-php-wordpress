# BACKLOG — middag-io/wordpress

**Blocked work only.** This file holds items that **cannot be done in the
normal development flow** — they need infrastructure or a decision that does not
exist yet. Anything doable in the flow is done, not parked here. Relocated from
the monorepo quality-gate tracker so the tracker can be retired.

Suite is green (676 tests) at **~92% line coverage**. Every remaining sub-80%
class is blocked for one of the two reasons below.

---

## Blocked: coverage of the `function_exists` no-op guards (needs new test infra)

The thin static seams over WordPress functions each have one uncovered branch:
the `\function_exists('wp_*') → return …;` no-op guard (the path taken when the
host WordPress function is absent). The suite loads the WP function stubs
**globally** (`tests/stubs/wp-stubs.php`), so the function-absent branch can
never be reached in-process.

Covering it is **blocked on test infrastructure we don't have**: a per-function
stub-skip under `#[RunInSeparateProcess]`, or a `runkit`/`uopz` extension to
undefine the stub. It is a harness capability, not a missing assertion.

Affected: `Support/{PostTypeSupport, ShortcodeSupport, CronSupport,
LifecycleSupport, MetaSupport, OptionSupport, PathSupport, PrivacySupport,
SettingsSupport, AssetSupport, UserSupport, SecuritySupport, CacheSupport,
HookSupport}`.

## Blocked: coverage of the terminating `exit` paths (needs a testable seam)

Two request-terminating paths end in `exit`, so PHPUnit cannot drive them and
assert afterwards (even under process isolation the `exit` ends the child before
the result is recorded). The source docblocks already flag these as the
intentionally-untested exit paths:

- `Http/Inertia/InertiaAdapter::sendJson()` and `::location()` (headers + `echo`
  + `exit`; plus the private `isPartialReload()`/`getPartialData()` reachable
  only through `sendJson()`).
- `Http/Security/CsrfGuard::reject()` (403 envelope + `exit`).

Unblocking would require refactoring the terminate step behind an injectable
seam (e.g. a `Terminator` callable) — a source change, out of scope for a
coverage pass.

## Blocked on a consumer: R1 feature roadmap (was QG-WP-05)

These WordPress capabilities have **no consumer and therefore no code**. They
cannot be built in this flow — each needs a real host-plugin requirement/spec
first, which does not exist yet:

- `WooCommerce/` integration
- OAuth2 + JWT in `Http/Client`
- Inbound webhook handling
- `Database/Schema` `dbDelta` migrations
- Generic EAV
- `Admin/` screens
- `Output/` + rate limiting + host event bridge
- Rewrite rules, Blocks (Gutenberg), Site Health, Media library, Multisite
- `AssetSupport` register/localize/inline enqueue helpers
- `WP_Query` auditing
- PSR-16 transients cache

Non-target-by-design surfaces (widgets / `admin-ajax.php`) are a scope decision,
documented in `CLAUDE.md` — not backlog.
