<?php
namespace PTT_Kargo_WC;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin menu pages and the AJAX endpoints behind them.
 */
final class Admin_Page {
	public const MENU_SLUG       = 'ptt-kargo-for-woocommerce';
	public const CAPABILITY      = 'manage_woocommerce';
	public const AJAX_SEND       = 'ptt_kargo_wc_send';
	public const AJAX_REFRESH    = 'ptt_kargo_wc_refresh';
	public const AJAX_TAKIP      = 'ptt_kargo_wc_takip';
	public const AJAX_PREPARE    = 'ptt_kargo_wc_prepare';
	public const AJAX_TEST       = 'ptt_kargo_wc_test_conn';
	public const AJAX_LOGS       = 'ptt_kargo_wc_clear_logs';
	public const AJAX_CANCEL     = 'ptt_kargo_wc_cancel';
	public const AJAX_COURIER    = 'ptt_kargo_wc_courier';
	public const AJAX_DROP_POINT = 'ptt_kargo_wc_drop_point';
	public const NONCE_ACTION    = 'ptt_kargo_wc';

	private Settings $settings;
	private Orders $orders;
	private Barcode $barcode;
	private PTT_Client $client;
	private Label $label;

	public function __construct( Settings $settings, Orders $orders, Barcode $barcode, PTT_Client $client, Label $label ) {
		$this->settings = $settings;
		$this->orders   = $orders;
		$this->barcode  = $barcode;
		$this->client   = $client;
		$this->label    = $label;
	}

	public function register(): void {
		add_action( 'admin_menu', [ $this, 'menu' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'assets' ] );

		add_action( 'wp_ajax_' . self::AJAX_SEND, [ $this, 'ajax_send' ] );
		add_action( 'wp_ajax_' . self::AJAX_REFRESH, [ $this, 'ajax_refresh' ] );
		add_action( 'wp_ajax_' . self::AJAX_TAKIP, [ $this, 'ajax_takip' ] );
		add_action( 'wp_ajax_' . self::AJAX_PREPARE, [ $this, 'ajax_prepare' ] );
		add_action( 'wp_ajax_' . self::AJAX_TEST, [ $this, 'ajax_test_connection' ] );
		add_action( 'wp_ajax_' . self::AJAX_LOGS, [ $this, 'ajax_clear_logs' ] );
		add_action( 'wp_ajax_' . self::AJAX_CANCEL, [ $this, 'ajax_cancel' ] );
		add_action( 'wp_ajax_' . self::AJAX_COURIER, [ $this, 'ajax_courier' ] );
		add_action( 'wp_ajax_' . self::AJAX_DROP_POINT, [ $this, 'ajax_drop_point' ] );
	}

	public function menu(): void {
		add_menu_page(
			__( 'PTT Kargo for WooCommerce', 'ptt-kargo-for-woocommerce' ),
			__( 'PTT Kargo', 'ptt-kargo-for-woocommerce' ),
			self::CAPABILITY,
			self::MENU_SLUG,
			[ $this, 'render_orders_page' ],
			'dashicons-archive',
			58
		);

		add_submenu_page(
			self::MENU_SLUG,
			__( 'Orders', 'ptt-kargo-for-woocommerce' ),
			__( 'Orders', 'ptt-kargo-for-woocommerce' ),
			self::CAPABILITY,
			self::MENU_SLUG,
			[ $this, 'render_orders_page' ]
		);

		add_submenu_page(
			self::MENU_SLUG,
			__( 'Request Courier', 'ptt-kargo-for-woocommerce' ),
			__( 'Request Courier', 'ptt-kargo-for-woocommerce' ),
			self::CAPABILITY,
			self::MENU_SLUG . '-kurye',
			[ $this, 'render_courier_page' ]
		);

		add_submenu_page(
			self::MENU_SLUG,
			__( 'Settings', 'ptt-kargo-for-woocommerce' ),
			__( 'Settings', 'ptt-kargo-for-woocommerce' ),
			self::CAPABILITY,
			self::MENU_SLUG . '-settings',
			[ $this, 'render_settings_page' ]
		);

		add_submenu_page(
			self::MENU_SLUG,
			__( 'PTT Logs', 'ptt-kargo-for-woocommerce' ),
			__( 'Logs', 'ptt-kargo-for-woocommerce' ),
			self::CAPABILITY,
			self::MENU_SLUG . '-logs',
			[ $this, 'render_logs_page' ]
		);
	}

