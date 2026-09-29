<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- view partial included from a class method, so the variables below are function-scoped rather than global.
/** @var array  $opts */
/** @var string $opt_key */
?>
<h2><?php esc_html_e( 'Sender Details', 'ptt-kargo-for-woocommerce' ); ?></h2>
<p class="description"><?php esc_html_e( 'These details go into the shipment record sent to PTT and appear at the bottom of the label. Filling in every field avoids missing-data errors when sending.', 'ptt-kargo-for-woocommerce' ); ?></p>

<table class="form-table" role="presentation">
	<tr>
		<th><label for="gonderici_ad"><?php esc_html_e( 'Sender Name', 'ptt-kargo-for-woocommerce' ); ?></label></th>
		<td>
			<input type="text" id="gonderici_ad" name="<?php echo esc_attr( $opt_key ); ?>[gonderici_ad]" value="<?php echo esc_attr( $opts['gonderici_ad'] ); ?>" class="large-text" data-preview-key="gonderici_ad">
			<p class="description"><?php esc_html_e( 'Your shop or company name, sent with kabulEkle2. Also used as the label title when the title field is empty.', 'ptt-kargo-for-woocommerce' ); ?></p>
		</td>
	</tr>
	<tr>
		<th><label for="gonderici_soyad"><?php esc_html_e( 'Sender Surname', 'ptt-kargo-for-woocommerce' ); ?></label></th>
		<td>
			<input type="text" id="gonderici_soyad" name="<?php echo esc_attr( $opt_key ); ?>[gonderici_soyad]" value="<?php echo esc_attr( $opts['gonderici_soyad'] ?? '' ); ?>" class="regular-text">
			<p class="description">
				<?php esc_html_e( 'Only the PTT courier service (siparisIstekEkle2) expects the first and last name separately. If empty, the last word of the sender name is used as the surname.', 'ptt-kargo-for-woocommerce' ); ?>
			</p>
		</td>
	</tr>
	<tr>
		<th><label for="gonderici_adres"><?php esc_html_e( 'Address', 'ptt-kargo-for-woocommerce' ); ?></label></th>
		<td>
			<input type="text" id="gonderici_adres" name="<?php echo esc_attr( $opt_key ); ?>[gonderici_adres]" value="<?php echo esc_attr( $opts['gonderici_adres'] ); ?>" class="large-text" data-preview-key="gonderici_adres">
			<p class="description"><?php esc_html_e( 'Neighbourhood, street, building and apartment.', 'ptt-kargo-for-woocommerce' ); ?></p>
		</td>
	</tr>
	<tr>
		<th><label for="gonderici_il"><?php esc_html_e( 'Province', 'ptt-kargo-for-woocommerce' ); ?></label></th>
		<td><input type="text" id="gonderici_il" name="<?php echo esc_attr( $opt_key ); ?>[gonderici_il]" value="<?php echo esc_attr( $opts['gonderici_il'] ); ?>" class="regular-text" data-preview-key="gonderici_il"></td>
	</tr>
	<tr>
		<th><label for="gonderici_ilce"><?php esc_html_e( 'District', 'ptt-kargo-for-woocommerce' ); ?></label></th>
		<td><input type="text" id="gonderici_ilce" name="<?php echo esc_attr( $opt_key ); ?>[gonderici_ilce]" value="<?php echo esc_attr( $opts['gonderici_ilce'] ); ?>" class="regular-text" data-preview-key="gonderici_ilce"></td>
	</tr>
	<tr>
		<th><label for="gonderici_posta"><?php esc_html_e( 'Postcode', 'ptt-kargo-for-woocommerce' ); ?></label></th>
		<td><input type="text" id="gonderici_posta" name="<?php echo esc_attr( $opt_key ); ?>[gonderici_posta]" value="<?php echo esc_attr( $opts['gonderici_posta'] ); ?>" class="small-text" data-preview-key="gonderici_posta"></td>
	</tr>
	<tr>
		<th><label for="gonderici_tel"><?php esc_html_e( 'Phone', 'ptt-kargo-for-woocommerce' ); ?></label></th>
		<td>
			<input type="text" id="gonderici_tel" name="<?php echo esc_attr( $opt_key ); ?>[gonderici_tel]" value="<?php echo esc_attr( $opts['gonderici_tel'] ); ?>" class="regular-text" data-preview-key="gonderici_tel">
			<p class="description"><?php esc_html_e( 'PTT expects 10 digits (5xxxxxxxxx). A leading 0 or 90 is trimmed automatically.', 'ptt-kargo-for-woocommerce' ); ?></p>
		</td>
	</tr>
	<tr>
		<th><label for="gonderici_email"><?php esc_html_e( 'Email', 'ptt-kargo-for-woocommerce' ); ?></label></th>
		<td><input type="email" id="gonderici_email" name="<?php echo esc_attr( $opt_key ); ?>[gonderici_email]" value="<?php echo esc_attr( $opts['gonderici_email'] ); ?>" class="regular-text"></td>
	</tr>
	<tr>
		<th><label for="posta_ceki_no"><?php esc_html_e( 'Postal Cheque Account Number', 'ptt-kargo-for-woocommerce' ); ?></label></th>
		<td>
			<input type="text" id="posta_ceki_no" name="<?php echo esc_attr( $opt_key ); ?>[posta_ceki_no]" value="<?php echo esc_attr( $opts['posta_ceki_no'] ); ?>" class="regular-text" pattern="\d{0,8}" maxlength="8" placeholder="12345678">
			<p class="description">
				<?php esc_html_e( 'The 8-digit PTT Bank account number opened at a PTT branch. It is stated in your PTT integration email.', 'ptt-kargo-for-woocommerce' ); ?>
				<br>
				<?php esc_html_e( 'Required for cash-on-delivery shipments — sent in the PTT envelope as the <code>rezerve1</code> field.', 'ptt-kargo-for-woocommerce' ); ?>
			</p>
		</td>
	</tr>
