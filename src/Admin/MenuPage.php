<?php

declare(strict_types=1);

/**
 * middag-io/wordpress — MIDDAG WordPress adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\WordPress\Admin;

/**
 * Immutable description of the top-level wp-admin menu entry an
 * {@see AdminRouteRegistrar} registers.
 *
 * The menu slug is NOT stored here — it is derived from the host component name
 * by the registrar, so the brand never appears as a literal. `routeBase` is the
 * path this page dispatches to when no explicit `?route` is present.
 *
 * @api
 */
final readonly class MenuPage
{
    public function __construct(
        public string $pageTitle,
        public string $menuTitle,
        public string $capability = 'manage_options',
        public string $icon = '',
        public ?int $position = null,
        public string $routeBase = '/',
    ) {}
}
