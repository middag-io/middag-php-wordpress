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
 * Boundary seam over the WordPress Object Cache API (`wp_cache_*`) —
 * request-scoped by default, persistent when a backend (Redis, Memcached)
 * is dropped in. Mirrors the moodle adapter's CacheSupport. No-ops outside
 * a WP runtime.
 *
 * @api
 */
final class CacheSupport
{
    public static function get(string $key, string $group = '', ?bool &$found = null): mixed
    {
        if (!\function_exists('wp_cache_get')) {
            // @codeCoverageIgnoreStart
            return $found = false;
            // @codeCoverageIgnoreEnd
        }

        return wp_cache_get($key, $group, false, $found);
    }

    /**
     * @param int $expirationSeconds 0 = no expiration
     */
    public static function set(string $key, mixed $value, string $group = '', int $expirationSeconds = 0): bool
    {
        if (!\function_exists('wp_cache_set')) {
            // @codeCoverageIgnoreStart
            return false;
            // @codeCoverageIgnoreEnd
        }

        return wp_cache_set($key, $value, $group, $expirationSeconds);
    }

    public static function delete(string $key, string $group = ''): bool
    {
        if (!\function_exists('wp_cache_delete')) {
            // @codeCoverageIgnoreStart
            return false;
            // @codeCoverageIgnoreEnd
        }

        return wp_cache_delete($key, $group);
    }

    public static function flush(): bool
    {
        if (!\function_exists('wp_cache_flush')) {
            // @codeCoverageIgnoreStart
            return false;
            // @codeCoverageIgnoreEnd
        }

        return wp_cache_flush();
    }
}
