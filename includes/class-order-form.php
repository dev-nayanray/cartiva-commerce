<?php
/**
 * Landing order form block: renders a one-page checkout for the landing
 * page's product, and the quantity changer.
 *
 * @package Cartiva_Commerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Order form.
 */
class Cartiva_Commerce_Order_Form {

	/**
	 * Hook up.
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'register' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_action( 'wc_ajax_cartiva_commerce_set_qty', array( __CLASS__, 'ajax_set_qty' ) );
	}

	/**
	 * Register the block.
	 */
	public static function register() {
		register_block_type(
			CARTIVA_COMMERCE_DIR . 'blocks/order-form',
			array( 'render_callback' => array( __CLASS__, 'render' ) )
		);
	}

	/**
	 * Front-end assets on landing pages.
	 */
	public static function assets() {
		if ( ! is_singular( CARTIVA_COMMERCE_LANDING ) ) {
			return;
		}
		wp_enqueue_style( 'cartiva-commerce-order-form', CARTIVA_COMMERCE_URL . 'assets/css/order-form.css', array(), CARTIVA_COMMERCE_VERSION );
		wp_enqueue_script( 'cartiva-commerce-order-form', CARTIVA_COMMERCE_URL . 'assets/js/order-form.js', array( 'jquery' ), CARTIVA_COMMERCE_VERSION, array( 'in_footer' => true ) );
		wp_localize_script(
			'cartiva-commerce-order-form',
			'cartivaOrderForm',
			array(
				'url'      => WC_AJAX::get_endpoint( 'cartiva_commerce_set_qty' ),
				'select'   => WC_AJAX::get_endpoint( 'cartiva_commerce_select' ),
				'bump'     => WC_AJAX::get_endpoint( 'cartiva_commerce_bump' ),
				'nonce'    => wp_create_nonce( 'cartiva_commerce_form' ),
				'error'    => __( 'Could not update your order. Please try again.', 'cartiva-commerce' ),
				'details'  => __( 'Your details', 'cartiva-commerce' ),
				'payment'  => __( 'Payment', 'cartiva-commerce' ),
				'continue' => __( 'Continue', 'cartiva-commerce' ),
				'back'     => __( 'Back to your details', 'cartiva-commerce' ),
				'required' => __( 'Please fill in the highlighted fields.', 'cartiva-commerce' ),
			)
		);
	}

	/**
	 * Render the form.
	 *
	 * @param array $attributes Block attributes.
	 * @return string
	 */
	public static function render( $attributes ) {
		$landing_id = cartiva_commerce_current_landing_id();
		if ( ! $landing_id ) {
			return '';
		}
		$config  = cartiva_commerce_landing_config( $landing_id );
		$offers  = cartiva_commerce_landing_offers( $config );
		$layout  = in_array( $config['layout'], array( 'one', 'two', 'steps' ), true ) ? $config['layout'] : 'one';
		$wrapper = get_block_wrapper_attributes(
			array(
				'class' => 'cartiva-order-form woocommerce cartiva-order-form--' . $layout,
				'id'    => ! empty( $attributes['anchor'] ) ? $attributes['anchor'] : 'order',
			)
		);

		$products = array();
		foreach ( $offers as $i => $row ) {
			$product = wc_get_product( $row['variation_id'] ? (int) $row['variation_id'] : (int) $row['product_id'] );
			if ( $product && $product->is_purchasable() && $product->is_in_stock() ) {
				$products[ $i ] = $product;
			}
		}
		if ( empty( $offers ) ) {
			if ( current_user_can( 'edit_post', $landing_id ) ) {
				return '<div ' . $wrapper . '><p class="cartiva-order-form__notice">' . esc_html__( 'Add the product to sell in the "Landing page: product and order form" box when editing this page.', 'cartiva-commerce' ) . '</p></div>';
			}
			return '';
		}
		if ( empty( $products ) ) {
			return '<div ' . $wrapper . '><p class="cartiva-order-form__notice">' . esc_html__( 'Sorry, this product is out of stock right now.', 'cartiva-commerce' ) . '</p></div>';
		}

		if ( 'close' === Cartiva_Commerce_Countdown::ending( $landing_id ) ) {
			return '<div ' . $wrapper . '><p class="cartiva-order-form__notice cartiva-order-form__ended">' . esc_html( Cartiva_Commerce_Countdown::ended_message( $landing_id ) ) . '</p></div>';
		}

		$html = '';
		if ( Cartiva_Commerce_Countdown::settings( $landing_id )['form'] ) {
			$html .= Cartiva_Commerce_Countdown::html( $landing_id, 'form' );
		}
		if ( ! empty( $attributes['heading'] ) ) {
			$html .= '<h2 class="cartiva-order-form__heading">' . wp_kses_post( $attributes['heading'] ) . '</h2>';
		}
		$html .= 'single' === $config['offer_mode']
			? self::single_summary( $landing_id, $config, reset( $products ) )
			: self::options_list( $landing_id, $config, $offers, $products );

		// WooCommerce's classic checkout, with this landing page's fields.
		$html .= '<div class="cartiva-order-form__checkout">' . WC_Shortcodes::checkout( array() ) . '</div>';

		return '<div ' . $wrapper . ' data-layout="' . esc_attr( $layout ) . '">' . $html . '</div>';
	}

