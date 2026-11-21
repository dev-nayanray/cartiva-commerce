<?php
/**
 * Slide-out cart, free shipping progress bar and recommendations in the cart.
 *
 * The cart drawer is printed once in the footer. Its contents are a
 * WooCommerce cart fragment, so every add to cart, quantity change and
 * removal refreshes it without reloading the page.
 *
 * @package Cartiva_Commerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Cart.
 */
class Cartiva_Commerce_Cart {

	/**
	 * Hook up.
	 */
	public static function init() {
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_action( 'wp_footer', array( __CLASS__, 'drawer' ) );
		add_filter( 'woocommerce_add_to_cart_fragments', array( __CLASS__, 'fragments' ) );
		add_action( 'wc_ajax_cartiva_commerce_cart_qty', array( __CLASS__, 'ajax_qty' ) );
		add_action( 'woocommerce_add_to_cart', array( __CLASS__, 'remember_added' ), 99 );
		add_action( 'woocommerce_before_cart', array( __CLASS__, 'page_bar' ) );
		add_action( 'woocommerce_before_checkout_form', array( __CLASS__, 'page_bar' ), 5 );
	}

	/**
	 * Is the drawer used on this page?
	 *
	 * @return bool
	 */
	public static function enabled() {
		if ( is_admin() || ! cartiva_commerce_setting( 'side_cart' ) || ! function_exists( 'WC' ) || ! WC()->cart ) {
			return false;
		}
		// Not on the cart and checkout pages or order forms, where the cart is already on screen.
		if ( is_cart() || is_checkout() || is_singular( CARTIVA_COMMERCE_LANDING ) ) {
			return false;
		}
		/**
		 * Filters whether the Cartiva Commerce cart drawer loads on this page.
		 *
		 * @param bool $enabled Enabled.
		 */
		return (bool) apply_filters( 'cartiva_commerce_side_cart_enabled', true );
	}

	/**
	 * Assets.
	 */
	public static function assets() {
		if ( ! is_admin() && function_exists( 'is_cart' ) && ( is_cart() || is_checkout() ) ) {
			wp_enqueue_style( 'cartiva-commerce-cart', CARTIVA_COMMERCE_URL . 'assets/css/cart.css', array(), CARTIVA_COMMERCE_VERSION );
		}
		if ( ! self::enabled() ) {
			return;
		}
		wp_enqueue_style( 'cartiva-commerce-cart', CARTIVA_COMMERCE_URL . 'assets/css/cart.css', array(), CARTIVA_COMMERCE_VERSION );
		wp_enqueue_script( 'cartiva-commerce-cart', CARTIVA_COMMERCE_URL . 'assets/js/cart.js', array( 'jquery' ), CARTIVA_COMMERCE_VERSION, array( 'in_footer' => true ) );
		$open = false;
		if ( WC()->session && WC()->session->get( 'cartiva_open_cart' ) ) {
			$open = true;
			WC()->session->set( 'cartiva_open_cart', null );
		}
		wp_localize_script(
			'cartiva-commerce-cart',
			'cartivaCart',
			array(
				'qty'       => WC_AJAX::get_endpoint( 'cartiva_commerce_cart_qty' ),
				'add'       => WC_AJAX::get_endpoint( 'add_to_cart' ),
				'refresh'   => WC_AJAX::get_endpoint( 'get_refreshed_fragments' ),
				'nonce'     => wp_create_nonce( 'cartiva_commerce_cart' ),
				'openOnAdd' => (bool) cartiva_commerce_setting( 'side_cart_open' ),
				'openNow'   => $open && cartiva_commerce_setting( 'side_cart_open' ),
				'links'     => (bool) cartiva_commerce_setting( 'side_cart_link' ),
				'cartUrl'   => wc_get_cart_url(),
				'error'     => __( 'Could not update your cart. Please try again.', 'cartiva-commerce' ),
			)
		);
	}

