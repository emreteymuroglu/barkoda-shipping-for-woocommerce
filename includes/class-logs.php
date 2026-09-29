<?php
namespace PTT_Kargo_WC;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange -- this class owns the plugin's log table; every call here is deliberately uncached, since a stale log view would hide the request the user is looking for.

/**
 * Integration log stored in a custom table: no autoload, fast queries, rotated by age.
 *
 *  - record():                adds a row and deletes the oldest ones past the limit
 *  - get_recent():            last N rows, for the admin log screen
 *  - prune_older_than_days(): manual cleanup
 *  - install_table() / drop_table(): activation and uninstall
 */
final class Logs {
	public const TABLE_NAME      = 'ptt_kargo_wc_logs';
	public const RETENTION_LIMIT = 500; // Rows above this count are pruned automatically.

	/**
	 * Masks credentials in a SOAP envelope. Every PTT request carries the password as
	 * plain text in <sifre>/<Sifre>/<password>, so bodies must pass through this before
	 * they are written to the log table or to order meta.
	 */
	public static function mask_sensitive( string $xml ): string {
		if ( $xml === '' ) {
			return $xml;
		}
		$xml = preg_replace( '~(<(?:[\w\-]+:)?[Ss]ifre>)[^<]*(</(?:[\w\-]+:)?[Ss]ifre>)~', '$1***$2', $xml );
		$xml = preg_replace( '~(<(?:[\w\-]+:)?[Pp]assword>)[^<]*(</(?:[\w\-]+:)?[Pp]assword>)~', '$1***$2', (string) $xml );
		return (string) $xml;
	}

	public static function table(): string {
		global $wpdb;
		return $wpdb->prefix . self::TABLE_NAME;
	}

	public static function install_table(): void {
		global $wpdb;
		$table   = self::table();
		$charset = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			created_at DATETIME NOT NULL,
			operation VARCHAR(64) NOT NULL,
			order_id BIGINT UNSIGNED NULL,
			success TINYINT(1) NOT NULL DEFAULT 0,
			message TEXT NULL,
			request LONGTEXT NULL,
			response LONGTEXT NULL,
			PRIMARY KEY (id),
			KEY created_at (created_at),
			KEY order_id (order_id),
			KEY operation (operation)
		) {$charset};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	public static function drop_table(): void {
		global $wpdb;
		$table = self::table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- the table name derives from $wpdb->prefix and cannot be parameterised.
		$wpdb->query( "DROP TABLE IF EXISTS {$table}" );
	}

	public static function record( string $operation, ?int $order_id, bool $success, string $message, string $request = '', string $response = '' ): void {
		global $wpdb;
		$table = self::table();

		// Swallow silently when the table is missing, e.g. installed without running migrations.
		$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
		if ( $exists !== $table ) {
			return;
		}

		$wpdb->insert(
			$table,
			[
				'created_at' => current_time( 'mysql' ),
				'operation'  => substr( $operation, 0, 64 ),
				'order_id'   => $order_id,
				'success'    => $success ? 1 : 0,
				'message'    => $message,
				'request'    => $request,
				'response'   => $response,
			],
			[ '%s', '%s', '%d', '%d', '%s', '%s', '%s' ]
		);

		self::rotate();
	}

	/**
	 * Logs an HTTP call in full: envelope, headers, status, timing and network error, so
	 * that a single row is enough for a post-mortem.
	 *
	 * @param array $req  ['method'=>, 'endpoint'=>, 'headers'=>[], 'body'=>'']
	 * @param array $resp ['code'=>int, 'headers'=>array, 'body'=>'', 'network_error'=>?string, 'wp_error_data'=>?mixed]
	 * @param float $duration_ms
	 */
	public static function record_http(
		string $operation,
		?int $order_id,
		bool $success,
		string $message,
		array $req,
		array $resp,
		float $duration_ms = 0.0
	): void {
		self::record(
			$operation,
			$order_id,
			$success,
			$message,
			self::format_http_request( $req ),
			self::format_http_response( $resp, $duration_ms )
		);
	}

