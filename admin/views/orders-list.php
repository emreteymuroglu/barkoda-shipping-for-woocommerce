<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- view partial included from a class method, so the variables below are function-scoped rather than global.
/** @var array $orders */
/** @var string $show */
?>
<div class="wrap wc-ptt-wrap">
	<h1 class="wp-heading-inline"><?php esc_html_e( 'PTT Kargo Orders', 'ptt-kargo-for-woocommerce' ); ?></h1>
	<hr class="wp-header-end">

	<ul class="subsubsub">
		<li><a href="<?php echo esc_url( admin_url( 'admin.php?page=' . \PTT_Kargo_WC\Admin_Page::MENU_SLUG . '&show=pending' ) ); ?>" class="<?php echo $show === 'pending' ? 'current' : ''; ?>"><?php esc_html_e( 'Pending', 'ptt-kargo-for-woocommerce' ); ?></a> |</li>
		<li><a href="<?php echo esc_url( admin_url( 'admin.php?page=' . \PTT_Kargo_WC\Admin_Page::MENU_SLUG . '&show=sent' ) ); ?>" class="<?php echo $show === 'sent' ? 'current' : ''; ?>"><?php esc_html_e( 'Sent', 'ptt-kargo-for-woocommerce' ); ?></a> |</li>
		<li><a href="<?php echo esc_url( admin_url( 'admin.php?page=' . \PTT_Kargo_WC\Admin_Page::MENU_SLUG . '&show=all' ) ); ?>" class="<?php echo $show === 'all' ? 'current' : ''; ?>"><?php esc_html_e( 'All', 'ptt-kargo-for-woocommerce' ); ?></a></li>
	</ul>

	<p class="wc-ptt-toolbar">
		<button type="button" class="button button-secondary" id="wc-ptt-refresh" data-show="<?php echo esc_attr( $show ); ?>">
			<span class="dashicons dashicons-update"></span> <?php esc_html_e( 'Refresh', 'ptt-kargo-for-woocommerce' ); ?>
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
				echo esc_html( sprintf( __( 'Print Labels in Bulk (%d)', 'ptt-kargo-for-woocommerce' ), count( $printable_ids ) ) );
				?>
			</a>
		<?php endif; ?>
		<span class="wc-ptt-count"><?php echo esc_html( count( $orders ) ); ?> <?php esc_html_e( 'order', 'ptt-kargo-for-woocommerce' ); ?></span>
	</p>

	<div id="wc-ptt-orders-container">
		<?php require PTT_KARGO_WC_DIR . 'admin/views/orders-table.php'; ?>
	</div>
</div>

<!-- Kargo Summary -->
<div id="wc-ptt-modal" class="wc-ptt-modal" style="display:none;">
	<div class="wc-ptt-modal-overlay"></div>
	<div class="wc-ptt-modal-box">
		<h2 class="wc-ptt-modal-title"><?php esc_html_e( 'Shipment Summary', 'ptt-kargo-for-woocommerce' ); ?></h2>

		<div class="missing-warn" style="display:none;">
			⚠️ <?php esc_html_e( 'Some fields are missing — those outlined in red. You can leave them empty or fill them in; confirming sends the shipment.', 'ptt-kargo-for-woocommerce' ); ?>
		</div>

		<div class="wc-ptt-pending-info" style="display:none;"></div>

		<h3><?php esc_html_e( 'Customer Details', 'ptt-kargo-for-woocommerce' ); ?></h3>
		<div class="wc-ptt-customer"></div>

		<h3><?php esc_html_e( 'Shipment Details', 'ptt-kargo-for-woocommerce' ); ?>
			<small style="font-weight:normal; color:#646970;"><?php esc_html_e( '(leave empty to use the auto-computed value)', 'ptt-kargo-for-woocommerce' ); ?></small>
		</h3>
		<div class="wc-ptt-shipping"></div>

		<div class="wc-ptt-payment-info" style="display:none;"></div>

		<h3><?php esc_html_e( 'Insurance (Valuable Goods)', 'ptt-kargo-for-woocommerce' ); ?></h3>
		<div class="wc-ptt-insurance"></div>

		<h3><?php esc_html_e( 'Multiple Packages', 'ptt-kargo-for-woocommerce' ); ?>
			<small style="font-weight:normal; color:#646970;"><?php esc_html_e( '(if the order ships as more than one package)', 'ptt-kargo-for-woocommerce' ); ?></small>
		</h3>
		<div class="wc-ptt-multipackage"></div>

		<div class="wc-ptt-modal-actions">
			<button type="button" class="button" data-action="cancel"><?php esc_html_e( 'Cancel', 'ptt-kargo-for-woocommerce' ); ?></button>
			<button type="button" class="button button-primary" data-action="confirm"><?php esc_html_e( 'Confirm and Send', 'ptt-kargo-for-woocommerce' ); ?></button>
		</div>
	</div>
</div>
