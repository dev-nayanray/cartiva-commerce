<?php
/**
 * Landing page results: product, visits, orders, revenue and conversion
 * columns in the Landing pages list.
 *
 * @package Cartiva_Commerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Stats.
 */
class Cartiva_Commerce_Stats {

	/**
	 * Hook up.
	 */
	public static function init() {
		add_filter( 'manage_' . CARTIVA_COMMERCE_LANDING . '_posts_columns', array( __CLASS__, 'columns' ) );
		add_action( 'manage_' . CARTIVA_COMMERCE_LANDING . '_posts_custom_column', array( __CLASS__, 'column' ), 10, 2 );
		add_action( 'woocommerce_checkout_order_processed', array( __CLASS__, 'flush_for_order' ), 10, 3 );
		add_action( 'woocommerce_order_status_changed', array( __CLASS__, 'flush_for_order_id' ) );
	}

	/**
	 * Add the columns.
	 *
	 * @param array $columns Columns.
	 * @return array
	 */
	public static function columns( $columns ) {
		$date = isset( $columns['date'] ) ? $columns['date'] : null;
		unset( $columns['date'] );
		$columns['cartiva_product']    = __( 'Product', 'cartiva-commerce' );
		$columns['cartiva_visits']     = __( 'Visits', 'cartiva-commerce' );
		$columns['cartiva_orders']     = __( 'Orders', 'cartiva-commerce' );
		$columns['cartiva_revenue']    = __( 'Revenue', 'cartiva-commerce' );
		$columns['cartiva_conversion'] = __( 'Conversion', 'cartiva-commerce' );
		if ( null !== $date ) {
			$columns['date'] = $date;
		}
		return $columns;
	}

	/**
	 * Orders and revenue of a landing page (cached for 10 minutes).
	 *
	 * Counts orders that are processing, completed or on hold.
	 *
	 * @param int $landing_id Landing page ID.
	 * @return array{orders:int,revenue:float}
	 */
	public static function totals( $landing_id ) {
		$key    = 'cartiva_landing_stats_' . (int) $landing_id;
		if ( Cartiva_Commerce_Ledger::ready() ) {
			$sum = Cartiva_Commerce_Ledger::summary( array( $landing_id ) );
			return array(
				'orders'  => $sum['orders'],
				'revenue' => $sum['revenue'],
			);
		}
		$cached = get_transient( $key );
		if ( is_array( $cached ) ) {
			return $cached;
		}
		$ids     = wc_get_orders(
			array(
				'limit'      => -1,
				'return'     => 'ids',
				'status'     => array( 'wc-processing', 'wc-completed', 'wc-on-hold' ),
				'meta_query' => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Admin list only, cached.
					array(
						'key'   => '_cartiva_landing_id',
						'value' => (int) $landing_id,
					),
				),
			)
		);
		$revenue = 0.0;
		foreach ( (array) $ids as $order_id ) {
			$order = wc_get_order( $order_id );
			if ( $order ) {
				$revenue += (float) $order->get_total();
			}
		}
		$totals = array(
			'orders'  => count( (array) $ids ),
			'revenue' => $revenue,
		);
		set_transient( $key, $totals, 10 * MINUTE_IN_SECONDS );
		return $totals;
	}

	/**
	 * Print a column.
	 *
	 * @param string $column  Column key.
	 * @param int    $post_id Landing page ID.
	 */
	public static function column( $column, $post_id ) {
		switch ( $column ) {
			case 'cartiva_product':
				$config  = cartiva_commerce_landing_config( $post_id );
				$item_id = $config['variation_id'] ? (int) $config['variation_id'] : (int) $config['product_id'];
				$product = $item_id ? wc_get_product( $item_id ) : null;
				echo $product ? esc_html( wp_strip_all_tags( $product->get_name() ) ) : '<span aria-hidden="true">&mdash;</span><span class="screen-reader-text">' . esc_html__( 'No product chosen', 'cartiva-commerce' ) . '</span>';
				break;
			case 'cartiva_visits':
				echo esc_html( number_format_i18n( (int) get_post_meta( $post_id, '_cartiva_visits', true ) ) );
				break;
			case 'cartiva_orders':
				echo esc_html( number_format_i18n( self::totals( $post_id )['orders'] ) );
				break;
			case 'cartiva_revenue':
				echo wp_kses_post( wc_price( self::totals( $post_id )['revenue'] ) );
				break;
			case 'cartiva_conversion':
				$visits = (int) get_post_meta( $post_id, '_cartiva_visits', true );
				$orders = self::totals( $post_id )['orders'];
				echo $visits > 0 ? esc_html( number_format_i18n( $orders / $visits * 100, 1 ) . '%' ) : '<span aria-hidden="true">&mdash;</span>';
				break;
		}
	}

	/**
	 * Refresh a landing page's numbers after a new order.
	 *
	 * @param int      $order_id    Order ID.
	 * @param array    $posted_data Posted data.
	 * @param WC_Order $order       Order.
	 */
	public static function flush_for_order( $order_id, $posted_data = array(), $order = null ) {
		self::flush_for_order_id( $order_id );
	}

	/**
	 * Refresh numbers for the landing page an order belongs to.
	 *
	 * @param int $order_id Order ID.
	 */
	public static function flush_for_order_id( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( $order ) {
			$landing_id = (int) $order->get_meta( '_cartiva_landing_id' );
			if ( $landing_id ) {
				delete_transient( 'cartiva_landing_stats_' . $landing_id );
				$funnel_id = cartiva_commerce_funnel_of( $landing_id );
				if ( $funnel_id ) {
					delete_transient( 'cartiva_funnel_stats_' . $funnel_id );
				}
			}
		}
	}
}