	/**
	 * One product with an optional quantity changer.
	 *
	 * @param int        $landing_id Landing page ID.
	 * @param array      $config     Landing settings.
	 * @param WC_Product $product    Product.
	 * @return string
	 */
	private static function single_summary( $landing_id, $config, $product ) {
		$key  = Cartiva_Commerce_Landing::cart_item_key( $config, $landing_id );
		$item = $key ? WC()->cart->get_cart_item( $key ) : array();
		$qty  = ! empty( $item['quantity'] ) ? (int) $item['quantity'] : (int) $config['quantity'];
		$max  = $product->get_max_purchase_quantity();
		$max  = $max > 0 ? min( 99, $max ) : 99;
		$row  = cartiva_commerce_landing_offers( $config )[0];

		$html  = '<div class="cartiva-order-form__product">';
		$html .= '<div class="cartiva-order-form__image">' . $product->get_image( 'woocommerce_thumbnail', array( 'alt' => '' ) ) . '</div>';
		$html .= '<div class="cartiva-order-form__info"><p class="cartiva-order-form__name">' . esc_html( '' !== $row['label'] ? $row['label'] : wp_strip_all_tags( $product->get_name() ) ) . '</p>';
		$html .= '<p class="cartiva-order-form__price">' . self::price_html( $product, $row, 1 ) . '</p></div>';
		if ( ! empty( $config['qty_editable'] ) && ! $product->is_sold_individually() ) {
			$html .= '<div class="cartiva-order-form__qty" data-landing="' . esc_attr( (string) $landing_id ) . '" data-max="' . esc_attr( (string) $max ) . '">';
			$html .= '<button type="button" class="cartiva-order-form__qty-btn" data-step="-1" aria-label="' . esc_attr__( 'Decrease quantity', 'cartiva-commerce' ) . '">&minus;</button>';
			$html .= '<input type="number" class="cartiva-order-form__qty-input" min="1" max="' . esc_attr( (string) $max ) . '" value="' . esc_attr( (string) max( 1, $qty ) ) . '" aria-label="' . esc_attr__( 'Quantity', 'cartiva-commerce' ) . '" inputmode="numeric" />';
			$html .= '<button type="button" class="cartiva-order-form__qty-btn" data-step="1" aria-label="' . esc_attr__( 'Increase quantity', 'cartiva-commerce' ) . '">+</button>';
			$html .= '</div>';
		}
		return $html . '</div>';
	}

	/**
	 * Price of an option for a quantity, with the regular price struck out
	 * when discounted.
	 *
	 * @param WC_Product $product Product.
	 * @param array      $row     Option.
	 * @param int        $qty     Quantity.
	 * @return string
	 */
	private static function price_html( $product, $row, $qty ) {
		if ( empty( $row['discount'] ) ) {
			return 1 === $qty ? wp_kses_post( $product->get_price_html() ) : wp_kses_post( wc_price( (float) $product->get_price() * $qty ) );
		}
		$regular = (float) $product->get_price() * $qty;
		$price   = cartiva_commerce_discounted( (float) $product->get_price(), (float) $row['discount'] ) * $qty;
		return '<del aria-hidden="true">' . wp_kses_post( wc_price( $regular ) ) . '</del> <ins>' . wp_kses_post( wc_price( $price ) ) . '</ins>';
	}

