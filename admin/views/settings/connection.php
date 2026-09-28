<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/** @var array  $opts */
/** @var string $opt_key */
?>
<h2><?php esc_html_e( 'PTT Bağlantı Bilgileri', 'ptt-kargo-for-woocommerce' ); ?></h2>
<p class="description"><?php esc_html_e( 'PTT entegrasyon hizmeti için size verilen müşteri numarası ve şifresini girin. İlk başta Test ortamıyla doğrulayın.', 'ptt-kargo-for-woocommerce' ); ?></p>

<table class="form-table" role="presentation">
	<tr>
		<th><label for="environment"><?php esc_html_e( 'Ortam', 'ptt-kargo-for-woocommerce' ); ?></label></th>
		<td>
			<select name="<?php echo esc_attr( $opt_key ); ?>[environment]" id="environment" data-preview-key="environment">
				<option value="test" <?php selected( $opts['environment'], 'test' ); ?>><?php esc_html_e( 'Test', 'ptt-kargo-for-woocommerce' ); ?></option>
				<option value="prod" <?php selected( $opts['environment'], 'prod' ); ?>><?php esc_html_e( 'Canlı (Production)', 'ptt-kargo-for-woocommerce' ); ?></option>
			</select>
			<p class="description"><?php esc_html_e( 'PTT onayından sonra Canlı\'ya geçin.', 'ptt-kargo-for-woocommerce' ); ?></p>
		</td>
	</tr>
	<tr>
		<th><label for="musteri_id"><?php esc_html_e( 'Müşteri Numarası', 'ptt-kargo-for-woocommerce' ); ?></label></th>
		<td>
			<input type="text" id="musteri_id" name="<?php echo esc_attr( $opt_key ); ?>[musteri_id]" value="<?php echo esc_attr( $opts['musteri_id'] ); ?>" class="regular-text" required>
			<p class="description"><?php esc_html_e( 'PTT\'nin entegrasyon onayında verdiği numerik müşteri ID\'si.', 'ptt-kargo-for-woocommerce' ); ?></p>
		</td>
	</tr>
	<tr>
		<th><label for="sifre"><?php esc_html_e( 'Şifre', 'ptt-kargo-for-woocommerce' ); ?></label></th>
		<td>
			<input type="password" id="sifre" name="<?php echo esc_attr( $opt_key ); ?>[sifre]" value="" autocomplete="new-password" class="regular-text" placeholder="<?php echo $opts['sifre_enc'] !== '' ? esc_attr__( 'Değiştirmek için yeni şifre girin', 'ptt-kargo-for-woocommerce' ) : ''; ?>">
			<?php if ( $opts['sifre_enc'] !== '' ) : ?>
				<p class="description">✓ <?php esc_html_e( 'Şifre kayıtlı (AES-256 ile şifrelenmiş). Değiştirmek istemiyorsanız boş bırakın.', 'ptt-kargo-for-woocommerce' ); ?></p>
			<?php else : ?>
				<p class="description"><?php esc_html_e( 'WordPress AUTH_KEY tabanlı AES-256 ile şifrelenip saklanır.', 'ptt-kargo-for-woocommerce' ); ?></p>
			<?php endif; ?>
		</td>
	</tr>
	<tr>
		<th><?php esc_html_e( 'Bağlantı Testi', 'ptt-kargo-for-woocommerce' ); ?></th>
		<td>
			<button type="button" class="button button-secondary" id="wc-ptt-test-conn-btn">
				<span class="dashicons dashicons-update" style="vertical-align:middle;"></span>
				<?php esc_html_e( 'Bağlantıyı Test Et', 'ptt-kargo-for-woocommerce' ); ?>
			</button>
			<div class="wc-ptt-test-result" id="wc-ptt-test-result" style="display:none;"></div>
			<p class="description"><?php esc_html_e( 'Yukarıdaki bilgilerle PTT servislerine örnek bir sorgu atar. Kaydetmeden önce de çalışır.', 'ptt-kargo-for-woocommerce' ); ?></p>
		</td>
	</tr>
</table>
