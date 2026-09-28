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
			__( 'WC PTT Kargo', 'ptt-kargo-for-woocommerce' ),
			__( 'PTT Kargo', 'ptt-kargo-for-woocommerce' ),
			self::CAPABILITY,
			self::MENU_SLUG,
			[ $this, 'render_orders_page' ],
			'dashicons-archive',
			58
		);

		add_submenu_page(
			self::MENU_SLUG,
			__( 'Kargo Siparişleri', 'ptt-kargo-for-woocommerce' ),
			__( 'Kargo Siparişleri', 'ptt-kargo-for-woocommerce' ),
			self::CAPABILITY,
			self::MENU_SLUG,
			[ $this, 'render_orders_page' ]
		);

		add_submenu_page(
			self::MENU_SLUG,
			__( 'Kurye Çağır', 'ptt-kargo-for-woocommerce' ),
			__( 'Kurye Çağır', 'ptt-kargo-for-woocommerce' ),
			self::CAPABILITY,
			self::MENU_SLUG . '-kurye',
			[ $this, 'render_courier_page' ]
		);

		add_submenu_page(
			self::MENU_SLUG,
			__( 'Ayarlar', 'ptt-kargo-for-woocommerce' ),
			__( 'Ayarlar', 'ptt-kargo-for-woocommerce' ),
			self::CAPABILITY,
			self::MENU_SLUG . '-settings',
			[ $this, 'render_settings_page' ]
		);

		add_submenu_page(
			self::MENU_SLUG,
			__( 'PTT Loglar', 'ptt-kargo-for-woocommerce' ),
			__( 'Loglar', 'ptt-kargo-for-woocommerce' ),
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
		$is_settings    = isset( $_GET['page'] ) && $_GET['page'] === self::MENU_SLUG . '-settings';
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
				],
				'i18n'    => [
					'sending'         => __( 'Gönderiliyor...', 'ptt-kargo-for-woocommerce' ),
					'success'         => __( 'Başarılı! Barkod: ', 'ptt-kargo-for-woocommerce' ),
					'error'           => __( 'Hata: ', 'ptt-kargo-for-woocommerce' ),
					'summaryTitle'    => __( 'Kargo Özeti', 'ptt-kargo-for-woocommerce' ),
					'customer'        => __( 'Müşteri', 'ptt-kargo-for-woocommerce' ),
					'missingWarn'     => __( 'Bazı alanlar eksik. Boş bırakabilirsin ya da aşağıdan doldur.', 'ptt-kargo-for-woocommerce' ),
					'confirm'         => __( 'Onayla ve Gönder', 'ptt-kargo-for-woocommerce' ),
					'cancel'          => __( 'İptal', 'ptt-kargo-for-woocommerce' ),
					'orderWord'       => __( 'sipariş', 'ptt-kargo-for-woocommerce' ),
					'shipBtn'         => __( 'Kargoya İlet', 'ptt-kargo-for-woocommerce' ),
					'unknownErr'      => __( 'Bilinmeyen hata', 'ptt-kargo-for-woocommerce' ),
					'serverErr'       => __( 'Sunucu hatası.', 'ptt-kargo-for-woocommerce' ),
					'prepareErr'      => __( 'Hazırlanamadı.', 'ptt-kargo-for-woocommerce' ),
					'trackErr'        => __( 'Takip sorgulanamadı.', 'ptt-kargo-for-woocommerce' ),
					'trackBarkod'     => __( 'Barkod:', 'ptt-kargo-for-woocommerce' ),
					'trackStatus'     => __( 'Durum:', 'ptt-kargo-for-woocommerce' ),
					'trackEvents'     => __( 'Hareketler:', 'ptt-kargo-for-woocommerce' ),
					'testing'         => __( 'Test ediliyor...', 'ptt-kargo-for-woocommerce' ),
					'cancelConfirm'   => __( "Bu sipariş için PTT'ye gönderilen kayıt silinecek. Eski barkod yeniden kullanılamaz; sipariş tekrar gönderilirse yeni bir barkod tüketilir. Devam edilsin mi?", 'ptt-kargo-for-woocommerce' ),
					'canceling'       => __( 'İptal ediliyor...', 'ptt-kargo-for-woocommerce' ),
					'cancelOk'        => __( 'PTT gönderisi iptal edildi.', 'ptt-kargo-for-woocommerce' ),
					'cancelErr'       => __( 'İptal başarısız: ', 'ptt-kargo-for-woocommerce' ),
					'cancelBtn'       => __( 'PTT Gönderisini İptal Et', 'ptt-kargo-for-woocommerce' ),
					'insuranceLabel'  => __( 'Sigortalı Gönder (Değerli Kargo)', 'ptt-kargo-for-woocommerce' ),
					'insuranceAmount' => __( 'Sigorta Tutarı (TL)', 'ptt-kargo-for-woocommerce' ),
					'codInfo'         => __( 'Kapıda Ödeme aktif:', 'ptt-kargo-for-woocommerce' ),
					'dropPointTitle'  => __( 'Şu an bulunduğu PTT şubesi', 'ptt-kargo-for-woocommerce' ),
					'courierSending'  => __( 'Kurye çağrılıyor...', 'ptt-kargo-for-woocommerce' ),
					'courierOk'       => __( 'Kurye siparişi alındı!', 'ptt-kargo-for-woocommerce' ),
					'courierErr'      => __( 'Kurye çağırma başarısız: ', 'ptt-kargo-for-woocommerce' ),
				],
			]
		);
	}

	public function render_orders_page(): void {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'Yetkisiz', 'ptt-kargo-for-woocommerce' ) );
		}

		$show   = isset( $_GET['show'] ) && in_array( $_GET['show'], [ 'pending', 'sent', 'all' ], true ) ? $_GET['show'] : 'pending';
		$orders = $this->orders->eligible_orders( 100, $show );

		include PTT_KARGO_WC_DIR . 'admin/views/orders-list.php';
	}

	public function render_settings_page(): void {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'Yetkisiz', 'ptt-kargo-for-woocommerce' ) );
		}
		include PTT_KARGO_WC_DIR . 'admin/views/settings.php';
	}

	public function render_logs_page(): void {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'Yetkisiz', 'ptt-kargo-for-woocommerce' ) );
		}
		include PTT_KARGO_WC_DIR . 'admin/views/logs.php';
	}

	public function render_courier_page(): void {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'Yetkisiz', 'ptt-kargo-for-woocommerce' ) );
		}
		$settings = $this->settings;
		include PTT_KARGO_WC_DIR . 'admin/views/courier.php';
	}

	public function ajax_test_connection(): void {
		check_ajax_referer( self::NONCE_ACTION, 'nonce' );
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_send_json_error( [ 'message' => __( 'Yetkisiz', 'ptt-kargo-for-woocommerce' ) ], 403 );
		}

		// Optional: test with credentials typed into the form but not yet saved. PTT_Client
		// reads its own settings, so the override goes through a global filter rather than a
		// throwaway Settings instance.
		$override_env = isset( $_POST['environment'] ) && in_array( $_POST['environment'], [ 'test', 'prod' ], true ) ? sanitize_key( $_POST['environment'] ) : null;
		$override_id  = isset( $_POST['musteri_id'] ) ? preg_replace( '/\D/', '', (string) $_POST['musteri_id'] ) : null;
		$override_pwd = isset( $_POST['sifre'] ) ? (string) $_POST['sifre'] : null;
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
		wp_send_json_error( [ 'message' => $result['mesaj'] ?? __( 'Bilinmeyen hata', 'ptt-kargo-for-woocommerce' ) ] );
	}

	public function ajax_clear_logs(): void {
		check_ajax_referer( self::NONCE_ACTION, 'nonce' );
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_send_json_error( [ 'message' => __( 'Yetkisiz', 'ptt-kargo-for-woocommerce' ) ], 403 );
		}

		Logs::clear();
		wp_send_json_success( [ 'message' => __( 'Loglar temizlendi.', 'ptt-kargo-for-woocommerce' ) ] );
	}

	public function ajax_refresh(): void {
		check_ajax_referer( self::NONCE_ACTION, 'nonce' );
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_send_json_error( [ 'message' => __( 'Yetkisiz', 'ptt-kargo-for-woocommerce' ) ], 403 );
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
			wp_send_json_error( [ 'message' => __( 'Yetkisiz', 'ptt-kargo-for-woocommerce' ) ], 403 );
		}

		$order_id = isset( $_POST['order_id'] ) ? (int) $_POST['order_id'] : 0;
		$order    = wc_get_order( $order_id );
		if ( ! $order ) {
			wp_send_json_error( [ 'message' => __( 'Sipariş bulunamadı.', 'ptt-kargo-for-woocommerce' ) ], 404 );
		}

		$existing = (string) $order->get_meta( Orders::META_BARKOD );
		if ( $existing !== '' ) {
			wp_send_json_error( [ 'message' => __( 'Bu sipariş için zaten barkod oluşturulmuş: ', 'ptt-kargo-for-woocommerce' ) . $existing ], 409 );
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
			wp_send_json_error( [ 'message' => __( 'Yetkisiz', 'ptt-kargo-for-woocommerce' ) ], 403 );
		}

		$order_id = isset( $_POST['order_id'] ) ? (int) $_POST['order_id'] : 0;
		$override = isset( $_POST['override'] ) && is_array( $_POST['override'] ) ? array_map( 'sanitize_text_field', wp_unslash( $_POST['override'] ) ) : [];

		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			wp_send_json_error( [ 'message' => __( 'Sipariş bulunamadı.', 'ptt-kargo-for-woocommerce' ) ], 404 );
		}

		$existing = (string) $order->get_meta( Orders::META_BARKOD );
		if ( $existing !== '' ) {
			wp_send_json_error( [ 'message' => __( 'Bu sipariş için zaten barkod oluşturulmuş: ', 'ptt-kargo-for-woocommerce' ) . $existing ], 409 );
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
		$irsaliye_no = isset( $_POST['irsaliye_no'] ) ? sanitize_text_field( (string) $_POST['irsaliye_no'] ) : '';

		// Retry: reuse the barcode consumed by the previous failed attempt instead of a new one.
		$pending   = $this->orders->get_pending_barkod( $order );
		$barkodlar = [];

		if ( $parca_adet === 1 ) {
			if ( $pending !== '' ) {
				$barkodlar[] = $pending;
			} else {
				$next = $this->barcode->next();
				if ( $next === null ) {
					wp_send_json_error( [ 'message' => __( 'Barkod aralığı tükendi. Lütfen ayarlardan yeni aralık tanımlayın.', 'ptt-kargo-for-woocommerce' ) ], 500 );
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
					wp_send_json_error( [ 'message' => __( 'Barkod aralığı yetersiz; tüm parçalar için yeterli barkod yok.', 'ptt-kargo-for-woocommerce' ) ], 500 );
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
			$err_msg = (string) ( $result['mesaj'] ?? __( 'Bilinmeyen hata', 'ptt-kargo-for-woocommerce' ) );
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
					'message'       => $result['mesaj'] ?? __( 'PTT gönderimi başarısız.', 'ptt-kargo-for-woocommerce' ),
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
				'mesaj'         => $result['mesaj'] ?? __( 'Gönderi oluşturuldu.', 'ptt-kargo-for-woocommerce' ),
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
			wp_send_json_error( [ 'message' => __( 'Yetkisiz', 'ptt-kargo-for-woocommerce' ) ], 403 );
		}

		$order_id = isset( $_POST['order_id'] ) ? (int) $_POST['order_id'] : 0;
		$order    = wc_get_order( $order_id );
		if ( ! $order ) {
			wp_send_json_error( [ 'message' => __( 'Sipariş bulunamadı.', 'ptt-kargo-for-woocommerce' ) ], 404 );
		}

		$status = (string) $order->get_meta( Orders::META_STATUS );
		$barkod = (string) $order->get_meta( Orders::META_BARKOD );
		$ref    = (string) $order->get_meta( Orders::META_REF );
		$dosya  = (string) $order->get_meta( Orders::META_DOSYA_ADI );

		if ( $status !== Orders::STATUS_SENT || $barkod === '' ) {
			wp_send_json_error(
				[ 'message' => __( 'Bu sipariş için iptal edilebilir bir PTT kaydı bulunamadı.', 'ptt-kargo-for-woocommerce' ) ],
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
			$err = (string) ( $result['mesaj'] ?? __( 'PTT iptal isteği başarısız.', 'ptt-kargo-for-woocommerce' ) );
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
				'message'     => $result['mesaj'] ?? __( 'PTT gönderisi iptal edildi.', 'ptt-kargo-for-woocommerce' ),
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
			wp_send_json_error( [ 'message' => __( 'Yetkisiz', 'ptt-kargo-for-woocommerce' ) ], 403 );
		}

		$params = [
			'adet'                 => isset( $_POST['adet'] ) ? max( 1, (int) $_POST['adet'] ) : 0,
			'agirlik'              => isset( $_POST['agirlik'] ) ? max( 0, (int) $_POST['agirlik'] ) : 0,
			'desi'                 => isset( $_POST['desi'] ) ? max( 0, (int) $_POST['desi'] ) : 0,
			'en'                   => isset( $_POST['en'] ) ? max( 0, (int) $_POST['en'] ) : 0,
			'boy'                  => isset( $_POST['boy'] ) ? max( 0, (int) $_POST['boy'] ) : 0,
			'yukseklik'            => isset( $_POST['yukseklik'] ) ? max( 0, (int) $_POST['yukseklik'] ) : 0,
			'ekhizmet'             => isset( $_POST['ekhizmet'] ) ? strtoupper( preg_replace( '/[^A-Za-z]/', '', (string) $_POST['ekhizmet'] ) ) : '',
			'deger_konulmus_ucret' => isset( $_POST['deger_konulmus_ucret'] ) ? number_format( (float) $_POST['deger_konulmus_ucret'], 2, '.', '' ) : '',
			'randevu_baslangic'    => isset( $_POST['randevu_baslangic'] ) ? sanitize_text_field( (string) $_POST['randevu_baslangic'] ) : '',
			'randevu_bitis'        => isset( $_POST['randevu_bitis'] ) ? sanitize_text_field( (string) $_POST['randevu_bitis'] ) : '',
			'ucret'                => isset( $_POST['ucret'] ) ? (float) $_POST['ucret'] : 0,
		];

		if ( $params['adet'] <= 0 ) {
			wp_send_json_error( [ 'message' => __( 'Geçerli bir paket sayısı girin.', 'ptt-kargo-for-woocommerce' ) ], 400 );
		}

		$result = $this->client->siparis_istek_ekle2( $params );

		if ( ! empty( $result['success'] ) ) {
			do_action( 'ptt_kargo_wc_after_courier', $params, $result );
			wp_send_json_success(
				[
					'message'    => $result['mesaj'] ?? __( 'Kurye siparişi alındı.', 'ptt-kargo-for-woocommerce' ),
					'siparis_id' => $result['siparis_id'] ?? '',
					'http_code'  => $result['http_code'] ?? null,
				]
			);
		}

		wp_send_json_error(
			[
				'message' => $result['mesaj'] ?? __( 'Kurye siparişi başarısız.', 'ptt-kargo-for-woocommerce' ),
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
			wp_send_json_error( [ 'message' => __( 'Yetkisiz', 'ptt-kargo-for-woocommerce' ) ], 403 );
		}

		$order_id = isset( $_POST['order_id'] ) ? (int) $_POST['order_id'] : 0;
		$barkod   = isset( $_POST['barkod'] ) ? preg_replace( '/\D/', '', (string) $_POST['barkod'] ) : '';

		if ( $barkod === '' && $order_id > 0 ) {
			$order = wc_get_order( $order_id );
			if ( $order ) {
				$barkod = (string) $order->get_meta( Orders::META_BARKOD );
			}
		}
		if ( $barkod === '' ) {
			wp_send_json_error( [ 'message' => __( 'Barkod gerekli.', 'ptt-kargo-for-woocommerce' ) ], 400 );
		}

		$result = $this->client->get_drop_point_info( $barkod );

		if ( ! empty( $result['success'] ) ) {
			wp_send_json_success( $result );
		}
		wp_send_json_error(
			[
				'message'   => $result['mesaj'] ?? __( 'Drop point bilgisi alınamadı.', 'ptt-kargo-for-woocommerce' ),
				'raw'       => $result['raw'] ?? '',
				'http_code' => $result['http_code'] ?? null,
			],
			502
		);
	}

	public function ajax_takip(): void {
		check_ajax_referer( self::NONCE_ACTION, 'nonce' );
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_send_json_error( [ 'message' => __( 'Yetkisiz', 'ptt-kargo-for-woocommerce' ) ], 403 );
		}

		$order_id = isset( $_POST['order_id'] ) ? (int) $_POST['order_id'] : 0;
		$order    = wc_get_order( $order_id );
		if ( ! $order ) {
			wp_send_json_error( [ 'message' => __( 'Sipariş bulunamadı.', 'ptt-kargo-for-woocommerce' ) ], 404 );
		}

		$barkod = (string) $order->get_meta( Orders::META_BARKOD );
		$ref    = (string) $order->get_meta( Orders::META_REF );

		if ( $barkod === '' && $ref === '' ) {
			wp_send_json_error( [ 'message' => __( 'Bu siparişte takip edilebilecek barkod ya da referans yok.', 'ptt-kargo-for-woocommerce' ) ], 400 );
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
