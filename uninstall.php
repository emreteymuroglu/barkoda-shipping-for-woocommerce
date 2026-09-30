<?php
/**
 * Removes everything the plugin stored.
 *
 * The plugin has been renamed twice, so all three generations of identifiers are
 * cleaned up: a site uninstalling before it ever ran a migration would otherwise
 * leave orphaned options, a log table and order meta behind.
 *
 * @package Barkoda_Shipping
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- removing the plugin's own table and order meta on uninstall; there is nothing to cache.

global $wpdb;

$barkoda_options = array(
	// Current.
	'barkoda_settings',
	'barkoda_barcode_cursor',
	'barkoda_db_version',
	// 2.x, under the ptt-kargo-for-woocommerce slug.
	'ptt_kargo_wc_settings',
	'ptt_kargo_wc_barcode_cursor',
	'ptt_kargo_wc_db_version',
	// 1.x, under the wc-ptt-kargo slug.
	'wc_ptt_kargo_settings',
	'wc_ptt_kargo_barcode_cursor',
);
foreach ( $barkoda_options as $barkoda_option ) {
	delete_option( $barkoda_option );
}

// The log table is dropped; it is re-created from scratch on reinstall.
foreach ( array( 'barkoda_logs', 'ptt_kargo_wc_logs', 'wc_ptt_kargo_logs' ) as $barkoda_table ) {
	$barkoda_full = $wpdb->prefix . $barkoda_table;
	$wpdb->query( "DROP TABLE IF EXISTS `{$barkoda_full}`" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- table name cannot be parameterised.
}

// Order meta, under the current prefix and both historical ones.
$barkoda_tables = array( $wpdb->postmeta );
$barkoda_hpos   = $wpdb->prefix . 'wc_orders_meta';
if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $barkoda_hpos ) ) === $barkoda_hpos ) {
	$barkoda_tables[] = $barkoda_hpos;
}

foreach ( $barkoda_tables as $barkoda_meta_table ) {
	foreach ( array( '_barkoda_', '_ptt_kargo_wc_', '_wc_ptt_kargo_' ) as $barkoda_prefix ) {
		$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- table name is derived from $wpdb, values are prepared.
			$wpdb->prepare(
				"DELETE FROM `{$barkoda_meta_table}` WHERE meta_key LIKE %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name cannot be parameterised.
				$wpdb->esc_like( $barkoda_prefix ) . '%'
			)
		);
	}
}
