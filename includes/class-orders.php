<?php
/**
 * Orders: shows where each order came from (the main site, a landing page or
 * a funnel) in the WooCommerce orders list, with a filter, and a summary box
 * on the order screen.
 *
 * Works with both order storage types (HPOS and the classic posts table).
 *
 * @package Cartiva_Commerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Orders.
 */
class Cartiva_Commerce_Orders {

	/**
	 * Hook up.
	 */
	public static function init() {
		// HPOS orders screen.
		add_filter( 'manage_woocommerce_page_wc-orders_columns', array( __CLASS__, 'columns' ) );
		add_action( 'manage_woocommerce_page_wc-orders_custom_column', array( __CLASS__, 'column_hpos' ), 10, 2 );
		add_action( 'woocommerce_order_list_table_restrict_manage_orders', array( __CLASS__, 'filter_dropdown' ) );
		add_filter( 'woocommerce_order_list_table_prepare_items_query_args', array( __CLASS__, 'filter_hpos' ) );
		// Classic orders screen.
		add_filter( 'manage_edit-shop_order_columns', array( __CLASS__, 'columns' ) );
		add_action( 'manage_shop_order_posts_custom_column', array( __CLASS__, 'column_legacy' ), 10, 2 );
		add_action( 'restrict_manage_posts', array( __CLASS__, 'filter_dropdown_legacy' ) );
		add_action( 'pre_get_posts', array( __CLASS__, 'filter_legacy' ) );
		// Order screen box.
		add_action( 'add_meta_boxes', array( __CLASS__, 'meta_box' ), 30 );
	}

	/**
	 * Is WooCommerce storing orders in its own tables (HPOS)?
	 *
	 * @return bool
	 */
	public static function hpos() {
		return class_exists( 'Automattic\\WooCommerce\\Utilities\\OrderUtil' ) && \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();
	}

	/**
	 * Orders list URL, optionally filtered by source.
	 *
	 * @param string $filter Filter value: main, cartiva, l{ID} or f{ID}.
	 * @return string
	 */
	public static function list_url( $filter = '' ) {
		$base = self::hpos() ? admin_url( 'admin.php?page=wc-orders' ) : admin_url( 'edit.php?post_type=shop_order' );
		return '' === $filter ? $base : add_query_arg( 'cartiva_source', $filter, $base );
	}

	/**
	 * Where an order came from.
	 *
	 * @param WC_Order $order Order.
	 * @return array type (main, landing, funnel), landing, funnel, step (type label).
	 */
	public static function source( $order ) {
		$landing = (int) $order->get_meta( '_cartiva_landing_id' );
		if ( ! $landing ) {
			return array(
				'type'    => 'main',
				'landing' => 0,
				'funnel'  => 0,
				'step'    => '',
			);
		}
		$funnel = cartiva_commerce_funnel_of( $landing );
		$types  = class_exists( 'Cartiva_Commerce_Funnels' ) ? Cartiva_Commerce_Funnels::step_types() : array();
		$type   = $funnel ? cartiva_commerce_step_type( $landing ) : '';
		return array(
			'type'    => $funnel ? 'funnel' : 'landing',
			'landing' => $landing,
			'funnel'  => $funnel,
			'step'    => isset( $types[ $type ] ) ? $types[ $type ] : '',
		);
	}

	/**
	 * Add the Source column after the order status.
	 *
	 * @param array $columns Columns.
	 * @return array
	 */
	public static function columns( $columns ) {
		$out = array();
		foreach ( $columns as $key => $label ) {
			$out[ $key ] = $label;
			if ( 'order_status' === $key ) {
				$out['cartiva_source'] = __( 'Source', 'cartiva-commerce' );
			}
		}
		if ( ! isset( $out['cartiva_source'] ) ) {
			$out['cartiva_source'] = __( 'Source', 'cartiva-commerce' );
		}
		return $out;
	}

	/**
	 * Source badge for the list.
	 *
	 * @param WC_Order $order Order.
	 * @return string
	 */
	public static function badge( $order ) {
		$source = self::source( $order );
		if ( 'main' === $source['type'] ) {
			return '<span class="cartiva-source cartiva-source--main">' . esc_html__( 'Main site', 'cartiva-commerce' ) . '</span>';
		}
		$html = '<span class="cartiva-source cartiva-source--' . esc_attr( $source['type'] ) . '">' . esc_html( 'funnel' === $source['type'] ? __( 'Funnel', 'cartiva-commerce' ) : __( 'Landing page', 'cartiva-commerce' ) ) . '</span>';
		$name = 'funnel' === $source['type'] ? get_the_title( $source['funnel'] ) : get_the_title( $source['landing'] );
		if ( '' !== (string) $name ) {
			$html .= '<br><small>' . esc_html( $name ) . '</small>';
		}
		$extras = array();
		$offers = $order->get_meta( '_cartiva_offers' );
		if ( is_array( $offers ) && in_array( 'yes', $offers, true ) ) {
			$extras[] = __( 'Upsell', 'cartiva-commerce' );
		}
		foreach ( $order->get_items() as $item ) {
			if ( '' !== (string) $item->get_meta( '_cartiva_bump' ) ) {
				$extras[] = __( 'Bump', 'cartiva-commerce' );
				break;
			}
		}
		if ( $order->get_meta( '_cartiva_risk' ) ) {
			$extras[] = __( 'Check', 'cartiva-commerce' );
		}
		if ( $extras ) {
			$html .= '<br><small class="cartiva-source__extras">+ ' . esc_html( implode( ', ', $extras ) ) . '</small>';
		}
		return $html;
	}

