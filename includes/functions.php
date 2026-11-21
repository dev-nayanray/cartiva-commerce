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
	);
	$saved    = get_post_meta( (int) $landing_id, '_cartiva_landing', true );
	return wp_parse_args( is_array( $saved ) ? $saved : array(), $defaults );
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
	$funnel = cartiva_commerce_funnel_of( $landing_id );
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
	$funnel = cartiva_commerce_funnel_of( $landing_id );
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
	$funnel = cartiva_commerce_funnel_of( $landing_id );
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
