<?php
namespace PTT_Kargo_WC;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Settings {
	public const OPTION_KEY = 'ptt_kargo_wc_settings';

	private const ENC_SALT = 'ptt-kargo-for-woocommerce|';

	/**
	 * Salt used before the plugin was renamed. The AES key is derived from the salt,
	 * so passwords stored by an older version can only be read with this value.
	 * Upgrade::run() decrypts with it once and re-encrypts under ENC_SALT.
	 */
	private const LEGACY_ENC_SALT = 'wc-ptt-kargo|';

	public static function defaults(): array {
		return [
			'environment'                  => 'test',
			'musteri_id'                   => '',
			'sifre_enc'                    => '',
			'barkod_prefix'                => '',
			'barkod_range_start'           => '0000',
			'barkod_range_end'             => '9999',
			'referans_prefix'              => '',
			'urun_idler'                   => '',
			'gonderici_ad'                 => '',
			'gonderici_adres'              => '',
			'gonderici_il'                 => '',
			'gonderici_ilce'               => '',
			'gonderici_posta'              => '',
			'gonderici_tel'                => '',
			'gonderici_email'              => '',
			// siparisIstekEkle2 wants first and last name separately. Left empty, the last
			// word of gonderici_ad is used as the surname.
			'gonderici_soyad'              => '',
			// Postal cheque account number from the PTT onboarding e-mail. Sent as
			// <xsd:rezerve1> on cash-on-delivery shipments.
			'posta_ceki_no'                => '',

			// Off by default, so PTT returns undeliverable parcels to the sender address.
			// When on, the iade_* fields below are sent as iadeAAdres/iadeAliciAdi/...
			'iade_adresi_farkli'           => 0,
			'iade_ad'                      => '',
			'iade_adres'                   => '',
			'iade_il'                      => '',
			'iade_ilce'                    => '',
			'iade_tel'                     => '',
			'iade_email'                   => '',
			'varsayilan_agirlik'           => 500,
			'varsayilan_desi'              => 1,
			'ekhizmet'                     => '',

			// 'static' (fixed default), 'wc_product' (line item weight x qty) or
			// 'wc_product_fallback' (product weight when set, otherwise the default).
			'weight_source'                => 'static',
			// 'static' or 'wc_product' (max of each product dimension, assuming one package).
			'dimensions_source'            => 'static',

			// WC payment method ids treated as "charge the recipient".
			// An empty array disables the COD logic entirely.
			'cod_payment_methods'          => [],
			// Service code appended to ekhizmet for COD orders (PTT default 'OS').
			'cod_extra_service_code'       => 'OS',

			// Service code appended when insurance is toggled on in the popup (PTT default 'DK').
			'insurance_extra_service_code' => 'DK',

			'order_statuses'            => [ 'processing', 'on-hold' ],

			// Customer-facing tracking: show the barcode and tracking link in WooCommerce
			// emails and on the My Account order page once a shipment exists.
			'customer_tracking'         => 1,
			'customer_tracking_emails'  => [ 'customer_completed_order' ],
			'customer_tracking_account' => 1,

			// Label appearance
			'label_logo_url'               => '',
			'label_header_title'           => '',
			'label_header_subtitle'        => '',

			// Label blocks to render (1/0)
			'label_show_order'             => 1,
			'label_show_recipient'         => 1,
			'label_show_products'          => 1,
			'label_show_barcode'           => 1,
			'label_show_sender'            => 1,

		];
	}

	public function register(): void {
		add_action( 'admin_init', [ $this, 'register_setting' ] );
	}

	public function register_setting(): void {
		register_setting(
			'ptt_kargo_wc_settings_group',
			self::OPTION_KEY,
			[
				'type'              => 'array',
				'sanitize_callback' => [ $this, 'sanitize' ],
				'default'           => self::defaults(),
			]
		);
	}

	public function all(): array {
		$saved = get_option( self::OPTION_KEY, [] );
		return array_merge( self::defaults(), is_array( $saved ) ? $saved : [] );
	}

	/**
	 * Updates a single field directly, for programmatic use.
	 */
	public function update( string $key, $value ): void {
		$current         = $this->all();
		$current[ $key ] = $value;
		update_option( self::OPTION_KEY, $current );
	}

	public function update_many( array $values ): void {
		$current = array_merge( $this->all(), $values );
		update_option( self::OPTION_KEY, $current );
	}

	public function get( string $key, $default = null ) {
		$all = $this->all();
		return $all[ $key ] ?? $default;
	}

