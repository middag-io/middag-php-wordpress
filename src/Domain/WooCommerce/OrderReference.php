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
 * Lightweight reference to a WooCommerce order without requiring WooCommerce.
 *
 * @api
 */
final readonly class OrderReference
{
    public function __construct(
        public int $id,
        public ?string $number = null,
        public ?string $status = null,
    ) {}
}
