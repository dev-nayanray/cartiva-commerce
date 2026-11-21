<?php
/**
 * Offers: product options (packs and choices), order bumps, discounts and the
 * inline coupon field of landing page order forms.
 *
 * Cart lines added by a landing page carry a reference to it
 * (cartiva_offer or cartiva_bump: landing ID and option index). Prices are
 * always worked out again from the landing page settings, never from the
 * request, so a shopper cannot change a discount.
 *
 * @package Cartiva_Commerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Offers.
 */
class Cartiva_Commerce_Offers {

	/**
	 * Hook up.
	 */
	public static function init() {
		add_action( 'woocommerce_before_calculate_totals', array( __CLASS__, 'prices' ), 20 );
		add_action( 'wc_ajax_cartiva_commerce_select', array( __CLASS__, 'ajax_select' ) );
		add_action( 'wc_ajax_cartiva_commerce_bump', array( __CLASS__, 'ajax_bump' ) );
		add_action( 'woocommerce_review_order_before_payment', array( __CLASS__, 'bumps_before_payment' ) );
		add_action( 'woocommerce_checkout_after_customer_details', array( __CLASS__, 'bumps_after_customer' ) );
		add_action( 'woocommerce_checkout_before_order_review', array( __CLASS__, 'coupon_field' ) );
		add_action( 'woocommerce_checkout_create_order_line_item', array( __CLASS__, 'tag_line_item' ), 10, 4 );
	}

	/**
	 * Cart lines that belong to a landing page.
	 *
	 * @param int $landing_id Landing page ID.
	 * @return array{offers:array,bumps:array,other:array} Index => cart key (other: keys).
	 */
	public static function landing_lines( $landing_id ) {
		$lines = array(
			'offers' => array(),
			'bumps'  => array(),
			'other'  => array(),
		);
		if ( ! WC()->cart ) {
			return $lines;
		}
		foreach ( WC()->cart->get_cart() as $key => $item ) {
			if ( ! empty( $item['cartiva_gift'] ) ) {
				continue; // Free gifts follow the products they come with.
			}
			if ( isset( $item['cartiva_offer']['landing'] ) && (int) $item['cartiva_offer']['landing'] === (int) $landing_id ) {
				$lines['offers'][ (int) $item['cartiva_offer']['index'] ] = (string) $key;
			} elseif ( isset( $item['cartiva_bump']['landing'] ) && (int) $item['cartiva_bump']['landing'] === (int) $landing_id ) {
				$lines['bumps'][ (int) $item['cartiva_bump']['index'] ] = (string) $key;
			} else {
				$lines['other'][] = (string) $key;
			}
		}
		return $lines;
	}

	/**
	 * Add one option or bump to the cart.
	 *
	 * @param int    $landing_id Landing page ID.
	 * @param string $type       offer or bump.
	 * @param int    $index      Option index.
	 * @param array  $row        Option settings.
	 * @param int    $qty        Quantity (defaults to the option's).
	 * @return string Cart key or ''.
	 */
	public static function add( $landing_id, $type, $index, $row, $qty = 0 ) {
		$attributes = array();
		if ( ! empty( $row['variation_id'] ) ) {
			$variation  = wc_get_product( (int) $row['variation_id'] );
			$attributes = $variation ? $variation->get_variation_attributes() : array();
		}
		$key = WC()->cart->add_to_cart(
			(int) $row['product_id'],
			$qty > 0 ? $qty : max( 1, (int) $row['qty'] ),
			(int) $row['variation_id'],
			$attributes,
			array(
				'cartiva_' . $type => array(
					'landing' => (int) $landing_id,
					'index'   => (int) $index,
				),
			)
		);
		return $key ? (string) $key : '';
	}

	/**
	 * Remove WooCommerce's "added to cart" notices: the order form is on screen.
	 */
	public static function clear_added_notices() {
		$notices = wc_get_notices();
		unset( $notices['success'] );
		wc_set_notices( $notices );
	}

