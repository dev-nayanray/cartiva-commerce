<?php
/**
 * One-click upsells and downsells: after an order from a funnel's checkout,
 * buyers see offer steps. Accepting adds the product to the same order, so
 * the buyer does not fill in anything again.
 *
 * Works with payment methods where nothing has been charged yet when the
 * order is placed (Cash on delivery, bank transfer, check). Orders paid with
 * other methods skip the offer steps and go straight to the thank-you step.
 *
 * @package Cartiva_Commerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Upsells.
 */
class Cartiva_Commerce_Upsells {

	/**
	 * Hook up.
	 */
	public static function init() {
		add_filter( 'woocommerce_get_checkout_order_received_url', array( __CLASS__, 'redirect_to_offer' ), 30, 2 );
		add_action( 'template_redirect', array( __CLASS__, 'handle' ), 1 );
		add_action( 'init', array( __CLASS__, 'register_blocks' ) );
	}

	/**
	 * Payment methods that allow adding offers to the order.
	 *
	 * @return string[]
	 */
	public static function gateways() {
		/**
		 * Filters the payment methods whose orders can get upsells and downsells.
		 *
		 * Only add methods that do not charge the buyer when the order is placed.
		 *
		 * @param string[] $gateways Payment method IDs.
		 */
		return (array) apply_filters( 'cartiva_commerce_upsell_gateways', array( 'cod', 'bacs', 'cheque' ) );
	}

	/**
	 * Offer settings of a step.
	 *
	 * @param int $step_id Step (landing page) ID.
	 * @return array
	 */
	public static function offer( $step_id ) {
		$offer = cartiva_commerce_landing_config( $step_id )['offer'];
		return wp_parse_args(
			is_array( $offer ) ? $offer : array(),
			array(
				'product_id'   => 0,
				'variation_id' => 0,
				'qty'          => 1,
				'discount'     => 0,
				'yes_label'    => '',
				'no_label'     => '',
			)
		);
	}

	/**
	 * Can this order still get offers?
	 *
	 * @param WC_Order $order Order.
	 * @return bool
	 */
	public static function order_can_get_offers( $order ) {
		if ( ! $order instanceof WC_Order || ! (int) $order->get_meta( '_cartiva_landing_id' ) ) {
			return false;
		}
		if ( ! in_array( $order->get_status(), array( 'pending', 'on-hold', 'processing' ), true ) ) {
			return false;
		}
		if ( ! in_array( (string) $order->get_payment_method(), self::gateways(), true ) ) {
			return false;
		}
		$created = $order->get_date_created();
		/**
		 * Filters how long after the order its offers can be accepted, in seconds.
		 *
		 * @param int $seconds Seconds (default two hours).
		 */
		$window = (int) apply_filters( 'cartiva_commerce_offer_window', 2 * HOUR_IN_SECONDS );
		return $created && ( time() - $created->getTimestamp() ) <= $window;
	}

	/**
	 * Decisions already taken for an order: step ID => yes or no.
	 *
	 * @param WC_Order $order Order.
	 * @return array
	 */
	public static function decisions( $order ) {
		$decisions = $order->get_meta( '_cartiva_offers' );
		return is_array( $decisions ) ? $decisions : array();
	}

	/**
	 * Is a step an offer step of the funnel this order came from?
	 *
	 * @param int      $step_id Step ID.
	 * @param WC_Order $order   Order.
	 * @return bool
	 */
	public static function is_offer_step_of( $step_id, $order ) {
		$funnel = cartiva_commerce_funnel_of( (int) $order->get_meta( '_cartiva_landing_id' ) );
		return $funnel && cartiva_commerce_funnel_of( $step_id ) === $funnel && in_array( cartiva_commerce_step_type( $step_id ), array( 'upsell', 'downsell' ), true );
	}

