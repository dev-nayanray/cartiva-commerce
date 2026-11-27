<?php
/**
 * Helper functions.
 *
 * @package Cartiva_Commerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'CARTIVA_COMMERCE_LANDING' ) ) {
	/**
	 * Landing page post type name.
	 */
	define( 'CARTIVA_COMMERCE_LANDING', 'cartiva_landing' );
}

if ( ! defined( 'CARTIVA_COMMERCE_RECOVERY' ) ) {
	/**
	 * Abandoned checkout post type name.
	 */
	define( 'CARTIVA_COMMERCE_RECOVERY', 'cartiva_recovery' );
}

if ( ! defined( 'CARTIVA_COMMERCE_FUNNEL' ) ) {
	/**
	 * Funnel post type name.
	 */
	define( 'CARTIVA_COMMERCE_FUNNEL', 'cartiva_funnel' );
}

/**
 * Plugin settings with defaults.
 *
 * @return array
 */
function cartiva_commerce_settings() {
	$defaults = array(
		'buy_now'        => true,
		'buy_now_label'  => '',
		'main_preset'    => 'default',
		'main_fields'    => array(),
		'main_bd_phone'  => false,
		'main_coupon'    => true,
		'fb_pixel'       => '',
		'ga4_id'         => '',
		'tiktok_pixel'   => '',
		'track_store'    => false,
		'recovery'       => true,
		'recovery_main'  => false,
		'recovery_after' => 30,
		'recovery_keep'  => 30,
		'recovery_note'  => '',
		'emails'         => array(),
		'coupon_percent' => 10,
		'coupon_days'    => 3,
		'whatsapp_text'  => '',
		'side_cart'      => true,
		'side_cart_open' => true,
		'side_cart_btn'  => false,
		'side_cart_link' => false,
		'ship_bar'       => true,
		'ship_min'       => 0,
		'cart_recs'      => true,
		'deals'          => array(),
		'protect'        => false,
		'protect_main'   => false,
		'protect_mode'   => 'flag',
		'protect_hours'  => 24,
		'protect_max'    => 0,
		'protect_block'  => '',
		'protect_message' => '',
	);
	$saved    = get_option( 'cartiva_commerce_settings', array() );
	return wp_parse_args( is_array( $saved ) ? $saved : array(), $defaults );
}

/**
 * One plugin setting.
 *
 * @param string $key Setting key.
 * @return mixed
 */
function cartiva_commerce_setting( $key ) {
	$settings = cartiva_commerce_settings();
	$value    = isset( $settings[ $key ] ) ? $settings[ $key ] : null;
	/**
	 * Filters a Cartiva Commerce setting.
	 *
	 * @param mixed  $value Value.
	 * @param string $key   Setting key.
	 */
	return apply_filters( 'cartiva_commerce_setting', $value, $key );
}

/**
 * Settings of one landing page, with defaults.
 *
 * @param int $landing_id Landing page ID.
 * @return array
 */
function cartiva_commerce_landing_config( $landing_id ) {
	$defaults = array(
		'product_id'   => 0,
		'variation_id' => 0,
		'quantity'     => 1,
		'qty_editable' => true,
		'gateways'     => array(),
		'preset'       => 'bd_cod',
		'fields'       => array(),
		'thankyou'     => 0,
		'show_coupon'  => false,
		'bd_phone'     => true,
		'offer_mode'   => 'single',
		'offers'       => array(),
		'bumps'        => array(),
		'layout'       => 'one',
		'offer'        => array(),
		'countdown'    => array(),
		'delivery'     => array(),
	);
	$saved  = get_post_meta( (int) $landing_id, '_cartiva_landing', true );
	$config = wp_parse_args( is_array( $saved ) ? $saved : array(), $defaults );
	/**
	 * Filters a landing page's settings as the shop uses them.
	 *
	 * @param array $config     Settings.
	 * @param int   $landing_id Landing page ID.
	 */
	return apply_filters( 'cartiva_commerce_landing_config', $config, (int) $landing_id );
}

/**
 * Is this ID a published (or previewable) landing page?
 *
 * @param int $id Post ID.
 * @return bool
 */
