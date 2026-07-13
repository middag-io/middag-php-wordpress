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

use Middag\WordPress\Security\Enum\CapabilityInterface;
use Middag\WordPress\Security\Enum\NormalizesCapability;
use Middag\WordPress\Security\Enum\WooCommerceCapability;
use Middag\WordPress\Security\Enum\WpCapability;
use WP_User;

/**
 * Thin wrapper over WordPress's current-user / capability functions.
 *
 * Isolates `get_current_user_id`, `current_user_can`, and `wp_get_current_user`
 * so the adapter resolves identity and authorization through one seam. Degrades
 * to "anonymous / not permitted" (0, false, null) when the user API is absent,
 * which matches WP-CLI and cron contexts.
 *
 * @internal
 */
final class UserSupport
{
    use NormalizesCapability;

    /**
     * Current logged-in user ID, or 0 when anonymous or the user API is absent.
     */
    public static function currentUserId(): int
    {
        if (!function_exists('get_current_user_id')) {
            return 0;
        }

        return get_current_user_id();
    }

    /**
     * Whether the current user holds a capability. Accepts a raw string or a
     * typed {@see CapabilityInterface} ({@see WpCapability}
     * / {@see WooCommerceCapability}). Returns
     * false when the capability API is unavailable.
     */
    public static function currentUserCan(CapabilityInterface|string $capability, mixed ...$args): bool
    {
        if (!function_exists('current_user_can')) {
            return false;
        }

        return current_user_can(self::capabilityString($capability), ...$args);
    }

    /**
     * Current user object, or null when anonymous or the user API is absent.
     */
    public static function currentUser(): ?WP_User
    {
        if (!function_exists('wp_get_current_user')) {
            return null;
        }

        $user = wp_get_current_user();

        return $user->ID > 0 ? $user : null;
    }
}
