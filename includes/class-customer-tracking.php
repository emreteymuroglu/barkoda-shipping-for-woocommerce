<?php
/**
 * Customer-facing shipment tracking.
 *
 * @package PTT_Kargo_WC
 */

namespace PTT_Kargo_WC;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Shows the barcode and tracking link to the customer, in WooCommerce order emails
 * and on the My Account order page.
 *
 * Output goes through wp_mail() like everything else WooCommerce sends, so any SMTP
 * plugin the store has installed delivers it without extra integration.
 */
final class Customer_Tracking {

	/** @var Settings */
	private $settings;

	/** @var Orders */
	private $orders;

	public function __construct( Settings $settings, Orders $orders ) {
		$this->settings = $settings;
		$this->orders   = $orders;
	}

	public function register(): void {
		add_action( 'woocommerce_email_order_meta', array( $this, 'render_email' ), 20, 4 );
		add_action( 'woocommerce_order_details_after_order_table', array( $this, 'render_account' ), 20 );
	}

	/**
	 * Appends tracking details to a WooCommerce order email.
	 *
	 * @param \WC_Order $order         The order being emailed.
	 * @param bool      $sent_to_admin Whether this is an admin notification.
	 * @param bool      $plain_text    Whether the email is plain text.
	 * @param \WC_Email $email         The email object.
	 */
	public function render_email( $order, $sent_to_admin = false, $plain_text = false, $email = null ): void {
		if ( $sent_to_admin || ! $order instanceof \WC_Order ) {
			return;
		}
		if ( empty( $this->settings->get( 'customer_tracking', 1 ) ) ) {
			return;
		}

		$allowed = (array) $this->settings->get( 'customer_tracking_emails', array() );
		$email_id = ( $email && isset( $email->id ) ) ? (string) $email->id : '';
		if ( '' === $email_id || ! in_array( $email_id, $allowed, true ) ) {
			return;
		}

		$data = $this->tracking_data( $order );
		if ( null === $data ) {
			return;
		}

		if ( $plain_text ) {
			echo "\n" . esc_html( strtoupper( $data['heading'] ) ) . "\n";
			echo esc_html( $data['barcode_label'] . ' ' . $data['barcode'] ) . "\n";
			if ( '' !== $data['url'] ) {
				echo esc_url_raw( $data['url'] ) . "\n";
			}
			echo "\n";
			return;
		}

		$this->render_html_block( $data, true );
	}

	/**
	 * Appends tracking details to the My Account order detail page.
	 *
	 * @param \WC_Order $order The order being viewed.
	 */
	public function render_account( $order ): void {
		if ( ! $order instanceof \WC_Order ) {
			return;
		}
		if ( empty( $this->settings->get( 'customer_tracking', 1 ) ) ) {
			return;
		}
		if ( empty( $this->settings->get( 'customer_tracking_account', 1 ) ) ) {
			return;
		}

		$data = $this->tracking_data( $order );
		if ( null === $data ) {
			return;
		}

		$this->render_html_block( $data, false );
	}

	/**
	 * Collects what the customer should see, or null when there is nothing to show.
	 *
	 * Only shipments that reached PTT qualify: an order that failed, was cancelled or
	 * was never sent has no barcode the customer could usefully track.
	 *
	 * @return array{heading:string,barcode_label:string,barcode:string,url:string,packages:array}|null
	 */
	private function tracking_data( \WC_Order $order ): ?array {
		$barcode = (string) $order->get_meta( Orders::META_BARKOD );
		$status  = (string) $order->get_meta( Orders::META_STATUS );

		if ( '' === $barcode || Orders::STATUS_SENT !== $status ) {
			return null;
		}

		$url = (string) $order->get_meta( Orders::META_TAKIP_URL );
		if ( '' === $url || ! preg_match( '~^https?://~i', $url ) ) {
			$url = 'https://gonderitakip.ptt.gov.tr/Track/Verify?q=' . rawurlencode( $barcode );
		}

		$packages = array();
		$raw      = (string) $order->get_meta( Orders::META_PARCA_BARKODLAR );
		if ( '' !== $raw ) {
			$decoded = json_decode( $raw, true );
			if ( is_array( $decoded ) && count( $decoded ) > 1 ) {
				$packages = array_map( 'strval', $decoded );
			}
		}

		$data = array(
			'heading'       => __( 'Shipment Tracking', 'ptt-kargo-for-woocommerce' ),
			'barcode_label' => __( 'Tracking number:', 'ptt-kargo-for-woocommerce' ),
			'barcode'       => $barcode,
			'url'           => $url,
			'packages'      => $packages,
		);

		/**
		 * Filters what the customer sees, or returns null to hide the block entirely.
		 *
		 * @param array|null $data  Tracking details.
		 * @param \WC_Order  $order The order.
		 */
		$filtered = apply_filters( 'ptt_kargo_wc_customer_tracking', $data, $order );

		return is_array( $filtered ) ? $filtered : null;
	}

	/**
	 * Renders the HTML block. Inline styles are used because WooCommerce emails are
	 * inlined and have no access to the theme's stylesheet.
	 *
	 * @param array $data    Tracking details.
	 * @param bool  $inEmail Whether this is being rendered into an email.
	 */
	private function render_html_block( array $data, bool $inEmail ): void {
		$wrap = $inEmail
			? 'margin:16px 0;padding:12px 16px;border:1px solid #e0e0e0;border-radius:4px;background:#fafafa;'
			: 'margin:24px 0;padding:14px 18px;border:1px solid #e0e0e0;border-radius:4px;background:#fafafa;';

		echo '<div style="' . esc_attr( $wrap ) . '">';
		echo '<h3 style="margin:0 0 8px;font-size:15px;">' . esc_html( $data['heading'] ) . '</h3>';
		echo '<p style="margin:0 0 6px;">' . esc_html( $data['barcode_label'] ) . ' <strong>' . esc_html( $data['barcode'] ) . '</strong></p>';

		if ( ! empty( $data['packages'] ) ) {
			echo '<p style="margin:0 0 6px;font-size:13px;">'
				. esc_html(
					sprintf(
						/* translators: %d: number of packages in the shipment */
						__( 'This order ships as %d packages:', 'ptt-kargo-for-woocommerce' ),
						count( $data['packages'] )
					)
				)
				. ' ' . esc_html( implode( ', ', $data['packages'] ) ) . '</p>';
		}

		if ( '' !== $data['url'] ) {
			echo '<p style="margin:0;"><a href="' . esc_url( $data['url'] ) . '" target="_blank" rel="noopener">'
				. esc_html__( 'Track your shipment', 'ptt-kargo-for-woocommerce' ) . '</a></p>';
		}

		echo '</div>';
	}
}