	/**
	 * Tab-aware sanitize: starts from the stored settings and only updates the fields of
	 * the submitted tab, read from the `__tab` hidden field. Checkbox groups are reset
	 * only when they belong to that tab.
	 */
	public function sanitize( $input ): array {
		$clean = $this->all(); // Keep the settings of every other tab.
		$input = is_array( $input ) ? $input : [];

		$tab = isset( $input['__tab'] ) ? sanitize_key( (string) $input['__tab'] ) : '';

		// Which tab is allowed to write which fields.
		$tab_fields = [
			'connection' => [ 'environment', 'musteri_id', 'sifre', 'sifre_enc' ],
			'barcode'    => [ 'barkod_prefix', 'barkod_range_start', 'barkod_range_end', 'referans_prefix' ],
			'sender'     => [
				'gonderici_ad',
				'gonderici_soyad',
				'gonderici_adres',
				'gonderici_il',
				'gonderici_ilce',
				'gonderici_posta',
				'gonderici_tel',
				'gonderici_email',
				'posta_ceki_no',
				'iade_adresi_farkli',
				'iade_ad',
				'iade_adres',
				'iade_il',
				'iade_ilce',
				'iade_tel',
				'iade_email',
			],
			'label'      => [
				'label_logo_url',
				'label_header_title',
				'label_header_subtitle',
				'label_show_order',
				'label_show_recipient',
				'label_show_products',
				'label_show_barcode',
				'label_show_sender',
			],
			'products'   => [ 'urun_idler', 'order_statuses' ],
			'defaults'   => [ 'varsayilan_agirlik', 'varsayilan_desi', 'ekhizmet', 'weight_source', 'dimensions_source' ],
			'payment'    => [ 'cod_payment_methods', 'cod_extra_service_code', 'insurance_extra_service_code' ],
			'customer'   => [ 'customer_tracking', 'customer_tracking_emails', 'customer_tracking_account' ],
		];

		// Unknown tab: process every field, for backwards compatibility.
		$active_keys = isset( $tab_fields[ $tab ] ) ? $tab_fields[ $tab ] : array_merge( ...array_values( $tab_fields ) );

		$apply = function ( string $key ) use ( &$clean, $input, $active_keys ) {
			if ( ! in_array( $key, $active_keys, true ) ) {
				return false;
			}
			return true;
		};

		if ( $apply( 'environment' ) ) {
			$clean['environment'] = in_array( $input['environment'] ?? '', [ 'test', 'prod' ], true ) ? $input['environment'] : 'test';
		}
		if ( $apply( 'musteri_id' ) ) {
			$clean['musteri_id'] = preg_replace( '/\D/', '', $input['musteri_id'] ?? '' );
		}
		if ( $apply( 'sifre' ) ) {
			$raw_sifre = $input['sifre'] ?? '';
			if ( is_string( $raw_sifre ) && $raw_sifre !== '' ) {
				$clean['sifre_enc'] = self::encrypt( $raw_sifre );
			}
		}

		if ( $apply( 'barkod_prefix' ) ) {
			$clean['barkod_prefix'] = preg_replace( '/\D/', '', $input['barkod_prefix'] ?? '' );
		}
		if ( $apply( 'barkod_range_start' ) ) {
			$clean['barkod_range_start'] = preg_replace( '/\D/', '', $input['barkod_range_start'] ?? '0000' );
		}
		if ( $apply( 'barkod_range_end' ) ) {
			$clean['barkod_range_end'] = preg_replace( '/\D/', '', $input['barkod_range_end'] ?? '9999' );
		}
		if ( $apply( 'referans_prefix' ) ) {
			$clean['referans_prefix'] = sanitize_text_field( $input['referans_prefix'] ?? '' );
		}

		// Barcode validation, "barcode" tab only. PTT allocates 12 digits (prefix + serial)
		// and the 13th is a computed check digit, so prefix + range_end must be 12 digits and
		// range_start must be the same length and not greater. Invalid input keeps the old
		// values and raises an admin notice.
		if ( $tab === 'barcode' ) {
			$bp = (string) $clean['barkod_prefix'];
			$bs = (string) $clean['barkod_range_start'];
			$be = (string) $clean['barkod_range_end'];

			$errors = [];
			if ( strlen( $bp . $be ) !== 12 ) {
				$errors[] = sprintf(
				/* translators: 1: prefix length, 2: range end length, 3: combined length */
					__( 'Invalid barcode range: prefix (%1$d digits) + end (%2$d digits) must total 12, but is currently %3$d. PTT allocates a 12-digit range; the 13th digit is an automatic check digit.', 'barkoda-shipping-for-woocommerce' ),
					strlen( $bp ),
					strlen( $be ),
					strlen( $bp . $be )
				);
			}
			if ( strlen( $bs ) !== strlen( $be ) ) {
				$errors[] = __( 'The barcode range start and end must have the same number of digits.', 'barkoda-shipping-for-woocommerce' );
			}
			if ( strlen( $bs ) === strlen( $be ) && $bs !== '' && (int) $bs > (int) $be ) {
				$errors[] = __( 'The barcode range start cannot be greater than the end.', 'barkoda-shipping-for-woocommerce' );
			}

			if ( ! empty( $errors ) ) {
				$old                         = $this->all();
				$clean['barkod_prefix']      = $old['barkod_prefix'];
				$clean['barkod_range_start'] = $old['barkod_range_start'];
				$clean['barkod_range_end']   = $old['barkod_range_end'];
				foreach ( $errors as $i => $msg ) {
					add_settings_error( self::OPTION_KEY, 'barkod_invalid_' . $i, $msg, 'error' );
				}
			}
		}

		if ( $apply( 'gonderici_ad' ) ) {
			$clean['gonderici_ad'] = sanitize_text_field( $input['gonderici_ad'] ?? '' );
		}
		if ( $apply( 'gonderici_soyad' ) ) {
			$clean['gonderici_soyad'] = sanitize_text_field( $input['gonderici_soyad'] ?? '' );
		}
		if ( $apply( 'gonderici_adres' ) ) {
			$clean['gonderici_adres'] = sanitize_text_field( $input['gonderici_adres'] ?? '' );
		}
		if ( $apply( 'gonderici_il' ) ) {
			$clean['gonderici_il'] = sanitize_text_field( $input['gonderici_il'] ?? '' );
		}
		if ( $apply( 'gonderici_ilce' ) ) {
			$clean['gonderici_ilce'] = sanitize_text_field( $input['gonderici_ilce'] ?? '' );
		}
		if ( $apply( 'gonderici_posta' ) ) {
			$clean['gonderici_posta'] = preg_replace( '/\D/', '', $input['gonderici_posta'] ?? '' );
		}
		if ( $apply( 'gonderici_tel' ) ) {
			$clean['gonderici_tel'] = preg_replace( '/\D/', '', $input['gonderici_tel'] ?? '' );
		}
		if ( $apply( 'gonderici_email' ) ) {
			$clean['gonderici_email'] = sanitize_email( $input['gonderici_email'] ?? '' );
		}
		// Postal cheque account number: PTT expects 8 digits (rezerve1).
		if ( $apply( 'posta_ceki_no' ) ) {
			$digits                 = preg_replace( '/\D/', '', (string) ( $input['posta_ceki_no'] ?? '' ) );
			$clean['posta_ceki_no'] = strlen( $digits ) > 8 ? substr( $digits, 0, 8 ) : $digits;
		}

		// Return address: one checkbox plus six text fields, only writable from the sender tab.
		if ( $apply( 'iade_adresi_farkli' ) ) {
			$clean['iade_adresi_farkli'] = ! empty( $input['iade_adresi_farkli'] ) ? 1 : 0;
		}
		if ( $apply( 'iade_ad' ) ) {
			$clean['iade_ad'] = sanitize_text_field( $input['iade_ad'] ?? '' );
		}
		if ( $apply( 'iade_adres' ) ) {
			$clean['iade_adres'] = sanitize_text_field( $input['iade_adres'] ?? '' );
		}
		if ( $apply( 'iade_il' ) ) {
			$clean['iade_il'] = sanitize_text_field( $input['iade_il'] ?? '' );
		}
		if ( $apply( 'iade_ilce' ) ) {
			$clean['iade_ilce'] = sanitize_text_field( $input['iade_ilce'] ?? '' );
		}
		if ( $apply( 'iade_tel' ) ) {
			$digits = preg_replace( '/\D/', '', (string) ( $input['iade_tel'] ?? '' ) );
			if ( strlen( $digits ) > 10 && strpos( $digits, '90' ) === 0 ) {
				$digits = substr( $digits, 2 );
			} elseif ( strlen( $digits ) === 11 && $digits[0] === '0' ) {
				$digits = substr( $digits, 1 );
			}
			$clean['iade_tel'] = $digits;
		}
		if ( $apply( 'iade_email' ) ) {
			$clean['iade_email'] = sanitize_email( $input['iade_email'] ?? '' );
		}

		if ( $apply( 'label_logo_url' ) ) {
			$clean['label_logo_url'] = esc_url_raw( $input['label_logo_url'] ?? '' );
		}
		if ( $apply( 'label_header_title' ) ) {
			$clean['label_header_title'] = sanitize_text_field( $input['label_header_title'] ?? '' );
		}
		if ( $apply( 'label_header_subtitle' ) ) {
			$clean['label_header_subtitle'] = sanitize_text_field( $input['label_header_subtitle'] ?? '' );
		}

		// label_show_* checkboxes are reset only on the label tab, where absent means 0.
		foreach ( [ 'label_show_order', 'label_show_recipient', 'label_show_products', 'label_show_barcode', 'label_show_sender' ] as $tk ) {
			if ( $apply( $tk ) ) {
				$clean[ $tk ] = ! empty( $input[ $tk ] ) ? 1 : 0;
			}
		}

		if ( $apply( 'urun_idler' ) ) {
			$clean['urun_idler'] = $this->clean_id_list( $input['urun_idler'] ?? '' );
		}
		if ( $apply( 'order_statuses' ) ) {
			$durumlar                   = $input['order_statuses'] ?? [];
			$clean['order_statuses'] = array_values( array_filter( array_map( 'sanitize_key', (array) $durumlar ) ) );
		}

		if ( $apply( 'varsayilan_agirlik' ) ) {
			$clean['varsayilan_agirlik'] = max( 1, (int) ( $input['varsayilan_agirlik'] ?? 500 ) );
		}
		if ( $apply( 'varsayilan_desi' ) ) {
			$clean['varsayilan_desi'] = max( 1, (int) ( $input['varsayilan_desi'] ?? 1 ) );
		}
		if ( $apply( 'ekhizmet' ) ) {
			$clean['ekhizmet'] = strtoupper( preg_replace( '/[^A-Za-z]/', '', (string) ( $input['ekhizmet'] ?? '' ) ) );
		}

		if ( $apply( 'weight_source' ) ) {
			$clean['weight_source'] = in_array( $input['weight_source'] ?? 'static', [ 'static', 'wc_product', 'wc_product_fallback' ], true )
				? $input['weight_source']
				: 'static';
		}
		if ( $apply( 'dimensions_source' ) ) {
			$clean['dimensions_source'] = in_array( $input['dimensions_source'] ?? 'static', [ 'static', 'wc_product' ], true )
				? $input['dimensions_source']
				: 'static';
		}

		if ( $apply( 'cod_payment_methods' ) ) {
			$raw                          = $input['cod_payment_methods'] ?? [];
			$clean['cod_payment_methods'] = array_values( array_filter( array_map( 'sanitize_key', (array) $raw ) ) );
		}
		if ( $apply( 'cod_extra_service_code' ) ) {
			// PTT extra service codes are always uppercase letters, e.g. OS, DK, UA, DKUA.
			$clean['cod_extra_service_code'] = strtoupper( preg_replace( '/[^A-Za-z]/', '', (string) ( $input['cod_extra_service_code'] ?? 'OS' ) ) );
			if ( $clean['cod_extra_service_code'] === '' ) {
				$clean['cod_extra_service_code'] = 'OS';
			}
		}

		if ( $apply( 'customer_tracking' ) ) {
			$clean['customer_tracking'] = empty( $input['customer_tracking'] ) ? 0 : 1;
		}
		if ( $apply( 'customer_tracking_account' ) ) {
			$clean['customer_tracking_account'] = empty( $input['customer_tracking_account'] ) ? 0 : 1;
		}
		if ( $apply( 'customer_tracking_emails' ) ) {
			$raw_mails                         = $input['customer_tracking_emails'] ?? [];
			$clean['customer_tracking_emails'] = array_values( array_filter( array_map( 'sanitize_key', (array) $raw_mails ) ) );
		}

		if ( $apply( 'insurance_extra_service_code' ) ) {
			$clean['insurance_extra_service_code'] = strtoupper( preg_replace( '/[^A-Za-z]/', '', (string) ( $input['insurance_extra_service_code'] ?? 'DK' ) ) );
			if ( $clean['insurance_extra_service_code'] === '' ) {
				$clean['insurance_extra_service_code'] = 'DK';
			}
		}

		return $clean;
	}

