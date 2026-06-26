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

use Middag\WordPress\Settings\SettingsRegistrar;

/**
 * Thin wrapper over WordPress's Settings API functions.
 *
 * Isolates `register_setting`, `add_settings_section`, and `add_settings_field`
 * behind a small seam so the {@see SettingsRegistrar}
 * never touches the platform functions directly. Each method degrades to a safe
 * no-op when the Settings API is unavailable (WP-CLI / cron / boot before
 * `wp-admin/includes/template.php` loads), which keeps the registrar callable in
 * every context.
 *
 * @internal
 */
final class SettingsSupport
{
    /**
     * Register a setting and its sanitize/default metadata under an option group.
     *
     * @param array<string, mixed> $args register_setting() args (e.g.
     *                                   `sanitize_callback`, `default`, `type`,
     *                                   `show_in_rest`)
     */
    public static function registerSetting(string $optionGroup, string $optionName, array $args = []): void
    {
        if (!function_exists('register_setting')) {
            return;
        }

        register_setting($optionGroup, $optionName, $args);
    }

    /**
     * Add a settings section to an admin page.
     *
     * @param callable             $callback renders the section's intro markup
     * @param array<string, mixed> $args     add_settings_section() args
     */
    public static function addSection(string $id, string $title, callable $callback, string $page, array $args = []): void
    {
        if (!function_exists('add_settings_section')) {
            return;
        }

        add_settings_section($id, $title, $callback, $page, $args);
    }

    /**
     * Add a settings field to a section on an admin page.
     *
     * @param callable             $callback renders the field's control markup
     * @param array<string, mixed> $args     add_settings_field() args
     */
    public static function addField(string $id, string $title, callable $callback, string $page, string $section = 'default', array $args = []): void
    {
        if (!function_exists('add_settings_field')) {
            return;
        }

        add_settings_field($id, $title, $callback, $page, $section, $args);
    }
}
