<?php
/**
 * Database Schema class.
 *
 * @package CFR2OffLoad
 */

namespace ThachPN165\CFR2OffLoad\Database;

defined( 'ABSPATH' ) || exit;

/**
 * Schema class - handles database table creation and upgrades.
 */
class Schema {

	/**
	 * Database version.
	 */
	private const DB_VERSION = '1.0';

	/**
	 * Create database tables.
	 */
	public static function create_tables(): void {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		// Offload status table.
		$sql_status = "CREATE TABLE {$wpdb->prefix}cfr2_offload_status (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			attachment_id BIGINT UNSIGNED NOT NULL,
			r2_key VARCHAR(500) NOT NULL,
			r2_url VARCHAR(500) NOT NULL,
			local_path VARCHAR(500) NOT NULL,
			local_exists TINYINT(1) DEFAULT 1,
			file_size BIGINT UNSIGNED DEFAULT 0,
			offloaded_at DATETIME DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			UNIQUE KEY idx_attachment (attachment_id),
			KEY idx_r2_key (r2_key(191))
		) $charset_collate;";

		// Queue table.
		$sql_queue = "CREATE TABLE {$wpdb->prefix}cfr2_offload_queue (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			attachment_id BIGINT UNSIGNED NOT NULL,
			action ENUM('offload', 'restore', 'delete_local') NOT NULL,
			status ENUM('pending', 'processing', 'completed', 'failed', 'cancelled') DEFAULT 'pending',
			error_message TEXT,
			created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
			processed_at DATETIME,
			PRIMARY KEY (id),
			KEY idx_status (status),
			KEY idx_attachment (attachment_id)
		) $charset_collate;";

		// Stats table.
		$sql_stats = "CREATE TABLE {$wpdb->prefix}cfr2_stats (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			date DATE NOT NULL,
			transformations INT UNSIGNED DEFAULT 0,
			bandwidth_bytes BIGINT UNSIGNED DEFAULT 0,
			PRIMARY KEY (id),
			UNIQUE KEY idx_date (date)
		) $charset_collate;";

		dbDelta( $sql_status );
		dbDelta( $sql_queue );
		dbDelta( $sql_stats );

		update_option( 'cfr2_db_version', self::DB_VERSION );
	}

	/**
	 * Drop database tables.
	 */
	public static function drop_tables(): void {
		global $wpdb;

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange -- Schema management requires direct queries.
		$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}cfr2_offload_status" );
		$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}cfr2_offload_queue" );
		$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}cfr2_stats" );
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange

		delete_option( 'cfr2_db_version' );
	}

	/**
	 * Maybe upgrade database schema.
	 */
	public static function maybe_upgrade(): void {
		$current = get_option( 'cfr2_db_version', '0' );
		if ( version_compare( $current, self::DB_VERSION, '<' ) ) {
			self::create_tables();
		}
	}
}
