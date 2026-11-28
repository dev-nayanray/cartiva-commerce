<?php
/**
 * Order ledger: one small row per landing page or funnel order, kept up to
 * date as orders are saved, so reports add numbers up in the database
 * instead of loading every order. Existing orders are added in the
 * background, 200 at a time.
 *
 * @package Cartiva_Commerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Ledger.
 */
class Cartiva_Commerce_Ledger {

	const DB_VERSION = '1';
	const BACKFILL   = 'cartiva_commerce_ledger_backfill';
	const BATCH      = 200;

	/**
	 * Order statuses that count as sales.
	 *
	 * @var string[]
	 */
	public static $counted = array( 'processing', 'completed', 'on-hold' );

	/**
	 * Hook up.
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'maybe_install' ), 5 );
		add_action( 'woocommerce_after_order_object_save', array( __CLASS__, 'record' ) );
		add_action( 'woocommerce_delete_order', array( __CLASS__, 'forget' ) );
		add_action( 'woocommerce_trash_order', array( __CLASS__, 'forget' ) );
		add_action( 'deleted_post', array( __CLASS__, 'forget' ) );
		add_action( self::BACKFILL, array( __CLASS__, 'backfill' ) );
	}

	/**
	 * Table name.
	 *
	 * @return string
	 */
	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'cartiva_order_stats';
	}

	/**
	 * Create or update the table, then start adding older orders.
	 */
	public static function maybe_install() {
		if ( self::DB_VERSION === get_option( 'cartiva_commerce_db_version' ) ) {
			return;
		}
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$table   = self::table();
		$charset = $wpdb->get_charset_collate();
		dbDelta(
			"CREATE TABLE {$table} (
				order_id bigint(20) unsigned NOT NULL,
				landing_id bigint(20) unsigned NOT NULL DEFAULT 0,
				status varchar(20) NOT NULL DEFAULT '',
				created int(10) unsigned NOT NULL DEFAULT 0,
				day date NOT NULL DEFAULT '1970-01-01',
				total decimal(19,4) NOT NULL DEFAULT 0,
				bump tinyint(1) unsigned NOT NULL DEFAULT 0,
				bump_revenue decimal(19,4) NOT NULL DEFAULT 0,
				upsell_revenue decimal(19,4) NOT NULL DEFAULT 0,
				upsell_yes smallint(5) unsigned NOT NULL DEFAULT 0,
				upsell_no smallint(5) unsigned NOT NULL DEFAULT 0,
				PRIMARY KEY  (order_id),
				KEY landing_created (landing_id,created),
				KEY status (status)
			) {$charset};"
		);
		update_option( 'cartiva_commerce_db_version', self::DB_VERSION, true );
		update_option( 'cartiva_commerce_ledger_offset', 0, false );
		delete_option( 'cartiva_commerce_ledger_ready' );
		if ( ! wp_next_scheduled( self::BACKFILL ) ) {
			wp_schedule_single_event( time() + 30, self::BACKFILL );
		}
	}

	/**
	 * Is every older order in the ledger?
	 *
	 * @return bool
	 */
	public static function ready() {
		return (bool) get_option( 'cartiva_commerce_ledger_ready' );
	}

	/**
	 * The ledger row of an order (null for main-site orders).
	 *
	 * @param WC_Order $order Order.
	 * @return array|null
	 */
	public static function row( $order ) {
		$landing = (int) $order->get_meta( '_cartiva_landing_id' );
		if ( ! $landing ) {
			return null;
		}
		$created = $order->get_date_created();
		$row     = array(
			'order_id'       => (int) $order->get_id(),
			'landing_id'     => $landing,
			'status'         => (string) $order->get_status(),
			'created'        => $created ? (int) $created->getTimestamp() : time(),
			'day'            => $created ? $created->date_i18n( 'Y-m-d' ) : gmdate( 'Y-m-d' ),
			'total'          => (float) $order->get_total(),
			'bump'           => 0,
			'bump_revenue'   => 0.0,
			'upsell_revenue' => 0.0,
			'upsell_yes'     => 0,
			'upsell_no'      => 0,
		);
		foreach ( $order->get_items() as $item ) {
			if ( '' !== (string) $item->get_meta( '_cartiva_bump' ) ) {
				$row['bump']          = 1;
				$row['bump_revenue'] += (float) $item->get_total();
			}
			if ( '' !== (string) $item->get_meta( '_cartiva_upsell' ) ) {
				$row['upsell_revenue'] += (float) $item->get_total();
			}
		}
		$answers = $order->get_meta( '_cartiva_offers' );
		foreach ( is_array( $answers ) ? $answers : array() as $answer ) {
			++$row[ 'yes' === $answer ? 'upsell_yes' : 'upsell_no' ];
		}
		return $row;
	}

	/**
	 * Keep an order's row up to date (on every order save).
	 *
	 * @param WC_Order $order Order.
	 */
	public static function record( $order ) {
		if ( ! $order instanceof WC_Order || 'shop_order_refund' === $order->get_type() ) {
			return;
		}
		global $wpdb;
		$row = self::row( $order );
		if ( null === $row ) {
			return;
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Our own table.
		$wpdb->replace( self::table(), $row, array( '%d', '%d', '%s', '%d', '%s', '%f', '%d', '%f', '%f', '%d', '%d' ) );
		self::flush_cache();
	}

	/**
	 * Remove a deleted order's row.
	 *
	 * @param int $order_id Order ID.
	 */
	public static function forget( $order_id ) {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Our own table.
		if ( $wpdb->delete( self::table(), array( 'order_id' => (int) $order_id ), array( '%d' ) ) ) {
			self::flush_cache();
		}
	}

	/**
	 * Reports changed.
	 */
	public static function flush_cache() {
		$gen = (int) wp_cache_get( 'cartiva_ledger_gen', 'cartiva_commerce' );
		wp_cache_set( 'cartiva_ledger_gen', $gen + 1, 'cartiva_commerce' );
	}

	/**
	 * Add older orders, a batch at a time.
	 */
	public static function backfill() {
		$offset = (int) get_option( 'cartiva_commerce_ledger_offset', 0 );
		$ids    = wc_get_orders(
			array(
				'limit'      => self::BATCH,
				'offset'     => $offset,
				'return'     => 'ids',
				'type'       => 'shop_order',
				'status'     => array_keys( wc_get_order_statuses() ),
				'orderby'    => 'ID',
				'order'      => 'ASC',
				'meta_query' => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Background job, once.
					array(
						'key'     => '_cartiva_landing_id',
						'compare' => 'EXISTS',
					),
				),
			)
		);
		$ids    = is_array( $ids ) ? $ids : array();
		foreach ( $ids as $id ) {
			$order = wc_get_order( $id );
			if ( $order ) {
				self::record( $order );
			}
		}
		if ( count( $ids ) < self::BATCH ) {
			update_option( 'cartiva_commerce_ledger_ready', 1, true );
			delete_option( 'cartiva_commerce_ledger_offset' );
			return;
		}
		update_option( 'cartiva_commerce_ledger_offset', $offset + self::BATCH, false );
		wp_schedule_single_event( time() + 5, self::BACKFILL );
	}

	/**
	 * Report numbers for some landing pages, added up in the database.
	 *
	 * @param int[] $landing_ids Landing page IDs.
	 * @param int   $from        Start timestamp (0 = all time).
	 * @param int   $to          End timestamp (0 = now).
	 * @return array Same keys as Cartiva_Commerce_Analytics::summarize().
	 */
	public static function summary( $landing_ids, $from = 0, $to = 0 ) {
		$sum         = array(
			'orders'         => 0,
			'revenue'        => 0.0,
			'bump_orders'    => 0,
			'bump_revenue'   => 0.0,
			'upsell_yes'     => 0,
			'upsell_no'      => 0,
			'upsell_revenue' => 0.0,
			'days'           => array(),
		);
		$landing_ids = array_values( array_unique( array_filter( array_map( 'intval', (array) $landing_ids ) ) ) );
		if ( ! $landing_ids ) {
			return $sum;
		}
		$to        = $to > 0 ? $to : time();
		$gen       = (string) wp_cache_get( 'cartiva_ledger_gen', 'cartiva_commerce' );
		$cache_key = 'sum_' . md5( implode( ',', $landing_ids ) . "|{$from}|{$to}|{$gen}" );
		$cached    = wp_cache_get( $cache_key, 'cartiva_commerce' );
		if ( is_array( $cached ) ) {
			return $cached;
		}
		global $wpdb;
		$table    = self::table();
		$ids_in   = implode( ',', array_fill( 0, count( $landing_ids ), '%d' ) );
		$stat_in  = implode( ',', array_fill( 0, count( self::$counted ), '%s' ) );
		$args     = array_merge( $landing_ids, self::$counted, array( (int) $from, (int) $to ) );
		$where    = "landing_id IN ({$ids_in}) AND status IN ({$stat_in}) AND created >= %d AND created <= %d";
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- Our own table; placeholders built above; cached.
		$total = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT COUNT(*) AS orders, COALESCE(SUM(total),0) AS revenue, COALESCE(SUM(bump),0) AS bump_orders,
					COALESCE(SUM(bump_revenue),0) AS bump_revenue, COALESCE(SUM(upsell_revenue),0) AS upsell_revenue,
					COALESCE(SUM(upsell_yes),0) AS upsell_yes, COALESCE(SUM(upsell_no),0) AS upsell_no
				FROM {$table} WHERE {$where}",
				$args
			),
			ARRAY_A
		);
		$days  = $wpdb->get_results(
			$wpdb->prepare( "SELECT day, SUM(total) AS revenue FROM {$table} WHERE {$where} GROUP BY day ORDER BY day", $args ),
			ARRAY_A
		);
		// phpcs:enable
		if ( is_array( $total ) ) {
			foreach ( array( 'orders', 'bump_orders', 'upsell_yes', 'upsell_no' ) as $key ) {
				$sum[ $key ] = (int) $total[ $key ];
			}
			foreach ( array( 'revenue', 'bump_revenue', 'upsell_revenue' ) as $key ) {
				$sum[ $key ] = (float) $total[ $key ];
			}
		}
		foreach ( (array) $days as $day ) {
			$sum['days'][ (string) $day['day'] ] = (float) $day['revenue'];
		}
		wp_cache_set( $cache_key, $sum, 'cartiva_commerce', 5 * MINUTE_IN_SECONDS );
		return $sum;
	}
}
