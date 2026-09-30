<?php
namespace Barkoda_Shipping;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * wp_remote_post wrapper around the PTT SOAP services.
 *
 * Responses are read with regex tag extraction instead of SimpleXML so the parser
 * stays agnostic to whatever namespace prefixes the server happens to return.
 */
final class PTT_Client {
	/** @var Settings */
	private $settings;

	/** @var string Last SOAP request body, kept for debugging. */
	private $last_request = '';

	public function __construct( Settings $settings ) {
		$this->settings = $settings;
	}

	public function kabul_ekle( array $gonderi, ?int $order_id = null ) {
		$musteri_id = (string) $this->settings->get( 'musteri_id', '' );
		$sifre      = $this->settings->sifre_plain();

		if ( $musteri_id === '' || $sifre === '' ) {
			$msg = __( 'The PTT customer number or password is missing from the settings.', 'barkoda-shipping-for-woocommerce' );
			Logs::record_event(
				'kabulEkle2',
				$order_id,
				false,
				$msg,
				[
					'reason'      => 'missing_credentials',
					'has_musteri' => $musteri_id !== '',
					'has_sifre'   => $sifre !== '',
				]
			);
			return [
				'success' => false,
				'mesaj'   => $msg,
			];
		}

		$ref_prefix = (string) $this->settings->get( 'referans_prefix', '' );
		$dosya_pre  = $ref_prefix !== '' ? rtrim( $ref_prefix, '-_' ) : 'WCPTT';
		$dosya_adi  = $dosya_pre . '-' . gmdate( 'YmdHis' ) . '-' . wp_generate_password( 4, false, false );

		$gonderi = (array) apply_filters( 'barkoda_kabul_fields', $gonderi );

		$body = $this->build_kabul_envelope( $musteri_id, $sifre, $dosya_adi, $gonderi );
		$body = (string) apply_filters( 'barkoda_soap_request_body', $body, 'kabulEkle2', $gonderi );

		$this->last_request = $body;

		$endpoint = $this->settings->endpoint_kabul();
		$headers  = [
			'Content-Type' => 'application/soap+xml; charset=utf-8; action="kabulEkle2"',
		];
		$req_log  = [
			'method'   => 'POST',
			'endpoint' => $endpoint,
			'headers'  => $headers,
			'body'     => $body,
		];

		$started  = microtime( true );
		$response = wp_remote_post(
			$endpoint,
			[
				'timeout'   => (int) apply_filters( 'barkoda_http_timeout', 30, 'kabulEkle2' ),
				'sslverify' => (bool) apply_filters( 'barkoda_sslverify', true, 'kabulEkle2' ),
				'headers'   => $headers,
				'body'      => $body,
			]
		);
		$duration = ( microtime( true ) - $started ) * 1000.0;

		if ( is_wp_error( $response ) ) {
			$msg = 'HTTP hatası: ' . $response->get_error_message();
			Logs::record_http(
				'kabulEkle2',
				$order_id,
				false,
				$msg,
				$req_log,
				[
					'network_error' => $response->get_error_message(),
					'wp_error_data' => $response->get_error_data(),
				],
				$duration
			);
			return [
				'success'   => false,
				'mesaj'     => $msg,
				'request'   => Logs::mask_sensitive( $body ),
				'dosya_adi' => $dosya_adi,
			];
		}

		$code         = (int) wp_remote_retrieve_response_code( $response );
		$raw          = (string) wp_remote_retrieve_body( $response );
		$resp_headers = wp_remote_retrieve_headers( $response );
		$resp_log     = [
			'code'    => $code,
			'headers' => $this->headers_to_array( $resp_headers ),
			'body'    => $raw,
		];

		if ( $code >= 400 ) {
			$msg = 'PTT servisi HTTP ' . $code . ' kodu döndürdü.';
			Logs::record_http( 'kabulEkle2', $order_id, false, $msg, $req_log, $resp_log, $duration );
			return [
				'success'   => false,
				'mesaj'     => $msg,
				'raw'       => $raw,
				'request'   => Logs::mask_sensitive( $body ),
				'http_code' => $code,
				'dosya_adi' => $dosya_adi,
			];
		}

		$parsed              = $this->parse_kabul_response( $raw );
		$parsed['request']   = Logs::mask_sensitive( $body );
		$parsed['http_code'] = $code;
		// Always return dosyaAdi: barkodVeriSil / referansVeriSil need it later.
		$parsed['dosya_adi'] = $dosya_adi;

		Logs::record_http(
			'kabulEkle2',
			$order_id,
			! empty( $parsed['success'] ),
			(string) ( $parsed['mesaj'] ?? '' ),
			$req_log,
			$resp_log,
			$duration
		);
		return $parsed;
	}

	/**
	 * Normalises wp_remote_retrieve_headers() into a plain array. Depending on the
	 * bundled Requests version it is either an array or a case-insensitive dictionary.
	 */
	private function headers_to_array( $headers ): array {
		if ( is_array( $headers ) ) {
			return $headers;
		}
		if ( is_object( $headers ) && method_exists( $headers, 'getAll' ) ) {
			return $headers->getAll();
		}
		$out = [];
		if ( is_object( $headers ) || is_array( $headers ) ) {
			foreach ( $headers as $k => $v ) {
				$out[ $k ] = $v;
			}
		}
		return $out;
	}

	/**
	 * Deletes a shipment PTT has not accepted yet, by barcode.
	 * Uses a SOAP 1.1 envelope on the kabul endpoint (PTT expects 1.1 for this method).
	 *
	 * @param string   $barkod    13-digit barcode.
	 * @param string   $dosya_adi dosyaAdi from the original kabulEkle call; optional but more reliable.
	 * @param int|null $order_id  Order id, for logging.
	 * @return array{success:bool,mesaj:string,raw?:string,request?:string,hata_kodu?:int|null}
	 */
	public function barkod_veri_sil( string $barkod, string $dosya_adi = '', ?int $order_id = null ): array {
		$musteri_id = (string) $this->settings->get( 'musteri_id', '' );
		$sifre      = $this->settings->sifre_plain();

		if ( $musteri_id === '' || $sifre === '' ) {
			return [
				'success' => false,
				'mesaj'   => __( 'The PTT customer number or password is missing from the settings.', 'barkoda-shipping-for-woocommerce' ),
			];
		}
		if ( $barkod === '' ) {
			return [
				'success' => false,
				'mesaj'   => __( 'The barcode to delete is empty.', 'barkoda-shipping-for-woocommerce' ),
			];
		}

		$e = function ( $v ) {
			return htmlspecialchars( (string) $v, ENT_XML1 | ENT_QUOTES, 'UTF-8' );
		};

		$body = '<?xml version="1.0" encoding="UTF-8"?>'
			. '<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:kab="http://kabul.ptt.gov.tr" xmlns:xsd="http://kabul.ptt.gov.tr/xsd">'
			. '<soapenv:Header/><soapenv:Body><kab:barkodVeriSil><kab:inpDelete>'
			. '<xsd:barcode>' . $e( $barkod ) . '</xsd:barcode>'
			. ( $dosya_adi !== '' ? '<xsd:dosyaAdi>' . $e( $dosya_adi ) . '</xsd:dosyaAdi>' : '' )
			. '<xsd:musteriId>' . $e( $musteri_id ) . '</xsd:musteriId>'
			. '<xsd:sifre>' . $e( $sifre ) . '</xsd:sifre>'
			. '</kab:inpDelete></kab:barkodVeriSil></soapenv:Body></soapenv:Envelope>';

		return $this->dispatch_delete_request( 'barkodVeriSil', $body, $order_id );
	}

