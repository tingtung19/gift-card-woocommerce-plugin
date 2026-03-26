<?php
/**
 * Frontend: Simple gift card product UI.
 *
 * Simple gift card products show the product's WooCommerce price directly —
 * no denomination buttons and no custom price field.
 *
 * This class is intentionally minimal. Its sole responsibility is to output
 * a small informational notice under the price so the buyer understands they
 * are purchasing a gift card coupon worth the displayed amount.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class WC_GiftCard_Frontend_Simple extends WC_GiftCard_Base {

    public function __construct() {
        // Render the info notice after the product price on the single product page
        add_action( 'woocommerce_single_product_summary', [ $this, 'render_info_notice' ], 15 );
    }

    // =========================================================================
    // Render
    // =========================================================================

    /**
     * Output a brief notice explaining that this product generates a gift card coupon.
     * Only shown on Simple gift card product pages.
     */
    public function render_info_notice(): void {
        global $product;

        // Only for simple (non-variable) gift card products
        if ( ! self::is_giftcard( $product->get_id() ) ) return;
        if ( $product instanceof \WC_Product_Variable ) return;
        ?>
        <div class="giftcard-info-notice"
             style="background:#f0f8f0; border-left:4px solid #4CAF50; padding:10px 14px; margin-bottom:12px; border-radius:4px; font-size:14px; color:#2e7d32;">
            🎁 <?php esc_html_e( 'Purchasing this product generates a gift card coupon worth the price above.', 'wc-giftcard' ); ?>
        </div>
        <?php
    }
}
