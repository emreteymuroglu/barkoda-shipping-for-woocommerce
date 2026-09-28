<?php
namespace PTT_Kargo_WC;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Plugin {
	/** @var Plugin|null */
	private static $instance = null;

	private $settings;
	private $orders;
	private $barcode;
	private $client;
	private $admin_page;
	private $label;
	private $wc_integration;

	public static function instance() {
		if ( self::$instance === null ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		$this->settings       = new Settings();
		$this->barcode        = new Barcode( $this->settings );
		$this->client         = new PTT_Client( $this->settings );
		$this->orders         = new Orders( $this->settings );
		$this->label          = new Label( $this->settings );
		$this->admin_page     = new Admin_Page( $this->settings, $this->orders, $this->barcode, $this->client, $this->label );
		$this->wc_integration = new WC_Integration( $this->settings, $this->orders, $this->barcode, $this->client, $this->label );
	}

	public function boot() {
		load_plugin_textdomain( 'ptt-kargo-for-woocommerce', false, dirname( plugin_basename( PTT_KARGO_WC_FILE ) ) . '/languages' );

		$this->settings->register();
		$this->admin_page->register();
		$this->label->register();
		$this->wc_integration->register();
	}

	public static function on_activation() {
		if ( get_option( Settings::OPTION_KEY, false ) === false ) {
			add_option( Settings::OPTION_KEY, Settings::defaults() );
		}
		// Pre-initialise the barcode cursor so the first shipment cannot hit a race.
		// add_option() is idempotent. The default '0' makes Barcode::next() jump to the
		// configured range_start on its first call.
		add_option( Barcode::CURSOR_OPTION, '0', '', 'no' );
	}

	public function settings() {
		return $this->settings; }
	public function barcode() {
		return $this->barcode; }
	public function client() {
		return $this->client; }
	public function orders() {
		return $this->orders; }
	public function label() {
		return $this->label; }
	public function wc_integration() {
		return $this->wc_integration; }
}