function cartiva_commerce_is_landing( $id ) {
	$id = (int) $id;
	if ( $id < 1 || CARTIVA_COMMERCE_LANDING !== get_post_type( $id ) ) {
		return false;
	}
	$status = get_post_status( $id );
	return 'publish' === $status || ( in_array( $status, array( 'draft', 'pending', 'future', 'private' ), true ) && current_user_can( 'edit_post', $id ) );
}

/**
 * The landing page the current request belongs to, or 0.
 *
 * Works on the landing page itself and on WooCommerce's checkout requests sent
 * from its order form (live totals and Place order), which carry the
 * landing page ID in a hidden field. Main checkout requests never carry it,
 * so the main checkout keeps its own rules.
 *
 * @return int
 */
function cartiva_commerce_current_landing_id() {
	static $cached = null;
	if ( null !== $cached && ! defined( 'CARTIVA_COMMERCE_TESTING' ) ) {
		return $cached;
	}
	$id = 0;
	if ( did_action( 'wp' ) && is_singular( CARTIVA_COMMERCE_LANDING ) ) {
		$id = (int) get_queried_object_id();
	} else {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- WooCommerce verifies its own checkout nonces; this value only selects which field rules apply.
		if ( isset( $_POST['_cartiva_landing_id'] ) ) {
			$id = absint( wp_unslash( $_POST['_cartiva_landing_id'] ) );
		} elseif ( isset( $_POST['post_data'] ) && is_string( $_POST['post_data'] ) ) {
			$post_data = array();
			wp_parse_str( wp_unslash( $_POST['post_data'] ), $post_data ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Parsed into an array, then only one integer is read.
			if ( isset( $post_data['_cartiva_landing_id'] ) ) {
				$id = absint( $post_data['_cartiva_landing_id'] );
			}
		}
		// phpcs:enable
	}
	$cached = cartiva_commerce_is_landing( $id ) ? $id : 0;
	return $cached;
}

/**
 * Store base country (used when the country field is hidden).
 *
 * @return string
 */
function cartiva_commerce_base_country() {
	return ( function_exists( 'WC' ) && WC()->countries ) ? WC()->countries->get_base_country() : '';
}

/**
 * Is a Bangladeshi mobile number valid? Accepts 01XXXXXXXXX, 8801XXXXXXXXX
 * and +8801XXXXXXXXX, with spaces or dashes.
 *
 * @param string $phone Phone number.
 * @return bool
 */
function cartiva_commerce_is_bd_mobile( $phone ) {
	$digits = preg_replace( '/[\s\-()]/', '', (string) $phone );
	return (bool) preg_match( '/^(?:\+?88)?01[3-9]\d{8}$/', $digits );
}

/**
 * Product options of a landing page.
 *
 * Landing pages saved before options existed keep working: their single
 * product becomes the only option.
 *
 * @param array $config Landing settings.
 * @return array[] Each: product_id, variation_id, qty, label, discount, badge, default.
 */
function cartiva_commerce_landing_offers( $config ) {
	$defaults = array(
		'product_id'   => 0,
		'variation_id' => 0,
		'qty'          => 1,
		'label'        => '',
		'discount'     => 0,
		'badge'        => '',
		'default'      => false,
	);
	$offers   = array();
	foreach ( (array) $config['offers'] as $row ) {
		if ( is_array( $row ) && ! empty( $row['product_id'] ) ) {
			$offers[] = wp_parse_args( $row, $defaults );
		}
	}
	if ( empty( $offers ) && ! empty( $config['product_id'] ) ) {
		$offers[] = wp_parse_args(
			array(
				'product_id'   => (int) $config['product_id'],
				'variation_id' => (int) $config['variation_id'],
				'qty'          => (int) $config['quantity'],
				'default'      => true,
			),
			$defaults
		);
	}
	if ( 'single' === $config['offer_mode'] ) {
		$offers = array_slice( $offers, 0, 1 );
	}
	return $offers;
}

/**
 * Options picked when the landing page opens.
 *
 * @param array $config Landing settings.
 * @return int[] Option indexes.
 */
function cartiva_commerce_default_offer_indexes( $config ) {
	$offers   = cartiva_commerce_landing_offers( $config );
	$selected = array();
	foreach ( $offers as $i => $row ) {
		if ( ! empty( $row['default'] ) ) {
			$selected[] = (int) $i;
		}
	}
	if ( empty( $selected ) && ! empty( $offers ) ) {
		$selected = array( 0 );
	}
	if ( 'many' !== $config['offer_mode'] ) {
		$selected = array_slice( $selected, 0, 1 );
	}
	return $selected;
}

/**
 * Price after a percentage discount, rounded to the store's decimals.
 *
 * @param float $price    Price.
 * @param float $discount Discount in percent (0-90).
 * @return float
 */
function cartiva_commerce_discounted( $price, $discount ) {
	$discount = max( 0, min( 90, (float) $discount ) );
	$decimals = function_exists( 'wc_get_price_decimals' ) ? wc_get_price_decimals() : 2;
	return round( (float) $price * ( 100 - $discount ) / 100, $decimals );
}

/**
 * Funnel a landing page belongs to, or 0.
 *
 * @param int $landing_id Landing page ID.
 * @return int
 */
function cartiva_commerce_funnel_of( $landing_id ) {
	return (int) get_post_meta( (int) $landing_id, '_cartiva_funnel', true );
}

/**
 * Steps of a funnel, in order.
 *
 * @param int $funnel_id Funnel ID.
 * @return array[] Each: id, type (landing, checkout or thankyou).
 */
function cartiva_commerce_funnel_steps( $funnel_id ) {
	$steps = get_post_meta( (int) $funnel_id, '_cartiva_funnel_steps', true );
	return is_array( $steps ) ? array_values( $steps ) : array();
}

/**
 * The published step after a landing page in its funnel, or 0.
 *
 * @param int         $landing_id Landing page ID.
 * @param string|null $type       Only a step of this type (any later one), or null for the next step.
 * @return int
 */
function cartiva_commerce_next_step( $landing_id, $type = null ) {
	$landing_id = cartiva_commerce_canonical_step( $landing_id );
	$funnel     = cartiva_commerce_funnel_of( $landing_id );
	if ( ! $funnel ) {
		return 0;
	}
	$found = false;
	foreach ( cartiva_commerce_funnel_steps( $funnel ) as $step ) {
		if ( $found && 'publish' === get_post_status( (int) $step['id'] ) && ( null === $type || $type === $step['type'] ) ) {
			return (int) $step['id'];
		}
		if ( (int) $step['id'] === (int) $landing_id ) {
			$found = true;
		}
	}
	return 0;
}

/**
 * Type of a funnel step, or ''.
 *
 * @param int $landing_id Landing page ID.
 * @return string landing, checkout, upsell, downsell, thankyou or ''.
 */
function cartiva_commerce_step_type( $landing_id ) {
	$landing_id = cartiva_commerce_canonical_step( $landing_id );
	$funnel     = cartiva_commerce_funnel_of( $landing_id );
	foreach ( $funnel ? cartiva_commerce_funnel_steps( $funnel ) : array() as $step ) {
		if ( (int) $step['id'] === (int) $landing_id ) {
			return (string) $step['type'];
		}
	}
	return '';
}

/**
 * The published step that follows a step, optionally skipping downsells
 * (used after an upsell is accepted, or after a downsell).
 *
 * @param int  $landing_id     Landing page ID.
 * @param bool $skip_downsells Skip downsell steps.
 * @return array|null Step (id, type) or null.
 */
function cartiva_commerce_step_after( $landing_id, $skip_downsells = false ) {
	$landing_id = cartiva_commerce_canonical_step( $landing_id );
	$funnel     = cartiva_commerce_funnel_of( $landing_id );
	$found  = false;
	foreach ( $funnel ? cartiva_commerce_funnel_steps( $funnel ) : array() as $step ) {
		if ( $found && 'publish' === get_post_status( (int) $step['id'] ) && ! ( $skip_downsells && 'downsell' === $step['type'] ) ) {
			return $step;
		}
		if ( (int) $step['id'] === (int) $landing_id ) {
			$found = true;
		}
	}
	return null;
}

/**
 * The funnel step a page stands for. An A/B test variant stands for the step
 * it is tested against; every other page stands for itself.
 *
 * @param int $landing_id Landing page ID.
 * @return int
 */
function cartiva_commerce_canonical_step( $landing_id ) {
	$parent = (int) get_post_meta( (int) $landing_id, '_cartiva_ab_parent', true );
	return $parent > 0 ? $parent : (int) $landing_id;
}

/**
 * Is the visitor a search engine or another bot? Bots are not counted and
 * not put into A/B tests.
 *
 * @return bool
 */
function cartiva_commerce_is_bot() {
	$agent = isset( $_SERVER['HTTP_USER_AGENT'] ) ? strtolower( sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) ) : '';
	return '' === $agent || (bool) preg_match( '/bot|crawl|spider|slurp|facebookexternalhit|preview|lighthouse|headless|curl|wget|python|monitor/', $agent );
}

