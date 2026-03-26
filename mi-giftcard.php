<?php
/**
 * Plugin Name: MI Gift Card
 * Description: Gift card product that auto-generates a coupon after payment.
 *              Supports Simple products (price from WooCommerce) and Variable
 *              products (denomination buttons + optional custom price).
 * Version:     2.0.0
 * @author Wawan Qurniawan
 * Text Domain: mi-giftcard
 * Domain Path: /languages
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'WC_GIFTCARD_PATH', plugin_dir_path( __FILE__ ) );
define( 'WC_GIFTCARD_URL',  plugin_dir_url( __FILE__ ) );

final class WC_GiftCard_Plugin {

    private static ?self $instance = null;

    public static function instance(): self {
        if ( self::$instance === null ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action( 'plugins_loaded', [ $this, 'init' ] );
    }

    // =========================================================================
    // Initialisation
    // =========================================================================

    public function init(): void {
        if ( ! class_exists( 'WooCommerce' ) ) {
            add_action( 'admin_notices', function () {
                echo '<div class="error"><p>'
                    . esc_html__( 'WC Gift Card requires WooCommerce to be installed and active.', 'mi-giftcard' )
                    . '</p></div>';
            } );
            return;
        }

        load_plugin_textdomain( 'mi-giftcard', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );

        $this->load_files();
        $this->boot_classes();
    }

    // =========================================================================
    // File loader
    // =========================================================================

    /**
     * Require all plugin class files.
     * Load order: abstract base -> admin -> frontend -> cart -> coupon -> email -> order.
     */
    private function load_files(): void {
        // Abstract base (must load first -- all classes extend it)
        require_once WC_GIFTCARD_PATH . 'includes/abstracts/abstract-giftcard-base.php';

        // Admin
        require_once WC_GIFTCARD_PATH . 'includes/admin/class-giftcard-admin-product.php';
        require_once WC_GIFTCARD_PATH . 'includes/admin/class-giftcard-admin-variation.php';

        // Frontend
        require_once WC_GIFTCARD_PATH . 'includes/frontend/class-giftcard-frontend-simple.php';
        require_once WC_GIFTCARD_PATH . 'includes/frontend/class-giftcard-frontend-variable.php';
        require_once WC_GIFTCARD_PATH . 'includes/frontend/class-giftcard-frontend-recipient.php';

        // Cart
        require_once WC_GIFTCARD_PATH . 'includes/cart/class-giftcard-cart-quantity.php';
        require_once WC_GIFTCARD_PATH . 'includes/cart/class-giftcard-cart-simple.php';
        require_once WC_GIFTCARD_PATH . 'includes/cart/class-giftcard-cart-variable.php';

        // Coupon
        require_once WC_GIFTCARD_PATH . 'includes/coupon/class-giftcard-coupon.php';

        // Email
        require_once WC_GIFTCARD_PATH . 'includes/email/class-giftcard-email.php';

        // Order
        require_once WC_GIFTCARD_PATH . 'includes/order/class-giftcard-order.php';
    }

    // =========================================================================
    // Class bootstrapper
    // =========================================================================

    /**
     * Instantiate all feature classes.
     * Each constructor registers its own WordPress hooks.
     */
    private function boot_classes(): void {
        // Admin
        new WC_GiftCard_Admin_Product();
        new WC_GiftCard_Admin_Variation();

        // Frontend
        new WC_GiftCard_Frontend_Simple();
        new WC_GiftCard_Frontend_Variable();
        new WC_GiftCard_Frontend_Recipient();

        // Cart
        new WC_GiftCard_Cart_Quantity();
        new WC_GiftCard_Cart_Simple();
        new WC_GiftCard_Cart_Variable();

        // Order
        new WC_GiftCard_Order();
    }
}

WC_GiftCard_Plugin::instance();
