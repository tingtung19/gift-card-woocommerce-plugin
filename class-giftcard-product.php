<?php
/**
 * Handles gift card product options, recipient email field,
 * and cart/order item meta storage.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class WC_GiftCard_Product {

    /** Meta key to flag a product as a gift card */
    const META_IS_GIFTCARD = '_is_giftcard';

    /** Meta key to store the recipient email on an order item */
    const META_RECIPIENT = '_giftcard_recipient_email';

    public function __construct() {
        // Add "Gift Card" checkbox in the product General tab (admin)
        add_action( 'woocommerce_product_options_general_product_data', [ $this, 'add_product_option' ] );
        add_action( 'woocommerce_process_product_meta',                 [ $this, 'save_product_option' ] );

        // Render recipient email field on the single product page
        add_action( 'woocommerce_before_add_to_cart_button', [ $this, 'render_recipient_field' ] );

        // Validate and persist the recipient field through cart → order
        add_filter( 'woocommerce_add_cart_item_data',             [ $this, 'save_recipient_to_cart' ],       10, 2 );
        add_action( 'woocommerce_checkout_create_order_line_item', [ $this, 'save_recipient_to_order_item' ], 10, 3 );

        // Display recipient email inside cart and order details
        add_filter( 'woocommerce_get_item_data', [ $this, 'display_recipient_in_cart' ], 10, 2 );
    }

    // -------------------------------------------------------------------------
    // Admin: product option
    // -------------------------------------------------------------------------

    /** Add a Gift Card checkbox to the product General tab */
    public function add_product_option(): void {
        woocommerce_wp_checkbox( [
            'id'          => self::META_IS_GIFTCARD,
            'label'       => __( 'Gift Card', 'mi-giftcard' ),
            'description' => __( 'Enable gift card functionality for this product.', 'mi-giftcard' ),
        ] );
    }

    /** Save the Gift Card checkbox value on product save */
    public function save_product_option( int $post_id ): void {
        update_post_meta(
            $post_id,
            self::META_IS_GIFTCARD,
            isset( $_POST[ self::META_IS_GIFTCARD ] ) ? 'yes' : 'no'
        );
    }

    // -------------------------------------------------------------------------
    // Frontend: recipient email field
    // -------------------------------------------------------------------------

    /** Render the optional recipient email field on the single product page */
    public function render_recipient_field(): void {
        global $product;

        if ( ! self::is_giftcard( $product->get_id() ) ) return;
        ?>
        <div class="giftcard-recipient-field" style="margin-bottom:15px;">
            <label for="giftcard_recipient_email">
                <?php esc_html_e( 'Recipient Email (optional)', 'mi-giftcard' ); ?>
            </label>
            <input
                type="email"
                id="giftcard_recipient_email"
                name="giftcard_recipient_email"
                placeholder="<?php esc_attr_e( 'Leave blank to use for yourself', 'mi-giftcard' ); ?>"
                style="width:100%; margin-top:5px;"
            />
            <small style="color:#777;">
                <?php esc_html_e( 'If provided, the coupon will only be usable by that email address and will be sent to both you and the recipient.', 'mi-giftcard' ); ?>
            </small>
        </div>
        <?php
    }

    // -------------------------------------------------------------------------
    // Cart: save recipient email
    // -------------------------------------------------------------------------

    /** Validate and attach the recipient email to the cart item data */
    public function save_recipient_to_cart( array $cart_item_data, int $product_id ): array {
        if ( ! self::is_giftcard( $product_id ) ) return $cart_item_data;

        $email = sanitize_email( $_POST['giftcard_recipient_email'] ?? '' );

        if ( ! empty( $email ) ) {
            if ( ! is_email( $email ) ) {
                wc_add_notice( __( 'The recipient email address is not valid.', 'mi-giftcard' ), 'error' );
            } else {
                $cart_item_data[ self::META_RECIPIENT ] = $email;
            }
        }

        return $cart_item_data;
    }

    // -------------------------------------------------------------------------
    // Order: persist recipient email to order item meta
    // -------------------------------------------------------------------------

    /** Copy the recipient email from cart item data into the order line item */
    public function save_recipient_to_order_item(
        \WC_Order_Item_Product $item,
        string $cart_item_key,
        array $cart_item
    ): void {
        if ( isset( $cart_item[ self::META_RECIPIENT ] ) ) {
            $item->update_meta_data( self::META_RECIPIENT, $cart_item[ self::META_RECIPIENT ] );
        }
    }

    // -------------------------------------------------------------------------
    // Display: show recipient email in cart / order details
    // -------------------------------------------------------------------------

    /** Display the recipient email as a line item detail in the cart */
    public function display_recipient_in_cart( array $item_data, array $cart_item ): array {
        if ( isset( $cart_item[ self::META_RECIPIENT ] ) ) {
            $item_data[] = [
                'key'   => __( 'Recipient Email', 'mi-giftcard' ),
                'value' => esc_html( $cart_item[ self::META_RECIPIENT ] ),
            ];
        }

        return $item_data;
    }

    // -------------------------------------------------------------------------
    // Helper
    // -------------------------------------------------------------------------

    /** Check whether the given product ID is marked as a gift card */
    public static function is_giftcard( int $product_id ): bool {
        return get_post_meta( $product_id, self::META_IS_GIFTCARD, true ) === 'yes';
    }
}
