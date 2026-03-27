<?php
/**
 * Frontend: Recipient email field.
 *
 * Renders the optional "Recipient Email" input on the single product page
 * for both Simple and Variable gift card products.
 *
 * Hooked at priority 15 so it always appears below the denomination
 * buttons rendered by WC_GiftCard_Frontend_Variable (priority 5).
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class WC_GiftCard_Frontend_Recipient extends WC_GiftCard_Base {

    public function __construct() {
        // Render after denomination buttons (priority 5) and before add-to-cart button
        add_action( 'woocommerce_before_add_to_cart_button', [ $this, 'render_field' ], 15 );
    }

    // =========================================================================
    // Render
    // =========================================================================

    /**
     * Output the recipient email input field.
     * Skipped for products that are not gift cards.
     */
    public function render_field(): void {
        global $product;

        if ( ! self::is_giftcard( $product->get_id() ) ) return;
        ?>
        <div class="giftcard-recipient-field" style="margin-bottom:15px;">

            <label for="giftcard_recipient_email"
                   style="font-weight:600; display:block; margin-bottom:5px;">
                <?php esc_html_e( 'E-mailadres van de ontvanger (optioneel)', 'wc-giftcard' ); ?>
            </label>

            <input
                type="email"
                id="giftcard_recipient_email"
                name="giftcard_recipient_email"
                placeholder="<?php esc_attr_e( 'email@domain.com', 'wc-giftcard' ); ?>"
                style="width:100%; padding:9px 12px; border:2px solid #ccc; border-radius:6px; font-size:14px;"
            />

            <small style="color:#777; margin-top:4px; display:block;">
                <?php esc_html_e( 'Indien opgegeven, is de kortingsbon alleen geldig voor dat e-mailadres en wordt deze zowel naar u als naar de ontvanger verzonden.', 'wc-giftcard' ); ?>
            </small>

        </div>
        <?php
    }
}
