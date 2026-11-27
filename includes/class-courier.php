<?php
/**
 * Courier booking: send orders to Steadfast, Pathao or RedX from the order
 * screen or the Orders list, keep the parcel status up to date, check a
 * customer's delivery history, and show the tracking link to the customer.
 *
 * @package Cartiva_Commerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Courier.
 */
class Cartiva_Commerce_Courier {

	const CRON = 'cartiva_commerce_courier_sync';

	/**
	 * Hook up.
	 */
	public static function init() {
		add_action( 'add_meta_boxes', array( __CLASS__, 'meta_box' ), 31 );
		add_action( 'wp_ajax_cartiva_courier_send', array( __CLASS__, 'ajax_send' ) );
		add_action( 'wp_ajax_cartiva_courier_refresh', array( __CLASS__, 'ajax_refresh' ) );
		add_action( 'wp_ajax_cartiva_courier_history', array( __CLASS__, 'ajax_history' ) );
		add_action( 'wp_ajax_cartiva_courier_test', array( __CLASS__, 'ajax_test' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
		// Orders list.
		add_filter( 'manage_woocommerce_page_wc-orders_columns', array( __CLASS__, 'columns' ), 20 );
		add_action( 'manage_woocommerce_page_wc-orders_custom_column', array( __CLASS__, 'column_hpos' ), 10, 2 );
		add_filter( 'manage_edit-shop_order_columns', array( __CLASS__, 'columns' ), 20 );
		add_action( 'manage_shop_order_posts_custom_column', array( __CLASS__, 'column_legacy' ), 10, 2 );
		foreach ( array( 'woocommerce_page_wc-orders', 'edit-shop_order' ) as $screen ) {
			add_filter( 'bulk_actions-' . $screen, array( __CLASS__, 'bulk_actions' ) );
			add_filter( 'handle_bulk_actions-' . $screen, array( __CLASS__, 'handle_bulk' ), 10, 3 );
		}
		add_action( 'admin_notices', array( __CLASS__, 'bulk_notice' ) );
		// Status sync.
		add_action( 'init', array( __CLASS__, 'schedule' ) );
		add_action( self::CRON, array( __CLASS__, 'sync' ) );
		add_action( 'rest_api_init', array( __CLASS__, 'routes' ) );
		// Customer tracking link.
		add_action( 'woocommerce_order_details_after_order_table', array( __CLASS__, 'customer_tracking' ) );
	}

	/* ------------------------------------------------------------------
	 * Providers and settings
	 * ------------------------------------------------------------------ */

	/**
	 * Couriers: key => label.
	 *
	 * @return array
	 */
	public static function providers() {
		return array(
			'steadfast' => __( 'Steadfast', 'cartiva-commerce' ),
			'pathao'    => __( 'Pathao', 'cartiva-commerce' ),
			'redx'      => __( 'RedX', 'cartiva-commerce' ),
		);
	}

	/**
	 * Has the courier got its keys?
	 *
	 * @param string $provider Courier.
	 * @return bool
	 */
	public static function configured( $provider ) {
		$s = cartiva_commerce_settings();
		if ( 'steadfast' === $provider ) {
			return '' !== $s['sf_key'] && '' !== $s['sf_secret'];
		}
		if ( 'pathao' === $provider ) {
			return '' !== $s['pa_client_id'] && '' !== $s['pa_client_secret'] && '' !== $s['pa_username'] && '' !== $s['pa_password'] && (int) $s['pa_store_id'] > 0;
		}
		if ( 'redx' === $provider ) {
			return '' !== $s['rx_token'];
		}
		return false;
	}

	/**
	 * Couriers that are ready to use.
	 *
	 * @return array key => label.
	 */
	public static function ready() {
		$out = array();
		foreach ( self::providers() as $key => $label ) {
			if ( self::configured( $key ) ) {
				$out[ $key ] = $label;
			}
		}
		return $out;
	}

	/**
	 * The default courier (the chosen one if ready, else the first ready).
	 *
	 * @return string
	 */
	public static function default_provider() {
		$ready  = self::ready();
		$chosen = (string) cartiva_commerce_setting( 'courier_default' );
		if ( isset( $ready[ $chosen ] ) ) {
			return $chosen;
		}
		return $ready ? (string) key( $ready ) : '';
	}

	/**
	 * API base URL.
	 *
	 * @param string $provider Courier.
	 * @return string
	 */
	public static function base( $provider ) {
		$s = cartiva_commerce_settings();
		if ( 'pathao' === $provider ) {
			return ! empty( $s['pa_sandbox'] ) ? 'https://courier-api-sandbox.pathao.com' : 'https://api-hermes.pathao.com';
		}
		if ( 'redx' === $provider ) {
			return ! empty( $s['rx_sandbox'] ) ? 'https://sandbox.redx.com.bd/v1.0.0-beta' : 'https://openapi.redx.com.bd/v1.0.0-beta';
		}
		return 'https://portal.packzy.com/api/v1';
	}

	/* ------------------------------------------------------------------
	 * HTTP
	 * ------------------------------------------------------------------ */

	/**
	 * Call a courier API.
	 *
	 * @param string     $provider Courier.
	 * @param string     $method   GET or POST.
	 * @param string     $path     Path after the base URL.
	 * @param array|null $body     JSON body.
	 * @return array{ok:bool,code:int,data:array,error:string}
	 */
	public static function call( $provider, $method, $path, $body = null ) {
		$headers = array(
			'Accept'       => 'application/json',
			'Content-Type' => 'application/json',
		);
		$s       = cartiva_commerce_settings();
		if ( 'steadfast' === $provider ) {
			$headers['Api-Key']    = $s['sf_key'];
			$headers['Secret-Key'] = $s['sf_secret'];
		} elseif ( 'redx' === $provider ) {
			$headers['API-ACCESS-TOKEN'] = 'Bearer ' . preg_replace( '/^Bearer\s+/i', '', $s['rx_token'] );
		} elseif ( 'pathao' === $provider && '/aladdin/api/v1/issue-token' !== $path ) {
			$token = self::pathao_token();
			if ( '' === $token ) {
				return self::result( false, 401, array(), __( 'Pathao did not accept the API details. Check the client ID, secret, email and password.', 'cartiva-commerce' ) );
			}
			$headers['Authorization'] = 'Bearer ' . $token;
		}
		$args     = array(
			'method'  => $method,
			'headers' => $headers,
			'timeout' => 20,
		);
		if ( null !== $body ) {
			$args['body'] = wp_json_encode( $body );
		}
		$response = wp_remote_request( self::base( $provider ) . $path, $args );
		if ( is_wp_error( $response ) ) {
			/* translators: %s: error message. */
			return self::result( false, 0, array(), sprintf( __( 'Could not reach the courier: %s', 'cartiva-commerce' ), $response->get_error_message() ) );
		}
		$code = (int) wp_remote_retrieve_response_code( $response );
		$data = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		$data = is_array( $data ) ? $data : array();
		$ok   = $code >= 200 && $code < 300 && ( ! isset( $data['status'] ) || ! is_numeric( $data['status'] ) || 200 === (int) $data['status'] );
		return self::result( $ok, $code, $data, $ok ? '' : self::error_text( $data, $code ) );
	}

	/**
	 * Result array.
	 *
	 * @param bool   $ok    Success.
	 * @param int    $code  HTTP code.
	 * @param array  $data  Decoded body.
	 * @param string $error Error text.
	 * @return array
	 */
	private static function result( $ok, $code, $data, $error ) {
		return array(
			'ok'    => $ok,
			'code'  => $code,
			'data'  => $data,
			'error' => $error,
		);
	}

	/**
	 * A readable error from a courier response.
	 *
	 * @param array $data Body.
	 * @param int   $code HTTP code.
	 * @return string
	 */
	public static function error_text( $data, $code ) {
		$parts = array();
		if ( isset( $data['message'] ) && is_string( $data['message'] ) ) {
			$parts[] = $data['message'];
		}
		foreach ( array( 'errors', 'validation_errors', 'error' ) as $key ) {
			if ( ! isset( $data[ $key ] ) ) {
				continue;
			}
			if ( is_string( $data[ $key ] ) ) {
				$parts[] = $data[ $key ];
			} elseif ( is_array( $data[ $key ] ) ) {
				array_walk_recursive(
					$data[ $key ],
					function ( $value ) use ( &$parts ) {
						if ( is_string( $value ) ) {
							$parts[] = $value;
						}
					}
				);
			}
		}
		$parts = array_unique( array_filter( array_map( 'trim', $parts ) ) );
		if ( $parts ) {
			return implode( ' ', $parts );
		}
		if ( 401 === $code || 403 === $code ) {
			return __( 'The courier did not accept your API details.', 'cartiva-commerce' );
		}
		/* translators: %d: HTTP status code. */
		return sprintf( __( 'The courier answered with an error (code %d).', 'cartiva-commerce' ), $code );
	}

	/**
	 * Pathao access token (kept until it expires).
	 *
	 * @param bool $fresh Ignore the saved token.
	 * @return string
	 */
	public static function pathao_token( $fresh = false ) {
		$s   = cartiva_commerce_settings();
		$key = 'cartiva_pathao_token_' . md5( $s['pa_client_id'] . '|' . $s['pa_username'] . '|' . ( empty( $s['pa_sandbox'] ) ? 'live' : 'sandbox' ) );
		if ( ! $fresh ) {
			$saved = get_transient( $key );
			if ( is_string( $saved ) && '' !== $saved ) {
				return $saved;
			}
		}
		$result = self::call(
			'pathao',
			'POST',
			'/aladdin/api/v1/issue-token',
			array(
				'client_id'     => $s['pa_client_id'],
				'client_secret' => $s['pa_client_secret'],
				'grant_type'    => 'password',
				'username'      => $s['pa_username'],
				'password'      => $s['pa_password'],
			)
		);
		$token  = $result['ok'] && isset( $result['data']['access_token'] ) ? (string) $result['data']['access_token'] : '';
		if ( '' !== $token ) {
			$life = isset( $result['data']['expires_in'] ) ? (int) $result['data']['expires_in'] : DAY_IN_SECONDS;
			set_transient( $key, $token, max( HOUR_IN_SECONDS, min( 30 * DAY_IN_SECONDS, $life - HOUR_IN_SECONDS ) ) );
		}
		return $token;
	}

	/* ------------------------------------------------------------------
	 * Building parcels
	 * ------------------------------------------------------------------ */

	/**
	 * Phone as 01XXXXXXXXX.
	 *
	 * @param string $phone Phone.
	 * @return string
	 */
	public static function phone( $phone ) {
		$digits = preg_replace( '/\D/', '', (string) $phone );
		if ( 13 === strlen( $digits ) && 0 === strpos( $digits, '880' ) ) {
			$digits = substr( $digits, 2 );
		} elseif ( 10 === strlen( $digits ) && '1' === $digits[0] ) {
			$digits = '0' . $digits;
		}
		return $digits;
	}

	/**
	 * Recipient details of an order (shipping address when there is one).
	 *
	 * @param WC_Order $order Order.
	 * @return array name, phone, address, city.
	 */
	public static function recipient( $order ) {
		$use   = $order->has_shipping_address() ? 'shipping' : 'billing';
		$get   = function ( $field ) use ( $order, $use ) {
			$method = 'get_' . $use . '_' . $field;
			return is_callable( array( $order, $method ) ) ? trim( (string) $order->$method() ) : '';
		};
		$name  = trim( $get( 'first_name' ) . ' ' . $get( 'last_name' ) );
		$state = $get( 'state' );
		if ( '' !== $state && function_exists( 'WC' ) && WC()->countries ) {
			$states = WC()->countries->get_states( $get( 'country' ) ? $get( 'country' ) : 'BD' );
			$state  = is_array( $states ) && isset( $states[ $state ] ) ? $states[ $state ] : $state;
		}
		$city    = $get( 'city' );
		$address = implode( ', ', array_unique( array_filter( array( $get( 'address_1' ), $get( 'address_2' ), $city, $state ) ) ) );
		$phone   = 'shipping' === $use && is_callable( array( $order, 'get_shipping_phone' ) ) && '' !== (string) $order->get_shipping_phone() ? $order->get_shipping_phone() : $order->get_billing_phone();
		return array(
			'name'    => '' !== $name ? $name : trim( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() ),
			'phone'   => self::phone( $phone ),
			'address' => $address,
			'city'    => '' !== $city ? $city : $state,
			'state'   => $state,
		);
	}

	/**
	 * Short list of the order's items.
	 *
	 * @param WC_Order $order Order.
	 * @return array text, quantity.
	 */
	public static function items( $order ) {
		$names = array();
		$qty   = 0;
		foreach ( $order->get_items() as $item ) {
			$names[] = wp_strip_all_tags( $item->get_name() ) . ' x ' . (int) $item->get_quantity();
			$qty    += (int) $item->get_quantity();
		}
		$text = implode( ', ', $names );
		if ( function_exists( 'mb_strlen' ) && mb_strlen( $text ) > 190 ) {
			$text = mb_substr( $text, 0, 187 ) . '...';
		}
		return array(
			'text'     => $text,
			'quantity' => max( 1, $qty ),
		);
	}

	/**
	 * Default send options for an order.
	 *
	 * @param WC_Order $order    Order.
	 * @param string   $provider Courier (the RedX area is only looked up for RedX).
	 * @return array cod, note, weight, area_id, area_name.
	 */
	public static function defaults( $order, $provider = 'redx' ) {
		$s    = cartiva_commerce_settings();
		$area = 'redx' === $provider && self::configured( 'redx' ) ? self::guess_redx_area( $order ) : null;
		return array(
			'cod'       => 'cod' === $order->get_payment_method() ? (float) $order->get_total() : 0.0,
			'note'      => trim( (string) $s['courier_note'] . ( '' !== (string) $order->get_customer_note() ? ' ' . $order->get_customer_note() : '' ) ),
			'weight'    => (float) $s['courier_weight'] > 0 ? (float) $s['courier_weight'] : 0.5,
			'area_id'   => $area ? (int) $area['id'] : (int) $s['rx_area_id'],
			'area_name' => $area ? (string) $area['name'] : (string) $s['rx_area_name'],
		);
	}

	/**
	 * Request body for a courier.
	 *
	 * @param string   $provider Courier.
	 * @param WC_Order $order    Order.
	 * @param array    $args     Options: cod, note, weight, area_id, area_name.
	 * @return array
	 */
	public static function payload( $provider, $order, $args ) {
		$s     = cartiva_commerce_settings();
		$to    = self::recipient( $order );
		$items = self::items( $order );
		$cod   = max( 0, round( (float) $args['cod'] ) );
		$note  = mb_substr( (string) $args['note'], 0, 250 );
		if ( 'pathao' === $provider ) {
			$body = array(
				'store_id'            => (int) $s['pa_store_id'],
				'merchant_order_id'   => (string) $order->get_order_number(),
				'recipient_name'      => $to['name'],
				'recipient_phone'     => $to['phone'],
				'recipient_address'   => $to['address'],
				'delivery_type'       => 48,
				'item_type'           => 2,
				'special_instruction' => $note,
				'item_quantity'       => $items['quantity'],
				'item_weight'         => max( 0.5, min( 10, (float) $args['weight'] ) ),
				'item_description'    => $items['text'],
				'amount_to_collect'   => (int) $cod,
			);
			return array_filter(
				$body,
				function ( $value ) {
					return '' !== $value;
				}
			);
		}
		if ( 'redx' === $provider ) {
			$body = array(
				'customer_name'          => $to['name'],
				'customer_phone'         => $to['phone'],
				'delivery_area'          => (string) $args['area_name'],
				'delivery_area_id'       => (int) $args['area_id'],
				'customer_address'       => $to['address'],
				'merchant_invoice_id'    => (string) $order->get_order_number(),
				'cash_collection_amount' => (string) $cod,
				'parcel_weight'          => (int) round( max( 0.1, (float) $args['weight'] ) * 1000 ),
				'instruction'            => $note,
				'value'                  => (int) round( (float) $order->get_total() ),
			);
			if ( (int) $s['rx_store_id'] > 0 ) {
				$body['pickup_store_id'] = (int) $s['rx_store_id'];
			}
			return $body;
		}
		return array(
			'invoice'           => (string) $order->get_order_number(),
			'recipient_name'    => $to['name'],
			'recipient_phone'   => $to['phone'],
			'recipient_address' => $to['address'],
			'cod_amount'        => $cod,
			'note'              => $note,
			'item_description'  => $items['text'],
		);
	}

	/**
	 * What is missing before a parcel can be booked.
	 *
	 * @param string   $provider Courier.
	 * @param WC_Order $order    Order.
	 * @param array    $args     Options.
	 * @return string Empty when fine.
	 */
	public static function problem( $provider, $order, $args ) {
		$providers = self::providers();
		if ( ! isset( $providers[ $provider ] ) || ! self::configured( $provider ) ) {
			return __( 'Add this courier\'s API details in Settings > Courier first.', 'cartiva-commerce' );
		}
		if ( '' !== (string) $order->get_meta( '_cartiva_consignment' ) ) {
			return __( 'This order was already sent to a courier.', 'cartiva-commerce' );
		}
		$to = self::recipient( $order );
		if ( ! preg_match( '/^01[3-9]\d{8}$/', $to['phone'] ) ) {
			return __( 'The phone number is not a valid Bangladeshi mobile number (01XXXXXXXXX).', 'cartiva-commerce' );
		}
		if ( strlen( $to['address'] ) < 10 ) {
			return __( 'The address is too short. Couriers need at least 10 characters.', 'cartiva-commerce' );
		}
		if ( 'redx' === $provider && ( (int) $args['area_id'] <= 0 || '' === (string) $args['area_name'] ) ) {
			return __( 'Choose the RedX delivery area.', 'cartiva-commerce' );
		}
		return '';
	}

	/**
	 * Book a parcel for an order.
	 *
	 * @param WC_Order $order    Order.
	 * @param string   $provider Courier.
	 * @param array    $args     Options (missing ones come from defaults()).
	 * @return array{ok:bool,message:string}
	 */
	public static function send( $order, $provider = '', $args = array() ) {
		$provider = '' !== $provider ? $provider : self::default_provider();
		$args     = wp_parse_args( $args, self::defaults( $order, $provider ) );
		$problem  = self::problem( $provider, $order, $args );
		if ( '' !== $problem ) {
			return array(
				'ok'      => false,
				'message' => $problem,
			);
		}
		$body = self::payload( $provider, $order, $args );
		$path = array(
			'steadfast' => '/create_order',
			'pathao'    => '/aladdin/api/v1/orders',
			'redx'      => '/parcel',
		);
		$res  = self::call( $provider, 'POST', $path[ $provider ], $body );
		$ids  = $res['ok'] ? self::parse_created( $provider, $res['data'] ) : array();
		if ( empty( $ids['consignment'] ) ) {
			$message = '' !== $res['error'] ? $res['error'] : __( 'The courier did not return a parcel number.', 'cartiva-commerce' );
			/* translators: 1: courier, 2: error. */
			$order->add_order_note( sprintf( __( '%1$s booking failed: %2$s', 'cartiva-commerce' ), self::providers()[ $provider ], $message ) );
			return array(
				'ok'      => false,
				'message' => $message,
			);
		}
		$order->update_meta_data( '_cartiva_courier', $provider );
		$order->update_meta_data( '_cartiva_consignment', $ids['consignment'] );
		$order->update_meta_data( '_cartiva_tracking', $ids['tracking'] );
		$order->update_meta_data( '_cartiva_courier_cod', (float) $body[ array( 'steadfast' => 'cod_amount', 'pathao' => 'amount_to_collect', 'redx' => 'cash_collection_amount' )[ $provider ] ] );
		self::set_status( $order, $ids['status'], false );
		$order->update_meta_data( '_cartiva_courier_sent', time() );
		/* translators: 1: courier, 2: parcel number. */
		$order->add_order_note( sprintf( __( 'Sent to %1$s. Parcel %2$s.', 'cartiva-commerce' ), self::providers()[ $provider ], $ids['tracking'] ) );
		$order->save();
		return array(
			'ok'      => true,
			/* translators: 1: courier, 2: parcel number. */
			'message' => sprintf( __( 'Sent to %1$s. Parcel %2$s.', 'cartiva-commerce' ), self::providers()[ $provider ], $ids['tracking'] ),
		);
	}

	/**
	 * Parcel numbers from a create response.
	 *
	 * @param string $provider Courier.
	 * @param array  $data     Body.
	 * @return array consignment, tracking, status.
	 */
	public static function parse_created( $provider, $data ) {
		if ( 'steadfast' === $provider ) {
			$c = isset( $data['consignment'] ) && is_array( $data['consignment'] ) ? $data['consignment'] : array();
			return array(
				'consignment' => isset( $c['consignment_id'] ) ? (string) $c['consignment_id'] : '',
				'tracking'    => isset( $c['tracking_code'] ) ? (string) $c['tracking_code'] : ( isset( $c['consignment_id'] ) ? (string) $c['consignment_id'] : '' ),
				'status'      => isset( $c['status'] ) ? (string) $c['status'] : 'in_review',
			);
		}
		if ( 'pathao' === $provider ) {
			$d = isset( $data['data'] ) && is_array( $data['data'] ) ? $data['data'] : array();
			return array(
				'consignment' => isset( $d['consignment_id'] ) ? (string) $d['consignment_id'] : '',
				'tracking'    => isset( $d['consignment_id'] ) ? (string) $d['consignment_id'] : '',
				'status'      => isset( $d['order_status'] ) ? (string) $d['order_status'] : 'Pending',
			);
		}
		return array(
			'consignment' => isset( $data['tracking_id'] ) ? (string) $data['tracking_id'] : '',
			'tracking'    => isset( $data['tracking_id'] ) ? (string) $data['tracking_id'] : '',
			'status'      => 'pickup-pending',
		);
	}

	/* ------------------------------------------------------------------
	 * Status
	 * ------------------------------------------------------------------ */

	/**
	 * Courier status in plain words: pending, transit, delivered, partial, returned.
	 *
	 * @param string $raw Courier's own status.
	 * @return string
	 */
	public static function normalize( $raw ) {
		$raw = strtolower( str_replace( array( ' ', '-' ), '_', trim( (string) $raw ) ) );
		if ( '' === $raw ) {
			return 'pending';
		}
		if ( in_array( $raw, array( 'delivered' ), true ) ) {
			return 'delivered';
		}
		if ( in_array( $raw, array( 'partial_delivered', 'partial_delivery' ), true ) ) {
			return 'partial';
		}
		if ( in_array( $raw, array( 'cancelled', 'return', 'returned', 'paid_return', 'pickup_cancelled', 'agent_returning', 'returned_to_merchant' ), true ) ) {
			return 'returned';
		}
		if ( in_array( $raw, array( 'in_review', 'pending', 'pickup_pending', 'pickup_requested', 'assigned_for_pickup', 'pickup_processing' ), true ) ) {
			return 'pending';
		}
		return 'transit';
	}

	/**
	 * Labels of the plain statuses.
	 *
	 * @return array
	 */
	public static function status_labels() {
		return array(
			'pending'   => __( 'Waiting for pickup', 'cartiva-commerce' ),
			'transit'   => __( 'On the way', 'cartiva-commerce' ),
			'delivered' => __( 'Delivered', 'cartiva-commerce' ),
			'partial'   => __( 'Partly delivered', 'cartiva-commerce' ),
			'returned'  => __( 'Returned / cancelled', 'cartiva-commerce' ),
		);
	}

	/**
	 * Save a new courier status on the order.
	 *
	 * @param WC_Order $order Order.
	 * @param string   $raw   Courier's status.
	 * @param bool     $save  Save the order.
	 * @return bool Changed.
	 */
	public static function set_status( $order, $raw, $save = true ) {
		$raw = trim( (string) $raw );
		if ( '' === $raw || $raw === (string) $order->get_meta( '_cartiva_courier_status' ) ) {
			return false;
		}
		$plain = self::normalize( $raw );
		$order->update_meta_data( '_cartiva_courier_status', $raw );
		$order->update_meta_data( '_cartiva_courier_state', $plain );
		$order->update_meta_data( '_cartiva_courier_checked', time() );
		if ( '' !== (string) $order->get_meta( '_cartiva_courier_sent' ) ) {
			/* translators: %s: courier status. */
			$order->add_order_note( sprintf( __( 'Courier status: %s', 'cartiva-commerce' ), self::status_labels()[ $plain ] . ' (' . $raw . ')' ) );
		}
		if ( 'delivered' === $plain && cartiva_commerce_setting( 'courier_complete' ) && $order->has_status( array( 'processing', 'on-hold', 'pending' ) ) ) {
			$order->set_status( 'completed', __( 'Delivered by the courier.', 'cartiva-commerce' ) );
		}
		if ( 'returned' === $plain && cartiva_commerce_setting( 'courier_cancel' ) && $order->has_status( array( 'processing', 'on-hold', 'pending' ) ) ) {
			$order->set_status( 'cancelled', __( 'Returned by the courier.', 'cartiva-commerce' ) );
		}
		if ( $save ) {
			$order->save();
		}
		return true;
	}

	/**
	 * Is the parcel finished (no more status changes expected)?
	 *
	 * @param WC_Order $order Order.
	 * @return bool
	 */
	public static function finished( $order ) {
		return in_array( (string) $order->get_meta( '_cartiva_courier_state' ), array( 'delivered', 'partial', 'returned' ), true );
	}

	/**
	 * Ask the courier for the latest status.
	 *
	 * @param WC_Order $order Order.
	 * @return array{ok:bool,message:string}
	 */
	public static function refresh( $order ) {
		$provider = (string) $order->get_meta( '_cartiva_courier' );
		$id       = (string) $order->get_meta( '_cartiva_consignment' );
		if ( '' === $id || ! self::configured( $provider ) ) {
			return array(
				'ok'      => false,
				'message' => __( 'This order has no parcel yet.', 'cartiva-commerce' ),
			);
		}
		$path = array(
			'steadfast' => '/status_by_cid/' . rawurlencode( $id ),
			'pathao'    => '/aladdin/api/v1/orders/' . rawurlencode( $id ) . '/info',
			'redx'      => '/parcel/info/' . rawurlencode( $id ),
		);
		$res  = self::call( $provider, 'GET', $path[ $provider ] );
		$raw  = $res['ok'] ? self::parse_status( $provider, $res['data'] ) : '';
		$order->update_meta_data( '_cartiva_courier_checked', time() );
		if ( '' === $raw ) {
			$order->save();
			return array(
				'ok'      => false,
				'message' => '' !== $res['error'] ? $res['error'] : __( 'The courier did not return a status.', 'cartiva-commerce' ),
			);
		}
		self::set_status( $order, $raw, false );
		$order->save();
		return array(
			'ok'      => true,
			'message' => self::status_labels()[ self::normalize( $raw ) ],
		);
	}

	/**
	 * Status from a status response.
	 *
	 * @param string $provider Courier.
	 * @param array  $data     Body.
	 * @return string
	 */
	public static function parse_status( $provider, $data ) {
		if ( 'steadfast' === $provider ) {
			return isset( $data['delivery_status'] ) ? (string) $data['delivery_status'] : '';
		}
		if ( 'pathao' === $provider ) {
			$d = isset( $data['data'] ) && is_array( $data['data'] ) ? $data['data'] : array();
			if ( isset( $d['order_status_slug'] ) && '' !== (string) $d['order_status_slug'] ) {
				return (string) $d['order_status_slug'];
			}
			return isset( $d['order_status'] ) ? (string) $d['order_status'] : '';
		}
		$p = isset( $data['parcel'] ) && is_array( $data['parcel'] ) ? $data['parcel'] : array();
		return isset( $p['status'] ) ? (string) $p['status'] : '';
	}

	/**
	 * Hourly status check.
	 */
	public static function schedule() {
		if ( self::ready() && ! wp_next_scheduled( self::CRON ) ) {
			wp_schedule_event( time() + 600, 'hourly', self::CRON );
		}
	}

	/**
	 * Refresh parcels that are still on the way (up to 40 an hour).
	 */
	public static function sync() {
		$ids = wc_get_orders(
			array(
				'limit'        => 40,
				'return'       => 'ids',
				'orderby'      => 'modified',
				'order'        => 'ASC',
				'date_created' => '>' . ( time() - 45 * DAY_IN_SECONDS ),
				'meta_query'   => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Hourly background job.
					array(
						'key'     => '_cartiva_consignment',
						'compare' => 'EXISTS',
					),
					array(
						'key'     => '_cartiva_courier_state',
						'value'   => array( 'delivered', 'partial', 'returned' ),
						'compare' => 'NOT IN',
					),
				),
			)
		);
		foreach ( (array) $ids as $id ) {
			$order = wc_get_order( $id );
			if ( $order && ! self::finished( $order ) ) {
				self::refresh( $order );
			}
		}
	}

	/**
	 * Steadfast status webhook route.
	 */
	public static function routes() {
		register_rest_route(
			'cartiva-commerce/v1',
			'/steadfast',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'steadfast_webhook' ),
				'permission_callback' => array( __CLASS__, 'webhook_allowed' ),
			)
		);
	}

	/**
	 * Webhook address to paste in the Steadfast panel.
	 *
	 * @return string
	 */
	public static function webhook_url() {
		return rest_url( 'cartiva-commerce/v1/steadfast' );
	}

	/**
	 * Does the webhook carry our token?
	 *
	 * @param WP_REST_Request $request Request.
	 * @return bool
	 */
	public static function webhook_allowed( $request ) {
		$token = (string) cartiva_commerce_setting( 'sf_webhook_token' );
		$auth  = (string) $request->get_header( 'authorization' );
		$sent  = preg_replace( '/^Bearer\s+/i', '', $auth );
		return '' !== $token && '' !== $sent && hash_equals( $token, $sent );
	}

	/**
	 * Steadfast tells us a parcel changed.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array
	 */
	public static function steadfast_webhook( $request ) {
		$body   = $request->get_json_params();
		$body   = is_array( $body ) ? $body : array();
		$id     = isset( $body['consignment_id'] ) ? (string) $body['consignment_id'] : '';
		$status = isset( $body['status'] ) ? sanitize_key( (string) $body['status'] ) : '';
		if ( '' === $id || '' === $status ) {
			return array(
				'status'  => 'error',
				'message' => 'Missing consignment_id or status',
			);
		}
		$orders = wc_get_orders(
			array(
				'limit'      => 1,
				'meta_key'   => '_cartiva_consignment', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- One lookup per webhook.
				'meta_value' => $id, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- Same.
			)
		);
		$order  = $orders ? $orders[0] : null;
		if ( ! $order || 'steadfast' !== $order->get_meta( '_cartiva_courier' ) ) {
			return array(
				'status'  => 'error',
				'message' => 'Unknown consignment',
			);
		}
		self::set_status( $order, $status );
		return array(
			'status'  => 'success',
			'message' => 'Updated',
		);
	}

	/* ------------------------------------------------------------------
	 * Customer delivery history (Steadfast fraud check)
	 * ------------------------------------------------------------------ */

	/**
	 * Parcels this phone received or refused, across all Steadfast merchants.
	 *
	 * @param string $phone Phone.
	 * @param bool   $fresh Skip the saved answer.
	 * @return array|null total, delivered, cancelled, reports, rate (0-100); null when not available.
	 */
	public static function history( $phone, $fresh = false ) {
		$phone = self::phone( $phone );
		if ( ! self::configured( 'steadfast' ) || ! preg_match( '/^01\d{9}$/', $phone ) ) {
			return null;
		}
		$key = 'cartiva_history_' . md5( $phone );
		if ( ! $fresh ) {
			$saved = get_transient( $key );
			if ( is_array( $saved ) ) {
				return $saved;
			}
		}
		$res = self::call( 'steadfast', 'GET', '/fraud_check/' . $phone );
		if ( ! $res['ok'] ) {
			return null;
		}
		$d         = $res['data'];
		$total     = isset( $d['total_parcels'] ) ? (int) $d['total_parcels'] : 0;
		$delivered = isset( $d['total_delivered'] ) ? (int) $d['total_delivered'] : 0;
		$history   = array(
			'total'     => $total,
			'delivered' => $delivered,
			'cancelled' => isset( $d['total_cancelled'] ) ? (int) $d['total_cancelled'] : max( 0, $total - $delivered ),
			'reports'   => isset( $d['total_fraud_reports'] ) ? ( is_array( $d['total_fraud_reports'] ) ? count( $d['total_fraud_reports'] ) : (int) $d['total_fraud_reports'] ) : 0,
			'rate'      => $total > 0 ? (int) round( $delivered * 100 / $total ) : null,
			'checked'   => time(),
		);
		set_transient( $key, $history, 12 * HOUR_IN_SECONDS );
		return $history;
	}

	/**
	 * Verdict text and tone for a history.
	 *
	 * @param array $h History.
	 * @return array tone (good, warn, bad, new), text.
	 */
	public static function history_verdict( $h ) {
		if ( ! $h['total'] ) {
			return array( 'new', __( 'New customer: no parcels at Steadfast yet.', 'cartiva-commerce' ) );
		}
		/* translators: 1: delivered parcels, 2: all parcels, 3: success percent. */
		$text = sprintf( __( 'Received %1$d of %2$d parcels (%3$d%%).', 'cartiva-commerce' ), $h['delivered'], $h['total'], $h['rate'] );
		if ( $h['reports'] > 0 ) {
			/* translators: %d: number of fraud reports. */
			$text .= ' ' . sprintf( _n( '%d fraud report.', '%d fraud reports.', $h['reports'], 'cartiva-commerce' ), $h['reports'] );
		}
		$tone = $h['rate'] >= 80 && ! $h['reports'] ? 'good' : ( $h['rate'] >= 50 ? 'warn' : 'bad' );
		return array( $tone, $text );
	}

	/* ------------------------------------------------------------------
	 * RedX areas
	 * ------------------------------------------------------------------ */

	/**
	 * RedX delivery areas (saved for a week).
	 *
	 * @return array[] id, name, district.
	 */
	public static function redx_areas() {
		if ( ! self::configured( 'redx' ) ) {
			return array();
		}
		$saved = get_transient( 'cartiva_redx_areas' );
		if ( is_array( $saved ) ) {
			return $saved;
		}
		$res   = self::call( 'redx', 'GET', '/areas' );
		$areas = array();
		if ( $res['ok'] && isset( $res['data']['areas'] ) && is_array( $res['data']['areas'] ) ) {
			foreach ( $res['data']['areas'] as $area ) {
				if ( isset( $area['id'], $area['name'] ) ) {
					$areas[] = array(
						'id'       => (int) $area['id'],
						'name'     => (string) $area['name'],
						'district' => isset( $area['district_name'] ) ? (string) $area['district_name'] : '',
					);
				}
			}
		}
		// Keep a failed answer for 10 minutes so pages do not wait on RedX again and again.
		set_transient( 'cartiva_redx_areas', $areas, $areas ? WEEK_IN_SECONDS : 10 * MINUTE_IN_SECONDS );
		return $areas;
	}

	/**
	 * Best RedX area for an order's address.
	 *
	 * @param WC_Order $order Order.
	 * @param array    $areas Areas (default: redx_areas()).
	 * @return array|null
	 */
	public static function guess_redx_area( $order, $areas = null ) {
		$areas = null === $areas ? self::redx_areas() : $areas;
		if ( ! $areas ) {
			return null;
		}
		$to      = self::recipient( $order );
		$address = strtolower( $to['address'] );
		$best    = null;
		foreach ( $areas as $area ) {
			$name = strtolower( trim( preg_replace( '/\s*\(.*\)$/', '', $area['name'] ) ) );
			if ( '' !== $name && strlen( $name ) >= 3 && preg_match( '/\b' . preg_quote( $name, '/' ) . '\b/', $address ) && ( ! $best || strlen( $name ) > $best[0] ) ) {
				$best = array( strlen( $name ), $area );
			}
		}
		if ( $best ) {
			return $best[1];
		}
		foreach ( array( $to['city'], $to['state'] ) as $place ) {
			$place = strtolower( trim( (string) $place ) );
			foreach ( $areas as $area ) {
				if ( '' !== $place && strtolower( $area['name'] ) === $place ) {
					return $area;
				}
			}
		}
		return null;
	}

	/* ------------------------------------------------------------------
	 * Tracking links
	 * ------------------------------------------------------------------ */

	/**
	 * Public tracking page of a parcel.
	 *
	 * @param WC_Order $order Order.
	 * @return string
	 */
	public static function tracking_url( $order ) {
		$provider = (string) $order->get_meta( '_cartiva_courier' );
		$tracking = (string) $order->get_meta( '_cartiva_tracking' );
		if ( '' === $tracking ) {
			return '';
		}
		$urls = array(
			'steadfast' => 'https://steadfast.com.bd/t/' . rawurlencode( $tracking ),
			'pathao'    => add_query_arg(
				array(
					'consignment_id' => rawurlencode( $tracking ),
					'phone'          => rawurlencode( self::phone( $order->get_billing_phone() ) ),
				),
				'https://merchant.pathao.com/tracking'
			),
			'redx'      => add_query_arg( 'trackingId', rawurlencode( $tracking ), 'https://redx.com.bd/track-parcel/' ),
		);
		$url  = isset( $urls[ $provider ] ) ? $urls[ $provider ] : '';
		return (string) apply_filters( 'cartiva_commerce_tracking_url', $url, $order, $provider, $tracking );
	}

	/**
	 * Tracking link on the customer's order page.
	 *
	 * @param WC_Order $order Order.
	 */
	public static function customer_tracking( $order ) {
		if ( ! $order instanceof WC_Order ) {
			return;
		}
		$url = self::tracking_url( $order );
		if ( '' === $url ) {
			return;
		}
		$providers = self::providers();
		$provider  = (string) $order->get_meta( '_cartiva_courier' );
		echo '<section class="cartiva-tracking"><h2>' . esc_html__( 'Delivery', 'cartiva-commerce' ) . '</h2><p>';
		/* translators: 1: courier, 2: parcel number. */
		echo esc_html( sprintf( __( 'Your parcel is with %1$s (parcel %2$s).', 'cartiva-commerce' ), isset( $providers[ $provider ] ) ? $providers[ $provider ] : $provider, $order->get_meta( '_cartiva_tracking' ) ) );
		echo ' <a href="' . esc_url( $url ) . '" target="_blank" rel="noopener">' . esc_html__( 'Track your parcel', 'cartiva-commerce' ) . '</a></p></section>';
	}

	/* ------------------------------------------------------------------
	 * Admin: orders list
	 * ------------------------------------------------------------------ */

	/**
	 * Courier column after Source.
	 *
	 * @param array $columns Columns.
	 * @return array
	 */
	public static function columns( $columns ) {
		$out = array();
		foreach ( $columns as $key => $label ) {
			$out[ $key ] = $label;
			if ( 'cartiva_source' === $key ) {
				$out['cartiva_courier'] = __( 'Courier', 'cartiva-commerce' );
			}
		}
		if ( ! isset( $out['cartiva_courier'] ) ) {
			$out['cartiva_courier'] = __( 'Courier', 'cartiva-commerce' );
		}
		return $out;
	}

	/**
	 * Courier cell.
	 *
	 * @param WC_Order $order Order.
	 * @return string
	 */
	public static function cell( $order ) {
		$provider = (string) $order->get_meta( '_cartiva_courier' );
		if ( '' === (string) $order->get_meta( '_cartiva_consignment' ) ) {
			return '<span class="cartiva-courier-state cartiva-courier-state--none">' . esc_html__( 'Not sent', 'cartiva-commerce' ) . '</span>';
		}
		$state     = (string) $order->get_meta( '_cartiva_courier_state' );
		$state     = '' !== $state ? $state : 'pending';
		$labels    = self::status_labels();
		$providers = self::providers();
		$html      = '<span class="cartiva-courier-state cartiva-courier-state--' . esc_attr( $state ) . '">' . esc_html( isset( $labels[ $state ] ) ? $labels[ $state ] : $state ) . '</span>';
		$html     .= '<br><small>' . esc_html( ( isset( $providers[ $provider ] ) ? $providers[ $provider ] : $provider ) . ' ' . $order->get_meta( '_cartiva_tracking' ) ) . '</small>';
		return $html;
	}

	/**
	 * Print the cell (HPOS).
	 *
	 * @param string   $column Column.
	 * @param WC_Order $order  Order.
	 */
	public static function column_hpos( $column, $order ) {
		if ( 'cartiva_courier' === $column && $order instanceof WC_Order ) {
			echo self::cell( $order ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in cell().
		}
	}

	/**
	 * Print the cell (classic).
	 *
	 * @param string $column  Column.
	 * @param int    $post_id Order ID.
	 */
	public static function column_legacy( $column, $post_id ) {
		if ( 'cartiva_courier' !== $column ) {
			return;
		}
		$order = wc_get_order( $post_id );
		if ( $order ) {
			echo self::cell( $order ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in cell().
		}
	}

	/**
	 * Bulk actions: send to each ready courier, refresh status.
	 *
	 * @param array $actions Actions.
	 * @return array
	 */
	public static function bulk_actions( $actions ) {
		$ready = self::ready();
		foreach ( $ready as $key => $label ) {
			/* translators: %s: courier. */
			$actions[ 'cartiva_send_' . $key ] = sprintf( __( 'Send to %s', 'cartiva-commerce' ), $label );
		}
		if ( $ready ) {
			$actions['cartiva_courier_refresh'] = __( 'Update courier status', 'cartiva-commerce' );
		}
		return $actions;
	}

	/**
	 * Run a bulk action.
	 *
	 * @param string $redirect Redirect URL.
	 * @param string $action   Action.
	 * @param array  $ids      Order IDs.
	 * @return string
	 */
	public static function handle_bulk( $redirect, $action, $ids ) {
		if ( 0 !== strpos( (string) $action, 'cartiva_' ) || ! current_user_can( 'edit_shop_orders' ) ) {
			return $redirect;
		}
		$done   = 0;
		$errors = array();
		foreach ( array_slice( array_map( 'absint', (array) $ids ), 0, 100 ) as $id ) {
			$order = wc_get_order( $id );
			if ( ! $order ) {
				continue;
			}
			if ( 'cartiva_courier_refresh' === $action ) {
				$result = '' !== (string) $order->get_meta( '_cartiva_consignment' ) ? self::refresh( $order ) : array(
					'ok'      => false,
					'message' => '',
				);
			} else {
				$result = self::send( $order, substr( $action, strlen( 'cartiva_send_' ) ) );
			}
			if ( $result['ok'] ) {
				++$done;
			} elseif ( '' !== $result['message'] ) {
				$errors[] = '#' . $order->get_order_number() . ': ' . $result['message'];
			}
		}
		set_transient(
			'cartiva_courier_bulk_' . get_current_user_id(),
			array(
				'action' => $action,
				'done'   => $done,
				'errors' => array_slice( $errors, 0, 20 ),
			),
			MINUTE_IN_SECONDS * 5
		);
		return $redirect;
	}

	/**
	 * Result of the last bulk action.
	 */
	public static function bulk_notice() {
		$key    = 'cartiva_courier_bulk_' . get_current_user_id();
		$result = get_transient( $key );
		if ( ! is_array( $result ) ) {
			return;
		}
		delete_transient( $key );
		$refresh = 'cartiva_courier_refresh' === $result['action'];
		echo '<div class="notice notice-' . ( $result['errors'] ? 'warning' : 'success' ) . ' is-dismissible"><p>';
		echo esc_html(
			$refresh
				/* translators: %d: number of orders. */
				? sprintf( _n( 'Courier status updated for %d order.', 'Courier status updated for %d orders.', $result['done'], 'cartiva-commerce' ), $result['done'] )
				/* translators: %d: number of orders. */
				: sprintf( _n( '%d order sent to the courier.', '%d orders sent to the courier.', $result['done'], 'cartiva-commerce' ), $result['done'] )
		);
		echo '</p>';
		if ( $result['errors'] ) {
			echo '<p><strong>' . esc_html__( 'Not sent:', 'cartiva-commerce' ) . '</strong></p><ul>';
			foreach ( $result['errors'] as $error ) {
				echo '<li>' . esc_html( $error ) . '</li>';
			}
			echo '</ul>';
		}
		echo '</div>';
	}

	/* ------------------------------------------------------------------
	 * Admin: order screen
	 * ------------------------------------------------------------------ */

	/**
	 * Courier box on the order screen.
	 */
	public static function meta_box() {
		$screens = array( 'shop_order' );
		if ( function_exists( 'wc_get_page_screen_id' ) ) {
			$screens[] = wc_get_page_screen_id( 'shop-order' );
		}
		foreach ( array_unique( $screens ) as $screen ) {
			add_meta_box( 'cartiva-courier', __( 'Courier', 'cartiva-commerce' ), array( __CLASS__, 'render_box' ), $screen, 'side', 'high' );
		}
	}

	/**
	 * Courier box.
	 *
	 * @param WP_Post|WC_Order $post_or_order Order or its post.
	 */
	public static function render_box( $post_or_order ) {
		$order = $post_or_order instanceof WC_Order ? $post_or_order : wc_get_order( $post_or_order->ID );
		if ( ! $order ) {
			return;
		}
		$ready = self::ready();
		echo '<div class="cartiva-courier-box" data-order="' . esc_attr( (string) $order->get_id() ) . '">';
		self::render_history( $order );
		if ( '' !== (string) $order->get_meta( '_cartiva_consignment' ) ) {
			$url = self::tracking_url( $order );
			echo self::cell( $order ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in cell().
			$checked = (int) $order->get_meta( '_cartiva_courier_checked' );
			if ( $checked ) {
				/* translators: %s: time ago, for example 5 mins. */
				echo '<p class="description">' . esc_html( sprintf( __( 'Checked %s ago', 'cartiva-commerce' ), human_time_diff( $checked ) ) ) . '</p>';
			}
			echo '<p>';
			echo '<button type="button" class="button" data-cartiva-courier="refresh">' . esc_html__( 'Update status', 'cartiva-commerce' ) . '</button> ';
			if ( '' !== $url ) {
				echo '<a class="button-link" href="' . esc_url( $url ) . '" target="_blank" rel="noopener">' . esc_html__( 'Tracking page', 'cartiva-commerce' ) . '</a>';
			}
			echo '</p><p class="cartiva-courier-msg" role="status"></p></div>';
			return;
		}
		if ( ! $ready ) {
			echo '<p>' . esc_html__( 'Book Steadfast, Pathao or RedX parcels from here.', 'cartiva-commerce' ) . '</p>';
			echo '<p><a class="button" href="' . esc_url( Cartiva_Commerce_Settings::url( 'courier' ) ) . '">' . esc_html__( 'Connect a courier', 'cartiva-commerce' ) . '</a></p></div>';
			return;
		}
		$d       = self::defaults( $order );
		$default = self::default_provider();
		echo '<p><label for="cartiva-courier-provider">' . esc_html__( 'Courier', 'cartiva-commerce' ) . '</label><br><select id="cartiva-courier-provider" class="cartiva-courier-provider" style="width:100%">';
		foreach ( $ready as $key => $label ) {
			echo '<option value="' . esc_attr( $key ) . '"' . selected( $default, $key, false ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select></p>';
		echo '<p><label for="cartiva-courier-cod">' . esc_html__( 'Cash to collect', 'cartiva-commerce' ) . '</label><br><input type="number" min="0" step="1" id="cartiva-courier-cod" class="cartiva-courier-cod" value="' . esc_attr( (string) round( $d['cod'] ) ) . '" style="width:100%" /></p>';
		echo '<p><label for="cartiva-courier-weight">' . esc_html__( 'Weight (kg)', 'cartiva-commerce' ) . '</label><br><input type="number" min="0.1" step="0.1" id="cartiva-courier-weight" class="cartiva-courier-weight" value="' . esc_attr( (string) $d['weight'] ) . '" style="width:100%" /></p>';
		if ( isset( $ready['redx'] ) ) {
			$areas = self::redx_areas();
			echo '<p class="cartiva-courier-only" data-provider="redx"><label for="cartiva-courier-area">' . esc_html__( 'RedX delivery area', 'cartiva-commerce' ) . '</label><br><select id="cartiva-courier-area" class="cartiva-courier-area wc-enhanced-select" style="width:100%"><option value="">' . esc_html__( 'Choose an area', 'cartiva-commerce' ) . '</option>';
			foreach ( $areas as $area ) {
				echo '<option value="' . esc_attr( (string) $area['id'] ) . '" data-name="' . esc_attr( $area['name'] ) . '"' . selected( $d['area_id'], $area['id'], false ) . '>' . esc_html( $area['name'] . ( '' !== $area['district'] ? ' (' . $area['district'] . ')' : '' ) ) . '</option>';
			}
			echo '</select></p>';
		}
		echo '<p><label for="cartiva-courier-note">' . esc_html__( 'Note for the rider', 'cartiva-commerce' ) . '</label><br><input type="text" id="cartiva-courier-note" class="cartiva-courier-note" value="' . esc_attr( $d['note'] ) . '" style="width:100%" /></p>';
		echo '<p><button type="button" class="button button-primary" data-cartiva-courier="send">' . esc_html__( 'Send to courier', 'cartiva-commerce' ) . '</button></p>';
		echo '<p class="cartiva-courier-msg" role="status"></p></div>';
	}

	/**
	 * Customer's delivery history in the box.
	 *
	 * @param WC_Order $order Order.
	 */
	private static function render_history( $order ) {
		if ( ! self::configured( 'steadfast' ) ) {
			return;
		}
		$phone = self::phone( $order->get_billing_phone() );
		$saved = get_transient( 'cartiva_history_' . md5( $phone ) );
		echo '<div class="cartiva-history">';
		if ( is_array( $saved ) ) {
			$verdict = self::history_verdict( $saved );
			echo '<p class="cartiva-history__verdict cartiva-history__verdict--' . esc_attr( $verdict[0] ) . '">' . esc_html( $verdict[1] ) . '</p>';
		} else {
			echo '<p><button type="button" class="button" data-cartiva-courier="history">' . esc_html__( 'Check customer\'s delivery history', 'cartiva-commerce' ) . '</button></p>';
		}
		echo '</div>';
	}

	/**
	 * Script for the order screen and settings.
	 *
	 * @param string $hook Admin page.
	 */
	public static function assets( $hook ) {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		$order  = $screen && in_array( $screen->id, array( 'shop_order', 'woocommerce_page_wc-orders' ), true );
		$ours   = false !== strpos( (string) $hook, Cartiva_Commerce_Settings::PAGE );
		if ( ! $order && ! $ours ) {
			return;
		}
		wp_enqueue_script( 'cartiva-commerce-courier', CARTIVA_COMMERCE_URL . 'assets/js/admin-courier.js', array( 'jquery' ), CARTIVA_COMMERCE_VERSION, true );
		wp_localize_script(
			'cartiva-commerce-courier',
			'cartivaCourier',
			array(
				'ajax'    => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'cartiva_courier' ),
				'working' => __( 'Working...', 'cartiva-commerce' ),
				'failed'  => __( 'Something went wrong. Please try again.', 'cartiva-commerce' ),
			)
		);
		if ( $order ) {
			wp_enqueue_style( 'cartiva-commerce-admin', CARTIVA_COMMERCE_URL . 'assets/css/admin.css', array(), CARTIVA_COMMERCE_VERSION );
		}
	}

	/**
	 * Order from an AJAX request, after the checks.
	 *
	 * @return WC_Order
	 */
	private static function ajax_order() {
		check_ajax_referer( 'cartiva_courier', 'nonce' );
		$id    = isset( $_POST['order'] ) ? absint( wp_unslash( $_POST['order'] ) ) : 0;
		$order = $id ? wc_get_order( $id ) : null;
		if ( ! $order || ! current_user_can( 'edit_shop_orders' ) ) {
			wp_send_json_error( array( 'message' => __( 'You are not allowed to do this.', 'cartiva-commerce' ) ), 403 );
		}
		return $order;
	}

	/**
	 * Send from the order screen.
	 */
	public static function ajax_send() {
		$order = self::ajax_order();
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- Checked in ajax_order().
		$args = array(
			'cod'       => isset( $_POST['cod'] ) ? (float) wp_unslash( $_POST['cod'] ) : 0,
			'weight'    => isset( $_POST['weight'] ) ? (float) wp_unslash( $_POST['weight'] ) : 0.5,
			'note'      => isset( $_POST['note'] ) ? sanitize_text_field( wp_unslash( $_POST['note'] ) ) : '',
			'area_id'   => isset( $_POST['area_id'] ) ? absint( wp_unslash( $_POST['area_id'] ) ) : 0,
			'area_name' => isset( $_POST['area_name'] ) ? sanitize_text_field( wp_unslash( $_POST['area_name'] ) ) : '',
		);
		$provider = isset( $_POST['provider'] ) ? sanitize_key( wp_unslash( $_POST['provider'] ) ) : '';
		// phpcs:enable
		$result = self::send( $order, $provider, $args );
		$result['ok'] ? wp_send_json_success( $result ) : wp_send_json_error( $result );
	}

	/**
	 * Refresh from the order screen.
	 */
	public static function ajax_refresh() {
		$result = self::refresh( self::ajax_order() );
		$result['ok'] ? wp_send_json_success( $result ) : wp_send_json_error( $result );
	}

	/**
	 * Delivery history from the order screen.
	 */
	public static function ajax_history() {
		$order   = self::ajax_order();
		$history = self::history( $order->get_billing_phone(), true );
		if ( null === $history ) {
			wp_send_json_error( array( 'message' => __( 'Could not get the delivery history. Check the Steadfast API details and the phone number.', 'cartiva-commerce' ) ) );
		}
		$verdict = self::history_verdict( $history );
		wp_send_json_success(
			array(
				'tone'    => $verdict[0],
				'message' => $verdict[1],
			)
		);
	}

	/**
	 * Test the saved API details of a courier.
	 *
	 * @param string $provider Courier.
	 * @return array{ok:bool,message:string}
	 */
	public static function test( $provider ) {
		if ( ! self::configured( $provider ) ) {
			return array(
				'ok'      => false,
				'message' => __( 'Fill in and save the API details first.', 'cartiva-commerce' ),
			);
		}
		if ( 'steadfast' === $provider ) {
			$res = self::call( 'steadfast', 'GET', '/get_balance' );
			return array(
				'ok'      => $res['ok'],
				/* translators: %s: account balance. */
				'message' => $res['ok'] ? sprintf( __( 'Connected. Balance: %s', 'cartiva-commerce' ), isset( $res['data']['current_balance'] ) ? $res['data']['current_balance'] : '0' ) : $res['error'],
			);
		}
		if ( 'pathao' === $provider ) {
			if ( '' === self::pathao_token( true ) ) {
				return array(
					'ok'      => false,
					'message' => __( 'Pathao did not accept the API details. Check the client ID, secret, email and password.', 'cartiva-commerce' ),
				);
			}
			$res    = self::call( 'pathao', 'GET', '/aladdin/api/v1/stores' );
			$stores = array();
			$list   = isset( $res['data']['data']['data'] ) && is_array( $res['data']['data']['data'] ) ? $res['data']['data']['data'] : array();
			foreach ( $list as $store ) {
				if ( isset( $store['store_id'] ) ) {
					$stores[] = $store['store_id'] . ' = ' . ( isset( $store['store_name'] ) ? $store['store_name'] : '' );
				}
			}
			return array(
				'ok'      => true,
				/* translators: %s: list of stores. */
				'message' => __( 'Connected.', 'cartiva-commerce' ) . ( $stores ? ' ' . sprintf( __( 'Your store IDs: %s', 'cartiva-commerce' ), implode( ', ', $stores ) ) : '' ),
			);
		}
		delete_transient( 'cartiva_redx_areas' );
		$areas = self::redx_areas();
		return array(
			'ok'      => (bool) $areas,
			/* translators: %d: number of delivery areas. */
			'message' => $areas ? sprintf( __( 'Connected. %d delivery areas loaded.', 'cartiva-commerce' ), count( $areas ) ) : __( 'RedX did not accept the API token.', 'cartiva-commerce' ),
		);
	}

	/**
	 * Test button in the settings.
	 */
	public static function ajax_test() {
		check_ajax_referer( 'cartiva_courier', 'nonce' );
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'You are not allowed to do this.', 'cartiva-commerce' ) ), 403 );
		}
		$provider = isset( $_POST['provider'] ) ? sanitize_key( wp_unslash( $_POST['provider'] ) ) : '';
		$result   = self::test( $provider );
		$result['ok'] ? wp_send_json_success( $result ) : wp_send_json_error( $result );
	}
}