/**
 * Count a visit to a landing page: the total and a per-day log (kept for
 * 400 days) for the analytics date ranges.
 *
 * @param int $landing_id Landing page ID.
 */
function cartiva_commerce_count_visit( $landing_id ) {
	$landing_id = (int) $landing_id;
	update_post_meta( $landing_id, '_cartiva_visits', (int) get_post_meta( $landing_id, '_cartiva_visits', true ) + 1 );
	$log   = get_post_meta( $landing_id, '_cartiva_visits_log', true );
	$log   = is_array( $log ) ? $log : array();
	$today = gmdate( 'Y-m-d', time() + (int) round( (float) get_option( 'gmt_offset', 0 ) * HOUR_IN_SECONDS ) );
	$log[ $today ] = isset( $log[ $today ] ) ? (int) $log[ $today ] + 1 : 1;
	if ( count( $log ) > 400 ) {
		ksort( $log );
		$log = array_slice( $log, -400, null, true );
	}
	update_post_meta( $landing_id, '_cartiva_visits_log', $log );
}

/**
 * Visits of a landing page between two days (Y-m-d, inclusive), or all time
 * when $from is empty.
 *
 * @param int    $landing_id Landing page ID.
 * @param string $from       First day or ''.
 * @param string $to         Last day or ''.
 * @return int
 */
