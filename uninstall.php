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