	/**
	 * Deletes a shipment PTT has not accepted yet, by customer reference number.
	 * Removes every data group tied to that reference, so take care with multiple records.
	 *
	 * @param string   $referans  Customer reference number sent earlier.
	 * @param string   $dosya_adi Optional.
	 * @param int|null $order_id  Order id, for logging.
	 */
	public function referans_veri_sil( string $referans, string $dosya_adi = '', ?int $order_id = null ): array {
		$musteri_id = (string) $this->settings->get( 'musteri_id', '' );
		$sifre      = $this->settings->sifre_plain();

		if ( $musteri_id === '' || $sifre === '' ) {
			return [
				'success' => false,
				'mesaj'   => __( 'The PTT customer number or password is missing from the settings.', 'barkoda-shipping-for-woocommerce' ),
			];
		}
		if ( $referans === '' ) {
			return [
				'success' => false,
				'mesaj'   => __( 'The reference number to delete is empty.', 'barkoda-shipping-for-woocommerce' ),
			];
		}

		$e = function ( $v ) {
			return htmlspecialchars( (string) $v, ENT_XML1 | ENT_QUOTES, 'UTF-8' );
		};

		$body = '<?xml version="1.0" encoding="UTF-8"?>'
			. '<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:kab="http://kabul.ptt.gov.tr" xmlns:xsd="http://kabul.ptt.gov.tr/xsd">'
			. '<soapenv:Header/><soapenv:Body><kab:referansVeriSil><kab:inpRefDelete>'
			. ( $dosya_adi !== '' ? '<xsd:dosyaAdi>' . $e( $dosya_adi ) . '</xsd:dosyaAdi>' : '' )
			. '<xsd:musteriId>' . $e( $musteri_id ) . '</xsd:musteriId>'
			. '<xsd:referansNo>' . $e( $referans ) . '</xsd:referansNo>'
			. '<xsd:sifre>' . $e( $sifre ) . '</xsd:sifre>'
			. '</kab:inpRefDelete></kab:referansVeriSil></soapenv:Body></soapenv:Envelope>';

		return $this->dispatch_delete_request( 'referansVeriSil', $body, $order_id );
	}

	/**
	 * Sends a SOAP 1.1 delete request and parses the reply.
	 * Targets the kabul endpoint; the SOAPAction header is required for PTT gateway routing.
	 */
	private function dispatch_delete_request( string $operation, string $body, ?int $order_id ): array {
		$endpoint = $this->settings->endpoint_kabul();
		$headers  = [
			'Content-Type' => 'text/xml; charset=utf-8',
			'SOAPAction'   => '"' . $operation . '"',
		];

		$req_log            = [
			'method'   => 'POST',
			'endpoint' => $endpoint,
			'headers'  => $headers,
			'body'     => $body,
		];
		$logged_request     = "POST {$endpoint}\n" . $this->format_headers_for_log( $headers ) . "\n\n" . Logs::mask_sensitive( $body );
		$this->last_request = $body;

		$started  = microtime( true );
		$response = wp_remote_post(
			$endpoint,
			[
				'timeout'   => (int) apply_filters( 'barkoda_http_timeout', 30, $operation ),
				'sslverify' => (bool) apply_filters( 'barkoda_sslverify', true, $operation ),
				'headers'   => $headers,
				'body'      => $body,
			]
		);
		$duration = ( microtime( true ) - $started ) * 1000.0;

		if ( is_wp_error( $response ) ) {
			$msg = 'HTTP hatası: ' . $response->get_error_message();
			Logs::record_http(
				$operation,
				$order_id,
				false,
				$msg,
				$req_log,
				[
					'network_error' => $response->get_error_message(),
					'wp_error_data' => $response->get_error_data(),
				],
				$duration
			);
			return [
				'success' => false,
				'mesaj'   => $msg,
				'request' => $logged_request,
			];
		}

		$code         = (int) wp_remote_retrieve_response_code( $response );
		$raw          = (string) wp_remote_retrieve_body( $response );
		$resp_headers = wp_remote_retrieve_headers( $response );
		$resp_log     = [
			'code'    => $code,
			'headers' => $this->headers_to_array( $resp_headers ),
			'body'    => $raw,
		];

		$parsed              = $this->parse_delete_response( $raw );
		$parsed['request']   = $logged_request;
		$parsed['http_code'] = $code;
		if ( $code >= 400 && empty( $parsed['success'] ) && empty( $parsed['mesaj'] ) ) {
			/* translators: %d: HTTP status code */
			$parsed['mesaj'] = sprintf( __( 'The PTT service returned HTTP status %d.', 'barkoda-shipping-for-woocommerce' ), $code );
		}

		Logs::record_http( $operation, $order_id, ! empty( $parsed['success'] ), (string) ( $parsed['mesaj'] ?? '' ), $req_log, $resp_log, $duration );
		return $parsed;
	}

	/**
	 * Parses barkodVeriSil / referansVeriSil replies.
	 * OutputDelete / OutputRefDelete carry only aciklama and hataKodu.
	 */
	private function parse_delete_response( string $raw ): array {
		if ( $raw === '' ) {
			return [
				'success' => false,
				'mesaj'   => __( 'Empty response from the PTT service.', 'barkoda-shipping-for-woocommerce' ),
				'raw'     => $raw,
			];
		}

		// SOAP 1.1 faults use <faultstring>, SOAP 1.2 uses <Text>. Check both.
		$fault_reason = $this->extract_tag( $raw, 'faultstring' );
		if ( $fault_reason === '' ) {
			$fault_reason = $this->extract_tag( $raw, 'Text' );
		}
		if ( stripos( $raw, 'Fault' ) !== false && $fault_reason !== '' ) {
			return [
				'success' => false,
				'mesaj'   => 'SOAP Fault: ' . $fault_reason,
				'raw'     => $raw,
			];
		}

		$hata_kodu = $this->extract_tag( $raw, 'hataKodu' );
		$aciklama  = $this->extract_tag( $raw, 'aciklama' );

		$hata_int = $hata_kodu === '' ? null : (int) $hata_kodu;
		$success  = ( $hata_int === 1 );

		return [
			'success'   => $success,
			'mesaj'     => $aciklama !== '' ? $aciklama : ( $success ? __( 'Operation successful.', 'barkoda-shipping-for-woocommerce' ) : __( 'PTT returned no explanation.', 'barkoda-shipping-for-woocommerce' ) ),
			'hata_kodu' => $hata_int,
			'raw'       => $raw,
		];
	}