function cartiva_commerce_visits_between( $landing_id, $from = '', $to = '' ) {
	if ( '' === $from ) {
		return (int) get_post_meta( (int) $landing_id, '_cartiva_visits', true );
	}
	$log   = get_post_meta( (int) $landing_id, '_cartiva_visits_log', true );
	$total = 0;
	foreach ( is_array( $log ) ? $log : array() as $day => $count ) {
		if ( $day >= $from && ( '' === $to || $day <= $to ) ) {
			$total += (int) $count;
		}
	}
	return $total;
}

/**
 * A phone number in international digits for WhatsApp links (wa.me).
 * Local numbers starting with 0 get the store country's calling code
 * (Bangladesh: 01712345678 becomes 8801712345678).
 *
 * @param string $phone   Phone number.
 * @param string $country Store country code.
 * @return string Digits, or '' when too short.
 */
function cartiva_commerce_whatsapp_number( $phone, $country = '' ) {
	$raw    = trim( (string) $phone );
	$digits = preg_replace( '/\D/', '', $raw );
	if ( 0 === strpos( $raw, '+' ) || 0 === strpos( $digits, '00' ) ) {
		$digits = ltrim( $digits, '0' );
	} elseif ( 0 === strpos( $digits, '0' ) ) {
		$codes  = array(
			'BD' => '880',
			'IN' => '91',
			'PK' => '92',
			'NP' => '977',
			'LK' => '94',
			'GB' => '44',
			'MY' => '60',
			'AE' => '971',
			'SA' => '966',
		);
		$code   = isset( $codes[ strtoupper( (string) $country ) ] ) ? $codes[ strtoupper( (string) $country ) ] : '';
		$digits = '' !== $code ? $code . substr( $digits, 1 ) : $digits;
	}
	return strlen( $digits ) >= 10 ? $digits : '';
}

/**
 * Fill {placeholders} in a message.
 *
 * @param string $text   Text.
 * @param array  $values Placeholder => value.
 * @return string
 */
function cartiva_commerce_fill( $text, $values ) {
	$pairs = array();
	foreach ( $values as $key => $value ) {
		$pairs[ '{' . $key . '}' ] = (string) $value;
	}
	return strtr( (string) $text, $pairs );
}
