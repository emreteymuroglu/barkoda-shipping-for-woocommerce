<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- view partial included from a class method, so the variables below are function-scoped rather than global.
/** @var array $orders */

$label = \PTT_Kargo_WC\Plugin::instance()->label();
?>
<table class="wp-list-table widefat fixed striped wc-ptt-table">
	<thead>
		<tr>
			<th class="col-order"><?php esc_html_e( 'Order', 'ptt-kargo-for-woocommerce' ); ?></th>
			<th class="col-date"><?php esc_html_e( 'Date', 'ptt-kargo-for-woocommerce' ); ?></th>
			<th class="col-customer"><?php esc_html_e( 'Customer', 'ptt-kargo-for-woocommerce' ); ?></th>
			<th class="col-address"><?php esc_html_e( 'Address', 'ptt-kargo-for-woocommerce' ); ?></th>
			<th class="col-items"><?php esc_html_e( 'Products', 'ptt-kargo-for-woocommerce' ); ?></th>
			<th class="col-status"><?php esc_html_e( 'PTT Status', 'ptt-kargo-for-woocommerce' ); ?></th>
			<th class="col-actions"><?php esc_html_e( 'Action', 'ptt-kargo-for-woocommerce' ); ?></th>
		</tr>
	</thead>
	<tbody>
		<?php if ( empty( $orders ) ) : ?>
			<tr><td colspan="7" style="text-align:center; padding:2em;"><?php esc_html_e( 'No orders to show.', 'ptt-kargo-for-woocommerce' ); ?></td></tr>
			<?php
		else :
			foreach ( $orders as $ptt_order ) :
				$ptt_status  = (string) $ptt_order->get_meta( \PTT_Kargo_WC\Orders::META_STATUS );
				$barkod  = (string) $ptt_order->get_meta( \PTT_Kargo_WC\Orders::META_BARKOD );
				$takip   = (string) $ptt_order->get_meta( \PTT_Kargo_WC\Orders::META_TAKIP_URL );
				$hata    = $ptt_status === \PTT_Kargo_WC\Orders::STATUS_ERROR ? (string) $ptt_order->get_meta( \PTT_Kargo_WC\Orders::META_PTT_LOG ) : '';
				$alici   = trim( $ptt_order->get_shipping_first_name() . ' ' . $ptt_order->get_shipping_last_name() ) ?: trim( $ptt_order->get_billing_first_name() . ' ' . $ptt_order->get_billing_last_name() );
				$tel     = $ptt_order->get_billing_phone();
				$adres_1 = $ptt_order->get_shipping_address_1() ?: $ptt_order->get_billing_address_1();
				$ilce    = $ptt_order->get_shipping_city() ?: $ptt_order->get_billing_city();
				$il_kod  = $ptt_order->get_shipping_state() ?: $ptt_order->get_billing_state();
				$il      = $il_kod;
				if ( function_exists( 'WC' ) && WC()->countries ) {
					$sts = WC()->countries->get_states( 'TR' );
					if ( isset( $sts[ $il_kod ] ) ) {
						$il = $sts[ $il_kod ];
					}
				}
				?>
			<tr data-order-id="<?php echo esc_attr( $ptt_order->get_id() ); ?>">
				<td class="col-order">
					<a href="<?php echo esc_url( $ptt_order->get_edit_order_url() ); ?>" target="_blank">#<?php echo esc_html( $ptt_order->get_order_number() ); ?></a>
				</td>
				<td class="col-date">
					<?php echo esc_html( $ptt_order->get_date_created() ? $ptt_order->get_date_created()->date_i18n( 'd.m.Y H:i' ) : '' ); ?>
				</td>
				<td class="col-customer">
					<strong><?php echo esc_html( $alici ); ?></strong><br>
					<small><?php echo esc_html( $tel ); ?></small><br>
					<small><?php echo esc_html( $ptt_order->get_billing_email() ); ?></small>
				</td>
				<td class="col-address">
					<?php echo esc_html( $adres_1 ); ?><br>
					<strong><?php echo esc_html( $ilce ); ?> / <?php echo esc_html( $il ); ?></strong>
				</td>
				<td class="col-items">
					<ul class="items-mini all-items">
						<?php foreach ( $ptt_order->get_items() as $item ) : ?>
							<li><?php echo esc_html( $item->get_name() ); ?> × <?php echo esc_html( $item->get_quantity() ); ?></li>
						<?php endforeach; ?>
					</ul>
				</td>
				<td class="col-status">
					<?php
					if ( $ptt_status === \PTT_Kargo_WC\Orders::STATUS_SENT ) :
						$ptt_mesaj       = (string) $ptt_order->get_meta( \PTT_Kargo_WC\Orders::META_PTT_LOG );
						$raw_resp        = (string) $ptt_order->get_meta( \PTT_Kargo_WC\Orders::META_PTT_RAW );
						$raw_req         = (string) $ptt_order->get_meta( \PTT_Kargo_WC\Orders::META_PTT_REQ );
						$parca_adet      = (int) $ptt_order->get_meta( \PTT_Kargo_WC\Orders::META_PARCA_ADET );
						$parca_barkodlar = (string) $ptt_order->get_meta( \PTT_Kargo_WC\Orders::META_PARCA_BARKODLAR );
						$irsaliye        = (string) $ptt_order->get_meta( \PTT_Kargo_WC\Orders::META_IRSALIYE_NO );
						?>
						<span class="status-badge status-sent"><?php esc_html_e( 'Sent', 'ptt-kargo-for-woocommerce' ); ?></span><br>
						<small class="barkod-mini"><?php echo esc_html( $barkod ); ?></small>
						<?php
						if ( $parca_adet > 1 && $parca_barkodlar !== '' ) :
							$bks = json_decode( $parca_barkodlar, true );
							if ( is_array( $bks ) ) :
								?>
								<details class="raw-toggle" style="margin-top:4px;">
									<summary><?php /* translators: %d: number of packages in the shipment */ echo esc_html( sprintf( __( '%d packages', 'ptt-kargo-for-woocommerce' ), $parca_adet ) ); ?>
										<?php
										if ( $irsaliye !== '' ) {
											echo ' · İrs. ' . esc_html( $irsaliye );}
										?>
									</summary>
									<ul class="items-mini" style="margin-top:4px;">
									<?php foreach ( $bks as $bk ) : ?>
										<li><code><?php echo esc_html( $bk ); ?></code></li>
									<?php endforeach; ?>
									</ul>
								</details>
								<?php
							endif;
						endif;
						?>
						<?php if ( $ptt_mesaj !== '' ) : ?>
							<br><small class="ptt-mesaj"><?php echo esc_html( $ptt_mesaj ); ?></small>
						<?php endif; ?>
						<?php if ( $takip ) : ?>
							<br><a href="<?php echo esc_url( $takip ); ?>" target="_blank"><?php esc_html_e( 'PTT tracking link', 'ptt-kargo-for-woocommerce' ); ?></a>
						<?php endif; ?>
						<?php if ( $raw_resp !== '' || $raw_req !== '' ) : ?>
							<details class="raw-toggle">
								<summary><?php esc_html_e( 'Show raw data', 'ptt-kargo-for-woocommerce' ); ?></summary>
								<?php if ( $raw_req !== '' ) : ?>
									<strong><?php esc_html_e( 'Request (SOAP sent):', 'ptt-kargo-for-woocommerce' ); ?></strong>
									<pre class="raw-dump"><?php echo esc_html( $raw_req ); ?></pre>
								<?php endif; ?>
								<?php if ( $raw_resp !== '' ) : ?>
									<strong><?php esc_html_e( 'Response (from PTT):', 'ptt-kargo-for-woocommerce' ); ?></strong>
									<pre class="raw-dump"><?php echo esc_html( $raw_resp ); ?></pre>
								<?php endif; ?>
							</details>
						<?php endif; ?>
					<?php elseif ( $ptt_status === \PTT_Kargo_WC\Orders::STATUS_CANCELED ) : ?>
						<span class="status-badge status-canceled"><?php esc_html_e( 'Cancel', 'ptt-kargo-for-woocommerce' ); ?></span>
						<?php $cancel_msg = (string) $ptt_order->get_meta( \PTT_Kargo_WC\Orders::META_PTT_LOG ); ?>
						<?php if ( $cancel_msg !== '' ) : ?>
							<small class="ptt-mesaj" style="color:#646970;"><?php echo esc_html( $cancel_msg ); ?></small>
						<?php endif; ?>
						<?php
								elseif ( $ptt_status === \PTT_Kargo_WC\Orders::STATUS_ERROR ) :
									$raw_resp = (string) $ptt_order->get_meta( \PTT_Kargo_WC\Orders::META_PTT_RAW );
									$raw_req  = (string) $ptt_order->get_meta( \PTT_Kargo_WC\Orders::META_PTT_REQ );
									?>
						<span class="status-badge status-error"><?php esc_html_e( 'Error', 'ptt-kargo-for-woocommerce' ); ?></span>
						<small class="error-msg"><?php echo esc_html( $hata ); ?></small>
									<?php if ( $raw_resp !== '' || $raw_req !== '' ) : ?>
							<details class="raw-toggle">
								<summary><?php esc_html_e( 'Show raw data', 'ptt-kargo-for-woocommerce' ); ?></summary>
										<?php if ( $raw_req !== '' ) : ?>
									<strong><?php esc_html_e( 'Request (SOAP sent):', 'ptt-kargo-for-woocommerce' ); ?></strong>
									<pre class="raw-dump"><?php echo esc_html( $raw_req ); ?></pre>
								<?php endif; ?>
										<?php if ( $raw_resp !== '' ) : ?>
									<strong><?php esc_html_e( 'Response (from PTT):', 'ptt-kargo-for-woocommerce' ); ?></strong>
									<pre class="raw-dump"><?php echo esc_html( $raw_resp ); ?></pre>
								<?php endif; ?>
							</details>
						<?php endif; ?>
								<?php else : ?>
						<span class="status-badge status-pending"><?php esc_html_e( 'Waiting', 'ptt-kargo-for-woocommerce' ); ?></span>
					<?php endif; ?>
				</td>
				<td class="col-actions">
								<?php if ( $ptt_status !== \PTT_Kargo_WC\Orders::STATUS_SENT ) : ?>
						<button type="button" class="button button-primary js-ptt-send" data-order-id="<?php echo esc_attr( $ptt_order->get_id() ); ?>">
									<?php
									echo $ptt_status === \PTT_Kargo_WC\Orders::STATUS_CANCELED
									? esc_html__( 'Resend', 'ptt-kargo-for-woocommerce' )
									: esc_html__( 'Ship', 'ptt-kargo-for-woocommerce' );
									?>
						</button>
					<?php endif; ?>
								<?php if ( $barkod && $ptt_status === \PTT_Kargo_WC\Orders::STATUS_SENT ) : ?>
						<a class="button" href="<?php echo esc_url( $label->label_url( $ptt_order->get_id() ) ); ?>" target="_blank">
									<?php esc_html_e( 'Print Label', 'ptt-kargo-for-woocommerce' ); ?>
						</a>
						<button type="button" class="button js-ptt-takip" data-order-id="<?php echo esc_attr( $ptt_order->get_id() ); ?>">
									<?php esc_html_e( 'Track', 'ptt-kargo-for-woocommerce' ); ?>
						</button>
						<button type="button" class="button button-link-delete js-ptt-cancel" data-order-id="<?php echo esc_attr( $ptt_order->get_id() ); ?>" title="<?php esc_attr_e( 'Cancel the PTT shipment (only while it has not been accepted)', 'ptt-kargo-for-woocommerce' ); ?>">
									<?php esc_html_e( 'Cancel', 'ptt-kargo-for-woocommerce' ); ?>
						</button>
					<?php endif; ?>
				</td>
			</tr>
					<?php
		endforeach;
endif;
		?>
	</tbody>
</table>
