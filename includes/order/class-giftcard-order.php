<?php
/**
 * Order: Gift card order processor.
 *
 * Listens for payment/processing events and orchestrates coupon generation
 * and email dispatch for every gift card line item in the order.
 *
 * Coupon amount resolution (highest priority first):
 *  1. META_RESOLVED_PRICE stored by WC_GiftCard_Cart_Variable (variable products,
 *     covers both denomination selection and custom price entry).
 *  2. Line item subtotal — fallback for Simple gift card products which carry
 *     no resolved price meta and rely on their WooCommerce product price.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class WC_GiftCard_Order extends WC_GiftCard_Base {

    public function __construct() {
        // Primary trigger: payment confirmed via gateway
        add_action( 'woocommerce_payment_complete', [ $this, 'process_order' ] );

        // Fallback trigger: order status set to "processing" (e.g. COD, manual)
        add_action( 'woocommerce_order_status_processing', [ $this, 'process_order' ] );
    }

    // =========================================================================
    // Order processing
    // =========================================================================

    /**
     * Iterate over all gift card line items in the order, generate coupons,
     * and send notification emails.
     *
     * @param int $order_id WooCommerce order ID.
     */
    public function process_order( int $order_id ): void {
        // Prevent duplicate processing when both hooks fire on the same order
        if ( get_post_meta( $order_id, self::META_ORDER_PROCESSED, true ) === 'yes' ) return;

        $order = wc_get_order( $order_id );
        if ( ! $order ) return;

        $buyer_email = $order->get_billing_email();
        $mailer      = new WC_GiftCard_Email();
        $any_generated = false;

        foreach ( $order->get_items() as $item ) {
            /** @var \WC_Order_Item_Product $item */
            if ( ! self::is_giftcard( $item->get_product_id() ) ) continue;

            $processed = $this->process_line_item( $item, $order, $buyer_email, $mailer );

            if ( $processed ) $any_generated = true;
        }

        // Mark the order so the hooks do not fire again for the same order
        if ( $any_generated ) {
            update_post_meta( $order_id, self::META_ORDER_PROCESSED, 'yes' );
        }
    }

    // =========================================================================
    // Per-line-item processing
    // =========================================================================

    /**
     * Generate a coupon and send emails for a single gift card line item.
     *
     * @param \WC_Order_Item_Product $item        Gift card order line item.
     * @param \WC_Order              $order       Parent order.
     * @param string                 $buyer_email Billing email of the customer.
     * @param WC_GiftCard_Email      $mailer      Email sender instance.
     * @return bool True if the coupon was generated successfully.
     */
    private function process_line_item(
        \WC_Order_Item_Product $item,
        \WC_Order $order,
        string $buyer_email,
        WC_GiftCard_Email $mailer
    ): bool {
        $amount          = $this->resolve_amount( $item );
        $recipient_email = $item->get_meta( self::META_RECIPIENT ) ?: null;
        $variation_label = $item->get_meta( self::META_VARIATION_LABEL ) ?: '';

        // Guard: skip line items with an unresolvable amount
        if ( $amount <= 0 ) {
            $order->add_order_note(
                sprintf(
                    /* translators: %s: product name */
                    __( 'Skipped gift card for "%s": could not determimi-giftcardamount.', 'wc-giftcard' ),
                    $item->get_name()
                )
            );
            return false;
        }

        try {
            $coupon_code = WC_GiftCard_Coupon::generate( $amount, $buyer_email, $recipient_email, $order->get_id() );

            $this->send_emails( $mailer, $buyer_email, $recipient_email, $coupon_code, $amount, $variation_label );

            $order->add_order_note( $this->build_success_note( $coupon_code, $amount, $variation_label ) );

            return true;

        } catch ( \Exception $e ) {
            $order->add_order_note(
                sprintf(
                    /* translators: %s: error message */
                    __( 'Failed to create gift card coupon: %s', 'wc-giftcard' ),
                    $e->getMessage()
                )
            );
            error_log( '[WC GiftCard] Error on order #' . $order->get_id() . ': ' . $e->getMessage() );

            return false;
        }
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    /**
     * Resolve the coupon amount for a line item.
     *
     * Prefers the resolved price stored by WC_GiftCard_Cart_Variable (variable
     * products). Falls back to the line item subtotal for simple gift card products.
     *
     * @param \WC_Order_Item_Product $item
     * @return float Resolved amount, or 0.0 if not determinable.
     */
    private function resolve_amount( \WC_Order_Item_Product $item ): float {
        $stored = (float) $item->get_meta( self::META_RESOLVED_PRICE );

        return $stored > 0 ? $stored : (float) $item->get_subtotal() + (float) $item->get_subtotal_tax();
    }

    /**
     * Send coupon notification emails to the buyer and (if applicable) the recipient.
     *
     * @param WC_GiftCard_Email $mailer
     * @param string            $buyer_email
     * @param string|null       $recipient_email
     * @param string            $coupon_code
     * @param float             $amount
     * @param string            $variation_label
     */
    private function send_emails(
        WC_GiftCard_Email $mailer,
        string  $buyer_email,
        ?string $recipient_email,
        string  $coupon_code,
        float   $amount,
        string  $variation_label
    ): void {
        // Always notify the buyer
        $mailer->send(
            to:              $buyer_email,
            coupon_code:     $coupon_code,
            amount:          $amount,
            variation_label: $variation_label,
            recipient_email: $recipient_email,
            is_buyer:        true
        );

        // Notify the recipient only when a different address was specified
        if ( ! empty( $recipient_email ) && $recipient_email !== $buyer_email ) {
            $mailer->send(
                to:              $recipient_email,
                coupon_code:     $coupon_code,
                amount:          $amount,
                variation_label: $variation_label,
                recipient_email: $recipient_email,
                is_buyer:        false
            );
        }
    }

    /**
     * Build the order note text logged after a successful coupon generation.
     *
     * @param string $coupon_code
     * @param float  $amount
     * @param string $variation_label
     * @return string HTML-safe note string.
     */
    private function build_success_note( string $coupon_code, float $amount, string $variation_label ): string {
        return sprintf(
            /* translators: 1: coupon code  2: formatted price  3: denomination label (optional) */
            __( 'Gift card coupon <strong>%1$s</strong> (worth %2$s%3$s) was successfully created and sent.', 'wc-giftcard' ),
            esc_html( $coupon_code ),
            wc_price( $amount ),
            $variation_label ? ' &mdash; ' . esc_html( $variation_label ) : ''
        );
    }
}
