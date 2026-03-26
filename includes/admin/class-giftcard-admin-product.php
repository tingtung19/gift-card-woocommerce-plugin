<?php
/**
 * Admin: Gift Card product option.
 *
 * Adds and saves the "Gift Card" checkbox in the product General tab.
 * Works for both Simple and Variable product types.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class WC_GiftCard_Admin_Product extends WC_GiftCard_Base {

    public function __construct() {
        // Render the checkbox in the product General tab
        add_action( 'woocommerce_product_options_general_product_data', [ $this, 'render_checkbox' ] );

        // Persist the checkbox value when the product is saved
        add_action( 'woocommerce_process_product_meta', [ $this, 'save_checkbox' ] );
    }

    // =========================================================================
    // Render
    // =========================================================================

    /**
     * Output the "Gift Card" checkbox inside the WooCommerce General tab panel.
     * Displayed for all product types.
     */
    public function render_checkbox(): void {
        woocommerce_wp_checkbox( [
            'id'          => self::META_IS_GIFTCARD,
            'label'       => __( 'Gift Card', 'wc-giftcard' ),
            'description' => __( 'Enable gift card functionality for this product.', 'wc-giftcard' ),
        ] );
    }

    // =========================================================================
    // Save
    // =========================================================================

    /**
     * Save the "Gift Card" checkbox value on product save.
     *
     * @param int $post_id Product post ID.
     */
    public function save_checkbox( int $post_id ): void {
        update_post_meta(
            $post_id,
            self::META_IS_GIFTCARD,
            isset( $_POST[ self::META_IS_GIFTCARD ] ) ? 'yes' : 'no'
        );
    }
}
