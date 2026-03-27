<?php
/**
 * Balance: Gift card balance and transaction management.
 *
 * Manages balance tracking, transaction history, and balance validation
 * for reusable gift card coupons.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class WC_GiftCard_Balance extends WC_GiftCard_Base {

    /**
     * Get the current balance for a gift card coupon.
     *
     * @param int|string $coupon_id_or_code Coupon post ID or coupon code.
     * @return float Current balance, or 0 if coupon not found.
     */
    public static function get_balance( $coupon_id_or_code ): float {
        $coupon_id = self::get_coupon_id( $coupon_id_or_code );
        
        if ( ! $coupon_id ) return 0;

        $balance = get_post_meta( $coupon_id, WC_GiftCard_Coupon::META_CURRENT_BALANCE, true );
        
        return (float) ( $balance ?: 0 );
    }

    /**
     * Get the initial/original amount of a gift card coupon.
     *
     * @param int|string $coupon_id_or_code Coupon post ID or coupon code.
     * @return float Initial amount, or 0 if coupon not found.
     */
    public static function get_initial_amount( $coupon_id_or_code ): float {
        $coupon_id = self::get_coupon_id( $coupon_id_or_code );
        if ( ! $coupon_id ) return 0;

        $amount = get_post_meta( $coupon_id, WC_GiftCard_Coupon::META_INITIAL_AMOUNT, true );
        return (float) ( $amount ?: 0 );
    }

    /**
     * Get the full transaction history for a gift card coupon.
     *
     * @param int|string $coupon_id_or_code Coupon post ID or coupon code.
     * @return array Array of transaction entries, each containing:
     *  - amount: decimal amount used
     *  - remaining_balance: balance after transaction
     *  - order_id: WooCommerce order ID
     *  - customer_email: email of the customer who redeemed
     *  - product_names: array of product names in the order
     *  - timestamp: Unix timestamp of when transaction occurred
     */
    public static function get_history( $coupon_id_or_code ): array {
        $coupon_id = self::get_coupon_id( $coupon_id_or_code );
        if ( ! $coupon_id ) return [];

        $history = get_post_meta( $coupon_id, WC_GiftCard_Coupon::META_TRANSACTION_HISTORY, true );
        return is_array( $history ) ? $history : [];
    }

    /**
     * Check if a gift card has sufficient balance for a given amount.
     *
     * @param int|string $coupon_id_or_code Coupon post ID or coupon code.
     * @param float      $requested_amount   Amount to validate.
     * @return bool True if balance >= requested amount.
     */
    public static function has_sufficient_balance( $coupon_id_or_code, float $requested_amount ): bool {
        $balance = self::get_balance( $coupon_id_or_code );
        
        // Normalize to 2 decimal places to avoid float precision issues
        $balance = round( $balance, 2 );
        $requested_amount = round( $requested_amount, 2 );
        
        return $balance >= $requested_amount;
    }

    /**
     * Deduct balance from a gift card coupon and log transaction.
     *
     * @param int|string $coupon_id_or_code Coupon post ID or coupon code.
     * @param float      $amount          Amount to deduct.
     * @param int        $order_id        WooCommerce order ID making the purchase.
     * @param string     $customer_email  Email of the customer redeeming the coupon.
     * @return bool True on success, false if balance insufficient or coupon not found.
     */
    public static function deduct_balance( $coupon_id_or_code, float $amount, int $order_id, string $customer_email ): bool {
        $coupon_id = self::get_coupon_id( $coupon_id_or_code );
        if ( ! $coupon_id ) return false;

        $current_balance = self::get_balance( $coupon_id );      
        
        // Normalize float values to 2 decimal places to avoid precision issues
        $current_balance = round( $current_balance, 2 );
        $amount = round( $amount, 2 );

        // Insufficient balance
        if ( $current_balance < $amount ) {
            return false;
        }
            
        $new_balance = $current_balance - $amount;

        // Update balance
        update_post_meta( $coupon_id, WC_GiftCard_Coupon::META_CURRENT_BALANCE, $new_balance );

        // Log transaction
        $order = wc_get_order( $order_id );
        $product_names = [];

        if ( $order ) {
            foreach ( $order->get_items() as $item ) {
                $product_names[] = $item->get_name();
            }
        }

        $transaction = [
            'amount'               => $amount,
            'remaining_balance'    => $new_balance,
            'order_id'             => $order_id,
            'customer_email'       => sanitize_email( $customer_email ),
            'product_names'        => array_unique( $product_names ),
            'timestamp'            => current_time( 'timestamp' ),
        ];

        $history = self::get_history( $coupon_id );
        $history[] = $transaction;

        update_post_meta( $coupon_id, WC_GiftCard_Coupon::META_TRANSACTION_HISTORY, $history );

        /**
         * Fired after a gift card balance is deducted.
         *
         * @param int    $coupon_id         Coupon post ID.
         * @param float  $amount            Amount deducted.
         * @param float  $new_balance       New balance after deduction.
         * @param array  $transaction       Full transaction record.
         */
        do_action( 'wc_giftcard_balance_deducted', $coupon_id, $amount, $new_balance, $transaction );

        return true;
    }

    /**
     * Get the appropriate discount amount for a checkout based on coupon balance.
     *
     * If the cart total is less than the remaining balance, apply the cart total.
     * If the cart total exceeds the balance, apply only the remaining balance.
     *
     * @param int|string $coupon_id_or_code Coupon post ID or coupon code.
     * @param float      $cart_total        Current cart total (subtotal).
     * @return float Amount to apply as discount, or 0 if insufficient balance.
     */
    public static function calculate_discount_amount( $coupon_id_or_code, float $cart_total ): float {
        
        $balance = self::get_balance( $coupon_id_or_code );
        
        // Normalize to 2 decimal places to avoid float precision issues
        $balance = round( $balance, 2 );
        $cart_total = round( $cart_total, 2 );

        if ( $balance <= 0 ) {
            return 0;
        }

        // Apply the lesser of balance or cart total
        return min( $balance, $cart_total );
    }

    // =========================================================================
    // Private helpers
    // =========================================================================

    /**
     * Resolve a coupon post ID from either an ID or coupon code.
     *
     * @param int|string $coupon_id_or_code Coupon post ID or coupon code.
     * @return int Coupon post ID, or 0 if not found.
     */
    private static function get_coupon_id( $coupon_id_or_code ): int {
        if ( is_numeric( $coupon_id_or_code ) ) {
            $coupon_id = (int) $coupon_id_or_code;
            // Verify it's actually a coupon post
            if ( get_post_type( $coupon_id ) === 'shop_coupon' ) {
                return $coupon_id;
            }
            return 0;
        }

        // It's a coupon code - find the post ID
        $posts = wc_get_coupon_id_by_code( $coupon_id_or_code );

        return ! empty( $posts ) ? (int) $posts : 0;
    }
}