	public function assets( string $hook ): void {
		// The admin hook string differs per WC/WP version and could not be pinned down on the
		// HPOS edit screen. The JS handlers are delegated from `document` anyway, so enqueuing
		// everywhere is the reliable option; ~50KB of CSS+JS, admin-only.
		wp_enqueue_style( 'ptt-kargo-for-woocommerce-admin', PTT_KARGO_WC_URL . 'admin/assets/admin.css', [], PTT_KARGO_WC_VERSION );
		wp_enqueue_script( 'ptt-kargo-for-woocommerce-admin', PTT_KARGO_WC_URL . 'admin/assets/admin.js', [ 'jquery' ], PTT_KARGO_WC_VERSION, true );

		// Settings page only: live preview, media library, connection test, product search.
		$is_plugin_page = strpos( $hook, self::MENU_SLUG ) !== false;
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- only decides which admin screen assets to load.
		$is_settings    = isset( $_GET['page'] ) && sanitize_key( wp_unslash( $_GET['page'] ) ) === self::MENU_SLUG . '-settings';
		if ( $is_plugin_page && $is_settings ) {
			wp_enqueue_media();
			// WooCommerce enhanced select, used by the product search widget.
			if ( wp_script_is( 'wc-enhanced-select', 'registered' ) ) {
				wp_enqueue_script( 'wc-enhanced-select' );
				wp_enqueue_style( 'woocommerce_admin_styles' );
			}
			wp_enqueue_script(
				'ptt-kargo-for-woocommerce-settings',
				PTT_KARGO_WC_URL . 'admin/assets/settings.js',
				[ 'jquery', 'ptt-kargo-for-woocommerce-admin' ],
				PTT_KARGO_WC_VERSION,
				true
			);
		}
		wp_localize_script(
			'ptt-kargo-for-woocommerce-admin',
			'PttKargoWC',
			[
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( self::NONCE_ACTION ),
				'actions' => [
					'send'        => self::AJAX_SEND,
					'refresh'     => self::AJAX_REFRESH,
					'takip'       => self::AJAX_TAKIP,
					'prepare'     => self::AJAX_PREPARE,
					'cancelKargo' => self::AJAX_CANCEL,
					'courier'     => self::AJAX_COURIER,
					'dropPoint'   => self::AJAX_DROP_POINT,
					'clearLogs'   => self::AJAX_LOGS,
				],
				'i18n'    => [
					'sending'         => __( 'Sending...', 'ptt-kargo-for-woocommerce' ),
					'success'         => __( 'Success! Barcode: ', 'ptt-kargo-for-woocommerce' ),
					'error'           => __( 'Error: ', 'ptt-kargo-for-woocommerce' ),
					'summaryTitle'    => __( 'Shipment Summary', 'ptt-kargo-for-woocommerce' ),
					'customer'        => __( 'Customer', 'ptt-kargo-for-woocommerce' ),
					'missingWarn'     => __( 'Some fields are missing. You can leave them empty or fill them in below.', 'ptt-kargo-for-woocommerce' ),
					'confirm'         => __( 'Confirm and Send', 'ptt-kargo-for-woocommerce' ),
					'cancel'          => __( 'Cancel', 'ptt-kargo-for-woocommerce' ),
					'orderWord'       => __( 'order', 'ptt-kargo-for-woocommerce' ),
					'shipBtn'         => __( 'Ship', 'ptt-kargo-for-woocommerce' ),
					'unknownErr'      => __( 'Unknown error', 'ptt-kargo-for-woocommerce' ),
					'serverErr'       => __( 'Server error.', 'ptt-kargo-for-woocommerce' ),
					'prepareErr'      => __( 'Could not prepare the shipment.', 'ptt-kargo-for-woocommerce' ),
					'trackErr'        => __( 'Could not query tracking.', 'ptt-kargo-for-woocommerce' ),
					'trackBarkod'     => __( 'Barcode:', 'ptt-kargo-for-woocommerce' ),
					'trackStatus'     => __( 'Status:', 'ptt-kargo-for-woocommerce' ),
					'trackEvents'     => __( 'Movements:', 'ptt-kargo-for-woocommerce' ),
					'testing'         => __( 'Testing...', 'ptt-kargo-for-woocommerce' ),
					'cancelConfirm'   => __( 'The record sent to PTT for this order will be deleted. The old barcode cannot be reused, and resending the order will consume a new one. Continue?', 'ptt-kargo-for-woocommerce' ),
					'canceling'       => __( 'Cancelling...', 'ptt-kargo-for-woocommerce' ),
					'cancelOk'        => __( 'The PTT shipment was cancelled.', 'ptt-kargo-for-woocommerce' ),
					'cancelErr'       => __( 'Cancellation failed: ', 'ptt-kargo-for-woocommerce' ),
					'cancelBtn'       => __( 'Cancel PTT Shipment', 'ptt-kargo-for-woocommerce' ),
					'insuranceLabel'  => __( 'Send Insured (Valuable Goods)', 'ptt-kargo-for-woocommerce' ),
					'insuranceAmount' => __( 'Insured Value (TRY)', 'ptt-kargo-for-woocommerce' ),
					'codInfo'         => __( 'Cash on delivery active:', 'ptt-kargo-for-woocommerce' ),
					'dropPointTitle'  => __( 'Current PTT branch', 'ptt-kargo-for-woocommerce' ),
					'courierSending'  => __( 'Requesting courier...', 'ptt-kargo-for-woocommerce' ),
					'courierOk'       => __( 'Courier request accepted!', 'ptt-kargo-for-woocommerce' ),
					'courierErr'      => __( 'Courier request failed: ', 'ptt-kargo-for-woocommerce' ),
					'clearLogsAsk'    => __( 'All log records will be deleted. Are you sure?', 'ptt-kargo-for-woocommerce' ),

					// Recipient fields shown in the shipment popup.
					'fieldRecipient'  => __( 'Recipient Name', 'ptt-kargo-for-woocommerce' ),
					'fieldAddress'    => __( 'Address', 'ptt-kargo-for-woocommerce' ),
					'fieldProvince'   => __( 'Province', 'ptt-kargo-for-woocommerce' ),
					'fieldDistrict'   => __( 'District', 'ptt-kargo-for-woocommerce' ),
					'fieldPhone'      => __( 'Phone (10 digits, no leading zero)', 'ptt-kargo-for-woocommerce' ),
					'fieldEmail'      => __( 'Email', 'ptt-kargo-for-woocommerce' ),

					// Per-order shipping overrides.
					'fieldWeight'     => __( 'Weight (g)', 'ptt-kargo-for-woocommerce' ),
					'fieldDesi'       => __( 'Volumetric Weight', 'ptt-kargo-for-woocommerce' ),
					'fieldWidth'      => __( 'Width (cm)', 'ptt-kargo-for-woocommerce' ),
					'fieldLength'     => __( 'Length (cm)', 'ptt-kargo-for-woocommerce' ),
					'fieldHeight'     => __( 'Height (cm)', 'ptt-kargo-for-woocommerce' ),

					// Retry and multi-package controls.
					'retryLabel'      => __( 'Retry:', 'ptt-kargo-for-woocommerce' ),
					'retryNotice'     => __( 'will be reused — no new barcode is consumed.', 'ptt-kargo-for-woocommerce' ),
					'packageCount'    => __( 'Package Count', 'ptt-kargo-for-woocommerce' ),
					'waybillNo'       => __( 'Waybill Number (optional)', 'ptt-kargo-for-woocommerce' ),
					'multiHint'       => __( 'A count above 1 switches to PTT\'s kabulEkleParcaliBarkod service. Each package consumes one barcode from the same range.', 'ptt-kargo-for-woocommerce' ),
					'extraPackages'   => __( 'more packages', 'ptt-kargo-for-woocommerce' ),
					'badPackageCount' => __( 'Enter a valid package count.', 'ptt-kargo-for-woocommerce' ),
					'courierOrderId'  => __( 'Order ID: ', 'ptt-kargo-for-woocommerce' ),

					// Tracking modal.
					'trackTitle'      => __( 'Shipment Tracking', 'ptt-kargo-for-woocommerce' ),
					'trackRefFound'   => __( 'The barcode query returned nothing; the shipment was found by reference number.', 'ptt-kargo-for-woocommerce' ),
					'trackByRef'      => __( 'Queried by reference number.', 'ptt-kargo-for-woocommerce' ),
					'trackNoEvents'   => __( 'No movements yet.', 'ptt-kargo-for-woocommerce' ),
					'trackRecipient'  => __( 'Recipient:', 'ptt-kargo-for-woocommerce' ),
					'trackSender'     => __( 'Sender:', 'ptt-kargo-for-woocommerce' ),
					'trackColDate'    => __( 'Date / Time', 'ptt-kargo-for-woocommerce' ),
					'trackColAction'  => __( 'Action', 'ptt-kargo-for-woocommerce' ),
					'trackColCenter'  => __( 'Branch', 'ptt-kargo-for-woocommerce' ),
					'close'           => __( 'Close', 'ptt-kargo-for-woocommerce' ),

					// Drop point block.
					'dropDeadline'    => __( 'Collection deadline:', 'ptt-kargo-for-woocommerce' ),
					'showOnMap'       => __( 'Show on map', 'ptt-kargo-for-woocommerce' ),
					'dropPointErr'    => __( 'Could not retrieve drop point information: ', 'ptt-kargo-for-woocommerce' ),
					'rawResponse'     => __( 'Raw PTT response', 'ptt-kargo-for-woocommerce' ),
					'rawDropPoint'    => __( 'Raw drop point response', 'ptt-kargo-for-woocommerce' ),

					// Settings screen.
					'mediaTitle'      => __( 'Select Logo', 'ptt-kargo-for-woocommerce' ),
					'mediaButton'     => __( 'Use this image', 'ptt-kargo-for-woocommerce' ),
					'genericErr'      => __( 'Error', 'ptt-kargo-for-woocommerce' ),
				],
			]
		);
	}

