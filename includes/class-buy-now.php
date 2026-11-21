<?php
/**
 * "Buy now" button: adds the product with WooCommerce's own add to cart form
 * and sends the shopper straight to checkout.
 *
 * @package Cartiva_Commerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Buy now.
 */
class Cartiva_Commerce_Buy_Now {

	/**
	 * Hook up.
	 */
	public static function init() {
		add_action( 'woocommerce_after_add_to_cart_button', array( __CLASS__, 'button' ) );
		add_filter( 'woocommerce_add_to_cart_redirect', array( __CLASS__, 'redirect' ), 99 );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'styles' ) );
	}

	/**
	 * Is Buy now switched on?
	 *
	 * @return bool
	 */
	public static function enabled() {
		return (bool) cartiva_commerce_setting( 'buy_now' );
	}

	/**
	 * Print the button after Add to cart on product pages.
	 */
	public static function button() {
		if ( ! self::enabled() || ! is_product() || cartiva_commerce_current_landing_id() ) {
			return;
		}
		global $product;
		if ( ! $product instanceof WC_Product || ! $product->is_purchasable() || ! $product->is_in_stock() || $product->is_type( 'external' ) ) {
			return;
		}
		$label = trim( (string) cartiva_commerce_setting( 'buy_now_label' ) );
		$label = '' !== $label ? $label : __( 'Buy now', 'cartiva-commerce' );
		/**
		 * Filters the Buy now button label.
		 *
		 * @param string     $label   Label.
		 * @param WC_Product $product Product.
		 */
		$label  = (string) apply_filters( 'cartiva_commerce_buy_now_text', $label, $product );
		$action = add_query_arg( 'cartiva_buy_now', '1', $product->get_permalink() );
		$value  = ( $product->is_type( 'variable' ) || $product->is_type( 'grouped' ) ) ? '' : (string) $product->get_id();
		printf(
			'<button type="submit" class="single_add_to_cart_button button alt cartiva-buy-now%1$s" formaction="%2$s"%3$s>%4$s</button>',
			$product->is_type( 'variable' ) ? ' disabled wc-variation-selection-needed' : '',
			esc_url( $action ),
			'' !== $value ? ' name="add-to-cart" value="' . esc_attr( $value ) . '"' : '',
			esc_html( $label )
		);
	}

	/**
	 * Is the current add to cart request a Buy now?
	 *
	 * @return bool
	 */
	public static function is_request() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only flag on WooCommerce's own add to cart request.
		return self::enabled() && isset( $_GET['cartiva_buy_now'] ) && '1' === sanitize_text_field( wp_unslash( $_GET['cartiva_buy_now'] ) );
	}

	/**
	 * Go straight to checkout, without the "added to your cart" notice.
	 *
	 * @param string $url Redirect URL.
	 * @return string
	 */
	public static function redirect( $url ) {
		if ( ! self::is_request() ) {
			return $url;
		}
		$notices = wc_get_notices();
		unset( $notices['success'] );
		wc_set_notices( $notices );
		return wc_get_checkout_url();
	}

	/**
	 * Small stylesheet for the button (works with any theme).
	 */
	public static function styles() {
		if ( self::enabled() && is_product() ) {
			wp_enqueue_style( 'cartiva-commerce-buy-now', CARTIVA_COMMERCE_URL . 'assets/css/buy-now.css', array(), CARTIVA_COMMERCE_VERSION );
		}
	}
}
