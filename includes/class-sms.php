<?php
/**
 * SMS: Bangladeshi SMS gateways (BulkSMSBD, Alpha SMS, SSL Wireless or any
 * gateway with a send link), order and parcel messages, a new-order alert
 * for the shop, one-time codes to confirm the phone number on cash on
 * delivery forms, and an SMS reminder for unfinished checkouts.
 *
 * @package Cartiva_Commerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * SMS.
 */
class Cartiva_Commerce_SMS {

	const OTP_TTL      = 600;
	const OTP_TRIES    = 5;
	const OTP_RESEND   = 60;
	const OTP_PER_HOUR = 4;

	/**
	 * Hook up.
	 */
	public static function init() {
		add_action( 'woocommerce_checkout_order_processed', array( __CLASS__, 'order_placed' ), 30, 3 );
		add_action( 'woocommerce_store_api_checkout_order_processed', array( __CLASS__, 'block_order_placed' ), 30 );
		add_action( 'cartiva_commerce_parcel_sent', array( __CLASS__, 'parcel_sent' ) );
		add_action( 'cartiva_commerce_parcel_status', array( __CLASS__, 'parcel_status' ), 10, 2 );
		add_action( 'cartiva_commerce_recovery_tick', array( __CLASS__, 'recovery_reminder' ), 10, 2 );
		// One-time codes.
		add_action( 'woocommerce_after_checkout_billing_form', array( __CLASS__, 'otp_field' ) );
		add_action( 'woocommerce_after_checkout_validation', array( __CLASS__, 'otp_check' ), 40, 2 );
		add_action( 'wc_ajax_cartiva_commerce_otp_resend', array( __CLASS__, 'ajax_resend' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'assets' ), 25 );
		// Settings test.
		add_action( 'wp_ajax_cartiva_sms_test', array( __CLASS__, 'ajax_test' ) );
	}

	/* ------------------------------------------------------------------
	 * Gateways
	 * ------------------------------------------------------------------ */

	/**
	 * Gateways: key => label.
	 *
	 * @return array
	 */
	public static function gateways() {
		return array(
			'bulksmsbd' => 'BulkSMSBD',
			'alpha'     => 'Alpha SMS (sms.net.bd)',
			'ssl'       => 'SSL Wireless',
			'custom'    => __( 'Other gateway (send link)', 'cartiva-commerce' ),
		);
	}

	/**
	 * Is a gateway set up?
	 *
	 * @return bool
	 */
	public static function ready() {
		$s = cartiva_commerce_settings();
		switch ( (string) $s['sms_gateway'] ) {
			case 'bulksmsbd':
				return '' !== $s['sms_key'] && '' !== $s['sms_sender'];
			case 'alpha':
				return '' !== $s['sms_key'];
			case 'ssl':
				return '' !== $s['sms_key'] && '' !== $s['sms_ssl_sid'];
			case 'custom':
				return false !== strpos( (string) $s['sms_custom_url'], '{phone}' ) && false !== strpos( (string) $s['sms_custom_url'], '{message}' );
		}
		return false;
	}

	/**
	 * Phone as 8801XXXXXXXXX, or '' when not a Bangladeshi mobile number.
	 *
	 * @param string $phone Phone.
	 * @return string
	 */
	public static function msisdn( $phone ) {
		$local = Cartiva_Commerce_Courier::phone( $phone );
		return preg_match( '/^01[3-9]\d{8}$/', $local ) ? '88' . $local : '';
	}