	/**
	 * Link to a step for an order.
	 *
	 * @param int      $step_id Step ID.
	 * @param WC_Order $order   Order.
	 * @return string
	 */
	public static function step_url( $step_id, $order ) {
		return add_query_arg(
			array(
				'cartiva_order' => $order->get_id(),
				'key'           => $order->get_order_key(),
			),
			get_permalink( $step_id )
		);
	}

	/**
	 * Where to go from a step: the next offer step, or the end of the funnel.
	 *
	 * @param int      $step_id Current step ID.
	 * @param WC_Order $order   Order.
	 * @param bool     $skip    Skip downsells (after a yes, or after a downsell).
	 * @return string URL.
	 */
	public static function next_url( $step_id, $order, $skip ) {
		$next = cartiva_commerce_step_after( $step_id, $skip );
		if ( $next && in_array( $next['type'], array( 'upsell', 'downsell' ), true ) && self::order_can_get_offers( $order ) ) {
			return self::step_url( (int) $next['id'], $order );
		}
		return Cartiva_Commerce_Checkout::final_url( $order );
	}

	/**
	 * After checkout, send the buyer to the first offer step (once).
	 *
	 * @param string   $url   Order received URL.
	 * @param WC_Order $order Order.
	 * @return string
	 */
	public static function redirect_to_offer( $url, $order ) {
		if ( ! self::order_can_get_offers( $order ) || self::decisions( $order ) ) {
			return $url;
		}
		$next = cartiva_commerce_step_after( (int) $order->get_meta( '_cartiva_landing_id' ) );
		if ( $next && in_array( $next['type'], array( 'upsell', 'downsell' ), true ) ) {
			return self::step_url( (int) $next['id'], $order );
		}
		return $url;
	}