	private function clean_id_list( $raw ): string {
		$parts = preg_split( '/[\s,;]+/', (string) $raw );
		$ids   = [];
		foreach ( $parts as $p ) {
			$p = preg_replace( '/\D/', '', $p );
			if ( $p !== '' ) {
				$ids[] = $p;
			}
		}
		return implode( ',', array_unique( $ids ) );
	}

	public function product_ids(): array {
		$raw = (string) $this->get( 'urun_idler', '' );
		if ( $raw === '' ) {
			return [];
		}
		return array_map( 'intval', array_filter( array_map( 'trim', explode( ',', $raw ) ) ) );
	}

	public function cod_payment_methods(): array {
		$raw = $this->get( 'cod_payment_methods', [] );
		return is_array( $raw ) ? array_values( array_filter( array_map( 'sanitize_key', $raw ) ) ) : [];
	}

	/**
	 * siparisIstekEkle2 expects the sender's first and last name as separate fields.
	 * Uses `gonderici_soyad` when set, otherwise splits `gonderici_ad` at the last space
	 * ("Ali Veli Yilmaz" becomes "Ali Veli" + "Yilmaz").
	 *
	 * @return array{ad:string,soyad:string}
	 */
	public function gonderici_ad_soyad(): array {
		$ad    = trim( (string) $this->get( 'gonderici_ad', '' ) );
		$soyad = trim( (string) $this->get( 'gonderici_soyad', '' ) );

		if ( $soyad !== '' ) {
			return [
				'ad'    => $ad,
				'soyad' => $soyad,
			];
		}
		if ( $ad === '' ) {
			return [
				'ad'    => '',
				'soyad' => '',
			];
		}
		// For a single-word sender name both fields repeat it; PTT requires at least 1 char.
		$pos = strrpos( $ad, ' ' );
		if ( $pos === false ) {
			return [
				'ad'    => $ad,
				'soyad' => $ad,
			];
		}
		return [
			'ad'    => trim( substr( $ad, 0, $pos ) ),
			'soyad' => trim( substr( $ad, $pos + 1 ) ),
		];
	}

