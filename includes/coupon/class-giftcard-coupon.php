<?php
/**
 * Coupon: Gift card coupon generator.
 *
 * Creates a WooCommerce coupon post for each gift card purchased.
 * The coupon is a single-use, fixed-cart discount worth the gift card amount.
 *
 * When a recipient email is provided the coupon is restricted to that email
 * via WooCommerce's built-in customer_email meta, so it will be rejected at
 * checkout if used by a different account.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class WC_GiftCard_Coupon extends WC_GiftCard_Base {

    /**
     * Generate a WooCommerce coupon for a gift card purchase.
     *
     * @param float       $amount          Coupon value (equals the resolved gift card price).
     * @param string      $buyer_email     Billing email of the purchasing customer.
     * @param string|null $recipient_email Restrict the coupon to this email; null = unrestricted.
     * @param int         $order_id        Source order ID stored for audit reference.
     *
     * @return string The generated coupon code.
     * @throws \RuntimeException If the WooCommerce coupon post cannot be created.
     */
    public static function generate(
        float   $amount,
        string  $buyer_email,
        ?string $recipient_email,
        int     $order_id
    ): string {

        $code = self::build_unique_code();

        $coupon_id = wp_insert_post([
            'post_title'   => $code,
            'post_name'    => $code,
            'post_status'  => 'publish',
            'post_type'    => 'shop_coupon',
            /* translators: %d: WooCommerce order ID */
            'post_excerpt' => sprintf( __( 'Gift card generatmi-giftcarder #%d.', 'wc-giftcard' ), $order_id ),
        ] );

        if ( is_wp_error( $coupon_id ) ) {
            throw new \RuntimeException(
                /* translators: %s: WP_Error message */
                sprintf( __( 'Failed to create gift card coupon: %s', 'wc-giftcard' ), $coupon_id->get_error_message() )
            );
        }

        self::set_coupon_meta( $coupon_id, $amount, $buyer_email, $recipient_email, $order_id );

        return $code;
    }

    // =========================================================================
    // Private helpers
    // =========================================================================

    /**
     * Build a unique, human-readable coupon code.
     * Format: GC-XXXXXX-YYYY  (uppercase alphanumeric, no special characters)
     *
     * @return string
     */
    private static function build_unique_code(): string {
        return strtoupper(
            'GC-' . substr( uniqid(), -6 ) . '-' . wp_generate_password( 4, false )
        );
    }

    /**
     * Write all required meta fields to the coupon post.
     *
     * Core WooCommerce meta:
     *  - discount_type   fixed_cart discount
     *  - coupon_amount   monetary value
     *  - usage_limit     1  (single use)
     *  - individual_use  yes (cannot be combined)
     *  - customer_email  restricted to recipient when provided
     *
     * Custom audit meta:
     *  - _giftcard_order_id
     *  - _giftcard_buyer_email
     *  - _giftcard_recipient
     *
     * @param int         $coupon_id
     * @param float       $amount
     * @param string      $buyer_email
     * @param string|null $recipient_email
     * @param int         $order_id
     */
    private static function set_coupon_meta(
        int     $coupon_id,
        float   $amount,
        string  $buyer_email,
        ?string $recipient_email,
        int     $order_id
    ): void {

        // Core coupon behaviour
        update_post_meta( $coupon_id, 'discount_type',  'fixed_cart' );
        update_post_meta( $coupon_id, 'coupon_amount',  $amount );
        update_post_meta( $coupon_id, 'usage_limit',    1 );
        update_post_meta( $coupon_id, 'individual_use', 'yes' );
        update_post_meta( $coupon_id, 'date_expires',   '' );   // No expiry by default

        // Restrict to recipient email if one was provided
        if ( ! empty( $recipient_email ) ) {
            update_post_meta( $coupon_id, 'customer_email', [ $recipient_email ] );
        }

        // Audit trail meta
        update_post_meta( $coupon_id, '_giftcard_order_id',    $order_id );
        update_post_meta( $coupon_id, '_giftcard_buyer_email', $buyer_email );
        update_post_meta( $coupon_id, '_giftcard_recipient',   $recipient_email ?? '' );
    }
}
