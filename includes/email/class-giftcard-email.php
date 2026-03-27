<?php
/**
 * Email: Gift card notification mailer.
 *
 * Builds and sends the gift card email to:
 *  - The buyer (always).
 *  - The recipient (when a different recipient email was provided).
 *
 * Uses wp_mail() with an HTML body. No WooCommerce email template system
 * is used here so the plugin remains independent of theme email templates.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class WC_GiftCard_Email extends WC_GiftCard_Base {

    /**
     * Compose and dispatch a gift card notification email.
     *
     * @param string      $to              Destination email address.
     * @param string      $coupon_code     The generated coupon code to display.
     * @param float       $amount          Monetary value of the coupon.
     * @param string      $variation_label Human-readable denomination label
     *                                     (e.g. "Gold", "Custom Amount", or empty string).
     * @param string|null $recipient_email Email that the coupon is restricted to,
     *                                     or null if the coupon is unrestricted.
     * @param bool        $is_buyer        True when sending to the buyer;
     *                                     false when sending to the recipient.
     */
    public function send(
        string  $to,
        string  $coupon_code,
        float   $amount,
        string  $variation_label = '',
        ?string $recipient_email = null,
        bool    $is_buyer        = true
    ): void {
        $subject = $this->build_subject( $coupon_code, $is_buyer );
        $body    = $this->build_body( $coupon_code, $amount, $variation_label, $recipient_email, $is_buyer );
        $headers = [ 'Content-Type: text/html; charset=UTF-8' ];

        wp_mail( $to, $subject, $body, $headers );
    }

    // =========================================================================
    // Subject line
    // =========================================================================

    /**
     * Build the email subject line.
     *
     * @param string $coupon_code Coupon code (included in the buyer subject).
     * @param bool   $is_buyer    True for the buyer, false for the recipient.
     * @return string
     */
    private function build_subject( string $coupon_code, bool $is_buyer ): string {
        if ( $is_buyer ) {
            /* translators: %s: coupon code */
            return sprintf( __( 'Your Gift Card – Code: %s', 'wc-giftcard' ), $coupon_code );
        }

        return __( 'You have received a Gift Card!', 'wc-giftcard' );
    }

    // =========================================================================
    // Body buildermi-giftcard
    // =========================================================================

    /**
     * Build the full HTML email body.
     *
     * @param string      $coupon_code     Coupon code shown prominently.
     * @param float       $amount          Coupon value in the store currency.
     * @param string      $variation_label Denomination label; empty string if not applicable.
     * @param string|null $recipient_email Restriction email; null if unrestricted.
     * @param bool        $is_buyer        Controls the personalised intro sentence.
     * @return string Full HTML string ready for wp_mail().
     */
    private function build_body(
        string  $coupon_code,
        float   $amount,
        string  $variation_label,
        ?string $recipient_email,
        bool    $is_buyer
    ): string {
        $shop_name        = esc_html( get_bloginfo( 'name' ) );
        $shop_url         = esc_url( home_url() );
        $formatted_amount = wc_price( $amount );

        $intro            = $this->build_intro( $shop_name, $is_buyer );
        $label_html       = $this->build_label_html( $variation_label );
        $restriction_note = $this->build_restriction_note( $recipient_email );
        $balance_note     = $this->build_balance_note();

        $label_code   = esc_html__( 'Your Coupon Code:', 'mi-giftcard' );
        $label_redeem = esc_html__( 'Use the code above at checkout on', 'mi-giftcard' );

        return <<<HTML
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
        </head>
        <body style="font-family:Arial,sans-serif; background:#f5f5f5; padding:30px; margin:0;">
            <div style="max-width:520px; margin:auto; background:#fff; border-radius:8px;
                        padding:30px; box-shadow:0 2px 8px rgba(0,0,0,0.1);">

                <h2 style="color:#333; margin-top:0;">🎁 Gift Card</h2>
                <p style="color:#555;">{$intro}</p>

                <div style="background:#f0f8f0; border:2px dashed #4CAF50; border-radius:8px;
                            padding:20px; text-align:center; margin:20px 0;">
                    <p style="margin:0 0 6px; font-size:13px; color:#555;">{$label_code}</p>
                    <h1 style="margin:0 0 10px; font-size:32px; color:#2e7d32; letter-spacing:4px;">
                        {$coupon_code}
                    </h1>
                    <p style="margin:0; font-size:20px; font-weight:700; color:#388e3c;">
                        {$formatted_amount}
                    </p>
                    {$label_html}
                </div>

                {$restriction_note}

                {$balance_note}

                <p style="color:#555;">
                    {$label_redeem}
                    <a href="{$shop_url}" style="color:#388e3c;">{$shop_url}</a>
                </p>

                <hr style="border:none; border-top:1px solid #eee; margin:20px 0;">
                <p style="font-size:12px; color:#999; margin-bottom:0;">© {$shop_name}</p>

            </div>
        </body>
        </html>
        HTML;
    }

    // =========================================================================
    // Body fragment builders
    // =========================================================================

    /**
     * Build the personalised intro sentence.
     *
     * @param string $shop_name Escaped shop name.
     * @param bool   $is_buyer  True for buyer email, false for recipient email.
     * @return string HTML fragment.
     */
    private function build_intro( string $shop_name, bool $is_buyer ): string {
        if ( $is_buyer ) {
            return sprintf(
                /* translators: %s: shop name */
                esc_html__( 'Thank you for purchasing a Gift Card at %s!', 'wc-giftcard' ),
                '<strong>' . $shop_name . '</strong>'
            );
        }

        return sprintf(
            /* translators: %s: shop name */
            esc_html__( 'Someone sent you a Gift Card from %s!', 'wc-giftcard' ),
            '<strong>' . $shop_name . '</strong>'
        );
    }

    /**
     * Build the denomination label paragraph shown inside the coupon block.
     * Returns an empty string when no label is available.
     *
     * @param string $variation_label Denomination label or empty string.
     * @return string HTML fragment.
     */
    private function build_label_html( string $variation_label ): string {
        if ( empty( $variation_label ) ) return '';

        return '<p style="margin:4px 0 0; font-size:13px; color:#555;">'
               . esc_html( $variation_label )
               . '</p>';
    }

    /**
     * Build the balance tracking info paragraph.
     *
     * Explains that this is a reusable gift card with balance tracking.
     *
     * @return string HTML fragment.
     */
    private function build_balance_note(): string {
        $account_url = wc_get_account_endpoint_url( 'giftcards' );

        return '<p style="background:#e3f2fd; border-left:4px solid #1976d2; padding:12px; margin:15px 0; font-size:13px; color:#555;">'
               . '💡 ' . esc_html__( 'This is a reusable gift card. You can use it multiple times until the balance is fully consumed. ', 'mi-giftcard' )
               . ( ! empty( $account_url ) ? 'View your balance and transaction history in your <a href="' . esc_url( $account_url ) . '" style="color:#1976d2; font-weight:600;">account</a>.' : '' )
               . '</p>';
    }

    /**
     * Build the coupon restriction notice paragraph.
     *
     * When a recipient email is set the notice warns that only that address
     * can redeem the coupon. Otherwise it confirms that anyone can use it.
     *
     * @param string|null $recipient_email Restriction email or null.
     * @return string HTML fragment.
     */
    private function build_restriction_note( ?string $recipient_email ): string {
        if ( ! empty( $recipient_email ) ) {
            return '<p>⚠️ ' . sprintf(
                /* translators: %s: recipient email address */
                esc_html__( 'This coupon can only be used by: %s', 'wc-giftcard' ),
                '<strong>' . esc_html( $recipient_email ) . '</strong>'
            ) . '</p>';
        }

        return '<p>✅ ' . esc_html__( 'This coupon can be used by anyone.', 'wc-giftcard' ) . '</p>';
    }
}