	/**
	 * Requests a courier pickup so PTT collects the shipments from the sender.
	 *
	 * @param array $params {
	 *     @type int    $adet                 Total package count (required).
	 *     @type int    $agirlik              Total weight in grams (optional).
	 *     @type int    $desi                 Total volumetric weight (optional).
	 *     @type int    $en                   Width in cm (optional).
	 *     @type int    $boy                  Length in cm (optional).
	 *     @type int    $yukseklik            Height in cm (optional).
	 *     @type string $ekhizmet             Additional service codes (optional).
	 *     @type float  $deger_konulmus_ucret Insured value; requires DK in $ekhizmet (optional).
	 *     @type string $randevu_baslangic    Appointment start, may be empty.
	 *     @type string $randevu_bitis        Appointment end, may be empty.
	 *     @type float  $ucret                Defaults to 0.
	 * }
	 */
	public function siparis_istek_ekle2( array $params ): array {
		$musteri_id = (string) $this->settings->get( 'musteri_id', '' );
		$sifre      = $this->settings->sifre_plain();

		if ( $musteri_id === '' || $sifre === '' ) {
			$msg = __( 'The PTT customer number or password is missing from the settings.', 'barkoda-shipping-for-woocommerce' );
			Logs::record_event( 'siparisIstekEkle2', null, false, $msg, [ 'reason' => 'missing_credentials' ] );
			return [
				'success' => false,
				'mesaj'   => $msg,
			];
		}

		$adet = max( 1, (int) ( $params['adet'] ?? 0 ) );

		$gonderici = $this->settings->gonderici_ad_soyad();
		if ( $gonderici['ad'] === '' || $gonderici['soyad'] === '' ) {
			$msg = __( 'The sender first or last name is missing. Fill them in on the Sender tab.', 'barkoda-shipping-for-woocommerce' );
			Logs::record_event(
				'siparisIstekEkle2',
				null,
				false,
				$msg,
				[
					'reason'    => 'missing_sender_name',
					'gonderici' => $gonderici,
				]
			);
			return [
				'success' => false,
				'mesaj'   => $msg,
			];
		}

		$gonderici_il    = (string) $this->settings->get( 'gonderici_il', '' );
		$gonderici_ilce  = (string) $this->settings->get( 'gonderici_ilce', '' );
		$gonderici_adres = (string) $this->settings->get( 'gonderici_adres', '' );
		$gonderici_tel   = $this->clean_phone( (string) $this->settings->get( 'gonderici_tel', '' ) );
		$gonderici_email = (string) $this->settings->get( 'gonderici_email', '' );
		$gonderici_posta = (string) $this->settings->get( 'gonderici_posta', '' );

		if ( $gonderici_il === '' || $gonderici_ilce === '' || $gonderici_adres === '' ) {
			$msg = __( 'The sender province, district or address is missing from the settings.', 'barkoda-shipping-for-woocommerce' );
			Logs::record_event(
				'siparisIstekEkle2',
				null,
				false,
				$msg,
				[
					'reason'    => 'missing_sender_address',
					'has_il'    => $gonderici_il !== '',
					'has_ilce'  => $gonderici_ilce !== '',
					'has_adres' => $gonderici_adres !== '',
				]
			);
			return [
				'success' => false,
				'mesaj'   => $msg,
			];
		}
		if ( $gonderici_tel === '' && $gonderici_email === '' ) {
			$msg = __( 'A sender phone number or email is required; PTT needs at least one of them.', 'barkoda-shipping-for-woocommerce' );
			Logs::record_event( 'siparisIstekEkle2', null, false, $msg, [ 'reason' => 'missing_contact' ] );
			return [
				'success' => false,
				'mesaj'   => $msg,
			];
		}

		$body               = $this->build_siparis_istek_envelope( $musteri_id, $sifre, $adet, $params );
		$body               = (string) apply_filters( 'barkoda_soap_request_body', $body, 'siparisIstekEkle2', $params );
		$this->last_request = $body;

		$endpoint = $this->settings->endpoint_kabul();
		$headers  = [
			'Content-Type' => 'application/soap+xml; charset=utf-8; action="siparisIstekEkle2"',
		];
		$req_log  = [
			'method'   => 'POST',
			'endpoint' => $endpoint,
			'headers'  => $headers,
			'body'     => $body,
		];

		$started  = microtime( true );
		$response = wp_remote_post(
			$endpoint,
			[
				'timeout'   => (int) apply_filters( 'barkoda_http_timeout', 30, 'siparisIstekEkle2' ),
				'sslverify' => (bool) apply_filters( 'barkoda_sslverify', true, 'siparisIstekEkle2' ),
				'headers'   => $headers,
				'body'      => $body,
			]
		);
		$duration = ( microtime( true ) - $started ) * 1000.0;

		if ( is_wp_error( $response ) ) {
			$msg = 'HTTP hatası: ' . $response->get_error_message();
			Logs::record_http(
				'siparisIstekEkle2',
				null,
				false,
				$msg,
				$req_log,
				[
					'network_error' => $response->get_error_message(),
					'wp_error_data' => $response->get_error_data(),
				],
				$duration
			);
			return [
				'success' => false,
				'mesaj'   => $msg,
				'request' => Logs::mask_sensitive( $body ),
			];
		}

		$code         = (int) wp_remote_retrieve_response_code( $response );
		$raw          = (string) wp_remote_retrieve_body( $response );
		$resp_headers = wp_remote_retrieve_headers( $response );
		$resp_log     = [
			'code'    => $code,
			'headers' => $this->headers_to_array( $resp_headers ),
			'body'    => $raw,
		];

		$parsed              = $this->parse_siparis_istek_response( $raw );
		$parsed['request']   = Logs::mask_sensitive( $body );
		$parsed['http_code'] = $code;
		if ( $code >= 400 && empty( $parsed['mesaj'] ) ) {
			/* translators: %d: HTTP status code */
			$parsed['mesaj'] = sprintf( __( 'The PTT service returned HTTP status %d.', 'barkoda-shipping-for-woocommerce' ), $code );
		}

		Logs::record_http( 'siparisIstekEkle2', null, ! empty( $parsed['success'] ), (string) ( $parsed['mesaj'] ?? '' ), $req_log, $resp_log, $duration );
		return $parsed;
	}

	/**
	 * Returns the PTT branch currently holding the given barcode.
	 * WSDL getDropPointInfo, InputDropPoint type: barcode/password/username (lowercase).
	 */
	public function get_drop_point_info( string $barkod ): array {
		$musteri_id = (string) $this->settings->get( 'musteri_id', '' );
		$sifre      = $this->settings->sifre_plain();

		if ( $musteri_id === '' || $sifre === '' ) {
			$msg = __( 'The PTT customer number or password is missing from the settings.', 'barkoda-shipping-for-woocommerce' );
			Logs::record_event( 'getDropPointInfo', null, false, $msg, [ 'reason' => 'missing_credentials' ] );
			return [
				'success' => false,
				'mesaj'   => $msg,
			];
		}

		$body     = $this->build_takip_envelope(
			'getDropPointInfo',
			[
				'barcode'  => $barkod,
				'password' => $sifre,
				'username' => $musteri_id,
			]
		);
		$endpoint = $this->settings->endpoint_takip();
		$headers  = $this->takip_headers( 'getDropPointInfo' );
		$req_log  = [
			'method'   => 'POST',
			'endpoint' => $endpoint,
			'headers'  => $headers,
			'body'     => $body,
		];

		$started  = microtime( true );
		$response = wp_remote_post(
			$endpoint,
			[
				'timeout'   => (int) apply_filters( 'barkoda_http_timeout', 20, 'getDropPointInfo' ),
				'sslverify' => (bool) apply_filters( 'barkoda_sslverify', true, 'getDropPointInfo' ),
				'headers'   => $headers,
				'body'      => $body,
			]
		);
		$duration = ( microtime( true ) - $started ) * 1000.0;

		if ( is_wp_error( $response ) ) {
			$msg = $response->get_error_message();
			Logs::record_http(
				'getDropPointInfo',
				null,
				false,
				$msg,
				$req_log,
				[
					'network_error' => $msg,
					'wp_error_data' => $response->get_error_data(),
				],
				$duration
			);
			return [
				'success' => false,
				'mesaj'   => $msg,
			];
		}

		$code         = (int) wp_remote_retrieve_response_code( $response );
		$raw          = (string) wp_remote_retrieve_body( $response );
		$resp_headers = wp_remote_retrieve_headers( $response );
		$resp_log     = [
			'code'    => $code,
			'headers' => $this->headers_to_array( $resp_headers ),
			'body'    => $raw,
		];

		$parsed              = $this->parse_drop_point_response( $raw );
		$parsed['http_code'] = $code;
		Logs::record_http( 'getDropPointInfo', null, ! empty( $parsed['success'] ), (string) ( $parsed['mesaj'] ?? '' ), $req_log, $resp_log, $duration );
		return $parsed;
	}

	/**
	 * Tracks a shipment by customer reference number; fallback when the barcode is lost.
	 */
	public function takip_sorgula_referans( string $referans ): array {
		$musteri_id = (string) $this->settings->get( 'musteri_id', '' );
		$sifre      = $this->settings->sifre_plain();

		if ( $musteri_id === '' || $sifre === '' ) {
			$msg = __( 'The PTT customer number or password is missing from the settings.', 'barkoda-shipping-for-woocommerce' );
			Logs::record_event( 'gonderiSorgu_referansNo', null, false, $msg, [ 'reason' => 'missing_credentials' ] );
			return [
				'success' => false,
				'mesaj'   => $msg,
			];
		}
		if ( $referans === '' ) {
			return [
				'success' => false,
				'mesaj'   => __( 'The reference number is empty.', 'barkoda-shipping-for-woocommerce' ),
			];
		}

		$body     = $this->build_takip_envelope(
			'gonderiSorgu_referansNo',
			[
				'referansNo' => $referans,
				'kullanici'  => $musteri_id,
				'sifre'      => $sifre,
			]
		);
		$endpoint = $this->settings->endpoint_takip();
		$headers  = $this->takip_headers( 'gonderiSorgu_referansNo' );
		$req_log  = [
			'method'   => 'POST',
			'endpoint' => $endpoint,
			'headers'  => $headers,
			'body'     => $body,
		];

		$started  = microtime( true );
		$response = wp_remote_post(
			$endpoint,
			[
				'timeout'   => (int) apply_filters( 'barkoda_http_timeout', 30, 'gonderiSorgu_referansNo' ),
				'sslverify' => (bool) apply_filters( 'barkoda_sslverify', true, 'gonderiSorgu_referansNo' ),
				'headers'   => $headers,
				'body'      => $body,
			]
		);
		$duration = ( microtime( true ) - $started ) * 1000.0;

		if ( is_wp_error( $response ) ) {
			$msg = $response->get_error_message();
			Logs::record_http(
				'gonderiSorgu_referansNo',
				null,
				false,
				$msg,
				$req_log,
				[
					'network_error' => $msg,
					'wp_error_data' => $response->get_error_data(),
				],
				$duration
			);
			return [
				'success' => false,
				'mesaj'   => $msg,
			];
		}

		$code         = (int) wp_remote_retrieve_response_code( $response );
		$raw          = (string) wp_remote_retrieve_body( $response );
		$resp_headers = wp_remote_retrieve_headers( $response );
		$resp_log     = [
			'code'    => $code,
			'headers' => $this->headers_to_array( $resp_headers ),
			'body'    => $raw,
		];

		$parsed              = $this->parse_takip_response( $raw );
		$parsed['http_code'] = $code;
		Logs::record_http( 'gonderiSorgu_referansNo', null, ! empty( $parsed['success'] ), (string) ( $parsed['mesaj'] ?? '' ), $req_log, $resp_log, $duration );
		return $parsed;
	}

