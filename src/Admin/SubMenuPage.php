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

use Middag\WordPress\Security\Enum\CapabilityInterface;
use Middag\WordPress\Security\Enum\NormalizesCapability;

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
    use NormalizesCapability;

    /**
     * Capability required to access the submenu, or null to inherit the parent
     * {@see MenuPage} capability (normalized to a plain string when set).
     */
    public ?string $capability;

    /**
     * @param null|CapabilityInterface|string $capability raw string, typed capability, or null to inherit
     */
    public function __construct(
        public string $slugSuffix,
        public string $pageTitle,
        public string $menuTitle,
        public string $routeBase = '/',
        CapabilityInterface|string|null $capability = null,
    ) {
        $this->capability = $capability === null ? null : self::capabilityString($capability);
    }
}
