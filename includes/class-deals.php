<?php
/**
 * Deals: coupons that apply themselves, and "buy X, get Y" offers (BOGO)
 * that add the gift product to the cart at a discount or for free.
 *
 * @package Cartiva_Commerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Deals.
 */
class Cartiva_Commerce_Deals {

	/**
	 * Running a sync (prevents loops: adding a gift is an add to cart too).
	 *
	 * @var bool
	 */
	private static $busy = false;

	/**
	 * Applying a coupon automatically (for its message).
	 *
	 * @var bool
	 */
	private static $auto = false;

	/**
	 * Hook up.
	 */
	public static function init() {
		foreach ( array( 'woocommerce_add_to_cart', 'woocommerce_cart_item_removed', 'woocommerce_after_cart_item_quantity_update', 'woocommerce_cart_item_restored' ) as $hook ) {
			add_action( $hook, array( __CLASS__, 'sync' ), 60 );
		}
		add_action( 'woocommerce_cart_loaded_from_session', array( __CLASS__, 'sync' ), 30 );
		add_action( 'woocommerce_checkout_update_order_review', array( __CLASS__, 'sync' ), 30 );
		add_action( 'woocommerce_before_calculate_totals', array( __CLASS__, 'gift_prices' ), 30 );
		add_filter( 'woocommerce_cart_item_remove_link', array( __CLASS__, 'gift_remove_link' ), 10, 2 );
		add_filter( 'woocommerce_cart_item_quantity', array( __CLASS__, 'gift_quantity_field' ), 10, 3 );
		add_filter( 'woocommerce_cart_item_name', array( __CLASS__, 'gift_name' ), 10, 2 );
		add_filter( 'woocommerce_store_api_product_quantity_editable', array( __CLASS__, 'gift_editable' ), 10, 3 );
		add_action( 'woocommerce_checkout_create_order_line_item', array( __CLASS__, 'tag_line_item' ), 10, 3 );
		add_action( 'woocommerce_removed_coupon', array( __CLASS__, 'coupon_removed' ) );
		add_filter( 'woocommerce_coupon_message', array( __CLASS__, 'coupon_message' ), 10, 3 );
		add_action( 'woocommerce_coupon_options', array( __CLASS__, 'coupon_option' ), 10, 2 );
		add_action( 'woocommerce_coupon_options_save', array( __CLASS__, 'coupon_option_save' ), 10, 2 );
	}

	/**
	 * Deal rules from settings, with defaults.
	 *
	 * @return array[] Each: active, product_id, variation_id, buy_qty, gift_product_id, gift_variation_id, gift_qty, discount, repeat.
	 */
	public static function rules() {
		$rules = array();
		foreach ( (array) cartiva_commerce_setting( 'deals' ) as $i => $rule ) {
			if ( is_array( $rule ) ) {
				$rules[ (int) $i ] = wp_parse_args(
					$rule,
					array(
						'active'            => true,
						'product_id'        => 0,
						'variation_id'      => 0,
						'buy_qty'           => 1,
						'gift_product_id'   => 0,
						'gift_variation_id' => 0,
						'gift_qty'          => 1,
						'discount'          => 100,
						'repeat'            => true,
					)
				);
			}
		}
		return $rules;
	}

	/**
	 * How many gifts a rule gives for a bought quantity.
	 *
	 * @param array $rule   Rule.
	 * @param int   $bought Quantity of the qualifying product (gifts not counted).
	 * @return int
	 */
	public static function gift_quantity( $rule, $bought ) {
		$need = max( 1, (int) $rule['buy_qty'] );
		$give = max( 1, (int) $rule['gift_qty'] );
		if ( $bought < $need ) {
			return 0;
		}
		return min( 99, ! empty( $rule['repeat'] ) ? intdiv( (int) $bought, $need ) * $give : $give );
	}

