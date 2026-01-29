<?php
/**
 * Bulk Progress Service class.
 *
 * Tracks and reports bulk operation progress.
 *
 * @package CFR2OffLoad
 */

namespace ThachPN165\CFR2OffLoad\Services;

defined( 'ABSPATH' ) || exit;

use ThachPN165\CFR2OffLoad\Constants\QueueStatus;
use ThachPN165\CFR2OffLoad\Constants\MetaKeys;

/**
 * BulkProgressService class - handles bulk operation progress tracking.
 */
class BulkProgressService {

	/**
	 * Get bulk operation progress statistics.
	 *
	 * @return array Progress statistics.
	 */
	public function get_progress(): array {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$stats = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT
					SUM(CASE WHEN status = %s THEN 1 ELSE 0 END) as completed,
					SUM(CASE WHEN status = %s THEN 1 ELSE 0 END) as failed,
					SUM(CASE WHEN status = %s THEN 1 ELSE 0 END) as pending,
					SUM(CASE WHEN status = %s THEN 1 ELSE 0 END) as processing,
					COUNT(*) as total
				 FROM {$wpdb->prefix}cfr2_offload_queue
				 WHERE created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)",
				QueueStatus::COMPLETED,
				QueueStatus::FAILED,
				QueueStatus::PENDING,
				QueueStatus::PROCESSING
			)
		);

		// Ensure stats object exists.
		if ( ! $stats ) {
			return array(
				'completed'    => 0,
				'failed'       => 0,
				'pending'      => 0,
				'processing'   => 0,
				'total'        => 0,
				'is_running'   => false,
				'current_file' => '',
			);
		}

		// Get current processing item.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$current_item = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT attachment_id FROM {$wpdb->prefix}cfr2_offload_queue
				 WHERE status = %s
				 ORDER BY created_at ASC
				 LIMIT 1",
				QueueStatus::PROCESSING
			)
		);

		$current_file = '';
		if ( $current_item ) {
			$file_path    = get_attached_file( $current_item->attachment_id );
			$current_file = $file_path ? basename( $file_path ) : '';
		}

		return array(
			'completed'    => (int) ( $stats->completed ?? 0 ),
			'failed'       => (int) ( $stats->failed ?? 0 ),
			'pending'      => (int) ( $stats->pending ?? 0 ),
			'processing'   => (int) ( $stats->processing ?? 0 ),
			'total'        => (int) ( $stats->total ?? 0 ),
			'is_running'   => ( $stats->pending ?? 0 ) > 0 || ( $stats->processing ?? 0 ) > 0,
			'current_file' => $current_file,
		);
	}

	/**
	 * Get bulk button counts.
	 *
	 * @return array Button counts (offloaded, not_offloaded, disk_saveable, pending).
	 */
	public function get_counts(): array {
		global $wpdb;

		// Count total attachments.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$total_attachments = (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$wpdb->posts}
			 WHERE post_type = 'attachment' AND post_status = 'inherit'"
		);

		// Count offloaded attachments.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$offloaded_count = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->postmeta}
				 WHERE meta_key = %s AND meta_value = '1'",
				MetaKeys::OFFLOADED
			)
		);

		$not_offloaded_count = max( 0, $total_attachments - $offloaded_count );

		// Count offloaded with local files (disk saveable).
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$disk_saveable_count = (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$wpdb->prefix}cfr2_offload_status os
			 INNER JOIN {$wpdb->posts} p ON os.attachment_id = p.ID
			 WHERE os.local_exists = 1 AND p.post_type = 'attachment'"
		);

		// Count pending items in queue.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$pending_count = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(DISTINCT attachment_id) FROM {$wpdb->prefix}cfr2_offload_queue
				 WHERE status IN (%s, %s)",
				QueueStatus::PENDING,
				QueueStatus::PROCESSING
			)
		);

		return array(
			'offloaded'     => $offloaded_count,
			'not_offloaded' => $not_offloaded_count,
			'disk_saveable' => $disk_saveable_count,
			'pending'       => $pending_count,
		);
	}

	/**
	 * Get pending items list.
	 *
	 * @param int $limit Maximum number of items to retrieve.
	 * @return array Pending items array.
	 */
	public function get_pending_items( int $limit = 100 ): array {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$items = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT q.id, q.attachment_id, q.action, q.status, q.created_at,
				        p.post_title
				 FROM {$wpdb->prefix}cfr2_offload_queue q
				 LEFT JOIN {$wpdb->posts} p ON q.attachment_id = p.ID
				 WHERE q.status IN (%s, %s)
				 ORDER BY q.created_at ASC
				 LIMIT %d",
				QueueStatus::PENDING,
				QueueStatus::PROCESSING,
				$limit
			)
		);

		$pending_items = array();
		foreach ( $items as $item ) {
			$file_path = get_attached_file( $item->attachment_id );
			$filename  = $file_path ? basename( $file_path ) : $item->post_title;

			$pending_items[] = array(
				'id'            => (int) $item->id,
				'attachment_id' => (int) $item->attachment_id,
				'filename'      => $filename ?: "ID: {$item->attachment_id}",
				'action'        => $item->action,
				'status'        => $item->status,
				'created_at'    => $item->created_at,
			);
		}

		return $pending_items;
	}
}
