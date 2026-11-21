<?php
/**
 * Tracking: Facebook (Meta) Pixel, Google Analytics 4 and TikTok Pixel
 * events for funnels and, optionally, the rest of the store.
 *
 * Events: page view, product view, checkout start, order bump added and
 * Purchase. Purchase is sent once per order, on the page where the buying
 * journey ends (after upsells), with the final order value.
 *
 * @package Cartiva_Commerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Tracking.
 */
class Cartiva_Commerce_Tracking {

	/**
	 * Hook up.
	 */
	public static function init() {
		add_action( 'wp_head', array( __CLASS__, 'output' ), 20 );
	}

	/**
	 * Configured services: fb, ga4, tiktok => ID.
	 *
	 * @return array
	 */
	public static function services() {
		return array_filter(
			array(
				'fb'     => (string) cartiva_commerce_setting( 'fb_pixel' ),
				'ga4'    => (string) cartiva_commerce_setting( 'ga4_id' ),
				'tiktok' => (string) cartiva_commerce_setting( 'tiktok_pixel' ),
			)
		);
	}

	/**
	 * May pixels load for this visitor?
	 *
	 * @return bool
	 */
	public static function allowed() {
		$allowed = ! function_exists( 'wp_has_consent' ) || wp_has_consent( 'marketing' );
		/**
		 * Filters whether Cartiva Commerce loads tracking pixels for this visitor.
		 *
		 * @param bool $allowed Allowed.
		 */
		return (bool) apply_filters( 'cartiva_commerce_tracking_allowed', $allowed );
	}

	/**
	 * Product data for events.
	 *
	 * @param WC_Product $product Product.
	 * @param int        $qty     Quantity.
	 * @param float|null $price   Price per piece (defaults to the product price).
	 * @return array
	 */
	public static function item( $product, $qty = 1, $price = null ) {
		return array(
			'id'       => (string) $product->get_id(),
			'name'     => wp_strip_all_tags( $product->get_name() ),
			'price'    => round( null === $price ? (float) $product->get_price() : (float) $price, 2 ),
			'quantity' => max( 1, (int) $qty ),
		);
	}

	/**
	 * Event payload from items.
	 *
	 * @param array $items    Items.
	 * @param float $value    Value.
	 * @param int   $order_id Order ID for Purchase (deduplication), or 0.
	 * @return array
	 */
	public static function payload( $items, $value, $order_id = 0 ) {
		$data = array(
			'value'    => round( (float) $value, 2 ),
			'currency' => get_woocommerce_currency(),
			'items'    => array_values( $items ),
		);
		if ( $order_id ) {
			$data['order_id'] = (string) $order_id;
		}
		return $data;
	}

	/**
	 * Purchase payload of an order.
	 *
	 * @param WC_Order $order Order.
	 * @return array
	 */
	public static function order_payload( $order ) {
		$items = array();
		foreach ( $order->get_items() as $line ) {
			$qty     = max( 1, (int) $line->get_quantity() );
			$items[] = array(
				'id'       => (string) ( $line->get_variation_id() ? $line->get_variation_id() : $line->get_product_id() ),
				'name'     => wp_strip_all_tags( $line->get_name() ),
				'price'    => round( (float) $line->get_total() / $qty, 2 ),
				'quantity' => $qty,
			);
		}
		$data             = self::payload( $items, (float) $order->get_total(), $order->get_id() );
		$data['currency'] = $order->get_currency();
		return $data;
	}

	/**
	 * The order whose journey ends on this page, if any.
	 *
	 * @return WC_Order|null
	 */
	public static function finished_order() {
		if ( is_singular( CARTIVA_COMMERCE_LANDING ) || is_page() ) {
			$order = Cartiva_Commerce_Upsells::request_order();
			if ( $order && Cartiva_Commerce_Checkout::thankyou_target( (int) $order->get_meta( '_cartiva_landing_id' ) ) === (int) get_queried_object_id() ) {
				return $order;
			}
		}
		if ( function_exists( 'is_order_received_page' ) && is_order_received_page() ) {
			$order = wc_get_order( absint( get_query_var( 'order-received' ) ) );
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- The order key is the proof.
			$key = isset( $_GET['key'] ) ? sanitize_text_field( wp_unslash( $_GET['key'] ) ) : '';
			if ( $order instanceof WC_Order && hash_equals( $order->get_order_key(), $key ) && ( cartiva_commerce_setting( 'track_store' ) || $order->get_meta( '_cartiva_landing_id' ) ) ) {
				return $order;
			}
		}
		return null;
	}

