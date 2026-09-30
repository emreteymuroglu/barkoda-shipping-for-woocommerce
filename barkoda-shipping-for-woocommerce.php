<?php
/**
 * Plugin Name:       Barkoda Shipping for WooCommerce
 * Plugin URI:        https://github.com/emreteymuroglu/barkoda-shipping-for-woocommerce
 * Description:       Creates PTT Kargo shipments from WooCommerce orders over the PTT SOAP API, generates barcodes, prints 80mm thermal labels and tracks deliveries. Not affiliated with or endorsed by PTT.
 * Version:           2.2.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Emre Teymuroglu
 * License:           GPLv2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       barkoda-shipping-for-woocommerce
 * Domain Path:       /languages
 * Requires Plugins: woocommerce
 * WC requires at least: 8.0
 * WC tested up to:   11.1
 *
 * @package Barkoda_Shipping
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'BARKODA_VERSION', '2.2.0' );
define( 'BARKODA_FILE', __FILE__ );
define( 'BARKODA_DIR', plugin_dir_path( __FILE__ ) );
define( 'BARKODA_URL', plugin_dir_url( __FILE__ ) );
define( 'BARKODA_SLUG', 'barkoda-shipping-for-woocommerce' );

if ( version_compare( PHP_VERSION, '7.4', '<' ) ) {
	add_action(
		'admin_notices',
		static function () {
			echo '<div class="notice notice-error"><p>';
			echo esc_html(
				sprintf(
					/* translators: %s: PHP version running on the server. */
					__( 'Barkoda Shipping for WooCommerce requires PHP 7.4 or newer. This server runs %s.', 'barkoda-shipping-for-woocommerce' ),
					PHP_VERSION
				)
			);
			echo '</p></div>';
		}
	);
	return;
}

add_action(
	'before_woocommerce_init',
	static function () {
		if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
				'custom_order_tables',
				BARKODA_FILE,
				true
			);
		}
	}
);

function barkoda_load_classes() {
	$files = [
		'class-logs.php',
		'class-upgrade.php',
		'class-settings.php',
		'class-barcode.php',
		'class-ptt-client.php',
		'class-orders.php',
		'class-label.php',
		'class-admin-page.php',
		'class-wc-integration.php',
		'class-customer-tracking.php',
		'class-plugin.php',
	];
	foreach ( $files as $f ) {
		$path = BARKODA_DIR . 'includes/' . $f;
		if ( file_exists( $path ) ) {
			require_once $path;
		}
	}
}

add_action(
	'plugins_loaded',
	static function () {
		if ( ! class_exists( 'WooCommerce' ) ) {
			add_action(
				'admin_notices',
				static function () {
					echo '<div class="notice notice-error"><p>';
					echo esc_html__( 'Barkoda Shipping for WooCommerce requires WooCommerce to be installed and active.', 'barkoda-shipping-for-woocommerce' );
					echo '</p></div>';
				}
			);
			return;
		}

		barkoda_load_classes();

		if ( class_exists( '\Barkoda_Shipping\Plugin' ) ) {
			\Barkoda_Shipping\Plugin::instance()->boot();
		}
	}
);

register_activation_hook(
	__FILE__,
	static function () {
		barkoda_load_classes();
		// Migrations first. install_table() would otherwise create the current log
		// table before the migration runs, and the migration, finding both the old
		// and the new table present, would treat the old one as an orphan and drop
		// it along with every row a previous version had written.
		if ( class_exists( '\Barkoda_Shipping\Plugin' ) ) {
			\Barkoda_Shipping\Plugin::on_activation();
		}
		if ( class_exists( '\Barkoda_Shipping\Logs' ) ) {
			\Barkoda_Shipping\Logs::install_table();
		}
	}
);
