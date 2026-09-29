<?php
/**
 * Plugin Name:       Barkoda Shipping for WooCommerce
 * Plugin URI:        https://github.com/emreteymuroglu/barkoda-shipping-for-woocommerce
 * Description:       Creates PTT Kargo shipments from WooCommerce orders over the PTT SOAP API, generates barcodes, prints 80mm thermal labels and tracks deliveries. Not affiliated with or endorsed by PTT.
 * Version:           2.1.0
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
 * @package PTT_Kargo_WC
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'PTT_KARGO_WC_VERSION', '2.1.0' );
define( 'PTT_KARGO_WC_FILE', __FILE__ );
define( 'PTT_KARGO_WC_DIR', plugin_dir_path( __FILE__ ) );
define( 'PTT_KARGO_WC_URL', plugin_dir_url( __FILE__ ) );
define( 'PTT_KARGO_WC_SLUG', 'barkoda-shipping-for-woocommerce' );

if ( version_compare( PHP_VERSION, '7.4', '<' ) ) {
	add_action(
		'admin_notices',
		static function () {
			echo '<div class="notice notice-error"><p>';
			echo esc_html( sprintf( 'WC PTT Kargo en az PHP 7.4 gerektirir. Sunucunuz: %s', PHP_VERSION ) );
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
				PTT_KARGO_WC_FILE,
				true
			);
		}
	}
);

function ptt_kargo_wc_load_classes() {
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
		$path = PTT_KARGO_WC_DIR . 'includes/' . $f;
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

		ptt_kargo_wc_load_classes();

		if ( class_exists( '\PTT_Kargo_WC\Plugin' ) ) {
			\PTT_Kargo_WC\Plugin::instance()->boot();
		}
	}
);

register_activation_hook(
	__FILE__,
	static function () {
		ptt_kargo_wc_load_classes();
		if ( class_exists( '\PTT_Kargo_WC\Logs' ) ) {
			\PTT_Kargo_WC\Logs::install_table();
		}
		if ( class_exists( '\PTT_Kargo_WC\Plugin' ) ) {
			\PTT_Kargo_WC\Plugin::on_activation();
		}
	}
);
