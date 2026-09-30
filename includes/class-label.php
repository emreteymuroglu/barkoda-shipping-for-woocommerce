<?php
namespace Barkoda_Shipping;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * 80mm thermal label output and live preview.
 *
 *  - render()        : label for a real order, opens window.print()
 *  - render_preview(): sample label built from POSTed settings, without auto-print
 */
final class Label {
	private const STYLE_HANDLE  = 'barkoda-shipping-for-woocommerce-label';
	private const SCRIPT_HANDLE = 'barkoda-shipping-for-woocommerce-label-print';

	private Settings $settings;

	public function __construct( Settings $settings ) {
		$this->settings = $settings;
	}

	public function register(): void {
		add_action( 'admin_post_barkoda_label', [ $this, 'render' ] );
		add_action( 'admin_post_barkoda_preview', [ $this, 'render_preview' ] );
		add_action( 'admin_post_barkoda_bulk_label', [ $this, 'render_bulk' ] );
	}

	/**
	 * Registers the label document's stylesheet and print script.
	 *
	 * Labels are served from admin-post.php as standalone documents, so
	 * `admin_enqueue_scripts` never runs for them. Registering the handles here and
	 * printing them in the document head keeps CSS and JS out of the markup while
	 * still going through the enqueue API.
	 */
	private static function register_assets(): void {
		wp_register_style( self::STYLE_HANDLE, BARKODA_URL . 'admin/assets/label.css', [], BARKODA_VERSION );
		wp_register_script( self::SCRIPT_HANDLE, BARKODA_URL . 'admin/assets/label-print.js', [], BARKODA_VERSION, true );
	}