	/**
	 * Does a cart line count toward a rule?
	 *
	 * @param array $line Cart line.
	 * @param array $rule Rule.
	 * @return bool
	 */
	public static function line_matches( $line, $rule ) {
		if ( ! empty( $line['cartiva_gift'] ) || (int) $line['product_id'] !== (int) $rule['product_id'] ) {
			return false;
		}
		return ! $rule['variation_id'] || (int) $line['variation_id'] === (int) $rule['variation_id'];
	}

	/**
	 * Gift lines in the cart: rule index => cart key.
	 *
	 * @return array
	 */
	public static function gift_lines() {
		$lines = array();
		foreach ( WC()->cart ? WC()->cart->get_cart() : array() as $key => $line ) {
			if ( isset( $line['cartiva_gift']['rule'] ) ) {
				$lines[ (int) $line['cartiva_gift']['rule'] ] = (string) $key;
			}
		}
		return $lines;
	}

	/**
	 * Bring gifts and automatic coupons in line with the cart.
	 */
	public static function sync() {
		if ( self::$busy || ! function_exists( 'WC' ) || ! WC()->cart ) {
			return;
		}
		self::$busy = true;
		self::sync_gifts();
		self::apply_coupons();
		self::$busy = false;
	}

	/**
	 * Add, change or remove gift lines.
	 */
	public static function sync_gifts() {
		$rules = self::rules();
		$lines = self::gift_lines();
		$cart  = WC()->cart->get_cart();
		foreach ( $lines as $index => $key ) {
			if ( ! isset( $rules[ $index ] ) || empty( $rules[ $index ]['active'] ) ) {
				WC()->cart->remove_cart_item( $key );
				unset( $lines[ $index ] );
			}
		}
		foreach ( $rules as $index => $rule ) {
			if ( empty( $rule['active'] ) || ! $rule['product_id'] || ! $rule['gift_product_id'] ) {
				continue;
			}
			$bought = 0;
			foreach ( $cart as $line ) {
				if ( self::line_matches( $line, $rule ) ) {
					$bought += (int) $line['quantity'];
				}
			}
			$want = self::gift_quantity( $rule, $bought );
			$key  = isset( $lines[ $index ] ) ? $lines[ $index ] : '';
			if ( 0 === $want ) {
				if ( '' !== $key ) {
					WC()->cart->remove_cart_item( $key );
				}
				continue;
			}
			if ( '' !== $key ) {
				$line = WC()->cart->get_cart_item( $key );
				if ( (int) $line['quantity'] !== $want ) {
					WC()->cart->set_quantity( $key, $want, false );
				}
				continue;
			}
			$gift = wc_get_product( $rule['gift_variation_id'] ? (int) $rule['gift_variation_id'] : (int) $rule['gift_product_id'] );
			if ( ! $gift || ! $gift->is_purchasable() || ! $gift->has_enough_stock( $want ) ) {
				continue;
			}
			WC()->cart->add_to_cart(
				(int) $rule['gift_product_id'],
				$want,
				(int) $rule['gift_variation_id'],
				$gift->is_type( 'variation' ) ? $gift->get_variation_attributes() : array(),
				array( 'cartiva_gift' => array( 'rule' => (int) $index ) )
			);
		}
	}

	/**
	 * Price of a gift after its discount (100% = free).
	 *
	 * @param float $price    Price.
	 * @param float $discount Discount 0-100.
	 * @return float
	 */
	public static function gift_price( $price, $discount ) {
		$discount = max( 0, min( 100, (float) $discount ) );
		return round( (float) $price * ( 100 - $discount ) / 100, function_exists( 'wc_get_price_decimals' ) ? wc_get_price_decimals() : 2 );
	}