	/**
	 * Multi-package shipment through the kabulEkleParcaliBarkod service:
	 * one barcode per package, all sent for the same order in a single SOAP call.
	 *
	 * @param array         $base_fields Recipient data (aliciAdi, aAdres, ...) repeated for every dongu.
	 * @param array<string> $barkodlar   Consumed barcodes; the count becomes parca_adet.
	 * @param string        $irsaliye_no Optional waybill number (1-30 chars).
	 * @param int|null      $order_id    Order id, for logging.
	 */
	public function kabul_ekle_parcali_barkod( array $base_fields, array $barkodlar, string $irsaliye_no = '', ?int $order_id = null ): array {
		$musteri_id = (string) $this->settings->get( 'musteri_id', '' );
		$sifre      = $this->settings->sifre_plain();

		if ( $musteri_id === '' || $sifre === '' ) {
			$msg = __( 'The PTT customer number or password is missing from the settings.', 'barkoda-shipping-for-woocommerce' );
			Logs::record_event( 'kabulEkleParcaliBarkod', $order_id, false, $msg, [ 'reason' => 'missing_credentials' ] );
			return [
				'success' => false,
				'mesaj'   => $msg,
			];
		}
		$adet = count( $barkodlar );
		if ( $adet < 1 ) {
			return [
				'success' => false,
				'mesaj'   => __( 'The package count is invalid.', 'barkoda-shipping-for-woocommerce' ),
			];
		}

		$ref_prefix = (string) $this->settings->get( 'referans_prefix', '' );
		$dosya_pre  = $ref_prefix !== '' ? rtrim( $ref_prefix, '-_' ) : 'WCPTT';
		$dosya_adi  = $dosya_pre . '-' . gmdate( 'YmdHis' ) . '-' . wp_generate_password( 4, false, false );

		$base_fields = (array) apply_filters( 'barkoda_kabul_fields', $base_fields );

		$body = $this->build_kabul_parcali_envelope( $musteri_id, $sifre, $dosya_adi, $base_fields, $barkodlar, $irsaliye_no );
		$body = (string) apply_filters( 'barkoda_soap_request_body', $body, 'kabulEkleParcaliBarkod', $base_fields );

		$this->last_request = $body;

		$endpoint = $this->settings->endpoint_kabul();
		$headers  = [
			'Content-Type' => 'application/soap+xml; charset=utf-8; action="kabulEkleParcaliBarkod"',
		];
		$req_log  = [
			'method'   => 'POST',
			'endpoint' => $endpoint,
			'headers'  => $headers,
			'body'     => $body,
		];

		$started  = microtime( true );
		$response = wp_remote_post(
			$endpoint,
			[
				'timeout'   => (int) apply_filters( 'barkoda_http_timeout', 30, 'kabulEkleParcaliBarkod' ),
				'sslverify' => (bool) apply_filters( 'barkoda_sslverify', true, 'kabulEkleParcaliBarkod' ),
				'headers'   => $headers,
				'body'      => $body,
			]
		);
		$duration = ( microtime( true ) - $started ) * 1000.0;

		if ( is_wp_error( $response ) ) {
			$msg = 'HTTP hatası: ' . $response->get_error_message();
			Logs::record_http(
				'kabulEkleParcaliBarkod',
				$order_id,
				false,
				$msg,
				$req_log,
				[
					'network_error' => $response->get_error_message(),
					'wp_error_data' => $response->get_error_data(),
				],
				$duration
			);
			return [
				'success'   => false,
				'mesaj'     => $msg,
				'request'   => Logs::mask_sensitive( $body ),
				'dosya_adi' => $dosya_adi,
			];
		}

		$code         = (int) wp_remote_retrieve_response_code( $response );
		$raw          = (string) wp_remote_retrieve_body( $response );
		$resp_headers = wp_remote_retrieve_headers( $response );
		$resp_log     = [
			'code'    => $code,
			'headers' => $this->headers_to_array( $resp_headers ),
			'body'    => $raw,
		];

		$parsed              = $this->parse_parcali_response( $raw );
		$parsed['request']   = Logs::mask_sensitive( $body );
		$parsed['http_code'] = $code;
		$parsed['dosya_adi'] = $dosya_adi;
		if ( $code >= 400 && empty( $parsed['mesaj'] ) ) {
			/* translators: %d: HTTP status code */
			$parsed['mesaj'] = sprintf( __( 'The PTT service returned HTTP status %d.', 'barkoda-shipping-for-woocommerce' ), $code );
		}

		Logs::record_http( 'kabulEkleParcaliBarkod', $order_id, ! empty( $parsed['success'] ), (string) ( $parsed['mesaj'] ?? '' ), $req_log, $resp_log, $duration );
		return $parsed;
	}

	public function takip_sorgula_barkod( string $barkod ) {
		$musteri_id = (string) $this->settings->get( 'musteri_id', '' );
		$sifre      = $this->settings->sifre_plain();

		$body     = $this->build_takip_envelope(
			'gonderiSorgu',
			[
				'barkod'    => $barkod,
				'kullanici' => $musteri_id,
				'sifre'     => $sifre,
			]
		);
		$endpoint = $this->settings->endpoint_takip();
		$headers  = $this->takip_headers( 'gonderiSorgu' );
		$req_log  = [
			'method'   => 'POST',
			'endpoint' => $endpoint,
			'headers'  => $headers,
			'body'     => $body,
		];

		$started  = microtime( true );
		$response = wp_remote_post(
			$endpoint,
			[
				'timeout'   => (int) apply_filters( 'barkoda_http_timeout', 30, 'gonderiSorgu' ),
				'sslverify' => (bool) apply_filters( 'barkoda_sslverify', true, 'gonderiSorgu' ),
				'headers'   => $headers,
				'body'      => $body,
			]
		);
		$duration = ( microtime( true ) - $started ) * 1000.0;

		if ( is_wp_error( $response ) ) {
			$msg = $response->get_error_message();
			Logs::record_http(
				'gonderiSorgu',
				null,
				false,
				$msg,
				$req_log,
				[
					'network_error' => $msg,
					'wp_error_data' => $response->get_error_data(),
				],
				$duration
			);
			return [
				'success' => false,
				'mesaj'   => $msg,
			];
		}

		$code         = (int) wp_remote_retrieve_response_code( $response );
		$raw          = (string) wp_remote_retrieve_body( $response );
		$resp_headers = wp_remote_retrieve_headers( $response );
		$resp_log     = [
			'code'    => $code,
			'headers' => $this->headers_to_array( $resp_headers ),
			'body'    => $raw,
		];

		$parsed              = $this->parse_takip_response( $raw );
		$parsed['http_code'] = $code;
		Logs::record_http( 'gonderiSorgu', null, ! empty( $parsed['success'] ), (string) ( $parsed['mesaj'] ?? '' ), $req_log, $resp_log, $duration );
		return $parsed;
	}

	private function format_headers_for_log( array $headers ): string {
		$out = [];
		foreach ( $headers as $k => $v ) {
			$out[] = $k . ': ' . $v;
		}
		return implode( "\n", $out );
	}

	public function get_last_request() {
		return $this->last_request;
	}

