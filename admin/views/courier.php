<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * @var \PTT_Kargo_WC\Settings $settings
 */

// Sender preview
$gonderici = $settings->gonderici_ad_soyad();
$opts      = $settings->all();

// Show how many orders are pending collection (sent to PTT but not yet collected)
$pending_count = 0;
if ( class_exists( '\PTT_Kargo_WC\Plugin' ) ) {
	$orders = \PTT_Kargo_WC\Plugin::instance()->orders();
	if ( $orders ) {
		$pending_count = count( $orders->eligible_orders( 200, 'sent' ) ); // already sent to PTT but not yet collected
	}
}
?>
<div class="wrap wc-ptt-wrap wc-ptt-courier-wrap">
	<h1><?php esc_html_e( 'PTT Kargo — Kurye Çağır', 'ptt-kargo-for-woocommerce' ); ?></h1>
	<p class="description">
		<?php esc_html_e( 'PTT\'nin gönderilerini şubenizden / adresinizden teslim alması için sipariş geçer (siparisIstekEkle2). Gönderici bilgisi "Gönderici" sekmesindeki ayarlardan alınır.', 'ptt-kargo-for-woocommerce' ); ?>
	</p>

	<div class="wc-ptt-courier-grid">
		<div class="wc-ptt-courier-form">
			<h2><?php esc_html_e( 'Toplama Bilgileri', 'ptt-kargo-for-woocommerce' ); ?></h2>

			<form id="wc-ptt-courier-form" onsubmit="return false;">
				<table class="form-table" role="presentation">
					<tr>
						<th><label for="ptt-courier-adet"><?php esc_html_e( 'Paket Sayısı', 'ptt-kargo-for-woocommerce' ); ?> *</label></th>
						<td>
							<input type="number" id="ptt-courier-adet" name="adet" value="<?php echo esc_attr( max( 1, $pending_count ) ); ?>" min="1" max="9999" class="small-text" required>
							<?php if ( $pending_count > 0 ) : ?>
								<p class="description">
									<?php
									/* translators: %d: number of orders sent to PTT but not yet collected */
									echo esc_html( sprintf( __( '%d sipariş PTT\'ye gönderildi (kabul bekleniyor) — toplama miktarı için referans olarak gösteriliyor.', 'ptt-kargo-for-woocommerce' ), $pending_count ) );
									?>
								</p>
							<?php endif; ?>
						</td>
					</tr>
					<tr>
						<th><label for="ptt-courier-agirlik"><?php esc_html_e( 'Toplam Ağırlık (gram)', 'ptt-kargo-for-woocommerce' ); ?></label></th>
						<td>
							<input type="number" id="ptt-courier-agirlik" name="agirlik" min="0" class="regular-text" placeholder="<?php esc_attr_e( 'Opsiyonel', 'ptt-kargo-for-woocommerce' ); ?>">
						</td>
					</tr>
					<tr>
						<th><label for="ptt-courier-desi"><?php esc_html_e( 'Toplam Desi', 'ptt-kargo-for-woocommerce' ); ?></label></th>
						<td>
							<input type="number" id="ptt-courier-desi" name="desi" min="0" class="small-text" placeholder="<?php esc_attr_e( 'Opsiyonel', 'ptt-kargo-for-woocommerce' ); ?>">
						</td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Tahmini Boyutlar (cm)', 'ptt-kargo-for-woocommerce' ); ?></th>
						<td>
							<input type="number" id="ptt-courier-en" name="en" min="0" class="small-text" placeholder="<?php esc_attr_e( 'En', 'ptt-kargo-for-woocommerce' ); ?>">
							×
							<input type="number" id="ptt-courier-boy" name="boy" min="0" class="small-text" placeholder="<?php esc_attr_e( 'Boy', 'ptt-kargo-for-woocommerce' ); ?>">
							×
							<input type="number" id="ptt-courier-yukseklik" name="yukseklik" min="0" class="small-text" placeholder="<?php esc_attr_e( 'Yükseklik', 'ptt-kargo-for-woocommerce' ); ?>">
						</td>
					</tr>
					<tr>
						<th><label for="ptt-courier-ekhizmet"><?php esc_html_e( 'Ek Hizmetler', 'ptt-kargo-for-woocommerce' ); ?></label></th>
						<td>
							<input type="text" id="ptt-courier-ekhizmet" name="ekhizmet" pattern="[A-Za-z]*" maxlength="40" class="regular-text" placeholder="<?php esc_attr_e( 'Örn: DK (sigortalı), DKUA (sigortalı + alıcıdan)', 'ptt-kargo-for-woocommerce' ); ?>">
							<p class="description">
								<?php esc_html_e( 'Sözleşmenizdeki ek hizmet kodlarını PTT Başmüdürlüğünden öğrenebilirsiniz. Çoklu kod alfabetik birleştirilir (örn. DKUA).', 'ptt-kargo-for-woocommerce' ); ?>
							</p>
						</td>
					</tr>
					<tr>
						<th><label for="ptt-courier-deger"><?php esc_html_e( 'Sigorta Tutarı (TL)', 'ptt-kargo-for-woocommerce' ); ?></label></th>
						<td>
							<input type="number" id="ptt-courier-deger" name="deger_konulmus_ucret" step="0.01" min="0" class="regular-text" placeholder="<?php esc_attr_e( 'Sigortalı göndermek istemiyorsan boş bırak', 'ptt-kargo-for-woocommerce' ); ?>">
							<p class="description"><?php esc_html_e( 'Doldurursan ek hizmet alanına DK eklemen gerekir.', 'ptt-kargo-for-woocommerce' ); ?></p>
						</td>
					</tr>
					<tr>
						<th><label for="ptt-courier-rb"><?php esc_html_e( 'Randevu (opsiyonel)', 'ptt-kargo-for-woocommerce' ); ?></label></th>
						<td>
							<input type="text" id="ptt-courier-rb" name="randevu_baslangic" maxlength="8" class="small-text" placeholder="<?php esc_attr_e( 'Başlangıç', 'ptt-kargo-for-woocommerce' ); ?>">
							→
							<input type="text" id="ptt-courier-rbi" name="randevu_bitis" maxlength="8" class="small-text" placeholder="<?php esc_attr_e( 'Bitiş', 'ptt-kargo-for-woocommerce' ); ?>">
							<p class="description"><?php esc_html_e( 'PTT\'nin desteklediği randevu formatı sözleşmenize göre değişir; boş bırakmak çoğu durumda yeterli.', 'ptt-kargo-for-woocommerce' ); ?></p>
						</td>
					</tr>
				</table>

				<p class="submit">
					<button type="button" class="button button-primary button-hero" id="wc-ptt-courier-submit">
						<span class="dashicons dashicons-car" style="vertical-align:middle;"></span>
						<?php esc_html_e( 'Kurye Çağır', 'ptt-kargo-for-woocommerce' ); ?>
					</button>
				</p>

				<div id="wc-ptt-courier-result" style="display:none;"></div>
			</form>
		</div>

		<aside class="wc-ptt-courier-info">
			<div class="card" style="background:#fff; border:1px solid #c3c4c7; padding:16px; border-radius:4px;">
				<h3 style="margin-top:0;"><?php esc_html_e( 'Toplama Adresi', 'ptt-kargo-for-woocommerce' ); ?></h3>
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
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . \PTT_Kargo_WC\Admin_Page::MENU_SLUG . '-settings&tab=sender' ) ); ?>"><?php esc_html_e( 'Gönderici ayarlarını düzenle →', 'ptt-kargo-for-woocommerce' ); ?></a>
				</p>
			</div>

		</aside>
	</div>
</div>

<style>
.wc-ptt-courier-grid { display: grid; grid-template-columns: minmax(0, 1fr) 320px; gap: 24px; align-items: start; margin-top: 16px; }
.wc-ptt-courier-form { background: #fff; border: 1px solid #c3c4c7; padding: 4px 24px 24px; border-radius: 4px; }
@media (max-width: 1100px) { .wc-ptt-courier-grid { grid-template-columns: 1fr; } }
#wc-ptt-courier-result { padding: 12px 16px; border-radius: 4px; margin-top: 16px; font-size: 13px; }
#wc-ptt-courier-result.is-success { background: #d4edda; color: #155724; border-left: 4px solid #00a32a; }
#wc-ptt-courier-result.is-error   { background: #f8d7da; color: #721c24; border-left: 4px solid #d63638; }
</style>
