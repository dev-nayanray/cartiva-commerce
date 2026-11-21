<?php
/**
 * Abandoned checkout recovery.
 *
 * While a shopper fills in an order form, their contact details and cart are
 * saved (with a notice on the form). If no order follows, the checkout counts
 * as abandoned: the store sees it with call and WhatsApp links, and up to
 * three reminder emails go out with a link that restores the cart, optionally
 * with a one-time coupon. An order from the same shopper marks it recovered.
 * Entries are deleted after the keep period (30 days by default).
 *
 * @package Cartiva_Commerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Recovery.
 */
class Cartiva_Commerce_Recovery {

	const CRON = 'cartiva_commerce_recovery_cron';

	/**
	 * Hook up.
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'register' ), 22 );
		add_filter( 'cron_schedules', array( __CLASS__, 'schedules' ) ); // phpcs:ignore WordPress.WP.CronInterval.CronSchedulesInterval -- 15 minutes keeps reminder timing close to the settings.
		add_action( 'init', array( __CLASS__, 'schedule' ), 30 );
		add_action( self::CRON, array( __CLASS__, 'run' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'assets' ), 20 );
		add_action( 'wc_ajax_cartiva_commerce_capture', array( __CLASS__, 'ajax_capture' ) );
		add_action( 'woocommerce_checkout_after_customer_details', array( __CLASS__, 'notice' ), 5 );
		add_action( 'woocommerce_checkout_order_processed', array( __CLASS__, 'order_placed' ), 20, 3 );
		add_action( 'wp_loaded', array( __CLASS__, 'restore' ), 30 );
		add_filter( 'wp_privacy_personal_data_exporters', array( __CLASS__, 'register_exporter' ) );
		add_filter( 'wp_privacy_personal_data_erasers', array( __CLASS__, 'register_eraser' ) );
	}

	/**
	 * A stored list (details, cart, sent emails), or an empty array.
	 *
	 * @param int    $id  Entry ID.
	 * @param string $key Meta key.
	 * @return array
	 */
	public static function meta_array( $id, $key ) {
		$value = get_post_meta( (int) $id, $key, true );
		return is_array( $value ) ? $value : array();
	}

	/**
	 * Default reminder emails.
	 *
	 * @return array[] Each: enabled, hours (after the checkout was abandoned), subject, body, coupon.
	 */
	public static function default_emails() {
		return array(
			array(
				'enabled' => true,
				'hours'   => 1,
				'subject' => __( 'You left something in your cart', 'cartiva-commerce' ),
				'body'    => __( "Hi {name},\n\nYou were about to order {products}. Your order is not finished yet, and we saved it for you.\n\n{button}\n\nIf you have a question, just reply to this email.\n\n{site}", 'cartiva-commerce' ),
				'coupon'  => false,
			),
			array(
				'enabled' => true,
				'hours'   => 24,
				'subject' => __( 'Your order is still waiting', 'cartiva-commerce' ),
				'body'    => __( "Hi {name},\n\nYour cart with {products} ({total}) is still saved. Finish your order in one click:\n\n{button}\n\n{site}", 'cartiva-commerce' ),
				'coupon'  => false,
			),
			array(
				'enabled' => false,
				'hours'   => 72,
				'subject' => __( 'A little gift to finish your order', 'cartiva-commerce' ),
				'body'    => __( "Hi {name},\n\nHere is {discount} off your order with the code {coupon}. It is valid until {expires}.\n\n{button}\n\n{site}", 'cartiva-commerce' ),
				'coupon'  => true,
			),
		);
	}

	/**
	 * Reminder emails from settings, with defaults.
	 *
	 * @return array[]
	 */
	public static function emails() {
		$saved = (array) cartiva_commerce_setting( 'emails' );
		$out   = array();
		foreach ( self::default_emails() as $i => $default ) {
			$out[ $i ] = isset( $saved[ $i ] ) && is_array( $saved[ $i ] ) ? wp_parse_args( $saved[ $i ], $default ) : $default;
		}
		return $out;
	}

	/**
	 * Default WhatsApp message.
	 *
	 * @return string
	 */
	public static function default_whatsapp() {
		return __( 'Hello {name}, this is {site}. We saw your order for {products} was not finished. Can we help you complete it? {link}', 'cartiva-commerce' );
	}

