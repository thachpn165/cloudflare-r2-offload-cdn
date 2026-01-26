<?php
/**
 * Bulk Operation Logger class.
 *
 * @package CFR2OffLoad
 */

namespace ThachPN165\CFR2OffLoad\Services;

defined( 'ABSPATH' ) || exit;

/**
 * BulkOperationLogger class - logs bulk operation activities.
 */
class BulkOperationLogger {

	/**
	 * Option key for storing logs.
	 */
	private const OPTION_KEY = 'cfr2_bulk_operation_logs';

	/**
	 * Maximum number of logs to store.
	 */
	private const MAX_LOGS = 100;

	/**
	 * Log an operation.
	 *
	 * @param int    $attachment_id Attachment ID.
	 * @param string $status        Status (success|error).
	 * @param string $message       Log message.
	 */
	public static function log( int $attachment_id, string $status, string $message ): void {
		$logs = self::get_all_logs();

		// Get attachment filename.
		$filename = basename( get_attached_file( $attachment_id ) );

		// Add new log entry.
		$logs[] = array(
			'timestamp'     => current_time( 'timestamp' ),
			'attachment_id' => $attachment_id,
			'filename'      => $filename,
			'status'        => $status,
			'message'       => $message,
		);

		// Keep only last MAX_LOGS entries.
		if ( count( $logs ) > self::MAX_LOGS ) {
			$logs = array_slice( $logs, -self::MAX_LOGS );
		}

		update_option( self::OPTION_KEY, $logs, false );
	}

	/**
	 * Get recent logs.
	 *
	 * @param int $limit Number of logs to retrieve.
	 * @return array Log entries.
	 */
	public static function get_logs( int $limit = 20 ): array {
		$logs = self::get_all_logs();

		// Return most recent first.
		$logs = array_reverse( $logs );

		return array_slice( $logs, 0, $limit );
	}

	/**
	 * Get failed logs.
	 *
	 * @return array Failed log entries.
	 */
	public static function get_failed(): array {
		$logs = self::get_all_logs();

		return array_filter(
			$logs,
			function ( $log ) {
				return 'error' === $log['status'];
			}
		);
	}

	/**
	 * Clear all logs.
	 */
	public static function clear(): void {
		delete_option( self::OPTION_KEY );
	}

	/**
	 * Get log summary.
	 *
	 * @return array Summary with counts by status.
	 */
	public static function get_summary(): array {
		$logs = self::get_all_logs();

		$summary = array(
			'total'   => count( $logs ),
			'success' => 0,
			'error'   => 0,
		);

		foreach ( $logs as $log ) {
			if ( 'success' === $log['status'] ) {
				++$summary['success'];
			} elseif ( 'error' === $log['status'] ) {
				++$summary['error'];
			}
		}

		return $summary;
	}

	/**
	 * Get all logs from database.
	 *
	 * @return array All log entries.
	 */
	private static function get_all_logs(): array {
		$logs = get_option( self::OPTION_KEY, array() );

		if ( ! is_array( $logs ) ) {
			return array();
		}

		return $logs;
	}
}