	/**
	 * Make the cart hold this landing page's default options (unless it
	 * already holds only this page's lines, for example after a reload).
	 *
	 * @param int   $landing_id Landing page ID.
	 * @param array $config     Landing settings.
	 */
	public static function setup_cart( $landing_id, $config ) {
		$offers = cartiva_commerce_landing_offers( $config );
		if ( empty( $offers ) || ! WC()->cart ) {
			return;
		}
		$lines = self::landing_lines( $landing_id );
		$valid = ! empty( $lines['offers'] ) && empty( $lines['other'] );
		foreach ( array_keys( $lines['offers'] ) as $index ) {
			if ( ! isset( $offers[ $index ] ) ) {
				$valid = false;
			}
		}
		if ( $valid ) {
			return;
		}
		WC()->cart->empty_cart();
		foreach ( cartiva_commerce_default_offer_indexes( $config ) as $index ) {
			self::add( $landing_id, 'offer', $index, $offers[ $index ] );
		}
		self::clear_added_notices();
	}

	/**
	 * Replace the landing page's options in the cart with the chosen ones.
	 *
	 * @param int   $landing_id Landing page ID.
	 * @param int[] $indexes    Chosen option indexes (already validated).
	 */
	public static function select( $landing_id, $indexes ) {
		$config = cartiva_commerce_landing_config( $landing_id );
		$offers = cartiva_commerce_landing_offers( $config );
		$lines  = self::landing_lines( $landing_id );
		foreach ( $lines['offers'] as $index => $key ) {
			if ( ! in_array( $index, $indexes, true ) ) {
				WC()->cart->remove_cart_item( $key );
			}
		}
		foreach ( $indexes as $index ) {
			if ( ! isset( $lines['offers'][ $index ] ) && isset( $offers[ $index ] ) ) {
				self::add( $landing_id, 'offer', $index, $offers[ $index ] );
			}
		}
		self::clear_added_notices();
	}

	/**
	 * Settings row behind a cart line, if it still matches the line's product.
	 *
	 * @param array $item Cart item.
	 * @return array|null
	 */
	public static function row_for_item( $item ) {
		foreach ( array( 'offer', 'bump' ) as $type ) {
			if ( empty( $item[ 'cartiva_' . $type ]['landing'] ) ) {
				continue;
			}
			$ref    = $item[ 'cartiva_' . $type ];
			$config = cartiva_commerce_landing_config( (int) $ref['landing'] );
			$list   = 'offer' === $type ? cartiva_commerce_landing_offers( $config ) : (array) $config['bumps'];
			$row    = isset( $list[ (int) $ref['index'] ] ) ? $list[ (int) $ref['index'] ] : null;
			if ( is_array( $row ) && (int) $row['product_id'] === (int) $item['product_id'] && (int) $row['variation_id'] === (int) $item['variation_id'] ) {
				return $row;
			}
		}
		return null;
	}

	/**
	 * Apply option and bump discounts.
	 *
	 * @param WC_Cart $cart Cart.
	 */
	public static function prices( $cart ) {
		if ( is_admin() && ! wp_doing_ajax() ) {
			return;
		}
		foreach ( $cart->get_cart() as $item ) {
			$row = self::row_for_item( $item );
			if ( ! $row || empty( $row['discount'] ) || ! isset( $item['data'] ) ) {
				continue;
			}
			// Read the price fresh, so running totals twice never discounts twice.
			$fresh = wc_get_product( $item['variation_id'] ? (int) $item['variation_id'] : (int) $item['product_id'] );
			if ( $fresh && '' !== $fresh->get_price() ) {
				$item['data']->set_price( cartiva_commerce_discounted( (float) $fresh->get_price(), (float) $row['discount'] ) );
			}
		}
	}

	/**
	 * Check the shared nonce and landing page of an order form request.
	 *
	 * @return int Landing page ID (ends the request on failure).
	 */
	private static function request_landing() {
		check_ajax_referer( 'cartiva_commerce_form', 'nonce' );
		$landing_id = isset( $_POST['landing'] ) ? absint( wp_unslash( $_POST['landing'] ) ) : 0;
		if ( ! cartiva_commerce_is_landing( $landing_id ) || ! WC()->cart ) {
			wp_send_json_error( null, 400 );
		}
		return $landing_id;
	}

