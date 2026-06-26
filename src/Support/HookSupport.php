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
 * Thin wrapper over WordPress's hook/filter functions.
 *
 * The adapter registers behavior through this seam instead of calling
 * `add_action`/`add_filter`/`apply_filters` directly, so the platform coupling
 * lives in one place. Every method is a no-op (or returns the unfiltered value)
 * when the WordPress function is absent, which keeps boot-time and CLI paths
 * predictable before the hooks API is loaded.
 *
 * @internal
 */
final class HookSupport
{
    /**
     * Register an action callback.
     */
    public static function addAction(string $hook, callable $callback, int $priority = 10, int $acceptedArgs = 1): void
    {
        if (!function_exists('add_action')) {
            return;
        }

        add_action($hook, $callback, $priority, $acceptedArgs);
    }

    /**
     * Register a filter callback.
     */
    public static function addFilter(string $hook, callable $callback, int $priority = 10, int $acceptedArgs = 1): void
    {
        if (!function_exists('add_filter')) {
            return;
        }

        add_filter($hook, $callback, $priority, $acceptedArgs);
    }

    /**
     * Apply registered filters to a value, returning it unchanged when the
     * filters API is unavailable.
     */
    public static function applyFilters(string $hook, mixed $value, mixed ...$args): mixed
    {
        if (!function_exists('apply_filters')) {
            return $value;
        }

        return apply_filters($hook, $value, ...$args);
    }
}
