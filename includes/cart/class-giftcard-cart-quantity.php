<?php
/**
 * Cart: Gift card quantity lock.
 *
 * Enforces a maximum quantity of 1 for all gift card products
 * (both Simple and Variable) at two levels:
 *
 *  1. Frontend  — sets min/max/value to 1 on the quantity input so the
 *                 stepper cannot be used to increase the amount.
 *  2. Server    — rejects add-to-cart requests where qty != 1, preventing
 *                 bypass via direct POST manipulation.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class WC_GiftCard_Cart_Quantity extends WC_GiftCard_Base {

    public function __construct() {
        // Lock the quantity input on the product page (frontend)
        add_filter( 'woocommerce_quantity_input_args', [ $this, 'lock_quantity_input' ], 10, 2 );

        // Enforce qty = 1 server-side on add-to-cart validation
        add_filter( 'woocommerce_add_to_cart_validation', [ $this, 'enforce_single_quantity' ], 10, 3 );
    }

    // =========================================================================
    // Frontend lock
    // =========================================================================

    /**
     * Force the quantity input arguments to min = max = 1 for gift card products.
     *
     * @param array       $args    WooCommerce quantity input arguments.
     * @param \WC_Product $product The product being displayed.
     * @return array Modified arguments with quantity locked to 1.
     */
    public function lock_quantity_input( array $args, \WC_Product $product ): array {
        $product_id = self::get_parent_id( $product );

        if ( self::is_giftcard( $product_id ) ) {
            $args['min_value']   = 1;
            $args['max_value']   = 1;
            $args['input_value'] = 1;
        }

        return $args;
    }

    // =========================================================================
    // Server-side enforcement
    // =========================================================================

    /**
     * Reject add-to-cart requests for gift cards with quantity other than 1.
     * This prevents bypassing the frontend lock via a crafted POST request.
     *
     * @param bool $passed     Current validation result.
     * @param int  $product_id Product being added to the cart.
     * @param int  $quantity   Requested quantity.
     * @return bool False (with an error notice) if qty != 1 for a gift card.
     */
    public function enforce_single_quantity( bool $passed, int $product_id, int $quantity ): bool {
        if ( self::is_giftcard( $product_id ) && $quantity !== 1 ) {

            wc_add_notice(
                __( 'Gift cards can only be purchased one at a time.', 'wc-giftcard' ),
                'error'
            );
            return false;
        }

        if(self::is_giftcard($product_id)){
            // remove another product in cart, so if you buy a gift card and a non-gift card, the non-gift card is removed
            foreach ( WC()->cart->get_cart() as $cart_item_key => $cart_item ) {
                if ( $cart_item['data'] !== $product_id ) {
                    WC()->cart->remove_cart_item( $cart_item_key );
                }
            }
        }
        

        return $passed;
    }
}
