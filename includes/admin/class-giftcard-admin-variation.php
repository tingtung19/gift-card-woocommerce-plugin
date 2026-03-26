<?php
/**
 * Admin: Variable gift card settings.
 *
 * Adds and saves the "Allow Custom Price" checkbox in the product General tab.
 * This option is only meaningful for Variable gift card products and is
 * hidden via CSS for other product types.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class WC_GiftCard_Admin_Variation extends WC_GiftCard_Base {

    public function __construct() {
        // Render the custom price checkbox after the Gift Card checkbox
        add_action( 'woocommerce_product_options_general_product_data', [ $this, 'render_checkbox' ] );

        // Persist the value on product save
        add_action( 'woocommerce_process_product_meta', [ $this, 'save_checkbox' ] );
    }

    // =========================================================================
    // Render
    // =========================================================================

    /**
     * Output the "Allow Custom Price" checkbox.
     *
     * Wrapped in WooCommerce's .show_if_variable div so it only appears
     * in the admin when the product type is Variable.
     */
    public function render_checkbox(): void {
        echo '<div class="show_if_variable">';

        woocommerce_wp_checkbox( [
            'id'          => self::META_ALLOW_CUSTOM_PRICE,
            'label'       => __( 'Allow Custom Price', 'wc-giftcard' ),
            'description' => __( 'Variable gift card only: let buyers enter any amount. Custom price overrides the selected denomination.', 'wc-giftcard' ),
        ] );

        echo '</div>';
    }

    // =========================================================================
    // Save
    // =========================================================================

    /**
     * Save the "Allow Custom Price" checkbox value on product save.
     *
     * @param int $post_id Product post ID.
     */
    public function save_checkbox( int $post_id ): void {
        update_post_meta(
            $post_id,
            self::META_ALLOW_CUSTOM_PRICE,
            isset( $_POST[ self::META_ALLOW_CUSTOM_PRICE ] ) ? 'yes' : 'no'
        );
    }
}
