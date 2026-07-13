<?php

declare(strict_types=1);

/**
 * middag-io/wordpress — MIDDAG WordPress adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\WordPress\Tests\Security\Enum;

use Middag\WordPress\Security\Enum\CapabilityInterface;
use Middag\WordPress\Security\Enum\WooCommerceCapability;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(WooCommerceCapability::class)]
final class WooCommerceCapabilityTest extends TestCase
{
    #[Test]
    public function implementsTheCapabilityContract(): void
    {
        self::assertInstanceOf(CapabilityInterface::class, WooCommerceCapability::ManageWooCommerce);
    }

    #[Test]
    public function backingValueIsTheExactWooCommerceCapabilityString(): void
    {
        self::assertSame('manage_woocommerce', WooCommerceCapability::ManageWooCommerce->value);
        self::assertSame('view_woocommerce_reports', WooCommerceCapability::ViewWooCommerceReports->value);
        self::assertSame('edit_shop_orders', WooCommerceCapability::EditShopOrders->value);
        self::assertSame('edit_product', WooCommerceCapability::EditProduct->value);
    }

    #[Test]
    public function toStringMatchesTheBackingValue(): void
    {
        foreach (WooCommerceCapability::cases() as $capability) {
            self::assertSame($capability->value, $capability->toString());
        }
    }

    #[Test]
    public function everyBackingValueIsLowercaseSnakeCase(): void
    {
        foreach (WooCommerceCapability::cases() as $capability) {
            self::assertMatchesRegularExpression(
                '/^[a-z][a-z0-9_]*$/',
                $capability->value,
                sprintf('%s has a non-snake_case backing value', $capability->name),
            );
        }
    }

    #[Test]
    public function objectScopedCapabilitiesAreFlaggedAsMeta(): void
    {
        // Singular object-scoped caps are meta (map_meta_cap resolves them).
        self::assertTrue(WooCommerceCapability::EditProduct->isMeta());
        self::assertTrue(WooCommerceCapability::ReadShopOrder->isMeta());
        self::assertTrue(WooCommerceCapability::DeleteShopCoupon->isMeta());
    }

    #[Test]
    public function collectionAndTopLevelCapabilitiesAreNotMeta(): void
    {
        self::assertFalse(WooCommerceCapability::ManageWooCommerce->isMeta());
        self::assertFalse(WooCommerceCapability::EditProducts->isMeta());
        self::assertFalse(WooCommerceCapability::EditOthersShopOrders->isMeta());
        self::assertFalse(WooCommerceCapability::ManageProductTerms->isMeta());
    }

    #[Test]
    public function caseValuesAreUnique(): void
    {
        $values = array_map(static fn (WooCommerceCapability $c): string => $c->value, WooCommerceCapability::cases());

        self::assertSame(array_unique($values), $values);
    }
}