	/**
	 * Send one SMS.
	 *
	 * @param string $phone   Phone.
	 * @param string $message Text.
	 * @return array{ok:bool,error:string}
	 */
	public static function send( $phone, $message ) {
		$to      = self::msisdn( $phone );
		$message = trim( (string) $message );
		if ( '' === $to ) {
			return array(
				'ok'    => false,
				'error' => __( 'Not a Bangladeshi mobile number.', 'cartiva-commerce' ),
			);
		}
		if ( '' === $message || ! self::ready() ) {
			return array(
				'ok'    => false,
				'error' => __( 'SMS is not set up.', 'cartiva-commerce' ),
			);
		}
		/**
		 * Filters an SMS before it is sent. Return a result array to send it yourself.
		 *
		 * @param null|array $result  Null to send normally.
		 * @param string     $to      Number (8801XXXXXXXXX).
		 * @param string     $message Text.
		 */
		$pre = apply_filters( 'cartiva_commerce_pre_send_sms', null, $to, $message );
		if ( is_array( $pre ) ) {
			return wp_parse_args(
				$pre,
				array(
					'ok'    => false,
					'error' => '',
				)
			);
		}
		$s       = cartiva_commerce_settings();
		$gateway = (string) $s['sms_gateway'];
		if ( 'bulksmsbd' === $gateway ) {
			$res = wp_remote_post(
				'https://bulksmsbd.net/api/smsapi',
				array(
					'timeout' => 15,
					'body'    => array(
						'api_key'  => $s['sms_key'],
						'type'     => 'text',
						'number'   => $to,
						'senderid' => $s['sms_sender'],
						'message'  => $message,
					),
				)
			);
		} elseif ( 'alpha' === $gateway ) {
			$body = array(
				'api_key' => $s['sms_key'],
				'msg'     => $message,
				'to'      => $to,
			);
			if ( '' !== $s['sms_sender'] ) {
				$body['sender_id'] = $s['sms_sender'];
			}
			$res = wp_remote_post(
				'https://api.sms.net.bd/sendsms',
				array(
					'timeout' => 15,
					'body'    => $body,
				)
			);
		} elseif ( 'ssl' === $gateway ) {
			$res = wp_remote_post(
				'https://smsplus.sslwireless.com/api/v3/send-sms',
				array(
					'timeout' => 15,
					'headers' => array(
						'Content-Type' => 'application/json',
						'Accept'       => 'application/json',
					),
					'body'    => wp_json_encode(
						array(
							'api_token' => $s['sms_key'],
							'sid'       => $s['sms_ssl_sid'],
							'msisdn'    => $to,
							'sms'       => $message,
							'csms_id'   => substr( 'c' . str_replace( '.', '', uniqid( '', true ) ), 0, 20 ),
						)
					),
				)
			);
		} else {
			$url = strtr(
				(string) $s['sms_custom_url'],
				array(
					'{phone}'   => rawurlencode( $to ),
					'{message}' => rawurlencode( $message ),
				)
			);
			$res = wp_remote_get( $url, array( 'timeout' => 15 ) );
		}
		return self::read_answer( $gateway, $res );
	}

	/**
	 * Did the gateway accept the message?
	 *
	 * @param string         $gateway Gateway.
	 * @param array|WP_Error $res     HTTP response.
	 * @return array{ok:bool,error:string}
	 */
	public static function read_answer( $gateway, $res ) {
		if ( is_wp_error( $res ) ) {
			return array(
				'ok'    => false,
				'error' => $res->get_error_message(),
			);
		}
		$code = (int) wp_remote_retrieve_response_code( $res );
		$raw  = (string) wp_remote_retrieve_body( $res );
		$data = json_decode( $raw, true );
		$data = is_array( $data ) ? $data : array();
		$ok   = $code >= 200 && $code < 300;
		$err  = '';
		if ( 'bulksmsbd' === $gateway ) {
			$ok  = $ok && isset( $data['response_code'] ) && 202 === (int) $data['response_code'];
			$err = isset( $data['error_message'] ) && '' !== $data['error_message'] ? (string) $data['error_message'] : ( isset( $data['response_code'] ) ? 'Error ' . (int) $data['response_code'] : '' );
		} elseif ( 'alpha' === $gateway ) {
			$ok  = $ok && isset( $data['error'] ) && 0 === (int) $data['error'];
			$err = isset( $data['msg'] ) ? (string) $data['msg'] : '';
		} elseif ( 'ssl' === $gateway ) {
			$ok  = $ok && isset( $data['status'] ) && 'SUCCESS' === strtoupper( (string) $data['status'] );
			$err = isset( $data['error_message'] ) ? (string) $data['error_message'] : '';
		}
		if ( ! $ok && '' === $err ) {
			/* translators: %d: HTTP status code. */
			$err = sprintf( __( 'The SMS gateway answered with an error (code %d).', 'cartiva-commerce' ), $code );
		}
		return array(
			'ok'    => $ok,
			'error' => $ok ? '' : $err,
		);
	}

	/* ------------------------------------------------------------------
	 * Messages
	 * ------------------------------------------------------------------ */

