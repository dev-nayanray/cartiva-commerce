<?php
/**
 * Cash on delivery tools: a delivery charge choice on landing page forms
 * (for example Inside Dhaka / Outside Dhaka), and fake-order protection
 * (repeat orders from the same phone, a daily limit, blocked numbers).
 *
 * @package Cartiva_Commerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * COD tools.
 */
class Cartiva_Commerce_COD {

	/**
	 * Hook up.
	 */
	public static function init() {
		add_action( 'woocommerce_review_order_before_payment', array( __CLASS__, 'delivery_choices' ), 5 );
		add_action( 'wc_ajax_cartiva_commerce_delivery', array( __CLASS__, 'ajax_delivery' ) );
		add_action( 'woocommerce_cart_calculate_fees', array( __CLASS__, 'delivery_fee' ) );
		add_filter( 'woocommerce_cart_needs_shipping', array( __CLASS__, 'needs_shipping' ), 20 );
		add_action( 'woocommerce_after_checkout_validation', array( __CLASS__, 'protect' ), 30, 2 );
		add_action( 'woocommerce_checkout_create_order', array( __CLASS__, 'tag_order' ), 20 );
	}

	/**
	 * Default delivery options.
	 *
	 * @return array[]
	 */
	public static function default_options() {
		return array(
			array(
				'label'  => __( 'Inside Dhaka', 'cartiva-commerce' ),
				'amount' => 60,
			),
			array(
				'label'  => __( 'Outside Dhaka', 'cartiva-commerce' ),
				'amount' => 120,
			),
		);
	}

	/**
	 * Delivery settings of a landing page.
	 *
	 * @param int $landing_id Landing page ID.
	 * @return array enabled, options.
	 */
	public static function delivery( $landing_id ) {
		$config   = $landing_id ? cartiva_commerce_landing_config( $landing_id ) : array();
		$delivery = isset( $config['delivery'] ) && is_array( $config['delivery'] ) ? $config['delivery'] : array();
		$delivery = wp_parse_args(
			$delivery,
			array(
				'enabled' => false,
				'options' => self::default_options(),
			)
		);
		$options  = array();
		foreach ( (array) $delivery['options'] as $row ) {
			if ( is_array( $row ) && isset( $row['label'] ) && '' !== $row['label'] ) {
				$options[] = array(
					'label'  => (string) $row['label'],
					'amount' => max( 0, (float) $row['amount'] ),
				);
			}
		}
		$delivery['options'] = $options;
		$delivery['enabled'] = ! empty( $delivery['enabled'] ) && ! empty( $options );
		return $delivery;
	}

	/**
	 * Clean delivery settings from the landing page box.
	 *
	 * @param mixed $raw Raw values.
	 * @return array
	 */
	public static function sanitize( $raw ) {
		$raw     = is_array( $raw ) ? $raw : array();
		$options = array();
		foreach ( isset( $raw['options'] ) && is_array( $raw['options'] ) ? $raw['options'] : array() as $row ) {
			$label = is_array( $row ) && isset( $row['label'] ) ? sanitize_text_field( $row['label'] ) : '';
			if ( '' === $label || count( $options ) >= 6 ) {
				continue;
			}
			$options[] = array(
				'label'  => $label,
				'amount' => isset( $row['amount'] ) ? max( 0, round( (float) $row['amount'], 2 ) ) : 0,
			);
		}
		return array(
			'enabled' => ! empty( $raw['enabled'] ),
			'options' => $options,
		);
	}

	/**
	 * The shopper's chosen delivery option for a landing page.
	 *
	 * @param int $landing_id Landing page ID.
	 * @return int Option index.
	 */
	public static function chosen( $landing_id ) {
		$choices = WC()->session ? (array) WC()->session->get( 'cartiva_delivery' ) : array();
		$index   = isset( $choices[ $landing_id ] ) ? (int) $choices[ $landing_id ] : 0;
		$options = self::delivery( $landing_id )['options'];
		return isset( $options[ $index ] ) ? $index : 0;
	}

