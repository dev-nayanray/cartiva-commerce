<?php
/**
 * Checkout rules: which field configuration applies to the current request
 * (a landing page form or the main checkout), payment methods per landing
 * page, phone validation, order tagging and the custom thank-you page.
 *
 * @package Cartiva_Commerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Checkout integration.
 */
class Cartiva_Commerce_Checkout {

	/**
	 * Hook everything up.
	 */
	public static function init() {
		add_filter( 'woocommerce_checkout_fields', array( __CLASS__, 'fields' ), 1000 );
		add_filter( 'woocommerce_checkout_posted_data', array( __CLASS__, 'posted_data' ), 20 );
		add_action( 'woocommerce_after_checkout_validation', array( __CLASS__, 'validate' ), 10, 2 );
		add_action( 'woocommerce_checkout_before_customer_details', array( __CLASS__, 'landing_field' ) );
		add_filter( 'woocommerce_cart_needs_shipping_address', array( __CLASS__, 'needs_shipping_address' ), 20 );
		add_filter( 'woocommerce_available_payment_gateways', array( __CLASS__, 'gateways' ), 20 );
		add_action( 'woocommerce_before_checkout_form', array( __CLASS__, 'landing_form_extras' ), 1 );
		add_action( 'woocommerce_checkout_create_order', array( __CLASS__, 'tag_order' ), 10, 2 );
		add_filter( 'woocommerce_get_checkout_order_received_url', array( __CLASS__, 'thankyou_url' ), 20, 2 );
		add_filter( 'the_content', array( __CLASS__, 'thankyou_content' ), 5 );
		add_action( 'woocommerce_admin_order_data_after_billing_address', array( __CLASS__, 'admin_order_source' ) );
	}

	/**
	 * Field configuration for the current request.
	 *
	 * @return array|null Null leaves WooCommerce's fields untouched.
	 */
	public static function current_config() {
		$landing_id = cartiva_commerce_current_landing_id();
		if ( $landing_id ) {
			$landing = cartiva_commerce_landing_config( $landing_id );
			return Cartiva_Commerce_Fields::config_for( $landing['preset'], $landing['fields'] );
		}
		return Cartiva_Commerce_Fields::config_for( (string) cartiva_commerce_setting( 'main_preset' ), (array) cartiva_commerce_setting( 'main_fields' ) );
	}

	/**
	 * Apply the field configuration.
	 *
	 * @param array $fields Checkout fields.
	 * @return array
	 */
	public static function fields( $fields ) {
		return Cartiva_Commerce_Fields::apply( $fields, self::current_config() );
	}

	/**
	 * Complete posted data for hidden fields.
	 *
	 * @param array $data Posted data.
	 * @return array
	 */
	public static function posted_data( $data ) {
		return Cartiva_Commerce_Fields::complete_posted_data( $data, self::current_config() );
	}

	/**
	 * Check Bangladeshi mobile numbers when that option is on.
	 *
	 * @param array    $data   Posted data.
	 * @param WP_Error $errors Errors.
	 */
	public static function validate( $data, $errors ) {
		$landing_id = cartiva_commerce_current_landing_id();
		if ( $landing_id ) {
			$check = (bool) cartiva_commerce_landing_config( $landing_id )['bd_phone'];
		} else {
			$check = (bool) cartiva_commerce_setting( 'main_bd_phone' );
		}
		if ( ! $check || empty( $data['billing_phone'] ) ) {
			return;
		}
		if ( ! cartiva_commerce_is_bd_mobile( $data['billing_phone'] ) ) {
			$errors->add( 'validation', __( 'Please enter a valid 11-digit mobile number, for example 01712345678.', 'cartiva-commerce' ) );
		}
	}

	/**
	 * Hidden field that ties the order form's requests to its landing page.
	 */
	public static function landing_field() {
		$landing_id = cartiva_commerce_current_landing_id();
		if ( $landing_id ) {
			echo '<input type="hidden" name="_cartiva_landing_id" value="' . esc_attr( (string) $landing_id ) . '" />';
		}
	}

	/**
	 * Landing forms with a custom field set ship to the billing address.
	 *
	 * @param bool $needs Needs a shipping address.
	 * @return bool
	 */
	public static function needs_shipping_address( $needs ) {
		if ( cartiva_commerce_current_landing_id() && null !== self::current_config() ) {
			return false;
		}
		return $needs;
	}

	/**
	 * Limit payment methods on a landing page form.
	 *
	 * @param array $gateways Available gateways.
	 * @return array
	 */
	public static function gateways( $gateways ) {
		$landing_id = cartiva_commerce_current_landing_id();
		if ( ! $landing_id || ! is_array( $gateways ) ) {
			return $gateways;
		}
		$allowed = (array) cartiva_commerce_landing_config( $landing_id )['gateways'];
		if ( empty( $allowed ) ) {
			return $gateways;
		}
		$limited = array_intersect_key( $gateways, array_flip( $allowed ) );
		// Never leave the shopper without a way to pay.
		return empty( $limited ) ? $gateways : $limited;
	}