	/**
	 * Verifies the credentials by querying gonderiSorgu (V1) with a synthetic barcode.
	 * Valid auth yields a plain "barcode not found" business reply: HTTP 200 and no SOAP Fault.
	 * Wrong credentials or missing permissions yield a SOAP Fault instead.
	 *
	 * @return array{success:bool,mesaj:string,raw?:string,http_code?:int}
	 */
	public function test_connection(): array {
		$musteri_id = (string) $this->settings->get( 'musteri_id', '' );
		$sifre      = $this->settings->sifre_plain();

		if ( $musteri_id === '' || $sifre === '' ) {
			$msg = __( 'No customer number or password has been entered.', 'barkoda-shipping-for-woocommerce' );
			Logs::record_event(
				'test_connection',
				null,
				false,
				$msg,
				[
					'has_musteri' => $musteri_id !== '',
					'has_sifre'   => $sifre !== '',
				]
			);
			return [
				'success' => false,
				'mesaj'   => $msg,
			];
		}

		// Synthetic barcode from the store's own prefix + range start + check digit. PTT
		// answers "barcode not found", which is enough to prove the credentials work.
		$test_barkod = $this->build_test_barkod();

		$body = $this->build_takip_envelope(
			'gonderiSorgu',
			[
				'barkod'    => $test_barkod,
				'kullanici' => $musteri_id,
				'sifre'     => $sifre,
			]
		);

		$endpoint = $this->settings->endpoint_takip();
		$headers  = $this->takip_headers( 'gonderiSorgu' );
		$req_log  = [
			'method'   => 'POST',
			'endpoint' => $endpoint,
			'headers'  => $headers,
			'body'     => $body,
		];

		$started  = microtime( true );
		$response = wp_remote_post(
			$endpoint,
			[
				'timeout'   => (int) apply_filters( 'barkoda_http_timeout', 15, 'test_connection' ),
				'sslverify' => (bool) apply_filters( 'barkoda_sslverify', true, 'test_connection' ),
				'headers'   => $headers,
				'body'      => $body,
			]
		);
		$duration = ( microtime( true ) - $started ) * 1000.0;

		if ( is_wp_error( $response ) ) {
			$msg = $response->get_error_message();
			Logs::record_http(
				'test_connection',
				null,
				false,
				$msg,
				$req_log,
				[
					'network_error' => $msg,
					'wp_error_data' => $response->get_error_data(),
				],
				$duration
			);
			return [
				'success' => false,
				/* translators: %s: network error message */
				'mesaj'   => sprintf( __( 'Could not connect: %s', 'barkoda-shipping-for-woocommerce' ), $msg ),
			];
		}

		$code         = (int) wp_remote_retrieve_response_code( $response );
		$raw          = (string) wp_remote_retrieve_body( $response );
		$resp_headers = wp_remote_retrieve_headers( $response );
		$resp_log     = [
			'code'    => $code,
			'headers' => $this->headers_to_array( $resp_headers ),
			'body'    => $raw,
		];

		// A SOAP Fault means an auth or permission problem.
		$fault = $this->extract_tag( $raw, 'Text' );
		if ( $fault === '' ) {
			$fault = $this->extract_tag( $raw, 'faultstring' );
		}
		if ( stripos( $raw, 'Fault' ) !== false && $fault !== '' ) {
			Logs::record_http( 'test_connection', null, false, $fault, $req_log, $resp_log, $duration );
			return [
				'success'   => false,
				/* translators: %s: SOAP fault reason returned by PTT */
				'mesaj'     => sprintf( __( 'PTT rejected the request: %s', 'barkoda-shipping-for-woocommerce' ), $fault ),
				'raw'       => $raw,
				'http_code' => $code,
			];
		}

		if ( $code >= 400 ) {
			Logs::record_http( 'test_connection', null, false, 'HTTP ' . $code, $req_log, $resp_log, $duration );
			return [
				'success'   => false,
				/* translators: %d: HTTP status code */
				'mesaj'     => sprintf( __( 'The PTT service returned HTTP status %d.', 'barkoda-shipping-for-woocommerce' ), $code ),
				'raw'       => $raw,
				'http_code' => $code,
			];
		}

		// No fault: credentials are valid, the dummy barcode is simply unknown.
		$env = $this->settings->get( 'environment' ) === 'prod' ? __( 'LIVE', 'barkoda-shipping-for-woocommerce' ) : __( 'TEST', 'barkoda-shipping-for-woocommerce' );
		Logs::record_http( 'test_connection', null, true, 'OK', $req_log, $resp_log, $duration );
		return [
			'success'   => true,
			/* translators: %s: environment name, TEST or LIVE */
			'mesaj'     => sprintf( __( '✓ Connection successful (%s environment). The customer number and password are correct.', 'barkoda-shipping-for-woocommerce' ), $env ),
			'http_code' => $code,
		];
	}

	private function build_kabul_envelope( string $musteri_id, string $sifre, string $dosya_adi, array $g ) {
		$e = function ( $v ) {
			return htmlspecialchars( (string) $v, ENT_XML1 | ENT_QUOTES, 'UTF-8' );
		};

		// InputDongu2 sequence: gondericibilgi sorts between 'en' and 'iadeAAdres'.
		// Axis2 ADB enforces <xs:sequence> order strictly and fails silently otherwise.
		$pre_gon  = [
			'aAdres',
			'aIlKodu',
			'aIlceKodu',
			'agirlik',
			'aliciAdi',
			'aliciEmail',
			'aliciIlAdi',
			'aliciIlceAdi',
			'aliciSms',
			'aliciTel',
			'barkodNo',
			'boy',
			'deger_ucreti',
			'desi',
			'ekhizmet',
			'en',
		];
		$post_gon = [
			'iadeAAdres',
			'iadeAIlKodu',
			'iadeAIlceKodu',
			'iadeAliciAdi',
			'iadeAliciEmail',
			'iadeAliciIlAdi',
			'iadeAliciIlceAdi',
			'iadeAliciTel',
			'musteriReferansNo',
			'odeme_sart_ucreti',
			'odemesekli',
			'rezerve1',
			'ucret',
			'yukseklik',
		];

		$emit = static function ( array $keys ) use ( $g, $e ): string {
			$out = '';
			foreach ( $keys as $f ) {
				if ( ! isset( $g[ $f ] ) || $g[ $f ] === '' || $g[ $f ] === null ) {
					continue;
				}
				$out .= '<xsd:' . $f . '>' . $e( $g[ $f ] ) . '</xsd:' . $f . '>';
			}
			return $out;
		};

		$dongu_xml = $emit( $pre_gon ) . $this->build_gondericibilgi_xml() . $emit( $post_gon );

		return '<?xml version="1.0" encoding="UTF-8"?>'
			. '<soap:Envelope xmlns:soap="http://www.w3.org/2003/05/soap-envelope" xmlns:kab="http://kabul.ptt.gov.tr" xmlns:xsd="http://kabul.ptt.gov.tr/xsd">'
			. '<soap:Header/><soap:Body><kab:kabulEkle2><kab:input>'
			. '<xsd:dongu>' . $dongu_xml . '</xsd:dongu>'
			. '<xsd:dosyaAdi>' . $e( $dosya_adi ) . '</xsd:dosyaAdi>'
			. '<xsd:gonderiTip>NORMAL</xsd:gonderiTip>'
			. '<xsd:gonderiTur>KARGO</xsd:gonderiTur>'
			. '<xsd:kullanici>PttWs</xsd:kullanici>'
			. '<xsd:musteriId>' . $e( $musteri_id ) . '</xsd:musteriId>'
			. '<xsd:sifre>' . $e( $sifre ) . '</xsd:sifre>'
			. '</kab:input></kab:kabulEkle2></soap:Body></soap:Envelope>';
	}

	private function clean_phone( string $raw ): string {
		$digits = preg_replace( '/\D/', '', $raw );
		if ( strlen( $digits ) > 10 && strpos( $digits, '90' ) === 0 ) {
			$digits = substr( $digits, 2 );
		} elseif ( strlen( $digits ) === 11 && $digits[0] === '0' ) {
			$digits = substr( $digits, 1 );
		}
		return $digits;
	}