	/**
	 * Delivery choices on the order form (above the payment methods).
	 */
	public static function delivery_choices() {
		$landing_id = cartiva_commerce_current_landing_id();
		$delivery   = self::delivery( $landing_id );
		if ( ! $landing_id || ! $delivery['enabled'] ) {
			return;
		}
		$chosen = self::chosen( $landing_id );
		echo '<fieldset class="cartiva-delivery" data-landing="' . esc_attr( (string) $landing_id ) . '"><legend>' . esc_html__( 'Delivery area', 'cartiva-commerce' ) . '</legend>';
		foreach ( $delivery['options'] as $i => $option ) {
			echo '<label class="cartiva-delivery__option' . ( $i === $chosen ? ' is-selected' : '' ) . '"><input type="radio" name="cartiva_delivery_choice" value="' . esc_attr( (string) $i ) . '"' . checked( $i, $chosen, false ) . ' /> <span>' . esc_html( $option['label'] ) . '</span> <strong>' . ( $option['amount'] > 0 ? wp_kses_post( wc_price( $option['amount'] ) ) : esc_html__( 'Free', 'cartiva-commerce' ) ) . '</strong></label>';
		}
		echo '</fieldset>';
	}

	/**
	 * Save the shopper's choice (wc-ajax endpoint).
	 */
	public static function ajax_delivery() {
		check_ajax_referer( 'cartiva_commerce_form', 'nonce' );
		$landing_id = isset( $_POST['landing'] ) ? absint( wp_unslash( $_POST['landing'] ) ) : 0;
		$choice     = isset( $_POST['choice'] ) ? absint( wp_unslash( $_POST['choice'] ) ) : 0;
		$options    = self::delivery( $landing_id )['options'];
		if ( ! cartiva_commerce_is_landing( $landing_id ) || ! isset( $options[ $choice ] ) || ! WC()->session ) {
			wp_send_json_error( null, 400 );
		}
		$choices                = (array) WC()->session->get( 'cartiva_delivery' );
		$choices[ $landing_id ] = $choice;
		WC()->session->set( 'cartiva_delivery', $choices );
		wp_send_json_success( array( 'choice' => $choice ) );
	}

	/**
	 * Add the delivery charge to landing page orders.
	 *
	 * @param WC_Cart $cart Cart.
	 */
	public static function delivery_fee( $cart ) {
		$landing_id = cartiva_commerce_current_landing_id();
		$delivery   = self::delivery( $landing_id );
		if ( ! $landing_id || ! $delivery['enabled'] || $cart->is_empty() ) {
			return;
		}
		$option = $delivery['options'][ self::chosen( $landing_id ) ];
		if ( $option['amount'] > 0 ) {
			/* translators: %s: delivery option, for example Inside Dhaka. */
			$cart->add_fee( sprintf( __( 'Delivery: %s', 'cartiva-commerce' ), $option['label'] ), $option['amount'], false );
		}
	}

	/**
	 * Landing pages with delivery choices charge delivery themselves, so
	 * WooCommerce shipping methods are not needed there.
	 *
	 * @param bool $needs Needs shipping.
	 * @return bool
	 */
	public static function needs_shipping( $needs ) {
		$landing_id = cartiva_commerce_current_landing_id();
		return ( $landing_id && self::delivery( $landing_id )['enabled'] ) ? false : $needs;
	}

	/**
	 * Orders from the same phone (last 10 digits) since a time.
	 *
	 * @param string $key   Phone key.
	 * @param int    $since Timestamp.
	 * @return int
	 */
	public static function orders_from_phone( $key, $since ) {
		if ( '' === $key ) {
			return 0;
		}
		$ids = wc_get_orders(
			array(
				'limit'        => 20,
				'return'       => 'ids',
				'status'       => array( 'wc-pending', 'wc-processing', 'wc-on-hold', 'wc-completed' ),
				'date_created' => '>' . (int) $since,
				'meta_key'     => '_cartiva_phone_key', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- One lookup per order attempt.
				'meta_value'   => $key, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- Same.
			)
		);
		return is_array( $ids ) ? count( $ids ) : 0;
	}

	/**
	 * Blocked phone keys from the setting (one number per line).
	 *
	 * @return string[]
	 */
	public static function blocked_keys() {
		$keys = array();
		foreach ( preg_split( '/[\r\n,]+/', (string) cartiva_commerce_setting( 'protect_block' ) ) as $line ) {
			$key = Cartiva_Commerce_Recovery::phone_key( $line );
			if ( '' !== $key ) {
				$keys[] = $key;
			}
		}
		return $keys;
	}

