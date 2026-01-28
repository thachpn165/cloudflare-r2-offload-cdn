<?php
/**
 * Offload Service class.
 *
 * @package CFR2OffLoad
 */

namespace ThachPN165\CFR2OffLoad\Services;

defined( 'ABSPATH' ) || exit;

use ThachPN165\CFR2OffLoad\Hooks\ExtensibilityHooks;

/**
 * OffloadService class - coordinates offload operations.
 */
class OffloadService {

	/**
	 * Meta keys for attachment tracking.
	 */
	private const META_OFFLOADED = '_cfr2_offloaded';
	private const META_R2_URL = '_cfr2_r2_url';
	private const META_R2_KEY = '_cfr2_r2_key';
	private const META_LOCAL_URL = '_cfr2_local_url';

	/**
	 * R2Client instance.
	 *
	 * @var R2Client
	 */
	private R2Client $r2;

	/**
	 * Constructor.
	 *
	 * @param R2Client $r2 R2Client instance.
	 */
	public function __construct( R2Client $r2 ) {
		$this->r2 = $r2;
	}

	/**
	 * Queue attachment for offload.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return bool True if queued, false otherwise.
	 */
	public function queue_offload( int $attachment_id ): bool {
		if ( $this->is_offloaded( $attachment_id ) ) {
			return false;
		}

		global $wpdb;
		$wpdb->insert(
			$wpdb->prefix . 'cfr2_offload_queue',
			array(
				'attachment_id' => $attachment_id,
				'action'        => 'offload',
				'status'        => 'pending',
				'created_at'    => current_time( 'mysql' ),
			),
			array( '%d', '%s', '%s', '%s' )
		);

		// Schedule queue processor if not already scheduled.
		if ( function_exists( 'as_next_scheduled_action' ) ) {
			if ( ! \as_next_scheduled_action( 'cfr2_process_queue' ) ) {
				\as_schedule_single_action( time(), 'cfr2_process_queue' );
			}
		} else {
			// Fallback to wp_cron if Action Scheduler not available.
			if ( ! wp_next_scheduled( 'cfr2_process_queue' ) ) {
				wp_schedule_single_event( time(), 'cfr2_process_queue' );
			}
		}

		return true;
	}

	/**
	 * Perform actual offload.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return array Result array with success/message.
	 */
	public function offload( int $attachment_id ): array {
		// Check if should offload (with filter).
		if ( ! ExtensibilityHooks::should_offload( $attachment_id ) ) {
			return array(
				'success' => false,
				'message' => __( 'Offload not allowed for this attachment', 'cloudflare-r2-offload-cdn' ),
			);
		}

		$file_path = get_attached_file( $attachment_id );
		if ( ! $file_path || ! file_exists( $file_path ) ) {
			return array(
				'success' => false,
				'message' => __( 'File not found', 'cloudflare-r2-offload-cdn' ),
			);
		}

		// Fire before offload hook.
		ExtensibilityHooks::before_offload( $attachment_id );

		// Generate R2 key (with filter).
		$r2_key = ExtensibilityHooks::get_r2_key( $attachment_id, $file_path );

		// Store local URL before offload.
		$local_url = wp_get_attachment_url( $attachment_id );
		update_post_meta( $attachment_id, self::META_LOCAL_URL, $local_url );

		// Upload to R2.
		$result = $this->r2->upload_file( $file_path, $r2_key );

		if ( $result['success'] ) {
			// Update meta.
			update_post_meta( $attachment_id, self::META_OFFLOADED, true );
			update_post_meta( $attachment_id, self::META_R2_URL, $result['url'] );
			update_post_meta( $attachment_id, self::META_R2_KEY, $r2_key );

			// Update database table.
			$this->update_offload_status( $attachment_id, $r2_key, $result['url'], $file_path );

			// Also offload thumbnail sizes.
			$thumb_results = $this->offload_thumbnails( $attachment_id );

			// Build success message with thumbnail info.
			$thumb_info = '';
			if ( $thumb_results['total'] > 0 ) {
				$thumb_info = sprintf(
					' (+%d/%d thumbnails)',
					$thumb_results['success'],
					$thumb_results['total']
				);
			}

			$success_result = array(
				'success'    => true,
				'url'        => $result['url'],
				'thumbnails' => $thumb_results,
				'message'    => __( 'Offloaded successfully', 'cloudflare-r2-offload-cdn' ) . $thumb_info,
			);

			// Fire after offload hook.
			ExtensibilityHooks::after_offload( $attachment_id, $success_result );

			return $success_result;
		}

		// Fire after offload hook even on failure.
		ExtensibilityHooks::after_offload( $attachment_id, $result );

		return $result;
	}

