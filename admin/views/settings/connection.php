<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- view partial included from a class method, so the variables below are function-scoped rather than global.
/** @var array  $opts */
/** @var string $opt_key */
?>
<h2><?php esc_html_e( 'PTT Connection Details', 'barkoda-shipping-for-woocommerce' ); ?></h2>
<p class="description"><?php esc_html_e( 'Enter the customer number and password PTT issued for the integration service. Verify against the Test environment first.', 'barkoda-shipping-for-woocommerce' ); ?></p>

<table class="form-table" role="presentation">
	<tr>
		<th><label for="environment"><?php esc_html_e( 'Environment', 'barkoda-shipping-for-woocommerce' ); ?></label></th>
		<td>
			<select name="<?php echo esc_attr( $opt_key ); ?>[environment]" id="environment" data-preview-key="environment">
				<option value="test" <?php selected( $opts['environment'], 'test' ); ?>><?php esc_html_e( 'Test', 'barkoda-shipping-for-woocommerce' ); ?></option>
				<option value="prod" <?php selected( $opts['environment'], 'prod' ); ?>><?php esc_html_e( 'Live (Production)', 'barkoda-shipping-for-woocommerce' ); ?></option>
			</select>
			<p class="description"><?php esc_html_e( 'Switch to Live after PTT approves your integration.', 'barkoda-shipping-for-woocommerce' ); ?></p>
		</td>
	</tr>
	<tr>
		<th><label for="musteri_id"><?php esc_html_e( 'Customer Number', 'barkoda-shipping-for-woocommerce' ); ?></label></th>
		<td>
			<input type="text" id="musteri_id" name="<?php echo esc_attr( $opt_key ); ?>[musteri_id]" value="<?php echo esc_attr( $opts['musteri_id'] ); ?>" class="regular-text" required>
			<p class="description"><?php esc_html_e( 'The numeric customer ID PTT issued when approving your integration.', 'barkoda-shipping-for-woocommerce' ); ?></p>
		</td>
	</tr>
	<tr>
		<th><label for="sifre"><?php esc_html_e( 'Password', 'barkoda-shipping-for-woocommerce' ); ?></label></th>
		<td>
			<input type="password" id="sifre" name="<?php echo esc_attr( $opt_key ); ?>[sifre]" value="" autocomplete="new-password" class="regular-text" placeholder="<?php echo $opts['sifre_enc'] !== '' ? esc_attr__( 'Enter a new password to change it', 'barkoda-shipping-for-woocommerce' ) : ''; ?>">
			<?php if ( $opts['sifre_enc'] !== '' ) : ?>
				<p class="description">✓ <?php esc_html_e( 'A password is stored (AES-256 encrypted). Leave empty to keep it unchanged.', 'barkoda-shipping-for-woocommerce' ); ?></p>
			<?php else : ?>
				<p class="description"><?php esc_html_e( 'Stored AES-256 encrypted, keyed from the WordPress AUTH_KEY.', 'barkoda-shipping-for-woocommerce' ); ?></p>
			<?php endif; ?>
		</td>
	</tr>
	<tr>
		<th><?php esc_html_e( 'Connection Test', 'barkoda-shipping-for-woocommerce' ); ?></th>
		<td>
			<button type="button" class="button button-secondary" id="wc-ptt-test-conn-btn">
				<span class="dashicons dashicons-update" style="vertical-align:middle;"></span>
				<?php esc_html_e( 'Test Connection', 'barkoda-shipping-for-woocommerce' ); ?>
			</button>
			<div class="wc-ptt-test-result" id="wc-ptt-test-result" style="display:none;"></div>
			<p class="description"><?php esc_html_e( 'Sends a sample query to the PTT services using the details above. Works before saving too.', 'barkoda-shipping-for-woocommerce' ); ?></p>
		</td>
	</tr>
</table>
