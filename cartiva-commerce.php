<?php
/**
 * Plugin Name:       Cartiva Commerce
 * Plugin URI:        https://godevs.net/plugins/cartiva-commerce/
 * Description:       Sales funnels and single product landing pages with a one-page checkout, product options, order bumps, one-click upsells and downsells, A/B tests, analytics, pixel tracking, abandoned checkout recovery, a slide-out cart, BOGO deals, auto-apply coupons, checkout field editor (per landing page and for the main checkout), and a Buy now button for WooCommerce. Free, fast and theme-independent; designed to match the Cartiva theme.
 * Version:           1.7.0
 * Requires at least: 6.9
 * Requires PHP:      7.4
 * Requires Plugins:  woocommerce
 * Author:            godevs
 * Author URI:        https://godevs.net/
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       cartiva-commerce
 * Domain Path:       /languages
 * WC requires at least: 9.0
 * WC tested up to:   10.5
 *
 * @package Cartiva_Commerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'CARTIVA_COMMERCE_VERSION', '1.7.0' );
define( 'CARTIVA_COMMERCE_FILE', __FILE__ );
define( 'CARTIVA_COMMERCE_DIR', plugin_dir_path( __FILE__ ) );
define( 'CARTIVA_COMMERCE_URL', plugin_dir_url( __FILE__ ) );

/**
 * Declare compatibility with WooCommerce features (HPOS, cart and checkout blocks).
 */
function cartiva_commerce_declare_compatibility() {
	if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', __FILE__, true );
	}
}
add_action( 'before_woocommerce_init', 'cartiva_commerce_declare_compatibility' );

/**
 * Start the plugin once WooCommerce is loaded.
 */
function cartiva_commerce_init() {
	if ( ! class_exists( 'WooCommerce' ) ) {
		add_action( 'admin_notices', 'cartiva_commerce_missing_wc_notice' );
		return;
	}
	require_once CARTIVA_COMMERCE_DIR . 'includes/functions.php';
	require_once CARTIVA_COMMERCE_DIR . 'includes/class-fields.php';
	require_once CARTIVA_COMMERCE_DIR . 'includes/class-settings.php';
	require_once CARTIVA_COMMERCE_DIR . 'includes/class-buy-now.php';
	require_once CARTIVA_COMMERCE_DIR . 'includes/class-checkout.php';
	require_once CARTIVA_COMMERCE_DIR . 'includes/class-landing.php';
	require_once CARTIVA_COMMERCE_DIR . 'includes/class-order-form.php';
	require_once CARTIVA_COMMERCE_DIR . 'includes/class-stats.php';
	require_once CARTIVA_COMMERCE_DIR . 'includes/class-offers.php';
	require_once CARTIVA_COMMERCE_DIR . 'includes/class-funnels.php';
	require_once CARTIVA_COMMERCE_DIR . 'includes/class-upsells.php';
	require_once CARTIVA_COMMERCE_DIR . 'includes/class-analytics.php';
	require_once CARTIVA_COMMERCE_DIR . 'includes/class-ab-test.php';
	require_once CARTIVA_COMMERCE_DIR . 'includes/class-tracking.php';
	require_once CARTIVA_COMMERCE_DIR . 'includes/class-recovery.php';
	require_once CARTIVA_COMMERCE_DIR . 'includes/class-recovery-admin.php';
	require_once CARTIVA_COMMERCE_DIR . 'includes/class-cart.php';
	require_once CARTIVA_COMMERCE_DIR . 'includes/class-deals.php';
	require_once CARTIVA_COMMERCE_DIR . 'includes/class-countdown.php';

	add_action( 'init', 'cartiva_commerce_load_textdomain', 0 );
	Cartiva_Commerce_Settings::init();
	Cartiva_Commerce_Buy_Now::init();
	Cartiva_Commerce_Checkout::init();
	Cartiva_Commerce_Landing::init();
	Cartiva_Commerce_Order_Form::init();
	Cartiva_Commerce_Stats::init();
	Cartiva_Commerce_Offers::init();
	Cartiva_Commerce_Funnels::init();
	Cartiva_Commerce_Upsells::init();
	Cartiva_Commerce_Analytics::init();
	Cartiva_Commerce_AB_Test::init();
	Cartiva_Commerce_Tracking::init();
	Cartiva_Commerce_Recovery::init();
	Cartiva_Commerce_Recovery_Admin::init();
	Cartiva_Commerce_Cart::init();
	Cartiva_Commerce_Deals::init();
	Cartiva_Commerce_Countdown::init();
}
add_action( 'plugins_loaded', 'cartiva_commerce_init', 20 );

/**
 * Notice when WooCommerce is missing.
 */
function cartiva_commerce_missing_wc_notice() {
	if ( ! current_user_can( 'activate_plugins' ) ) {
		return;
	}
	echo '<div class="notice notice-warning"><p>' . esc_html__( 'Cartiva Commerce needs WooCommerce. Please install and activate WooCommerce.', 'cartiva-commerce' ) . '</p></div>';
}

/**
 * Flush rewrite rules so landing page links work right after activation.
 */
function cartiva_commerce_activate() {
	update_option( 'cartiva_commerce_flush_rewrite', 1, false );
}
register_activation_hook( __FILE__, 'cartiva_commerce_activate' );

/**
 * Clean up rewrite rules on deactivation.
 */
function cartiva_commerce_deactivate() {
	wp_clear_scheduled_hook( 'cartiva_commerce_recovery_cron' );
	flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'cartiva_commerce_deactivate' );

/**
 * Load the bundled translations (Bangla is included). Translations from
 * translate.wordpress.org, when available, take priority.
 */
function cartiva_commerce_load_textdomain() {
	load_plugin_textdomain( 'cartiva-commerce', false, dirname( plugin_basename( CARTIVA_COMMERCE_FILE ) ) . '/languages' ); // phpcs:ignore PluginCheck.CodeAnalysis.DiscouragedFunctions.load_plugin_textdomainFound -- Loads the bundled Bangla translation.
}
