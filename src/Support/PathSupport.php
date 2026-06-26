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
 * Thin wrapper over WordPress's theme/path lookups.
 *
 * Currently isolates `get_stylesheet_directory` for the email template theme
 * fallback. Returns an empty string when the function is unavailable, which the
 * callers already treat as "no candidate path".
 *
 * @internal
 */
final class PathSupport
{
    /**
     * Absolute filesystem path of the active (child) theme directory, or an
     * empty string when the theme API is unavailable.
     */
    public static function stylesheetDirectory(): string
    {
        if (!function_exists('get_stylesheet_directory')) {
            return '';
        }

        return get_stylesheet_directory();
    }
}
