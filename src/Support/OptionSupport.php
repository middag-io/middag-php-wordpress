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

use Middag\WordPress\Config\WpConfigResolver;
use Middag\WordPress\Settings\SettingsRegistrar;

/**
 * Thin wrapper over WordPress's option read API (`get_option`).
 *
 * Isolates option reads behind a small seam so callers such as
 * {@see SettingsRegistrar} and
 * {@see WpConfigResolver} never touch the platform
 * function directly, and stay unit-testable without a live `wp_options` table.
 * Degrades to the supplied default when `get_option` is unavailable (WP-CLI /
 * cron / boot before WordPress is loaded).
 *
 * @internal
 */
final class OptionSupport
{
    /**
     * Read an option value, returning $default when WordPress is unavailable or
     * the option is unset.
     */
    public static function get(string $name, mixed $default = false): mixed
    {
        if (!function_exists('get_option')) {
            return $default;
        }

        return get_option($name, $default);
    }
}
