<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/** @var array  $opts */
/** @var string $opt_key */

// Pull all current payment gateways defined in WC (even if not active).
$gateways = [];
if ( function_exists( 'WC' ) && WC()->payment_gateways ) {
	$all = WC()->payment_gateways->payment_gateways();
	foreach ( $all as $id => $gateway ) {
		// Only include gateways that are valid objects and have an ID.
		if ( ! is_object( $gateway ) || empty( $gateway->id ) ) {
			continue;
		}
		$gateways[ $gateway->id ] = [
			'title'   => $gateway->get_method_title(),
			'enabled' => ( method_exists( $gateway, 'is_available' ) && $gateway->is_available() ) || ( $gateway->enabled ?? '' ) === 'yes',
		];
	}
}

$selected = (array) ( $opts['cod_payment_methods'] ?? [] );
?>
<h2><?php esc_html_e( 'Cash on Delivery (COD) Mapping', 'ptt-kargo-for-woocommerce' ); ?></h2>
<p class="description">
	<?php esc_html_e( 'The WooCommerce payment methods you tick here count as cash on delivery. Orders paid with them are sent to PTT with:', 'ptt-kargo-for-woocommerce' ); ?>
</p>
<ul style="list-style:disc; margin-left:24px; font-size:13px; color:#50575e;">
	<li><code>odemesekli</code> = <strong>UA</strong> (Ücreti Alıcıdan)</li>
	<li><code>odeme_sart_ucreti</code> = sipariş toplam tutarı</li>
	<li><code>ekhizmet</code> alanına aşağıdaki kod (varsayılan <strong>OS</strong>) eklenir</li>
</ul>
<p class="description">
	<?php esc_html_e( '⚠ When COD is active, Sender → Postal Cheque Account Number must be filled in, otherwise PTT rejects the shipment.', 'ptt-kargo-for-woocommerce' ); ?>
</p>

<table class="form-table" role="presentation">
	<tr>
		<th><?php esc_html_e( 'Cash on Delivery Methods', 'ptt-kargo-for-woocommerce' ); ?></th>
		<td>
			<?php if ( empty( $gateways ) ) : ?>
				<p><em><?php esc_html_e( 'No payment methods found. Enable at least one under WooCommerce > Settings > Payments.', 'ptt-kargo-for-woocommerce' ); ?></em></p>
			<?php else : ?>
				<fieldset>
					<?php
					foreach ( $gateways as $gw_id => $gw ) :
						$is_checked = in_array( $gw_id, $selected, true );
						?>
						<label style="display:block; margin-bottom:6px;">
							<input type="checkbox"
								name="<?php echo esc_attr( $opt_key ); ?>[cod_payment_methods][]"
								value="<?php echo esc_attr( $gw_id ); ?>"
								<?php checked( $is_checked ); ?>>
							<strong><?php echo esc_html( $gw['title'] ); ?></strong>
							<code style="background:#f0f0f1; padding:1px 6px; border-radius:2px; font-size:11px;"><?php echo esc_html( $gw_id ); ?></code>
							<?php if ( ! $gw['enabled'] ) : ?>
								<small style="color:#646970;">(<?php esc_html_e( 'currently inactive', 'ptt-kargo-for-woocommerce' ); ?>)</small>
							<?php endif; ?>
						</label>
					<?php endforeach; ?>
				</fieldset>
				<p class="description">
					<?php esc_html_e( 'The usual choice is WooCommerce Cash on Delivery (cod). Card and online payments count as normal shipments and should not be ticked.', 'ptt-kargo-for-woocommerce' ); ?>
				</p>
			<?php endif; ?>
		</td>
	</tr>
	<tr>
		<th><label for="cod_extra_service_code"><?php esc_html_e( 'COD Additional Service Code', 'ptt-kargo-for-woocommerce' ); ?></label></th>
		<td>
			<input type="text" id="cod_extra_service_code" name="<?php echo esc_attr( $opt_key ); ?>[cod_extra_service_code]" value="<?php echo esc_attr( $opts['cod_extra_service_code'] ?? 'OS' ); ?>" class="small-text" maxlength="10" pattern="[A-Za-z]+">
			<p class="description">
				<?php esc_html_e( 'PTT\'s payment-on-delivery service code — default: ', 'ptt-kargo-for-woocommerce' ); ?>
				<code>OS</code>.
				<?php esc_html_e( 'Change this if the PTT integration team gave you a different code, such as the combination DKUA.', 'ptt-kargo-for-woocommerce' ); ?>
			</p>
			<p class="description">
				<?php esc_html_e( 'The code is merged automatically with the fixed code in Shipment Defaults → Additional Service Code, for example DK + OS = DKOS.', 'ptt-kargo-for-woocommerce' ); ?>
			</p>
		</td>
	</tr>
</table>

<div class="wc-ptt-tip">
	💡 <strong><?php esc_html_e( 'Reminder:', 'ptt-kargo-for-woocommerce' ); ?></strong>
	<?php esc_html_e( 'Cash-on-delivery shipments are sent to PTT as collect-on-delivery. PTT transfers the collected amount to your Postal Cheque account. Contact your PTT branch to verify the account.', 'ptt-kargo-for-woocommerce' ); ?>
</div>

<h2 style="margin-top:32px;"><?php esc_html_e( 'Insurance (Valuable Goods)', 'ptt-kargo-for-woocommerce' ); ?></h2>
<p class="description">
	<?php esc_html_e( 'Insurance is toggled per order in the Ship popup. When enabled, the PTT envelope carries:', 'ptt-kargo-for-woocommerce' ); ?>
</p>
<ul style="list-style:disc; margin-left:24px; font-size:13px; color:#50575e;">
	<li><code>deger_ucreti</code> = popup'ta girilen tutar</li>
	<li><code>ekhizmet</code> alanına <strong>DK</strong> (varsayılan) eklenir, mevcutla birleştirilir (DKUA gibi)</li>
</ul>

<table class="form-table" role="presentation">
	<tr>
		<th><label for="insurance_extra_service_code"><?php esc_html_e( 'Insurance Additional Service Code', 'ptt-kargo-for-woocommerce' ); ?></label></th>
		<td>
			<input type="text" id="insurance_extra_service_code" name="<?php echo esc_attr( $opt_key ); ?>[insurance_extra_service_code]" value="<?php echo esc_attr( $opts['insurance_extra_service_code'] ?? 'DK' ); ?>" class="small-text" maxlength="10" pattern="[A-Za-z]+">
			<p class="description">
				<?php esc_html_e( 'PTT\'s Valuable Goods code — default: ', 'ptt-kargo-for-woocommerce' ); ?>
				<code>DK</code>.
			</p>
		</td>
	</tr>
</table>

<div class="wc-ptt-tip">
	💡 <?php esc_html_e( 'When both cash on delivery and valuable goods are active, the service codes are merged in the PTT envelope, for example DKUA + OS = DKUAOS.', 'ptt-kargo-for-woocommerce' ); ?>
</div>
