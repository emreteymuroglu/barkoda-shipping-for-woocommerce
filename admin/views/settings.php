<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- view partial included from a class method, so the variables below are function-scoped rather than global.

$settings = \PTT_Kargo_WC\Plugin::instance()->settings();
$opts     = $settings->all();
$opt_key  = \PTT_Kargo_WC\Settings::OPTION_KEY;

$ptt_tabs = [
	'connection' => [
		'label' => __( 'PTT Connection', 'ptt-kargo-for-woocommerce' ),
		'icon'  => 'admin-network',
	],
	'barcode'    => [
		'label' => __( 'Barcode', 'ptt-kargo-for-woocommerce' ),
		'icon'  => 'tickets-alt',
	],
	'sender'     => [
		'label' => __( 'Sender', 'ptt-kargo-for-woocommerce' ),
		'icon'  => 'businessperson',
	],
	'label'      => [
		'label' => __( 'Label', 'ptt-kargo-for-woocommerce' ),
		'icon'  => 'media-document',
	],
	'products'   => [
		'label' => __( 'Products & Filters', 'ptt-kargo-for-woocommerce' ),
		'icon'  => 'filter',
	],
	'defaults'   => [
		'label' => __( 'Shipment Defaults', 'ptt-kargo-for-woocommerce' ),
		'icon'  => 'archive',
	],
	'payment'    => [
		'label' => __( 'Payment', 'ptt-kargo-for-woocommerce' ),
		'icon'  => 'money-alt',
	],
	'customer'   => [
		'label' => __( 'Customer Notices', 'ptt-kargo-for-woocommerce' ),
		'icon'  => 'email-alt',
	],
];

// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- the tab is a read-only view selector.
$requested_tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : '';
$current_tab   = isset( $ptt_tabs[ $requested_tab ] ) ? $requested_tab : 'connection';
$base_url      = admin_url( 'admin.php?page=' . \PTT_Kargo_WC\Admin_Page::MENU_SLUG . '-settings' );

// Preview is shown for these tabs; others are just form fields
$preview_tabs = [ 'connection', 'barcode', 'sender', 'label', 'products', 'defaults', 'payment' ];
$show_preview = in_array( $current_tab, $preview_tabs, true );
?>
<div class="wrap wc-ptt-wrap wc-ptt-settings-wrap">
	<h1><?php esc_html_e( 'PTT Kargo for WooCommerce — Settings', 'ptt-kargo-for-woocommerce' ); ?></h1>
	<?php settings_errors( \PTT_Kargo_WC\Settings::OPTION_KEY ); ?>

	<nav class="nav-tab-wrapper wc-ptt-tab-nav">
		<?php
		foreach ( $ptt_tabs as $slug => $info ) :
			$url = add_query_arg( 'tab', $slug, $base_url );
			$cls = 'nav-tab' . ( $slug === $current_tab ? ' nav-tab-active' : '' );
			?>
			<a href="<?php echo esc_url( $url ); ?>" class="<?php echo esc_attr( $cls ); ?>">
				<span class="dashicons dashicons-<?php echo esc_attr( $info['icon'] ); ?>"></span>
				<?php echo esc_html( $info['label'] ); ?>
			</a>
		<?php endforeach; ?>
	</nav>

	<div class="wc-ptt-settings-grid <?php echo $show_preview ? 'has-preview' : ''; ?>">
		<div class="wc-ptt-settings-main">
			<form id="wc-ptt-settings-form" method="post" action="options.php">
				<?php settings_fields( 'ptt_kargo_wc_settings_group' ); ?>
				<input type="hidden" name="<?php echo esc_attr( $opt_key ); ?>[__tab]" value="<?php echo esc_attr( $current_tab ); ?>">

				<?php
				$tab_file = PTT_KARGO_WC_DIR . 'admin/views/settings/' . $current_tab . '.php';
				if ( file_exists( $tab_file ) ) {
					include $tab_file;
				}
				?>

				<?php submit_button( __( 'Save Settings', 'ptt-kargo-for-woocommerce' ) ); ?>
			</form>
		</div>

		<?php if ( $show_preview ) : ?>
		<aside class="wc-ptt-settings-preview" aria-label="<?php esc_attr_e( 'Label Preview', 'ptt-kargo-for-woocommerce' ); ?>">
			<div class="wc-ptt-preview-card">
				<h3>
					<span class="dashicons dashicons-visibility"></span>
					<?php esc_html_e( 'Live Label Preview', 'ptt-kargo-for-woocommerce' ); ?>
				</h3>
				<p class="description"><?php esc_html_e( 'The preview refreshes automatically as you change the form.', 'ptt-kargo-for-woocommerce' ); ?></p>

				<form id="wc-ptt-preview-form"
					method="post"
					action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"
					target="wc-ptt-preview-iframe"
					style="display:none;">
					<input type="hidden" name="action" value="ptt_kargo_wc_preview">
					<?php wp_nonce_field( 'ptt_kargo_wc_preview' ); ?>
				</form>

				<div class="wc-ptt-preview-frame-wrap">
					<iframe id="wc-ptt-preview-iframe"
						name="wc-ptt-preview-iframe"
						src="about:blank"
						title="<?php esc_attr_e( 'Label preview', 'ptt-kargo-for-woocommerce' ); ?>"></iframe>
				</div>

				<p class="wc-ptt-preview-meta">
					<small><?php esc_html_e( '⚠ Rendered with sample data — the order details are placeholders.', 'ptt-kargo-for-woocommerce' ); ?></small>
				</p>
			</div>
		</aside>
		<?php endif; ?>
	</div>
</div>
