<?php

declare(strict_types=1);

/**
 * middag-io/wordpress — MIDDAG WordPress adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\WordPress\Security;

use Middag\WordPress\Security\Enum\CapabilityInterface;
use Middag\WordPress\Security\Enum\NormalizesCapability;
use Middag\WordPress\Support\CapabilitySupport;

/**
 * Declarative custom-capability registration: describe which capabilities each
 * role receives once, then apply on plugin activation and remove on
 * deactivation/uninstall. Replaces the ad-hoc `add_cap()` loops every plugin
 * reinvents.
 *
 * ```php
 * $registrar = new CapabilityRegistrar([
 *     'administrator' => ['middag_manage_quotes', 'middag_view_reports'],
 *     'shop_manager'  => ['middag_manage_quotes'],
 * ]);
 * $registrar->register();   // activation
 * $registrar->unregister(); // deactivation
 * ```
 *
 * @api
 */
final readonly class CapabilityRegistrar
{
    use NormalizesCapability;

    /**
     * @param array<string, list<CapabilityInterface|string>> $capabilitiesByRole role slug =>
     *                                                                            capabilities to grant (raw
     *                                                                            strings or typed capabilities)
     */
    public function __construct(
        private array $capabilitiesByRole,
    ) {}

    /**
     * Grant every declared capability. Missing roles are skipped (reported in
     * the result map) so a plugin can declare caps for optional roles such as
     * WooCommerce's `shop_manager`.
     *
     * @return array<string, bool> "role:capability" => granted
     */
    public function register(): array
    {
        return $this->apply(granting: true);
    }

    /**
     * Revoke every declared capability.
     *
     * @return array<string, bool> "role:capability" => revoked
     */
    public function unregister(): array
    {
        return $this->apply(granting: false);
    }

    /**
     * @return array<string, bool>
     */
    private function apply(bool $granting): array
    {
        $results = [];

        foreach ($this->capabilitiesByRole as $role => $capabilities) {
            foreach ($capabilities as $capability) {
                $name = self::capabilityString($capability);
                $results[$role . ':' . $name] = $granting
                    ? CapabilitySupport::addCap($role, $name)
                    : CapabilitySupport::removeCap($role, $name);
            }
        }

        return $results;
    }
}