	/**
	 * Events for this page: list of array( name, payload ).
	 *
	 * @return array
	 */
	public static function events() {
		$events = array();
		$store  = (bool) cartiva_commerce_setting( 'track_store' );
		$order  = self::finished_order();
		if ( $order ) {
			if ( ! $order->get_meta( '_cartiva_tracked' ) ) {
				$events[] = array( 'purchase', self::order_payload( $order ) );
				$order->update_meta_data( '_cartiva_tracked', 1 );
				$order->save();
			}
			return $events;
		}
		if ( is_singular( CARTIVA_COMMERCE_LANDING ) ) {
			$landing_id = (int) get_queried_object_id();
			$offers     = cartiva_commerce_landing_offers( cartiva_commerce_landing_config( $landing_id ) );
			if ( $offers ) {
				$first   = $offers[0];
				$product = wc_get_product( $first['variation_id'] ? (int) $first['variation_id'] : (int) $first['product_id'] );
				if ( $product ) {
					$price    = cartiva_commerce_discounted( (float) $product->get_price(), (float) $first['discount'] );
					$events[] = array( 'view', self::payload( array( self::item( $product, (int) $first['qty'], $price ) ), $price * max( 1, (int) $first['qty'] ) ) );
				}
				if ( WC()->cart ) {
					$items = array();
					foreach ( WC()->cart->get_cart() as $line ) {
						if ( isset( $line['data'] ) && $line['data'] instanceof WC_Product ) {
							$items[] = self::item( $line['data'], (int) $line['quantity'] );
						}
					}
					$events[] = array( 'checkout', self::payload( $items, (float) WC()->cart->get_subtotal() ) );
				}
			}
			return $events;
		}
		if ( $store && function_exists( 'is_product' ) && is_product() ) {
			$product = wc_get_product( (int) get_queried_object_id() );
			if ( $product ) {
				$events[] = array( 'view', self::payload( array( self::item( $product ) ), (float) $product->get_price() ) );
			}
		} elseif ( $store && function_exists( 'is_checkout' ) && is_checkout() && WC()->cart && ! WC()->cart->is_empty() ) {
			$items = array();
			foreach ( WC()->cart->get_cart() as $line ) {
				if ( isset( $line['data'] ) && $line['data'] instanceof WC_Product ) {
					$items[] = self::item( $line['data'], (int) $line['quantity'] );
				}
			}
			$events[] = array( 'checkout', self::payload( $items, (float) WC()->cart->get_subtotal() ) );
		}
		return $events;
	}

	/**
	 * Should the pixels load on this page?
	 *
	 * @return bool
	 */
	public static function page_tracked() {
		if ( is_singular( CARTIVA_COMMERCE_LANDING ) || cartiva_commerce_setting( 'track_store' ) ) {
			return true;
		}
		return null !== self::finished_order();
	}

