<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- view partial included from a class method, so the variables below are function-scoped rather than global.
/** @var array  $opts */
/** @var string $opt_key */

$cursor_row = get_option( \Barkoda_Shipping\Barcode::CURSOR_OPTION );
?>
<h2><?php esc_html_e( 'Barcode Range and Reference', 'barkoda-shipping-for-woocommerce' ); ?></h2>
<p class="description"><?php esc_html_e( 'PTT allocates you a 13-digit barcode range. The first 8 digits are a fixed prefix, the next 4 are a sequential serial number, and the last digit is an automatically calculated check digit.', 'barkoda-shipping-for-woocommerce' ); ?></p>

<table class="form-table" role="presentation">
	<tr>
		<th><label for="barkod_prefix"><?php esc_html_e( 'Barcode Prefix (8 digits)', 'barkoda-shipping-for-woocommerce' ); ?></label></th>
		<td>
			<input type="text" id="barkod_prefix" name="<?php echo esc_attr( $opt_key ); ?>[barkod_prefix]" value="<?php echo esc_attr( $opts['barkod_prefix'] ); ?>" class="regular-text" pattern="\d{8}" placeholder="27918802" data-preview-key="barkod_prefix">
			<p class="description"><?php esc_html_e( 'The fixed 8-digit prefix stated in your PTT integration letter.', 'barkoda-shipping-for-woocommerce' ); ?></p>
		</td>
	</tr>
	<tr>
		<th><label><?php esc_html_e( 'Serial Number Range', 'barkoda-shipping-for-woocommerce' ); ?></label></th>
		<td>
			<input type="text" name="<?php echo esc_attr( $opt_key ); ?>[barkod_range_start]" value="<?php echo esc_attr( $opts['barkod_range_start'] ); ?>" class="small-text" pattern="\d+" placeholder="0000">
			→
			<input type="text" name="<?php echo esc_attr( $opt_key ); ?>[barkod_range_end]" value="<?php echo esc_attr( $opts['barkod_range_end'] ); ?>" class="small-text" pattern="\d+" placeholder="9999">
			<p class="description">
				<?php esc_html_e( 'The start and end serial numbers PTT allocated to you. Prefix + serial = 12 digits.', 'barkoda-shipping-for-woocommerce' ); ?>
				<?php if ( $cursor_row !== false ) : ?>
					<br><strong><?php esc_html_e( 'Next serial to be used:', 'barkoda-shipping-for-woocommerce' ); ?></strong> <?php echo esc_html( $cursor_row ); ?>
					<?php
					$end       = (int) $opts['barkod_range_end'];
					$remaining = max( 0, $end - (int) $cursor_row + 1 );
					/* translators: %d: barcodes left in the allocated range */
					echo ' <em>(' . esc_html( sprintf( __( '%d remaining', 'barkoda-shipping-for-woocommerce' ), $remaining ) ) . ')</em>';
					?>
				<?php endif; ?>
			</p>
		</td>
	</tr>
	<tr>
		<th><label for="referans_prefix"><?php esc_html_e( 'Customer Reference Prefix', 'barkoda-shipping-for-woocommerce' ); ?></label></th>
		<td>
			<input type="text" id="referans_prefix" name="<?php echo esc_attr( $opt_key ); ?>[referans_prefix]" value="<?php echo esc_attr( $opts['referans_prefix'] ); ?>" class="regular-text" placeholder="SHOP-">
			<p class="description"><?php esc_html_e( 'A prefix added before the order ID. For example SHOP- sends SHOP-12345 to PTT. Can be left empty.', 'barkoda-shipping-for-woocommerce' ); ?></p>
		</td>
	</tr>
</table>

<div class="wc-ptt-tip">
	💡 <strong><?php esc_html_e( 'Example:', 'barkoda-shipping-for-woocommerce' ); ?></strong>
	<?php esc_html_e( 'Prefix "27918802" + serial "0001" → 12 digits "279188020001" + check digit (automatic) → 13 digits "2791880200017".', 'barkoda-shipping-for-woocommerce' ); ?>
</div>
