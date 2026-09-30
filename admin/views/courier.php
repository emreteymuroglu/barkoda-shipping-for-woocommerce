<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- view partial included from a class method, so the variables below are function-scoped rather than global.
/**
 * @var \Barkoda_Shipping\Settings $settings
 */

// Sender preview
$gonderici = $settings->gonderici_ad_soyad();
$opts      = $settings->all();

// Show how many orders are pending collection (sent to PTT but not yet collected)
$pending_count = 0;
if ( class_exists( '\Barkoda_Shipping\Plugin' ) ) {
	$orders = \Barkoda_Shipping\Plugin::instance()->orders();
	if ( $orders ) {
		$pending_count = count( $orders->eligible_orders( 200, 'sent' ) ); // already sent to PTT but not yet collected
	}
}
?>
<div class="wrap wc-ptt-wrap wc-ptt-courier-wrap">
	<h1><?php esc_html_e( 'PTT Kargo — Request Courier', 'barkoda-shipping-for-woocommerce' ); ?></h1>
	<p class="description">
		<?php esc_html_e( 'Places a pickup order so PTT collects your shipments from your address (siparisIstekEkle2). Sender details are taken from the Sender tab.', 'barkoda-shipping-for-woocommerce' ); ?>
	</p>

	<div class="wc-ptt-courier-grid">
		<div class="wc-ptt-courier-form">
			<h2><?php esc_html_e( 'Pickup Details', 'barkoda-shipping-for-woocommerce' ); ?></h2>

			<form id="wc-ptt-courier-form" onsubmit="return false;">
				<table class="form-table" role="presentation">
					<tr>
						<th><label for="ptt-courier-adet"><?php esc_html_e( 'Package Count', 'barkoda-shipping-for-woocommerce' ); ?> *</label></th>
						<td>
							<input type="number" id="ptt-courier-adet" name="adet" value="<?php echo esc_attr( max( 1, $pending_count ) ); ?>" min="1" max="9999" class="small-text" required>
							<?php if ( $pending_count > 0 ) : ?>
								<p class="description">
									<?php
									/* translators: %d: number of orders sent to PTT but not yet collected */
									echo esc_html( sprintf( __( '%d order(s) sent to PTT and awaiting acceptance — shown as a reference for the pickup quantity.', 'barkoda-shipping-for-woocommerce' ), $pending_count ) );
									?>
								</p>
							<?php endif; ?>
						</td>
					</tr>
					<tr>
						<th><label for="ptt-courier-agirlik"><?php esc_html_e( 'Total Weight (grams)', 'barkoda-shipping-for-woocommerce' ); ?></label></th>
						<td>
							<input type="number" id="ptt-courier-agirlik" name="agirlik" min="0" class="regular-text" placeholder="<?php esc_attr_e( 'Optional', 'barkoda-shipping-for-woocommerce' ); ?>">
						</td>
					</tr>
					<tr>
						<th><label for="ptt-courier-desi"><?php esc_html_e( 'Total Volumetric Weight', 'barkoda-shipping-for-woocommerce' ); ?></label></th>
						<td>
							<input type="number" id="ptt-courier-desi" name="desi" min="0" class="small-text" placeholder="<?php esc_attr_e( 'Optional', 'barkoda-shipping-for-woocommerce' ); ?>">
						</td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Estimated Dimensions (cm)', 'barkoda-shipping-for-woocommerce' ); ?></th>
						<td>
							<input type="number" id="ptt-courier-en" name="en" min="0" class="small-text" placeholder="<?php esc_attr_e( 'Width', 'barkoda-shipping-for-woocommerce' ); ?>">
							×
							<input type="number" id="ptt-courier-boy" name="boy" min="0" class="small-text" placeholder="<?php esc_attr_e( 'Length', 'barkoda-shipping-for-woocommerce' ); ?>">
							×
							<input type="number" id="ptt-courier-yukseklik" name="yukseklik" min="0" class="small-text" placeholder="<?php esc_attr_e( 'Height', 'barkoda-shipping-for-woocommerce' ); ?>">
						</td>
					</tr>
					<tr>
						<th><label for="ptt-courier-ekhizmet"><?php esc_html_e( 'Additional Services', 'barkoda-shipping-for-woocommerce' ); ?></label></th>
						<td>
							<input type="text" id="ptt-courier-ekhizmet" name="ekhizmet" pattern="[A-Za-z]*" maxlength="40" class="regular-text" placeholder="<?php esc_attr_e( 'e.g. DK (insured), DKUA (insured + charge recipient)', 'barkoda-shipping-for-woocommerce' ); ?>">
							<p class="description">
								<?php esc_html_e( 'Ask your PTT regional office which additional service codes your contract includes. Multiple codes are merged alphabetically (e.g. DKUA).', 'barkoda-shipping-for-woocommerce' ); ?>
							</p>
						</td>
					</tr>
					<tr>
						<th><label for="ptt-courier-deger"><?php esc_html_e( 'Insured Value (TRY)', 'barkoda-shipping-for-woocommerce' ); ?></label></th>
						<td>
							<input type="number" id="ptt-courier-deger" name="deger_konulmus_ucret" step="0.01" min="0" class="regular-text" placeholder="<?php esc_attr_e( 'Leave empty if you do not want insurance', 'barkoda-shipping-for-woocommerce' ); ?>">
							<p class="description"><?php esc_html_e( 'If you fill this in, you must add DK to the additional services field.', 'barkoda-shipping-for-woocommerce' ); ?></p>
						</td>
					</tr>
					<tr>
						<th><label for="ptt-courier-rb"><?php esc_html_e( 'Appointment (optional)', 'barkoda-shipping-for-woocommerce' ); ?></label></th>
						<td>
							<input type="text" id="ptt-courier-rb" name="randevu_baslangic" maxlength="8" class="small-text" placeholder="<?php esc_attr_e( 'Start', 'barkoda-shipping-for-woocommerce' ); ?>">
							→
							<input type="text" id="ptt-courier-rbi" name="randevu_bitis" maxlength="8" class="small-text" placeholder="<?php esc_attr_e( 'End', 'barkoda-shipping-for-woocommerce' ); ?>">
							<p class="description"><?php esc_html_e( 'The appointment format PTT accepts depends on your contract; leaving this empty is usually fine.', 'barkoda-shipping-for-woocommerce' ); ?></p>
						</td>
					</tr>
				</table>

				<p class="submit">
					<button type="button" class="button button-primary button-hero" id="wc-ptt-courier-submit">
						<span class="dashicons dashicons-car" style="vertical-align:middle;"></span>
						<?php esc_html_e( 'Request Courier', 'barkoda-shipping-for-woocommerce' ); ?>
					</button>
				</p>

				<div id="wc-ptt-courier-result" style="display:none;"></div>
			</form>
		</div>

		<aside class="wc-ptt-courier-info">
			<div class="card" style="background:#fff; border:1px solid #c3c4c7; padding:16px; border-radius:4px;">
				<h3 style="margin-top:0;"><?php esc_html_e( 'Pickup Address', 'barkoda-shipping-for-woocommerce' ); ?></h3>
				<p>
					<strong><?php echo esc_html( trim( $gonderici['ad'] . ' ' . $gonderici['soyad'] ) ); ?></strong><br>
					<?php echo esc_html( $opts['gonderici_adres'] ?? '' ); ?><br>
					<?php echo esc_html( ( $opts['gonderici_ilce'] ?? '' ) . ' / ' . ( $opts['gonderici_il'] ?? '' ) . ' ' . ( $opts['gonderici_posta'] ?? '' ) ); ?><br>
					<?php if ( ! empty( $opts['gonderici_tel'] ) ) : ?>
						Tel: <?php echo esc_html( $opts['gonderici_tel'] ); ?><br>
					<?php endif; ?>
					<?php if ( ! empty( $opts['gonderici_email'] ) ) : ?>
						Email: <?php echo esc_html( $opts['gonderici_email'] ); ?>
					<?php endif; ?>
				</p>
				<p>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . \Barkoda_Shipping\Admin_Page::MENU_SLUG . '-settings&tab=sender' ) ); ?>"><?php esc_html_e( 'Edit sender settings →', 'barkoda-shipping-for-woocommerce' ); ?></a>
				</p>
			</div>

		</aside>
	</div>
</div>