	/**
	 * Set gift prices.
	 *
	 * @param WC_Cart $cart Cart.
	 */
	public static function gift_prices( $cart ) {
		if ( is_admin() && ! wp_doing_ajax() ) {
			return;
		}
		$rules = self::rules();
		foreach ( $cart->get_cart() as $line ) {
			if ( ! isset( $line['cartiva_gift']['rule'], $line['data'] ) ) {
				continue;
			}
			$index = (int) $line['cartiva_gift']['rule'];
			if ( ! isset( $rules[ $index ] ) || (int) $rules[ $index ]['gift_product_id'] !== (int) $line['product_id'] ) {
				continue;
			}
			$fresh = wc_get_product( $line['variation_id'] ? (int) $line['variation_id'] : (int) $line['product_id'] );
			if ( $fresh ) {
				$line['data']->set_price( self::gift_price( (float) $fresh->get_price(), (float) $rules[ $index ]['discount'] ) );
			}
		}
	}

	/**
	 * Gifts cannot be removed on their own (they follow their product).
	 *
	 * @param string $link Remove link.
	 * @param string $key  Cart key.
	 * @return string
	 */
	public static function gift_remove_link( $link, $key ) {
		$line = WC()->cart ? WC()->cart->get_cart_item( $key ) : array();
		return ! empty( $line['cartiva_gift'] ) ? '' : $link;
	}

	/**
	 * Fixed quantity for gifts on the classic cart page.
	 *
	 * @param string $field Quantity field.
	 * @param string $key   Cart key.
	 * @param array  $line  Cart line.
	 * @return string
	 */
	public static function gift_quantity_field( $field, $key, $line ) {
		return ! empty( $line['cartiva_gift'] ) ? '<span class="cartiva-gift-qty">' . esc_html( (string) (int) $line['quantity'] ) . '</span>' : $field;
	}

	/**
	 * "Gift" label on the classic cart page.
	 *
	 * @param string $name Item name.
	 * @param array  $line Cart line.
	 * @return string
	 */
	public static function gift_name( $name, $line ) {
		return ! empty( $line['cartiva_gift'] ) ? $name . ' <span class="cartiva-gift-badge">' . esc_html__( 'Gift', 'cartiva-commerce' ) . '</span>' : $name;
	}

	/**
	 * Fixed quantity for gifts in the Cart block.
	 *
	 * @param bool       $editable Editable.
	 * @param WC_Product $product  Product.
	 * @param array      $line     Cart line.
	 * @return bool
	 */
	public static function gift_editable( $editable, $product, $line = array() ) {
		return ! empty( $line['cartiva_gift'] ) ? false : $editable;
	}

	/**
	 * Mark gift lines on orders (for reports).
	 *
	 * @param WC_Order_Item_Product $item   Order line.
	 * @param string                $key    Cart key.
	 * @param array                 $values Cart line.
	 */
	public static function tag_line_item( $item, $key, $values ) {
		if ( isset( $values['cartiva_gift']['rule'] ) ) {
			$item->add_meta_data( '_cartiva_gift', (int) $values['cartiva_gift']['rule'], true );
		}
	}

	/**
	 * Codes of coupons that apply themselves (cached).
	 *
	 * @return string[]
	 */
	public static function auto_coupons() {
		$codes = get_transient( 'cartiva_auto_coupons' );
		if ( is_array( $codes ) ) {
			return $codes;
		}
		$codes = array();
		$posts = get_posts(
			array(
				'post_type'      => 'shop_coupon',
				'post_status'    => 'publish',
				'posts_per_page' => 20,
				'meta_key'       => '_cartiva_auto_apply', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Cached list.
				'meta_value'     => 'yes', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- Cached list.
			)
		);
		foreach ( $posts as $post ) {
			$codes[] = wc_format_coupon_code( $post->post_title );
		}
		set_transient( 'cartiva_auto_coupons', $codes, DAY_IN_SECONDS );
		return $codes;
	}