	/**
	 * Offload all thumbnail sizes.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return array Results with success count and failed sizes.
	 */
	private function offload_thumbnails( int $attachment_id ): array {
		$metadata = wp_get_attachment_metadata( $attachment_id );
		$results  = array(
			'total'    => 0,
			'success'  => 0,
			'failed'   => array(),
			'uploaded' => array(),
		);

		if ( empty( $metadata['sizes'] ) ) {
			return $results;
		}

		$file_path  = get_attached_file( $attachment_id );
		$base_dir   = dirname( $file_path );
		$upload_dir = wp_upload_dir();

		$results['total'] = count( $metadata['sizes'] );

		foreach ( $metadata['sizes'] as $size => $data ) {
			$thumb_path = $base_dir . '/' . $data['file'];

			if ( ! file_exists( $thumb_path ) ) {
				$results['failed'][] = array(
					'size'    => $size,
					'message' => 'File not found: ' . $data['file'],
				);
				continue;
			}

			$r2_key = str_replace( $upload_dir['basedir'] . '/', '', $thumb_path );
			$result = $this->r2->upload_file( $thumb_path, $r2_key );

			if ( $result['success'] ) {
				++$results['success'];
				$results['uploaded'][ $size ] = array(
					'r2_key' => $r2_key,
					'url'    => $result['url'] ?? '',
				);
			} else {
				$results['failed'][] = array(
					'size'    => $size,
					'message' => $result['message'] ?? 'Unknown error',
				);
			}
		}

		// Store thumbnail R2 keys in meta for later reference.
		if ( ! empty( $results['uploaded'] ) ) {
			update_post_meta( $attachment_id, '_cfr2_thumbnails', $results['uploaded'] );
		}

		return $results;
	}

	/**
	 * Queue attachment for restore.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return bool True if queued, false otherwise.
	 */
	public function queue_restore( int $attachment_id ): bool {
		if ( ! $this->is_offloaded( $attachment_id ) ) {
			return false;
		}

		global $wpdb;
		$wpdb->insert(
			$wpdb->prefix . 'cfr2_offload_queue',
			array(
				'attachment_id' => $attachment_id,
				'action'        => 'restore',
				'status'        => 'pending',
				'created_at'    => current_time( 'mysql' ),
			),
			array( '%d', '%s', '%s', '%s' )
		);

		if ( function_exists( 'as_next_scheduled_action' ) ) {
			if ( ! \as_next_scheduled_action( 'cfr2_process_queue' ) ) {
				\as_schedule_single_action( time(), 'cfr2_process_queue' );
			}
		} else {
			if ( ! wp_next_scheduled( 'cfr2_process_queue' ) ) {
				wp_schedule_single_event( time(), 'cfr2_process_queue' );
			}
		}

		return true;
	}

	/**
	 * Restore from R2 to local.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return array Result array with success/message.
	 */
	public function restore( int $attachment_id ): array {
		// Fire before restore hook.
		ExtensibilityHooks::before_restore( $attachment_id );

		// Clear offload meta.
		delete_post_meta( $attachment_id, self::META_OFFLOADED );
		delete_post_meta( $attachment_id, self::META_R2_URL );
		delete_post_meta( $attachment_id, self::META_R2_KEY );

		// Remove from offload_status table.
		global $wpdb;
		$wpdb->delete(
			$wpdb->prefix . 'cfr2_offload_status',
			array( 'attachment_id' => $attachment_id ),
			array( '%d' )
		);

		$result = array( 'success' => true );

		// Fire after restore hook.
		ExtensibilityHooks::after_restore( $attachment_id, $result );

		return $result;
	}

	/**
	 * Check if attachment is offloaded.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return bool True if offloaded, false otherwise.
	 */
	public function is_offloaded( int $attachment_id ): bool {
		return (bool) get_post_meta( $attachment_id, self::META_OFFLOADED, true );
	}

	/**
	 * Get R2 URL for attachment.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return string|null R2 URL or null if not offloaded.
	 */
	public function get_r2_url( int $attachment_id ): ?string {
		$url = get_post_meta( $attachment_id, self::META_R2_URL, true );
		return $url ? $url : null;
	}

	/**
	 * Get local URL for attachment.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return string|null Local URL or null if not stored.
	 */
	public function get_local_url( int $attachment_id ): ?string {
		$url = get_post_meta( $attachment_id, self::META_LOCAL_URL, true );
		return $url ? $url : null;
	}

	/**
	 * Update offload status in database.
	 *
	 * @param int    $attachment_id Attachment ID.
	 * @param string $r2_key        R2 object key.
	 * @param string $r2_url        R2 URL.
	 * @param string $local_path    Local file path.
	 */
	private function update_offload_status( int $attachment_id, string $r2_key, string $r2_url, string $local_path ): void {
		global $wpdb;

		$wpdb->replace(
			$wpdb->prefix . 'cfr2_offload_status',
			array(
				'attachment_id' => $attachment_id,
				'r2_key'        => $r2_key,
				'r2_url'        => $r2_url,
				'local_path'    => $local_path,
				'local_exists'  => file_exists( $local_path ) ? 1 : 0,
				'file_size'     => filesize( $local_path ),
				'offloaded_at'  => current_time( 'mysql' ),
			),
			array( '%d', '%s', '%s', '%s', '%d', '%d', '%s' )
		);
	}
}
