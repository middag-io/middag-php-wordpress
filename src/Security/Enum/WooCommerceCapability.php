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
 * Closed catalog of WooCommerce capabilities.
 *
 * Kept separate from {@see WpCapability} because WooCommerce is optional: these
 * capabilities are registered dynamically by WooCommerce (WC_Install) and only
 * exist on a role while the plugin is active. Referencing a case here is a plain
 * inert string and does NOT require WooCommerce to be loaded — but actually
 * granting one only makes sense once the WooCommerce roles (e.g. `shop_manager`)
 * exist. The backed value is the exact string WooCommerce expects.
 *
 * Cases cover the top-level gate caps plus the full post-type cap families for
 * the `product`, `shop_order`, and `shop_coupon` post types. The singular
 * object-scoped forms (edit_product, read_shop_order, ...) are META
 * capabilities (see {@see self::isMeta()}) resolved by `map_meta_cap()`; the
 * plural/collection and `*_terms` forms are primitives stored on roles.
 *
 * @api
 */
enum WooCommerceCapability: string implements CapabilityInterface
{
    // Core (top-level) (primitive)
    case ManageWooCommerce = 'manage_woocommerce';

    case ViewWooCommerceReports = 'view_woocommerce_reports';

    case CreateCustomers = 'create_customers';

    // Product (object-scoped) (meta)
    case EditProduct = 'edit_product';

    case ReadProduct = 'read_product';

    case DeleteProduct = 'delete_product';

    // Product (collection) (primitive)
    case EditProducts = 'edit_products';

    case EditOthersProducts = 'edit_others_products';

    case PublishProducts = 'publish_products';

    case ReadPrivateProducts = 'read_private_products';

    case DeleteProducts = 'delete_products';

    case DeletePrivateProducts = 'delete_private_products';

    case DeletePublishedProducts = 'delete_published_products';

    case DeleteOthersProducts = 'delete_others_products';

    case EditPrivateProducts = 'edit_private_products';

    case EditPublishedProducts = 'edit_published_products';

    case ManageProductTerms = 'manage_product_terms';

    case EditProductTerms = 'edit_product_terms';

    case DeleteProductTerms = 'delete_product_terms';

    case AssignProductTerms = 'assign_product_terms';

    // Shop Order (object-scoped) (meta)
    case EditShopOrder = 'edit_shop_order';

    case ReadShopOrder = 'read_shop_order';

    case DeleteShopOrder = 'delete_shop_order';

    // Shop Order (collection) (primitive)
    case EditShopOrders = 'edit_shop_orders';

    case EditOthersShopOrders = 'edit_others_shop_orders';

    case PublishShopOrders = 'publish_shop_orders';

    case ReadPrivateShopOrders = 'read_private_shop_orders';

    case DeleteShopOrders = 'delete_shop_orders';

    case DeletePrivateShopOrders = 'delete_private_shop_orders';

    case DeletePublishedShopOrders = 'delete_published_shop_orders';

    case DeleteOthersShopOrders = 'delete_others_shop_orders';

    case EditPrivateShopOrders = 'edit_private_shop_orders';

    case EditPublishedShopOrders = 'edit_published_shop_orders';

    case ManageShopOrderTerms = 'manage_shop_order_terms';

    case EditShopOrderTerms = 'edit_shop_order_terms';

    case DeleteShopOrderTerms = 'delete_shop_order_terms';

    case AssignShopOrderTerms = 'assign_shop_order_terms';

    // Shop Coupon (object-scoped) (meta)
    case EditShopCoupon = 'edit_shop_coupon';

    case ReadShopCoupon = 'read_shop_coupon';

    case DeleteShopCoupon = 'delete_shop_coupon';

    // Shop Coupon (collection) (primitive)
    case EditShopCoupons = 'edit_shop_coupons';

    case EditOthersShopCoupons = 'edit_others_shop_coupons';

    case PublishShopCoupons = 'publish_shop_coupons';

    case ReadPrivateShopCoupons = 'read_private_shop_coupons';

    case DeleteShopCoupons = 'delete_shop_coupons';

    case DeletePrivateShopCoupons = 'delete_private_shop_coupons';

    case DeletePublishedShopCoupons = 'delete_published_shop_coupons';

    case DeleteOthersShopCoupons = 'delete_others_shop_coupons';

    case EditPrivateShopCoupons = 'edit_private_shop_coupons';

    case EditPublishedShopCoupons = 'edit_published_shop_coupons';

    case ManageShopCouponTerms = 'manage_shop_coupon_terms';

    case EditShopCouponTerms = 'edit_shop_coupon_terms';

    case DeleteShopCouponTerms = 'delete_shop_coupon_terms';

    case AssignShopCouponTerms = 'assign_shop_coupon_terms';

    public function toString(): string
    {
        return $this->value;
    }

    public function isMeta(): bool
    {
        return match ($this) {
            self::EditProduct, self::ReadProduct, self::DeleteProduct, self::EditShopOrder,
            self::ReadShopOrder, self::DeleteShopOrder, self::EditShopCoupon, self::ReadShopCoupon,
            self::DeleteShopCoupon => true,
            default => false,
        };
    }
}
