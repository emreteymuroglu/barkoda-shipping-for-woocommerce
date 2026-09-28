<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/** @var array $orders */
/** @var string $show */
?>
<div class="wrap wc-ptt-wrap">
	<h1 class="wp-heading-inline"><?php esc_html_e( 'PTT Kargo Siparişleri', 'ptt-kargo-for-woocommerce' ); ?></h1>
	<hr class="wp-header-end">

	<ul class="subsubsub">
		<li><a href="<?php echo esc_url( admin_url( 'admin.php?page=' . \PTT_Kargo_WC\Admin_Page::MENU_SLUG . '&show=pending' ) ); ?>" class="<?php echo $show === 'pending' ? 'current' : ''; ?>"><?php esc_html_e( 'Bekleyenler', 'ptt-kargo-for-woocommerce' ); ?></a> |</li>
		<li><a href="<?php echo esc_url( admin_url( 'admin.php?page=' . \PTT_Kargo_WC\Admin_Page::MENU_SLUG . '&show=sent' ) ); ?>" class="<?php echo $show === 'sent' ? 'current' : ''; ?>"><?php esc_html_e( 'Gönderilenler', 'ptt-kargo-for-woocommerce' ); ?></a> |</li>
		<li><a href="<?php echo esc_url( admin_url( 'admin.php?page=' . \PTT_Kargo_WC\Admin_Page::MENU_SLUG . '&show=all' ) ); ?>" class="<?php echo $show === 'all' ? 'current' : ''; ?>"><?php esc_html_e( 'Tümü', 'ptt-kargo-for-woocommerce' ); ?></a></li>
	</ul>

	<p class="wc-ptt-toolbar">
		<button type="button" class="button button-secondary" id="wc-ptt-refresh" data-show="<?php echo esc_attr( $show ); ?>">
			<span class="dashicons dashicons-update"></span> <?php esc_html_e( 'Yenile', 'ptt-kargo-for-woocommerce' ); ?>
		</button>
		<?php
		// Show bulk print button if there are printable orders
		$printable_ids = [];
		foreach ( $orders as $o ) {
			if ( (string) $o->get_meta( \PTT_Kargo_WC\Orders::META_BARKOD ) !== '' ) {
				$printable_ids[] = $o->get_id();
			}
		}
		if ( ! empty( $printable_ids ) ) :
			$bulk_url = \PTT_Kargo_WC\Plugin::instance()->label()->bulk_url( $printable_ids );
			?>
			<a class="button button-secondary" href="<?php echo esc_url( $bulk_url ); ?>" target="_blank">
				<span class="dashicons dashicons-printer"></span>
				<?php
				/* translators: %d: number of labels */
				echo esc_html( sprintf( __( 'Toplu Etiket Bas (%d)', 'ptt-kargo-for-woocommerce' ), count( $printable_ids ) ) );
				?>
			</a>
		<?php endif; ?>
		<span class="wc-ptt-count"><?php echo esc_html( count( $orders ) ); ?> <?php esc_html_e( 'sipariş', 'ptt-kargo-for-woocommerce' ); ?></span>
	</p>

	<div id="wc-ptt-orders-container">
		<?php require PTT_KARGO_WC_DIR . 'admin/views/orders-table.php'; ?>
	</div>
</div>

<!-- Kargo Summary -->
<div id="wc-ptt-modal" class="wc-ptt-modal" style="display:none;">
	<div class="wc-ptt-modal-overlay"></div>
	<div class="wc-ptt-modal-box">
		<h2 class="wc-ptt-modal-title"><?php esc_html_e( 'Kargo Özeti', 'ptt-kargo-for-woocommerce' ); ?></h2>

		<div class="missing-warn" style="display:none;">
			⚠️ <?php esc_html_e( 'Bazı alanlar eksik — kırmızı kenarlıklı olanlar. Boş bırakabilir ya da doldurabilirsin; popup onayı gönderir.', 'ptt-kargo-for-woocommerce' ); ?>
		</div>

		<div class="wc-ptt-pending-info" style="display:none;"></div>

		<h3><?php esc_html_e( 'Müşteri Bilgileri', 'ptt-kargo-for-woocommerce' ); ?></h3>
		<div class="wc-ptt-customer"></div>

		<h3><?php esc_html_e( 'Kargo Detayları', 'ptt-kargo-for-woocommerce' ); ?>
			<small style="font-weight:normal; color:#646970;"><?php esc_html_e( '(boş bırakırsan auto-compute değer kullanılır)', 'ptt-kargo-for-woocommerce' ); ?></small>
		</h3>
		<div class="wc-ptt-shipping"></div>

		<div class="wc-ptt-payment-info" style="display:none;"></div>

		<h3><?php esc_html_e( 'Sigorta (Değerli Kargo)', 'ptt-kargo-for-woocommerce' ); ?></h3>
		<div class="wc-ptt-insurance"></div>

		<h3><?php esc_html_e( 'Çoklu Paket', 'ptt-kargo-for-woocommerce' ); ?>
			<small style="font-weight:normal; color:#646970;"><?php esc_html_e( '(birden fazla parça halinde gönderilecekse)', 'ptt-kargo-for-woocommerce' ); ?></small>
		</h3>
		<div class="wc-ptt-multipackage"></div>

		<div class="wc-ptt-modal-actions">
			<button type="button" class="button" data-action="cancel"><?php esc_html_e( 'İptal', 'ptt-kargo-for-woocommerce' ); ?></button>
			<button type="button" class="button button-primary" data-action="confirm"><?php esc_html_e( 'Onayla ve Gönder', 'ptt-kargo-for-woocommerce' ); ?></button>
		</div>
	</div>
</div>
