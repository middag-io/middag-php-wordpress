<?php

declare(strict_types=1);

/**
 * middag-io/wordpress — MIDDAG WordPress adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\WordPress\Security\Enum;

/**
 * Marker for a typed WordPress capability.
 *
 * Implemented by the closed capability catalogs {@see WpCapability} and
 * {@see WooCommerceCapability}. Lets the authorization seams accept either enum
 * (or a raw string) through a single `string|CapabilityInterface` union instead
 * of a two-enum union, so a plugin references `WpCapability::ManageOptions`
 * instead of retyping the magic string `'manage_options'`.
 *
 * `toString()` yields the exact WordPress capability string the platform
 * expects (identical to the backed enum's `->value`); it exists so callers and
 * static analysis never depend on the concrete backed-enum type.
 *
 * @api
 */
interface CapabilityInterface
{
    /**
     * The exact WordPress capability string (e.g. `manage_options`).
     */
    public function toString(): string;

    /**
     * Whether this is a META capability — one checked against a specific object
     * id (e.g. `current_user_can('edit_post', $id)`) and resolved by WordPress'
     * `map_meta_cap()` at runtime. Meta caps must never be granted to a role;
     * grant the primitive they map to instead.
     */
    public function isMeta(): bool;
}
