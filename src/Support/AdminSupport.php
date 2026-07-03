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
 * Boundary seam over the wp-admin menu/notice APIs. No-ops outside a WP
 * runtime.
 *
 * @api
 */
final class AdminSupport
{
    public static function addMenuPage(
        string $pageTitle,
        string $menuTitle,
        string $capability,
        string $slug,
        callable $render,
        string $icon = '',
        ?int $position = null,
    ): string {
        if (!\function_exists('add_menu_page')) {
            return '';
        }

        return add_menu_page($pageTitle, $menuTitle, $capability, $slug, $render, $icon, $position);
    }

    public static function addSubmenuPage(
        string $parentSlug,
        string $pageTitle,
        string $menuTitle,
        string $capability,
        string $slug,
        callable $render,
    ): false|string {
        if (!\function_exists('add_submenu_page')) {
            return false;
        }

        return add_submenu_page($parentSlug, $pageTitle, $menuTitle, $capability, $slug, $render);
    }

    /**
     * Queue an escaped admin notice for the current request.
     *
     * @param 'error'|'info'|'success'|'warning' $level
     */
    public static function notice(string $message, string $level = 'info', bool $dismissible = true): void
    {
        HookSupport::addAction('admin_notices', static function () use ($message, $level, $dismissible): void {
            printf(
                '<div class="notice notice-%s%s"><p>%s</p></div>',
                EscapeSupport::attr($level),
                $dismissible ? ' is-dismissible' : '',
                EscapeSupport::html($message),
            );
        });
    }
}
