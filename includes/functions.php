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
