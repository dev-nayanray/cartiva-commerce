<?php
/**
 * Checkout field model: the editable fields, presets, sanitizing and
 * applying a configuration to WooCommerce's classic checkout fields.
 *
 * @package Cartiva_Commerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Field definitions and presets.
 */
class Cartiva_Commerce_Fields {

	/**
	 * Editable fields with their WooCommerce defaults.
	 *
	 * Keys are WooCommerce field keys, except "billing_full_name", which the
	 * plugin adds and maps to first and last name.
	 *
	 * @return array
	 */
	public static function definitions() {
		$fields = array(
			'billing_full_name'  => array( 'label' => __( 'Full name', 'cartiva-commerce' ), 'group' => 'billing', 'enabled' => false, 'required' => true, 'width' => 'full', 'priority' => 5 ),
			'billing_first_name' => array( 'label' => __( 'First name', 'cartiva-commerce' ), 'group' => 'billing', 'enabled' => true, 'required' => true, 'width' => 'half', 'priority' => 10 ),
			'billing_last_name'  => array( 'label' => __( 'Last name', 'cartiva-commerce' ), 'group' => 'billing', 'enabled' => true, 'required' => true, 'width' => 'half', 'priority' => 20 ),
			'billing_company'    => array( 'label' => __( 'Company name', 'cartiva-commerce' ), 'group' => 'billing', 'enabled' => true, 'required' => false, 'width' => 'full', 'priority' => 30 ),
			'billing_country'    => array( 'label' => __( 'Country / Region', 'cartiva-commerce' ), 'group' => 'billing', 'enabled' => true, 'required' => true, 'width' => 'full', 'priority' => 40 ),
			'billing_address_1'  => array( 'label' => __( 'Street address', 'cartiva-commerce' ), 'group' => 'billing', 'enabled' => true, 'required' => true, 'width' => 'full', 'priority' => 50 ),
			'billing_address_2'  => array( 'label' => __( 'Apartment, suite, unit, etc.', 'cartiva-commerce' ), 'group' => 'billing', 'enabled' => true, 'required' => false, 'width' => 'full', 'priority' => 60 ),
			'billing_city'       => array( 'label' => __( 'Town / City', 'cartiva-commerce' ), 'group' => 'billing', 'enabled' => true, 'required' => true, 'width' => 'full', 'priority' => 70 ),
			'billing_state'      => array( 'label' => __( 'State / County', 'cartiva-commerce' ), 'group' => 'billing', 'enabled' => true, 'required' => true, 'width' => 'full', 'priority' => 80 ),
			'billing_postcode'   => array( 'label' => __( 'Postcode / ZIP', 'cartiva-commerce' ), 'group' => 'billing', 'enabled' => true, 'required' => true, 'width' => 'full', 'priority' => 90 ),
			'billing_phone'      => array( 'label' => __( 'Phone', 'cartiva-commerce' ), 'group' => 'billing', 'enabled' => true, 'required' => true, 'width' => 'full', 'priority' => 100 ),
			'billing_email'      => array( 'label' => __( 'Email address', 'cartiva-commerce' ), 'group' => 'billing', 'enabled' => true, 'required' => true, 'width' => 'full', 'priority' => 110 ),
			'order_comments'     => array( 'label' => __( 'Order notes', 'cartiva-commerce' ), 'group' => 'order', 'enabled' => true, 'required' => false, 'width' => 'full', 'priority' => 120 ),
		);
		foreach ( $fields as $key => $field ) {
			$fields[ $key ]['placeholder'] = '';
		}
		/**
		 * Filters the editable checkout fields.
		 *
		 * @param array $fields Field definitions.
		 */
		return (array) apply_filters( 'cartiva_commerce_field_definitions', $fields );
	}

	/**
	 * Presets: key => label.
	 *
	 * @param bool $with_default Include "WooCommerce default".
	 * @return array
	 */
	public static function presets( $with_default = true ) {
		$presets = array();
		if ( $with_default ) {
			$presets['default'] = __( 'WooCommerce default (no changes)', 'cartiva-commerce' );
		}
		$presets['bd_cod']  = __( 'Short COD form: Full name, Mobile, Address', 'cartiva-commerce' );
		$presets['minimal'] = __( 'Minimal: Full name, Email, Phone', 'cartiva-commerce' );
		$presets['custom']  = __( 'Custom (edit the fields below)', 'cartiva-commerce' );
		return $presets;
	}

