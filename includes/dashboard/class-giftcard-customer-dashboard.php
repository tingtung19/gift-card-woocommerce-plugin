<?php
/**
 * Dashboard: Customer gift card balance and history dashboard.
 *
 * Displays gift card coupons belonging to the customer with current balance
 * and transaction history in the My Account page.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class WC_GiftCard_Customer_Dashboard extends WC_GiftCard_Base {

    public function __construct() {
        // Add gift card endpoint to My Account
        add_action( 'init', [ $this, 'add_endpoint' ] );

        // Add navigation link to My Account menu after orders

        add_filter( 'woocommerce_account_menu_items', [ $this, 'add_menu_item' ], 10, 1 );

        // Load gift card dashboard content
        add_action( 'woocommerce_account_giftcards_endpoint', [ $this, 'render_dashboard' ] );

        // Add shortcode for direct display
        add_shortcode( 'wc_giftcard_dashboard', [ $this, 'shortcode_handler' ] );
    }

    // =========================================================================
    // Endpoint registration
    // =========================================================================

    /**
     * Register custom My Account endpoint for gift cards.
     */
    public function add_endpoint(): void {
        add_rewrite_endpoint( 'giftcards', EP_ROOT | EP_PAGES );
    }

    // =========================================================================
    // Menu integration
    // =========================================================================

    /**
     * Add "Gift Cards" link to My Account menu.
     *
     * @param array $items Menu items.
     * @return array Modified menu items.
     */
    public function add_menu_item( array $items ): array {
        // Add "Gift Cards" link after orders
        $new_items = [];
        foreach ( $items as $key => $item ) {
            $new_items[ $key ] = $item;
            if ( 'orders' === $key ) {
                $new_items['giftcards'] = __( 'Gift Cards', 'mi-giftcard' );
            }
        }
        
        return $new_items;
    }

    // =========================================================================
    // Dashboard rendering
    // =========================================================================

    /**
     * Render the gift card dashboard for the current customer.
     */
    public function render_dashboard(): void {
        if ( ! is_user_logged_in() ) {
            wc_print_notice( __( 'Please log in to view your gift cards.', 'mi-giftcard' ), 'error' );
            return;
        }

        $current_user = wp_get_current_user();
        $user_email = $current_user->user_email;

        // Get all gift card coupons where this user is the buyer or recipient
        $giftcards = $this->get_customer_giftcards( $user_email );

        if ( empty( $giftcards ) ) {
            echo '<p>' . esc_html__( 'You don\'t have any gift cards yet.', 'mi-giftcard' ) . '</p>';
            return;
        }

        echo '<div class="wc-giftcard-dashboard">';

        foreach ( $giftcards as $giftcard ) {
            $this->render_giftcard_card( $giftcard );
        }

        echo '</div>';

        // Add CSS for dashboard
        $this->output_dashboard_styles();
    }

    /**
     * Render a single gift card card with balance and history.
     *
     * @param array $giftcard Gift card data.
     */
    private function render_giftcard_card( array $giftcard ): void {
        $coupon_code = $giftcard['code'];
        $initial_amount = $giftcard['initial_amount'];
        $current_balance = $giftcard['current_balance'];
        $used_amount = $initial_amount - $current_balance;
        $progress_percent = $initial_amount > 0 ? round( ( $used_amount / $initial_amount ) * 100 ) : 0;

        $currency_symbol = get_woocommerce_currency_symbol();
        ?>

        <div class="wc-giftcard-card">
            <div class="gc-header">
                <div class="gc-code-section">
                    <h3 class="gc-code"><?php echo esc_html( $coupon_code ); ?></h3>
                    <button class="gc-copy-btn" data-code="<?php echo esc_attr( $coupon_code ); ?>">
                        📋 <?php esc_html_e( 'Copy Code', 'mi-giftcard' ); ?>
                    </button>
                </div>
            </div>

            <div class="gc-balance-section">
                <div class="gc-balance-info">
                    <span class="gc-label"><?php esc_html_e( 'Initial Amount:', 'mi-giftcard' ); ?></span>
                    <span class="gc-amount"><?php echo esc_html( $currency_symbol . number_format( $initial_amount, 2 ) ); ?></span>
                </div>

                <div class="gc-balance-info">
                    <span class="gc-label"><?php esc_html_e( 'Current Balance:', 'mi-giftcard' ); ?></span>
                    <span class="gc-amount-current"><?php echo esc_html( $currency_symbol . number_format( $current_balance, 2 ) ); ?></span>
                </div>

                <div class="gc-progress">
                    <div class="gc-progress-bar">
                        <div class="gc-progress-fill" style="width: <?php echo esc_attr( $progress_percent ); ?>%"></div>
                    </div>
                    <p class="gc-progress-text">
                        <?php
                        printf(
                            /* translators: %s: percentage used */
                            esc_html__( '%s%% Used', 'mi-giftcard' ),
                            esc_html( $progress_percent )
                        );
                        ?>
                    </p>
                </div>
            </div>

            <?php if ( ! empty( $giftcard['history'] ) ) : ?>
                <div class="gc-history-section">
                    <h4><?php esc_html_e( 'Transaction History', 'mi-giftcard' ); ?></h4>
                    <div class="gc-history-table">
                        <table>
                            <thead>
                                <tr>
                                    <th><?php esc_html_e( 'Date', 'mi-giftcard' ); ?></th>
                                    <th><?php esc_html_e( 'Amount Used', 'mi-giftcard' ); ?></th>
                                    <th><?php esc_html_e( 'Remaining', 'mi-giftcard' ); ?></th>
                                    <th><?php esc_html_e( 'Order ID', 'mi-giftcard' ); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ( $giftcard['history'] as $transaction ) : ?>
                                    <tr>
                                        <td><?php echo esc_html( wp_date( 'M d, Y H:i', $transaction['timestamp'] ) ); ?></td>
                                        <td><?php echo esc_html( $currency_symbol . number_format( $transaction['amount'], 2 ) ); ?></td>
                                        <td><?php echo esc_html( $currency_symbol . number_format( $transaction['remaining_balance'], 2 ) ); ?></td>
                                        <td><a href="<?php echo esc_url( admin_url( 'post.php?post=' . $transaction['order_id'] . '&action=edit' ) ); ?>#order-<?php echo esc_attr( $transaction['order_id'] ); ?>">#<?php echo esc_html( $transaction['order_id'] ); ?></a></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <?php
    }

    /**
     * Output CSS styles for the dashboard.
     */
    private function output_dashboard_styles(): void {
        ?>
        <style>
            .wc-giftcard-dashboard {
                display: grid;
                gap: 20px;
                margin: 20px 0;
            }

            .wc-giftcard-card {
                border: 2px solid #ddd;
                border-radius: 8px;
                padding: 20px;
                background: #fff;
                box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            }

            .gc-header {
                margin-bottom: 20px;
                border-bottom: 1px solid #eee;
                padding-bottom: 15px;
            }

            .gc-code-section {
                display: flex;
                align-items: center;
                gap: 15px;
                flex-wrap: wrap;
            }

            .gc-code {
                margin: 0;
                font-size: 22px;
                font-weight: 700;
                color: #2e7d32;
                letter-spacing: 2px;
                font-family: 'Courier New', monospace;
            }

            .gc-copy-btn {
                padding: 8px 15px;
                background: #4CAF50;
                color: #fff;
                border: none;
                border-radius: 4px;
                cursor: pointer;
                font-size: 14px;
                transition: background 0.2s;
            }

            .gc-copy-btn:hover {
                background: #45a049;
            }

            .gc-balance-section {
                display: grid;
                grid-template-columns: 1fr 1fr;
                gap: 20px;
                margin-bottom: 20px;
                padding: 15px;
                background: #f9f9f9;
                border-radius: 6px;
            }

            .gc-balance-info {
                display: flex;
                justify-content: space-between;
                align-items: center;
            }

            .gc-label {
                font-weight: 600;
                color: #555;
            }

            .gc-amount {
                font-size: 18px;
                font-weight: 700;
                color: #2e7d32;
            }

            .gc-amount-current {
                font-size: 18px;
                font-weight: 700;
                color: #388e3c;
            }

            .gc-progress {
                grid-column: 1 / -1;
            }

            .gc-progress-bar {
                height: 24px;
                background: #e0e0e0;
                border-radius: 12px;
                overflow: hidden;
                margin-bottom: 8px;
            }

            .gc-progress-fill {
                height: 100%;
                background: linear-gradient(90deg, #4CAF50, #45a049);
                transition: width 0.3s ease;
            }

            .gc-progress-text {
                margin: 0;
                text-align: center;
                font-size: 13px;
                color: #666;
            }

            .gc-history-section {
                margin-top: 25px;
                padding-top: 20px;
                border-top: 1px solid #eee;
            }

            .gc-history-section h4 {
                margin: 0 0 15px 0;
                color: #333;
                font-size: 16px;
            }

            .gc-history-table table {
                width: 100%;
                border-collapse: collapse;
                font-size: 14px;
            }

            .gc-history-table thead {
                background: #f5f5f5;
            }

            .gc-history-table th,
            .gc-history-table td {
                padding: 12px;
                text-align: left;
                border-bottom: 1px solid #ddd;
            }

            .gc-history-table th {
                font-weight: 600;
                color: #333;
            }

            .gc-history-table tbody tr:hover {
                background: #fafafa;
            }

            .gc-history-table a {
                color: #4CAF50;
                text-decoration: none;
            }

            .gc-history-table a:hover {
                text-decoration: underline;
            }

            @media (max-width: 768px) {
                .gc-balance-section {
                    grid-template-columns: 1fr;
                }

                .gc-code-section {
                    flex-direction: column;
                    align-items: flex-start;
                }

                .gc-history-table {
                    font-size: 12px;
                }

                .gc-history-table th,
                .gc-history-table td {
                    padding: 8px;
                }
            }
        </style>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                const copyBtns = document.querySelectorAll('.gc-copy-btn');
                copyBtns.forEach(function(btn) {
                    btn.addEventListener('click', function(e) {
                        e.preventDefault();
                        const code = this.getAttribute('data-code');
                        navigator.clipboard.writeText(code).then(function() {
                            const originalText = btn.innerText;
                            btn.innerText = '✓ Copied!';
                            setTimeout(function() {
                                btn.innerText = originalText;
                            }, 2000);
                        });
                    });
                });
            });
        </script>
        <?php
    }

    /**
     * Shortcode handler for displaying gift cards dashboard.
     *
     * Usage: [wc_giftcard_dashboard]
     *
     * @return string Shortcode output.
     */
    public function shortcode_handler(): string {
        ob_start();
        $this->render_dashboard();
        return (string) ob_get_clean();
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    /**
     * Get all gift cards belonging to a customer (by email).
     *
     * Searches for coupons where the customer is:
     *  1. The buyer (via order who purchased the gift card)
     *  2. The recipient (if recipient email was set and matches)
     *
     * @param string $email Customer email address.
     * @return array Array of gift card data.
     */
    private function get_customer_giftcards( string $email ): array {
        $giftcards = [];

        // Get all shop_coupon posts with gift card meta
        $coupon_posts = get_posts([
            'post_type'      => 'shop_coupon',
            'numberposts'    => -1,
            'meta_key'       => WC_GiftCard_Coupon::META_INITIAL_AMOUNT,
            'meta_compare'   => 'EXISTS',
        ]);

        foreach ( $coupon_posts as $coupon_post ) {
            $coupon_id = $coupon_post->ID;
            $buyer_email = get_post_meta( $coupon_id, '_giftcard_buyer_email', true );
            $recipient_email = get_post_meta( $coupon_id, '_giftcard_recipient', true );

            // Skip if this customer is neither buyer nor recipient
            if ( $buyer_email !== $email && $recipient_email !== $email ) {
                continue;
            }

            // If recipient email is set and this is not the recipient, skip
            // (unless they're the buyer)
            if ( ! empty( $recipient_email ) && $recipient_email !== $email && $buyer_email !== $email ) {
                continue;
            }

            // Add to results
            $giftcards[] = [
                'coupon_id'       => $coupon_id,
                'code'            => $coupon_post->post_title,
                'initial_amount'  => (float) get_post_meta( $coupon_id, WC_GiftCard_Coupon::META_INITIAL_AMOUNT, true ),
                'current_balance' => (float) get_post_meta( $coupon_id, WC_GiftCard_Coupon::META_CURRENT_BALANCE, true ),
                'history'         => (array) get_post_meta( $coupon_id, WC_GiftCard_Coupon::META_TRANSACTION_HISTORY, true ),
                'buyer_email'     => $buyer_email,
                'recipient_email' => $recipient_email,
            ];
        }

        return $giftcards;
    }
}
