<?php
/**
 * Uninstall: remove the plugin's settings and the saved abandoned checkouts
 * (shoppers' personal data). Landing pages, funnels and order data stay,
 * because they are the store's content and order history.
 *
 * @package Cartiva_Commerce
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'cartiva_commerce_settings' );
delete_option( 'cartiva_commerce_flush_rewrite' );
wp_clear_scheduled_hook( 'cartiva_commerce_recovery_cron' );
wp_clear_scheduled_hook( 'cartiva_commerce_courier_sync' );
wp_clear_scheduled_hook( 'cartiva_commerce_ledger_backfill' );
foreach ( array( 'cartiva_commerce_db_version', 'cartiva_commerce_ledger_ready', 'cartiva_commerce_ledger_offset', 'cartiva_commerce_show_setup' ) as $cartiva_commerce_option ) {
	delete_option( $cartiva_commerce_option );
}

// The report table only holds totals copied from orders; it is rebuilt if the plugin comes back.
global $wpdb;
$wpdb->query( 'DROP TABLE IF EXISTS ' . $wpdb->prefix . 'cartiva_order_stats' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange -- Our own table on uninstall.

$cartiva_commerce_entries = get_posts(
	array(
		'post_type'      => 'cartiva_recovery',
		'post_status'    => 'any',
		'posts_per_page' => -1,
		'fields'         => 'ids',
	)
);
foreach ( $cartiva_commerce_entries as $cartiva_commerce_entry ) {
	wp_delete_post( (int) $cartiva_commerce_entry, true );
}