	/**
	 * Default message texts.
	 *
	 * @return array
	 */
	public static function default_texts() {
		return array(
			'order'     => __( 'Dear {name}, we got your order #{order} of {total}. We will call you to confirm. - {site}', 'cartiva-commerce' ),
			'courier'   => __( 'Your order #{order} is on the way with {courier}. Track: {tracking} - {site}', 'cartiva-commerce' ),
			'delivered' => __( 'Your order #{order} was delivered. Thank you for shopping with {site}!', 'cartiva-commerce' ),
			'admin'     => __( 'New order #{order}: {total} from {name}, {phone}.', 'cartiva-commerce' ),
			'otp'       => __( 'Your {site} order code is {code}. It works for 10 minutes.', 'cartiva-commerce' ),
			'recovery'  => __( 'Hi {name}, your order at {site} is not finished. Complete it here: {link}', 'cartiva-commerce' ),
		);
	}

	/**
	 * A message text (the saved one or the default).
	 *
	 * @param string $key Message.
	 * @return string
	 */
	public static function text( $key ) {
		$saved = (string) cartiva_commerce_setting( 'sms_tpl_' . $key );
		$texts = self::default_texts();
		return '' !== trim( $saved ) ? $saved : ( isset( $texts[ $key ] ) ? $texts[ $key ] : '' );
	}

