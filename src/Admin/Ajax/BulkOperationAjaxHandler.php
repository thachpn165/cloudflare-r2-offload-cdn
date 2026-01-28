<?php
/**
 * Bulk Operation AJAX Handler class.
 *
 * Handles AJAX requests for bulk offload/restore operations.
 *
 * @package CFR2OffLoad
 */

namespace ThachPN165\CFR2OffLoad\Admin\Ajax;

defined( 'ABSPATH' ) || exit;

use ThachPN165\CFR2OffLoad\Constants\NonceActions;
use ThachPN165\CFR2OffLoad\Constants\Settings;
use ThachPN165\CFR2OffLoad\Constants\MetaKeys;
use ThachPN165\CFR2OffLoad\Constants\TransientKeys;
use ThachPN165\CFR2OffLoad\Constants\CacheDuration;
use ThachPN165\CFR2OffLoad\Constants\QueueStatus;
use ThachPN165\CFR2OffLoad\Constants\QueueAction;
use ThachPN165\CFR2OffLoad\Services\EncryptionService;
use ThachPN165\CFR2OffLoad\Services\R2Client;
use ThachPN165\CFR2OffLoad\Services\OffloadService;
use ThachPN165\CFR2OffLoad\Services\BulkOperationLogger;

/**
 * BulkOperationAjaxHandler class - handles bulk operation AJAX requests.
 */
class BulkOperationAjaxHandler {

	/**
	 * Register AJAX hooks.
	 */
	public function register_hooks(): void {
		add_action( 'wp_ajax_cfr2_bulk_offload_all', array( $this, 'ajax_bulk_offload_all' ) );
		add_action( 'wp_ajax_cfr2_bulk_restore_all', array( $this, 'ajax_bulk_restore_all' ) );
		add_action( 'wp_ajax_cfr2_bulk_delete_local', array( $this, 'ajax_bulk_delete_local' ) );
		add_action( 'wp_ajax_cfr2_process_bulk_item', array( $this, 'ajax_process_bulk_item' ) );
		add_action( 'wp_ajax_cfr2_process_restore_item', array( $this, 'ajax_process_restore_item' ) );
		add_action( 'wp_ajax_cfr2_process_delete_local_item', array( $this, 'ajax_process_delete_local_item' ) );
		add_action( 'wp_ajax_cfr2_cancel_bulk', array( $this, 'ajax_cancel_bulk' ) );
		add_action( 'wp_ajax_cfr2_get_bulk_progress', array( $this, 'ajax_get_bulk_progress' ) );
		add_action( 'wp_ajax_cfr2_get_bulk_counts', array( $this, 'ajax_get_bulk_counts' ) );
	}

	/**
	 * Verify nonce for bulk operations.
	 *
	 * @return bool True if valid, sends error response otherwise.
	 */
	private function verify_bulk_nonce(): bool {
		// Support both legacy and new nonces during transition.
		$nonce_valid = check_ajax_referer( NonceActions::LEGACY, 'nonce', false );
		if ( false === $nonce_valid ) {
			$nonce_valid = check_ajax_referer( NonceActions::BULK, 'cfr2_nonce', false );
		}

		if ( false === $nonce_valid ) {
			wp_send_json_error(
				array( 'message' => __( 'Security check failed.', 'cloudflare-r2-offload-cdn' ) ),
				403
			);
			return false;
		}

		return true;
	}

