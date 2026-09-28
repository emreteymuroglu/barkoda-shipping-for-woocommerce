<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/** @var array  $opts */
/** @var string $opt_key */
?>
<h2><?php esc_html_e( 'Label Appearance', 'ptt-kargo-for-woocommerce' ); ?></h2>
<p class="description"><?php esc_html_e( 'Controls the label text and which blocks appear. The live preview on the right updates as you make changes.', 'ptt-kargo-for-woocommerce' ); ?></p>

<h3><?php esc_html_e( 'Branding Block (Top)', 'ptt-kargo-for-woocommerce' ); ?></h3>
<table class="form-table" role="presentation">
	<tr>
		<th><label for="label_logo_url"><?php esc_html_e( 'Logo', 'ptt-kargo-for-woocommerce' ); ?></label></th>
		<td>
			<div class="wc-ptt-logo-picker">
				<input type="text" id="label_logo_url" name="<?php echo esc_attr( $opt_key ); ?>[label_logo_url]" value="<?php echo esc_attr( $opts['label_logo_url'] ); ?>" class="large-text" placeholder="https://..." data-preview-key="label_logo_url">
				<button type="button" class="button" id="wc-ptt-logo-pick"><?php esc_html_e( 'Select Image', 'ptt-kargo-for-woocommerce' ); ?></button>
				<button type="button" class="button" id="wc-ptt-logo-clear"><?php esc_html_e( 'Remove', 'ptt-kargo-for-woocommerce' ); ?></button>
			</div>
			<div class="wc-ptt-logo-preview" id="wc-ptt-logo-preview" style="<?php echo $opts['label_logo_url'] === '' ? 'display:none;' : ''; ?>">
				<?php if ( $opts['label_logo_url'] !== '' ) : ?>
					<img src="<?php echo esc_url( $opts['label_logo_url'] ); ?>" alt="">
				<?php endif; ?>
			</div>
			<p class="description"><?php esc_html_e( 'Pick from the WordPress Media Library or enter a URL directly. It appears at roughly 60mm × 18mm on the label.', 'ptt-kargo-for-woocommerce' ); ?></p>
		</td>
	</tr>
	<tr>
		<th><label for="label_header_title"><?php esc_html_e( 'Title', 'ptt-kargo-for-woocommerce' ); ?></label></th>
		<td>
			<input type="text" id="label_header_title" name="<?php echo esc_attr( $opt_key ); ?>[label_header_title]" value="<?php echo esc_attr( $opts['label_header_title'] ); ?>" class="large-text" data-preview-key="label_header_title">
			<p class="description"><?php esc_html_e( 'The heading printed at the top of the label, in uppercase. If empty, the sender name is used; if that is also empty, the site name.', 'ptt-kargo-for-woocommerce' ); ?></p>
		</td>
	</tr>
	<tr>
		<th><label for="label_header_subtitle"><?php esc_html_e( 'Subtitle', 'ptt-kargo-for-woocommerce' ); ?></label></th>
		<td>
			<input type="text" id="label_header_subtitle" name="<?php echo esc_attr( $opt_key ); ?>[label_header_subtitle]" value="<?php echo esc_attr( $opts['label_header_subtitle'] ); ?>" class="large-text" data-preview-key="label_header_subtitle">
			<p class="description"><?php esc_html_e( 'Small text printed under the title. Can be left empty.', 'ptt-kargo-for-woocommerce' ); ?></p>
		</td>
	</tr>
</table>

<h3><?php esc_html_e( 'Label Sections', 'ptt-kargo-for-woocommerce' ); ?></h3>
<p class="description"><?php esc_html_e( 'Tick the sections you want on the label. Section headings are hidden automatically when empty.', 'ptt-kargo-for-woocommerce' ); ?></p>
<table class="form-table" role="presentation">
	<tr>
		<th><?php esc_html_e( 'Visible Blocks', 'ptt-kargo-for-woocommerce' ); ?></th>
		<td>
			<?php
			$blocks = [
				'label_show_order'     => __( 'Order Number + Date', 'ptt-kargo-for-woocommerce' ),
				'label_show_recipient' => __( 'Recipient Details', 'ptt-kargo-for-woocommerce' ),
				'label_show_products'  => __( 'Product List', 'ptt-kargo-for-woocommerce' ),
				'label_show_barcode'   => __( 'Barcode', 'ptt-kargo-for-woocommerce' ),
				'label_show_sender'    => __( 'Sender Block (Bottom)', 'ptt-kargo-for-woocommerce' ),
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

