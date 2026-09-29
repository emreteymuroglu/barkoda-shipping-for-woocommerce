<?php
/**
 * Removes everything the plugin stored.
 *
 * Both the current and the pre-rename identifiers are cleaned up, so uninstalling a
 * site that never ran the migration does not leave orphaned rows behind.
 *
 * @package PTT_Kargo_WC
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- removing the plugin's own table and order meta on uninstall; there is nothing to cache.

global $wpdb;

foreach ( array( 'ptt_kargo_wc_settings', 'ptt_kargo_wc_barcode_cursor', 'ptt_kargo_wc_db_version', 'wc_ptt_kargo_settings', 'wc_ptt_kargo_barcode_cursor' ) as $ptt_kargo_wc_option ) {
	delete_option( $ptt_kargo_wc_option );
}

// The log table is dropped; it is re-created from scratch on reinstall.
foreach ( array( 'ptt_kargo_wc_logs', 'wc_ptt_kargo_logs' ) as $ptt_kargo_wc_table ) {
	$ptt_kargo_wc_full = $wpdb->prefix . $ptt_kargo_wc_table;
	$wpdb->query( "DROP TABLE IF EXISTS `{$ptt_kargo_wc_full}`" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- table name cannot be parameterised.
}

// Order meta, under both the current and the legacy prefix.
$ptt_kargo_wc_tables = array( $wpdb->postmeta );
$ptt_kargo_wc_hpos   = $wpdb->prefix . 'wc_orders_meta';
if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $ptt_kargo_wc_hpos ) ) === $ptt_kargo_wc_hpos ) {
	$ptt_kargo_wc_tables[] = $ptt_kargo_wc_hpos;
}

foreach ( $ptt_kargo_wc_tables as $ptt_kargo_wc_meta_table ) {
	foreach ( array( '_ptt_kargo_wc_', '_wc_ptt_kargo_' ) as $ptt_kargo_wc_prefix ) {
		$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- table name is derived from $wpdb, values are prepared.
			$wpdb->prepare(
				"DELETE FROM `{$ptt_kargo_wc_meta_table}` WHERE meta_key LIKE %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name cannot be parameterised.
				$wpdb->esc_like( $ptt_kargo_wc_prefix ) . '%'
			)
		);
	}
}