	/**
	 * Register the post type (admin only).
	 */
	public static function register() {
		register_post_type(
			CARTIVA_COMMERCE_RECOVERY,
			array(
				'labels'       => array(
					'name'          => __( 'Abandoned checkouts', 'cartiva-commerce' ),
					'singular_name' => __( 'Abandoned checkout', 'cartiva-commerce' ),
					'menu_name'     => __( 'Abandoned checkouts', 'cartiva-commerce' ),
					'all_items'     => __( 'Abandoned checkouts', 'cartiva-commerce' ),
					'edit_item'     => __( 'Abandoned checkout', 'cartiva-commerce' ),
					'search_items'  => __( 'Search abandoned checkouts', 'cartiva-commerce' ),
					'not_found'     => __( 'No abandoned checkouts yet.', 'cartiva-commerce' ),
				),
				'public'       => false,
				'show_ui'      => true,
				'show_in_menu' => 'edit.php?post_type=' . CARTIVA_COMMERCE_FUNNEL,
				'show_in_rest' => false,
				'supports'     => array( 'title' ),
				'map_meta_cap' => false,
				'capabilities' => array(
					'edit_post'          => 'manage_woocommerce',
					'read_post'          => 'manage_woocommerce',
					'delete_post'        => 'manage_woocommerce',
					'edit_posts'         => 'manage_woocommerce',
					'edit_others_posts'  => 'manage_woocommerce',
					'delete_posts'       => 'manage_woocommerce',
					'publish_posts'      => 'manage_woocommerce',
					'read_private_posts' => 'manage_woocommerce',
					'create_posts'       => 'do_not_allow',
				),
			)
		);
	}

	/**
	 * Every 15 minutes.
	 *
	 * @param array $schedules Schedules.
	 * @return array
	 */
	public static function schedules( $schedules ) {
		$schedules['cartiva_fifteen_minutes'] = array(
			'interval' => 15 * MINUTE_IN_SECONDS,
			'display'  => __( 'Every 15 minutes', 'cartiva-commerce' ),
		);
		return $schedules;
	}

	/**
	 * Keep the background job scheduled.
	 */
	public static function schedule() {
		if ( ! wp_next_scheduled( self::CRON ) ) {
			wp_schedule_event( time() + 5 * MINUTE_IN_SECONDS, 'cartiva_fifteen_minutes', self::CRON );
		}
	}

	/**
	 * Is saving switched on for the current form?
	 *
	 * @return bool
	 */
	public static function active_here() {
		if ( ! cartiva_commerce_setting( 'recovery' ) ) {
			return false;
		}
		if ( cartiva_commerce_current_landing_id() ) {
			return true;
		}
		return (bool) cartiva_commerce_setting( 'recovery_main' ) && function_exists( 'is_checkout' ) && is_checkout() && ! is_order_received_page();
	}

	/**
	 * Script that saves details as they are typed.
	 */
	public static function assets() {
		if ( is_admin() || ! self::active_here() ) {
			return;
		}
		wp_enqueue_script( 'cartiva-commerce-capture', CARTIVA_COMMERCE_URL . 'assets/js/capture.js', array( 'jquery' ), CARTIVA_COMMERCE_VERSION, array( 'in_footer' => true ) );
		wp_localize_script(
			'cartiva-commerce-capture',
			'cartivaCapture',
			array(
				'url'   => WC_AJAX::get_endpoint( 'cartiva_commerce_capture' ),
				'nonce' => wp_create_nonce( 'cartiva_commerce_capture' ),
			)
		);
	}

	/**
	 * The notice under the details on forms that save them.
	 */
	public static function notice() {
		if ( ! self::active_here() ) {
			return;
		}
		$text = (string) cartiva_commerce_setting( 'recovery_note' );
		$text = '' !== trim( $text ) ? $text : __( 'We save the details you enter here so we can help you if your order is not finished.', 'cartiva-commerce' );
		echo '<p class="cartiva-capture-note">' . esc_html( $text ) . '</p>';
	}

