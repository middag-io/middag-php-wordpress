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
 * Thin wrapper over WordPress's plugin lifecycle hook functions.
 *
 * Isolates `register_activation_hook`/`register_deactivation_hook` behind a
 * small seam so the lifecycle registrar never calls them directly. Both
 * functions key their callback on the plugin's main file path. Each method is a
 * no-op when the plugin API is absent (CLI / cron / boot before the plugin
 * functions load), which keeps lifecycle wiring predictable everywhere.
 *
 * @internal
 */
final class LifecycleSupport
{
    /**
     * Register a callback to run when the plugin (identified by its main file)
     * is activated. No-op when the plugin API is unavailable.
     */
    public static function registerActivation(string $pluginFile, callable $callback): void
    {
        if (!function_exists('register_activation_hook')) {
            return;
        }

        register_activation_hook($pluginFile, $callback);
    }

    /**
     * Register a callback to run when the plugin (identified by its main file)
     * is deactivated. No-op when the plugin API is unavailable.
     */
    public static function registerDeactivation(string $pluginFile, callable $callback): void
    {
        if (!function_exists('register_deactivation_hook')) {
            return;
        }

        register_deactivation_hook($pluginFile, $callback);
    }
}
