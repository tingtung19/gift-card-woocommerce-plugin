<?php
/**
 * Frontend: Variable gift card denomination buttons + custom price field.
 *
 * Renders clickable price buttons built from the product's variations,
 * an optional custom price input (when enabled by admin), and the
 * JavaScript that keeps WooCommerce's hidden variation fields in sync.
 *
 * Also suppresses the default WooCommerce variation dropdown for gift card
 * variable products, replacing it entirely with the button UI.
 *
 * Hooked at priority 5 — renders before the recipient email field (15).
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class WC_GiftCard_Frontend_Variable extends WC_GiftCard_Base {

    public function __construct() {
        // Render denomination buttons before the add-to-cart button
        add_action( 'woocommerce_before_add_to_cart_button', [ $this, 'render_denomination_ui' ], 5 );

        // Remove the default WooCommerce variation dropdown for gift card products
        add_filter( 'woocommerce_dropdown_variation_attribute_options_html', [ $this, 'suppress_default_dropdown' ], 10, 2 );
    }

    // =========================================================================
    // Render: denomination buttons
    // =========================================================================

    /**
     * Render denomination buttons and the optional custom price field.
     * Skipped for non-variable or non-gift-card products.
     */
    public function render_denomination_ui(): void {
        global $product;

        if ( ! $product instanceof \WC_Product_Variable ) return;
        if ( ! self::is_giftcard( $product->get_id() ) ) return;

        $variations         = $this->build_variation_list( $product );
        $allow_custom_price = self::allows_custom_price( $product->get_id() );

        if ( empty( $variations ) && ! $allow_custom_price ) return;
        ?>
        <div class="giftcard-denomination-wrapper" style="margin-bottom:20px;">

            <!-- <?php $this->render_buttons( $variations ); ?> -->
            <?php $this->render_hidden_fields( $variations ); ?>
            <!-- <?php if ( $allow_custom_price ) $this->render_custom_price_field(); ?> -->

        </div>

        <?php $this->render_styles_and_scripts( ! empty( $variations ), $allow_custom_price ); ?>
        <?php
    }

    // =========================================================================
    // Render: button group
    // =========================================================================

    /**
     * Output the denomination button strip.
     *
     * @param array $variations Processed variation list from build_variation_list().
     */
    private function render_buttons( array $variations ): void {
        if ( empty( $variations ) ) return;
        ?>
        <p style="font-weight:600; margin-bottom:8px;">
            <?php esc_html_e( 'Select Amount', 'wc-giftcard' ); ?>
        </p>

        <div class="giftcard-denomination-buttons"
             style="display:flex; flex-wrap:wrap; gap:10px; margin-bottom:15px;">

            <?php foreach ( $variations as $v ) :
                $formatted = wc_price( $v['display_price'] );
            ?>
                <button
                    type="button"
                    class="giftcard-denomination-btn"
                    data-variation-id="<?php echo esc_attr( $v['variation_id'] ); ?>"
                    data-price="<?php echo esc_attr( $v['display_price'] ); ?>"
                    data-label="<?php echo esc_attr( $v['variation_label'] ); ?>"
                    data-attributes="<?php echo esc_attr( wp_json_encode( $v['attributes'] ) ); ?>"
                    style="padding:10px 18px; border:2px solid #ccc; border-radius:6px;
                           background:#fff; cursor:pointer; font-size:15px; font-weight:600;
                           transition:all .2s;"
                >
                    <?php echo wp_kses_post( $formatted ); ?>
                    <?php if ( $v['variation_label'] ) : ?>
                        <small style="display:block; font-weight:400; font-size:11px; color:#666;">
                            <?php echo esc_html( $v['variation_label'] ); ?>
                        </small>
                    <?php endif; ?>
                </button>
            <?php endforeach; ?>

        </div>
        <?php
    }

    // =========================================================================
    // Render: hidden fields for WooCommerce variation system
    // =========================================================================

    /**
     * Output hidden fields that WooCommerce requires to identify the selected
     * variation when the form is submitted.
     *
     * @param array $variations Processed variation list.
     */
    private function render_hidden_fields( array $variations ): void {
        if ( empty( $variations ) ) return;
        ?>
        <input type="hidden" name="variation_id"   id="giftcard_variation_id"   value="">
        <input type="hidden" name="giftcard_price" id="giftcard_selected_price" value="">
        <input type="hidden" name="giftcard_label" id="giftcard_selected_label" value="">

        <?php
        // One hidden field per variation attribute so WooCommerce validates the combination
        $first = reset( $variations );
        foreach ( array_keys( $first['attributes'] ) as $attr_name ) :
        ?>
            <input type="hidden"
                   name="<?php echo esc_attr( $attr_name ); ?>"
                   class="giftcard-attr-field"
                   data-attr="<?php echo esc_attr( $attr_name ); ?>"
                   value="">
        <?php endforeach; ?>
        <?php
    }

    // =========================================================================
    // Render: custom price field
    // =========================================================================

    /**
     * Output the custom price number input.
     * Only called when the product has "Allow Custom Price" enabled.
     */
    private function render_custom_price_field(): void {
        $currency_symbol = get_woocommerce_currency_symbol();
        ?>
        <div class="giftcard-custom-price-wrapper" style="margin-top:5px;">

            <label for="giftcard_custom_price"
                   style="font-weight:600; display:block; margin-bottom:5px;">
                <?php esc_html_e( 'Or Enter Custom Amount', 'wc-giftcard' ); ?>
            </label>

            <div style="display:flex; align-items:center; gap:8px;">
                <span style="font-size:18px; font-weight:600; color:#555;">
                    <?php echo esc_html( $currency_symbol ); ?>
                </span>
                <input
                    type="number"
                    id="giftcard_custom_price"
                    name="giftcard_custom_price"
                    min="0.01"
                    step="0.01"
                    placeholder="<?php esc_attr_e( 'Enter amount', 'wc-giftcard' ); ?>"
                    style="width:160px; padding:9px 12px; border:2px solid #ccc;
                           border-radius:6px; font-size:15px;"
                />
            </div>

            <small style="color:#777; margin-top:4px; display:block;">
                <?php esc_html_e( 'Custom amount overrides the selected denomination above.', 'wc-giftcard' ); ?>
            </small>

        </div>
        <?php
    }

    // =========================================================================
    // Render: inline CSS + JavaScript
    // =========================================================================

    /**
     * Output the inline styles and JS that manage button active state and
     * keep WooCommerce's hidden variation/attribute fields in sync with
     * whichever denomination button the buyer clicks.
     *
     * @param bool $has_variations   Whether denomination buttons are rendered.
     * @param bool $has_custom_price Whether the custom price field is rendered.
     */
    private function render_styles_and_scripts( bool $has_variations, bool $has_custom_price ): void {
        ?>
        <style>
            .giftcard-denomination-btn.selected {
                border-color: #2e7d32 !important;
                background:   #f0f8f0 !important;
                color:        #2e7d32 !important;
            }
            .giftcard-denomination-btn:hover {
                border-color: #4CAF50;
                background:   #f9fff9;
            }
            #giftcard_custom_price:focus {
                border-color: #4CAF50;
                outline:      none;
                box-shadow:   0 0 0 3px rgba(76,175,80,.15);
            }
        </style>

        <script>
        (function () {
            'use strict';

            <?php if ( $has_variations ) : ?>

            var buttons            = document.querySelectorAll('.giftcard-denomination-btn');
            var variationIdField   = document.getElementById('giftcard_variation_id');
            var selectedPriceField = document.getElementById('giftcard_selected_price');
            var selectedLabelField = document.getElementById('giftcard_selected_label');

            /**
             * Activate a denomination button and sync all WooCommerce hidden fields.
             * @param {HTMLElement} btn The clicked button element.
             */
            function activateButton( btn ) {
                buttons.forEach( function (b) { b.classList.remove('selected'); } );
                btn.classList.add('selected');

                variationIdField.value   = btn.dataset.variationId;
                selectedPriceField.value = btn.dataset.price;
                selectedLabelField.value = btn.dataset.label;

                // Populate each attribute hidden field so WooCommerce can validate
                var attributes = JSON.parse( btn.dataset.attributes || '{}' );
                Object.keys( attributes ).forEach( function (attrName) {
                    var field = document.querySelector(
                        '.giftcard-attr-field[data-attr="' + attrName + '"]'
                    );
                    if ( field ) field.value = attributes[ attrName ];
                });
            }

            buttons.forEach( function (btn) {
                btn.addEventListener('click', function () {
                    activateButton( btn );

                    <?php if ( $has_custom_price ) : ?>
                    // Denomination click clears the custom price input
                    var customInput = document.getElementById('giftcard_custom_price');
                    if ( customInput ) customInput.value = '';
                    <?php endif; ?>
                });
            });

            <?php endif; ?>

            <?php if ( $has_custom_price && $has_variations ) : ?>
            // Typing a custom amount deselects all denomination buttons so the
            // buyer's intent is visually clear. The server always takes custom
            // price over denomination when both values are present.
            var customPriceInput = document.getElementById('giftcard_custom_price');
            if ( customPriceInput ) {
                customPriceInput.addEventListener('input', function () {
                    if ( this.value ) {
                        buttons.forEach( function (b) { b.classList.remove('selected'); } );
                    }
                });
            }
            <?php endif; ?>

        })();
        </script>
        <?php
    }

    // =========================================================================
    // Suppress default WooCommerce dropdown
    // =========================================================================

    /**
     * Return an empty string instead of the default variation dropdown HTML
     * for gift card variable products. Our denomination buttons replace it.
     *
     * @param string $html Default dropdown HTML.
     * @param array  $args Dropdown render arguments including the product object.
     * @return string
     */
    public function suppress_default_dropdown( string $html, array $args ): string {
        $product = $args['product'] ?? null;

        if ( $product && self::is_giftcard( $product->get_id() ) ) {
            return ''; // Denomination buttons are used instead
        }

        return $html;
    }

    // =========================================================================
    // Data helper
    // =========================================================================

    /**
     * Build a sorted list of available variations for the denomination buttons.
     * Each entry contains: variation_id, display_price, attributes, variation_label.
     *
     * Skips variations that are not purchasable.
     * Sorted ascending by price.
     *
     * @param \WC_Product_Variable $product
     * @return array[]
     */
    private function build_variation_list( \WC_Product_Variable $product ): array {
        $result = [];

        foreach ( $product->get_available_variations() as $variation_data ) {
            $variation_id = (int) $variation_data['variation_id'];
            $variation    = wc_get_product( $variation_id );

            if ( ! $variation || ! $variation->is_purchasable() ) continue;

            // Derive a human-readable label from attribute values, e.g. "Gold / Large"
            $attr_labels = [];
            foreach ( $variation_data['attributes'] as $attr_value ) {
                if ( $attr_value ) {
                    $attr_labels[] = ucfirst( str_replace( '-', ' ', $attr_value ) );
                }
            }

            $result[] = [
                'variation_id'    => $variation_id,
                'display_price'   => (float) wc_get_price_including_tax( $variation ),
                'attributes'      => $variation_data['attributes'],
                'variation_label' => implode( ' / ', $attr_labels ),
            ];
        }

        usort( $result, fn( $a, $b ) => $a['display_price'] <=> $b['display_price'] );

        return $result;
    }
}