	/**
	 * Field configuration for a preset. Returns null for "default"
	 * (leave WooCommerce's fields untouched).
	 *
	 * @param string $preset Preset key.
	 * @param array  $custom Saved custom configuration.
	 * @return array|null
	 */
	public static function config_for( $preset, $custom = array() ) {
		$defs = self::definitions();
		if ( 'default' === $preset ) {
			return null;
		}
		if ( 'custom' === $preset ) {
			return self::sanitize_config( $custom );
		}
		$config = array();
		foreach ( $defs as $key => $def ) {
			$config[ $key ] = array(
				'enabled'     => false,
				'required'    => $def['required'],
				'label'       => $def['label'],
				'placeholder' => '',
				'width'       => 'full',
				'priority'    => $def['priority'],
			);
		}
		if ( 'bd_cod' === $preset ) {
			$config['billing_full_name'] = array_merge( $config['billing_full_name'], array( 'enabled' => true, 'required' => true, 'label' => __( 'Your full name', 'cartiva-commerce' ), 'placeholder' => __( 'e.g. Rahim Uddin', 'cartiva-commerce' ), 'priority' => 10 ) );
			$config['billing_phone']     = array_merge( $config['billing_phone'], array( 'enabled' => true, 'required' => true, 'label' => __( 'Mobile number', 'cartiva-commerce' ), 'placeholder' => '01XXXXXXXXX', 'priority' => 20 ) );
			$config['billing_address_1'] = array_merge( $config['billing_address_1'], array( 'enabled' => true, 'required' => true, 'label' => __( 'Full address', 'cartiva-commerce' ), 'placeholder' => __( 'House, road, area, district', 'cartiva-commerce' ), 'priority' => 30 ) );
		} elseif ( 'minimal' === $preset ) {
			$config['billing_full_name'] = array_merge( $config['billing_full_name'], array( 'enabled' => true, 'required' => true, 'priority' => 10 ) );
			$config['billing_email']     = array_merge( $config['billing_email'], array( 'enabled' => true, 'required' => true, 'priority' => 20 ) );
			$config['billing_phone']     = array_merge( $config['billing_phone'], array( 'enabled' => true, 'required' => true, 'priority' => 30 ) );
		}
		/**
		 * Filters a preset's field configuration.
		 *
		 * @param array  $config Configuration.
		 * @param string $preset Preset key.
		 */
		return (array) apply_filters( 'cartiva_commerce_preset_config', $config, $preset );
	}

	/**
	 * Clean a submitted field configuration. Unknown fields are dropped.
	 *
	 * @param mixed $raw Raw configuration.
	 * @return array
	 */
	public static function sanitize_config( $raw ) {
		$raw   = is_array( $raw ) ? $raw : array();
		$clean = array();
		foreach ( self::definitions() as $key => $def ) {
			$row           = isset( $raw[ $key ] ) && is_array( $raw[ $key ] ) ? $raw[ $key ] : array();
			$clean[ $key ] = array(
				'enabled'     => ! empty( $row['enabled'] ),
				'required'    => ! empty( $row['required'] ),
				'label'       => isset( $row['label'] ) && '' !== trim( (string) $row['label'] ) ? sanitize_text_field( (string) $row['label'] ) : $def['label'],
				'placeholder' => isset( $row['placeholder'] ) ? sanitize_text_field( (string) $row['placeholder'] ) : '',
				'width'       => isset( $row['width'] ) && in_array( $row['width'], array( 'full', 'first', 'last' ), true ) ? $row['width'] : 'full',
				'priority'    => isset( $row['priority'] ) ? max( 1, min( 999, absint( $row['priority'] ) ) ) : $def['priority'],
			);
		}
		// A form with no name at all cannot create a useful order.
		if ( ! $clean['billing_full_name']['enabled'] && ! $clean['billing_first_name']['enabled'] ) {
			$clean['billing_full_name']['enabled']  = true;
			$clean['billing_full_name']['required'] = true;
		}
		// Full name replaces first and last name.
		if ( $clean['billing_full_name']['enabled'] ) {
			$clean['billing_first_name']['enabled'] = false;
			$clean['billing_last_name']['enabled']  = false;
		}
		return $clean;
	}