</table>

<h3 style="margin-top:32px;"><?php esc_html_e( 'Return Address', 'ptt-kargo-for-woocommerce' ); ?></h3>
<p class="description">
	<?php esc_html_e( 'By default, undeliverable parcels are returned to the sender address. Fill in the fields below if returns should go somewhere else, such as a warehouse.', 'ptt-kargo-for-woocommerce' ); ?>
</p>

<table class="form-table" role="presentation">
	<tr>
		<th><?php esc_html_e( 'Different Return Address', 'ptt-kargo-for-woocommerce' ); ?></th>
		<td>
			<label>
				<input type="checkbox" id="iade_adresi_farkli" name="<?php echo esc_attr( $opt_key ); ?>[iade_adresi_farkli]" value="1" <?php checked( ! empty( $opts['iade_adresi_farkli'] ) ); ?>>
				<?php esc_html_e( 'The return address differs from the sender address', 'ptt-kargo-for-woocommerce' ); ?>
			</label>
			<p class="description">
				<?php esc_html_e( 'When unticked, the fields below are not sent to PTT and returns go to the sender address.', 'ptt-kargo-for-woocommerce' ); ?>
			</p>
		</td>
	</tr>
	<tr>
		<th><label for="iade_ad"><?php esc_html_e( 'Return Recipient Name', 'ptt-kargo-for-woocommerce' ); ?></label></th>
		<td><input type="text" id="iade_ad" name="<?php echo esc_attr( $opt_key ); ?>[iade_ad]" value="<?php echo esc_attr( $opts['iade_ad'] ?? '' ); ?>" class="large-text"></td>
	</tr>
	<tr>
		<th><label for="iade_adres"><?php esc_html_e( 'Return Address', 'ptt-kargo-for-woocommerce' ); ?></label></th>
		<td><input type="text" id="iade_adres" name="<?php echo esc_attr( $opt_key ); ?>[iade_adres]" value="<?php echo esc_attr( $opts['iade_adres'] ?? '' ); ?>" class="large-text"></td>
	</tr>
	<tr>
		<th><label for="iade_il"><?php esc_html_e( 'Return Province', 'ptt-kargo-for-woocommerce' ); ?></label></th>
		<td><input type="text" id="iade_il" name="<?php echo esc_attr( $opt_key ); ?>[iade_il]" value="<?php echo esc_attr( $opts['iade_il'] ?? '' ); ?>" class="regular-text"></td>
	</tr>
	<tr>
		<th><label for="iade_ilce"><?php esc_html_e( 'Return District', 'ptt-kargo-for-woocommerce' ); ?></label></th>
		<td><input type="text" id="iade_ilce" name="<?php echo esc_attr( $opt_key ); ?>[iade_ilce]" value="<?php echo esc_attr( $opts['iade_ilce'] ?? '' ); ?>" class="regular-text"></td>
	</tr>
	<tr>
		<th><label for="iade_tel"><?php esc_html_e( 'Return Phone', 'ptt-kargo-for-woocommerce' ); ?></label></th>
		<td>
			<input type="text" id="iade_tel" name="<?php echo esc_attr( $opt_key ); ?>[iade_tel]" value="<?php echo esc_attr( $opts['iade_tel'] ?? '' ); ?>" class="regular-text">
			<p class="description"><?php esc_html_e( '10 digits (5xxxxxxxxx). A leading 0 or 90 is trimmed automatically.', 'ptt-kargo-for-woocommerce' ); ?></p>
		</td>
	</tr>
	<tr>
		<th><label for="iade_email"><?php esc_html_e( 'Return Email', 'ptt-kargo-for-woocommerce' ); ?></label></th>
		<td><input type="email" id="iade_email" name="<?php echo esc_attr( $opt_key ); ?>[iade_email]" value="<?php echo esc_attr( $opts['iade_email'] ?? '' ); ?>" class="regular-text"></td>
	</tr>
</table>