	/**
	 * Print the pixels and this page's events.
	 */
	public static function output() {
		$services = self::services();
		if ( empty( $services ) || is_admin() || ! self::page_tracked() || ! self::allowed() ) {
			return;
		}
		$js = '';
		if ( isset( $services['fb'] ) ) {
			$js .= "!function(f,b,e,v,n,t,s){if(f.fbq)return;n=f.fbq=function(){n.callMethod?n.callMethod.apply(n,arguments):n.queue.push(arguments)};if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';n.queue=[];t=b.createElement(e);t.async=!0;t.src=v;s=b.getElementsByTagName(e)[0];s.parentNode.insertBefore(t,s)}(window,document,'script','https://connect.facebook.net/en_US/fbevents.js');\n";
			$js .= 'fbq("init",' . wp_json_encode( $services['fb'] ) . ');fbq("track","PageView");' . "\n";
		}
		if ( isset( $services['ga4'] ) ) {
			$js .= '(function(d,id){var s=d.createElement("script");s.async=true;s.src="https://www.googletagmanager.com/gtag/js?id="+encodeURIComponent(id);d.head.appendChild(s);})(document,' . wp_json_encode( $services['ga4'] ) . ');' . "\n";
			$js .= 'window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}gtag("js",new Date());gtag("config",' . wp_json_encode( $services['ga4'] ) . ');' . "\n";
		}
		if ( isset( $services['tiktok'] ) ) {
			$js .= '!function(w,d,t){w.TiktokAnalyticsObject=t;var ttq=w[t]=w[t]||[];ttq.methods=["page","track","identify","instances","debug","on","off","once","ready","alias","group","enableCookie","disableCookie","holdConsent","revokeConsent","grantConsent"],ttq.setAndDefer=function(t,e){t[e]=function(){t.push([e].concat(Array.prototype.slice.call(arguments,0)))}};for(var i=0;i<ttq.methods.length;i++)ttq.setAndDefer(ttq,ttq.methods[i]);ttq.instance=function(t){for(var e=ttq._i[t]||[],n=0;n<ttq.methods.length;n++)ttq.setAndDefer(e,ttq.methods[n]);return e},ttq.load=function(e,n){var r="https://analytics.tiktok.com/i18n/pixel/events.js";ttq._i=ttq._i||{},ttq._i[e]=[],ttq._i[e]._u=r,ttq._t=ttq._t||{},ttq._t[e]=+new Date,ttq._o=ttq._o||{},ttq._o[e]=n||{};var o=d.createElement("script");o.type="text/javascript",o.async=!0,o.src=r+"?sdkid="+e+"&lib="+t;var a=d.getElementsByTagName("script")[0];a.parentNode.insertBefore(o,a)};ttq.load(' . wp_json_encode( $services['tiktok'] ) . ');ttq.page();}(window,document,"ttq");' . "\n";
		}
		// One function sends an event to every configured service.
		$js .= 'window.cartivaTrack=function(name,d){var map={view:["ViewContent","view_item","ViewContent"],checkout:["InitiateCheckout","begin_checkout","InitiateCheckout"],add:["AddToCart","add_to_cart","AddToCart"],purchase:["Purchase","purchase","CompletePayment"]}[name];if(!map||!d){return;}'
			. 'var items=d.items||[];var ids=items.map(function(i){return i.id;});var n=items.reduce(function(a,i){return a+i.quantity;},0);'
			. 'if(window.fbq){fbq("track",map[0],{value:d.value,currency:d.currency,content_type:"product",content_ids:ids,contents:items.map(function(i){return{id:i.id,quantity:i.quantity};}),num_items:n},d.order_id?{eventID:"order_"+d.order_id}:{});}'
			. 'if(window.gtag){var g={value:d.value,currency:d.currency,items:items.map(function(i){return{item_id:i.id,item_name:i.name,price:i.price,quantity:i.quantity};})};if(d.order_id){g.transaction_id=d.order_id;}gtag("event",map[1],g);}'
			. 'if(window.ttq){ttq.track(map[2],{value:d.value,currency:d.currency,content_type:"product",contents:items.map(function(i){return{content_id:i.id,content_name:i.name,price:i.price,quantity:i.quantity};})},d.order_id?{event_id:"order_"+d.order_id}:{});}};' . "\n";
		foreach ( self::events() as $event ) {
			$js .= 'cartivaTrack(' . wp_json_encode( $event[0] ) . ',' . wp_json_encode( $event[1] ) . ');' . "\n";
		}
		// Order bumps added on landing forms.
		$js .= 'document.addEventListener("DOMContentLoaded",function(){if(window.jQuery){jQuery(document.body).on("cartiva_bump_added",function(e,item){cartivaTrack("add",{value:item.price*item.quantity,currency:' . wp_json_encode( get_woocommerce_currency() ) . ',items:[item]});});}});';
		wp_print_inline_script_tag( $js, array( 'id' => 'cartiva-commerce-tracking' ) );
	}
}
