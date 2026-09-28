<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$settings = \PTT_Kargo_WC\Plugin::instance()->settings();
$opts     = $settings->all();
$opt_key  = \PTT_Kargo_WC\Settings::OPTION_KEY;

$tabs = [
	'connection' => [
		'label' => __( 'PTT Bağlantı', 'ptt-kargo-for-woocommerce' ),
		'icon'  => 'admin-network',
	],
	'barcode'    => [
		'label' => __( 'Barkod', 'ptt-kargo-for-woocommerce' ),
		'icon'  => 'tickets-alt',
	],
	'sender'     => [
		'label' => __( 'Gönderici', 'ptt-kargo-for-woocommerce' ),
		'icon'  => 'businessperson',
	],
	'label'      => [
		'label' => __( 'Etiket', 'ptt-kargo-for-woocommerce' ),
		'icon'  => 'media-document',
	],
	'products'   => [
		'label' => __( 'Ürün & Filtreler', 'ptt-kargo-for-woocommerce' ),
		'icon'  => 'filter',
	],
	'defaults'   => [
		'label' => __( 'Gönderi Varsayılanları', 'ptt-kargo-for-woocommerce' ),
		'icon'  => 'archive',
	],
	'payment'    => [
		'label' => __( 'Ödeme', 'ptt-kargo-for-woocommerce' ),
		'icon'  => 'money-alt',
	],
];

$current_tab = isset( $_GET['tab'] ) && isset( $tabs[ $_GET['tab'] ] ) ? sanitize_key( $_GET['tab'] ) : 'connection';
$base_url    = admin_url( 'admin.php?page=' . \PTT_Kargo_WC\Admin_Page::MENU_SLUG . '-settings' );

// Preview is shown for these tabs; others are just form fields
$preview_tabs = [ 'connection', 'barcode', 'sender', 'label', 'products', 'defaults', 'payment' ];
$show_preview = in_array( $current_tab, $preview_tabs, true );
?>
<div class="wrap wc-ptt-wrap wc-ptt-settings-wrap">
	<h1><?php esc_html_e( 'WC PTT Kargo — Ayarlar', 'ptt-kargo-for-woocommerce' ); ?></h1>
	<?php settings_errors( \PTT_Kargo_WC\Settings::OPTION_KEY ); ?>

	<nav class="nav-tab-wrapper wc-ptt-tab-nav">
		<?php
		foreach ( $tabs as $slug => $info ) :
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

				<?php submit_button( __( 'Ayarları Kaydet', 'ptt-kargo-for-woocommerce' ) ); ?>
			</form>
		</div>

		<?php if ( $show_preview ) : ?>
		<aside class="wc-ptt-settings-preview" aria-label="<?php esc_attr_e( 'Etiket Önizleme', 'ptt-kargo-for-woocommerce' ); ?>">
			<div class="wc-ptt-preview-card">
				<h3>
					<span class="dashicons dashicons-visibility"></span>
					<?php esc_html_e( 'Canlı Etiket Önizleme', 'ptt-kargo-for-woocommerce' ); ?>
				</h3>
				<p class="description"><?php esc_html_e( 'Form alanları değiştikçe önizleme otomatik yenilenir.', 'ptt-kargo-for-woocommerce' ); ?></p>

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
						title="<?php esc_attr_e( 'Etiket önizleme', 'ptt-kargo-for-woocommerce' ); ?>"></iframe>
				</div>

				<p class="wc-ptt-preview-meta">
					<small><?php esc_html_e( '⚠ Örnek veriyle render — sipariş bilgileri yer tutucudur.', 'ptt-kargo-for-woocommerce' ); ?></small>
				</p>
			</div>
		</aside>
		<?php endif; ?>
	</div>
</div>