	/**
	 * Clean contact details from a request.
	 *
	 * @param array $raw Raw values.
	 * @return array
	 */
	public static function clean_details( $raw ) {
		$fields  = array( 'billing_full_name', 'billing_first_name', 'billing_last_name', 'billing_phone', 'billing_email', 'billing_address_1', 'billing_address_2', 'billing_city', 'billing_state', 'billing_postcode', 'billing_country' );
		$details = array();
		foreach ( $fields as $field ) {
			if ( isset( $raw[ $field ] ) && is_scalar( $raw[ $field ] ) ) {
				$value = 'billing_email' === $field ? sanitize_email( (string) $raw[ $field ] ) : sanitize_text_field( (string) $raw[ $field ] );
				if ( '' !== $value ) {
					$details[ $field ] = mb_substr( $value, 0, 200 );
				}
			}
		}
		if ( empty( $details['billing_full_name'] ) ) {
			$name = trim( ( isset( $details['billing_first_name'] ) ? $details['billing_first_name'] : '' ) . ' ' . ( isset( $details['billing_last_name'] ) ? $details['billing_last_name'] : '' ) );
			if ( '' !== $name ) {
				$details['billing_full_name'] = $name;
			}
		}
		return $details;
	}

	/**
	 * Can this shopper be contacted?
	 *
	 * @param array $details Details.
	 * @return bool
	 */
	public static function contactable( $details ) {
		$phone = isset( $details['billing_phone'] ) ? preg_replace( '/\D/', '', $details['billing_phone'] ) : '';
		return strlen( $phone ) >= 10 || ( ! empty( $details['billing_email'] ) && is_email( $details['billing_email'] ) );
	}

	/**
	 * The cart, as data that can be put back later.
	 *
	 * @return array{items:array,total:float,currency:string,names:string}
	 */
	public static function cart_snapshot() {
		$items = array();
		$names = array();
		foreach ( WC()->cart ? WC()->cart->get_cart() : array() as $line ) {
			$item = array(
				'product_id'   => (int) $line['product_id'],
				'variation_id' => (int) $line['variation_id'],
				'quantity'     => (int) $line['quantity'],
				'variation'    => isset( $line['variation'] ) && is_array( $line['variation'] ) ? $line['variation'] : array(),
			);
			foreach ( array( 'cartiva_offer', 'cartiva_bump' ) as $ref ) {
				if ( ! empty( $line[ $ref ] ) ) {
					$item[ $ref ] = array(
						'landing' => (int) $line[ $ref ]['landing'],
						'index'   => (int) $line[ $ref ]['index'],
					);
				}
			}
			$items[] = $item;
			if ( isset( $line['data'] ) && $line['data'] instanceof WC_Product ) {
				$names[] = wp_strip_all_tags( $line['data']->get_name() ) . ( $line['quantity'] > 1 ? ' × ' . (int) $line['quantity'] : '' );
			}
		}
		return array(
			'items'    => $items,
			'total'    => WC()->cart ? (float) WC()->cart->get_total( 'edit' ) : 0.0,
			'currency' => get_woocommerce_currency(),
			'names'    => implode( ', ', $names ),
		);
	}