	/**
	 * Open the drawer after a normal (non-AJAX) add to cart, on the next page.
	 */
	public static function remember_added() {
		if ( ! wp_doing_ajax() && WC()->session && cartiva_commerce_setting( 'side_cart' ) ) {
			WC()->session->set( 'cartiva_open_cart', 1 );
		}
	}

	/**
	 * The free shipping threshold for the shopper: the setting, or the
	 * minimum amount of the Free shipping method of their shipping zone.
	 *
	 * @return float 0 when there is none.
	 */
	public static function free_shipping_min() {
		$manual = (float) cartiva_commerce_setting( 'ship_min' );
		if ( $manual > 0 ) {
			return $manual;
		}
		if ( ! WC()->cart || ! class_exists( 'WC_Shipping_Zones' ) ) {
			return 0.0;
		}
		$packages = WC()->cart->get_shipping_packages();
		$zone     = WC_Shipping_Zones::get_zone_matching_package( $packages ? reset( $packages ) : array() );
		$min      = 0.0;
		foreach ( $zone ? $zone->get_shipping_methods( true ) : array() as $method ) {
			if ( 'free_shipping' === $method->id && in_array( $method->get_option( 'requires' ), array( 'min_amount', 'either' ), true ) ) {
				$amount = (float) $method->get_option( 'min_amount' );
				if ( $amount > 0 && ( 0.0 === $min || $amount < $min ) ) {
					$min = $amount;
				}
			}
		}
		return $min;
	}

	/**
	 * Progress toward free shipping.
	 *
	 * @param float $total Cart amount that counts.
	 * @param float $min   Threshold.
	 * @return array{left:float,percent:int,done:bool}|null Null without a threshold.
	 */
	public static function progress( $total, $min ) {
		if ( $min <= 0 ) {
			return null;
		}
		$left = max( 0.0, round( $min - $total, 2 ) );
		return array(
			'left'    => $left,
			'percent' => (int) min( 100, max( 0, floor( $total / $min * 100 ) ) ),
			'done'    => $left <= 0,
		);
	}

	/**
	 * Free shipping bar.
	 *
	 * @return string
	 */
	public static function shipping_bar() {
		if ( ! cartiva_commerce_setting( 'ship_bar' ) || ! WC()->cart || WC()->cart->is_empty() || ! WC()->cart->needs_shipping() ) {
			return '';
		}
		$total = (float) WC()->cart->get_displayed_subtotal();
		if ( 'no' === get_option( 'woocommerce_free_shipping_ignore_discounts', 'no' ) ) {
			$total -= (float) WC()->cart->get_discount_total();
			if ( WC()->cart->display_prices_including_tax() ) {
				$total -= (float) WC()->cart->get_discount_tax();
			}
		}
		$progress = self::progress( $total, self::free_shipping_min() );
		if ( ! $progress ) {
			return '';
		}
		$text = $progress['done']
			? esc_html__( 'You get free shipping!', 'cartiva-commerce' )
			/* translators: %s: amount left, for example ৳ 250. */
			: sprintf( esc_html__( 'Add %s more for free shipping', 'cartiva-commerce' ), '<strong>' . wp_kses_post( wc_price( $progress['left'] ) ) . '</strong>' );
		return '<div class="cartiva-ship' . ( $progress['done'] ? ' is-done' : '' ) . '"><p class="cartiva-ship__text">' . $text . '</p>'
			. '<div class="cartiva-ship__track" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="' . esc_attr( (string) $progress['percent'] ) . '" aria-label="' . esc_attr__( 'Progress toward free shipping', 'cartiva-commerce' ) . '"><span style="width:' . esc_attr( (string) $progress['percent'] ) . '%"></span></div></div>';
	}