	/**
	 * What protection says about an order attempt.
	 *
	 * @param string $phone Phone.
	 * @return array{verdict:string,reason:string} verdict: ok, flag or block.
	 */
	public static function check( $phone ) {
		$key = Cartiva_Commerce_Recovery::phone_key( $phone );
		if ( '' !== $key && in_array( $key, self::blocked_keys(), true ) ) {
			return array(
				'verdict' => 'block',
				'reason'  => __( 'This phone number is on the blocked list.', 'cartiva-commerce' ),
			);
		}
		$mode  = 'block' === cartiva_commerce_setting( 'protect_mode' ) ? 'block' : 'flag';
		$hours = (int) cartiva_commerce_setting( 'protect_hours' );
		if ( $hours > 0 && self::orders_from_phone( $key, time() - $hours * HOUR_IN_SECONDS ) > 0 ) {
			return array(
				'verdict' => $mode,
				/* translators: %d: hours. */
				'reason'  => sprintf( _n( 'The same phone number ordered in the last %d hour.', 'The same phone number ordered in the last %d hours.', $hours, 'cartiva-commerce' ), $hours ),
			);
		}
		$max = (int) cartiva_commerce_setting( 'protect_max' );
		if ( $max > 0 && self::orders_from_phone( $key, time() - DAY_IN_SECONDS ) >= $max ) {
			return array(
				'verdict' => $mode,
				/* translators: %d: number of orders. */
				'reason'  => sprintf( _n( 'The same phone number already placed %d order today.', 'The same phone number already placed %d orders today.', $max, 'cartiva-commerce' ), $max ),
			);
		}
		return array(
			'verdict' => 'ok',
			'reason'  => '',
		);
	}

	/**
	 * Is protection on for the current checkout?
	 *
	 * @return bool
	 */
	public static function protecting() {
		if ( ! cartiva_commerce_setting( 'protect' ) ) {
			return false;
		}
		return cartiva_commerce_current_landing_id() || cartiva_commerce_setting( 'protect_main' );
	}

	/**
	 * Check an order attempt: refuse blocked ones, remember flagged ones.
	 *
	 * @param array    $data   Posted data.
	 * @param WP_Error $errors Errors.
	 */
	public static function protect( $data, $errors ) {
		if ( ! self::protecting() || ( method_exists( $errors, 'has_errors' ) && $errors->has_errors() ) ) {
			return;
		}
		$result = self::check( isset( $data['billing_phone'] ) ? (string) $data['billing_phone'] : '' );
		if ( 'block' === $result['verdict'] ) {
			$message = (string) cartiva_commerce_setting( 'protect_message' );
			$errors->add( 'cartiva_protect', '' !== trim( $message ) ? $message : __( 'We already have an order from this phone number. We will call you to confirm it. To change your order, please call us.', 'cartiva-commerce' ) );
		} elseif ( 'flag' === $result['verdict'] && WC()->session ) {
			WC()->session->set( 'cartiva_risk', $result['reason'] );
		}
	}

	/**
	 * On new orders: the phone key for later checks, the delivery choice, and
	 * the "check before shipping" note.
	 *
	 * @param WC_Order $order Order.
	 */
	public static function tag_order( $order ) {
		$key = Cartiva_Commerce_Recovery::phone_key( $order->get_billing_phone() );
		if ( '' !== $key ) {
			$order->update_meta_data( '_cartiva_phone_key', $key );
		}
		$landing_id = cartiva_commerce_current_landing_id();
		$delivery   = self::delivery( $landing_id );
		if ( $landing_id && $delivery['enabled'] ) {
			$option = $delivery['options'][ self::chosen( $landing_id ) ];
			$order->update_meta_data( '_cartiva_delivery', $option['label'] );
		}
		if ( WC()->session && WC()->session->get( 'cartiva_risk' ) ) {
			/* translators: %s: reason. */
			$risk = sprintf( __( 'Check before shipping: %s', 'cartiva-commerce' ), WC()->session->get( 'cartiva_risk' ) );
			$order->update_meta_data( '_cartiva_risk', $risk );
			$order->add_order_note( $risk );
			WC()->session->set( 'cartiva_risk', null );
		}
	}
}