	private function parse_kabul_response( string $raw ) {
		if ( $raw === '' ) {
			return [
				'success' => false,
				'mesaj'   => 'PTT servisinden boş cevap.',
			];
		}

		// SOAP Fault check, namespace-agnostic.
		$fault_reason = $this->extract_tag( $raw, 'Text' );
		if ( $fault_reason === '' ) {
			$fault_reason = $this->extract_tag( $raw, 'faultstring' );
		}
		if ( stripos( $raw, 'Fault' ) !== false && $fault_reason !== '' ) {
			return [
				'success' => false,
				'mesaj'   => 'SOAP Fault: ' . $fault_reason,
				'raw'     => $raw,
			];
		}

		$hata_kodu      = $this->extract_tag( $raw, 'hataKodu' );
		$aciklama       = $this->extract_tag( $raw, 'aciklama' );
		$dongu_hata     = $this->extract_tag( $raw, 'donguHataKodu' );
		$dongu_aciklama = $this->extract_tag( $raw, 'donguAciklama' );
		$barkod         = $this->extract_tag( $raw, 'barkod' );
		$quid           = $this->extract_tag( $raw, 'Barkod_quid' );
		if ( $quid === '' ) {
			$quid = $this->extract_tag( $raw, 'barkod_quid' );
		}

		// In practice PTT returns the tracking URL inside donguAciklama, unlike the docs.
		$dongu_is_url = $dongu_aciklama !== '' && preg_match( '~^https?://~i', $dongu_aciklama );
		if ( $quid === '' && $dongu_is_url ) {
			$quid = $dongu_aciklama;
		}

		$dongu_hata_int = $dongu_hata === '' ? null : (int) $dongu_hata;
		$hata_kodu_int  = $hata_kodu === '' ? null : (int) $hata_kodu;

		$overall_success = ( $hata_kodu_int === 1 );
		$dongu_success   = ( $dongu_hata_int === null || $dongu_hata_int === 1 );

		if ( $overall_success && $dongu_success ) {
			return [
				'success'   => true,
				'barkod'    => $barkod,
				'takip_url' => $quid,
				'mesaj'     => $aciklama !== '' ? $aciklama : 'Gönderi oluşturuldu.',
				'raw'       => $raw,
			];
		}

		$detay_msg = ( ! $dongu_is_url && $dongu_aciklama !== '' ) ? $dongu_aciklama : $aciklama;
		if ( $detay_msg === '' ) {
			$detay_msg = 'hataKodu=' . ( $hata_kodu_int === null ? '?' : $hata_kodu_int )
				. ( $dongu_hata_int !== null ? ', donguHataKodu=' . $dongu_hata_int : '' )
				. ' (PTT açıklama dönmedi)';
		}

		return [
			'success'    => false,
			'mesaj'      => $detay_msg,
			'hata_kodu'  => $hata_kodu_int,
			'dongu_hata' => $dongu_hata_int,
			'raw'        => $raw,
		];
	}

	private function parse_takip_response( string $raw ) {
		if ( $raw === '' ) {
			return [
				'success' => false,
				'mesaj'   => __( 'Empty response from the PTT service.', 'barkoda-shipping-for-woocommerce' ),
				'raw'     => $raw,
			];
		}

		// Detect SOAP Faults so the error message is not swallowed.
		$fault_reason = $this->extract_tag( $raw, 'Text' );
		if ( $fault_reason === '' ) {
			$fault_reason = $this->extract_tag( $raw, 'faultstring' );
		}
		if ( stripos( $raw, 'Fault' ) !== false && $fault_reason !== '' ) {
			return [
				'success' => false,
				'mesaj'   => 'SOAP Fault: ' . $fault_reason,
				'raw'     => $raw,
			];
		}

		$aciklama = $this->extract_tag( $raw, 'sonucAciklama' );
		$barno    = $this->extract_tag( $raw, 'BARNO' );
		$dongu    = $this->extract_dongu( $raw );

		// sonucKodu is unreliable: the test environment returns 10 for "islem basarili",
		// so success is decided on the presence of BARNO or movement rows instead.
		$success = $barno !== '' || ! empty( $dongu );

		// Empty reply: the barcode is unknown to PTT or has not been scanned yet.
		if ( ! $success && $aciklama === '' ) {
			$aciklama = __( 'The barcode is not in the PTT system yet, or has not been processed.', 'barkoda-shipping-for-woocommerce' );
		}

		return [
			'success'  => $success,
			'mesaj'    => $aciklama,
			'barkod'   => $barno,
			'alici'    => $this->extract_tag( $raw, 'ALICI' ),
			'gonderen' => $this->extract_tag( $raw, 'GONDEREN' ),
			'dongu'    => $dongu,
			'raw'      => $raw,
		];
	}

	/**
	 * Returns the first value of the given tag, whatever namespace prefix it carries.
	 */
	private function extract_tag( string $xml, string $tag ): string {
		$pattern = '/<(?:[\w\-]+:)?' . preg_quote( $tag, '/' ) . '(?:\s[^>]*)?>(.*?)<\/(?:[\w\-]+:)?' . preg_quote( $tag, '/' ) . '>/s';
		if ( preg_match( $pattern, $xml, $m ) ) {
			return trim( html_entity_decode( $m[1], ENT_QUOTES | ENT_XML1, 'UTF-8' ) );
		}
		return '';
	}

	/**
	 * Parses the <dongu> movement rows of a tracking reply. Fields confirmed against the
	 * test environment: siraNo, ITARIH (DD/MM/YYYY), ISAAT (HH:MM:SS), ISLEM, IMERK.
	 */
	private function extract_dongu( string $xml ): array {
		$results = [];
		if ( preg_match_all( '/<(?:[\w\-]+:)?dongu(?:\s[^>]*)?>(.*?)<\/(?:[\w\-]+:)?dongu>/s', $xml, $matches ) ) {
			foreach ( $matches[1] as $inner ) {
				$results[] = [
					'siraNo' => $this->extract_tag( $inner, 'siraNo' ),
					'ITARIH' => $this->extract_tag( $inner, 'ITARIH' ),
					'ISAAT'  => $this->extract_tag( $inner, 'ISAAT' ),
					'ISLEM'  => $this->extract_tag( $inner, 'ISLEM' ),
					'IMERK'  => $this->extract_tag( $inner, 'IMERK' ),
				];
			}
		}
		return $results;
	}

	/**
	 * Builds the kabulEkleParcaliBarkod envelope: N packages become N dongu blocks that share
	 * the recipient data, each carrying its own barkodNo plus parca_adet and posta_ceki_no.
	 */
	private function build_kabul_parcali_envelope( string $musteri_id, string $sifre, string $dosya_adi, array $base, array $barkodlar, string $irsaliye_no ): string {
		$e = function ( $v ) {
			return htmlspecialchars( (string) $v, ENT_XML1 | ENT_QUOTES, 'UTF-8' );
		};

		// InputParcaliBarkodDongu sequence: gondericibilgi sits between 'en' and 'iadeAAdres'.
		$pre_gon  = [
			'aAdres',
			'aIlKodu',
			'aIlceKodu',
			'agirlik',
			'aliciAdi',
			'aliciEmail',
			'aliciIlAdi',
			'aliciIlceAdi',
			'aliciSms',
			'aliciTel',
			'barkodNo',
			'boy',
			'deger_ucreti',
			'desi',
			'ekhizmet',
			'en',
		];
		$post_gon = [
			'iadeAAdres',
			'iadeAIlKodu',
			'iadeAIlceKodu',
			'iadeAliciAdi',
			'iadeAliciEmail',
			'iadeAliciIlAdi',
			'iadeAliciIlceAdi',
			'iadeAliciTel',
			'irsaliye_no',
			'musteriReferansNo',
			'odeme_sart_ucreti',
			'odemesekli',
			'parca_adet',
			'posta_ceki_no',
			'rezerve1',
			'ucret',
			'urun_ad',
			'yukseklik',
		];

		$adet = count( $barkodlar );
		$pc   = (string) $this->settings->get( 'posta_ceki_no', '' );

		$dongus_xml = '';
		foreach ( $barkodlar as $bk ) {
			$row               = $base;
			$row['barkodNo']   = $bk;
			$row['parca_adet'] = $adet;
			if ( $irsaliye_no !== '' ) {
				$row['irsaliye_no'] = $irsaliye_no;
			}
			// kabulEkle2 carries this in rezerve1; parcaliBarkod has a dedicated posta_ceki_no field.
			if ( $pc !== '' ) {
				$row['posta_ceki_no'] = $pc;
			}

			$emit = static function ( array $keys ) use ( $row, $e ): string {
				$out = '';
				foreach ( $keys as $f ) {
					if ( ! isset( $row[ $f ] ) || $row[ $f ] === '' || $row[ $f ] === null ) {
						continue;
					}
					$out .= '<xsd:' . $f . '>' . $e( $row[ $f ] ) . '</xsd:' . $f . '>';
				}
				return $out;
			};

			$dongu_inner = $emit( $pre_gon ) . $this->build_gondericibilgi_xml() . $emit( $post_gon );
			$dongus_xml .= '<xsd:dongu>' . $dongu_inner . '</xsd:dongu>';
		}

		return '<?xml version="1.0" encoding="UTF-8"?>'
			. '<soap:Envelope xmlns:soap="http://www.w3.org/2003/05/soap-envelope" xmlns:kab="http://kabul.ptt.gov.tr" xmlns:xsd="http://kabul.ptt.gov.tr/xsd">'
			. '<soap:Header/><soap:Body><kab:kabulEkleParcaliBarkod><kab:input>'
			. $dongus_xml
			. '<xsd:dosyaAdi>' . $e( $dosya_adi ) . '</xsd:dosyaAdi>'
			. '<xsd:gonderiTip>NORMAL</xsd:gonderiTip>'
			. '<xsd:gonderiTur>KARGO</xsd:gonderiTur>'
			. '<xsd:kullanici>PttWs</xsd:kullanici>'
			. '<xsd:musteriId>' . $e( $musteri_id ) . '</xsd:musteriId>'
			. '<xsd:sifre>' . $e( $sifre ) . '</xsd:sifre>'
			. '</kab:input></kab:kabulEkleParcaliBarkod></soap:Body></soap:Envelope>';
	}