	/**
	 * Choose product options (wc-ajax endpoint).
	 */
	public static function ajax_select() {
		$landing_id = self::request_landing();
		$config     = cartiva_commerce_landing_config( $landing_id );
		$offers     = cartiva_commerce_landing_offers( $config );
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Checked in request_landing().
		$raw     = isset( $_POST['options'] ) ? (array) wp_unslash( $_POST['options'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Cast to integers below.
		$indexes = array();
		foreach ( $raw as $value ) {
			$index = absint( $value );
			if ( isset( $offers[ $index ] ) && ! in_array( $index, $indexes, true ) ) {
				$indexes[] = $index;
			}
		}
		if ( 'single' === $config['offer_mode'] || empty( $indexes ) ) {
			wp_send_json_error( null, 400 );
		}
		if ( 'one' === $config['offer_mode'] ) {
			$indexes = array_slice( $indexes, 0, 1 );
		}
		self::select( $landing_id, $indexes );
		wp_send_json_success( array( 'options' => $indexes ) );
	}

	/**
	 * Bumps a shopper may add now.
	 *
	 * @param int    $landing_id Landing page ID.
	 * @param string $position   before_payment, after_customer or '' for all.
	 * @return array Index => array( row, product, in_cart ).
	 */
	public static function visible_bumps( $landing_id, $position = '' ) {
		$config  = cartiva_commerce_landing_config( $landing_id );
		$lines   = self::landing_lines( $landing_id );
		$offered = array();
		foreach ( cartiva_commerce_landing_offers( $config ) as $offer ) {
			$offered[] = (int) $offer['product_id'] . ':' . (int) $offer['variation_id'];
		}
		$subtotal = 0.0;
		foreach ( WC()->cart ? WC()->cart->get_cart() : array() as $item ) {
			if ( empty( $item['cartiva_bump'] ) ) {
				$subtotal += (float) $item['line_subtotal'];
			}
		}
		$list = array();
		foreach ( (array) $config['bumps'] as $index => $row ) {
			if ( empty( $row['product_id'] ) || ( '' !== $position && $position !== $row['position'] ) ) {
				continue;
			}
			$product = wc_get_product( $row['variation_id'] ? (int) $row['variation_id'] : (int) $row['product_id'] );
			if ( ! $product || ! $product->is_purchasable() || ! $product->is_in_stock() ) {
				continue;
			}
			if ( in_array( (int) $row['product_id'] . ':' . (int) $row['variation_id'], $offered, true ) ) {
				continue;
			}
			$in_cart = isset( $lines['bumps'][ $index ] );
			if ( ! $in_cart && (float) $row['min_total'] > 0 && $subtotal < (float) $row['min_total'] ) {
				continue;
			}
			$list[ (int) $index ] = array(
				'row'     => $row,
				'product' => $product,
				'in_cart' => $in_cart,
			);
		}
		return $list;
	}

	/**
	 * Add or remove an order bump (wc-ajax endpoint).
	 */
	public static function ajax_bump() {
		$landing_id = self::request_landing();
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- Checked in request_landing().
		$index = isset( $_POST['index'] ) ? absint( wp_unslash( $_POST['index'] ) ) : -1;
		$on    = ! empty( $_POST['on'] ) && '0' !== $_POST['on'];
		// phpcs:enable
		$lines = self::landing_lines( $landing_id );
		if ( $on ) {
			$visible = self::visible_bumps( $landing_id );
			if ( ! isset( $visible[ $index ] ) ) {
				wp_send_json_error( null, 400 );
			}
			if ( ! isset( $lines['bumps'][ $index ] ) ) {
				self::add( $landing_id, 'bump', $index, $visible[ $index ]['row'] );
				self::clear_added_notices();
			}
		} elseif ( isset( $lines['bumps'][ $index ] ) ) {
			WC()->cart->remove_cart_item( $lines['bumps'][ $index ] );
		}
		wp_send_json_success( array( 'on' => $on ) );
	}

	/**
	 * Print bump boxes for a position.
	 *
	 * @param string $position Position.
	 */
	public static function render_bumps( $position ) {
		$landing_id = cartiva_commerce_current_landing_id();
		if ( ! $landing_id ) {
			return;
		}
		$bumps = self::visible_bumps( $landing_id, $position );
		if ( empty( $bumps ) ) {
			return;
		}
		echo '<div class="cartiva-bumps">';
		foreach ( $bumps as $index => $bump ) {
			$row     = $bump['row'];
			$product = $bump['product'];
			$regular = (float) $product->get_price() * max( 1, (int) $row['qty'] );
			$price   = cartiva_commerce_discounted( (float) $product->get_price(), (float) $row['discount'] ) * max( 1, (int) $row['qty'] );
			$name    = wp_strip_all_tags( $product->get_name() );
			/* translators: %s: product name. */
			$title = '' !== $row['title'] ? $row['title'] : sprintf( __( 'Yes, add %s to my order', 'cartiva-commerce' ), $name );
			$id    = 'cartiva-bump-' . (int) $index;
			?>
			<div class="cartiva-bump<?php echo esc_attr( $bump['in_cart'] ? ' is-added' : '' ); ?>">
				<label class="cartiva-bump__head" for="<?php echo esc_attr( $id ); ?>">
					<input type="checkbox" id="<?php echo esc_attr( $id ); ?>" class="cartiva-bump__check" data-landing="<?php echo esc_attr( (string) $landing_id ); ?>" data-index="<?php echo esc_attr( (string) $index ); ?>" data-item="<?php echo esc_attr( (string) wp_json_encode( Cartiva_Commerce_Tracking::item( $product, max( 1, (int) $row['qty'] ), cartiva_commerce_discounted( (float) $product->get_price(), (float) $row['discount'] ) ) ) ); ?>" <?php checked( $bump['in_cart'] ); ?> />
					<span class="cartiva-bump__title"><?php echo esc_html( $title ); ?></span>
				</label>
				<div class="cartiva-bump__body">
					<div class="cartiva-bump__image"><?php echo wp_kses_post( $product->get_image( 'woocommerce_gallery_thumbnail', array( 'alt' => '' ) ) ); ?></div>
					<div>
						<?php if ( '' !== $row['text'] ) : ?>
							<p class="cartiva-bump__text"><?php echo esc_html( $row['text'] ); ?></p>
						<?php endif; ?>
						<p class="cartiva-bump__price">
							<?php if ( $price < $regular ) : ?>
								<del aria-hidden="true"><?php echo wp_kses_post( wc_price( $regular ) ); ?></del>
							<?php endif; ?>
							<?php echo wp_kses_post( wc_price( $price ) ); ?>
						</p>
					</div>
				</div>
			</div>
			<?php
		}
		echo '</div>';
	}

	/**
	 * Bumps above the payment methods.
	 */
	public static function bumps_before_payment() {
		self::render_bumps( 'before_payment' );
	}

	/**
	 * Bumps under the customer details.
	 */
	public static function bumps_after_customer() {
		self::render_bumps( 'after_customer' );
	}

	/**
	 * Coupon field inside landing page order forms.
	 */
	public static function coupon_field() {
		$landing_id = cartiva_commerce_current_landing_id();
		if ( ! $landing_id || ! wc_coupons_enabled() || ! cartiva_commerce_landing_config( $landing_id )['show_coupon'] ) {
			return;
		}
		?>
		<div class="cartiva-coupon">
			<label class="screen-reader-text" for="cartiva-coupon-code"><?php esc_html_e( 'Coupon code', 'cartiva-commerce' ); ?></label>
			<input type="text" id="cartiva-coupon-code" class="input-text cartiva-coupon__code" placeholder="<?php esc_attr_e( 'Coupon code', 'cartiva-commerce' ); ?>" autocomplete="off" />
			<button type="button" class="button cartiva-coupon__apply"><?php esc_html_e( 'Apply', 'cartiva-commerce' ); ?></button>
		</div>
		<?php
	}

	/**
	 * Remember on order lines which ones were options and bumps (for reports).
	 *
	 * @param WC_Order_Item_Product $item          Order line.
	 * @param string                $cart_item_key Cart key.
	 * @param array                 $values        Cart item.
	 * @param WC_Order              $order         Order.
	 */
	public static function tag_line_item( $item, $cart_item_key, $values, $order ) {
		if ( ! empty( $values['cartiva_bump'] ) ) {
			$item->add_meta_data( '_cartiva_bump', (int) $values['cartiva_bump']['index'], true );
		} elseif ( ! empty( $values['cartiva_offer'] ) ) {
			$item->add_meta_data( '_cartiva_offer', (int) $values['cartiva_offer']['index'], true );
		}
	}
}
