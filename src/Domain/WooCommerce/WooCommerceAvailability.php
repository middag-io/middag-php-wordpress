<?php

declare(strict_types=1);

/**
 * middag-io/wordpress — MIDDAG WordPress adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\WordPress\Domain\WooCommerce;

/**
 * Runtime feature probe for optional WooCommerce integrations.
 *
 * @api
 */
final class WooCommerceAvailability
{
    public static function isAvailable(): bool
    {
        return \class_exists('WooCommerce') || \function_exists('WC');
    }
}
