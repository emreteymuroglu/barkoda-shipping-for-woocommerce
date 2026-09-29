<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- view partial included from a class method, so the variables below are function-scoped rather than global.
/**
 * @var \WC_Order              $order
 * @var string                 $status
 * @var string                 $barkod
 * @var string                 $takip
 * @var string                 $mesaj
 * @var \PTT_Kargo_WC\Label    $label
 * @var \PTT_Kargo_WC\Orders   $orders
 */
?>
<div class="wc-ptt-metabox" data-order-id="<?php echo esc_attr( $order->get_id() ); ?>">
	<?php if ( $status === \PTT_Kargo_WC\Orders::STATUS_SENT ) : ?>
		<p class="wc-ptt-mb-status">
			<span class="status-badge status-sent">✓ <?php esc_html_e( 'Sent', 'ptt-kargo-for-woocommerce' ); ?></span>
		</p>
		<p class="wc-ptt-mb-barkod">
			<strong><?php esc_html_e( 'Barcode:', 'ptt-kargo-for-woocommerce' ); ?></strong><br>
			<code style="font-size:13px;"><?php echo esc_html( $barkod ); ?></code>
		</p>
		<?php if ( $mesaj !== '' ) : ?>
			<p class="wc-ptt-mb-mesaj"><small><?php echo esc_html( $mesaj ); ?></small></p>
		<?php endif; ?>
		<?php if ( $takip !== '' ) : ?>
			<p><a href="<?php echo esc_url( $takip ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'PTT tracking link', 'ptt-kargo-for-woocommerce' ); ?> ↗</a></p>
		<?php endif; ?>
		<p class="wc-ptt-mb-actions">
			<a class="button button-primary" href="<?php echo esc_url( $label->label_url( $order->get_id() ) ); ?>" target="_blank">
				<span class="dashicons dashicons-printer" style="vertical-align:middle;"></span>
				<?php esc_html_e( 'Print Label', 'ptt-kargo-for-woocommerce' ); ?>
			</a>
			<button type="button" class="button js-ptt-takip" data-order-id="<?php echo esc_attr( $order->get_id() ); ?>">
				<?php esc_html_e( 'Track', 'ptt-kargo-for-woocommerce' ); ?>
			</button>
		</p>
		<p class="wc-ptt-mb-cancel">
			<button type="button" class="button-link js-ptt-cancel" data-order-id="<?php echo esc_attr( $order->get_id() ); ?>" style="color:#a00;">
				<span class="dashicons dashicons-no-alt" style="vertical-align:middle;"></span>
				<?php esc_html_e( 'Cancel PTT Shipment', 'ptt-kargo-for-woocommerce' ); ?>
			</button>
			<br><small style="color:#646970;">
				<?php esc_html_e( 'Only possible while PTT has not yet accepted the shipment.', 'ptt-kargo-for-woocommerce' ); ?>
			</small>
		</p>
	<?php elseif ( $status === \PTT_Kargo_WC\Orders::STATUS_CANCELED ) : ?>
		<p class="wc-ptt-mb-status">
			<span class="status-badge status-canceled">⊘ <?php esc_html_e( 'Cancelled', 'ptt-kargo-for-woocommerce' ); ?></span>
		</p>
		<?php if ( $mesaj !== '' ) : ?>
			<p class="wc-ptt-mb-mesaj"><small><?php echo esc_html( $mesaj ); ?></small></p>
		<?php endif; ?>
		<p><small style="color:#646970;">
			<?php esc_html_e( 'This order was cancelled at PTT. Resending will consume a new barcode.', 'ptt-kargo-for-woocommerce' ); ?>
		</small></p>
		<p class="wc-ptt-mb-actions">
			<button type="button" class="button button-primary js-ptt-send" data-order-id="<?php echo esc_attr( $order->get_id() ); ?>">
				<?php esc_html_e( 'Resend', 'ptt-kargo-for-woocommerce' ); ?>
			</button>
		</p>
		<?php
	elseif ( $status === \PTT_Kargo_WC\Orders::STATUS_ERROR ) :
		$pending = (string) $order->get_meta( \PTT_Kargo_WC\Orders::META_PENDING_BARKOD );
		?>
		<p class="wc-ptt-mb-status">
			<span class="status-badge status-error">! <?php esc_html_e( 'Error', 'ptt-kargo-for-woocommerce' ); ?></span>
		</p>
		<p><small style="color:#a00;"><?php echo esc_html( $mesaj ?: __( 'Unknown error', 'ptt-kargo-for-woocommerce' ) ); ?></small></p>
		<?php if ( $pending !== '' ) : ?>
			<p style="font-size:11px; color:#0c63e4;">
				🔁 
				<?php
				/* translators: %s: pending barcode */
				echo esc_html( sprintf( __( 'Consumed barcode: %s — it will be reused on the next attempt.', 'ptt-kargo-for-woocommerce' ), $pending ) );
				?>
			</p>
		<?php endif; ?>
		<p class="wc-ptt-mb-actions">
			<button type="button" class="button button-primary js-ptt-send" data-order-id="<?php echo esc_attr( $order->get_id() ); ?>">
				<?php esc_html_e( 'Try Again', 'ptt-kargo-for-woocommerce' ); ?>
			</button>
		</p>
	<?php else : ?>
		<p class="wc-ptt-mb-status">
			<span class="status-badge status-pending"><?php esc_html_e( 'Not sent yet', 'ptt-kargo-for-woocommerce' ); ?></span>
		</p>
		<p class="wc-ptt-mb-actions">
			<button type="button" class="button button-primary button-large js-ptt-send" data-order-id="<?php echo esc_attr( $order->get_id() ); ?>">
				<span class="dashicons dashicons-airplane" style="vertical-align:middle;"></span>
				<?php esc_html_e( 'Send to PTT Kargo', 'ptt-kargo-for-woocommerce' ); ?>
			</button>
		</p>
	<?php endif; ?>
</div>
