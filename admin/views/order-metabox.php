<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
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
			<span class="status-badge status-sent">✓ <?php esc_html_e( 'Gönderildi', 'ptt-kargo-for-woocommerce' ); ?></span>
		</p>
		<p class="wc-ptt-mb-barkod">
			<strong><?php esc_html_e( 'Barkod:', 'ptt-kargo-for-woocommerce' ); ?></strong><br>
			<code style="font-size:13px;"><?php echo esc_html( $barkod ); ?></code>
		</p>
		<?php if ( $mesaj !== '' ) : ?>
			<p class="wc-ptt-mb-mesaj"><small><?php echo esc_html( $mesaj ); ?></small></p>
		<?php endif; ?>
		<?php if ( $takip !== '' ) : ?>
			<p><a href="<?php echo esc_url( $takip ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'PTT takip linki', 'ptt-kargo-for-woocommerce' ); ?> ↗</a></p>
		<?php endif; ?>
		<p class="wc-ptt-mb-actions">
			<a class="button button-primary" href="<?php echo esc_url( $label->label_url( $order->get_id() ) ); ?>" target="_blank">
				<span class="dashicons dashicons-printer" style="vertical-align:middle;"></span>
				<?php esc_html_e( 'Etiket Yazdır', 'ptt-kargo-for-woocommerce' ); ?>
			</a>
			<button type="button" class="button js-ptt-takip" data-order-id="<?php echo esc_attr( $order->get_id() ); ?>">
				<?php esc_html_e( 'Takip', 'ptt-kargo-for-woocommerce' ); ?>
			</button>
		</p>
		<p class="wc-ptt-mb-cancel">
			<button type="button" class="button-link js-ptt-cancel" data-order-id="<?php echo esc_attr( $order->get_id() ); ?>" style="color:#a00;">
				<span class="dashicons dashicons-no-alt" style="vertical-align:middle;"></span>
				<?php esc_html_e( 'PTT Gönderisini İptal Et', 'ptt-kargo-for-woocommerce' ); ?>
			</button>
			<br><small style="color:#646970;">
				<?php esc_html_e( 'Sadece PTT henüz gönderiyi kabul etmediyse mümkün.', 'ptt-kargo-for-woocommerce' ); ?>
			</small>
		</p>
	<?php elseif ( $status === \PTT_Kargo_WC\Orders::STATUS_CANCELED ) : ?>
		<p class="wc-ptt-mb-status">
			<span class="status-badge status-canceled">⊘ <?php esc_html_e( 'İptal edildi', 'ptt-kargo-for-woocommerce' ); ?></span>
		</p>
		<?php if ( $mesaj !== '' ) : ?>
			<p class="wc-ptt-mb-mesaj"><small><?php echo esc_html( $mesaj ); ?></small></p>
		<?php endif; ?>
		<p><small style="color:#646970;">
			<?php esc_html_e( 'Bu sipariş PTT\'den iptal edildi. Yeniden gönderim yeni bir barkod tüketir.', 'ptt-kargo-for-woocommerce' ); ?>
		</small></p>
		<p class="wc-ptt-mb-actions">
			<button type="button" class="button button-primary js-ptt-send" data-order-id="<?php echo esc_attr( $order->get_id() ); ?>">
				<?php esc_html_e( 'Yeniden Gönder', 'ptt-kargo-for-woocommerce' ); ?>
			</button>
		</p>
		<?php
	elseif ( $status === \PTT_Kargo_WC\Orders::STATUS_ERROR ) :
		$pending = (string) $order->get_meta( \PTT_Kargo_WC\Orders::META_PENDING_BARKOD );
		?>
		<p class="wc-ptt-mb-status">
			<span class="status-badge status-error">! <?php esc_html_e( 'Hata', 'ptt-kargo-for-woocommerce' ); ?></span>
		</p>
		<p><small style="color:#a00;"><?php echo esc_html( $mesaj ?: __( 'Bilinmeyen hata', 'ptt-kargo-for-woocommerce' ) ); ?></small></p>
		<?php if ( $pending !== '' ) : ?>
			<p style="font-size:11px; color:#0c63e4;">
				🔁 
				<?php
				/* translators: %s: pending barcode */
				echo esc_html( sprintf( __( 'Tüketilmiş barkod: %s — yeniden denemede tekrar kullanılacak.', 'ptt-kargo-for-woocommerce' ), $pending ) );
				?>
			</p>
		<?php endif; ?>
		<p class="wc-ptt-mb-actions">
			<button type="button" class="button button-primary js-ptt-send" data-order-id="<?php echo esc_attr( $order->get_id() ); ?>">
				<?php esc_html_e( 'Tekrar Dene', 'ptt-kargo-for-woocommerce' ); ?>
			</button>
		</p>
	<?php else : ?>
		<p class="wc-ptt-mb-status">
			<span class="status-badge status-pending"><?php esc_html_e( 'Henüz gönderilmedi', 'ptt-kargo-for-woocommerce' ); ?></span>
		</p>
		<p class="wc-ptt-mb-actions">
			<button type="button" class="button button-primary button-large js-ptt-send" data-order-id="<?php echo esc_attr( $order->get_id() ); ?>">
				<span class="dashicons dashicons-airplane" style="vertical-align:middle;"></span>
				<?php esc_html_e( 'PTT Kargoya Gönder', 'ptt-kargo-for-woocommerce' ); ?>
			</button>
		</p>
	<?php endif; ?>
</div>
