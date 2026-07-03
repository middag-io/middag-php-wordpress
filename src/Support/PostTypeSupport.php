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
 * Boundary seam over the WordPress content-model registration API
 * (`register_post_type()` / `register_taxonomy()`). No-ops outside a WP
 * runtime so library code stays testable.
 *
 * @api
 */
final class PostTypeSupport
{
    /**
     * @param array<string, mixed> $args
     */
    public static function registerPostType(string $slug, array $args = []): void
    {
        if (!\function_exists('register_post_type')) {
            return;
        }

        register_post_type($slug, $args);
    }

    /**
     * @param list<string>|string  $objectTypes
     * @param array<string, mixed> $args
     */
    public static function registerTaxonomy(string $slug, array|string $objectTypes, array $args = []): void
    {
        if (!\function_exists('register_taxonomy')) {
            return;
        }

        register_taxonomy($slug, $objectTypes, $args);
    }
}
