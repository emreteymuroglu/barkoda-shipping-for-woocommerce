<?php
namespace Barkoda_Shipping;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Order meta, PTT field mapping and eligibility filtering.
 * HPOS compatible: reads orders through wc_get_orders().
 */
final class Orders {
	public const META_BARKOD    = '_barkoda_barkod';
	public const META_REF       = '_barkoda_ref';
	public const META_STATUS    = '_barkoda_status';
	public const META_SENT_AT   = '_barkoda_sent_at';
	public const META_TAKIP_URL = '_barkoda_takip_url';
	public const META_PTT_LOG   = '_barkoda_last_response';
	public const META_PTT_RAW   = '_barkoda_last_raw';
	public const META_PTT_REQ   = '_barkoda_last_request';
	// Passed to barkodVeriSil / referansVeriSil when a shipment is cancelled.
	public const META_DOSYA_ADI = '_barkoda_dosya_adi';
	// Barcode already consumed by a failed attempt. While this meta exists a retry
	// reuses it instead of burning a new number from the range.
	public const META_PENDING_BARKOD = '_barkoda_pending_barkod';
	// JSON array of every barcode of a multi-package (parcaliBarkod) shipment.
	public const META_PARCA_BARKODLAR = '_barkoda_parca_barkodlar';
	public const META_PARCA_ADET      = '_barkoda_parca_adet';
	public const META_IRSALIYE_NO     = '_barkoda_irsaliye_no';

	public const STATUS_PENDING  = 'pending';
	public const STATUS_SENT     = 'sent';
	public const STATUS_ERROR    = 'error';
	public const STATUS_CANCELED = 'canceled';

	private Settings $settings;

	public function __construct( Settings $settings ) {
		$this->settings = $settings;
	}

	/**
	 * Lists orders that are candidates for shipping: orders in the configured statuses
	 * that contain at least one of the configured product ids.
	 *
	 * @return \WC_Order[]
	 */
	public function eligible_orders( int $limit = 100, string $show = 'pending' ): array {
		$durumlar = (array) $this->settings->get( 'order_statuses', [ 'processing' ] );
		if ( empty( $durumlar ) ) {
			$durumlar = [ 'processing' ];
		}

		$args = [
			'limit'   => $limit,
			'status'  => $durumlar,
			'orderby' => 'date',
			'order'   => 'DESC',
			'return'  => 'objects',
		];

		if ( $show === 'pending' ) {
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- wc_get_orders() offers no other way to filter on the plugin's own status meta; the result is bounded by $limit and only the plugin's admin screen runs it.
			$args['meta_query'] = [
				'relation' => 'OR',
				[
					'key'     => self::META_STATUS,
					'compare' => 'NOT EXISTS',
				],
				[
					'key'     => self::META_STATUS,
					'value'   => self::STATUS_SENT,
					'compare' => '!=',
				],
			];
		} elseif ( $show === 'sent' ) {
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- as above: the documented wc_get_orders() filter, bounded by $limit.
			$args['meta_query'] = [
				[
					'key'   => self::META_STATUS,
					'value' => self::STATUS_SENT,
				],
			];
		}

		$args = apply_filters( 'barkoda_eligible_orders_args', $args, $show, $limit );

		$orders = wc_get_orders( $args );
		if ( empty( $orders ) ) {
			return [];
		}

		$filtered = array_values( array_filter( $orders, fn( $order ) => $this->is_eligible( $order ) ) );
		return apply_filters( 'barkoda_eligible_orders', $filtered, $show, $args );
	}

	/**
	 * With a product filter set, only orders containing those products qualify.
	 * With an empty filter every order in an eligible status qualifies.
	 */
	public function is_eligible( \WC_Order $order ): bool {
		$product_ids = $this->settings->product_ids();
		$result      = empty( $product_ids ) ? true : $this->order_contains_products( $order, $product_ids );
		return (bool) apply_filters( 'barkoda_is_eligible', $result, $order, $product_ids );
	}