	/**
	 * Apply a configuration to WooCommerce's checkout fields
	 * (filter: woocommerce_checkout_fields).
	 *
	 * @param array      $fields WooCommerce checkout fields.
	 * @param array|null $config Field configuration, null for no changes.
	 * @return array
	 */
	public static function apply( $fields, $config ) {
		if ( ! is_array( $config ) ) {
			return $fields;
		}
		$width_class = array(
			'full'  => 'form-row-wide',
			'first' => 'form-row-first',
			'last'  => 'form-row-last',
		);
		foreach ( self::definitions() as $key => $def ) {
			if ( ! isset( $config[ $key ] ) ) {
				continue;
			}
			$row   = $config[ $key ];
			$group = $def['group'];
			if ( ! isset( $fields[ $group ] ) || ! is_array( $fields[ $group ] ) ) {
				$fields[ $group ] = array();
			}
			if ( empty( $row['enabled'] ) ) {
				unset( $fields[ $group ][ $key ] );
				continue;
			}
			$field = isset( $fields[ $group ][ $key ] ) ? $fields[ $group ][ $key ] : array();
			if ( 'billing_full_name' === $key ) {
				$field = array(
					'type'         => 'text',
					'autocomplete' => 'name',
				);
			}
			$classes = isset( $field['class'] ) && is_array( $field['class'] ) ? $field['class'] : array();
			$classes = array_values( array_diff( $classes, array_values( $width_class ) ) );
			array_unshift( $classes, $width_class[ $row['width'] ] );

			$field['label']       = $row['label'];
			$field['placeholder'] = $row['placeholder'];
			$field['required']    = ! empty( $row['required'] );
			$field['class']       = $classes;
			$field['priority']    = (int) $row['priority'];
			if ( 'billing_phone' === $key ) {
				$field['type'] = 'tel';
			}
			$fields[ $group ][ $key ] = $field;
		}
		// Keep WooCommerce's order of rendering in line with the priorities.
		foreach ( array( 'billing', 'order' ) as $group ) {
			if ( ! empty( $fields[ $group ] ) ) {
				uasort(
					$fields[ $group ],
					function ( $a, $b ) {
						$pa = isset( $a['priority'] ) ? (int) $a['priority'] : 0;
						$pb = isset( $b['priority'] ) ? (int) $b['priority'] : 0;
						return $pa <=> $pb;
					}
				);
			}
		}
		return $fields;
	}

	/**
	 * Fill in data for fields the form hides, so WooCommerce can create a
	 * complete order (filter: woocommerce_checkout_posted_data).
	 *
	 * @param array      $data   Posted checkout data.
	 * @param array|null $config Field configuration.
	 * @return array
	 */
	public static function complete_posted_data( $data, $config ) {
		if ( ! is_array( $config ) ) {
			return $data;
		}
		if ( ! empty( $config['billing_full_name']['enabled'] ) ) {
			$full = isset( $data['billing_full_name'] ) ? trim( preg_replace( '/\s+/', ' ', (string) $data['billing_full_name'] ) ) : '';
			if ( '' === $full && isset( $_POST['billing_full_name'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Inside WooCommerce's verified checkout request.
				$full = trim( preg_replace( '/\s+/', ' ', sanitize_text_field( wp_unslash( $_POST['billing_full_name'] ) ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
			}
			$parts = '' === $full ? array() : explode( ' ', $full );
			$last  = count( $parts ) > 1 ? array_pop( $parts ) : '';
			$data['billing_first_name'] = implode( ' ', $parts );
			$data['billing_last_name']  = $last;
			// The full name stays in the data so WooCommerce's required-field check passes.
		}
		if ( empty( $config['billing_country']['enabled'] ) && empty( $data['billing_country'] ) ) {
			$data['billing_country'] = cartiva_commerce_base_country();
		}
		// Ship to the billing address: copy what the form collected.
		if ( empty( $data['ship_to_different_address'] ) ) {
			foreach ( array( 'first_name', 'last_name', 'company', 'country', 'address_1', 'address_2', 'city', 'state', 'postcode' ) as $part ) {
				if ( isset( $data[ 'billing_' . $part ] ) ) {
					$data[ 'shipping_' . $part ] = $data[ 'billing_' . $part ];
				}
			}
		}
		return $data;
	}
}
