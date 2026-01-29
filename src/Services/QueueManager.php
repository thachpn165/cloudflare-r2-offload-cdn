<?php
/**
 * Queue Manager Service class.
 *
 * Centralized queue operations for bulk processing.
 *
 * @package CFR2OffLoad
 */

namespace ThachPN165\CFR2OffLoad\Services;

defined( 'ABSPATH' ) || exit;

use ThachPN165\CFR2OffLoad\Constants\QueueStatus;
use ThachPN165\CFR2OffLoad\Constants\QueueAction;

/**
 * QueueManager class - handles queue operations.
 */
class QueueManager {

	/**
	 * Enqueue an item for processing.
	 *
	 * @param int    $attachment_id Attachment ID.
	 * @param string $action        Action type (offload|restore|delete_local).
	 * @return bool True on success, false on failure.
	 */
	public function enqueue( int $attachment_id, string $action ): bool {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$result = $wpdb->insert(
			$wpdb->prefix . 'cfr2_offload_queue',
			array(
				'attachment_id' => $attachment_id,
				'action'        => $action,
				'status'        => QueueStatus::PENDING,
				'created_at'    => current_time( 'mysql' ),
			),
			array( '%d', '%s', '%s', '%s' )
		);

		return false !== $result;
	}

	/**
	 * Get next pending item for action.
	 *
	 * @param string $action Action type.
	 * @return object|null Queue item or null if none pending.
	 */
	public function get_next_pending( string $action ): ?object {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}cfr2_offload_queue
				 WHERE status = %s AND action = %s
				 ORDER BY created_at ASC
				 LIMIT 1",
				QueueStatus::PENDING,
				$action
			)
		);
	}

	/**
	 * Check if item exists in queue.
	 *
	 * @param int    $attachment_id Attachment ID.
	 * @param string $status        Queue status.
	 * @return bool True if exists, false otherwise.
	 */
	public function item_exists( int $attachment_id, string $status = QueueStatus::PENDING ): bool {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$exists = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$wpdb->prefix}cfr2_offload_queue
				 WHERE attachment_id = %d AND status = %s",
				$attachment_id,
				$status
			)
		);

		return null !== $exists;
	}

	/**
	 * Mark item as processing.
	 *
	 * @param int $item_id Queue item ID.
	 * @return bool True on success, false on failure.
	 */
	public function mark_processing( int $item_id ): bool {
		return $this->update_status( $item_id, QueueStatus::PROCESSING );
	}

	/**
	 * Mark item as completed.
	 *
	 * @param int    $item_id Queue item ID.
	 * @param string $message Optional success message.
	 * @return bool True on success, false on failure.
	 */
	public function mark_completed( int $item_id, string $message = '' ): bool {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->update(
			$wpdb->prefix . 'cfr2_offload_queue',
			array(
				'status'       => QueueStatus::COMPLETED,
				'error_message' => $message,
				'processed_at' => current_time( 'mysql' ),
			),
			array( 'id' => $item_id ),
			array( '%s', '%s', '%s' ),
			array( '%d' )
		);

		return false !== $result;
	}

	/**
	 * Mark item as failed.
	 *
	 * @param int    $item_id       Queue item ID.
	 * @param string $error_message Error message.
	 * @return bool True on success, false on failure.
	 */
	public function mark_failed( int $item_id, string $error_message ): bool {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->update(
			$wpdb->prefix . 'cfr2_offload_queue',
			array(
				'status'        => QueueStatus::FAILED,
				'error_message' => $error_message,
				'processed_at'  => current_time( 'mysql' ),
			),
			array( 'id' => $item_id ),
			array( '%s', '%s', '%s' ),
			array( '%d' )
		);

		return false !== $result;
	}

	/**
	 * Clear old queue items (completed, cancelled, failed).
	 */
	public function clear_old_items(): void {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->prefix}cfr2_offload_queue
				 WHERE status IN (%s, %s, %s)",
				QueueStatus::COMPLETED,
				QueueStatus::CANCELLED,
				QueueStatus::FAILED
			)
		);
	}

	/**
	 * Update queue item status.
	 *
	 * @param int    $item_id Queue item ID.
	 * @param string $status  New status.
	 * @return bool True on success, false on failure.
	 */
	private function update_status( int $item_id, string $status ): bool {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->update(
			$wpdb->prefix . 'cfr2_offload_queue',
			array( 'status' => $status ),
			array( 'id' => $item_id ),
			array( '%s' ),
			array( '%d' )
		);

		return false !== $result;
	}
}
