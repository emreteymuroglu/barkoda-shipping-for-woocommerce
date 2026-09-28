<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/** @var array  $opts */
/** @var string $opt_key */
?>
<h2><?php esc_html_e( 'Etiket Görünümü', 'ptt-kargo-for-woocommerce' ); ?></h2>
<p class="description"><?php esc_html_e( 'Etiketteki tüm metinler ve hangi blokların görüneceği buradan kontrol edilir. Sağdaki canlı önizleme ile değişiklikleri anında görebilirsin.', 'ptt-kargo-for-woocommerce' ); ?></p>

<h3><?php esc_html_e( 'Marka Bloğu (Üst Kısım)', 'ptt-kargo-for-woocommerce' ); ?></h3>
<table class="form-table" role="presentation">
	<tr>
		<th><label for="label_logo_url"><?php esc_html_e( 'Logo', 'ptt-kargo-for-woocommerce' ); ?></label></th>
		<td>
			<div class="wc-ptt-logo-picker">
				<input type="text" id="label_logo_url" name="<?php echo esc_attr( $opt_key ); ?>[label_logo_url]" value="<?php echo esc_attr( $opts['label_logo_url'] ); ?>" class="large-text" placeholder="https://..." data-preview-key="label_logo_url">
				<button type="button" class="button" id="wc-ptt-logo-pick"><?php esc_html_e( 'Görsel Seç', 'ptt-kargo-for-woocommerce' ); ?></button>
				<button type="button" class="button" id="wc-ptt-logo-clear"><?php esc_html_e( 'Kaldır', 'ptt-kargo-for-woocommerce' ); ?></button>
			</div>
			<div class="wc-ptt-logo-preview" id="wc-ptt-logo-preview" style="<?php echo $opts['label_logo_url'] === '' ? 'display:none;' : ''; ?>">
				<?php if ( $opts['label_logo_url'] !== '' ) : ?>
					<img src="<?php echo esc_url( $opts['label_logo_url'] ); ?>" alt="">
				<?php endif; ?>
			</div>
			<p class="description"><?php esc_html_e( 'WordPress Medya Kütüphanesi\'nden seç ya da doğrudan URL gir. Etikette maks. ~60mm × 18mm boyutta görünür.', 'ptt-kargo-for-woocommerce' ); ?></p>
		</td>
	</tr>
	<tr>
		<th><label for="label_header_title"><?php esc_html_e( 'Başlık', 'ptt-kargo-for-woocommerce' ); ?></label></th>
		<td>
			<input type="text" id="label_header_title" name="<?php echo esc_attr( $opt_key ); ?>[label_header_title]" value="<?php echo esc_attr( $opts['label_header_title'] ); ?>" class="large-text" data-preview-key="label_header_title">
			<p class="description"><?php esc_html_e( 'Etiketin tepe başlığı (büyük harfle basılır). Boş bırakılırsa "Gönderici Adı" kullanılır; o da boşsa site adı.', 'ptt-kargo-for-woocommerce' ); ?></p>
		</td>
	</tr>
	<tr>
		<th><label for="label_header_subtitle"><?php esc_html_e( 'Alt başlık', 'ptt-kargo-for-woocommerce' ); ?></label></th>
		<td>
			<input type="text" id="label_header_subtitle" name="<?php echo esc_attr( $opt_key ); ?>[label_header_subtitle]" value="<?php echo esc_attr( $opts['label_header_subtitle'] ); ?>" class="large-text" data-preview-key="label_header_subtitle">
			<p class="description"><?php esc_html_e( 'Başlığın altına yazılacak küçük metin. Boş bırakılabilir.', 'ptt-kargo-for-woocommerce' ); ?></p>
		</td>
	</tr>
</table>

<h3><?php esc_html_e( 'Etiket Bölümleri', 'ptt-kargo-for-woocommerce' ); ?></h3>
<p class="description"><?php esc_html_e( 'Etikette görünmesini istediğin bölümleri işaretle. Bölüm başlıkları otomatik gizlenir.', 'ptt-kargo-for-woocommerce' ); ?></p>
<table class="form-table" role="presentation">
	<tr>
		<th><?php esc_html_e( 'Görünür Bloklar', 'ptt-kargo-for-woocommerce' ); ?></th>
		<td>
			<?php
			$blocks = [
				'label_show_order'     => __( 'Sipariş No + Tarih', 'ptt-kargo-for-woocommerce' ),
				'label_show_recipient' => __( 'Alıcı Bilgileri', 'ptt-kargo-for-woocommerce' ),
				'label_show_products'  => __( 'Ürün Listesi', 'ptt-kargo-for-woocommerce' ),
				'label_show_barcode'   => __( 'Barkod', 'ptt-kargo-for-woocommerce' ),
				'label_show_sender'    => __( 'Gönderici Bloğu (Alt Kısım)', 'ptt-kargo-for-woocommerce' ),
			];
			foreach ( $blocks as $key => $lbl ) :
				?>
				<label style="display:block; margin-bottom:6px;">
					<input type="checkbox" name="<?php echo esc_attr( $opt_key ); ?>[<?php echo esc_attr( $key ); ?>]" value="1" <?php checked( ! empty( $opts[ $key ] ) ); ?> data-preview-key="<?php echo esc_attr( $key ); ?>" data-preview-checkbox="1">
					<?php echo esc_html( $lbl ); ?>
				</label>
			<?php endforeach; ?>
		</td>
	</tr>
</table>

