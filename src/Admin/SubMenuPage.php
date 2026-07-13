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
 * Immutable description of one wp-admin submenu entry.
 *
 * `slugSuffix` is appended to the component name by the registrar to form the
 * page slug (`{component}-{suffix}`). `routeBase` is the path this submenu
 * dispatches to when no explicit `?route` is present. A null `capability`
 * inherits the parent {@see MenuPage} capability.
 *
 * @api
 */
final readonly class SubMenuPage
{
    public function __construct(
        public string $slugSuffix,
        public string $pageTitle,
        public string $menuTitle,
        public string $routeBase = '/',
        public ?string $capability = null,
    ) {}
}
