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

use Middag\WordPress\Http\Routing\PublicRouteRegistrar;

/**
 * Thin wrapper over the WordPress rewrite API used by the public routing
 * surface: registering rewrite rules, flushing them, and reading a query var.
 *
 * Isolates `add_rewrite_rule`/`flush_rewrite_rules`/`get_query_var` behind one
 * seam so {@see PublicRouteRegistrar} never
 * touches the globals directly. Each method degrades to a safe no-op / default
 * when the rewrite API is absent (CLI / cron / boot before the rewrite API
 * loads), matching {@see CronSupport} and {@see HookSupport}.
 *
 * `template_redirect` (action) and `query_vars` (filter) are NOT wrapped here —
 * they go through {@see HookSupport::addAction()} / {@see HookSupport::addFilter()}.
 *
 * @internal
 */
final class RewriteSupport
{
    /**
     * Register a rewrite rule mapping a URL regex to an internal query string.
     * No-op when the rewrite API is unavailable.
     *
     * @param 'bottom'|'top' $after
     */
    public static function addRule(string $regex, string $query, string $after = 'top'): void
    {
        if (!function_exists('add_rewrite_rule')) {
            return;
        }

        add_rewrite_rule($regex, $query, $after);
    }

    /**
     * Flush (recompile + persist) the rewrite rules. Expensive — call only on
     * plugin activation/deactivation, never on a normal request. No-op when the
     * rewrite API is unavailable.
     */
    public static function flush(bool $hard = true): void
    {
        if (!function_exists('flush_rewrite_rules')) {
            return;
        }

        flush_rewrite_rules($hard);
    }

    /**
     * Read a registered query var, returning $default when it is absent or the
     * rewrite API is unavailable.
     */
    public static function queryVar(string $var, mixed $default = ''): mixed
    {
        if (!function_exists('get_query_var')) {
            return $default;
        }

        return get_query_var($var, $default);
    }
}