	/**
	 * Logs a notable event that involved no network call: validation error, missing
	 * setting, parse failure and so on.
	 *
	 * @param array $context Serialisable context, written to the request column as JSON.
	 */
	public static function record_event( string $operation, ?int $order_id, bool $success, string $message, array $context = [] ): void {
		$ctx = '';
		if ( ! empty( $context ) ) {
			$ctx = "-- CONTEXT --\n" . wp_json_encode( $context, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
		}
		self::record( $operation, $order_id, $success, $message, $ctx, '' );
	}

	private static function format_http_request( array $req ): string {
		$lines    = [];
		$method   = strtoupper( (string) ( $req['method'] ?? 'POST' ) );
		$endpoint = (string) ( $req['endpoint'] ?? '' );
		if ( $endpoint !== '' ) {
			$lines[] = $method . ' ' . $endpoint;
		}

		$headers = $req['headers'] ?? [];
		if ( is_array( $headers ) ) {
			foreach ( $headers as $k => $v ) {
				$val     = is_array( $v ) ? implode( ', ', $v ) : (string) $v;
				$lines[] = $k . ': ' . $val;
			}
		}
		$lines[] = ''; // Header/body separator.
		if ( isset( $req['body'] ) ) {
			$lines[] = self::mask_sensitive( (string) $req['body'] );
		}
		return implode( "\n", $lines );
	}

	private static function format_http_response( array $resp, float $duration_ms ): string {
		$lines = [];
		if ( isset( $resp['code'] ) ) {
			$lines[] = 'HTTP ' . (int) $resp['code'];
		}
		if ( ! empty( $resp['network_error'] ) ) {
			$lines[] = '[NETWORK ERROR] ' . (string) $resp['network_error'];
		}
		if ( ! empty( $resp['wp_error_data'] ) ) {
			$lines[] = '[WP_Error data] ' . wp_json_encode( $resp['wp_error_data'], JSON_UNESCAPED_UNICODE );
		}
		if ( $duration_ms > 0 ) {
			$lines[] = sprintf( 'Süre: %.0f ms', $duration_ms );
		}

		$rh = $resp['headers'] ?? [];
		if ( ! empty( $rh ) && is_array( $rh ) ) {
			$lines[] = '';
			$lines[] = '-- Response Headers --';
			foreach ( $rh as $k => $v ) {
				$val     = is_array( $v ) ? implode( ', ', $v ) : (string) $v;
				$lines[] = $k . ': ' . $val;
			}
		}

		if ( isset( $resp['body'] ) && (string) $resp['body'] !== '' ) {
			$lines[] = '';
			$lines[] = '-- Response Body --';
			$lines[] = (string) $resp['body'];
		}

		return implode( "\n", $lines );
	}

	private static function rotate(): void {
		global $wpdb;
		$table = self::table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- the table name derives from $wpdb->prefix and cannot be parameterised.
		$count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
		if ( $count <= self::RETENTION_LIMIT ) {
			return;
		}

		$delete_count = $count - self::RETENTION_LIMIT;
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- the table name derives from $wpdb->prefix and cannot be parameterised; every value is bound through prepare().
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$table} ORDER BY id ASC LIMIT %d",
				$delete_count
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
	}

	/**
	 * @return array<int,array<string,mixed>>
	 */
	public static function get_recent( int $limit = 100, ?bool $only_success = null, string $operation = '' ): array {
		global $wpdb;
		$table = self::table();

		$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
		if ( $exists !== $table ) {
			return [];
		}

		$where  = [];
		$params = [];
		if ( $only_success !== null ) {
			$where[]  = 'success = %d';
			$params[] = $only_success ? 1 : 0;
		}
		if ( $operation !== '' ) {
			$where[]  = 'operation = %s';
			$params[] = $operation;
		}
		$where_sql = ! empty( $where ) ? ( 'WHERE ' . implode( ' AND ', $where ) ) : '';

		$params[] = $limit;
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- the table name derives from $wpdb->prefix and cannot be parameterised; every value is bound through prepare().
		$rows     = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} {$where_sql} ORDER BY id DESC LIMIT %d",
				$params
			),
			ARRAY_A
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter

		return is_array( $rows ) ? $rows : [];
	}

	public static function clear(): void {
		global $wpdb;
		$table  = self::table();
		$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
		if ( $exists !== $table ) {
			return;
		}
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange -- the table name comes from $wpdb->prefix and cannot be parameterised.
		$wpdb->query( 'TRUNCATE TABLE `' . esc_sql( $table ) . '`' );
	}

	public static function count(): int {
		global $wpdb;
		$table  = self::table();
		$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
		if ( $exists !== $table ) {
			return 0;
		}
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- the table name derives from $wpdb->prefix and cannot be parameterised.
		$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter

		return $total;
	}
}
