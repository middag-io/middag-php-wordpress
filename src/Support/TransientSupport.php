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

/**
 * Boundary seam over the WordPress Transients API — TTL'd caching persisted in
 * wp_options (or the object cache when a persistent backend is wired).
 * No-ops (returning `false`) outside a WP runtime.
 *
 * @api
 */
final class TransientSupport
{
    /**
     * @return mixed `false` when absent/expired (WP convention)
     */
    public static function get(string $key): mixed
    {
        if (!\function_exists('get_transient')) {
            return false;
        }

        return get_transient($key);
    }

    /**
     * @param int $expirationSeconds 0 = no expiration
     */
    public static function set(string $key, mixed $value, int $expirationSeconds = 0): bool
    {
        if (!\function_exists('set_transient')) {
            return false;
        }

        return set_transient($key, $value, $expirationSeconds);
    }

    public static function delete(string $key): bool
    {
        if (!\function_exists('delete_transient')) {
            return false;
        }

        return delete_transient($key);
    }

    /**
     * Read-through helper: return the cached value or produce, store and
     * return it.
     */
    public static function remember(string $key, int $expirationSeconds, callable $producer): mixed
    {
        $cached = self::get($key);
        if ($cached !== false) {
            return $cached;
        }

        $value = $producer();
        self::set($key, $value, $expirationSeconds);

        return $value;
    }
}
