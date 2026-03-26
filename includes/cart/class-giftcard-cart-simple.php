<?php
/**
 * Cart: Simple gift card cart handler.
 *
 * Responsibilities:
 *  - Validate and attach the recipient email to simple gift card cart items.
 *  - Persist the recipient email from the cart item into the order line item.
 *  - Display the recipient email in cart and order detail views.
 *
 * Note: Price handling is not required here because simple gift card products
 * use their WooCommerce product price directly — no override is needed.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class WC_GiftCard_Cart_Simple extends WC_GiftCard_Base {

    public function __construct() {
        // Validate and attach recipient email when item is added to cart
        add_filter( 'woocommerce_add_cart_item_data', [ $this, 'attach_recipient_email' ], 10, 2 );

        // Copy recipient email from cart item data into the order line item
        add_action( 'woocommerce_checkout_create_order_line_item', [ $this, 'persist_recipient_to_order_item' ], 10, 3 );

        // Show recipient email in cart and order detail summaries
        add_filter( 'woocommerce_get_item_data', [ $this, 'display_recipient_in_cart' ], 10, 2 );
    }

    // =========================================================================
    // Cart: attach recipient email
    // =========================================================================

    /**
     * Validate the submitted recipient email and store it in cart item data.
     *
     * Runs for all gift card products (simple and variable). The recipient
     * email field is rendered by WC_GiftCard_Frontend_Recipient for both types.
     *
     * @param array $cart_item_data Existing cart item data.
     * @param int   $product_id    Product being added to the cart.
     * @return array Updated cart item data.
     */
    public function attach_recipient_email( array $cart_item_data, int $product_id ): array {
        if ( ! self::is_giftcard( $product_id ) ) return $cart_item_data;

        $email = sanitize_email( $_POST['giftcard_recipient_email'] ?? '' );

        if ( empty( $email ) ) return $cart_item_data; // Field left blank — coupon is unrestricted

        if ( ! is_email( $email ) ) {
            wc_add_notice( __( 'The recipient email address is not valid.', 'wc-giftcard' ), 'error' );
            return $cart_item_data;
        }

        $cart_item_data[ self::META_RECIPIENT ] = $email;

        return $cart_item_data;
    }

    // =========================================================================
    // Order: persist recipient email to line item
    // =========================================================================

    /**
     * Copy the recipient email from cart item data into the order line item meta
     * so WC_GiftCard_Order can read it during coupon generation.
     *
     * @param \WC_Order_Item_Product $item          Order line item.
     * @param string                 $cart_item_key Cart item hash key.
     * @param array                  $cart_item     Cart item data array.
     */
    public function persist_recipient_to_order_item(
        \WC_Order_Item_Product $item,
        string $cart_item_key,
        array $cart_item
    ): void {
        if ( isset( $cart_item[ self::META_RECIPIENT ] ) ) {
            $item->update_meta_data( self::META_RECIPIENT, $cart_item[ self::META_RECIPIENT ] );
        }
    }

    // =========================================================================
    // Display: cart and order detail
    // =========================================================================

    /**
     * Add the recipient email as a visible line item detail in the cart
     * and order review tables.
     *
     * @param array $item_data Existing item detail rows.
     * @param array $cart_item Cart item data array.
     * @return array Updated item detail rows.
     */
    public function display_recipient_in_cart( array $item_data, array $cart_item ): array {
        if ( isset( $cart_item[ self::META_RECIPIENT ] ) ) {
            $item_data[] = [
                'key'   => __( 'Recipient Email', 'wc-giftcard' ),
                'value' => esc_html( $cart_item[ self::META_RECIPIENT ] ),
            ];
        }

        return $item_data;
    }
}
