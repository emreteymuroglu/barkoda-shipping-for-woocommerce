<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- view partial included from a class method, so the variables below are function-scoped rather than global.

// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only list filters, nothing is written.
$current_filter = isset( $_GET['filter'] ) ? sanitize_key( wp_unslash( $_GET['filter'] ) ) : '';
$operation      = isset( $_GET['op'] ) ? sanitize_key( wp_unslash( $_GET['op'] ) ) : '';
// phpcs:enable WordPress.Security.NonceVerification.Recommended

$only_success = 'success' === $current_filter ? true : ( 'error' === $current_filter ? false : null );

$logs  = \Barkoda_Shipping\Logs::get_recent( 200, $only_success, $operation );
$total = \Barkoda_Shipping\Logs::count();
$nonce = wp_create_nonce( 'barkoda' );
?>
<div class="wrap wc-ptt-wrap wc-ptt-logs-wrap">
	<h1 class="wp-heading-inline"><?php esc_html_e( 'Integration Log', 'barkoda-shipping-for-woocommerce' ); ?></h1>
	<button type="button" class="page-title-action" id="wc-ptt-clear-logs" data-nonce="<?php echo esc_attr( $nonce ); ?>">
		<?php esc_html_e( 'Clear All', 'barkoda-shipping-for-woocommerce' ); ?>
	</button>
	<hr class="wp-header-end">

	<p class="description">
		<?php
		echo esc_html(
			sprintf(
			/* translators: %d: log count */
				__( 'The last %d integration records, newest first. Automatically capped at 500 records.', 'barkoda-shipping-for-woocommerce' ),
				count( $logs )
			)
		);
		?>
		<?php if ( $total > count( $logs ) ) : ?>
			<em>(<?php /* translators: %d: total number of log records */ echo esc_html( sprintf( __( '%d records in total', 'barkoda-shipping-for-woocommerce' ), $total ) ); ?>)</em>
		<?php endif; ?>
	</p>

	<ul class="subsubsub">
		<?php
		$base           = admin_url( 'admin.php?page=' . \Barkoda_Shipping\Admin_Page::MENU_SLUG . '-logs' );
		$filters        = [
			''        => __( 'All', 'barkoda-shipping-for-woocommerce' ),
			'success' => __( 'Successful', 'barkoda-shipping-for-woocommerce' ),
			'error'   => __( 'Error', 'barkoda-shipping-for-woocommerce' ),
		];
		$last_key       = array_key_last( $filters );
		foreach ( $filters as $val => $lbl ) :
			$url = $val === '' ? $base : add_query_arg( 'filter', $val, $base );
			$cls = $current_filter === $val ? 'current' : '';
			?>
			<li><a href="<?php echo esc_url( $url ); ?>" class="<?php echo esc_attr( $cls ); ?>"><?php echo esc_html( $lbl ); ?></a><?php echo $val !== $last_key ? ' |' : ''; ?></li>
		<?php endforeach; ?>
	</ul>

	<table class="wp-list-table widefat fixed striped wc-ptt-logs-table">
		<thead>
			<tr>
				<th style="width:140px;"><?php esc_html_e( 'Date', 'barkoda-shipping-for-woocommerce' ); ?></th>
				<th style="width:120px;"><?php esc_html_e( 'Operation', 'barkoda-shipping-for-woocommerce' ); ?></th>
				<th style="width:80px;"><?php esc_html_e( 'Order', 'barkoda-shipping-for-woocommerce' ); ?></th>
				<th style="width:80px;"><?php esc_html_e( 'Status', 'barkoda-shipping-for-woocommerce' ); ?></th>
				<th><?php esc_html_e( 'Message', 'barkoda-shipping-for-woocommerce' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php if ( empty( $logs ) ) : ?>
				<tr><td colspan="5" style="text-align:center; padding:2em;"><?php esc_html_e( 'No log records yet.', 'barkoda-shipping-for-woocommerce' ); ?></td></tr>
				<?php
			else :
				foreach ( $logs as $log ) :
					$success    = (int) $log['success'] === 1;
					$order_link = '';
					if ( ! empty( $log['order_id'] ) ) {
						$order_link = get_edit_post_link( (int) $log['order_id'] );
						if ( ! $order_link && function_exists( 'wc_get_order' ) ) {
							$o = wc_get_order( (int) $log['order_id'] );
							if ( $o ) {
								$order_link = $o->get_edit_order_url();
							}
						}
					}
					?>
				<tr>
					<td><small><?php echo esc_html( $log['created_at'] ); ?></small></td>
					<td><code><?php echo esc_html( $log['operation'] ); ?></code></td>
					<td>
						<?php if ( ! empty( $log['order_id'] ) ) : ?>
							<?php if ( $order_link ) : ?>
								<a href="<?php echo esc_url( $order_link ); ?>">#<?php echo esc_html( $log['order_id'] ); ?></a>
							<?php else : ?>
								#<?php echo esc_html( $log['order_id'] ); ?>
							<?php endif; ?>
						<?php else : ?>
							—
						<?php endif; ?>
					</td>
					<td>
						<?php if ( $success ) : ?>
							<span class="status-badge status-sent">✓ OK</span>
						<?php else : ?>
							<span class="status-badge status-error">✗</span>
						<?php endif; ?>
					</td>
					<td>
						<div class="wc-ptt-log-msg"><?php echo esc_html( $log['message'] ?: '—' ); ?></div>
						<?php if ( ! empty( $log['request'] ) || ! empty( $log['response'] ) ) : ?>
							<details class="raw-toggle">
								<summary><?php esc_html_e( 'Raw data', 'barkoda-shipping-for-woocommerce' ); ?></summary>
								<?php if ( ! empty( $log['request'] ) ) : ?>
									<strong><?php esc_html_e( 'Request:', 'barkoda-shipping-for-woocommerce' ); ?></strong>
									<pre class="raw-dump"><?php echo esc_html( $log['request'] ); ?></pre>
								<?php endif; ?>
								<?php if ( ! empty( $log['response'] ) ) : ?>
									<strong><?php esc_html_e( 'Response:', 'barkoda-shipping-for-woocommerce' ); ?></strong>
									<pre class="raw-dump"><?php echo esc_html( $log['response'] ); ?></pre>
								<?php endif; ?>
							</details>
						<?php endif; ?>
					</td>
				</tr>
							<?php
			endforeach;
endif;
			?>
		</tbody>
	</table>
</div>
