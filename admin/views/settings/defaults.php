<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/** @var array  $opts */
/** @var string $opt_key */
?>
<h2><?php esc_html_e( 'Shipment Defaults', 'ptt-kargo-for-woocommerce' ); ?></h2>
<p class="description"><?php esc_html_e( 'PTT requires a weight and volumetric weight on every shipment. The sources below control the auto-compute behaviour; you can override them per order in the Ship popup.', 'ptt-kargo-for-woocommerce' ); ?></p>

<h3><?php esc_html_e( 'Weight / Volumetric Weight Source', 'ptt-kargo-for-woocommerce' ); ?></h3>
<table class="form-table" role="presentation">
	<tr>
		<th><label for="weight_source"><?php esc_html_e( 'Weight Source', 'ptt-kargo-for-woocommerce' ); ?></label></th>
		<td>
			<?php $ws = (string) ( $opts['weight_source'] ?? 'static' ); ?>
			<select id="weight_source" name="<?php echo esc_attr( $opt_key ); ?>[weight_source]">
				<option value="static" <?php selected( $ws, 'static' ); ?>><?php esc_html_e( 'Use a fixed default', 'ptt-kargo-for-woocommerce' ); ?></option>
				<option value="wc_product" <?php selected( $ws, 'wc_product' ); ?>><?php esc_html_e( 'Calculate from WooCommerce product weight (Σ weight × quantity)', 'ptt-kargo-for-woocommerce' ); ?></option>
				<option value="wc_product_fallback" <?php selected( $ws, 'wc_product_fallback' ); ?>><?php esc_html_e( 'Calculate from product weight, falling back to the fixed default', 'ptt-kargo-for-woocommerce' ); ?></option>
			</select>
			<p class="description">
				<?php esc_html_e( 'The WooCommerce weight unit is converted to grams automatically (g/kg/lbs/oz supported). Current unit:', 'ptt-kargo-for-woocommerce' ); ?>
				<code><?php echo esc_html( get_option( 'woocommerce_weight_unit', 'kg' ) ); ?></code>
			</p>
		</td>
	</tr>
	<tr>
		<th><label for="varsayilan_agirlik"><?php esc_html_e( 'Fixed Weight (grams)', 'ptt-kargo-for-woocommerce' ); ?></label></th>
		<td>
			<input type="number" id="varsayilan_agirlik" name="<?php echo esc_attr( $opt_key ); ?>[varsayilan_agirlik]" value="<?php echo esc_attr( $opts['varsayilan_agirlik'] ); ?>" min="1" class="small-text">
			<span class="description"><?php esc_html_e( 'grams', 'ptt-kargo-for-woocommerce' ); ?></span>
			<p class="description"><?php esc_html_e( 'When "Use a fixed default" is selected above, every shipment uses this value. It is also the fallback in fallback mode.', 'ptt-kargo-for-woocommerce' ); ?></p>
		</td>
	</tr>
	<tr>
		<th><label for="dimensions_source"><?php esc_html_e( 'Dimension Source', 'ptt-kargo-for-woocommerce' ); ?></label></th>
		<td>
			<?php $ds = (string) ( $opts['dimensions_source'] ?? 'static' ); ?>
			<select id="dimensions_source" name="<?php echo esc_attr( $opt_key ); ?>[dimensions_source]">
				<option value="static" <?php selected( $ds, 'static' ); ?>><?php esc_html_e( 'Do not send dimensions (volumetric weight only)', 'ptt-kargo-for-woocommerce' ); ?></option>
				<option value="wc_product" <?php selected( $ds, 'wc_product' ); ?>><?php esc_html_e( 'Calculate from WooCommerce product dimensions (maximum per axis)', 'ptt-kargo-for-woocommerce' ); ?></option>
			</select>
			<p class="description">
				<?php esc_html_e( 'With product dimensions selected, the maximum width/length/height across all line items is used, assuming a single package. The volumetric weight formula is width × length × height / 3000.', 'ptt-kargo-for-woocommerce' ); ?>
				<br>
				<?php esc_html_e( 'Current WooCommerce dimension unit:', 'ptt-kargo-for-woocommerce' ); ?>
				<code><?php echo esc_html( get_option( 'woocommerce_dimension_unit', 'cm' ) ); ?></code>
			</p>
		</td>
	</tr>
	<tr>
		<th><label for="varsayilan_desi"><?php esc_html_e( 'Fixed Volumetric Weight', 'ptt-kargo-for-woocommerce' ); ?></label></th>
		<td>
			<input type="number" id="varsayilan_desi" name="<?php echo esc_attr( $opt_key ); ?>[varsayilan_desi]" value="<?php echo esc_attr( $opts['varsayilan_desi'] ); ?>" min="1" class="small-text">
			<p class="description"><?php esc_html_e( 'Used when the dimension source is set to not send dimensions. Start with 1 and adjust to your package volume.', 'ptt-kargo-for-woocommerce' ); ?></p>
		</td>
	</tr>
	<tr>
		<th><label for="ekhizmet"><?php esc_html_e( 'Additional Service Code', 'ptt-kargo-for-woocommerce' ); ?></label></th>
		<td>
			<input type="text" id="ekhizmet" name="<?php echo esc_attr( $opt_key ); ?>[ekhizmet]" value="<?php echo esc_attr( $opts['ekhizmet'] ); ?>" class="regular-text" placeholder="<?php esc_attr_e( 'Can be left empty', 'ptt-kargo-for-woocommerce' ); ?>" pattern="[A-Za-z]*" maxlength="40">
			<p class="description">
				<?php esc_html_e( 'PTT service codes are uppercase and alphabetical (e.g. DK = Valuable Goods). Several codes can be combined, such as DKUA. When cash on delivery is active, OS is added automatically and does not need to be entered here.', 'ptt-kargo-for-woocommerce' ); ?>
			</p>
		</td>
	</tr>
</table>

<div class="wc-ptt-tip">
	💡 <?php esc_html_e( 'If a product has no weight, fill in Product → General → Weight. The weight unit can be changed under WooCommerce > Settings > Products > Measurements.', 'ptt-kargo-for-woocommerce' ); ?>
</div>
