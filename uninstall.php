<?php
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

delete_option( 'wc_ptt_kargo_settings' );
delete_option( 'wc_ptt_kargo_barcode_cursor' );

// Logs table is dropped on uninstall, as it can be re-created if the plugin is re-installed.
$wpdb->query( 'DROP TABLE IF EXISTS ' . $wpdb->prefix . 'wc_ptt_kargo_logs' );
