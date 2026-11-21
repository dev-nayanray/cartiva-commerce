<?php
/**
 * Uninstall: remove the plugin's settings. Landing pages and order data stay,
 * because they are the store's content and order history.
 *
 * @package Cartiva_Commerce
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'cartiva_commerce_settings' );
delete_option( 'cartiva_commerce_flush_rewrite' );