	public function render_orders_page(): void {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'Unauthorised', 'ptt-kargo-for-woocommerce' ) );
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only list filter.
		$requested_show = isset( $_GET['show'] ) ? sanitize_key( wp_unslash( $_GET['show'] ) ) : '';
		$show           = in_array( $requested_show, [ 'pending', 'sent', 'all' ], true ) ? $requested_show : 'pending';
		$orders = $this->orders->eligible_orders( 100, $show );

		include PTT_KARGO_WC_DIR . 'admin/views/orders-list.php';
	}

	public function render_settings_page(): void {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'Unauthorised', 'ptt-kargo-for-woocommerce' ) );
		}
		include PTT_KARGO_WC_DIR . 'admin/views/settings.php';
	}

	public function render_logs_page(): void {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'Unauthorised', 'ptt-kargo-for-woocommerce' ) );
		}
		include PTT_KARGO_WC_DIR . 'admin/views/logs.php';
	}

	public function render_courier_page(): void {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'Unauthorised', 'ptt-kargo-for-woocommerce' ) );
		}
		$settings = $this->settings;
		include PTT_KARGO_WC_DIR . 'admin/views/courier.php';
	}

	public function ajax_test_connection(): void {
		check_ajax_referer( self::NONCE_ACTION, 'nonce' );
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_send_json_error( [ 'message' => __( 'Unauthorised', 'ptt-kargo-for-woocommerce' ) ], 403 );
		}

		// Optional: test with credentials typed into the form but not yet saved. PTT_Client
		// reads its own settings, so the override goes through a global filter rather than a
		// throwaway Settings instance.
		$posted_env   = isset( $_POST['environment'] ) ? sanitize_key( wp_unslash( $_POST['environment'] ) ) : '';
		$override_env = in_array( $posted_env, [ 'test', 'prod' ], true ) ? $posted_env : null;
		$override_id  = isset( $_POST['musteri_id'] ) ? preg_replace( '/\D/', '', sanitize_text_field( wp_unslash( $_POST['musteri_id'] ) ) ) : null;
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- a password is used verbatim; sanitising would corrupt valid characters.
		$override_pwd = isset( $_POST['sifre'] ) ? (string) wp_unslash( $_POST['sifre'] ) : null;
		$override_pwd = $override_pwd !== null ? wp_unslash( $override_pwd ) : null;

		$has_override   = $override_env !== null || $override_id !== null || ( $override_pwd !== null && $override_pwd !== '' );
		$applied_filter = null;

		if ( $has_override ) {
			$applied_filter = function ( $value, $option ) use ( $override_env, $override_id, $override_pwd ) {
				if ( $option !== Settings::OPTION_KEY || ! is_array( $value ) ) {
					return $value;
				}
				if ( $override_env !== null ) {
					$value['environment'] = $override_env;
				}
				if ( $override_id !== null ) {
					$value['musteri_id'] = $override_id;
				}
				if ( $override_pwd !== null && $override_pwd !== '' ) {
					$value['sifre_enc'] = Settings::encrypt( $override_pwd );
				}
				return $value;
			};
			add_filter( 'option_' . Settings::OPTION_KEY, $applied_filter, 10, 2 );
		}

		$result = $this->client->test_connection();

		if ( $applied_filter !== null ) {
			remove_filter( 'option_' . Settings::OPTION_KEY, $applied_filter, 10 );
		}

		if ( ! empty( $result['success'] ) ) {
			wp_send_json_success( [ 'message' => $result['mesaj'] ] );
		}
		wp_send_json_error( [ 'message' => $result['mesaj'] ?? __( 'Unknown error', 'ptt-kargo-for-woocommerce' ) ] );
	}

	public function ajax_clear_logs(): void {
		check_ajax_referer( self::NONCE_ACTION, 'nonce' );
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_send_json_error( [ 'message' => __( 'Unauthorised', 'ptt-kargo-for-woocommerce' ) ], 403 );
		}

		Logs::clear();
		wp_send_json_success( [ 'message' => __( 'Logs cleared.', 'ptt-kargo-for-woocommerce' ) ] );
	}

	public function ajax_refresh(): void {
		check_ajax_referer( self::NONCE_ACTION, 'nonce' );
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_send_json_error( [ 'message' => __( 'Unauthorised', 'ptt-kargo-for-woocommerce' ) ], 403 );
		}

		$show   = isset( $_POST['show'] ) ? sanitize_key( $_POST['show'] ) : 'pending';
		$orders = $this->orders->eligible_orders( 100, $show );

		ob_start();
		include PTT_KARGO_WC_DIR . 'admin/views/orders-table.php';
		$html = ob_get_clean();

		wp_send_json_success(
			[
				'html'  => $html,
				'count' => count( $orders ),
			]
		);
	}

	public function ajax_prepare(): void {
		check_ajax_referer( self::NONCE_ACTION, 'nonce' );
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_send_json_error( [ 'message' => __( 'Unauthorised', 'ptt-kargo-for-woocommerce' ) ], 403 );
		}

		$order_id = isset( $_POST['order_id'] ) ? (int) $_POST['order_id'] : 0;
		$order    = wc_get_order( $order_id );
		if ( ! $order ) {
			wp_send_json_error( [ 'message' => __( 'Order not found.', 'ptt-kargo-for-woocommerce' ) ], 404 );
		}

		$existing = (string) $order->get_meta( Orders::META_BARKOD );
		if ( $existing !== '' ) {
			wp_send_json_error( [ 'message' => __( 'A barcode already exists for this order: ', 'ptt-kargo-for-woocommerce' ) . $existing ], 409 );
		}

		$payload = $this->orders->to_ptt_payload( $order );

		// Show the barcode held back from a failed attempt; a retry will not consume a new one.
		$pending_barkod = $this->orders->get_pending_barkod( $order );

		// Insurance is off by default and enabled from the popup, which suggests the order total.
		$insurance_default = false;
		$insurance_amount  = (float) $order->get_total();
		$is_cod            = false;
		$cod_methods       = $this->settings->cod_payment_methods();
		if ( ! empty( $cod_methods ) ) {
			$is_cod = in_array( (string) $order->get_payment_method(), $cod_methods, true );
		}

		wp_send_json_success(
			[
				'order_id'          => $order_id,
				'order_no'          => $payload['order_no'],
				'fields'            => $payload['fields'],
				'missing'           => $payload['missing'],
				'posta'             => $payload['posta'],
				'insurance_default' => $insurance_default,
				'insurance_amount'  => number_format( $insurance_amount, 2, '.', '' ),
				'is_cod'            => $is_cod,
				'cod_amount'        => number_format( (float) $order->get_total(), 2, '.', '' ),
				'payment_method'    => $order->get_payment_method_title(),
				'pending_barkod'    => $pending_barkod,
			]
		);
	}

	public function ajax_send(): void {
		check_ajax_referer( self::NONCE_ACTION, 'nonce' );
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_send_json_error( [ 'message' => __( 'Unauthorised', 'ptt-kargo-for-woocommerce' ) ], 403 );
		}

		$order_id = isset( $_POST['order_id'] ) ? (int) $_POST['order_id'] : 0;
		$override = isset( $_POST['override'] ) && is_array( $_POST['override'] ) ? array_map( 'sanitize_text_field', wp_unslash( $_POST['override'] ) ) : [];

		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			wp_send_json_error( [ 'message' => __( 'Order not found.', 'ptt-kargo-for-woocommerce' ) ], 404 );
		}

		$existing = (string) $order->get_meta( Orders::META_BARKOD );
		if ( $existing !== '' ) {
			wp_send_json_error( [ 'message' => __( 'A barcode already exists for this order: ', 'ptt-kargo-for-woocommerce' ) . $existing ], 409 );
		}

		$payload = $this->orders->to_ptt_payload( $order );

		$shipping_int_keys   = [ 'agirlik', 'desi', 'en', 'boy', 'yukseklik' ];
		$money_keys          = [ 'deger_ucreti' ]; // number_format 12,2
		$customer_field_keys = [ 'aliciAdi', 'aAdres', 'aliciIlAdi', 'aliciIlceAdi', 'aliciSms', 'aliciEmail' ];
		$allowed_override    = array_unique( array_merge( $customer_field_keys, $shipping_int_keys, $money_keys ) );

		// Insurance toggle from the popup: 'on' adds deger_ucreti and DK, 'off' clears both.
		$insurance_toggle = isset( $override['__insurance'] ) ? sanitize_key( (string) $override['__insurance'] ) : '';
		unset( $override['__insurance'] );

		foreach ( $override as $k => $v ) {
			if ( $v === '' ) {
				continue;
			}
			if ( ! in_array( $k, $allowed_override, true ) ) {
				continue;
			}

			if ( in_array( $k, $shipping_int_keys, true ) ) {
				// Dimension and weight fields take positive integers only; PTT rejects 0 or less.
				$num = (int) $v;
				if ( $num <= 0 ) {
					continue;
				}
				$payload['fields'][ $k ] = $num;
			} elseif ( in_array( $k, $money_keys, true ) ) {
				$num = (float) $v;
				if ( $num <= 0 ) {
					continue;
				}
				$payload['fields'][ $k ] = number_format( $num, 2, '.', '' );
			} else {
				$payload['fields'][ $k ] = $v;
			}

			if ( isset( $payload['missing'][ $k ] ) ) {
				unset( $payload['missing'][ $k ] );
			}
		}

		// With insurance on, deger_ucreti comes from the override and DK is added to ekhizmet;
		// with it off both are removed. PTT requires DK in ekhizmet whenever deger_ucreti is sent.
		$ins_eh = strtoupper( (string) $this->settings->get( 'insurance_extra_service_code', 'DK' ) );
		if ( $insurance_toggle === 'on' && ! empty( $payload['fields']['deger_ucreti'] ) && $ins_eh !== '' ) {
			$payload['fields']['ekhizmet'] = Orders::merge_extra_service_codes(
				(string) ( $payload['fields']['ekhizmet'] ?? '' ),
				$ins_eh
			);
		} elseif ( $insurance_toggle === 'off' ) {
			unset( $payload['fields']['deger_ucreti'] );
			if ( $ins_eh !== '' && isset( $payload['fields']['ekhizmet'] ) ) {
				$payload['fields']['ekhizmet'] = str_replace( $ins_eh, '', strtoupper( (string) $payload['fields']['ekhizmet'] ) );
			}
		}

		$parca_adet  = isset( $_POST['parca_adet'] ) ? max( 1, (int) $_POST['parca_adet'] ) : 1;
		$irsaliye_no = isset( $_POST['irsaliye_no'] ) ? sanitize_text_field( wp_unslash( $_POST['irsaliye_no'] ) ) : '';

		// Retry: reuse the barcode consumed by the previous failed attempt instead of a new one.
		$pending   = $this->orders->get_pending_barkod( $order );
		$barkodlar = [];

		if ( $parca_adet === 1 ) {
			if ( $pending !== '' ) {
				$barkodlar[] = $pending;
			} else {
				$next = $this->barcode->next();
				if ( $next === null ) {
					wp_send_json_error( [ 'message' => __( 'The barcode range is exhausted. Please define a new range in the settings.', 'ptt-kargo-for-woocommerce' ) ], 500 );
				}
				$barkodlar[] = $next;
			}
		} else {
			// Multi-package: the pending barcode becomes the first package, the rest come from next().
			if ( $pending !== '' ) {
				$barkodlar[] = $pending;
			}
			while ( count( $barkodlar ) < $parca_adet ) {
				$next = $this->barcode->next();
				if ( $next === null ) {
					wp_send_json_error( [ 'message' => __( 'The barcode range is too small; there are not enough barcodes for every package.', 'ptt-kargo-for-woocommerce' ) ], 500 );
				}
				$barkodlar[] = $next;
			}
		}

		$ref = $this->orders->build_ref( $order );

		$base_fields                      = $payload['fields'];
		$base_fields['musteriReferansNo'] = $ref;

		if ( $parca_adet === 1 ) {
			$base_fields['barkodNo'] = $barkodlar[0];
			$result                  = $this->client->kabul_ekle( $base_fields, $order->get_id() );
		} else {
			$result = $this->client->kabul_ekle_parcali_barkod( $base_fields, $barkodlar, $irsaliye_no, $order->get_id() );
		}

		if ( empty( $result['success'] ) ) {
			$err_msg = (string) ( $result['mesaj'] ?? __( 'Unknown error', 'ptt-kargo-for-woocommerce' ) );
			// Keep the first consumed barcode as pending; a retry tops the rest up via next().
			$pending_to_store = $barkodlar[0] ?? '';
			$this->orders->mark_error(
				$order,
				$err_msg,
				(string) ( $result['raw'] ?? '' ),
				(string) ( $result['request'] ?? '' ),
				$pending_to_store
			);
			do_action( 'ptt_kargo_wc_after_error', $order, $err_msg, $result );
			wp_send_json_error(
				[
					'message'       => $result['mesaj'] ?? __( 'Sending to PTT failed.', 'ptt-kargo-for-woocommerce' ),
					'raw'           => $result['raw'] ?? '',
					'request'       => $result['request'] ?? '',
					'parca_results' => $result['parca_results'] ?? null,
				],
				502
			);
		}

		$returned_barkod = (string) ( $result['barkod'] ?? $barkodlar[0] );
		$takip_url       = (string) ( $result['takip_url'] ?? '' );
		$dosya_adi       = (string) ( $result['dosya_adi'] ?? '' );

		$this->orders->mark_sent(
			$order,
			$returned_barkod,
			$ref,
			$takip_url,
			(string) ( $result['raw'] ?? '' ),
			(string) ( $result['request'] ?? '' ),
			(string) ( $result['mesaj'] ?? '' ),
			$dosya_adi
		);
		if ( $parca_adet > 1 ) {
			$this->orders->mark_parca( $order, $barkodlar, $irsaliye_no );
		}

		do_action( 'ptt_kargo_wc_after_send', $order, $returned_barkod, $result );

		wp_send_json_success(
			[
				'barkod'        => $returned_barkod,
				'barkodlar'     => $barkodlar,
				'takip_url'     => $takip_url,
				'label_url'     => $this->label->label_url( $order_id ),
				'mesaj'         => $result['mesaj'] ?? __( 'Shipment created.', 'ptt-kargo-for-woocommerce' ),
				'parca_results' => $result['parca_results'] ?? null,
			]
		);
	}

	/**
	 * Cancels a shipment PTT has not accepted yet. Tries the barcode first and falls back
	 * to the reference number when the barcode is missing or rejected.
	 *
	 * On success META_BARKOD, REF and TAKIP_URL are cleared and the status becomes
	 * STATUS_CANCELED, so the same order can be resent with a fresh barcode.
	 */
	public function ajax_cancel(): void {
		check_ajax_referer( self::NONCE_ACTION, 'nonce' );
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_send_json_error( [ 'message' => __( 'Unauthorised', 'ptt-kargo-for-woocommerce' ) ], 403 );
		}

		$order_id = isset( $_POST['order_id'] ) ? (int) $_POST['order_id'] : 0;
		$order    = wc_get_order( $order_id );
		if ( ! $order ) {
			wp_send_json_error( [ 'message' => __( 'Order not found.', 'ptt-kargo-for-woocommerce' ) ], 404 );
		}

		$status = (string) $order->get_meta( Orders::META_STATUS );
		$barkod = (string) $order->get_meta( Orders::META_BARKOD );
		$ref    = (string) $order->get_meta( Orders::META_REF );
		$dosya  = (string) $order->get_meta( Orders::META_DOSYA_ADI );

		if ( $status !== Orders::STATUS_SENT || $barkod === '' ) {
			wp_send_json_error(
				[ 'message' => __( 'No cancellable PTT record was found for this order.', 'ptt-kargo-for-woocommerce' ) ],
				400
			);
		}

		$result = $this->client->barkod_veri_sil( $barkod, $dosya, $order_id );

		if ( empty( $result['success'] ) && $ref !== '' ) {
			$ref_result = $this->client->referans_veri_sil( $ref, $dosya, $order_id );
			if ( ! empty( $ref_result['success'] ) ) {
				$result             = $ref_result;
				$result['fallback'] = 'referansVeriSil';
			}
		}

		if ( empty( $result['success'] ) ) {
			$err = (string) ( $result['mesaj'] ?? __( 'The PTT cancellation request failed.', 'ptt-kargo-for-woocommerce' ) );
			do_action( 'ptt_kargo_wc_after_cancel_error', $order, $barkod, $err, $result );
			wp_send_json_error(
				[
					'message' => $err,
					'raw'     => (string) ( $result['raw'] ?? '' ),
					'request' => (string) ( $result['request'] ?? '' ),
				],
				502
			);
		}

		$this->orders->mark_canceled(
			$order,
			$barkod,
			(string) ( $result['mesaj'] ?? '' ),
			(string) ( $result['raw'] ?? '' ),
			(string) ( $result['request'] ?? '' )
		);

		do_action( 'ptt_kargo_wc_after_cancel', $order, $barkod, $result );

		wp_send_json_success(
			[
				'message'     => $result['mesaj'] ?? __( 'The PTT shipment was cancelled.', 'ptt-kargo-for-woocommerce' ),
				'old_barkod'  => $barkod,
				'used_method' => $result['fallback'] ?? 'barkodVeriSil',
			]
		);
	}

	/**
	 * Courier request AJAX: posts the form fields to siparisIstekEkle2. The form supplies
	 * only the pickup parameters (count, weight, volumetric weight); the sender details
	 * come from the sender settings.
	 */
	public function ajax_courier(): void {
		check_ajax_referer( self::NONCE_ACTION, 'nonce' );
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_send_json_error( [ 'message' => __( 'Unauthorised', 'ptt-kargo-for-woocommerce' ) ], 403 );
		}

		$params = [
			'adet'                 => isset( $_POST['adet'] ) ? max( 1, (int) $_POST['adet'] ) : 0,
			'agirlik'              => isset( $_POST['agirlik'] ) ? max( 0, (int) $_POST['agirlik'] ) : 0,
			'desi'                 => isset( $_POST['desi'] ) ? max( 0, (int) $_POST['desi'] ) : 0,
			'en'                   => isset( $_POST['en'] ) ? max( 0, (int) $_POST['en'] ) : 0,
			'boy'                  => isset( $_POST['boy'] ) ? max( 0, (int) $_POST['boy'] ) : 0,
			'yukseklik'            => isset( $_POST['yukseklik'] ) ? max( 0, (int) $_POST['yukseklik'] ) : 0,
			'ekhizmet'             => isset( $_POST['ekhizmet'] ) ? strtoupper( preg_replace( '/[^A-Za-z]/', '', sanitize_text_field( wp_unslash( $_POST['ekhizmet'] ) ) ) ) : '',
			'deger_konulmus_ucret' => isset( $_POST['deger_konulmus_ucret'] ) ? number_format( (float) $_POST['deger_konulmus_ucret'], 2, '.', '' ) : '',
			'randevu_baslangic'    => isset( $_POST['randevu_baslangic'] ) ? sanitize_text_field( wp_unslash( $_POST['randevu_baslangic'] ) ) : '',
			'randevu_bitis'        => isset( $_POST['randevu_bitis'] ) ? sanitize_text_field( wp_unslash( $_POST['randevu_bitis'] ) ) : '',
			'ucret'                => isset( $_POST['ucret'] ) ? (float) $_POST['ucret'] : 0,
		];

		if ( $params['adet'] <= 0 ) {
			wp_send_json_error( [ 'message' => __( 'Enter a valid package count.', 'ptt-kargo-for-woocommerce' ) ], 400 );
		}

		$result = $this->client->siparis_istek_ekle2( $params );

		if ( ! empty( $result['success'] ) ) {
			do_action( 'ptt_kargo_wc_after_courier', $params, $result );
			wp_send_json_success(
				[
					'message'    => $result['mesaj'] ?? __( 'Courier request accepted.', 'ptt-kargo-for-woocommerce' ),
					'siparis_id' => $result['siparis_id'] ?? '',
					'http_code'  => $result['http_code'] ?? null,
				]
			);
		}

		wp_send_json_error(
			[
				'message' => $result['mesaj'] ?? __( 'Courier request failed.', 'ptt-kargo-for-woocommerce' ),
				'raw'     => $result['raw'] ?? '',
				'request' => $result['request'] ?? '',
			],
			502
		);
	}

	/**
	 * Returns PTT's getDropPointInfo result for an order. Also called by ajax_takip()
	 * to enrich the tracking response.
	 */
	public function ajax_drop_point(): void {
		check_ajax_referer( self::NONCE_ACTION, 'nonce' );
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_send_json_error( [ 'message' => __( 'Unauthorised', 'ptt-kargo-for-woocommerce' ) ], 403 );
		}

		$order_id = isset( $_POST['order_id'] ) ? (int) $_POST['order_id'] : 0;
		$barkod   = isset( $_POST['barkod'] ) ? preg_replace( '/\D/', '', sanitize_text_field( wp_unslash( $_POST['barkod'] ) ) ) : '';

		if ( $barkod === '' && $order_id > 0 ) {
			$order = wc_get_order( $order_id );
			if ( $order ) {
				$barkod = (string) $order->get_meta( Orders::META_BARKOD );
			}
		}
		if ( $barkod === '' ) {
			wp_send_json_error( [ 'message' => __( 'A barcode is required.', 'ptt-kargo-for-woocommerce' ) ], 400 );
		}

		$result = $this->client->get_drop_point_info( $barkod );

		if ( ! empty( $result['success'] ) ) {
			wp_send_json_success( $result );
		}
		wp_send_json_error(
			[
				'message'   => $result['mesaj'] ?? __( 'Could not retrieve drop point information.', 'ptt-kargo-for-woocommerce' ),
				'raw'       => $result['raw'] ?? '',
				'http_code' => $result['http_code'] ?? null,
			],
			502
		);
	}

	public function ajax_takip(): void {
		check_ajax_referer( self::NONCE_ACTION, 'nonce' );
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_send_json_error( [ 'message' => __( 'Unauthorised', 'ptt-kargo-for-woocommerce' ) ], 403 );
		}

		$order_id = isset( $_POST['order_id'] ) ? (int) $_POST['order_id'] : 0;
		$order    = wc_get_order( $order_id );
		if ( ! $order ) {
			wp_send_json_error( [ 'message' => __( 'Order not found.', 'ptt-kargo-for-woocommerce' ) ], 404 );
		}

		$barkod = (string) $order->get_meta( Orders::META_BARKOD );
		$ref    = (string) $order->get_meta( Orders::META_REF );

		if ( $barkod === '' && $ref === '' ) {
			wp_send_json_error( [ 'message' => __( 'This order has no barcode or reference number to track.', 'ptt-kargo-for-woocommerce' ) ], 400 );
		}

		$result       = [];
		$used_method  = '';
		$ref_fallback = false;

		if ( $barkod !== '' ) {
			$result      = $this->client->takip_sorgula_barkod( $barkod );
			$used_method = 'gonderiSorgu';

			if ( empty( $result['success'] ) && $ref !== '' ) {
				$ref_result = $this->client->takip_sorgula_referans( $ref );
				if ( ! empty( $ref_result['success'] ) ) {
					$result       = $ref_result;
					$used_method  = 'gonderiSorgu_referansNo';
					$ref_fallback = true;
				}
			}
		} else {
				// Reference number only, no barcode.
			$result      = $this->client->takip_sorgula_referans( $ref );
			$used_method = 'gonderiSorgu_referansNo';
		}

		$result['used_method']  = $used_method;
		$result['ref_fallback'] = $ref_fallback;

		// Drop point info needs a barcode; it cannot be looked up by reference number.
		$with_drop = (bool) apply_filters( 'ptt_kargo_wc_takip_with_drop_point', true, $order );
		if ( $with_drop && $barkod !== '' ) {
			$drop = $this->client->get_drop_point_info( $barkod );
			if ( ! empty( $drop['success'] ) ) {
				$result['drop_point'] = $drop;
			} else {
				$result['drop_point_error'] = $drop['mesaj'] ?? '';
			}
		}

		wp_send_json_success( $result );
	}
}