	/**
	 * Apply automatic coupons whose conditions the cart meets, unless the
	 * shopper removed them.
	 */
	public static function apply_coupons() {
		$cart = WC()->cart;
		if ( $cart->is_empty() || ! wc_coupons_enabled() ) {
			return;
		}
		$removed = WC()->session ? (array) WC()->session->get( 'cartiva_removed_coupons' ) : array();
		foreach ( self::auto_coupons() as $code ) {
			if ( $cart->has_discount( $code ) || in_array( $code, $removed, true ) ) {
				continue;
			}
			$coupon = new WC_Coupon( $code );
			if ( ! $coupon->get_id() ) {
				continue;
			}
			$discounts = new WC_Discounts( $cart );
			if ( true !== $discounts->is_coupon_valid( $coupon ) ) {
				continue;
			}
			self::$auto = true;
			$cart->apply_coupon( $code );
			self::$auto = false;
		}
	}

	/**
	 * A clearer message for coupons that applied themselves.
	 *
	 * @param string    $message Message.
	 * @param int       $code    Message code.
	 * @param WC_Coupon $coupon  Coupon.
	 * @return string
	 */
	public static function coupon_message( $message, $code, $coupon ) {
		if ( self::$auto && WC_Coupon::WC_COUPON_SUCCESS === $code ) {
			/* translators: %s: coupon code. */
			return sprintf( __( 'Good news: the discount %s was added to your cart automatically.', 'cartiva-commerce' ), strtoupper( $coupon->get_code() ) );
		}
		return $message;
	}

	/**
	 * Remember coupons the shopper removed, so they are not added again.
	 *
	 * Coupons that WooCommerce removes because the cart no longer qualifies
	 * are not remembered: they come back when the cart qualifies again.
	 *
	 * @param string $code Coupon code.
	 */
	public static function coupon_removed( $code ) {
		// phpcs:disable WordPress.Security.NonceVerification -- Only reads which action is running; WooCommerce checks its own nonces.
		$by_shopper = isset( $_REQUEST['remove_coupon'] )
			|| ( isset( $_REQUEST['wc-ajax'] ) && 'remove_coupon' === sanitize_key( wp_unslash( $_REQUEST['wc-ajax'] ) ) )
			|| ( isset( $_SERVER['REQUEST_URI'] ) && false !== strpos( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ), 'remove-coupon' ) );
		// phpcs:enable
		$code = wc_format_coupon_code( $code );
		if ( ! $by_shopper || ! WC()->session || ! in_array( $code, self::auto_coupons(), true ) ) {
			return;
		}
		$removed   = (array) WC()->session->get( 'cartiva_removed_coupons' );
		$removed[] = $code;
		WC()->session->set( 'cartiva_removed_coupons', array_values( array_unique( array_filter( $removed ) ) ) );
	}

	/**
	 * "Apply automatically" checkbox on the coupon screen.
	 *
	 * @param int       $coupon_id Coupon ID.
	 * @param WC_Coupon $coupon    Coupon.
	 */
	public static function coupon_option( $coupon_id, $coupon ) {
		woocommerce_wp_checkbox(
			array(
				'id'          => '_cartiva_auto_apply',
				'label'       => __( 'Apply automatically', 'cartiva-commerce' ),
				'description' => __( 'Add this coupon to the cart by itself as soon as the cart meets its usage restrictions (for example a minimum spend or certain products). Shoppers can still remove it.', 'cartiva-commerce' ),
				'value'       => 'yes' === get_post_meta( $coupon_id, '_cartiva_auto_apply', true ) ? 'yes' : 'no',
			)
		);
	}

	/**
	 * Save the checkbox.
	 *
	 * @param int       $post_id Coupon ID.
	 * @param WC_Coupon $coupon  Coupon.
	 */
	public static function coupon_option_save( $post_id, $coupon ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- WooCommerce checks the coupon screen nonce before this action.
		update_post_meta( $post_id, '_cartiva_auto_apply', isset( $_POST['_cartiva_auto_apply'] ) ? 'yes' : 'no' );
		delete_transient( 'cartiva_auto_coupons' );
	}
}