	/**
	 * Open entry of a shopper session, or 0.
	 *
	 * @param string $session Session key.
	 * @return int
	 */
	public static function entry_for_session( $session ) {
		$ids = get_posts(
			array(
				'post_type'      => CARTIVA_COMMERCE_RECOVERY,
				'post_status'    => 'private',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- One small lookup per saved form.
					array(
						'key'   => '_session',
						'value' => (string) $session,
					),
					array(
						'key'     => '_status',
						'value'   => array( 'open', 'abandoned' ),
						'compare' => 'IN',
					),
				),
			)
		);
		return $ids ? (int) $ids[0] : 0;
	}

	/**
	 * Save details typed into an order form (wc-ajax endpoint).
	 */
	public static function ajax_capture() {
		check_ajax_referer( 'cartiva_commerce_capture', 'nonce' );
		if ( ! cartiva_commerce_setting( 'recovery' ) || ! WC()->cart || WC()->cart->is_empty() || ! WC()->session ) {
			wp_send_json_success( array( 'saved' => false ) );
		}
		$raw        = isset( $_POST['fields'] ) && is_array( $_POST['fields'] ) ? wp_unslash( $_POST['fields'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Cleaned in clean_details().
		$details    = self::clean_details( $raw );
		$landing_id = isset( $_POST['landing'] ) ? absint( wp_unslash( $_POST['landing'] ) ) : 0;
		$landing_id = cartiva_commerce_is_landing( $landing_id ) ? $landing_id : 0;
		if ( ! $landing_id && ! cartiva_commerce_setting( 'recovery_main' ) ) {
			wp_send_json_success( array( 'saved' => false ) );
		}
		if ( ! self::contactable( $details ) ) {
			wp_send_json_success( array( 'saved' => false ) );
		}
		$session = (string) WC()->session->get_customer_id();
		$id      = self::entry_for_session( $session );
		$title   = ! empty( $details['billing_full_name'] ) ? $details['billing_full_name'] : ( ! empty( $details['billing_phone'] ) ? $details['billing_phone'] : $details['billing_email'] );
		if ( ! $id ) {
			$id = wp_insert_post(
				array(
					'post_type'   => CARTIVA_COMMERCE_RECOVERY,
					'post_status' => 'private',
					'post_title'  => $title,
				)
			);
			if ( ! $id || is_wp_error( $id ) ) {
				wp_send_json_success( array( 'saved' => false ) );
			}
			update_post_meta( $id, '_session', $session );
			update_post_meta( $id, '_status', 'open' );
			update_post_meta( $id, '_created', time() );
			update_post_meta( $id, '_token', wp_generate_password( 32, false, false ) );
		} else {
			wp_update_post(
				array(
					'ID'         => $id,
					'post_title' => $title,
				)
			);
			if ( 'abandoned' === get_post_meta( $id, '_status', true ) ) {
				// Back on the form: the clock starts again, reminders continue later.
				update_post_meta( $id, '_status', 'open' );
			}
		}
		$cart = self::cart_snapshot();
		update_post_meta( $id, '_details', $details );
		update_post_meta( $id, '_cart', $cart );
		update_post_meta( $id, '_landing', $landing_id );
		update_post_meta( $id, '_updated', time() );
		wp_send_json_success( array( 'saved' => true ) );
	}

	/**
	 * Mark entries when an order is placed: by the same session, or (for
	 * abandoned ones) by the same phone or email.
	 *
	 * @param int      $order_id    Order ID.
	 * @param array    $posted_data Posted data.
	 * @param WC_Order $order       Order.
	 */
	public static function order_placed( $order_id, $posted_data, $order ) {
		if ( ! $order instanceof WC_Order ) {
			$order = wc_get_order( $order_id );
		}
		if ( ! $order ) {
			return;
		}
		$ids = array();
		if ( WC()->session ) {
			$session_entry = self::entry_for_session( (string) WC()->session->get_customer_id() );
			if ( $session_entry ) {
				$ids[] = $session_entry;
			}
			$from_link = (int) WC()->session->get( 'cartiva_recovery' );
			if ( $from_link ) {
				$ids[] = $from_link;
				WC()->session->set( 'cartiva_recovery', null );
			}
		}
		foreach ( array( '_phone_key' => self::phone_key( $order->get_billing_phone() ), '_email_key' => strtolower( (string) $order->get_billing_email() ) ) as $key => $value ) {
			if ( '' === $value ) {
				continue;
			}
			$ids = array_merge(
				$ids,
				get_posts(
					array(
						'post_type'      => CARTIVA_COMMERCE_RECOVERY,
						'post_status'    => 'private',
						'posts_per_page' => 5,
						'fields'         => 'ids',
						'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Once per order.
							array(
								'key'   => $key,
								'value' => $value,
							),
							array(
								'key'   => '_status',
								'value' => 'abandoned',
							),
						),
					)
				)
			);
		}
		foreach ( array_unique( array_map( 'intval', $ids ) ) as $id ) {
			$status = (string) get_post_meta( $id, '_status', true );
			if ( ! in_array( $status, array( 'open', 'abandoned' ), true ) ) {
				continue;
			}
			// Recovered: it had been abandoned, or the buyer came back through a reminder link.
			$recovered = 'abandoned' === $status || get_post_meta( $id, '_clicked', true ) || (int) get_post_meta( $id, '_sent_count', true ) > 0;
			update_post_meta( $id, '_status', $recovered ? 'recovered' : 'converted' );
			update_post_meta( $id, '_order', (int) $order->get_id() );
			update_post_meta( $id, '_order_total', (float) $order->get_total() );
			update_post_meta( $id, '_closed', time() );
			if ( $recovered ) {
				/* translators: %d: abandoned checkout ID. */
				$order->add_order_note( sprintf( __( 'Recovered abandoned checkout #%d.', 'cartiva-commerce' ), $id ) );
			}
		}
	}

	/**
	 * Phone digits used to match a buyer (last 10 digits).
	 *
	 * @param string $phone Phone.
	 * @return string
	 */
	public static function phone_key( $phone ) {
		$digits = preg_replace( '/\D/', '', (string) $phone );
		return strlen( $digits ) >= 10 ? substr( $digits, -10 ) : '';
	}

	/**
	 * Recovery link of an entry.
	 *
	 * @param int  $id   Entry ID.
	 * @param bool $stop Unsubscribe link instead.
	 * @return string
	 */
	public static function link( $id, $stop = false ) {
		$args = array( 'cartiva_recover' => (string) get_post_meta( $id, '_token', true ) );
		if ( $stop ) {
			$args['stop'] = '1';
		}
		return add_query_arg( $args, home_url( '/' ) );
	}

	/**
	 * Entry of a token, or 0.
	 *
	 * @param string $token Token.
	 * @return int
	 */
	public static function entry_for_token( $token ) {
		if ( strlen( $token ) !== 32 ) {
			return 0;
		}
		$ids = get_posts(
			array(
				'post_type'      => CARTIVA_COMMERCE_RECOVERY,
				'post_status'    => 'private',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'meta_key'       => '_token', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Only when a reminder link is opened.
				'meta_value'     => $token, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- Same.
			)
		);
		return ( $ids && hash_equals( (string) get_post_meta( (int) $ids[0], '_token', true ), $token ) ) ? (int) $ids[0] : 0;
	}

	/**
	 * Open a reminder link: put the cart back and go to the form, or stop
	 * the reminders.
	 */
	public static function restore() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- The 32-character token is the proof.
		if ( is_admin() || ! isset( $_GET['cartiva_recover'] ) ) {
			return;
		}
		$id   = self::entry_for_token( sanitize_key( wp_unslash( $_GET['cartiva_recover'] ) ) );
		$stop = ! empty( $_GET['stop'] );
		// phpcs:enable
		if ( ! $id ) {
			wp_safe_redirect( home_url( '/' ) );
			exit;
		}
		if ( $stop ) {
			update_post_meta( $id, '_stop', 1 );
			if ( function_exists( 'wc_add_notice' ) && WC()->session ) {
				wc_add_notice( __( 'You will not get more reminders about this order.', 'cartiva-commerce' ) );
			}
			wp_safe_redirect( home_url( '/' ) );
			exit;
		}
		$status = (string) get_post_meta( $id, '_status', true );
		if ( ! WC()->cart || ! in_array( $status, array( 'open', 'abandoned' ), true ) ) {
			wp_safe_redirect( wc_get_page_permalink( 'shop' ) );
			exit;
		}
		update_post_meta( $id, '_clicked', time() );
		if ( WC()->session && ! WC()->session->has_session() ) {
			WC()->session->set_customer_session_cookie( true );
		}
		$cart = get_post_meta( $id, '_cart', true );
		WC()->cart->empty_cart();
		foreach ( is_array( $cart ) && ! empty( $cart['items'] ) ? $cart['items'] : array() as $item ) {
			$extra = array();
			foreach ( array( 'cartiva_offer', 'cartiva_bump' ) as $ref ) {
				if ( ! empty( $item[ $ref ] ) ) {
					$extra[ $ref ] = $item[ $ref ];
				}
			}
			WC()->cart->add_to_cart( (int) $item['product_id'], max( 1, (int) $item['quantity'] ), (int) $item['variation_id'], (array) $item['variation'], $extra );
		}
		$details = get_post_meta( $id, '_details', true );
		if ( is_array( $details ) && WC()->customer ) {
			foreach ( $details as $field => $value ) {
				$setter = 'set_' . $field;
				if ( 'billing_full_name' !== $field && is_callable( array( WC()->customer, $setter ) ) ) {
					WC()->customer->$setter( $value );
				}
			}
			if ( ! empty( $details['billing_full_name'] ) && empty( $details['billing_first_name'] ) ) {
				$parts = preg_split( '/\s+/', trim( $details['billing_full_name'] ) );
				$last  = count( $parts ) > 1 ? array_pop( $parts ) : '';
				WC()->customer->set_billing_first_name( implode( ' ', $parts ) );
				WC()->customer->set_billing_last_name( $last );
			}
			WC()->customer->save();
		}
		$coupon = (string) get_post_meta( $id, '_coupon', true );
		if ( '' !== $coupon && ! WC()->cart->has_discount( $coupon ) ) {
			WC()->cart->apply_coupon( $coupon );
		}
		WC()->session->set( 'cartiva_recovery', $id );
		update_post_meta( $id, '_session', (string) WC()->session->get_customer_id() );
		$notices = wc_get_notices();
		unset( $notices['success'] );
		wc_set_notices( $notices );
		$landing = (int) get_post_meta( $id, '_landing', true );
		$target  = $landing && 'publish' === get_post_status( $landing ) ? get_permalink( $landing ) . '#order' : wc_get_checkout_url();
		wp_safe_redirect( $target );
		exit;
	}

	/**
	 * Background job: mark abandoned checkouts, send due reminders, delete old entries.
	 *
	 * @param int $now Current time (for tests).
	 */
	public static function run( $now = 0 ) {
		$now   = $now ? (int) $now : time();
		$after = max( 10, (int) cartiva_commerce_setting( 'recovery_after' ) ) * MINUTE_IN_SECONDS;
		$keep  = max( 1, (int) cartiva_commerce_setting( 'recovery_keep' ) ) * DAY_IN_SECONDS;

		$ids = get_posts(
			array(
				'post_type'      => CARTIVA_COMMERCE_RECOVERY,
				'post_status'    => 'private',
				'posts_per_page' => 200,
				'fields'         => 'ids',
				'orderby'        => 'ID',
				'order'          => 'ASC',
				'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Background job.
					array(
						'key'     => '_status',
						'value'   => array( 'open', 'abandoned' ),
						'compare' => 'IN',
					),
				),
			)
		);
		$sent = 0;
		foreach ( $ids as $id ) {
			$updated = (int) get_post_meta( $id, '_updated', true );
			$status  = (string) get_post_meta( $id, '_status', true );
			if ( 'open' === $status && $now - $updated >= $after ) {
				update_post_meta( $id, '_status', 'abandoned' );
				update_post_meta( $id, '_abandoned', $now );
				$status = 'abandoned';
				$details = get_post_meta( $id, '_details', true );
				// Keys to match a later order from the same buyer.
				if ( is_array( $details ) ) {
					update_post_meta( $id, '_phone_key', self::phone_key( isset( $details['billing_phone'] ) ? $details['billing_phone'] : '' ) );
					update_post_meta( $id, '_email_key', strtolower( isset( $details['billing_email'] ) ? (string) $details['billing_email'] : '' ) );
				}
				/**
				 * Fires when a checkout becomes abandoned (for example to send an SMS).
				 *
				 * @param int $id Abandoned checkout ID.
				 */
				do_action( 'cartiva_commerce_checkout_abandoned', $id );
			}
			if ( 'abandoned' === $status && $sent < 50 && self::due_email( $id, $now ) !== null ) {
				if ( self::send( $id, (int) self::due_email( $id, $now ) ) ) {
					++$sent;
				}
			}
		}

		// Delete entries past the keep period (privacy).
		$old = get_posts(
			array(
				'post_type'      => CARTIVA_COMMERCE_RECOVERY,
				'post_status'    => 'private',
				'posts_per_page' => 200,
				'fields'         => 'ids',
				'date_query'     => array(
					array(
						'before' => gmdate( 'Y-m-d H:i:s', $now - $keep ),
						'column' => 'post_date_gmt',
					),
				),
			)
		);
		foreach ( $old as $id ) {
			wp_delete_post( (int) $id, true );
		}
	}

	/**
	 * Index of the reminder email due for an entry, or null.
	 *
	 * @param int $id  Entry ID.
	 * @param int $now Current time.
	 * @return int|null
	 */
	public static function due_email( $id, $now ) {
		if ( get_post_meta( $id, '_stop', true ) ) {
			return null;
		}
		$details = get_post_meta( $id, '_details', true );
		if ( ! is_array( $details ) || empty( $details['billing_email'] ) || ! is_email( $details['billing_email'] ) ) {
			return null;
		}
		$abandoned = (int) get_post_meta( $id, '_abandoned', true );
		$done      = get_post_meta( $id, '_sent', true );
		$done      = is_array( $done ) ? $done : array();
		foreach ( self::emails() as $i => $email ) {
			if ( empty( $email['enabled'] ) || isset( $done[ $i ] ) ) {
				continue;
			}
			// Reminders go out in order: only the first unsent, enabled one can be due.
			return $now - $abandoned >= (float) $email['hours'] * HOUR_IN_SECONDS ? (int) $i : null;
		}
		return null;
	}

	/**
	 * A one-time coupon for an entry (created once).
	 *
	 * @param int $id Entry ID.
	 * @return array{code:string,expires:int}|null
	 */
	public static function coupon( $id ) {
		$code    = (string) get_post_meta( $id, '_coupon', true );
		$expires = (int) get_post_meta( $id, '_coupon_expires', true );
		if ( '' !== $code ) {
			return array(
				'code'    => $code,
				'expires' => $expires,
			);
		}
		$percent = max( 1, min( 90, (int) cartiva_commerce_setting( 'coupon_percent' ) ) );
		$days    = max( 1, (int) cartiva_commerce_setting( 'coupon_days' ) );
		$code    = 'cartiva-' . strtolower( wp_generate_password( 8, false, false ) );
		$expires = time() + $days * DAY_IN_SECONDS;
		$coupon  = new WC_Coupon();
		$coupon->set_code( $code );
		$coupon->set_discount_type( 'percent' );
		$coupon->set_amount( $percent );
		$coupon->set_usage_limit( 1 );
		$coupon->set_individual_use( true );
		$coupon->set_date_expires( $expires );
		$details = get_post_meta( $id, '_details', true );
		if ( is_array( $details ) && ! empty( $details['billing_email'] ) ) {
			$coupon->set_email_restrictions( array( $details['billing_email'] ) );
		}
		/* translators: %d: abandoned checkout ID. */
		$coupon->set_description( sprintf( __( 'Cartiva Commerce: reminder coupon for abandoned checkout #%d', 'cartiva-commerce' ), $id ) );
		$coupon->save();
		update_post_meta( $id, '_coupon', $code );
		update_post_meta( $id, '_coupon_expires', $expires );
		return array(
			'code'    => $code,
			'expires' => $expires,
		);
	}

	/**
	 * Placeholder values for an entry's messages.
	 *
	 * @param int  $id         Entry ID.
	 * @param bool $with_coupon Create and include a coupon.
	 * @return array
	 */
	public static function placeholders( $id, $with_coupon = false ) {
		$details = self::meta_array( $id, '_details' );
		$cart    = self::meta_array( $id, '_cart' );
		$name    = ! empty( $details['billing_full_name'] ) ? $details['billing_full_name'] : '';
		$first   = '' !== $name ? strtok( $name, ' ' ) : __( 'there', 'cartiva-commerce' );
		$values  = array(
			'name'     => $first,
			'products' => isset( $cart['names'] ) && '' !== $cart['names'] ? $cart['names'] : __( 'your items', 'cartiva-commerce' ),
			'total'    => isset( $cart['total'] ) ? html_entity_decode( wp_strip_all_tags( wc_price( (float) $cart['total'], array( 'currency' => isset( $cart['currency'] ) ? $cart['currency'] : '' ) ) ), ENT_QUOTES, 'UTF-8' ) : '',
			'link'     => self::link( $id ),
			'site'     => wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ),
			'coupon'   => '',
			'discount' => '',
			'expires'  => '',
		);
		if ( $with_coupon ) {
			$coupon             = self::coupon( $id );
			$values['coupon']   = $coupon ? strtoupper( $coupon['code'] ) : '';
			$values['discount'] = max( 1, min( 90, (int) cartiva_commerce_setting( 'coupon_percent' ) ) ) . '%';
			$values['expires']  = $coupon ? date_i18n( get_option( 'date_format' ), $coupon['expires'] ) : '';
		}
		return $values;
	}

	/**
	 * Send a reminder email.
	 *
	 * @param int $id    Entry ID.
	 * @param int $index Email index.
	 * @return bool
	 */
	public static function send( $id, $index ) {
		$emails = self::emails();
		if ( ! isset( $emails[ $index ] ) ) {
			return false;
		}
		$email   = $emails[ $index ];
		$details = self::meta_array( $id, '_details' );
		$values  = self::placeholders( $id, ! empty( $email['coupon'] ) || false !== strpos( $email['body'], '{coupon}' ) );
		$subject = cartiva_commerce_fill( $email['subject'], $values );
		$button  = '<p style="margin:24px 0"><a href="' . esc_url( $values['link'] ) . '" style="display:inline-block;padding:14px 28px;border-radius:8px;background:#111;color:#fff;font-weight:700;text-decoration:none">' . esc_html__( 'Finish my order', 'cartiva-commerce' ) . '</a></p>';
		$escaped = array();
		foreach ( $values as $key => $value ) {
			$escaped[ $key ] = 'link' === $key ? esc_url( $value ) : esc_html( $value );
		}
		$escaped['button'] = $button;
		$body              = wpautop( cartiva_commerce_fill( esc_html( $email['body'] ), $escaped ) );
		$body             .= '<p style="font-size:12px;color:#777"><a href="' . esc_url( self::link( $id, true ) ) . '" style="color:#777">' . esc_html__( 'Stop these reminders', 'cartiva-commerce' ) . '</a></p>';
		$mailer            = WC()->mailer();
		$html              = $mailer->wrap_message( $subject, $body );
		$ok                = $mailer->send( (string) $details['billing_email'], $subject, $html, "Content-Type: text/html\r\n" );
		if ( $ok ) {
			$done           = get_post_meta( $id, '_sent', true );
			$done           = is_array( $done ) ? $done : array();
			$done[ $index ] = time();
			update_post_meta( $id, '_sent', $done );
			update_post_meta( $id, '_sent_count', count( $done ) );
		}
		return (bool) $ok;
	}

	/**
	 * WhatsApp link for an entry, or ''.
	 *
	 * @param int $id Entry ID.
	 * @return string
	 */
	public static function whatsapp_link( $id ) {
		$details = self::meta_array( $id, '_details' );
		$number  = cartiva_commerce_whatsapp_number( isset( $details['billing_phone'] ) ? $details['billing_phone'] : '', cartiva_commerce_base_country() );
		if ( '' === $number ) {
			return '';
		}
		$text = (string) cartiva_commerce_setting( 'whatsapp_text' );
		$text = '' !== trim( $text ) ? $text : self::default_whatsapp();
		return 'https://wa.me/' . $number . '?text=' . rawurlencode( cartiva_commerce_fill( $text, self::placeholders( $id ) ) );
	}

	/**
	 * Register the personal data exporter.
	 *
	 * @param array $exporters Exporters.
	 * @return array
	 */
	public static function register_exporter( $exporters ) {
		$exporters['cartiva-commerce'] = array(
			'exporter_friendly_name' => __( 'Cartiva Commerce abandoned checkouts', 'cartiva-commerce' ),
			'callback'               => array( __CLASS__, 'export' ),
		);
		return $exporters;
	}

	/**
	 * Register the personal data eraser.
	 *
	 * @param array $erasers Erasers.
	 * @return array
	 */
	public static function register_eraser( $erasers ) {
		$erasers['cartiva-commerce'] = array(
			'eraser_friendly_name' => __( 'Cartiva Commerce abandoned checkouts', 'cartiva-commerce' ),
			'callback'             => array( __CLASS__, 'erase' ),
		);
		return $erasers;
	}

	/**
	 * Entries with an email address.
	 *
	 * @param string $email Email.
	 * @return int[]
	 */
	private static function entries_for_email( $email ) {
		$found = array();
		$ids   = get_posts(
			array(
				'post_type'      => CARTIVA_COMMERCE_RECOVERY,
				'post_status'    => 'private',
				'posts_per_page' => 500,
				'fields'         => 'ids',
			)
		);
		foreach ( $ids as $id ) {
			$details = self::meta_array( $id, '_details' );
			if ( ! empty( $details['billing_email'] ) && strtolower( $details['billing_email'] ) === strtolower( $email ) ) {
				$found[] = (int) $id;
			}
		}
		return $found;
	}

	/**
	 * Export personal data.
	 *
	 * @param string $email Email.
	 * @return array
	 */
	public static function export( $email ) {
		$data = array();
		foreach ( self::entries_for_email( $email ) as $id ) {
			$fields = array();
			foreach ( self::meta_array( $id, '_details' ) as $key => $value ) {
				$fields[] = array(
					'name'  => ucwords( str_replace( array( 'billing_', '_' ), array( '', ' ' ), $key ) ),
					'value' => $value,
				);
			}
			$cart     = self::meta_array( $id, '_cart' );
			$fields[] = array(
				'name'  => __( 'Products', 'cartiva-commerce' ),
				'value' => isset( $cart['names'] ) ? $cart['names'] : '',
			);
			$data[]   = array(
				'group_id'    => 'cartiva-commerce-recovery',
				'group_label' => __( 'Abandoned checkouts', 'cartiva-commerce' ),
				'item_id'     => 'cartiva-recovery-' . $id,
				'data'        => $fields,
			);
		}
		return array(
			'data' => $data,
			'done' => true,
		);
	}

	/**
	 * Erase personal data.
	 *
	 * @param string $email Email.
	 * @return array
	 */
	public static function erase( $email ) {
		$ids = self::entries_for_email( $email );
		foreach ( $ids as $id ) {
			wp_delete_post( $id, true );
		}
		return array(
			'items_removed'  => count( $ids ) > 0,
			'items_retained' => false,
			'messages'       => array(),
			'done'           => true,
		);
	}
}