	/**
	 * Sender block shared by kabulEkle2, kabulEkleParcaliBarkod and siparisIstekEkle2.
	 * GondericiBilgi children must be alphabetical (Axis2 convention).
	 *
	 * The WSDL exposes both gonderici_sms (GSM notifications) and gonderici_telefonu and
	 * requires at least one, so both are filled with the same 10-digit number.
	 */
	private function build_gondericibilgi_xml(): string {
		$e     = function ( $v ) {
			return htmlspecialchars( (string) $v, ENT_XML1 | ENT_QUOTES, 'UTF-8' );
		};
		$isim  = $this->settings->gonderici_ad_soyad();
		$ad    = $isim['ad'];
		$soyad = $isim['soyad'];
		$adres = (string) $this->settings->get( 'gonderici_adres', '' );
		$il    = (string) $this->settings->get( 'gonderici_il', '' );
		$ilce  = (string) $this->settings->get( 'gonderici_ilce', '' );
		$posta = (string) $this->settings->get( 'gonderici_posta', '' );
		$tel   = $this->clean_phone( (string) $this->settings->get( 'gonderici_tel', '' ) );
		$mail  = (string) $this->settings->get( 'gonderici_email', '' );

		return '<xsd:gondericibilgi>'
			. '<xsd:gonderici_adi>' . $e( $ad ) . '</xsd:gonderici_adi>'
			. '<xsd:gonderici_adresi>' . $e( $adres ) . '</xsd:gonderici_adresi>'
			. '<xsd:gonderici_email>' . $e( $mail ) . '</xsd:gonderici_email>'
			. '<xsd:gonderici_il_ad>' . $e( $il ) . '</xsd:gonderici_il_ad>'
			. '<xsd:gonderici_ilce_ad>' . $e( $ilce ) . '</xsd:gonderici_ilce_ad>'
			. '<xsd:gonderici_posta_kodu>' . $e( $posta ) . '</xsd:gonderici_posta_kodu>'
			. ( $tel !== '' ? '<xsd:gonderici_sms>' . $e( $tel ) . '</xsd:gonderici_sms>' : '' )
			. '<xsd:gonderici_soyadi>' . $e( $soyad ) . '</xsd:gonderici_soyadi>'
			. ( $tel !== '' ? '<xsd:gonderici_telefonu>' . $e( $tel ) . '</xsd:gonderici_telefonu>' : '' )
			. '<xsd:gonderici_ulke_id>052</xsd:gonderici_ulke_id>'
			. '</xsd:gondericibilgi>';
	}

	/**
	 * Parses kabulEkleParcaliBarkod: hataKodu plus one dongu per package (donguHataKodu,
	 * donguAciklama, barkod, Barkod_quid). A single failed package fails the whole call.
	 */
	private function parse_parcali_response( string $raw ): array {
		if ( $raw === '' ) {
			return [
				'success' => false,
				'mesaj'   => __( 'Empty response from the PTT service.', 'barkoda-shipping-for-woocommerce' ),
				'raw'     => $raw,
			];
		}

		$fault = $this->extract_tag( $raw, 'Text' );
		if ( $fault === '' ) {
			$fault = $this->extract_tag( $raw, 'faultstring' );
		}
		if ( stripos( $raw, 'Fault' ) !== false && $fault !== '' ) {
			return [
				'success' => false,
				'mesaj'   => 'SOAP Fault: ' . $fault,
				'raw'     => $raw,
			];
		}

		$hata_kodu = $this->extract_tag( $raw, 'hataKodu' );
		$aciklama  = $this->extract_tag( $raw, 'aciklama' );
		$hata_int  = $hata_kodu === '' ? null : (int) $hata_kodu;

		$results = [];
		if ( preg_match_all( '/<(?:[\w\-]+:)?Dongu(?:\s[^>]*)?>(.*?)<\/(?:[\w\-]+:)?Dongu>/is', $raw, $matches ) ) {
			foreach ( $matches[1] as $inner ) {
				$results[] = [
					'donguHataKodu' => $this->extract_tag( $inner, 'donguHataKodu' ),
					'donguAciklama' => $this->extract_tag( $inner, 'donguAciklama' ),
					'barkod'        => $this->extract_tag( $inner, 'barkod' ),
					'takip_url'     => $this->extract_tag( $inner, 'Barkod_quid' ) ?: $this->extract_tag( $inner, 'barkod_quid' ),
				];
			}
		}

		$all_ok = ( $hata_int === 1 );
		foreach ( $results as $r ) {
			if ( (int) ( $r['donguHataKodu'] ?? 0 ) !== 1 ) {
				$all_ok = false;
				break;
			}
		}

		// Surface the first successful barcode and URL so single-package UX stays unchanged.
		$first_barkod = '';
		$first_url    = '';
		foreach ( $results as $r ) {
			if ( $first_barkod === '' && ! empty( $r['barkod'] ) ) {
				$first_barkod = $r['barkod'];
			}
			if ( $first_url === '' && ! empty( $r['takip_url'] ) ) {
				$cand = $r['takip_url'];
				// donguAciklama is sometimes the URL, same as in kabulEkle2.
				$first_url = preg_match( '~^https?://~i', $cand ) ? $cand : '';
			}
		}

		return [
			'success'       => $all_ok,
			'barkod'        => $first_barkod,
			'takip_url'     => $first_url,
			'mesaj'         => $aciklama !== '' ? $aciklama : ( $all_ok ? 'Çoklu paket gönderisi oluşturuldu.' : 'Bir veya daha fazla parça reddedildi.' ),
			'parca_results' => $results,
			'raw'           => $raw,
		];
	}

	/**
	 * Builds the siparisIstekEkle2 envelope against the WSDL InputIstek2 type:
	 *   - all field names are snake_case (ek_hizmetler, randevu_baslangic, ...)
	 *   - gondericibilgi is a lowercase wrapper with snake_case children
	 *   - elements are emitted alphabetically (Axis2 convention)
	 *   - fixed values: gonderiTip=NORMAL, gonderiTur=KARGO, musteriKullanici=admin,
	 *     isyeriId=0, gonderici_ulke_id=052
	 *   - xs:double fields are formatted as "0.00"
	 */
	private function build_siparis_istek_envelope( string $musteri_id, string $sifre, int $adet, array $params ): string {
		$e = function ( $v ) {
			return htmlspecialchars( (string) $v, ENT_XML1 | ENT_QUOTES, 'UTF-8' );
		};

		$double = function ( $v ) use ( $e ) {
			return $e( number_format( (float) $v, 2, '.', '' ) );
		};

		$lines   = [];
		$lines[] = '<xsd:adet>' . $e( $adet ) . '</xsd:adet>';

		// Optional xs:double fields, alphabetical; omitted when empty or zero.
		foreach ( [ 'agirlik', 'boy', 'deger_konulmus_ucret', 'desi' ] as $k ) {
			if ( isset( $params[ $k ] ) && (float) $params[ $k ] > 0 ) {
				$lines[] = '<xsd:' . $k . '>' . $double( $params[ $k ] ) . '</xsd:' . $k . '>';
			}
		}

		// dosyaAdi is optional and deliberately omitted.

		if ( ! empty( $params['ekhizmet'] ) ) {
			$lines[] = '<xsd:ek_hizmetler>' . $e( strtoupper( (string) $params['ekhizmet'] ) ) . '</xsd:ek_hizmetler>';
		}

		if ( isset( $params['en'] ) && (float) $params['en'] > 0 ) {
			$lines[] = '<xsd:en>' . $double( $params['en'] ) . '</xsd:en>';
		}

		// Kept uniform with kabulEkle2 (NORMAL/KARGO); the WSDL does not enforce these values.
		$lines[] = '<xsd:gonderiTip>NORMAL</xsd:gonderiTip>';
		$lines[] = '<xsd:gonderiTur>KARGO</xsd:gonderiTur>';
		$lines[] = $this->build_gondericibilgi_xml();
		$lines[] = '<xsd:isyeriId>0</xsd:isyeriId>';
		$lines[] = '<xsd:musteriId>' . $e( $musteri_id ) . '</xsd:musteriId>';
		$lines[] = '<xsd:musteriKullanici>admin</xsd:musteriKullanici>';

		if ( ! empty( $params['randevu_baslangic'] ) ) {
			$lines[] = '<xsd:randevu_baslangic>' . $e( $params['randevu_baslangic'] ) . '</xsd:randevu_baslangic>';
		}
		if ( ! empty( $params['randevu_bitis'] ) ) {
			$lines[] = '<xsd:randevu_bitis>' . $e( $params['randevu_bitis'] ) . '</xsd:randevu_bitis>';
		}

		$lines[] = '<xsd:sifre>' . $e( $sifre ) . '</xsd:sifre>';
		$lines[] = '<xsd:ucret>' . $double( $params['ucret'] ?? 0 ) . '</xsd:ucret>';

		// urunTipFatura and urunTur are optional and unused.

		if ( isset( $params['yukseklik'] ) && (float) $params['yukseklik'] > 0 ) {
			$lines[] = '<xsd:yukseklik>' . $double( $params['yukseklik'] ) . '</xsd:yukseklik>';
		}

		return '<?xml version="1.0" encoding="UTF-8"?>'
			. '<soap:Envelope xmlns:soap="http://www.w3.org/2003/05/soap-envelope" xmlns:kab="http://kabul.ptt.gov.tr" xmlns:xsd="http://kabul.ptt.gov.tr/xsd">'
			. '<soap:Header/><soap:Body><kab:siparisIstekEkle2><kab:input>'
			. implode( '', $lines )
			. '</kab:input></kab:siparisIstekEkle2></soap:Body></soap:Envelope>';
	}

