<?php
/**
 * Checkout: Gift card balance validation and discount application.
 *
 * Handles validation and discount calculation during checkout based on
 * the current balance of the applied gift card coupon.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class WC_GiftCard_Checkout extends WC_GiftCard_Base {

    public function __construct() {
        // Validate coupon and check balance before checkout
        add_action( 'woocommerce_before_calculate_totals', [ $this, 'validate_giftcard_balance' ], 9 );

        // Calculate discount based on balance instead of fixed coupon amount
        add_filter( 'woocommerce_coupon_get_discount_amount', [ $this, 'apply_balance_discount' ], 10, 5 );

        // Deduct balance after order is placed
        add_action( 'woocommerce_order_status_processing', [ $this, 'deduct_balance_on_order' ], 10 );
        add_action( 'woocommerce_payment_complete', [ $this, 'deduct_balance_on_payment' ], 10 );
    }

    // =========================================================================
    // Coupon balance validation
    // =========================================================================

    /**
     * Validate gift card balance before checkout calculation.
     * Removes the coupon if balance is exhausted or insufficient.
     */
    public function validate_giftcard_balance(): void {
        if ( is_admin() ) return;
        if ( ! is_checkout() && ! is_cart() ) return;

        $cart = WC()->cart;
        if ( ! $cart ) return;

        $applied_coupons = $cart->get_applied_coupons();

        foreach ( $applied_coupons as $coupon_code ) {
            if ( ! $this->is_giftcard_coupon( $coupon_code ) ) continue;

            $balance = WC_GiftCard_Balance::get_balance( $coupon_code );

            // If balance is exhausted, remove the coupon
            if ( $balance <= 0 ) {
                $cart->remove_coupon( $coupon_code );
                wc_add_notice(
                    sprintf(
                        /* translators: %s: coupon code */
                        __( 'Gift card "%s" has been fully redeemed and cannot be used again.', 'mi-giftcard' ),
                        esc_html( $coupon_code )
                    ),
                    'error'
                );
            }
        }
    }

    // =========================================================================
    // Discount calculation based on balance
    // =========================================================================

    /**
     * Apply balance-based discount instead of fixed coupon amount.
     *
     * If cart total > balance: apply full balance as discount
     * If cart total < balance: apply cart total (auto-adjust)
     * If balance is 0: apply no discount
     *
     * @param float      $discount             Original discount amount from WooCommerce.
     * @param float      $discounting_amount   Amount available to discount on this coupon context.
     * @param array      $cart_item            Cart item data (unused but required by filter signature).
     * @param bool       $single               Whether single item is being discounted (unused).
     * @param WC_Coupon  $coupon               WooCommerce coupon object.
     * @return float Calculated discount amount based on balance.
     */
    public function apply_balance_discount( $discount, $discounting_amount, $cart_item, $single, $coupon ) {
        // $coupon may be passed as WC_Coupon object, or in older contexts sometimes as string.
        if ( is_object( $coupon ) && method_exists( $coupon, 'get_code' ) ) {
            $coupon_code = $coupon->get_code();
        } elseif ( is_string( $coupon ) ) {
            $coupon_code = $coupon;
        } else {
            return $discount;
        }

        // Only apply balance logic to gift card coupons
        if ( ! $this->is_giftcard_coupon( $coupon_code ) ) {
            return $discount;
        }

        $balance = WC_GiftCard_Balance::get_balance( $coupon_code );

        $cart = WC()->cart;
        if ( ! $cart ) {
            return $discount;
        }

        // Get cart subtotal (excluding shipping & taxes initially)
        if( $cart->display_prices_including_tax() ) {
            $cart_subtotal = $cart->get_subtotal_tax() + $cart->get_subtotal();
        }else{
            $cart_subtotal = $cart->get_subtotal();
        }      

        // Calculate appropriate discount based on balance and cart totals
        $discount_amount = WC_GiftCard_Balance::calculate_discount_amount( $coupon_code, $cart_subtotal );
      

        return (float) $discount_amount;
    }

    // =========================================================================
    // Balance deduction on order completion
    // =========================================================================

    /**
     * Deduct gift card balance after payment is successfully processed.
     *
     * @param int $order_id WooCommerce order ID.
     */
    public function deduct_balance_on_payment( int $order_id ): void {
        $this->process_balance_deduction( $order_id );
    }

    /**
     * Deduct gift card balance when order status changes to completed.
     *
     * @param int $order_id WooCommerce order ID.
     */
    public function deduct_balance_on_order( int $order_id ): void {
        $this->process_balance_deduction( $order_id );
    }

    /**
     * Process balance deduction for all gift card coupons used in an order.
     * Prevents duplicate deductions with meta flag.
     *
     * @param int $order_id WooCommerce order ID.
     */
    private function process_balance_deduction( int $order_id ): void {
        $order = wc_get_order( $order_id );
        if ( ! $order ) return;

        // Prevent duplicate processing
        $already_processed = get_post_meta( $order_id, '_giftcard_balance_deducted', true );
        if ( $already_processed === 'yes' ) return;

        $customer_email = $order->get_billing_email() ?: $order->get_customer_email();

        // Find all applied coupons and check which are gift cards
        $applied_coupons = $order->get_coupon_codes();

        foreach ( $applied_coupons as $coupon_code ) {
            if ( ! $this->is_giftcard_coupon( $coupon_code ) ) continue;

            // Get the discount amount applied to this order
            foreach ( $order->get_coupons() as $coupon_item ) {
                if ( $coupon_item->get_code() !== $coupon_code ) continue;
                
                $discount_amount = abs( (float) $coupon_item->get_discount() + (float) $coupon_item->get_discount_tax() );

                if ( $discount_amount > 0 ) {
                    // Deduct the balance
                    WC_GiftCard_Balance::deduct_balance(
                        $coupon_code,
                        $discount_amount,
                        $order_id,
                        $customer_email
                    );
                    // Mark as processed
                    update_post_meta( $order_id, '_giftcard_balance_deducted', 'yes' );
                }
            }
        }

        
    }

    // =========================================================================
    // Helper methods
    // =========================================================================

    /**
     * Check if a coupon code is a gift card coupon.
     *
     * @param string $coupon_code Coupon code to check.
     * @return bool True if the coupon is a gift card, false otherwise.
     */
    private function is_giftcard_coupon( string $coupon_code ): bool {
        $coupon_id = $this->get_coupon_id_by_code( $coupon_code );
        if ( ! $coupon_id ) return false;

        // Check if it has gift card balance meta
        return get_post_meta( $coupon_id, WC_GiftCard_Coupon::META_INITIAL_AMOUNT, true ) !== '';
    }

    /**
     * Get coupon post ID from coupon code.
     *
     * @param string $coupon_code Coupon code.
     * @return int Coupon post ID, or 0 if not found.
     */
    private function get_coupon_id_by_code( string $coupon_code ): int {
        $posts = get_posts([
            'post_type'  => 'shop_coupon',
            'post_title' => $coupon_code,
            'fields'     => 'ids',
            'numberposts' => 1,
        ]);

        return ! empty( $posts ) ? (int) $posts[0] : 0;
    }
}