	/**
	 * The order of the current offer page, checked against its key.
	 *
	 * @return WC_Order|null
	 */
	public static function request_order() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- The order key is the proof; actions check a nonce too.
		if ( ! isset( $_GET['cartiva_order'], $_GET['key'] ) ) {
			return null;
		}
		$order = wc_get_order( absint( wp_unslash( $_GET['cartiva_order'] ) ) );
		$key   = sanitize_text_field( wp_unslash( $_GET['key'] ) );
		// phpcs:enable
		return ( $order instanceof WC_Order && hash_equals( $order->get_order_key(), $key ) ) ? $order : null;
	}

	/**
	 * On an offer step: take the buyer's answer, or move on when the offer
	 * no longer applies (answered already, too late, other payment method).
	 */
	public static function handle() {
		if ( ! is_singular( CARTIVA_COMMERCE_LANDING ) ) {
			return;
		}
		$step_id = (int) get_queried_object_id();
		if ( ! in_array( cartiva_commerce_step_type( $step_id ), array( 'upsell', 'downsell' ), true ) ) {
			return;
		}
		$order = self::request_order();
		if ( ! $order || ! self::is_offer_step_of( $step_id, $order ) ) {
			return; // The offer block explains that the offer follows an order.
		}
		$skip      = 'downsell' === cartiva_commerce_step_type( $step_id );
		$decisions = self::decisions( $order );
		if ( isset( $decisions[ $step_id ] ) || ! self::order_can_get_offers( $order ) ) {
			self::go( self::next_url( $step_id, $order, $skip || ( isset( $decisions[ $step_id ] ) && 'yes' === $decisions[ $step_id ] ) ) );
		}
		if ( ! isset( $_SERVER['REQUEST_METHOD'] ) || 'POST' !== $_SERVER['REQUEST_METHOD'] || ! isset( $_POST['cartiva_offer_nonce'], $_POST['cartiva_choice'] ) ) {
			return;
		}
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['cartiva_offer_nonce'] ) ), 'cartiva_offer_' . $step_id . '_' . $order->get_id() ) ) {
			wc_add_notice( __( 'This page expired. Please try again.', 'cartiva-commerce' ), 'error' );
			return;
		}
		$yes = 'yes' === sanitize_key( wp_unslash( $_POST['cartiva_choice'] ) );
		self::record( $order, $step_id, $yes );
		self::go( self::next_url( $step_id, $order, $skip || $yes ) );
	}

	/**
	 * Save the buyer's answer and add the product on a yes.
	 *
	 * @param WC_Order $order   Order.
	 * @param int      $step_id Step ID.
	 * @param bool     $yes     Accepted.
	 * @return bool Whether a product was added.
	 */
	public static function record( $order, $step_id, $yes ) {
		$decisions             = self::decisions( $order );
		$decisions[ $step_id ] = $yes ? 'yes' : 'no';
		$order->update_meta_data( '_cartiva_offers', $decisions );
		$added = $yes ? self::add_offer( $order, $step_id ) : false;
		if ( ! $yes ) {
			/* translators: %s: step title. */
			$order->add_order_note( sprintf( __( 'Offer declined: %s', 'cartiva-commerce' ), get_the_title( $step_id ) ) );
		}
		$order->save();
		delete_transient( 'cartiva_landing_stats_' . (int) $order->get_meta( '_cartiva_landing_id' ) );
		$funnel = cartiva_commerce_funnel_of( $step_id );
		if ( $funnel ) {
			delete_transient( 'cartiva_funnel_stats_' . $funnel );
		}
		return $added;
	}

	/**
	 * Price per piece of an offer.
	 *
	 * @param WC_Product $product Product.
	 * @param array      $offer   Offer settings.
	 * @return float
	 */
	public static function unit_price( $product, $offer ) {
		return cartiva_commerce_discounted( (float) $product->get_price(), (float) $offer['discount'] );
	}

	/**
	 * Add a step's offer to the order.
	 *
	 * @param WC_Order $order   Order.
	 * @param int      $step_id Step ID.
	 * @return bool
	 */
	public static function add_offer( $order, $step_id ) {
		$offer   = self::offer( $step_id );
		$product = $offer['product_id'] ? wc_get_product( $offer['variation_id'] ? (int) $offer['variation_id'] : (int) $offer['product_id'] ) : null;
		$qty     = max( 1, (int) $offer['qty'] );
		if ( ! $product || ! $product->is_purchasable() || ! $product->has_enough_stock( $qty ) ) {
			/* translators: %s: step title. */
			$order->add_order_note( sprintf( __( 'Offer accepted but the product is not available: %s', 'cartiva-commerce' ), get_the_title( $step_id ) ) );
			return false;
		}
		$total   = self::unit_price( $product, $offer ) * $qty;
		$args    = array(
			'subtotal' => $total,
			'total'    => $total,
		);
		if ( $product->is_type( 'variation' ) ) {
			$args['variation'] = $product->get_variation_attributes();
		}
		$item_id = $order->add_product( $product, $qty, $args );
		if ( ! $item_id ) {
			return false;
		}
		$item = $order->get_item( $item_id );
		if ( $item ) {
			$item->add_meta_data( '_cartiva_upsell', (int) $step_id, true );
			// Stock was taken for the rest of the order already: take it for this line too.
			if ( $order->get_data_store()->get_stock_reduced( $order->get_id() ) && $product->managing_stock() ) {
				wc_update_product_stock( $product, $qty, 'decrease' );
				$item->add_meta_data( '_reduced_stock', $qty, true );
			}
			$item->save();
		}
		$order->calculate_totals( true );
		$order->add_order_note(
			sprintf(
				/* translators: 1: quantity, 2: product name, 3: price. */
				__( 'Offer accepted: %1$s × %2$s added for %3$s.', 'cartiva-commerce' ),
				$qty,
				wp_strip_all_tags( $product->get_name() ),
				html_entity_decode( wp_strip_all_tags( wc_price( $total, array( 'currency' => $order->get_currency() ) ) ), ENT_QUOTES, 'UTF-8' )
			)
		);
		return true;
	}

	/**
	 * Redirect and stop.
	 *
	 * @param string $url URL.
	 */
	private static function go( $url ) {
		wp_safe_redirect( $url );
		exit;
	}

	/**
	 * Register the offer and order details blocks.
	 */
	public static function register_blocks() {
		register_block_type( CARTIVA_COMMERCE_DIR . 'blocks/offer', array( 'render_callback' => array( __CLASS__, 'render_offer' ) ) );
		register_block_type( CARTIVA_COMMERCE_DIR . 'blocks/order-details', array( 'render_callback' => array( __CLASS__, 'render_order_details' ) ) );
	}

	/**
	 * Offer block: the product and the Yes / No buttons.
	 *
	 * @param array $attributes Block attributes.
	 * @return string
	 */
	public static function render_offer( $attributes ) {
		$step_id = (int) get_the_ID();
		$wrapper = get_block_wrapper_attributes( array( 'class' => 'cartiva-offer-box' ) );
		$offer   = self::offer( $step_id );
		$product = $offer['product_id'] ? wc_get_product( $offer['variation_id'] ? (int) $offer['variation_id'] : (int) $offer['product_id'] ) : null;
		$order   = self::request_order();
		$valid   = $order && self::is_offer_step_of( $step_id, $order );
		$editor  = current_user_can( 'edit_post', $step_id );

		if ( ! $product ) {
			return $editor ? '<div ' . $wrapper . '><p class="cartiva-order-form__notice">' . esc_html__( 'Choose the offer product in the "Upsell or downsell offer" section when editing this page, and add this page to a funnel as an Upsell or Downsell step.', 'cartiva-commerce' ) . '</p></div>' : '';
		}
		if ( ! $valid && ! $editor ) {
			return '<div ' . $wrapper . '><p class="cartiva-order-form__notice">' . esc_html__( 'This offer is shown right after an order.', 'cartiva-commerce' ) . '</p></div>';
		}

		$qty     = max( 1, (int) $offer['qty'] );
		$price   = self::unit_price( $product, $offer ) * $qty;
		$regular = (float) $product->get_price() * $qty;
		$yes     = '' !== $offer['yes_label'] ? $offer['yes_label'] : __( 'Yes, add it to my order', 'cartiva-commerce' );
		$no      = '' !== $offer['no_label'] ? $offer['no_label'] : __( 'No thanks, I don\'t want this offer', 'cartiva-commerce' );

		$html  = '<div class="cartiva-offer-box__product">';
		$html .= '<div class="cartiva-offer-box__image">' . $product->get_image( 'woocommerce_single', array( 'alt' => '' ) ) . '</div>';
		$html .= '<div class="cartiva-offer-box__info"><p class="cartiva-offer-box__name">' . esc_html( wp_strip_all_tags( $product->get_name() ) ) . ( $qty > 1 ? ' &times; ' . esc_html( (string) $qty ) : '' ) . '</p>';
		$html .= '<p class="cartiva-offer-box__price">' . ( $price < $regular ? '<del aria-hidden="true">' . wp_kses_post( wc_price( $regular ) ) . '</del> ' : '' ) . '<ins>' . wp_kses_post( wc_price( $price ) ) . '</ins></p>';
		if ( $valid ) {
			$html .= '<p class="cartiva-offer-box__note">' . esc_html(
				sprintf(
					/* translators: %s: order number. */
					__( 'One click adds it to your order #%s. You do not need to fill in anything again.', 'cartiva-commerce' ),
					$order->get_order_number()
				)
			) . '</p>';
		}
		$html .= '</div></div>';

		if ( $valid ) {
			$html .= '<form method="post" class="cartiva-offer-box__form" action="' . esc_url( self::step_url( $step_id, $order ) ) . '">';
			$html .= wp_nonce_field( 'cartiva_offer_' . $step_id . '_' . $order->get_id(), 'cartiva_offer_nonce', false, false );
			$html .= '<button type="submit" name="cartiva_choice" value="yes" class="cartiva-offer-box__yes">' . esc_html( $yes ) . '</button>';
			$html .= '<button type="submit" name="cartiva_choice" value="no" class="cartiva-offer-box__no">' . esc_html( $no ) . '</button>';
			$html .= '</form>';
		} else {
			$html .= '<p class="cartiva-offer-box__form"><span class="cartiva-offer-box__yes" aria-disabled="true">' . esc_html( $yes ) . '</span><span class="cartiva-offer-box__no" aria-disabled="true">' . esc_html( $no ) . '</span></p>';
			$html .= '<p class="cartiva-order-form__notice">' . esc_html__( 'Preview: buyers see working buttons right after they place an order from this funnel.', 'cartiva-commerce' ) . '</p>';
		}
		return '<div ' . $wrapper . '>' . $html . '</div>';
	}

	/**
	 * Order details block for thank-you pages.
	 *
	 * @param array $attributes Block attributes.
	 * @return string
	 */
	public static function render_order_details( $attributes ) {
		$order   = self::request_order();
		$wrapper = get_block_wrapper_attributes( array( 'class' => 'cartiva-order-details' ) );
		if ( ! $order ) {
			return current_user_can( 'edit_posts' ) ? '<div ' . $wrapper . '><p class="cartiva-order-form__notice">' . esc_html__( 'Buyers see their order details here after they place an order.', 'cartiva-commerce' ) . '</p></div>' : '';
		}
		$rows = array(
			__( 'Order number', 'cartiva-commerce' ) => $order->get_order_number(),
			__( 'Date', 'cartiva-commerce' )         => $order->get_date_created() ? wc_format_datetime( $order->get_date_created() ) : '',
			__( 'Payment', 'cartiva-commerce' )      => wp_strip_all_tags( $order->get_payment_method_title() ),
		);
		$html = '<p class="cartiva-order-details__lead">' . esc_html__( 'Thank you. Your order has been received.', 'cartiva-commerce' ) . '</p>';
		$html .= '<ul class="cartiva-order-details__facts">';
		foreach ( $rows as $label => $value ) {
			if ( '' !== (string) $value ) {
				$html .= '<li><span>' . esc_html( $label ) . '</span> <strong>' . esc_html( (string) $value ) . '</strong></li>';
			}
		}
		$html .= '<li><span>' . esc_html__( 'Total', 'cartiva-commerce' ) . '</span> <strong>' . wp_kses_post( $order->get_formatted_order_total() ) . '</strong></li>';
		$html .= '</ul>';

		$html .= '<table class="cartiva-order-details__items"><caption class="screen-reader-text">' . esc_html__( 'Order items', 'cartiva-commerce' ) . '</caption><tbody>';
		foreach ( $order->get_items() as $item ) {
			$html .= '<tr><th scope="row">' . esc_html( wp_strip_all_tags( $item->get_name() ) ) . ' <span>&times; ' . esc_html( (string) $item->get_quantity() ) . '</span></th><td>' . wp_kses_post( $order->get_formatted_line_subtotal( $item ) ) . '</td></tr>';
		}
		foreach ( $order->get_order_item_totals() as $key => $total ) {
			if ( 'cart_subtotal' === $key || 'payment_method' === $key ) {
				continue;
			}
			$html .= '<tr class="is-total"><th scope="row">' . esc_html( wp_strip_all_tags( $total['label'] ) ) . '</th><td>' . wp_kses_post( $total['value'] ) . '</td></tr>';
		}
		$html .= '</tbody></table>';

		$address = $order->get_formatted_billing_address();
		if ( $address ) {
			$html .= '<div class="cartiva-order-details__address"><h3>' . esc_html__( 'Delivery details', 'cartiva-commerce' ) . '</h3><address>' . wp_kses_post( $address );
			if ( $order->get_billing_phone() ) {
				$html .= '<br>' . esc_html( $order->get_billing_phone() );
			}
			$html .= '</address></div>';
		}
		// Let payment methods add their instructions (for example bank details).
		ob_start();
		do_action( 'woocommerce_thankyou_' . $order->get_payment_method(), $order->get_id() );
		$html .= ob_get_clean();

		return '<div ' . $wrapper . '>' . $html . '</div>';
	}
}