	/**
	 * Coupon and login prompts: hidden above landing forms (they have an inline
	 * coupon field), and on the main checkout when switched off.
	 */
	public static function landing_form_extras() {
		if ( ! cartiva_commerce_current_landing_id() ) {
			if ( ! cartiva_commerce_setting( 'main_coupon' ) ) {
				remove_action( 'woocommerce_before_checkout_form', 'woocommerce_checkout_coupon_form', 10 );
			}
			return;
		}
		// Landing forms show their own inline coupon field (when allowed).
		remove_action( 'woocommerce_before_checkout_form', 'woocommerce_checkout_login_form', 10 );
		remove_action( 'woocommerce_before_checkout_form', 'woocommerce_checkout_coupon_form', 10 );
	}

	/**
	 * Remember which landing page an order came from.
	 *
	 * @param WC_Order $order Order.
	 * @param array    $data  Posted data.
	 */
	public static function tag_order( $order, $data ) {
		$landing_id = cartiva_commerce_current_landing_id();
		if ( $landing_id && $order instanceof WC_Order ) {
			$order->update_meta_data( '_cartiva_landing_id', $landing_id );
		}
	}

	/**
	 * Send landing page orders to that landing page's thank-you page.
	 *
	 * @param string   $url   Order received URL.
	 * @param WC_Order $order Order.
	 * @return string
	 */
	public static function thankyou_url( $url, $order ) {
		if ( ! $order instanceof WC_Order || ! (int) $order->get_meta( '_cartiva_landing_id' ) ) {
			return $url;
		}
		return self::final_url( $order, $url );
	}

	/**
	 * The end of the buying journey for an order: the landing page's
	 * thank-you page or funnel step, else WooCommerce's order received page.
	 *
	 * @param WC_Order    $order    Order.
	 * @param string|null $fallback WooCommerce's URL, when already known.
	 * @return string
	 */
	public static function final_url( $order, $fallback = null ) {
		$page = self::thankyou_target( (int) $order->get_meta( '_cartiva_landing_id' ) );
		if ( $page ) {
			return add_query_arg(
				array(
					'cartiva_order' => $order->get_id(),
					'key'           => $order->get_order_key(),
				),
				get_permalink( $page )
			);
		}
		if ( null !== $fallback ) {
			return $fallback;
		}
		return add_query_arg( 'key', $order->get_order_key(), wc_get_endpoint_url( 'order-received', (string) $order->get_id(), wc_get_checkout_url() ) );
	}

	/**
	 * Where a landing page sends buyers: its chosen thank-you page, else the
	 * next thank-you step of its funnel, else 0 (WooCommerce's page).
	 *
	 * @param int $landing_id Landing page ID.
	 * @return int
	 */
	public static function thankyou_target( $landing_id ) {
		$page = (int) cartiva_commerce_landing_config( $landing_id )['thankyou'];
		if ( $page && 'publish' === get_post_status( $page ) ) {
			return $page;
		}
		return cartiva_commerce_next_step( $landing_id, 'thankyou' );
	}

	/**
	 * Show the order summary at the top of a landing thank-you page.
	 *
	 * @param string $content Page content.
	 * @return string
	 */
	public static function thankyou_content( $content ) {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only; the order key proves the visitor placed the order.
		if ( ! isset( $_GET['cartiva_order'], $_GET['key'] ) || ! ( is_page() || is_singular( CARTIVA_COMMERCE_LANDING ) ) || (int) get_the_ID() !== (int) get_queried_object_id() ) {
			return $content;
		}
		$order = wc_get_order( absint( wp_unslash( $_GET['cartiva_order'] ) ) );
		$key   = sanitize_text_field( wp_unslash( $_GET['key'] ) );
		// phpcs:enable
		if ( ! $order instanceof WC_Order || ! hash_equals( $order->get_order_key(), $key ) ) {
			return $content;
		}
		$landing_id = (int) $order->get_meta( '_cartiva_landing_id' );
		if ( ! $landing_id || self::thankyou_target( $landing_id ) !== (int) get_the_ID() || has_block( 'cartiva-commerce/order-details', (int) get_the_ID() ) ) {
			return $content;
		}
		ob_start();
		wc_get_template( 'checkout/thankyou.php', array( 'order' => $order ) );
		return '<div class="cartiva-commerce-thankyou woocommerce">' . ob_get_clean() . '</div>' . $content;
	}

	/**
	 * Show the landing page source on the order screen.
	 *
	 * @param WC_Order $order Order.
	 */
	public static function admin_order_source( $order ) {
		$landing_id = (int) $order->get_meta( '_cartiva_landing_id' );
		if ( ! $landing_id ) {
			return;
		}
		printf(
			'<p><strong>%1$s</strong><br><a href="%2$s">%3$s</a></p>',
			esc_html__( 'Landing page', 'cartiva-commerce' ),
			esc_url( (string) get_edit_post_link( $landing_id ) ),
			esc_html( get_the_title( $landing_id ) )
		);
	}
}
