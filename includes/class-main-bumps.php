<?php
/**
 * Order bumps on the main checkout: up to three "Yes, add this to my order"
 * boxes set in Settings > Cart & deals, shown on the classic checkout page
 * (landing pages have their own bumps).
 *
 * Cart items carry `cartiva_main_bump` => index.
 *
 * @package Cartiva_Commerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Main checkout bumps.
 */
class Cartiva_Commerce_Main_Bumps {

	const MAX = 3;

	/**
	 * Hook up.
	 */
	public static function init() {
		add_action( 'woocommerce_review_order_before_payment', array( __CLASS__, 'render' ), 6 );
		add_action( 'wc_ajax_cartiva_commerce_main_bump', array( __CLASS__, 'ajax_toggle' ) );
		add_action( 'woocommerce_before_calculate_totals', array( __CLASS__, 'prices' ), 21 );
		add_action( 'woocommerce_check_cart_items', array( __CLASS__, 'clean_cart' ) );
		add_action( 'woocommerce_checkout_create_order_line_item', array( __CLASS__, 'tag_line_item' ), 10, 3 );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'assets' ) );
	}

	/**
	 * Saved bump rows.
	 *
	 * @return array[]
	 */
	public static function rows() {
		$rows = cartiva_commerce_setting( 'main_bumps' );
		return is_array( $rows ) ? array_slice( array_values( $rows ), 0, self::MAX ) : array();
	}

	/**
	 * Clean the rows from the settings form.
	 *
	 * @param mixed $raw Raw rows.
	 * @return array[]
	 */
	public static function sanitize( $raw ) {
		$out = array();
		foreach ( is_array( $raw ) ? $raw : array() as $row ) {
			if ( ! is_array( $row ) || count( $out ) >= self::MAX ) {
				continue;
			}
			$ids = Cartiva_Commerce_Landing::resolve_single( isset( $row['item'] ) ? $row['item'] : 0 );
			if ( ! $ids ) {
				continue;
			}
			$when  = Cartiva_Commerce_Landing::resolve_item( isset( $row['when'] ) ? $row['when'] : 0 );
			$out[] = array(
				'active'       => ! empty( $row['active'] ),
				'product_id'   => $ids['product_id'],
				'variation_id' => $ids['variation_id'],
				'discount'     => isset( $row['discount'] ) ? (float) max( 0, min( 90, round( (float) $row['discount'], 1 ) ) ) : 0.0,
				'title'        => isset( $row['title'] ) ? sanitize_text_field( $row['title'] ) : '',
				'text'         => isset( $row['text'] ) ? sanitize_textarea_field( $row['text'] ) : '',
				'min_total'    => isset( $row['min_total'] ) ? max( 0, round( (float) $row['min_total'], 2 ) ) : 0,
				'when_id'      => $when ? (int) $when['product_id'] : 0,
			);
		}
		return $out;
	}

	/**
	 * Is this the main checkout (not a landing page form)?
	 *
	 * @return bool
	 */
	public static function on_main_checkout() {
		return ! cartiva_commerce_current_landing_id();
	}

	/**
	 * Cart key of a bump line, by index.
	 *
	 * @return array index => cart key.
	 */
	public static function lines() {
		$lines = array();
		foreach ( WC()->cart ? WC()->cart->get_cart() : array() as $key => $item ) {
			if ( isset( $item['cartiva_main_bump'] ) ) {
				$lines[ (int) $item['cartiva_main_bump'] ] = (string) $key;
			}
		}
		return $lines;
	}

	/**
	 * Bumps the shopper can see now.
	 *
	 * @return array index => row, product, in_cart.
	 */
	public static function visible() {
		$rows = self::rows();
		if ( ! $rows || ! WC()->cart ) {
			return array();
		}
		$lines    = self::lines();
		$subtotal = 0.0;
		$in_cart  = array();
		foreach ( WC()->cart->get_cart() as $item ) {
			if ( isset( $item['cartiva_main_bump'] ) ) {
				continue;
			}
			$subtotal += (float) ( isset( $item['line_subtotal'] ) ? $item['line_subtotal'] : 0 );
			$in_cart[] = (int) $item['product_id'];
			$in_cart[] = (int) $item['variation_id'];
		}
		$list = array();
		foreach ( $rows as $index => $row ) {
			if ( empty( $row['active'] ) ) {
				continue;
			}
			$added = isset( $lines[ $index ] );
			if ( ! $added ) {
				if ( in_array( (int) $row['product_id'], $in_cart, true ) || ( $row['variation_id'] && in_array( (int) $row['variation_id'], $in_cart, true ) ) ) {
					continue; // Already buying it.
				}
				if ( $row['when_id'] && ! in_array( (int) $row['when_id'], $in_cart, true ) ) {
					continue;
				}
				if ( (float) $row['min_total'] > 0 && $subtotal < (float) $row['min_total'] ) {
					continue;
				}
			}
			$product = wc_get_product( $row['variation_id'] ? (int) $row['variation_id'] : (int) $row['product_id'] );
			if ( ! $product || ! $product->is_purchasable() || ( ! $added && ! $product->is_in_stock() ) ) {
				continue;
			}
			$list[ $index ] = array(
				'row'     => $row,
				'product' => $product,
				'in_cart' => $added,
			);
		}
		return $list;
	}

	/**
	 * Bump boxes above the payment methods on the main checkout.
	 */
	public static function render() {
		if ( ! self::on_main_checkout() ) {
			return;
		}
		$bumps = self::visible();
		if ( ! $bumps ) {
			return;
		}
		echo '<div class="cartiva-bumps cartiva-bumps--main">';
		foreach ( $bumps as $index => $bump ) {
			$row     = $bump['row'];
			$product = $bump['product'];
			$regular = (float) $product->get_price();
			$price   = cartiva_commerce_discounted( $regular, (float) $row['discount'] );
			/* translators: %s: product name. */
			$title = '' !== $row['title'] ? $row['title'] : sprintf( __( 'Yes, add %s to my order', 'cartiva-commerce' ), wp_strip_all_tags( $product->get_name() ) );
			$id    = 'cartiva-main-bump-' . (int) $index;
			?>
			<div class="cartiva-bump<?php echo esc_attr( $bump['in_cart'] ? ' is-added' : '' ); ?>">
				<label class="cartiva-bump__head" for="<?php echo esc_attr( $id ); ?>">
					<input type="checkbox" id="<?php echo esc_attr( $id ); ?>" class="cartiva-main-bump__check" data-index="<?php echo esc_attr( (string) $index ); ?>" <?php checked( $bump['in_cart'] ); ?> />
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
	 * Add or remove a bump (wc-ajax endpoint).
	 */
	public static function ajax_toggle() {
		check_ajax_referer( 'cartiva_commerce_main_bump', 'nonce' );
		$index = isset( $_POST['index'] ) ? absint( wp_unslash( $_POST['index'] ) ) : -1;
		$on    = ! empty( $_POST['on'] ) && '0' !== $_POST['on'];
		if ( ! WC()->cart ) {
			wp_send_json_error( null, 400 );
		}
		$lines = self::lines();
		if ( $on ) {
			$visible = self::visible();
			if ( ! isset( $visible[ $index ] ) ) {
				wp_send_json_error( null, 400 );
			}
			if ( ! isset( $lines[ $index ] ) ) {
				$row = $visible[ $index ]['row'];
				WC()->cart->add_to_cart( (int) $row['product_id'], 1, (int) $row['variation_id'], array(), array( 'cartiva_main_bump' => (int) $index ) );
				Cartiva_Commerce_Offers::clear_added_notices();
			}
		} elseif ( isset( $lines[ $index ] ) ) {
			WC()->cart->remove_cart_item( $lines[ $index ] );
		}
		wp_send_json_success( array( 'on' => $on ) );
	}

	/**
	 * Bump discount.
	 *
	 * @param WC_Cart $cart Cart.
	 */
	public static function prices( $cart ) {
		if ( is_admin() && ! wp_doing_ajax() ) {
			return;
		}
		$rows = self::rows();
		foreach ( $cart->get_cart() as $item ) {
			if ( ! isset( $item['cartiva_main_bump'], $item['data'] ) ) {
				continue;
			}
			$row = isset( $rows[ (int) $item['cartiva_main_bump'] ] ) ? $rows[ (int) $item['cartiva_main_bump'] ] : null;
			if ( ! $row || empty( $row['discount'] ) || (int) $row['product_id'] !== (int) $item['product_id'] ) {
				continue;
			}
			$fresh = wc_get_product( $item['variation_id'] ? (int) $item['variation_id'] : (int) $item['product_id'] );
			if ( $fresh && '' !== $fresh->get_price() ) {
				$item['data']->set_price( cartiva_commerce_discounted( (float) $fresh->get_price(), (float) $row['discount'] ) );
			}
		}
	}

	/**
	 * Take bumps out when they no longer apply (the product that showed it
	 * was removed, the bump was turned off, or the cart is below the minimum).
	 */
	public static function clean_cart() {
		if ( ! WC()->cart ) {
			return;
		}
		$lines = self::lines();
		if ( ! $lines ) {
			return;
		}
		$rows     = self::rows();
		$others   = array();
		$subtotal = 0.0;
		foreach ( WC()->cart->get_cart() as $item ) {
			if ( ! isset( $item['cartiva_main_bump'] ) ) {
				$others[]  = (int) $item['product_id'];
				$subtotal += (float) ( isset( $item['line_subtotal'] ) ? $item['line_subtotal'] : 0 );
			}
		}
		foreach ( $lines as $index => $key ) {
			$row  = isset( $rows[ $index ] ) ? $rows[ $index ] : null;
			$keep = $row && ! empty( $row['active'] ) && $others
				&& ( ! $row['when_id'] || in_array( (int) $row['when_id'], $others, true ) )
				&& ( (float) $row['min_total'] <= 0 || $subtotal >= (float) $row['min_total'] );
			if ( ! $keep ) {
				WC()->cart->remove_cart_item( $key );
			}
		}
	}

	/**
	 * Mark bump lines on the order (reports and the order box).
	 *
	 * @param WC_Order_Item_Product $item          Order line.
	 * @param string                $cart_item_key Cart key.
	 * @param array                 $values        Cart item.
	 */
	public static function tag_line_item( $item, $cart_item_key, $values ) {
		if ( isset( $values['cartiva_main_bump'] ) ) {
			$item->add_meta_data( '_cartiva_bump', 'main-' . (int) $values['cartiva_main_bump'], true );
		}
	}

	/**
	 * Script and styles on the main checkout.
	 */
	public static function assets() {
		if ( ! function_exists( 'is_checkout' ) || ! is_checkout() || is_wc_endpoint_url() || ! self::on_main_checkout() || ! self::rows() ) {
			return;
		}
		wp_enqueue_style( 'cartiva-commerce-main-bumps', CARTIVA_COMMERCE_URL . 'assets/css/main-bumps.css', array(), CARTIVA_COMMERCE_VERSION );
		wp_enqueue_script( 'cartiva-commerce-main-bumps', CARTIVA_COMMERCE_URL . 'assets/js/main-bumps.js', array( 'jquery' ), CARTIVA_COMMERCE_VERSION, true );
		wp_localize_script(
			'cartiva-commerce-main-bumps',
			'cartivaMainBumps',
			array(
				'url'   => WC_AJAX::get_endpoint( 'cartiva_commerce_main_bump' ),
				'nonce' => wp_create_nonce( 'cartiva_commerce_main_bump' ),
			)
		);
	}
}
