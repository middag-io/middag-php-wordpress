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
 * Boundary seam over the WordPress Shortcode API. No-ops outside a WP runtime.
 *
 * @api
 */
final class ShortcodeSupport
{
    public static function add(string $tag, callable $callback): void
    {
        if (!\function_exists('add_shortcode')) {
            return;
        }

        add_shortcode($tag, $callback);
    }

    public static function remove(string $tag): void
    {
        if (!\function_exists('remove_shortcode')) {
            return;
        }

        remove_shortcode($tag);
    }

    public static function render(string $content): string
    {
        if (!\function_exists('do_shortcode')) {
            return $content;
        }

        return do_shortcode($content);
    }
}