	/**
	 * Bulk labels: one HTML document for several orders with a page break between each.
	 * URL: admin-post.php?action=barkoda_bulk_label&orders=123,456,789&_wpnonce=...
	 */
	public function render_bulk(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'Unauthorised.', 'barkoda-shipping-for-woocommerce' ), 403 );
		}
		check_admin_referer( 'barkoda_bulk_label' );

		$orders_raw = isset( $_GET['orders'] ) ? sanitize_text_field( wp_unslash( $_GET['orders'] ) ) : '';
		$ids        = array_filter( array_map( 'intval', explode( ',', $orders_raw ) ) );
		if ( empty( $ids ) ) {
			wp_die( esc_html__( 'No orders were selected for label printing.', 'barkoda-shipping-for-woocommerce' ), 400 );
		}
		$ids = array_slice( $ids, 0, 200 ); // Defensive cap: 200 labels per page is plenty.

		$opts    = $this->settings->all();
		$blocks  = [];
		$skipped = [];

		foreach ( $ids as $oid ) {
			$order = wc_get_order( $oid );
			if ( ! $order ) {
				$skipped[] = $oid;
				continue; }
			$barkod = (string) $order->get_meta( Orders::META_BARKOD );
			if ( $barkod === '' ) {
				$skipped[] = $oid;
				continue; }

			$data     = $this->order_to_data( $order );
			$blocks[] = [
				'data'   => $data,
				'barkod' => $barkod,
				'order'  => $order,
			];

			$parca_raw = (string) $order->get_meta( Orders::META_PARCA_BARKODLAR );
			if ( $parca_raw !== '' ) {
				$parca = json_decode( $parca_raw, true );
				if ( is_array( $parca ) ) {
					foreach ( $parca as $pb ) {
						if ( $pb !== '' && $pb !== $barkod ) {
							$blocks[] = [
								'data'   => $data,
								'barkod' => (string) $pb,
								'order'  => $order,
							];
						}
					}
				}
			}
		}

		if ( empty( $blocks ) ) {
			wp_die( esc_html__( 'None of the selected orders has a printable barcode.', 'barkoda-shipping-for-woocommerce' ), 400 );
		}

		header( 'Content-Type: text/html; charset=UTF-8' );
		echo $this->build_bulk_html( $blocks, $opts ); // phpcs:ignore WordPress.Security.EscapeOutput
		exit;
	}

	private function build_bulk_html( array $blocks, array $opts ): string {
		self::register_assets();

		// build_html() returns a whole document, so render one per label and keep only the
		// body contents, separated by page breaks.
		$count = count( $blocks );

		$inner_blocks = [];
		foreach ( $blocks as $i => $b ) {
			$is_last = ( $i === $count - 1 );
			// Pull <body>...</body> out of the full document build_html() returns.
			$full = $this->build_html( $b['data'], $opts, $b['barkod'], false, $b['order'] );
			if ( preg_match( '~<body[^>]*>(.*?)</body>~is', $full, $m ) ) {
				$body           = $m[1];
				$inner_blocks[] = '<section class="ptt-label">' . $body . '</section>'
					. ( $is_last ? '' : '<div class="ptt-page-break"></div>' );
			}
		}

		ob_start();
		?><!DOCTYPE html>
<html lang="<?php echo esc_attr( get_bloginfo( 'language' ) ); ?>">
<head>
	<meta charset="UTF-8">
	<title><?php /* translators: %d: number of labels in the document */ echo esc_html( sprintf( __( 'PTT Bulk Labels (%d)', 'barkoda-shipping-for-woocommerce' ), $count ) ); ?></title>
	<?php wp_print_styles( self::STYLE_HANDLE ); ?>
</head>
<body class="ptt-label-bulk">
		<?php echo implode( "\n", $inner_blocks ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
	<?php wp_print_scripts( self::SCRIPT_HANDLE ); ?>
</body>
</html>
		<?php
		return (string) ob_get_clean();
	}

	public function bulk_url( array $order_ids ): string {
		return add_query_arg(
			[
				'action'   => 'barkoda_bulk_label',
				'orders'   => implode( ',', array_map( 'intval', $order_ids ) ),
				'_wpnonce' => wp_create_nonce( 'barkoda_bulk_label' ),
			],
			admin_url( 'admin-post.php' )
		);
	}

	public function render(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'Unauthorised.', 'barkoda-shipping-for-woocommerce' ), 403 );
		}
		check_admin_referer( 'barkoda_label' );

		$order_id = isset( $_GET['order'] ) ? (int) $_GET['order'] : 0;
		$order    = $order_id > 0 ? wc_get_order( $order_id ) : null;
		if ( ! $order ) {
			wp_die( esc_html__( 'Order not found.', 'barkoda-shipping-for-woocommerce' ), 404 );
		}

		$barkod = (string) $order->get_meta( Orders::META_BARKOD );
		if ( $barkod === '' ) {
			wp_die( esc_html__( 'No PTT barcode has been created for this order yet.', 'barkoda-shipping-for-woocommerce' ), 400 );
		}

		$data = $this->order_to_data( $order );
		$opts = $this->settings->all();

		header( 'Content-Type: text/html; charset=UTF-8' );
		echo $this->build_html( $data, $opts, $barkod, true, $order ); // phpcs:ignore WordPress.Security.EscapeOutput
		exit;
	}

	/**
	 * Live preview: renders a sample order using the settings POSTed from the form.
	 */
	public function render_preview(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'Unauthorised.', 'barkoda-shipping-for-woocommerce' ), 403 );
		}
		check_admin_referer( 'barkoda_preview' );

		$base = $this->settings->all();

		// phpcs:disable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- every field is sanitised by sanitize_preview_input() below.
		$override = isset( $_POST['preview'] ) && is_array( $_POST['preview'] )
			? $this->sanitize_preview_input( wp_unslash( $_POST['preview'] ) )
			: [];
		// phpcs:enable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

		$opts = array_merge( $base, $override );

		$data   = $this->sample_data();
		$barkod = $this->sample_barkod( $opts );

		header( 'Content-Type: text/html; charset=UTF-8' );
		echo $this->build_html( $data, $opts, $barkod, false, null ); // phpcs:ignore WordPress.Security.EscapeOutput
		exit;
	}

	private function sanitize_preview_input( array $input ): array {
		$out       = [];
		$text_keys = [
			'gonderici_ad',
			'gonderici_adres',
			'gonderici_il',
			'gonderici_ilce',
			'gonderici_posta',
			'gonderici_tel',
			'label_header_title',
			'label_header_subtitle',
		];
		foreach ( $text_keys as $k ) {
			if ( isset( $input[ $k ] ) ) {
				$out[ $k ] = sanitize_text_field( (string) $input[ $k ] );
			}
		}
		if ( isset( $input['label_logo_url'] ) ) {
			$out['label_logo_url'] = esc_url_raw( (string) $input['label_logo_url'] );
		}
		foreach ( [ 'label_show_order', 'label_show_recipient', 'label_show_products', 'label_show_barcode', 'label_show_sender' ] as $tk ) {
			if ( array_key_exists( $tk, $input ) ) {
				$out[ $tk ] = ! empty( $input[ $tk ] ) ? 1 : 0;
			}
		}
		if ( isset( $input['barkod_prefix'] ) ) {
			$out['barkod_prefix'] = preg_replace( '/\D/', '', (string) $input['barkod_prefix'] );
		}
		return $out;
	}

	private function order_to_data( \WC_Order $order ): array {
		$ad = trim( $order->get_shipping_first_name() . ' ' . $order->get_shipping_last_name() );
		if ( $ad === '' ) {
			$ad = trim( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() );
		}

		$adres_1 = $order->get_shipping_address_1() ?: $order->get_billing_address_1();
		$adres_2 = $order->get_shipping_address_2() ?: $order->get_billing_address_2();
		$adres   = trim( $adres_1 . ( $adres_2 ? ' ' . $adres_2 : '' ) );

		$ilce   = $order->get_shipping_city() ?: $order->get_billing_city();
		$il_kod = $order->get_shipping_state() ?: $order->get_billing_state();
		$il     = $il_kod;
		if ( function_exists( 'WC' ) && WC()->countries ) {
			$states = WC()->countries->get_states( 'TR' );
			if ( isset( $states[ $il_kod ] ) ) {
				$il = $states[ $il_kod ];
			}
		}

		$urunler = [];
		foreach ( $order->get_items() as $item ) {
			$urunler[] = $item->get_name();
		}

		return [
			'siparis_no'    => $order->get_order_number(),
			'siparis_tarih' => $order->get_date_created() ? $order->get_date_created()->date_i18n( 'd.m.Y H:i' ) : '',
			'ad'            => $ad,
			'adres'         => $adres,
			'ilce'          => $ilce,
			'il'            => $il,
			'posta'         => $order->get_shipping_postcode() ?: $order->get_billing_postcode(),
			'tel'           => $order->get_billing_phone(),
			'urunler'       => $urunler,
		];
	}

	private function sample_data(): array {
		return [
			'siparis_no'    => '1234',
			'siparis_tarih' => date_i18n( 'd.m.Y H:i' ),
			'ad'            => __( 'Sample Customer', 'barkoda-shipping-for-woocommerce' ),
			'adres'         => __( 'Sample Neighbourhood, Example Street No:1 Apt:2', 'barkoda-shipping-for-woocommerce' ),
			'ilce'          => __( 'Kadıköy', 'barkoda-shipping-for-woocommerce' ),
			'il'            => __( 'İstanbul', 'barkoda-shipping-for-woocommerce' ),
			'posta'         => '34710',
			'tel'           => '0555 123 45 67',
			'urunler'       => [ __( 'Sample Product 1', 'barkoda-shipping-for-woocommerce' ), __( 'Sample Product 2', 'barkoda-shipping-for-woocommerce' ) ],
		];
	}

	private function sample_barkod( array $opts ): string {
		$prefix = (string) ( $opts['barkod_prefix'] ?? '' );
		$prefix = preg_replace( '/\D/', '', $prefix );
		if ( strlen( $prefix ) >= 8 ) {
			$prefix = substr( $prefix, 0, 8 );
		} else {
			$prefix = str_pad( $prefix, 8, '0', STR_PAD_LEFT );
		}
		$twelve = $prefix . '0001';
		return $twelve . Barcode::check_digit( $twelve );
	}

	/**
	 * @param array          $data       Order data (name, address, items, ...).
	 * @param array          $opts       Settings array.
	 * @param string         $barkod     13-digit barcode.
	 * @param bool           $auto_print Call window.print() on load?
	 * @param \WC_Order|null $order      Passed to the filters; null in preview mode.
	 */
	private function build_html( array $data, array $opts, string $barkod, bool $auto_print, $order ): string {
		self::register_assets();

		$gonderici_ad    = (string) ( $opts['gonderici_ad'] ?? '' );
		$gonderici_adres = (string) ( $opts['gonderici_adres'] ?? '' );
		$gonderici_il    = (string) ( $opts['gonderici_il'] ?? '' );
		$gonderici_ilce  = (string) ( $opts['gonderici_ilce'] ?? '' );
		$gonderici_tel   = (string) ( $opts['gonderici_tel'] ?? '' );

		$logo_url     = (string) ( $opts['label_logo_url'] ?? '' );
		$header_title = (string) ( $opts['label_header_title'] ?? '' );
		if ( $header_title === '' ) {
			$header_title = $gonderici_ad !== '' ? $gonderici_ad : (string) get_bloginfo( 'name' );
		}
		$header_sub = (string) ( $opts['label_header_subtitle'] ?? '' );

		$header_data = (array) apply_filters(
			'barkoda_label_header',
			[
				'logo_url' => $logo_url,
				'title'    => $header_title,
				'subtitle' => $header_sub,
			],
			$order
		);

		$urunler = (array) apply_filters( 'barkoda_label_products', $data['urunler'], $order );

		$show = [
			'order'     => ! empty( $opts['label_show_order'] ),
			'recipient' => ! empty( $opts['label_show_recipient'] ),
			'products'  => ! empty( $opts['label_show_products'] ),
			'barcode'   => ! empty( $opts['label_show_barcode'] ),
			'sender'    => ! empty( $opts['label_show_sender'] ),
		];

		$barkod_svg = Barcode::svg( $barkod, 90, 2 );

		ob_start();
		?>
		<!DOCTYPE html>
<html lang="<?php echo esc_attr( get_bloginfo( 'language' ) ); ?>">
<head>
	<meta charset="UTF-8">
	<title>PTT Etiketi #<?php echo esc_html( $data['siparis_no'] ); ?></title>
	<?php wp_print_styles( self::STYLE_HANDLE ); ?>
</head>
<body class="ptt-label-single">
		<?php if ( ! empty( $header_data['logo_url'] ) || ! empty( $header_data['title'] ) || ! empty( $header_data['subtitle'] ) ) : ?>
	<div class="header">
			<?php if ( ! empty( $header_data['logo_url'] ) ) : ?>
			<img class="logo" src="<?php echo esc_url( $header_data['logo_url'] ); ?>" alt="">
		<?php endif; ?>
			<?php if ( ! empty( $header_data['title'] ) ) : ?>
			<h1><?php echo esc_html( mb_strtoupper( $header_data['title'], 'UTF-8' ) ); ?></h1>
		<?php endif; ?>
			<?php if ( ! empty( $header_data['subtitle'] ) ) : ?>
			<p class="sub"><?php echo esc_html( $header_data['subtitle'] ); ?></p>
		<?php endif; ?>
	</div>
	<?php endif; ?>

		<?php if ( $show['order'] ) : ?>
	<div class="siparis-satir">
		<strong>#<?php echo esc_html( $data['siparis_no'] ); ?></strong>
		<span><?php echo esc_html( $data['siparis_tarih'] ); ?></span>
	</div>
	<div class="row-sep"></div>
	<?php endif; ?>

		<?php if ( $show['recipient'] ) : ?>
	<p class="bolum-basligi"><?php esc_html_e( 'Recipient', 'barkoda-shipping-for-woocommerce' ); ?></p>
	<p class="alici-adi"><?php echo esc_html( mb_strtoupper( (string) $data['ad'], 'UTF-8' ) ); ?></p>
	<p class="alici-adres"><?php echo esc_html( $data['adres'] ); ?></p>
	<p class="alici-il"><?php echo esc_html( $data['ilce'] ); ?> / <?php echo esc_html( $data['il'] ); ?> <?php echo esc_html( $data['posta'] ); ?></p>
			<?php if ( ! empty( $data['tel'] ) ) : ?>
	<p class="alici-tel">Tel: <?php echo esc_html( $data['tel'] ); ?></p>
	<?php endif; ?>
	<div class="row-sep"></div>
	<?php endif; ?>

		<?php if ( $show['products'] && ! empty( $urunler ) ) : ?>
	<p class="bolum-basligi"><?php esc_html_e( 'Products', 'barkoda-shipping-for-woocommerce' ); ?></p>
	<ul class="urun-listesi">
			<?php foreach ( $urunler as $u ) : ?>
			<li>- <?php echo esc_html( $u ); ?></li>
		<?php endforeach; ?>
	</ul>
	<div class="row-sep"></div>
	<?php endif; ?>

		<?php if ( $show['barcode'] ) : ?>
	<p class="bolum-basligi"><?php esc_html_e( 'Shipment Barcode', 'barkoda-shipping-for-woocommerce' ); ?></p>
	<div class="barkod-bolum">
		<div class="barkod-svg"><?php echo $barkod_svg; // phpcs:ignore WordPress.Security.EscapeOutput ?></div>
		<p class="barkod-no"><?php echo esc_html( $barkod ); ?></p>
	</div>
	<div class="row-sep"></div>
	<?php endif; ?>

		<?php if ( $show['sender'] ) : ?>
	<div class="gonderici">
			<?php echo esc_html( $gonderici_ad ); ?><br>
			<?php echo esc_html( $gonderici_adres ); ?><br>
			<?php echo esc_html( $gonderici_ilce . ' / ' . $gonderici_il ); ?><br>
			<?php
			if ( $gonderici_tel ) :
				?>
				Tel: <?php echo esc_html( $gonderici_tel ); ?><?php endif; ?>
	</div>
	<?php endif; ?>

	<div class="dotted"></div>

		<?php if ( $auto_print ) : ?>
	<?php wp_print_scripts( self::SCRIPT_HANDLE ); ?>
	<?php endif; ?>
</body>
</html>
		<?php
		$html = (string) ob_get_clean();
		return (string) apply_filters( 'barkoda_label_html', $html, $order, $barkod );
	}

	public function label_url( int $order_id ): string {
		// wp_nonce_url() HTML-escapes the separator (& becomes &amp;), which drops _wpnonce
		// once the URL travels through JSON into window.open(). add_query_arg keeps it raw.
		return add_query_arg(
			[
				'action'   => 'barkoda_label',
				'order'    => $order_id,
				'_wpnonce' => wp_create_nonce( 'barkoda_label' ),
			],
			admin_url( 'admin-post.php' )
		);
	}

	public function preview_url(): string {
		return admin_url( 'admin-post.php?action=barkoda_preview' );
	}
}
