<?php
/**
 * Abstract base class for all WC Gift Card classes.
 *
 * Provides shared constants (meta keys) and common helper methods
 * so every concrete class reads from a single source of truth.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

abstract class WC_GiftCard_Base {

    // =========================================================================
    // Meta key constants — used across admin, frontend, cart, and order classes
    // =========================================================================

    /** Flags a product as a gift card */
    const META_IS_GIFTCARD = '_is_giftcard';

    /** Enables custom price entry on variable gift card products */
    const META_ALLOW_CUSTOM_PRICE = '_giftcard_allow_custom_price';

    /** Stores the recipient email on a cart item / order line item */
    const META_RECIPIENT = '_giftcard_recipient_email';

    /**
     * Stores the resolved gift card amount on a cart item / order line item.
     * Set by the variable cart handler; not used for simple products.
     */
    const META_RESOLVED_PRICE = '_giftcard_custom_price';

    /** Stores the human-readable denomination label on a cart item / order line item */
    const META_VARIATION_LABEL = '_giftcard_variation_label';

    /** Prevents duplicate coupon generation on an order */
    const META_ORDER_PROCESSED = '_giftcard_processed';

    // =========================================================================
    // Shared helpers
    // =========================================================================

    /**
     * Check whether a product is flagged as a gift card.
     *
     * @param int $product_id WooCommerce product ID.
     * @return bool
     */
    public static function is_giftcard( int $product_id ): bool {
        return get_post_meta( $product_id, self::META_IS_GIFTCARD, true ) === 'yes';
    }

    /**
     * Check whether custom price entry is enabled for a product.
     * Only meaningful for variable gift card products.
     *
     * @param int $product_id WooCommerce product ID.
     * @return bool
     */
    public static function allows_custom_price( int $product_id ): bool {
        return get_post_meta( $product_id, self::META_ALLOW_CUSTOM_PRICE, true ) === 'yes';
    }

    /**
     * Determine the parent product ID regardless of whether a simple
     * or variation product object is passed.
     *
     * @param \WC_Product $product
     * @return int Parent product ID, or the product's own ID if it has no parent.
     */
    public static function get_parent_id( \WC_Product $product ): int {
        $parent = $product->get_parent_id();
        return $parent > 0 ? $parent : $product->get_id();
    }
}
