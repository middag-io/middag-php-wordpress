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

use Middag\Framework\Http\HttpKernel;

/**
 * Thin wrapper over WordPress's nonce (CSRF) functions.
 *
 * WordPress admin pages dispatch through WP/admin hooks, not the framework's
 * PSR-15 {@see HttpKernel}, so they cannot reuse the
 * framework's `VerifyCsrfMiddleware`. The native mechanism is the WordPress
 * nonce; this seam isolates `wp_create_nonce`/`wp_verify_nonce` so the platform
 * coupling lives in one place. Both methods degrade safely when the WordPress
 * functions are absent (CLI / cron / boot before the pluggable API loads):
 * creation yields an empty string, verification yields false.
 *
 * @internal
 */
final class SecuritySupport
{
    /**
     * Create a WordPress nonce for the given action, or an empty string when
     * the nonce API is unavailable.
     */
    public static function createNonce(string $action): string
    {
        if (!function_exists('wp_create_nonce')) {
            return '';
        }

        return wp_create_nonce($action);
    }

    /**
     * Verify a nonce against an action. Returns false for an invalid/expired
     * nonce and when the nonce API is unavailable.
     *
     * `wp_verify_nonce()` returns 1|2 on success (token age tier) or false on
     * failure; this normalizes that to a strict boolean.
     */
    public static function verifyNonce(?string $nonce, string $action): bool
    {
        if ($nonce === null || $nonce === '') {
            return false;
        }

        if (!function_exists('wp_verify_nonce')) {
            return false;
        }

        return wp_verify_nonce($nonce, $action) !== false;
    }
}
