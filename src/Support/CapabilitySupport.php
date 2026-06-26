<?php

declare(strict_types=1);

/**
 * middag-io/wordpress — MIDDAG WordPress adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\WordPress\Support;

use WP_Role;

/**
 * Thin wrapper over WordPress's roles/capabilities write-side functions.
 *
 * Isolates `get_role`/`add_role`/`remove_role` and the per-role
 * `WP_Role::add_cap`/`remove_cap` mutations behind one seam, so plugin
 * activation/lifecycle code installs capabilities and role grants through a
 * single place instead of touching the WordPress roles API directly. The
 * *read* side stays in {@see UserSupport::currentUserCan()}; {@see self::userCan()}
 * is a thin convenience that forwards to it so grant/install code can co-locate
 * a permission check without reaching across seams.
 *
 * Caller supplies every role and capability name — this class hard-codes none,
 * keeping the adapter free of product-specific authorization vocabulary.
 *
 * Every method degrades to a safe "nothing happened" result (false / no-op /
 * null) when the roles API is absent, which matches boot-before-pluggable,
 * WP-CLI, and cron contexts.
 *
 * @internal
 */
final class CapabilitySupport
{
    /**
     * Grant a capability to a role. Returns false when the role does not exist
     * or the roles API is unavailable; true once the grant has been applied.
     *
     * Mutations made here persist for the role only when WordPress is loaded —
     * the underlying `WP_Role::add_cap()` writes to the roles option.
     */
    public static function addCap(string $role, string $capability, bool $grant = true): bool
    {
        $roleObject = self::role($role);

        if (!$roleObject instanceof WP_Role) {
            return false;
        }

        $roleObject->add_cap($capability, $grant);

        return true;
    }

    /**
     * Remove a capability from a role. Returns false when the role does not
     * exist or the roles API is unavailable; true once the capability has been
     * removed.
     */
    public static function removeCap(string $role, string $capability): bool
    {
        $roleObject = self::role($role);

        if (!$roleObject instanceof WP_Role) {
            return false;
        }

        $roleObject->remove_cap($capability);

        return true;
    }

    /**
     * Register a role with an optional set of capabilities, returning the
     * created {@see WP_Role}. Returns null when the role already exists (WP
     * declines to recreate it) or the roles API is unavailable.
     *
     * `add_role()` returns the new role on success and nothing when the role
     * already exists; this re-reads via `get_role()` so the return type is a
     * predictable `?WP_Role` regardless of that quirk.
     *
     * @param array<int, string>|array<string, bool> $capabilities capability
     *                                                             names to grant; either a list of names
     *                                                             or a name => granted map
     */
    public static function addRole(string $role, string $displayName, array $capabilities = []): ?WP_Role
    {
        if (!function_exists('add_role')) {
            return null;
        }

        if (self::roleExists($role)) {
            return null;
        }

        add_role($role, $displayName, $capabilities);

        return self::role($role);
    }

    /**
     * Remove a role. No-op when the role is absent or the roles API is
     * unavailable.
     */
    public static function removeRole(string $role): void
    {
        if (!function_exists('remove_role')) {
            return;
        }

        remove_role($role);
    }

    /**
     * Whether a role is currently registered. Returns false when the roles API
     * is unavailable.
     */
    public static function roleExists(string $role): bool
    {
        return self::role($role) instanceof WP_Role;
    }

    /**
     * Read-side convenience: whether the current user holds a capability.
     *
     * Delegates to {@see UserSupport::currentUserCan()} (the canonical read
     * seam) so lifecycle/grant code can verify a permission without importing a
     * second seam. Returns false when the capability API is unavailable.
     */
    public static function userCan(string $capability, mixed ...$args): bool
    {
        return UserSupport::currentUserCan($capability, ...$args);
    }

    /**
     * Resolve a role object, or null when the role is unknown or the roles API
     * is unavailable.
     */
    private static function role(string $role): ?WP_Role
    {
        if (!function_exists('get_role')) {
            return null;
        }

        $resolved = get_role($role);

        return $resolved instanceof WP_Role ? $resolved : null;
    }
}
