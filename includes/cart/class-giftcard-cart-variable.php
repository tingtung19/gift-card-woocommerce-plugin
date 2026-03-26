<?php
/**
 * Cart: Variable gift card cart handler.
 *
 * Responsibilities:
 *  - Validate denomination / custom price selection on add-to-cart.
 *  - Resolve the final gift card amount (custom price overrides denomination).
 *  - Store the resolved price and denomination label in cart item meta.
 *  - Override the WooCommerce cart line item price so totals are correct.
 *  - Display the resolved amount in cart and order review tables.
 *  - Persist the resolved price and label into the order line item meta.
 *
 * Only acts on Variable gift card products. Simple gift card products
 * use their WooCommerce price directly and skip all logic in this class.
 *
 * Price resolution priority (highest first):
 *  1. Custom price  — if the field is filled and the product allows it.
 *                     Value entered by buyer is treated as a tax-inclusive amount
 *                     (i.e. the buyer types $100 and means $100).
 *  2. Denomination  — resolved server-side via wc_get_price_including_tax()
 *                     using the variation_id posted from the denomination button.
 *                     We re-derive the price on the server rather than trusting
 *                     the POSTed price value to prevent tampering.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class WC_GiftCard_Cart_Variable extends WC_GiftCard_Base {

    public function __construct() {
        // Resolve the final price and attach it to cart item data
        add_filter( 'woocommerce_add_cart_item_data', [ $this, 'resolve_and_attach_price' ], 10, 3 );

        // Override the WooCommerce cart item price before totals are calculated
        // add_action( 'woocommerce_before_calculate_totals', [ $this, 'apply_resolved_price_to_cart' ], 20 );

        // Display the resolved amount as a cart item detail
        add_filter( 'woocommerce_get_item_data', [ $this, 'display_resolved_price_in_cart' ], 10, 2 );

        // Persist the resolved price and label to the order line item
        add_action( 'woocommerce_checkout_create_order_line_item', [ $this, 'persist_price_to_order_item' ], 10, 3 );
    }

    // =========================================================================
    // Cart: resolve and attach price
    // =========================================================================

    /**
     * Validate the buyer's denomination / custom price input, resolve the
     * final gift card amount, and store it in the cart item data array.
     *
     * Skipped for simple products — only Variable gift cards are handled here.
     *
     * @param array $cart_item_data Existing cart item data.
     * @param int   $product_id    Parent product ID.
     * @param int   $variation_id  Selected variation ID.
     * @return array Updated cart item data, or unchanged on validation failure.
     */
    public function resolve_and_attach_price( array $cart_item_data, int $product_id, int $variation_id ): array {
        if ( ! self::is_giftcard( $product_id ) ) return $cart_item_data;

        // Only act on variable products
        $product = wc_get_product( $product_id );
        if ( ! $product instanceof \WC_Product_Variable ) return $cart_item_data;

        $custom_price_raw = $_POST['giftcard_custom_price'] ?? '';
        $denom_label      = sanitize_text_field( $_POST['giftcard_label'] ?? '' );
        $allow_custom     = self::allows_custom_price( $product_id );

        // ── Resolve ──────────────────────────────────────────────────────────
        if ( $allow_custom && $custom_price_raw !== '' ) {
            // Custom price: buyer types an inclusive amount (e.g. $100 means $100)
            $custom_price = (float) $custom_price_raw;

            if ( $custom_price <= 0 ) {
                wc_add_notice( __( 'Custom amount must be greater than zero.', 'wc-giftcard' ), 'error' );
                return $cart_item_data;
            }

            $final_price = $custom_price;
            $final_label = __( 'Custom Amount', 'wc-giftcard' );

        } elseif ( $variation_id > 0 ) {
            // Denomination: resolve the price server-side from the variation object
            // using wc_get_price_including_tax() so the stored amount always equals
            // the full display price the buyer saw — regardless of tax settings.
            $variation = wc_get_product( $variation_id );

            if ( ! $variation || ! $variation->is_purchasable() ) {
                wc_add_notice( __( 'The selected gift card denomination is not available.', 'wc-giftcard' ), 'error' );
                return $cart_item_data;
            }

            $final_price = (float) wc_get_price_including_tax( $variation );
            $final_label = $denom_label;

        } else {
            // Neither denomination nor custom price was provided
            wc_add_notice(
                __( 'Please select a gift card denomination or enter a custom amount.', 'wc-giftcard' ),
                'error'
            );
            return $cart_item_data;
        }

        $cart_item_data[ self::META_RESOLVED_PRICE ]  = $final_price;
        $cart_item_data[ self::META_VARIATION_LABEL ] = $final_label;

        return $cart_item_data;
    }

    // =========================================================================
    // Cart: apply resolved price before totals
    // =========================================================================

    /**
     * Override the WooCommerce cart line item price with the resolved gift card amount.
     *
     * WooCommerce will later add tax on top of whatever price we set here.
     * Because our resolved price is already the tax-inclusive display price,
     * we must set the EXCLUDING-tax price on the product object so that after
     * WooCommerce adds tax back the final total equals the resolved price exactly.
     *
     * Formula: price_ex_tax = resolved_price / (1 + tax_rate)
     *
     * If the store has no tax configured, or the tax rate is zero, the division
     * has no effect and the price is set as-is.
     *
     * Only overrides items that carry META_RESOLVED_PRICE in their cart item data,
     * leaving simple gift card products (no such key) completely untouched.
     *
     * @param \WC_Cart $cart The WooCommerce cart instance.
     */
    public function apply_resolved_price_to_cart( \WC_Cart $cart ): void {
        if ( is_admin() && ! defined( 'DOING_AJAX' ) ) return;

        foreach ( $cart->get_cart() as $cart_item ) {
            if ( ! isset( $cart_item[ self::META_RESOLVED_PRICE ] ) ) continue;

            /** @var \WC_Product $product_data */
            $product_data  = $cart_item['data'];
            $resolved_incl = (float) $cart_item[ self::META_RESOLVED_PRICE ];

            // Derive the tax rate for this product so we can back-calculate ex-tax price
            $tax_rates = \WC_Tax::get_rates( $product_data->get_tax_class() );
            $tax_rate  = array_sum( array_column( $tax_rates, 'rate' ) ) / 100; // e.g. 0.21

            // Back-calculate the ex-tax price: incl / (1 + rate)
            // WooCommerce will then add the tax again, yielding the original inclusive price
            $price_ex_tax = $tax_rate > 0
                ? $resolved_incl / ( 1 + $tax_rate )
                : $resolved_incl;

            $product_data->set_price( $price_ex_tax );
        }
    }

    // =========================================================================
    // Display: cart and order review
    // =========================================================================

    /**
     * Show the resolved gift card amount (and denomination label) as a
     * visible line item detail row in the cart and order review tables.
     *
     * @param array $item_data Existing item detail rows.
     * @param array $cart_item Cart item data array.
     * @return array Updated item detail rows.
     */
    public function display_resolved_price_in_cart( array $item_data, array $cart_item ): array {
        if ( ! isset( $cart_item[ self::META_RESOLVED_PRICE ] ) ) return $item_data;

        $label = $cart_item[ self::META_VARIATION_LABEL ] ?? '';

        $item_data[] = [
            'key'   => __( 'Gift Card Value', 'wc-giftcard' ),
            'value' => wc_price( $cart_item[ self::META_RESOLVED_PRICE ] )
                       . ( $label ? ' <small>(' . esc_html( $label ) . ')</small>' : '' ),
        ];

        return $item_data;
    }

    // =========================================================================
    // Order: persist resolved price to line item
    // =========================================================================

    /**
     * Copy the resolved price and denomination label from cart item data
     * into the order line item meta for later coupon generation.
     *
     * @param \WC_Order_Item_Product $item          Order line item.
     * @param string                 $cart_item_key Cart item hash key.
     * @param array                  $cart_item     Cart item data array.
     */
    public function persist_price_to_order_item(
        \WC_Order_Item_Product $item,
        string $cart_item_key,
        array $cart_item
    ): void {
        if ( isset( $cart_item[ self::META_RESOLVED_PRICE ] ) ) {
            $item->update_meta_data( self::META_RESOLVED_PRICE,   (float) $cart_item[ self::META_RESOLVED_PRICE ] );
        }

        if ( isset( $cart_item[ self::META_VARIATION_LABEL ] ) ) {
            $item->update_meta_data( self::META_VARIATION_LABEL, $cart_item[ self::META_VARIATION_LABEL ] );
        }
    }
}