	/**
	 * Fill a message.
	 *
	 * @param string $text   Text with {placeholders}.
	 * @param array  $values Values.
	 * @return string
	 */
	public static function fill( $text, $values ) {
		$values = array_merge( array( 'site' => wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ) ), $values );
		$pairs  = array();
		foreach ( $values as $key => $value ) {
			$pairs[ '{' . $key . '}' ] = (string) $value;
		}
		return trim( preg_replace( '/\s+/u', ' ', strtr( $text, $pairs ) ) );
	}

	/**
	 * Values of an order for messages.
	 *
	 * @param WC_Order $order Order.
	 * @return array
	 */
	public static function order_values( $order ) {
		$providers = Cartiva_Commerce_Courier::providers();
		$provider  = (string) $order->get_meta( '_cartiva_courier' );
		return array(
			'name'     => trim( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() ),
			'order'    => $order->get_order_number(),
			'total'    => html_entity_decode( wp_strip_all_tags( wc_price( $order->get_total(), array( 'currency' => $order->get_currency() ) ) ), ENT_QUOTES, 'UTF-8' ),
			'phone'    => Cartiva_Commerce_Courier::phone( $order->get_billing_phone() ),
			'courier'  => isset( $providers[ $provider ] ) ? $providers[ $provider ] : $provider,
			'tracking' => Cartiva_Commerce_Courier::tracking_url( $order ),
		);
	}

	/**
	 * Send a message about an order, once, and note it on the order.
	 *
	 * @param WC_Order $order Order.
	 * @param string   $key   Message.
	 * @param string   $to    Number (default: the customer).
	 * @return bool Sent.
	 */
	public static function order_message( $order, $key, $to = '' ) {
		$flag = '_cartiva_sms_' . $key;
		if ( ! self::ready() || ! cartiva_commerce_setting( 'sms_on_' . $key ) || $order->get_meta( $flag ) ) {
			return false;
		}
		$to     = '' !== $to ? $to : $order->get_billing_phone();
		$result = self::send( $to, self::fill( self::text( $key ), self::order_values( $order ) ) );
		$labels = self::labels();
		if ( $result['ok'] ) {
			$order->update_meta_data( $flag, time() );
			/* translators: 1: message name, 2: phone number. */
			$order->add_order_note( sprintf( __( 'SMS sent (%1$s) to %2$s.', 'cartiva-commerce' ), $labels[ $key ], self::msisdn( $to ) ) );
		} else {
			/* translators: 1: message name, 2: error. */
			$order->add_order_note( sprintf( __( 'SMS not sent (%1$s): %2$s', 'cartiva-commerce' ), $labels[ $key ], $result['error'] ) );
		}
		$order->save();
		return $result['ok'];
	}

	/**
	 * Message names.
	 *
	 * @return array
	 */
	public static function labels() {
		return array(
			'order'     => __( 'Order received', 'cartiva-commerce' ),
			'courier'   => __( 'Sent to courier', 'cartiva-commerce' ),
			'delivered' => __( 'Delivered', 'cartiva-commerce' ),
			'admin'     => __( 'New order alert for you', 'cartiva-commerce' ),
			'otp'       => __( 'Order code', 'cartiva-commerce' ),
			'recovery'  => __( 'Unfinished order reminder', 'cartiva-commerce' ),
		);
	}

	/**
	 * New order: tell the customer and the shop.
	 *
	 * @param int      $order_id Order ID.
	 * @param array    $data     Posted data.
	 * @param WC_Order $order    Order.
	 */
	public static function order_placed( $order_id, $data = array(), $order = null ) {
		$order = $order instanceof WC_Order ? $order : wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}
		self::order_message( $order, 'order' );
		$shop = (string) cartiva_commerce_setting( 'sms_shop_phone' );
		if ( '' !== $shop ) {
			self::order_message( $order, 'admin', $shop );
		}
	}

	/**
	 * New order through the Checkout block.
	 *
	 * @param WC_Order $order Order.
	 */
	public static function block_order_placed( $order ) {
		if ( $order instanceof WC_Order ) {
			self::order_placed( $order->get_id(), array(), $order );
		}
	}

	/**
	 * Parcel booked.
	 *
	 * @param WC_Order $order Order.
	 */
	public static function parcel_sent( $order ) {
		self::order_message( $order, 'courier' );
	}

	/**
	 * Parcel status changed.
	 *
	 * @param WC_Order $order Order.
	 * @param string   $state Plain status.
	 */
	public static function parcel_status( $order, $state ) {
		if ( 'delivered' === $state ) {
			self::order_message( $order, 'delivered' );
		}
	}

	/**
	 * SMS reminder for an unfinished checkout, once, a while after it was abandoned.
	 *
	 * @param int $id  Abandoned checkout ID.
	 * @param int $now Time.
	 * @return bool Sent.
	 */
	public static function recovery_reminder( $id, $now ) {
		if ( ! self::ready() || ! cartiva_commerce_setting( 'sms_on_recovery' ) || get_post_meta( $id, '_sms_sent', true ) ) {
			return false;
		}
		$abandoned = (int) get_post_meta( $id, '_abandoned', true );
		$wait      = max( 0, (int) cartiva_commerce_setting( 'sms_recovery_after' ) ) * MINUTE_IN_SECONDS;
		if ( ! $abandoned || $now - $abandoned < $wait ) {
			return false;
		}
		$details = Cartiva_Commerce_Recovery::meta_array( $id, '_details' );
		$phone   = isset( $details['billing_phone'] ) ? (string) $details['billing_phone'] : '';
		if ( '' === self::msisdn( $phone ) ) {
			update_post_meta( $id, '_sms_sent', -1 );
			return false;
		}
		$name   = isset( $details['billing_full_name'] ) ? $details['billing_full_name'] : ( isset( $details['billing_first_name'] ) ? $details['billing_first_name'] : '' );
		$result = self::send(
			$phone,
			self::fill(
				self::text( 'recovery' ),
				array(
					'name' => $name,
					'link' => Cartiva_Commerce_Recovery::link( $id ),
				)
			)
		);
		update_post_meta( $id, '_sms_sent', $result['ok'] ? $now : -1 );
		return $result['ok'];
	}

	/* ------------------------------------------------------------------
	 * One-time codes
	 * ------------------------------------------------------------------ */

	/**
	 * Are codes asked on the current form?
	 *
	 * @return bool
	 */
	public static function otp_here() {
		if ( ! self::ready() ) {
			return false;
		}
		return cartiva_commerce_current_landing_id() ? (bool) cartiva_commerce_setting( 'otp_landing' ) : (bool) cartiva_commerce_setting( 'otp_main' );
	}

	/**
	 * Was this phone confirmed (in this visit, or recently)?
	 *
	 * @param string $key Phone key.
	 * @return bool
	 */
	public static function verified( $key ) {
		if ( '' === $key ) {
			return false;
		}
		$ok = WC()->session ? (array) WC()->session->get( 'cartiva_otp_ok' ) : array();
		return in_array( $key, $ok, true ) || (bool) get_transient( 'cartiva_otp_ok_' . md5( $key ) );
	}

	/**
	 * Remember a confirmed phone.
	 *
	 * @param string $key Phone key.
	 */
	public static function remember( $key ) {
		if ( WC()->session ) {
			$ok   = (array) WC()->session->get( 'cartiva_otp_ok' );
			$ok[] = $key;
			WC()->session->set( 'cartiva_otp_ok', array_values( array_unique( $ok ) ) );
		}
		$days = (int) cartiva_commerce_setting( 'otp_remember' );
		if ( $days > 0 ) {
			set_transient( 'cartiva_otp_ok_' . md5( $key ), 1, $days * DAY_IN_SECONDS );
		}
	}

	/**
	 * Send a new code to a phone (with limits).
	 *
	 * @param string $phone Phone.
	 * @return array{ok:bool,error:string}
	 */
	public static function send_code( $phone ) {
		$key = Cartiva_Commerce_Recovery::phone_key( $phone );
		if ( '' === self::msisdn( $phone ) || ! WC()->session ) {
			return array(
				'ok'    => false,
				'error' => __( 'Please enter a valid mobile number (01XXXXXXXXX).', 'cartiva-commerce' ),
			);
		}
		$saved = (array) WC()->session->get( 'cartiva_otp' );
		if ( isset( $saved['key'], $saved['sent'] ) && $saved['key'] === $key && time() - (int) $saved['sent'] < self::OTP_RESEND ) {
			return array(
				'ok'    => false,
				/* translators: %d: seconds. */
				'error' => sprintf( __( 'Please wait %d seconds before asking for a new code.', 'cartiva-commerce' ), self::OTP_RESEND - ( time() - (int) $saved['sent'] ) ),
			);
		}
		$limit = 'cartiva_otp_count_' . md5( $key );
		$count = (int) get_transient( $limit );
		if ( $count >= self::OTP_PER_HOUR ) {
			return array(
				'ok'    => false,
				'error' => __( 'Too many codes for this number. Please try again in an hour or call us.', 'cartiva-commerce' ),
			);
		}
		$code   = (string) wp_rand( 1000, 9999 );
		$result = self::send( $phone, self::fill( self::text( 'otp' ), array( 'code' => $code ) ) );
		if ( ! $result['ok'] ) {
			return array(
				'ok'    => false,
				'error' => __( 'We could not send the code by SMS. Please try again or call us.', 'cartiva-commerce' ),
			);
		}
		set_transient( $limit, $count + 1, HOUR_IN_SECONDS );
		WC()->session->set(
			'cartiva_otp',
			array(
				'key'   => $key,
				'hash'  => wp_hash( $key . '|' . $code ),
				'sent'  => time(),
				'tries' => 0,
			)
		);
		return array(
			'ok'    => true,
			'error' => '',
		);
	}

	/**
	 * Check a typed code.
	 *
	 * @param string $phone Phone.
	 * @param string $code  Code.
	 * @return string ok, wrong, expired (send a new one).
	 */
	public static function check_code( $phone, $code ) {
		$key   = Cartiva_Commerce_Recovery::phone_key( $phone );
		$saved = WC()->session ? (array) WC()->session->get( 'cartiva_otp' ) : array();
		if ( ! isset( $saved['key'], $saved['hash'] ) || $saved['key'] !== $key || time() - (int) $saved['sent'] > self::OTP_TTL || (int) $saved['tries'] >= self::OTP_TRIES ) {
			return 'expired';
		}
		if ( hash_equals( (string) $saved['hash'], wp_hash( $key . '|' . preg_replace( '/\D/', '', $code ) ) ) ) {
			WC()->session->set( 'cartiva_otp', null );
			self::remember( $key );
			return 'ok';
		}
		++$saved['tries'];
		WC()->session->set( 'cartiva_otp', $saved );
		return 'wrong';
	}

	/**
	 * Code box on the form (shown once a code is sent).
	 */
	public static function otp_field() {
		if ( ! self::otp_here() ) {
			return;
		}
		?>
		<div class="cartiva-otp" hidden>
			<p class="form-row form-row-wide">
				<label for="cartiva-otp-code"><?php esc_html_e( 'Code from the SMS', 'cartiva-commerce' ); ?></label>
				<input type="text" id="cartiva-otp-code" name="cartiva_otp" class="input-text" inputmode="numeric" pattern="[0-9]*" maxlength="6" autocomplete="one-time-code" />
			</p>
			<p class="cartiva-otp__help">
				<?php esc_html_e( 'We sent a 4-digit code to your phone. Type it here and place the order again.', 'cartiva-commerce' ); ?>
				<button type="button" class="button-link cartiva-otp__resend"><?php esc_html_e( 'Send a new code', 'cartiva-commerce' ); ?></button>
			</p>
			<p class="cartiva-otp__msg" role="status"></p>
		</div>
		<?php
	}

	/**
	 * Ask for the code before a cash on delivery order is accepted.
	 *
	 * @param array    $data   Posted data.
	 * @param WP_Error $errors Errors.
	 */
	public static function otp_check( $data, $errors ) {
		if ( ! self::otp_here() || ( method_exists( $errors, 'has_errors' ) && $errors->has_errors() ) ) {
			return;
		}
		$method = isset( $data['payment_method'] ) ? (string) $data['payment_method'] : '';
		if ( 'cod' !== $method && ! cartiva_commerce_setting( 'otp_all_methods' ) ) {
			return;
		}
		$phone = isset( $data['billing_phone'] ) ? (string) $data['billing_phone'] : '';
		$key   = Cartiva_Commerce_Recovery::phone_key( $phone );
		if ( self::verified( $key ) ) {
			return;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- WooCommerce checked the checkout nonce.
		$code = isset( $_POST['cartiva_otp'] ) ? sanitize_text_field( wp_unslash( $_POST['cartiva_otp'] ) ) : '';
		if ( '' !== $code ) {
			$check = self::check_code( $phone, $code );
			if ( 'ok' === $check ) {
				return;
			}
			if ( 'wrong' === $check ) {
				$errors->add( 'cartiva_otp', __( 'That code is not right. Please check the SMS and try again.', 'cartiva-commerce' ), array( 'id' => 'cartiva_otp' ) );
				return;
			}
		}
		$sent = self::send_code( $phone );
		$errors->add(
			'cartiva_otp',
			$sent['ok']
				/* translators: %s: phone number. */
				? sprintf( __( 'Please confirm your phone number: we sent a code by SMS to %s. Type it in the box and place the order again.', 'cartiva-commerce' ), Cartiva_Commerce_Courier::phone( $phone ) )
				: $sent['error'],
			array( 'id' => 'cartiva_otp' )
		);
	}

	/**
	 * Send a new code (wc-ajax endpoint).
	 */
	public static function ajax_resend() {
		check_ajax_referer( 'cartiva_commerce_otp', 'nonce' );
		$phone  = isset( $_POST['phone'] ) ? sanitize_text_field( wp_unslash( $_POST['phone'] ) ) : '';
		$result = self::send_code( $phone );
		$result['ok']
			? wp_send_json_success( array( 'message' => __( 'A new code is on its way.', 'cartiva-commerce' ) ) )
			: wp_send_json_error( array( 'message' => $result['error'] ) );
	}

	/**
	 * Script for the code box.
	 */
	public static function assets() {
		if ( ! function_exists( 'is_checkout' ) ) {
			return;
		}
		if ( ! cartiva_commerce_current_landing_id() && ( ! is_checkout() || is_wc_endpoint_url() ) ) {
			return;
		}
		if ( ! self::otp_here() ) {
			return;
		}
		wp_enqueue_style( 'cartiva-commerce-otp', CARTIVA_COMMERCE_URL . 'assets/css/otp.css', array(), CARTIVA_COMMERCE_VERSION );
		wp_enqueue_script( 'cartiva-commerce-otp', CARTIVA_COMMERCE_URL . 'assets/js/otp.js', array( 'jquery' ), CARTIVA_COMMERCE_VERSION, true );
		wp_localize_script(
			'cartiva-commerce-otp',
			'cartivaOtp',
			array(
				'url'   => WC_AJAX::get_endpoint( 'cartiva_commerce_otp_resend' ),
				'nonce' => wp_create_nonce( 'cartiva_commerce_otp' ),
			)
		);
	}

	/* ------------------------------------------------------------------
	 * Settings test
	 * ------------------------------------------------------------------ */

	/**
	 * Send a test SMS (settings button).
	 */
	public static function ajax_test() {
		check_ajax_referer( 'cartiva_courier', 'nonce' );
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'You are not allowed to do this.', 'cartiva-commerce' ) ), 403 );
		}
		if ( ! self::ready() ) {
			wp_send_json_error( array( 'message' => __( 'Choose a gateway, fill in its details and save first.', 'cartiva-commerce' ) ) );
		}
		$phone  = isset( $_POST['phone'] ) ? sanitize_text_field( wp_unslash( $_POST['phone'] ) ) : '';
		$result = self::send( $phone, self::fill( __( 'Test SMS from {site}. Your SMS settings work.', 'cartiva-commerce' ), array() ) );
		$result['ok']
			? wp_send_json_success( array( 'message' => __( 'Sent. Check the phone.', 'cartiva-commerce' ) ) )
			: wp_send_json_error( array( 'message' => $result['error'] ) );
	}
}
