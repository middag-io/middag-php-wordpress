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
 * Boundary seam over the generic WordPress Metadata API (`get_metadata()` and
 * friends) — covers every meta type (`post`, `user`, `term`, `comment`) with
 * one surface. The Domain repositories keep their typed post/user helpers;
 * this is the escape hatch for the rest. No-ops outside a WP runtime.
 *
 * @api
 */
final class MetaSupport
{
    /**
     * @param 'comment'|'post'|'term'|'user' $type
     */
    public static function get(string $type, int $objectId, string $key, bool $single = true): mixed
    {
        if (!\function_exists('get_metadata')) {
            return $single ? '' : [];
        }

        return get_metadata($type, $objectId, $key, $single);
    }

    /**
     * @param 'comment'|'post'|'term'|'user' $type
     */
    public static function update(string $type, int $objectId, string $key, mixed $value): bool
    {
        if (!\function_exists('update_metadata')) {
            return false;
        }

        return update_metadata($type, $objectId, $key, $value);
    }

    /**
     * @param 'comment'|'post'|'term'|'user' $type
     */
    public static function delete(string $type, int $objectId, string $key, mixed $value = ''): bool
    {
        if (!\function_exists('delete_metadata')) {
            return false;
        }

        return delete_metadata($type, $objectId, $key, $value);
    }
}