	/**
	 * Check user permissions for bulk operations.
	 *
	 * @return bool True if authorized, sends error response otherwise.
	 */
	private function check_permissions(): bool {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'cloudflare-r2-offload-cdn' ) ), 403 );
			return false;
		}
		return true;
	}

	/**
	 * Get R2 credentials from settings.
	 *
	 * @return array|false Credentials array or false if not configured.
	 */
	private function get_r2_credentials() {
		$settings   = get_option( Settings::OPTION_KEY, array() );
		$encryption = new EncryptionService();
		$secret_key = $encryption->decrypt( $settings['r2_secret_access_key'] ?? '' );

		if ( empty( $secret_key ) ) {
			return false;
		}

		return array(
			'account_id'        => $settings['r2_account_id'] ?? '',
			'access_key_id'     => $settings['r2_access_key_id'] ?? '',
			'secret_access_key' => $secret_key,
			'bucket'            => $settings['r2_bucket'] ?? '',
		);
	}

	/**
	 * Clear old queue items (completed, cancelled, failed) from all actions.
	 */
	private function clear_old_queue_items(): void {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom queue table cleanup.
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->prefix}cfr2_offload_queue WHERE status IN (%s, %s, %s)",
				QueueStatus::COMPLETED,
				QueueStatus::CANCELLED,
				QueueStatus::FAILED
			)
		);
	}

	/**
	 * AJAX handler for bulk offload all.
	 * Queues items for AJAX-based processing.
	 */
	public function ajax_bulk_offload_all(): void {
		$this->verify_bulk_nonce();
		$this->check_permissions();

		global $wpdb;

		// Clear old queue items from all actions.
		$this->clear_old_queue_items();

		// Get all non-offloaded attachments.
		// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Required to find non-offloaded attachments.
		$attachments = get_posts(
			array(
				'post_type'      => 'attachment',
				'post_status'    => 'inherit',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'meta_query'     => array(
					array(
						'key'     => MetaKeys::OFFLOADED,
						'compare' => 'NOT EXISTS',
					),
				),
			)
		);

		$queued = 0;
		foreach ( $attachments as $attachment_id ) {
			// Check if already in queue.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Queue check.
			$exists = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT id FROM {$wpdb->prefix}cfr2_offload_queue WHERE attachment_id = %d AND status = %s",
					$attachment_id,
					QueueStatus::PENDING
				)
			);

			if ( ! $exists ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Custom queue table.
				$wpdb->insert(
					$wpdb->prefix . 'cfr2_offload_queue',
					array(
						'attachment_id' => $attachment_id,
						'action'        => QueueAction::OFFLOAD,
						'status'        => QueueStatus::PENDING,
						'created_at'    => current_time( 'mysql' ),
					),
					array( '%d', '%s', '%s', '%s' )
				);
				++$queued;
			}
		}

		// Clear any cancellation flag.
		delete_transient( TransientKeys::BULK_CANCELLED );

		wp_send_json_success(
			array(
				'message' => sprintf(
					/* translators: %d: number of files */
					__( '%d files queued for offload.', 'cloudflare-r2-offload-cdn' ),
					$queued
				),
				'total'   => $queued,
			)
		);
	}

	/**
	 * AJAX handler for bulk restore all.
	 * Queues offloaded items for restore to local.
	 */
	public function ajax_bulk_restore_all(): void {
		$this->verify_bulk_nonce();
		$this->check_permissions();

		global $wpdb;

		// Clear old queue items from all actions.
		$this->clear_old_queue_items();

		// Get all offloaded attachments.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Aggregating postmeta.
		$attachments = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT DISTINCT post_id FROM {$wpdb->postmeta}
				 WHERE meta_key = %s AND meta_value = '1'",
				MetaKeys::OFFLOADED
			)
		);

		$queued = 0;
		foreach ( $attachments as $attachment_id ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Custom queue table.
			$wpdb->insert(
				$wpdb->prefix . 'cfr2_offload_queue',
				array(
					'attachment_id' => $attachment_id,
					'action'        => QueueAction::RESTORE,
					'status'        => QueueStatus::PENDING,
					'created_at'    => current_time( 'mysql' ),
				),
				array( '%d', '%s', '%s', '%s' )
			);
			++$queued;
		}

		// Clear any cancellation flag.
		delete_transient( TransientKeys::BULK_CANCELLED );

		wp_send_json_success(
			array(
				'message' => sprintf(
					/* translators: %d: number of files */
					__( '%d files queued for restore.', 'cloudflare-r2-offload-cdn' ),
					$queued
				),
				'total'   => $queued,
			)
		);
	}

	/**
	 * AJAX handler for bulk delete local files (disk saving).
	 * Queues offloaded items with local copies for local file deletion.
	 */
	public function ajax_bulk_delete_local(): void {
		$this->verify_bulk_nonce();
		$this->check_permissions();

		global $wpdb;

		// Clear old queue items from all actions.
		$this->clear_old_queue_items();

		// Get all offloaded attachments with local copies (local_exists = 1).
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom status table.
		$attachments = $wpdb->get_col(
			"SELECT attachment_id FROM {$wpdb->prefix}cfr2_offload_status
			 WHERE local_exists = 1"
		);

		$queued = 0;
		foreach ( $attachments as $attachment_id ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Custom queue table.
			$wpdb->insert(
				$wpdb->prefix . 'cfr2_offload_queue',
				array(
					'attachment_id' => $attachment_id,
					'action'        => QueueAction::DELETE_LOCAL,
					'status'        => QueueStatus::PENDING,
					'created_at'    => current_time( 'mysql' ),
				),
				array( '%d', '%s', '%s', '%s' )
			);
			++$queued;
		}

		// Clear any cancellation flag.
		delete_transient( TransientKeys::BULK_CANCELLED );

		wp_send_json_success(
			array(
				'message' => sprintf(
					/* translators: %d: number of files */
					__( '%d files queued for local deletion.', 'cloudflare-r2-offload-cdn' ),
					$queued
				),
				'total'   => $queued,
			)
		);
	}

	/**
	 * AJAX handler for process single delete local item.
	 * Called repeatedly by JavaScript to delete local files one by one.
	 */
	public function ajax_process_delete_local_item(): void {
		$this->verify_bulk_nonce();
		$this->check_permissions();

		// Check if cancelled.
		if ( get_transient( TransientKeys::BULK_CANCELLED ) ) {
			wp_send_json_success(
				array(
					'done'    => true,
					'message' => __( 'Bulk delete cancelled.', 'cloudflare-r2-offload-cdn' ),
				)
			);
		}

		global $wpdb;

		// Get next pending delete_local item.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Queue processing requires fresh data.
		$item = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}cfr2_offload_queue
				 WHERE status = %s AND action = %s
				 ORDER BY created_at ASC
				 LIMIT 1",
				QueueStatus::PENDING,
				QueueAction::DELETE_LOCAL
			)
		);

		if ( ! $item ) {
			wp_send_json_success(
				array(
					'done'    => true,
					'message' => __( 'All local files deleted.', 'cloudflare-r2-offload-cdn' ),
				)
			);
		}

		// Mark as processing.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom queue table requires fresh data.
		$wpdb->update(
			$wpdb->prefix . 'cfr2_offload_queue',
			array( 'status' => QueueStatus::PROCESSING ),
			array( 'id' => $item->id ),
			array( '%s' ),
			array( '%d' )
		);

		$attachment_id = (int) $item->attachment_id;
		$file_path     = get_attached_file( $attachment_id );
		$filename      = $file_path ? basename( $file_path ) : "ID: {$attachment_id}";

		// Get credentials (needed for OffloadService constructor).
		$credentials = $this->get_r2_credentials();

		if ( false === $credentials ) {
			$this->mark_item_failed( $item->id, 'R2 credentials not configured' );
			BulkOperationLogger::log( $attachment_id, 'error', 'R2 credentials not configured' );

			wp_send_json_success(
				array(
					'done'     => false,
					'status'   => 'error',
					'filename' => $filename,
					'message'  => __( 'R2 credentials not configured', 'cloudflare-r2-offload-cdn' ),
				)
			);
		}

		$r2      = new R2Client( $credentials );
		$offload = new OffloadService( $r2 );
		$result  = $offload->delete_local_files( $attachment_id );

		if ( $result['success'] ) {
			// Mark completed.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom queue table.
			$wpdb->update(
				$wpdb->prefix . 'cfr2_offload_queue',
				array(
					'status'       => QueueStatus::COMPLETED,
					'processed_at' => current_time( 'mysql' ),
				),
				array( 'id' => $item->id ),
				array( '%s', '%s' ),
				array( '%d' )
			);

			$message = $result['deleted_main']
				? sprintf( __( 'Deleted local files (+%d thumbnails)', 'cloudflare-r2-offload-cdn' ), $result['deleted_thumbs'] )
				: __( 'No local files to delete', 'cloudflare-r2-offload-cdn' );

			BulkOperationLogger::log( $attachment_id, 'success', $message );

			wp_send_json_success(
				array(
					'done'     => false,
					'status'   => 'success',
					'filename' => $filename,
					'message'  => $message,
				)
			);
		} else {
			$error_msg = $result['message'] ?? 'Unknown error';
			$this->mark_item_failed( $item->id, $error_msg );
			BulkOperationLogger::log( $attachment_id, 'error', $error_msg );

			wp_send_json_success(
				array(
					'done'     => false,
					'status'   => 'error',
					'filename' => $filename,
					'message'  => $error_msg,
				)
			);
		}
	}

	/**
	 * AJAX handler for process single bulk item.
	 * Called repeatedly by JavaScript to process queue items one by one.
	 */
	public function ajax_process_bulk_item(): void {
		$this->verify_bulk_nonce();
		$this->check_permissions();

		// Check if cancelled.
		if ( get_transient( TransientKeys::BULK_CANCELLED ) ) {
			wp_send_json_success(
				array(
					'done'    => true,
					'message' => __( 'Bulk offload cancelled.', 'cloudflare-r2-offload-cdn' ),
				)
			);
		}

		global $wpdb;

		// Get next pending item.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Queue processing requires fresh data.
		$item = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}cfr2_offload_queue
				 WHERE status = %s AND action = %s
				 ORDER BY created_at ASC
				 LIMIT 1",
				QueueStatus::PENDING,
				QueueAction::OFFLOAD
			)
		);

		if ( ! $item ) {
			// No more items.
			wp_send_json_success(
				array(
					'done'    => true,
					'message' => __( 'All items processed.', 'cloudflare-r2-offload-cdn' ),
				)
			);
		}

		// Mark as processing.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom queue table requires fresh data.
		$wpdb->update(
			$wpdb->prefix . 'cfr2_offload_queue',
			array( 'status' => QueueStatus::PROCESSING ),
			array( 'id' => $item->id ),
			array( '%s' ),
			array( '%d' )
		);

		$attachment_id = (int) $item->attachment_id;
		$file_path     = get_attached_file( $attachment_id );
		$filename      = $file_path ? basename( $file_path ) : "ID: {$attachment_id}";

		// Get credentials.
		$credentials = $this->get_r2_credentials();

		if ( false === $credentials ) {
			$this->mark_item_failed( $item->id, 'R2 credentials not configured' );
			BulkOperationLogger::log( $attachment_id, 'error', 'R2 credentials not configured' );

			wp_send_json_success(
				array(
					'done'     => false,
					'status'   => 'error',
					'filename' => $filename,
					'message'  => __( 'R2 credentials not configured', 'cloudflare-r2-offload-cdn' ),
				)
			);
		}

		$r2      = new R2Client( $credentials );
		$offload = new OffloadService( $r2 );
		$result  = $offload->offload( $attachment_id );

		if ( $result['success'] ) {
			// Mark completed.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom queue table.
			$wpdb->update(
				$wpdb->prefix . 'cfr2_offload_queue',
				array(
					'status'       => QueueStatus::COMPLETED,
					'processed_at' => current_time( 'mysql' ),
				),
				array( 'id' => $item->id ),
				array( '%s', '%s' ),
				array( '%d' )
			);

			$thumb_info = '';
			if ( ! empty( $result['thumbnails']['total'] ) ) {
				$thumb_info = sprintf(
					' (+%d/%d thumbnails)',
					$result['thumbnails']['success'],
					$result['thumbnails']['total']
				);
			}

			BulkOperationLogger::log( $attachment_id, 'success', 'Offloaded to R2' . $thumb_info );

			wp_send_json_success(
				array(
					'done'     => false,
					'status'   => 'success',
					'filename' => $filename,
					'message'  => __( 'Offloaded to R2', 'cloudflare-r2-offload-cdn' ) . $thumb_info,
				)
			);
		} else {
			$error_msg = $result['message'] ?? 'Unknown error';
			$this->mark_item_failed( $item->id, $error_msg );
			BulkOperationLogger::log( $attachment_id, 'error', $error_msg );

			wp_send_json_success(
				array(
					'done'     => false,
					'status'   => 'error',
					'filename' => $filename,
					'message'  => $error_msg,
				)
			);
		}
	}

	/**
	 * AJAX handler for process single restore item.
	 * Called repeatedly by JavaScript to restore items one by one.
	 */
	public function ajax_process_restore_item(): void {
		$this->verify_bulk_nonce();
		$this->check_permissions();

		// Check if cancelled.
		if ( get_transient( TransientKeys::BULK_CANCELLED ) ) {
			wp_send_json_success(
				array(
					'done'    => true,
					'message' => __( 'Bulk restore cancelled.', 'cloudflare-r2-offload-cdn' ),
				)
			);
		}

		global $wpdb;

		// Get next pending restore item.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Queue processing requires fresh data.
		$item = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}cfr2_offload_queue
				 WHERE status = %s AND action = %s
				 ORDER BY created_at ASC
				 LIMIT 1",
				QueueStatus::PENDING,
				QueueAction::RESTORE
			)
		);

		if ( ! $item ) {
			wp_send_json_success(
				array(
					'done'    => true,
					'message' => __( 'All items restored.', 'cloudflare-r2-offload-cdn' ),
				)
			);
		}

		// Mark as processing.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom queue table requires fresh data.
		$wpdb->update(
			$wpdb->prefix . 'cfr2_offload_queue',
			array( 'status' => QueueStatus::PROCESSING ),
			array( 'id' => $item->id ),
			array( '%s' ),
			array( '%d' )
		);

		$attachment_id = (int) $item->attachment_id;
		$file_path     = get_attached_file( $attachment_id );
		$filename      = $file_path ? basename( $file_path ) : "ID: {$attachment_id}";

		// Get credentials.
		$credentials = $this->get_r2_credentials();

		if ( false === $credentials ) {
			$this->mark_item_failed( $item->id, 'R2 credentials not configured' );
			BulkOperationLogger::log( $attachment_id, 'error', 'R2 credentials not configured' );

			wp_send_json_success(
				array(
					'done'     => false,
					'status'   => 'error',
					'filename' => $filename,
					'message'  => __( 'R2 credentials not configured', 'cloudflare-r2-offload-cdn' ),
				)
			);
		}

		$r2      = new R2Client( $credentials );
		$offload = new OffloadService( $r2 );
		$result  = $offload->restore( $attachment_id );

		if ( $result['success'] ) {
			// Mark completed.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom queue table.
			$wpdb->update(
				$wpdb->prefix . 'cfr2_offload_queue',
				array(
					'status'       => QueueStatus::COMPLETED,
					'processed_at' => current_time( 'mysql' ),
				),
				array( 'id' => $item->id ),
				array( '%s', '%s' ),
				array( '%d' )
			);

			BulkOperationLogger::log( $attachment_id, 'success', 'Restored to local' );

			wp_send_json_success(
				array(
					'done'     => false,
					'status'   => 'success',
					'filename' => $filename,
					'message'  => __( 'Restored to local', 'cloudflare-r2-offload-cdn' ),
				)
			);
		} else {
			$error_msg = $result['message'] ?? 'Unknown error';
			$this->mark_item_failed( $item->id, $error_msg );
			BulkOperationLogger::log( $attachment_id, 'error', $error_msg );

			wp_send_json_success(
				array(
					'done'     => false,
					'status'   => 'error',
					'filename' => $filename,
					'message'  => $error_msg,
				)
			);
		}
	}

	/**
	 * AJAX handler for cancel bulk.
	 */
	public function ajax_cancel_bulk(): void {
		$this->verify_bulk_nonce();
		$this->check_permissions();

		set_transient( TransientKeys::BULK_CANCELLED, true, CacheDuration::CANCEL_TTL );

		// Clear pending queue items.
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom queue table.
		$wpdb->update(
			$wpdb->prefix . 'cfr2_offload_queue',
			array( 'status' => QueueStatus::CANCELLED ),
			array( 'status' => QueueStatus::PENDING ),
			array( '%s' ),
			array( '%s' )
		);

		wp_send_json_success( array( 'message' => __( 'Bulk offload cancelled.', 'cloudflare-r2-offload-cdn' ) ) );
	}

	/**
	 * AJAX handler for get bulk progress.
	 */
	public function ajax_get_bulk_progress(): void {
		$this->verify_bulk_nonce();

		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Queue progress requires fresh data.
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

		// Get current processing item.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Queue status requires fresh data.
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

		wp_send_json_success(
			array(
				'completed'    => (int) $stats->completed,
				'failed'       => (int) $stats->failed,
				'pending'      => (int) $stats->pending,
				'processing'   => (int) $stats->processing,
				'total'        => (int) $stats->total,
				'is_running'   => $stats->pending > 0 || $stats->processing > 0,
				'current_file' => $current_file,
			)
		);
	}

	/**
	 * Mark queue item as failed.
	 *
	 * @param int    $item_id       Queue item ID.
	 * @param string $error_message Error message.
	 */
	private function mark_item_failed( int $item_id, string $error_message ): void {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom queue table.
		$wpdb->update(
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
	}

	/**
	 * AJAX handler for getting bulk button counts.
	 * Returns updated counts for Offload/Restore/Delete Local buttons.
	 */
	public function ajax_get_bulk_counts(): void {
		$this->verify_bulk_nonce();

		global $wpdb;

		// Clear dashboard stats cache first to ensure fresh data.
		delete_transient( TransientKeys::DASHBOARD_STATS );

		// Count non-offloaded attachments.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Count query.
		$total_attachments = (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$wpdb->posts}
			 WHERE post_type = 'attachment' AND post_status = 'inherit'"
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Count offloaded.
		$offloaded_count = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->postmeta}
				 WHERE meta_key = %s AND meta_value = '1'",
				MetaKeys::OFFLOADED
			)
		);

		$not_offloaded_count = max( 0, $total_attachments - $offloaded_count );

		// Count offloaded with local files (disk saveable).
		// Only count attachments that still exist and have local_exists = 1.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom status table.
		$disk_saveable_count = (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$wpdb->prefix}cfr2_offload_status os
			 INNER JOIN {$wpdb->posts} p ON os.attachment_id = p.ID
			 WHERE os.local_exists = 1 AND p.post_type = 'attachment'"
		);

		wp_send_json_success(
			array(
				'offloaded'      => $offloaded_count,
				'not_offloaded'  => $not_offloaded_count,
				'disk_saveable'  => $disk_saveable_count,
			)
		);
	}
}
