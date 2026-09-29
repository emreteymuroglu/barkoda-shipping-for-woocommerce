<?php
/**
 * Customer notices settings tab.
 *
 * @package PTT_Kargo_WC
 *
 * @var array  $opts
 * @var string $opt_key
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- view partial included from a class method, so the variables below are function-scoped rather than global.

// Account emails are customer-facing but never about an order, so they cannot
// carry tracking details and are left out of the list.
$ptt_not_orders = array(
	'customer_reset_password',
	'customer_new_account',
	'customer_verify_email',
);

$ptt_emails = array();
if ( function_exists( 'WC' ) && WC()->mailer() ) {
	foreach ( WC()->mailer()->get_emails() as $ptt_email ) {
		// Customer-facing only; admin notifications are not relevant here.
		if ( ! is_object( $ptt_email ) || empty( $ptt_email->id ) ) {
			continue;
		}
		if ( ! method_exists( $ptt_email, 'is_customer_email' ) || ! $ptt_email->is_customer_email() ) {
			continue;
		}
		if ( in_array( $ptt_email->id, $ptt_not_orders, true ) ) {
			continue;
		}
		$ptt_emails[ $ptt_email->id ] = $ptt_email->get_title() ? $ptt_email->get_title() : $ptt_email->id;
	}
}

$ptt_selected = (array) ( $opts['customer_tracking_emails'] ?? array() );
?>

<h2><?php esc_html_e( 'Customer Notices', 'barkoda-shipping-for-woocommerce' ); ?></h2>
<p class="description">
	<?php esc_html_e( 'Show the tracking number and a tracking link to the customer once a shipment has been created. Nothing is shown for orders that have not been sent to PTT.', 'barkoda-shipping-for-woocommerce' ); ?>
</p>

<table class="form-table" role="presentation">
	<tr>
		<th scope="row"><?php esc_html_e( 'Customer Tracking', 'barkoda-shipping-for-woocommerce' ); ?></th>
		<td>
			<label>
				<input type="checkbox" name="<?php echo esc_attr( $opt_key ); ?>[customer_tracking]" value="1" <?php checked( ! empty( $opts['customer_tracking'] ) ); ?>>
				<?php esc_html_e( 'Show tracking details to customers', 'barkoda-shipping-for-woocommerce' ); ?>
			</label>
			<p class="description">
				<?php esc_html_e( 'Turn this off to keep tracking visible only to store administrators.', 'barkoda-shipping-for-woocommerce' ); ?>
			</p>
		</td>
	</tr>

	<tr>
		<th scope="row"><?php esc_html_e( 'Include in Emails', 'barkoda-shipping-for-woocommerce' ); ?></th>
		<td>
			<?php if ( empty( $ptt_emails ) ) : ?>
				<p class="description">
					<?php esc_html_e( 'No WooCommerce customer emails were found.', 'barkoda-shipping-for-woocommerce' ); ?>
				</p>
			<?php else : ?>
				<?php foreach ( $ptt_emails as $ptt_id => $ptt_title ) : ?>
					<label style="display:block;margin-bottom:4px;">
						<input type="checkbox" name="<?php echo esc_attr( $opt_key ); ?>[customer_tracking_emails][]" value="<?php echo esc_attr( $ptt_id ); ?>" <?php checked( in_array( $ptt_id, $ptt_selected, true ) ); ?>>
						<?php echo esc_html( $ptt_title ); ?>
						<code style="font-size:11px;"><?php echo esc_html( $ptt_id ); ?></code>
					</label>
				<?php endforeach; ?>
				<p class="description">
					<?php esc_html_e( 'Tracking is appended to the selected emails. Pick the one your store sends after shipping, which is usually the completed order email.', 'barkoda-shipping-for-woocommerce' ); ?>
				</p>
			<?php endif; ?>
		</td>
	</tr>

	<tr>
		<th scope="row"><?php esc_html_e( 'My Account Page', 'barkoda-shipping-for-woocommerce' ); ?></th>
		<td>
			<label>
				<input type="checkbox" name="<?php echo esc_attr( $opt_key ); ?>[customer_tracking_account]" value="1" <?php checked( ! empty( $opts['customer_tracking_account'] ) ); ?>>
				<?php esc_html_e( 'Show tracking on the customer\'s order detail page', 'barkoda-shipping-for-woocommerce' ); ?>
			</label>
		</td>
	</tr>
</table>

<p class="description">
	<?php esc_html_e( 'Emails are delivered through WordPress, so any SMTP plugin your store uses handles them automatically. No extra configuration is needed.', 'barkoda-shipping-for-woocommerce' ); ?>
</p>