	/**
	 * Print the column (HPOS).
	 *
	 * @param string   $column Column.
	 * @param WC_Order $order  Order.
	 */
	public static function column_hpos( $column, $order ) {
		if ( 'cartiva_source' === $column && $order instanceof WC_Order ) {
			echo self::badge( $order ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in badge().
		}
	}

	/**
	 * Print the column (classic).
	 *
	 * @param string $column  Column.
	 * @param int    $post_id Order ID.
	 */
	public static function column_legacy( $column, $post_id ) {
		if ( 'cartiva_source' !== $column ) {
			return;
		}
		$order = wc_get_order( $post_id );
		if ( $order ) {
			echo self::badge( $order ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in badge().
		}
	}

	/**
	 * Choices of the Source filter: value => label.
	 *
	 * @return array
	 */
	public static function filter_choices() {
		$choices = array(
			''        => __( 'All sources', 'cartiva-commerce' ),
			'main'    => __( 'Main site only', 'cartiva-commerce' ),
			'cartiva' => __( 'Landing pages and funnels', 'cartiva-commerce' ),
		);
		$funnels = get_posts(
			array(
				'post_type'      => CARTIVA_COMMERCE_FUNNEL,
				'post_status'    => 'publish',
				'posts_per_page' => 50,
				'orderby'        => 'title',
				'order'          => 'ASC',
			)
		);
		foreach ( $funnels as $funnel ) {
			/* translators: %s: funnel name. */
			$choices[ 'f' . $funnel->ID ] = sprintf( __( 'Funnel: %s', 'cartiva-commerce' ), $funnel->post_title );
		}
		$landings = get_posts(
			array(
				'post_type'      => CARTIVA_COMMERCE_LANDING,
				'post_status'    => 'publish',
				'posts_per_page' => 100,
				'orderby'        => 'title',
				'order'          => 'ASC',
			)
		);
		foreach ( $landings as $landing ) {
			if ( ! cartiva_commerce_funnel_of( $landing->ID ) ) {
				/* translators: %s: landing page name. */
				$choices[ 'l' . $landing->ID ] = sprintf( __( 'Landing page: %s', 'cartiva-commerce' ), $landing->post_title );
			}
		}
		return $choices;
	}

	/**
	 * The chosen filter value.
	 *
	 * @return string
	 */
	public static function current_filter() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- List filter.
		return isset( $_GET['cartiva_source'] ) ? sanitize_key( wp_unslash( $_GET['cartiva_source'] ) ) : '';
	}

	/**
	 * Print the Source filter.
	 */
	public static function filter_dropdown() {
		$current = self::current_filter();
		echo '<label class="screen-reader-text" for="cartiva-source-filter">' . esc_html__( 'Filter by source', 'cartiva-commerce' ) . '</label>';
		echo '<select name="cartiva_source" id="cartiva-source-filter">';
		foreach ( self::filter_choices() as $value => $label ) {
			echo '<option value="' . esc_attr( $value ) . '"' . selected( $current, $value, false ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select>';
	}

	/**
	 * Print the Source filter on the classic orders screen.
	 *
	 * @param string $post_type Post type.
	 */
	public static function filter_dropdown_legacy( $post_type ) {
		if ( 'shop_order' === $post_type ) {
			self::filter_dropdown();
		}
	}

	/**
	 * Meta query for a filter value.
	 *
	 * @param string $value Filter value.
	 * @return array|null
	 */
	public static function meta_query_for( $value ) {
		if ( 'main' === $value ) {
			return array(
				'key'     => '_cartiva_landing_id',
				'compare' => 'NOT EXISTS',
			);
		}
		if ( 'cartiva' === $value ) {
			return array(
				'key'     => '_cartiva_landing_id',
				'compare' => 'EXISTS',
			);
		}
		if ( preg_match( '/^l(\d+)$/', $value, $m ) ) {
			$ids = array( (int) $m[1] );
		} elseif ( preg_match( '/^f(\d+)$/', $value, $m ) ) {
			$ids = Cartiva_Commerce_Analytics::funnel_pages( (int) $m[1] )['all'];
		} else {
			return null;
		}
		return array(
			'key'     => '_cartiva_landing_id',
			'value'   => $ids ? array_map( 'strval', $ids ) : array( '0' ),
			'compare' => 'IN',
		);
	}

	/**
	 * Apply the filter (HPOS).
	 *
	 * @param array $args Query args.
	 * @return array
	 */
	public static function filter_hpos( $args ) {
		$meta = self::meta_query_for( self::current_filter() );
		if ( $meta ) {
			$args['meta_query']   = isset( $args['meta_query'] ) && is_array( $args['meta_query'] ) ? $args['meta_query'] : array(); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Admin list filter.
			$args['meta_query'][] = $meta;
		}
		return $args;
	}

	/**
	 * Apply the filter (classic).
	 *
	 * @param WP_Query $query Query.
	 */
	public static function filter_legacy( $query ) {
		if ( ! is_admin() || ! $query->is_main_query() || 'shop_order' !== $query->get( 'post_type' ) ) {
			return;
		}
		$meta = self::meta_query_for( self::current_filter() );
		if ( $meta ) {
			$existing   = $query->get( 'meta_query' );
			$existing   = is_array( $existing ) ? $existing : array();
			$existing[] = $meta;
			$query->set( 'meta_query', $existing );
		}
	}

	/**
	 * Box on the order screen.
	 */
	public static function meta_box() {
		$screens = array( 'shop_order' );
		if ( function_exists( 'wc_get_page_screen_id' ) ) {
			$screens[] = wc_get_page_screen_id( 'shop-order' );
		}
		foreach ( array_unique( $screens ) as $screen ) {
			add_meta_box( 'cartiva-order-source', __( 'Cartiva Commerce', 'cartiva-commerce' ), array( __CLASS__, 'render_box' ), $screen, 'side', 'default' );
		}
	}

	/**
	 * Order screen box.
	 *
	 * @param WP_Post|WC_Order $post_or_order Order or its post.
	 */
	public static function render_box( $post_or_order ) {
		$order = $post_or_order instanceof WC_Order ? $post_or_order : wc_get_order( $post_or_order->ID );
		if ( ! $order ) {
			return;
		}
		$source = self::source( $order );
		echo '<p>' . self::badge( $order ) . '</p>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in badge().
		if ( 'main' === $source['type'] ) {
			echo '<p class="description">' . esc_html__( 'Placed through the main shop and checkout.', 'cartiva-commerce' ) . '</p>';
			return;
		}
		echo '<ul class="cartiva-order-facts">';
		if ( $source['funnel'] ) {
			echo '<li><span>' . esc_html__( 'Funnel', 'cartiva-commerce' ) . '</span> <a href="' . esc_url( (string) get_edit_post_link( $source['funnel'] ) ) . '">' . esc_html( get_the_title( $source['funnel'] ) ) . '</a></li>';
		}
		echo '<li><span>' . esc_html__( 'Page', 'cartiva-commerce' ) . '</span> <a href="' . esc_url( (string) get_edit_post_link( $source['landing'] ) ) . '">' . esc_html( get_the_title( $source['landing'] ) ) . '</a>' . ( '' !== $source['step'] ? ' (' . esc_html( $source['step'] ) . ')' : '' ) . '</li>';
		$offers = $order->get_meta( '_cartiva_offers' );
		if ( is_array( $offers ) && $offers ) {
			foreach ( $offers as $step => $answer ) {
				echo '<li><span>' . esc_html( get_the_title( (int) $step ) ) . '</span> ' . esc_html( 'yes' === $answer ? __( 'accepted', 'cartiva-commerce' ) : __( 'declined', 'cartiva-commerce' ) ) . '</li>';
			}
		}
		foreach ( $order->get_items() as $item ) {
			if ( '' !== (string) $item->get_meta( '_cartiva_bump' ) ) {
				echo '<li><span>' . esc_html__( 'Order bump', 'cartiva-commerce' ) . '</span> ' . esc_html( $item->get_name() ) . '</li>';
			}
			if ( '' !== (string) $item->get_meta( '_cartiva_gift' ) ) {
				echo '<li><span>' . esc_html__( 'Gift', 'cartiva-commerce' ) . '</span> ' . esc_html( $item->get_name() ) . '</li>';
			}
		}
		$delivery = (string) $order->get_meta( '_cartiva_delivery' );
		if ( '' !== $delivery ) {
			echo '<li><span>' . esc_html__( 'Delivery', 'cartiva-commerce' ) . '</span> ' . esc_html( $delivery ) . '</li>';
		}
		echo '</ul>';
		$risk = (string) $order->get_meta( '_cartiva_risk' );
		if ( '' !== $risk ) {
			echo '<p class="cartiva-order-risk">' . esc_html( $risk ) . '</p>';
		}
	}
}
