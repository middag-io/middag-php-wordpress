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
 * Thin wrapper over WordPress's privacy (GDPR) integration points.
 *
 * WordPress exposes personal-data export and erasure through two filters
 * (`wp_privacy_personal_data_exporters` / `wp_privacy_personal_data_erasers`)
 * and lets a plugin suggest privacy-policy copy via
 * `wp_add_privacy_policy_content`. This seam isolates those touchpoints so the
 * privacy registrar never calls them directly. Every method degrades to a safe
 * no-op when the WordPress function is absent (CLI / cron / boot before the
 * privacy API loads).
 *
 * @internal
 */
final class PrivacySupport
{
    /**
     * Register a callback on the personal-data exporters filter.
     *
     * The callback receives the running exporters array (keyed by exporter id)
     * and must return it, optionally with its own entry appended.
     */
    public static function registerExporters(callable $callback, int $priority = 10): void
    {
        if (!function_exists('add_filter')) {
            return;
        }

        add_filter('wp_privacy_personal_data_exporters', $callback, $priority, 1);
    }

    /**
     * Register a callback on the personal-data erasers filter.
     *
     * The callback receives the running erasers array (keyed by eraser id) and
     * must return it, optionally with its own entry appended.
     */
    public static function registerErasers(callable $callback, int $priority = 10): void
    {
        if (!function_exists('add_filter')) {
            return;
        }

        add_filter('wp_privacy_personal_data_erasers', $callback, $priority, 1);
    }

    /**
     * Suggest privacy-policy content for the site's Privacy Policy Guide.
     *
     * No-op when the WordPress function is unavailable.
     */
    public static function addPrivacyPolicyContent(string $pluginName, string $policyText): void
    {
        if (!function_exists('wp_add_privacy_policy_content')) {
            return;
        }

        wp_add_privacy_policy_content($pluginName, $policyText);
    }
}
