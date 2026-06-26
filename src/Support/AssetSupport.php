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
 * Thin wrapper over WordPress's asset enqueue functions.
 *
 * Isolates `wp_enqueue_script`/`wp_enqueue_style` so the Inertia adapter's
 * enqueue seam routes through one place. No-op when the asset API is absent
 * (e.g. before `wp_enqueue_scripts` has run, or outside a web request).
 *
 * @internal
 */
final class AssetSupport
{
    /**
     * Enqueue a script handle.
     *
     * @param array<int, string> $deps
     */
    public static function enqueueScript(
        string $handle,
        string $src,
        array $deps = [],
        bool|string|null $version = false,
        bool $inFooter = true,
    ): void {
        if (!function_exists('wp_enqueue_script')) {
            return;
        }

        wp_enqueue_script($handle, $src, $deps, $version, $inFooter);
    }

    /**
     * Enqueue a stylesheet handle.
     *
     * @param array<int, string> $deps
     */
    public static function enqueueStyle(
        string $handle,
        string $src,
        array $deps = [],
        bool|string|null $version = false,
        string $media = 'all',
    ): void {
        if (!function_exists('wp_enqueue_style')) {
            return;
        }

        wp_enqueue_style($handle, $src, $deps, $version, $media);
    }
}
