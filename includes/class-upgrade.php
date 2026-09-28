<?php
/**
 * Schema and stored-data migrations.
 *
 * @package PTT_Kargo_WC
 */

namespace PTT_Kargo_WC;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Brings stored data up to the current schema.
 *
 * Every step is idempotent and guards on the destination not already existing, so a
 * partially applied migration (a timeout, a fatal in another plugin) can be re-run
 * safely. The installed version is only stamped once all steps have completed.
 */
final class Upgrade {

	/** Current schema version. Bump when a new migration step is added. */
	public const DB_VERSION = 2;

	/** Option holding the schema version installed on this site. */
	public const VERSION_OPTION = 'ptt_kargo_wc_db_version';

	/** Identifiers used before the plugin was renamed to ptt-kargo-for-woocommerce. */
	private const LEGACY_SETTINGS_OPTION = 'wc_ptt_kargo_settings';
	private const LEGACY_CURSOR_OPTION   = 'wc_ptt_kargo_barcode_cursor';
	private const LEGACY_TABLE           = 'wc_ptt_kargo_logs';
	private const LEGACY_META_PREFIX     = '_wc_ptt_kargo_';
	private const META_PREFIX            = '_ptt_kargo_wc_';

	/**
	 * Runs any pending migration. Cheap enough for every request: one autoloaded
	 * option read when the site is already up to date.
	 */
	public static function maybe_run(): void {
		$installed = (int) get_option( self::VERSION_OPTION, 0 );

		if ( $installed >= self::DB_VERSION ) {
			return;
		}

		// A site that has neither the new nor the legacy option is a fresh install and
		// needs no migration, only the version stamp.
		$is_fresh = false === get_option( Settings::OPTION_KEY, false )
			&& false === get_option( self::LEGACY_SETTINGS_OPTION, false );

		if ( ! $is_fresh && $installed < 2 ) {
			self::migrate_to_2();
		}

		update_option( self::VERSION_OPTION, self::DB_VERSION, false );
	}

	/**
	 * Version 2: rename everything the 2.0.x releases stored under the old plugin slug.
	 *
	 * Covers the two options, the log table, the thirteen order meta keys on both HPOS
	 * and legacy post storage, the non-ASCII `sipariş_durumlari` settings key, and the
	 * encrypted PTT password, which has to be re-encrypted because the AES key is
	 * derived from a salt containing the old slug.
	 */
	private static function migrate_to_2(): void {
		self::rename_option( self::LEGACY_CURSOR_OPTION, Barcode::CURSOR_OPTION );
		self::migrate_settings();
		self::rename_table( self::LEGACY_TABLE, Logs::TABLE_NAME );
		self::rename_order_meta();
	}

	/**
	 * Moves the settings option across, renaming the one non-ASCII key and
	 * re-encrypting the stored password under the new salt.
	 */
	private static function migrate_settings(): void {
		$legacy = get_option( self::LEGACY_SETTINGS_OPTION, false );

		if ( ! is_array( $legacy ) ) {
			return;
		}

		// `sipariş_durumlari` carried a non-ASCII character in an option key, which
		// sanitize_key() strips and which has to be URL-encoded in form field names.
		if ( isset( $legacy['sipariş_durumlari'] ) ) {
			$legacy['order_statuses'] = $legacy['sipariş_durumlari'];
			unset( $legacy['sipariş_durumlari'] );
		}

		// The AES key is derived from ENC_SALT, which changed with the rename, so the
		// password must be read with the old salt and written back under the new one.
		// A failed decrypt leaves the field empty and the store re-enters the password,
		// which is strictly better than persisting a value that can never be read.
		if ( ! empty( $legacy['sifre_enc'] ) ) {
			$plain               = Settings::decrypt( (string) $legacy['sifre_enc'], true );
			$legacy['sifre_enc'] = '' === $plain ? '' : Settings::encrypt( $plain );
		}

		if ( false === get_option( Settings::OPTION_KEY, false ) ) {
			add_option( Settings::OPTION_KEY, $legacy );
		}
		delete_option( self::LEGACY_SETTINGS_OPTION );
	}

	/** Moves an option to a new name, preserving its autoload flag. */
	private static function rename_option( string $from, string $to ): void {
		$value = get_option( $from, null );

		if ( null === $value ) {
			return;
		}
		if ( false === get_option( $to, false ) ) {
			add_option( $to, $value, '', 'no' );
		}
		delete_option( $from );
	}

	/** Renames the log table, keeping its rows. */
	private static function rename_table( string $from, string $to ): void {
		global $wpdb;

		$from_full = $wpdb->prefix . $from;
		$to_full   = $wpdb->prefix . $to;

		$has_from = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $from_full ) ) === $from_full;
		$has_to   = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $to_full ) ) === $to_full;

		if ( ! $has_from ) {
			return;
		}

		if ( $has_to ) {
			// Both exist, which means a previous run was interrupted after creating the
			// new table. The new one is authoritative; drop the orphan.
			$wpdb->query( "DROP TABLE IF EXISTS `{$from_full}`" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name cannot be parameterised.
			return;
		}

		$wpdb->query( "RENAME TABLE `{$from_full}` TO `{$to_full}`" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name cannot be parameterised.
	}

	/**
	 * Renames the order meta keys in place.
	 *
	 * Orders may live in the HPOS tables, in post meta, or in both while a store is
	 * mid-sync, so both are updated. A LIKE on the old prefix means new keys are never
	 * touched, which keeps the step idempotent.
	 */
	private static function rename_order_meta(): void {
		global $wpdb;

		$tables = array( $wpdb->postmeta );

		$hpos = $wpdb->prefix . 'wc_orders_meta';
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $hpos ) ) === $hpos ) {
			$tables[] = $hpos;
		}

		foreach ( $tables as $table ) {
			$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared -- table name is derived from $wpdb, values are prepared.
				$wpdb->prepare(
					"UPDATE `{$table}` SET meta_key = REPLACE(meta_key, %s, %s) WHERE meta_key LIKE %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name cannot be parameterised.
					self::LEGACY_META_PREFIX,
					self::META_PREFIX,
					$wpdb->esc_like( self::LEGACY_META_PREFIX ) . '%'
				)
			);
		}

		// Order objects are cached by WooCommerce; the renamed meta must not be served
		// from a stale cache entry.
		if ( function_exists( 'wp_cache_flush_group' ) ) {
			wp_cache_flush_group( 'orders' );
		}
		wp_cache_flush();
	}
}