	/**
	 * Parses siparisIstekEkle2 (WSDL OutputIstek): sonucKodu, sonucAciklama, siparisId — a
	 * different schema from the hataKodu/aciklama used by the kabulEkle2 family.
	 * sonucKodu 1 means success; a returned siparisId is accepted as a fallback signal.
	 */
	private function parse_siparis_istek_response( string $raw ): array {
		if ( $raw === '' ) {
			return [
				'success' => false,
				'mesaj'   => __( 'Empty response from the PTT service.', 'barkoda-shipping-for-woocommerce' ),
				'raw'     => $raw,
			];
		}

		$fault = $this->extract_tag( $raw, 'Text' );
		if ( $fault === '' ) {
			$fault = $this->extract_tag( $raw, 'faultstring' );
		}
		if ( stripos( $raw, 'Fault' ) !== false && $fault !== '' ) {
			return [
				'success' => false,
				'mesaj'   => 'SOAP Fault: ' . $fault,
				'raw'     => $raw,
			];
		}

		$sonuc_kodu = $this->extract_tag( $raw, 'sonucKodu' );
		$aciklama   = $this->extract_tag( $raw, 'sonucAciklama' );
		$siparis_id = $this->extract_tag( $raw, 'siparisId' );

		$kod_int = $sonuc_kodu === '' ? null : (int) $sonuc_kodu;
		$success = ( $kod_int === 1 ) || ( $siparis_id !== '' && $siparis_id !== '0' );

		return [
			'success'    => $success,
			'mesaj'      => $aciklama !== '' ? $aciklama : ( $success ? 'Sipariş alındı.' : ( 'sonucKodu=' . ( $kod_int === null ? '?' : $kod_int ) ) ),
			'siparis_id' => $siparis_id,
			'sonuc_kodu' => $kod_int,
			'raw'        => $raw,
		];
	}

	/**
	 * Builds the shared SOAP 1.1 envelope for the GonderiTakipV2 services:
	 * takip.ptt.gov.tr namespace, tak:/xsd: prefixes, <input> wrapper, lowercase tags.
	 *
	 * @param string $operation gonderiSorgu, gonderiSorgu2, gonderiSorgu_referansNo, ...
	 * @param array  $params    Tag name => value, e.g. ['barkod' => ..., 'kullanici' => ...].
	 */
	private function build_takip_envelope( string $operation, array $params ): string {
		$e = function ( $v ) {
			return htmlspecialchars( (string) $v, ENT_XML1 | ENT_QUOTES, 'UTF-8' );
		};

		$inner = '';
		foreach ( $params as $k => $v ) {
			$inner .= '<xsd:' . $k . '>' . $e( $v ) . '</xsd:' . $k . '>';
		}

		return '<?xml version="1.0" encoding="UTF-8"?>'
			. '<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:tak="http://takip.ptt.gov.tr" xmlns:xsd="http://takip.ptt.gov.tr/xsd">'
			. '<soapenv:Header/><soapenv:Body>'
			. '<tak:' . $operation . '><tak:input>' . $inner . '</tak:input></tak:' . $operation . '>'
			. '</soapenv:Body></soapenv:Envelope>';
	}

	/**
	 * SOAP 1.1 headers for the tracking endpoint; the Layer7 gateway routes on SOAPAction.
	 */
	private function takip_headers( string $operation ): array {
		return [
			'Content-Type' => 'text/xml; charset=utf-8',
			'SOAPAction'   => '"' . $operation . '"',
		];
	}

	/**
	 * Builds the synthetic 13-digit barcode used by the connection test: prefix + range start
	 * + check digit, falling back to zeros when no range has been configured yet.
	 */
	private function build_test_barkod(): string {
		$prefix      = (string) $this->settings->get( 'barkod_prefix', '' );
		$range_start = (string) $this->settings->get( 'barkod_range_start', '0000' );
		if ( ctype_digit( $prefix ) && ctype_digit( $range_start ) && strlen( $prefix . $range_start ) === 12 ) {
			$twelve = $prefix . $range_start;
			return $twelve . Barcode::check_digit( $twelve );
		}
		return '0000000000000';
	}

	private function parse_drop_point_response( string $raw ): array {
		if ( $raw === '' ) {
			return [
				'success' => false,
				'mesaj'   => __( 'Empty response from the PTT service.', 'barkoda-shipping-for-woocommerce' ),
				'raw'     => $raw,
			];
		}

		$fault = $this->extract_tag( $raw, 'Text' );
		if ( $fault === '' ) {
			$fault = $this->extract_tag( $raw, 'faultstring' );
		}
		if ( stripos( $raw, 'Fault' ) !== false && $fault !== '' ) {
			return [
				'success' => false,
				'mesaj'   => 'SOAP Fault: ' . $fault,
				'raw'     => $raw,
			];
		}

		$result_code = $this->extract_tag( $raw, 'resultCode' );
		$result_expl = $this->extract_tag( $raw, 'resultExplanation' );

		$rc = $result_code === '' ? null : (int) $result_code;
		// PTT documents 0 as success but some versions return 1; accept both.
		$success = ( $rc === 0 || $rc === 1 ) && $this->extract_tag( $raw, 'dropPointName' ) !== '';

		return [
			'success'              => $success,
			'mesaj'                => $result_expl,
			'result_code'          => $rc,
			'dropPointCode'        => $this->extract_tag( $raw, 'dropPointCode' ),
			'dropPointName'        => $this->extract_tag( $raw, 'dropPointName' ),
			'dropPointCountry'     => $this->extract_tag( $raw, 'dropPointCountry' ),
			'dropPointProvince'    => $this->extract_tag( $raw, 'dropPointProvince' ),
			'dropPointZipCode'     => $this->extract_tag( $raw, 'dropPointZipCode' ),
			'dropPointFullAddress' => $this->extract_tag( $raw, 'dropPointFullAddress' ),
			'dropPointPhoneNumber' => $this->extract_tag( $raw, 'dropPointPhoneNumber' ),
			'dropPointEmail'       => $this->extract_tag( $raw, 'dropPointEmail' ),
			'dropPointWorkHours'   => $this->extract_tag( $raw, 'dropPointWorkHours' ),
			'dropPointLatitude'    => $this->extract_tag( $raw, 'dropPointLatitude' ),
			'dropPointLongitude'   => $this->extract_tag( $raw, 'dropPointLongitude' ),
			'dropPointDeadLine'    => $this->extract_tag( $raw, 'dropPointDeadLine' ),
			'raw'                  => $raw,
		];
	}
}