	public function sifre_plain(): string {
		$enc = (string) $this->get( 'sifre_enc', '' );
		return $enc === '' ? '' : (string) self::decrypt( $enc );
	}

	public function endpoint_kabul(): string {
		return $this->get( 'environment' ) === 'prod'
			? 'https://pttws.ptt.gov.tr/PttVeriYukleme/services/Sorgu'
			: 'https://pttws.ptt.gov.tr/PttVeriYuklemeTest/services/Sorgu';
	}

	public function endpoint_takip(): string {
		return $this->get( 'environment' ) === 'prod'
			? 'https://pttws.ptt.gov.tr/GonderiTakipV2/services/Sorgu'
			: 'https://pttws.ptt.gov.tr/GonderiTakipV2Test/services/Sorgu';
	}

	/**
	 * Derives the AES key from the site's AUTH_KEY.
	 *
	 * @param bool $legacy Use the pre-rename salt, for reading old values during upgrade.
	 */
	private static function key( bool $legacy = false ): string {
		$fallback = $legacy ? 'wc-ptt-kargo-fallback-key' : 'ptt-kargo-for-woocommerce-fallback-key';
		$salt     = $legacy ? self::LEGACY_ENC_SALT : self::ENC_SALT;
		$k        = defined( 'AUTH_KEY' ) ? AUTH_KEY : $fallback;
		return hash( 'sha256', $salt . $k, true );
	}

	public static function encrypt( string $plain ): string {
		$iv     = random_bytes( 16 );
		$cipher = openssl_encrypt( $plain, 'AES-256-CBC', self::key(), OPENSSL_RAW_DATA, $iv );
		if ( $cipher === false ) {
			return '';
		}
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- encoding binary ciphertext for storage in an option, not obfuscating code.
		return base64_encode( $iv . $cipher );
	}

	/**
	 * @param string $enc    Base64 of IV + ciphertext.
	 * @param bool   $legacy Decrypt with the pre-rename salt.
	 */
	public static function decrypt( string $enc, bool $legacy = false ): string {
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- decoding the stored ciphertext written by encrypt() above.
		$raw = base64_decode( $enc, true );
		if ( $raw === false || strlen( $raw ) < 17 ) {
			return '';
		}
		$iv     = substr( $raw, 0, 16 );
		$cipher = substr( $raw, 16 );
		$plain  = openssl_decrypt( $cipher, 'AES-256-CBC', self::key( $legacy ), OPENSSL_RAW_DATA, $iv );
		return $plain === false ? '' : $plain;
	}
}