	/**
	 * Free shipping bar on the cart and checkout pages.
	 */
	public static function page_bar() {
		if ( cartiva_commerce_current_landing_id() ) {
			return;
		}
		echo self::shipping_bar(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in shipping_bar().
	}

	/**
	 * Products to suggest in the cart: cross-sells of the cart items.
	 *
	 * @return WC_Product[]
	 */
	public static function recommendations() {
		if ( ! cartiva_commerce_setting( 'cart_recs' ) || ! WC()->cart ) {
			return array();
		}
		$in_cart = array();
		$ids     = array();
		foreach ( WC()->cart->get_cart() as $line ) {
			$in_cart[] = (int) $line['product_id'];
			if ( isset( $line['data'] ) && $line['data'] instanceof WC_Product ) {
				$parent = $line['variation_id'] ? wc_get_product( (int) $line['product_id'] ) : $line['data'];
				$ids    = array_merge( $ids, $parent ? $parent->get_cross_sell_ids() : array() );
			}
		}
		$list = array();
		foreach ( array_unique( array_map( 'intval', $ids ) ) as $id ) {
			if ( in_array( $id, $in_cart, true ) ) {
				continue;
			}
			$product = wc_get_product( $id );
			if ( $product && $product->is_visible() && $product->is_purchasable() && $product->is_in_stock() ) {
				$list[] = $product;
			}
			if ( count( $list ) >= 3 ) {
				break;
			}
		}
		/**
		 * Filters the products suggested in the cart drawer.
		 *
		 * @param WC_Product[] $list Products.
		 */
		return (array) apply_filters( 'cartiva_commerce_cart_recommendations', $list );
	}

	/**
	 * Number of items in the cart.
	 *
	 * @return string
	 */
	public static function count_html() {
		$count = WC()->cart ? (int) WC()->cart->get_cart_contents_count() : 0;
		return '<span class="cartiva-sc-count" data-count="' . esc_attr( (string) $count ) . '">' . esc_html( number_format_i18n( $count ) ) . '</span>';
	}

	/**
	 * Drawer contents (also the cart fragment).
	 *
	 * @return string
	 */
	public static function body() {
		$cart = WC()->cart;
		$html = '<div class="cartiva-sc__body">';
		if ( ! $cart || $cart->is_empty() ) {
			$html .= '<div class="cartiva-sc__empty"><p>' . esc_html__( 'Your cart is empty.', 'cartiva-commerce' ) . '</p>';
			$html .= '<a class="cartiva-sc__button" href="' . esc_url( wc_get_page_permalink( 'shop' ) ) . '">' . esc_html__( 'Start shopping', 'cartiva-commerce' ) . '</a></div>';
			return $html . '</div>';
		}
		$html .= self::shipping_bar();
		$html .= '<ul class="cartiva-sc__items">';
		foreach ( $cart->get_cart() as $key => $line ) {
			$product = isset( $line['data'] ) ? $line['data'] : null;
			if ( ! $product instanceof WC_Product || ! $product->exists() || $line['quantity'] < 1 ) {
				continue;
			}
			$gift  = ! empty( $line['cartiva_gift'] );
			$link  = $product->is_visible() ? $product->get_permalink( $line ) : '';
			$name  = wp_strip_all_tags( $product->get_name() );
			$html .= '<li class="cartiva-sc__item' . ( $gift ? ' is-gift' : '' ) . '" data-key="' . esc_attr( (string) $key ) . '">';
			$html .= '<span class="cartiva-sc__thumb">' . $product->get_image( 'woocommerce_gallery_thumbnail', array( 'alt' => '' ) ) . '</span>';
			$html .= '<span class="cartiva-sc__info">';
			$html .= $link ? '<a class="cartiva-sc__name" href="' . esc_url( $link ) . '">' . esc_html( $name ) . '</a>' : '<span class="cartiva-sc__name">' . esc_html( $name ) . '</span>';
			$meta  = wc_get_formatted_cart_item_data( $line, true );
			if ( '' !== trim( $meta ) ) {
				$html .= '<span class="cartiva-sc__meta">' . esc_html( trim( $meta ) ) . '</span>';
			}
			if ( $gift ) {
				$html .= '<span class="cartiva-sc__gift">' . esc_html__( 'Gift with your order', 'cartiva-commerce' ) . '</span>';
			}
			$html .= '<span class="cartiva-sc__price">' . wp_kses_post( $cart->get_product_subtotal( $product, (int) $line['quantity'] ) ) . '</span>';
			$html .= '</span>';
			if ( $gift ) {
				$html .= '<span class="cartiva-sc__fixed">&times; ' . esc_html( (string) (int) $line['quantity'] ) . '</span>';
			} else {
				$max   = $product->get_max_purchase_quantity();
				$html .= '<span class="cartiva-sc__qty">';
				$html .= '<button type="button" class="cartiva-sc__step" data-step="-1" aria-label="' . esc_attr( sprintf( /* translators: %s: product name. */ __( 'Decrease quantity of %s', 'cartiva-commerce' ), $name ) ) . '">&minus;</button>';
				$html .= '<span class="cartiva-sc__num" aria-live="polite">' . esc_html( (string) (int) $line['quantity'] ) . '</span>';
				$html .= '<button type="button" class="cartiva-sc__step" data-step="1" data-max="' . esc_attr( (string) ( $max > 0 ? $max : 999 ) ) . '" aria-label="' . esc_attr( sprintf( /* translators: %s: product name. */ __( 'Increase quantity of %s', 'cartiva-commerce' ), $name ) ) . '"' . ( $product->is_sold_individually() ? ' disabled' : '' ) . '>+</button>';
				$html .= '</span>';
				$html .= '<button type="button" class="cartiva-sc__remove" aria-label="' . esc_attr( sprintf( /* translators: %s: product name. */ __( 'Remove %s', 'cartiva-commerce' ), $name ) ) . '">&times;</button>';
			}
			$html .= '</li>';
		}
		$html .= '</ul>';

		$recs = self::recommendations();
		if ( $recs ) {
			$html .= '<div class="cartiva-sc__recs"><p class="cartiva-sc__recs-title">' . esc_html__( 'You may also like', 'cartiva-commerce' ) . '</p><ul>';
			foreach ( $recs as $product ) {
				$html .= '<li><span class="cartiva-sc__thumb">' . $product->get_image( 'woocommerce_gallery_thumbnail', array( 'alt' => '' ) ) . '</span>';
				$html .= '<span class="cartiva-sc__info"><a class="cartiva-sc__name" href="' . esc_url( $product->get_permalink() ) . '">' . esc_html( wp_strip_all_tags( $product->get_name() ) ) . '</a><span class="cartiva-sc__price">' . wp_kses_post( $product->get_price_html() ) . '</span></span>';
				if ( $product->is_type( 'simple' ) && ! $product->is_sold_individually() ) {
					$html .= '<button type="button" class="cartiva-sc__add" data-product="' . esc_attr( (string) $product->get_id() ) . '">' . esc_html__( 'Add', 'cartiva-commerce' ) . '</button>';
				} else {
					$html .= '<a class="cartiva-sc__add" href="' . esc_url( $product->get_permalink() ) . '">' . esc_html__( 'Choose', 'cartiva-commerce' ) . '</a>';
				}
				$html .= '</li>';
			}
			$html .= '</ul></div>';
		}

		$html .= '<div class="cartiva-sc__foot">';
		foreach ( $cart->get_coupons() as $code => $coupon ) {
			$html .= '<p class="cartiva-sc__row"><span>' . esc_html( sprintf( /* translators: %s: coupon code. */ __( 'Coupon: %s', 'cartiva-commerce' ), strtoupper( $code ) ) ) . '</span><span>&minus;' . wp_kses_post( wc_price( $cart->get_coupon_discount_amount( $code, $cart->display_cart_ex_tax ) ) ) . '</span></p>';
		}
		$html .= '<p class="cartiva-sc__row cartiva-sc__subtotal"><span>' . esc_html__( 'Subtotal', 'cartiva-commerce' ) . '</span><span>' . wp_kses_post( $cart->get_cart_subtotal() ) . '</span></p>';
		$html .= '<a class="cartiva-sc__button" href="' . esc_url( wc_get_checkout_url() ) . '">' . esc_html__( 'Checkout', 'cartiva-commerce' ) . '</a>';
		$html .= '<a class="cartiva-sc__link" href="' . esc_url( wc_get_cart_url() ) . '">' . esc_html__( 'View cart', 'cartiva-commerce' ) . '</a>';
		$html .= '</div></div>';
		return $html;
	}

	/**
	 * Fragments for WooCommerce's cart refresh.
	 *
	 * @param array $fragments Fragments.
	 * @return array
	 */
	public static function fragments( $fragments ) {
		if ( ! cartiva_commerce_setting( 'side_cart' ) ) {
			return $fragments;
		}
		$fragments['div.cartiva-sc__body'] = self::body();
		$fragments['span.cartiva-sc-count'] = self::count_html();
		return $fragments;
	}

	/**
	 * The drawer and the floating button.
	 */
	public static function drawer() {
		if ( ! self::enabled() ) {
			return;
		}
		?>
		<div class="cartiva-sc" id="cartiva-side-cart" hidden>
			<div class="cartiva-sc__overlay" data-cartiva-close></div>
			<div class="cartiva-sc__panel" role="dialog" aria-modal="true" aria-labelledby="cartiva-sc-title" tabindex="-1">
				<div class="cartiva-sc__head">
					<h2 id="cartiva-sc-title" class="cartiva-sc__title"><?php esc_html_e( 'Your cart', 'cartiva-commerce' ); ?> <?php echo self::count_html(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in count_html(). ?></h2>
					<button type="button" class="cartiva-sc__close" data-cartiva-close aria-label="<?php esc_attr_e( 'Close cart', 'cartiva-commerce' ); ?>">&times;</button>
				</div>
				<?php echo self::body(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in body(). ?>
			</div>
		</div>
		<?php if ( cartiva_commerce_setting( 'side_cart_btn' ) ) : ?>
			<button type="button" class="cartiva-sc-toggle" aria-controls="cartiva-side-cart" aria-label="<?php esc_attr_e( 'Open cart', 'cartiva-commerce' ); ?>">
				<svg width="22" height="22" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" d="M6 7h12l-1 13H7L6 7Zm3 0a3 3 0 0 1 6 0"/></svg>
				<?php echo self::count_html(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in count_html(). ?>
			</button>
			<?php
		endif;
	}

	/**
	 * Change the quantity of a cart line, or remove it (wc-ajax endpoint).
	 * Replies with WooCommerce's refreshed fragments.
	 */
	public static function ajax_qty() {
		check_ajax_referer( 'cartiva_commerce_cart', 'nonce' );
		$key  = isset( $_POST['key'] ) ? sanitize_key( wp_unslash( $_POST['key'] ) ) : '';
		$qty  = isset( $_POST['qty'] ) ? absint( wp_unslash( $_POST['qty'] ) ) : 0;
		$line = ( '' !== $key && WC()->cart ) ? WC()->cart->get_cart_item( $key ) : array();
		if ( empty( $line ) || ! empty( $line['cartiva_gift'] ) ) {
			wp_send_json_error( null, 400 );
		}
		if ( 0 === $qty ) {
			WC()->cart->remove_cart_item( $key );
		} else {
			$product = $line['data'];
			$max     = $product instanceof WC_Product ? $product->get_max_purchase_quantity() : -1;
			WC()->cart->set_quantity( $key, $max > 0 ? min( $qty, $max ) : $qty, true );
		}
		WC_AJAX::get_refreshed_fragments();
	}
}