	public function order_contains_products( \WC_Order $order, array $product_ids ): bool {
		if ( empty( $product_ids ) ) {
			return false;
		}
		foreach ( $order->get_items() as $item ) {
			if ( ! $item instanceof \WC_Order_Item_Product ) {
				continue;
			}
			$pid    = (int) $item->get_product_id();
			$var_id = (int) $item->get_variation_id();
			if ( in_array( $pid, $product_ids, true ) || ( $var_id > 0 && in_array( $var_id, $product_ids, true ) ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Maps the order's recipient data onto PTT field names.
	 * Fields that are missing or too short are listed under 'missing' for the popup.
	 */
	public function to_ptt_payload( \WC_Order $order ): array {
		$ad = trim( $order->get_shipping_first_name() . ' ' . $order->get_shipping_last_name() );
		if ( $ad === '' ) {
			$ad = trim( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() );
		}

		$adres_1 = $order->get_shipping_address_1() ?: $order->get_billing_address_1();
		$adres_2 = $order->get_shipping_address_2() ?: $order->get_billing_address_2();
		$adres   = trim( $adres_1 . ( $adres_2 ? ' ' . $adres_2 : '' ) );

		$ilce   = $order->get_shipping_city() ?: $order->get_billing_city();
		$il_kod = $order->get_shipping_state() ?: $order->get_billing_state();
		$il     = $this->resolve_state_name( $il_kod );
		$posta  = $order->get_shipping_postcode() ?: $order->get_billing_postcode();
		$tel    = $order->get_billing_phone();
		$email  = $order->get_billing_email();

		$tel_clean = preg_replace( '/\D/', '', (string) $tel );
		if ( strlen( $tel_clean ) > 10 && strpos( $tel_clean, '90' ) === 0 ) {
			$tel_clean = substr( $tel_clean, 2 );
		} elseif ( strlen( $tel_clean ) === 11 && $tel_clean[0] === '0' ) {
			$tel_clean = substr( $tel_clean, 1 );
		}

		$agirlik    = $this->resolve_weight( $order );
		$dimensions = $this->resolve_dimensions( $order );
		$desi       = $this->resolve_desi( $order, $dimensions );

		$missing = [];
		if ( mb_strlen( $ad ) < 5 ) {
			$missing['aliciAdi'] = 'Alıcı ad soyad';
		}
		if ( mb_strlen( $adres ) < 5 ) {
			$missing['aAdres'] = 'Adres';
		}
		if ( $il === '' ) {
			$missing['aliciIlAdi'] = 'İl';
		}
		if ( $ilce === '' ) {
			$missing['aliciIlceAdi'] = 'İlçe';
		}
		if ( strlen( $tel_clean ) !== 10 ) {
			$missing['aliciSms'] = 'Telefon (10 hane)';
		}

		$fields = [
			'aliciAdi'     => $ad,
			'aAdres'       => $adres,
			'aliciIlAdi'   => $il,
			'aliciIlceAdi' => $ilce,
			'aliciSms'     => $tel_clean,
			'aliciEmail'   => $email,
			'agirlik'      => $agirlik,
			'desi'         => $desi,
			'ekhizmet'     => strtoupper( (string) $this->settings->get( 'ekhizmet', '' ) ),
		];

		if ( $dimensions['en'] > 0 ) {
			$fields['en'] = $dimensions['en'];
		}
		if ( $dimensions['boy'] > 0 ) {
			$fields['boy'] = $dimensions['boy'];
		}
		if ( $dimensions['yukseklik'] > 0 ) {
			$fields['yukseklik'] = $dimensions['yukseklik'];
		}

		// Postal cheque number goes into <xsd:rezerve1> for cash-on-delivery shipments.
		$posta_ceki = (string) $this->settings->get( 'posta_ceki_no', '' );
		if ( $posta_ceki !== '' ) {
			$fields['rezerve1'] = $posta_ceki;
		}

		$this->apply_cod_logic( $order, $fields );

		// Insurance (Degerli Kargo) is never added automatically; the popup toggles deger_ucreti + DK.

		$this->apply_iade_logic( $fields );

		$payload = [
			'fields'   => $fields,
			'missing'  => $missing,
			'order_id' => $order->get_id(),
			'order_no' => $order->get_order_number(),
			'posta'    => $posta,
		];
		return (array) apply_filters( 'barkoda_ptt_payload', $payload, $order );
	}

	/**
	 * Returns the order weight in grams, per the configured source:
	 * - static:              the varsayilan_agirlik setting
	 * - wc_product:          sum of weight x quantity over all line items, 0 when unset
	 * - wc_product_fallback: same as wc_product, falling back to static when the total is 0
	 */
	public function resolve_weight( \WC_Order $order ): int {
		$source = (string) $this->settings->get( 'weight_source', 'static' );
		$static = max( 1, (int) $this->settings->get( 'varsayilan_agirlik', 500 ) );

		if ( $source === 'static' ) {
			return (int) apply_filters( 'barkoda_resolved_weight', $static, $order, $source );
		}

		$total_g = 0;
		$wc_unit = function_exists( 'get_option' ) ? (string) get_option( 'woocommerce_weight_unit', 'kg' ) : 'kg';
		foreach ( $order->get_items() as $item ) {
			if ( ! $item instanceof \WC_Order_Item_Product ) {
				continue;
			}
			$product = $item->get_product();
			if ( ! $product ) {
				continue;
			}
			$w = (float) $product->get_weight();
			if ( $w <= 0 ) {
				continue;
			}
			$qty      = max( 1, (int) $item->get_quantity() );
			$total_g += self::weight_to_grams( $w, $wc_unit ) * $qty;
		}

		$result = (int) round( $total_g );

		if ( $result <= 0 && $source === 'wc_product_fallback' ) {
			$result = $static;
		}
		if ( $result <= 0 ) {
			// Fall back to static rather than send 0: PTT may reject a zero weight.
			$result = $static;
		}

		return (int) apply_filters( 'barkoda_resolved_weight', $result, $order, $source );
	}

	/**
	 * Returns the box dimensions in cm as ['en' =>, 'boy' =>, 'yukseklik' =>].
	 * - static:     0,0,0, so the fields are omitted from the envelope
	 * - wc_product: the max of every product per axis, assuming one package. With several
	 *               products the operator is expected to override them in the popup.
	 */
	public function resolve_dimensions( \WC_Order $order ): array {
		$source = (string) $this->settings->get( 'dimensions_source', 'static' );

		if ( $source !== 'wc_product' ) {
			return apply_filters(
				'barkoda_resolved_dimensions',
				[
					'en'        => 0,
					'boy'       => 0,
					'yukseklik' => 0,
				],
				$order,
				$source
			);
		}

		$max_l   = 0;
		$max_w   = 0;
		$max_h   = 0;
		$wc_unit = function_exists( 'get_option' ) ? (string) get_option( 'woocommerce_dimension_unit', 'cm' ) : 'cm';
		foreach ( $order->get_items() as $item ) {
			if ( ! $item instanceof \WC_Order_Item_Product ) {
				continue;
			}
			$product = $item->get_product();
			if ( ! $product ) {
				continue;
			}
			$l = self::length_to_cm( (float) $product->get_length(), $wc_unit );
			$w = self::length_to_cm( (float) $product->get_width(), $wc_unit );
			$h = self::length_to_cm( (float) $product->get_height(), $wc_unit );
			if ( $l > $max_l ) {
				$max_l = $l;
			}
			if ( $w > $max_w ) {
				$max_w = $w;
			}
			if ( $h > $max_h ) {
				$max_h = $h;
			}
		}

		// PTT expects 1-4 numeric digits for boy / en / yukseklik, so round to integers.
		$dims = [
			'en'        => (int) round( $max_w ),
			'boy'       => (int) round( $max_l ),
			'yukseklik' => (int) round( $max_h ),
		];
		return (array) apply_filters( 'barkoda_resolved_dimensions', $dims, $order, $source );
	}

	/**
	 * Volumetric weight: en x boy x yukseklik / 3000 when dimensions are known,
	 * otherwise the varsayilan_desi setting.
	 */
	public function resolve_desi( \WC_Order $order, array $dimensions ): int {
		$static = max( 1, (int) $this->settings->get( 'varsayilan_desi', 1 ) );
		$source = (string) $this->settings->get( 'dimensions_source', 'static' );

		$desi = $static;
		if ( $source === 'wc_product'
			&& $dimensions['en'] > 0 && $dimensions['boy'] > 0 && $dimensions['yukseklik'] > 0
		) {
			$calc = ( $dimensions['en'] * $dimensions['boy'] * $dimensions['yukseklik'] ) / 3000;
			$desi = max( 1, (int) ceil( $calc ) );
		}
		return (int) apply_filters( 'barkoda_resolved_desi', $desi, $order, $dimensions, $source );
	}

	/**
	 * When the order's payment method is on the cash-on-delivery list, sets the PTT fields:
	 *  - odemesekli = 'UA' (charge the recipient)
	 *  - odeme_sart_ucreti = order total, 12,2 decimal
	 *  - cod_extra_service_code (default 'OS') merged into ekhizmet
	 *
	 * The `barkoda_is_cod_order` filter can override the detection.
	 */
	private function apply_cod_logic( \WC_Order $order, array &$fields ): void {
		$cod_methods = $this->settings->cod_payment_methods();
		$is_cod      = ! empty( $cod_methods ) && in_array( (string) $order->get_payment_method(), $cod_methods, true );
		$is_cod      = (bool) apply_filters( 'barkoda_is_cod_order', $is_cod, $order, $cod_methods );

		if ( ! $is_cod ) {
			return;
		}

		// odeme_sart_ucreti allows at most 12,2 decimals; WC totals arrive as floats.
		$total = (float) $order->get_total();
		if ( $total <= 0 ) {
			// PTT rejects zero-total shipments, so skip rather than send one.
			return;
		}

		$fields['odemesekli']        = 'UA';
		$fields['odeme_sart_ucreti'] = number_format( $total, 2, '.', '' );

		$cod_eh = strtoupper( (string) $this->settings->get( 'cod_extra_service_code', 'OS' ) );
		if ( $cod_eh !== '' ) {
			$fields['ekhizmet'] = self::merge_extra_service_codes( (string) ( $fields['ekhizmet'] ?? '' ), $cod_eh );
		}
	}

	/**
	 * Merges extra service codes the way PTT expects: split into two-letter codes,
	 * de-duplicate, then sort alphabetically (the PTT sample "DKUA" is alphabetical).
	 */
	public static function merge_extra_service_codes( string $existing, string $add ): string {
		$existing = strtoupper( preg_replace( '/[^A-Za-z]/', '', $existing ) );
		$add      = strtoupper( preg_replace( '/[^A-Za-z]/', '', $add ) );
		if ( $add === '' ) {
			return $existing;
		}

		// PTT service codes are always two letters: DK, OS, UA, KO and so on.
		$codes = [];
		foreach ( str_split( $existing, 2 ) as $c ) {
			if ( strlen( $c ) === 2 ) {
				$codes[] = $c;
			}
		}
		foreach ( str_split( $add, 2 ) as $c ) {
			if ( strlen( $c ) === 2 ) {
				$codes[] = $c;
			}
		}
		$codes = array_unique( $codes );
		sort( $codes );
		return implode( '', $codes );
	}

	/**
	 * Injects the iade* return-address fields when "different return address" is enabled.
	 * Empty fields are omitted so PTT falls back to the sender address.
	 */
	private function apply_iade_logic( array &$fields ): void {
		if ( empty( $this->settings->get( 'iade_adresi_farkli', 0 ) ) ) {
			return;
		}

		$ad    = trim( (string) $this->settings->get( 'iade_ad', '' ) );
		$adres = trim( (string) $this->settings->get( 'iade_adres', '' ) );
		$il    = trim( (string) $this->settings->get( 'iade_il', '' ) );
		$ilce  = trim( (string) $this->settings->get( 'iade_ilce', '' ) );
		$tel   = trim( (string) $this->settings->get( 'iade_tel', '' ) );
		$email = trim( (string) $this->settings->get( 'iade_email', '' ) );

		if ( $ad !== '' ) {
			$fields['iadeAliciAdi'] = $ad;
		}
		if ( $adres !== '' ) {
			$fields['iadeAAdres'] = $adres;
		}
		if ( $il !== '' ) {
			$fields['iadeAliciIlAdi'] = $il;
		}
		if ( $ilce !== '' ) {
			$fields['iadeAliciIlceAdi'] = $ilce;
		}
		if ( $tel !== '' ) {
			$fields['iadeAliciTel'] = $tel;
		}
		if ( $email !== '' ) {
			$fields['iadeAliciEmail'] = $email;
		}
	}

	private static function weight_to_grams( float $value, string $unit ): float {
		switch ( strtolower( $unit ) ) {
			case 'g':
				return $value;
			case 'kg':
				return $value * 1000.0;
			case 'lbs':
				return $value * 453.59237;
			case 'oz':
				return $value * 28.349523125;
		}
		return $value; // Unknown unit: pass through untouched.
	}

	private static function length_to_cm( float $value, string $unit ): float {
		switch ( strtolower( $unit ) ) {
			case 'mm':
				return $value / 10.0;
			case 'cm':
				return $value;
			case 'm':
				return $value * 100.0;
			case 'in':
				return $value * 2.54;
			case 'yd':
				return $value * 91.44;
		}
		return $value;
	}

	private function resolve_state_name( string $state_code ): string {
		if ( $state_code === '' ) {
			return '';
		}
		if ( function_exists( 'WC' ) ) {
			$states = WC()->countries ? WC()->countries->get_states( 'TR' ) : [];
			if ( is_array( $states ) && isset( $states[ $state_code ] ) ) {
				return (string) $states[ $state_code ];
			}
		}
		return $state_code;
	}

	public function mark_sent( \WC_Order $order, string $barkod, string $ref, string $takip_url = '', string $raw = '', string $request = '', string $mesaj = '', string $dosya_adi = '' ): void {
		$order->update_meta_data( self::META_BARKOD, $barkod );
		$order->update_meta_data( self::META_REF, $ref );
		$order->update_meta_data( self::META_STATUS, self::STATUS_SENT );
		$order->update_meta_data( self::META_SENT_AT, current_time( 'mysql' ) );
		if ( $takip_url !== '' ) {
			$order->update_meta_data( self::META_TAKIP_URL, $takip_url );
		}
		if ( $raw !== '' ) {
			$order->update_meta_data( self::META_PTT_RAW, $raw );
		}
		if ( $request !== '' ) {
			$order->update_meta_data( self::META_PTT_REQ, $request );
		}
		if ( $mesaj !== '' ) {
			$order->update_meta_data( self::META_PTT_LOG, $mesaj );
		}
		if ( $dosya_adi !== '' ) {
			$order->update_meta_data( self::META_DOSYA_ADI, $dosya_adi );
		}
		// A successful shipment clears the barcode that was held back for retries.
		$order->delete_meta_data( self::META_PENDING_BARKOD );
		$order->save();

		$order->add_order_note(
			sprintf(
			/* translators: 1: barcode 2: reference number 3: PTT response message */
				__( 'PTT Kargo: shipment created. Barcode: %1$s, reference: %2$s. PTT response: %3$s', 'barkoda-shipping-for-woocommerce' ),
				$barkod,
				$ref,
				$mesaj !== '' ? $mesaj : '-'
			)
		);
	}

	/**
	 * Records the error and keeps the already-consumed barcode so a retry can reuse it.
	 * The PTT barcode range is a scarce resource: a failed attempt must not burn a new number.
	 *
	 * @param string $pending_barkod Barcode sent to PTT that did not succeed, if any.
	 */
	public function mark_error( \WC_Order $order, string $mesaj, string $raw = '', string $request = '', string $pending_barkod = '' ): void {
		$order->update_meta_data( self::META_STATUS, self::STATUS_ERROR );
		$order->update_meta_data( self::META_PTT_LOG, $mesaj );
		if ( $raw !== '' ) {
			$order->update_meta_data( self::META_PTT_RAW, $raw );
		}
		if ( $request !== '' ) {
			$order->update_meta_data( self::META_PTT_REQ, $request );
		}
		if ( $pending_barkod !== '' ) {
			$order->update_meta_data( self::META_PENDING_BARKOD, $pending_barkod );
		}
		$order->save();
		$order->add_order_note( __( 'PTT Kargo error: ', 'barkoda-shipping-for-woocommerce' ) . $mesaj );
	}

	/**
	 * Stores the extra meta of a multi-package (kabulEkleParcaliBarkod) shipment.
	 * Called right after mark_sent().
	 */
	public function mark_parca( \WC_Order $order, array $barkodlar, string $irsaliye_no = '' ): void {
		if ( count( $barkodlar ) > 1 ) {
			$order->update_meta_data( self::META_PARCA_BARKODLAR, wp_json_encode( array_values( array_unique( $barkodlar ) ) ) );
			$order->update_meta_data( self::META_PARCA_ADET, count( $barkodlar ) );
		}
		if ( $irsaliye_no !== '' ) {
			$order->update_meta_data( self::META_IRSALIYE_NO, $irsaliye_no );
		}
		$order->save();
	}

	public function get_pending_barkod( \WC_Order $order ): string {
		return (string) $order->get_meta( self::META_PENDING_BARKOD );
	}

	public function clear_pending_barkod( \WC_Order $order ): void {
		$order->delete_meta_data( self::META_PENDING_BARKOD );
		$order->save();
	}

	/**
	 * Called when a shipment is cancelled at PTT before acceptance.
	 * Clears the barcode, reference and tracking meta so a resend consumes a fresh
	 * barcode without colliding, and sets the status the UI renders as "cancelled".
	 */
	public function mark_canceled( \WC_Order $order, string $eski_barkod, string $mesaj = '', string $raw = '', string $request = '' ): void {
		$order->update_meta_data( self::META_STATUS, self::STATUS_CANCELED );

		// Clean slate for a resend: drop the barcode and reference.
		$order->delete_meta_data( self::META_BARKOD );
		$order->delete_meta_data( self::META_REF );
		$order->delete_meta_data( self::META_TAKIP_URL );
		$order->delete_meta_data( self::META_SENT_AT );
		$order->delete_meta_data( self::META_DOSYA_ADI );
		$order->delete_meta_data( self::META_PENDING_BARKOD );
		$order->delete_meta_data( self::META_PARCA_BARKODLAR );
		$order->delete_meta_data( self::META_PARCA_ADET );
		$order->delete_meta_data( self::META_IRSALIYE_NO );

		if ( $mesaj !== '' ) {
			$order->update_meta_data( self::META_PTT_LOG, $mesaj );
		}
		if ( $raw !== '' ) {
			$order->update_meta_data( self::META_PTT_RAW, $raw );
		}
		if ( $request !== '' ) {
			$order->update_meta_data( self::META_PTT_REQ, $request );
		}
		$order->save();

		$order->add_order_note(
			sprintf(
			/* translators: 1: previous barcode 2: PTT response message */
				__( 'PTT Kargo: shipment cancelled (previous barcode: %1$s). PTT response: %2$s', 'barkoda-shipping-for-woocommerce' ),
				$eski_barkod !== '' ? $eski_barkod : '-',
				$mesaj !== '' ? $mesaj : '-'
			)
		);
	}

	public function build_ref( \WC_Order $order ): string {
		$prefix = (string) $this->settings->get( 'referans_prefix', '' );
		return $prefix . $order->get_id();
	}
}