	/**
	 * Option cards (pick one, or pick several).
	 *
	 * @param int          $landing_id Landing page ID.
	 * @param array        $config     Landing settings.
	 * @param array        $offers     Options.
	 * @param WC_Product[] $products   Available products by option index.
	 * @return string
	 */
	private static function options_list( $landing_id, $config, $offers, $products ) {
		$many     = 'many' === $config['offer_mode'];
		$lines    = Cartiva_Commerce_Offers::landing_lines( $landing_id );
		$selected = array_keys( $lines['offers'] );
		if ( empty( $selected ) ) {
			$selected = cartiva_commerce_default_offer_indexes( $config );
		}
		$type  = $many ? 'checkbox' : 'radio';
		$label = $many ? __( 'Choose your products', 'cartiva-commerce' ) : __( 'Choose your package', 'cartiva-commerce' );
		$html  = '<fieldset class="cartiva-offers" data-landing="' . esc_attr( (string) $landing_id ) . '" data-mode="' . esc_attr( $config['offer_mode'] ) . '">';
		$html .= '<legend class="cartiva-offers__legend">' . esc_html( $label ) . '</legend>';
		foreach ( $products as $i => $product ) {
			$row   = $offers[ $i ];
			$qty   = max( 1, (int) $row['qty'] );
			$on    = in_array( (int) $i, $selected, true );
			$name  = '' !== $row['label'] ? $row['label'] : wp_strip_all_tags( $product->get_name() );
			$html .= '<label class="cartiva-offer' . ( $on ? ' is-selected' : '' ) . '">';
			$html .= '<input type="' . esc_attr( $type ) . '" class="cartiva-offer__input" name="cartiva_offer_choice' . ( $many ? '[]' : '' ) . '" value="' . esc_attr( (string) $i ) . '"' . checked( $on, true, false ) . ' />';
			$html .= '<span class="cartiva-offer__image">' . $product->get_image( 'woocommerce_gallery_thumbnail', array( 'alt' => '' ) ) . '</span>';
			$html .= '<span class="cartiva-offer__info"><span class="cartiva-offer__name">' . esc_html( $name );
			if ( $qty > 1 && '' === $row['label'] ) {
				$html .= ' <span class="cartiva-offer__qty">&times; ' . esc_html( (string) $qty ) . '</span>';
			}
			$html .= '</span>';
			if ( ! empty( $row['discount'] ) ) {
				/* translators: %s: discount percentage. */
				$html .= '<span class="cartiva-offer__save">' . esc_html( sprintf( __( 'Save %s%%', 'cartiva-commerce' ), number_format_i18n( (float) $row['discount'], floor( (float) $row['discount'] ) == (float) $row['discount'] ? 0 : 1 ) ) ) . '</span>'; // phpcs:ignore Universal.Operators.StrictComparisons.LooseEqual -- Float to whole number check.
			}
			$html .= '</span>';
			$html .= '<span class="cartiva-offer__price">' . self::price_html( $product, $row, $qty ) . '</span>';
			if ( '' !== $row['badge'] ) {
				$html .= '<span class="cartiva-offer__badge">' . esc_html( $row['badge'] ) . '</span>';
			}
			$html .= '</label>';
		}
		return $html . '</fieldset>';
	}

	/**
	 * Change the quantity of the landing page's product (wc-ajax endpoint).
	 */
	public static function ajax_set_qty() {
		check_ajax_referer( 'cartiva_commerce_form', 'nonce' );
		$landing_id = isset( $_POST['landing'] ) ? absint( wp_unslash( $_POST['landing'] ) ) : 0;
		$qty        = isset( $_POST['qty'] ) ? absint( wp_unslash( $_POST['qty'] ) ) : 0;
		if ( ! cartiva_commerce_is_landing( $landing_id ) || $qty < 1 ) {
			wp_send_json_error( null, 400 );
		}
		$config = cartiva_commerce_landing_config( $landing_id );
		if ( empty( $config['qty_editable'] ) || 'single' !== $config['offer_mode'] ) {
			wp_send_json_error( null, 403 );
		}
		$key = Cartiva_Commerce_Landing::cart_item_key( $config, $landing_id );
		if ( '' === $key ) {
			wp_send_json_error( null, 409 );
		}
		$item    = WC()->cart->get_cart_item( $key );
		$product = isset( $item['data'] ) ? $item['data'] : null;
		$max     = ( $product instanceof WC_Product && $product->get_max_purchase_quantity() > 0 ) ? $product->get_max_purchase_quantity() : 99;
		$qty     = min( $qty, min( 99, $max ) );
		WC()->cart->set_quantity( $key, $qty, true );
		wp_send_json_success( array( 'qty' => $qty ) );
	}
}
