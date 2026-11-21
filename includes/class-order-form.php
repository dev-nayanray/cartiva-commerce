<?php
/**
 * Landing order form block: renders a one-page checkout for the landing
 * page's product, and the quantity changer.
 *
 * @package Cartiva_Commerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Order form.
 */
class Cartiva_Commerce_Order_Form {

	/**
	 * Hook up.
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'register' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_action( 'wc_ajax_cartiva_commerce_set_qty', array( __CLASS__, 'ajax_set_qty' ) );
	}

	/**
	 * Register the block.
	 */
	public static function register() {
		register_block_type(
			CARTIVA_COMMERCE_DIR . 'blocks/order-form',
			array( 'render_callback' => array( __CLASS__, 'render' ) )
		);
	}

	/**
	 * Front-end assets on landing pages.
	 */
	public static function assets() {
		if ( ! is_singular( CARTIVA_COMMERCE_LANDING ) ) {
			return;
		}
		wp_enqueue_style( 'cartiva-commerce-order-form', CARTIVA_COMMERCE_URL . 'assets/css/order-form.css', array(), CARTIVA_COMMERCE_VERSION );
		wp_enqueue_script( 'cartiva-commerce-order-form', CARTIVA_COMMERCE_URL . 'assets/js/order-form.js', array( 'jquery' ), CARTIVA_COMMERCE_VERSION, array( 'in_footer' => true ) );
		wp_localize_script(
			'cartiva-commerce-order-form',
			'cartivaOrderForm',
			array(
				'url'   => WC_AJAX::get_endpoint( 'cartiva_commerce_set_qty' ),
				'nonce' => wp_create_nonce( 'cartiva_commerce_qty' ),
				'error' => __( 'Could not update the quantity. Please try again.', 'cartiva-commerce' ),
			)
		);
	}

	/**
	 * Render the form.
	 *
	 * @param array $attributes Block attributes.
	 * @return string
	 */
	public static function render( $attributes ) {
		$landing_id = cartiva_commerce_current_landing_id();
		if ( ! $landing_id ) {
			return '';
		}
		$config  = cartiva_commerce_landing_config( $landing_id );
		$item_id = $config['variation_id'] ? (int) $config['variation_id'] : (int) $config['product_id'];
		$product = $item_id ? wc_get_product( $item_id ) : null;
		$wrapper = get_block_wrapper_attributes(
			array(
				'class' => 'cartiva-order-form woocommerce',
				'id'    => ! empty( $attributes['anchor'] ) ? $attributes['anchor'] : 'order',
			)
		);

		if ( ! $product ) {
			if ( current_user_can( 'edit_post', $landing_id ) ) {
				return '<div ' . $wrapper . '><p class="cartiva-order-form__notice">' . esc_html__( 'Choose the product to sell in the "Landing page: product and order form" box when editing this landing page.', 'cartiva-commerce' ) . '</p></div>';
			}
			return '';
		}
		if ( ! $product->is_purchasable() || ! $product->is_in_stock() ) {
			return '<div ' . $wrapper . '><p class="cartiva-order-form__notice">' . esc_html__( 'Sorry, this product is out of stock right now.', 'cartiva-commerce' ) . '</p></div>';
		}

		$key  = Cartiva_Commerce_Landing::cart_item_key( $config );
		$qty  = $key ? (int) WC()->cart->get_cart_item( $key )['quantity'] : (int) $config['quantity'];
		$max  = $product->get_max_purchase_quantity();
		$max  = $max > 0 ? min( 99, $max ) : 99;
		$html = '';

		if ( ! empty( $attributes['heading'] ) ) {
			$html .= '<h2 class="cartiva-order-form__heading">' . wp_kses_post( $attributes['heading'] ) . '</h2>';
		}

		// Product summary.
		$html .= '<div class="cartiva-order-form__product">';
		$html .= '<div class="cartiva-order-form__image">' . $product->get_image( 'woocommerce_thumbnail', array( 'alt' => '' ) ) . '</div>';
		$html .= '<div class="cartiva-order-form__info"><p class="cartiva-order-form__name">' . esc_html( wp_strip_all_tags( $product->get_name() ) ) . '</p>';
		$html .= '<p class="cartiva-order-form__price">' . wp_kses_post( $product->get_price_html() ) . '</p></div>';
		if ( ! empty( $config['qty_editable'] ) && ! $product->is_sold_individually() ) {
			$html .= '<div class="cartiva-order-form__qty" data-landing="' . esc_attr( (string) $landing_id ) . '" data-max="' . esc_attr( (string) $max ) . '">';
			$html .= '<button type="button" class="cartiva-order-form__qty-btn" data-step="-1" aria-label="' . esc_attr__( 'Decrease quantity', 'cartiva-commerce' ) . '">&minus;</button>';
			$html .= '<input type="number" class="cartiva-order-form__qty-input" min="1" max="' . esc_attr( (string) $max ) . '" value="' . esc_attr( (string) max( 1, $qty ) ) . '" aria-label="' . esc_attr__( 'Quantity', 'cartiva-commerce' ) . '" inputmode="numeric" />';
			$html .= '<button type="button" class="cartiva-order-form__qty-btn" data-step="1" aria-label="' . esc_attr__( 'Increase quantity', 'cartiva-commerce' ) . '">+</button>';
			$html .= '</div>';
		}
		$html .= '</div>';

		// WooCommerce's classic checkout, with this landing page's fields.
		$html .= '<div class="cartiva-order-form__checkout">' . WC_Shortcodes::checkout( array() ) . '</div>';

		return '<div ' . $wrapper . '>' . $html . '</div>';
	}

	/**
	 * Change the quantity of the landing page's product (wc-ajax endpoint).
	 */
	public static function ajax_set_qty() {
		check_ajax_referer( 'cartiva_commerce_qty', 'nonce' );
		$landing_id = isset( $_POST['landing'] ) ? absint( wp_unslash( $_POST['landing'] ) ) : 0;
		$qty        = isset( $_POST['qty'] ) ? absint( wp_unslash( $_POST['qty'] ) ) : 0;
		if ( ! cartiva_commerce_is_landing( $landing_id ) || $qty < 1 ) {
			wp_send_json_error( null, 400 );
		}
		$config = cartiva_commerce_landing_config( $landing_id );
		if ( empty( $config['qty_editable'] ) ) {
			wp_send_json_error( null, 403 );
		}
		$key = Cartiva_Commerce_Landing::cart_item_key( $config );
		if ( '' === $key ) {
			wp_send_json_error( null, 409 );
		}
		$item    = WC()->cart->get_cart_item( $key );
		$product = isset( $item['data'] ) ? $item['data'] : null;
		$max     = ( $product instanceof WC_Product && $product->get_max_purchase_quantity() > 0 ) ? $product->get_max_purchase_quantity() : 99;
		$qty     = min( $qty, min( 99, $max ) );
		WC()->cart->set_quantity( $key, $qty, true );
		wp_send_json_success( array( 'qty' => $qty ) );
	}
}
