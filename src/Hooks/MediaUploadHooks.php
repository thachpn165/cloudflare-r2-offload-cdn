<?php
/**
 * Media Upload Hooks class.
 *
 * @package CFR2OffLoad
 */

namespace ThachPN165\CFR2OffLoad\Hooks;

defined( 'ABSPATH' ) || exit;

use ThachPN165\CFR2OffLoad\Interfaces\HookableInterface;
use ThachPN165\CFR2OffLoad\Services\BulkOperationLogger;
use ThachPN165\CFR2OffLoad\Services\OffloadService;
use ThachPN165\CFR2OffLoad\Services\R2Client;
use ThachPN165\CFR2OffLoad\Services\QueueProcessor;
use ThachPN165\CFR2OffLoad\Traits\CredentialsHelperTrait;

/**
 * MediaUploadHooks class - handles media upload and deletion hooks.
 */
class MediaUploadHooks implements HookableInterface {

	use CredentialsHelperTrait;

	/**
	 * Register hooks.
	 */
	public function register_hooks(): void {
		// Use wp_generate_attachment_metadata filter instead of add_attachment action
		// This ensures thumbnails are already generated before offloading.
		add_filter( 'wp_generate_attachment_metadata', array( $this, 'on_attachment_metadata_generated' ), 20, 2 );
		add_action( 'delete_attachment', array( $this, 'on_attachment_deleted' ), 10 );
		add_action( 'cfr2_process_queue', array( QueueProcessor::class, 'process' ) );
	}

	/**
	 * Handle attachment metadata generated (after thumbnails created).
	 *
	 * @param array $metadata      Attachment metadata.
	 * @param int   $attachment_id Attachment ID.
	 * @return array Unmodified metadata.
	 */
	public function on_attachment_metadata_generated( array $metadata, int $attachment_id ): array {
		$this->process_auto_offload( $attachment_id );
		return $metadata;
	}

	/**
	 * Process auto-offload for attachment.
	 *
	 * @param int $attachment_id Attachment ID.
	 */
	private function process_auto_offload( int $attachment_id ): void {
		// Check if auto-offload enabled.
		$settings = get_option( 'cloudflare_r2_offload_cdn_settings', array() );
		if ( empty( $settings['auto_offload'] ) ) {
			return;
		}

		// Validate R2 configured.
		if ( empty( $settings['r2_account_id'] ) || empty( $settings['r2_bucket'] ) ) {
			BulkOperationLogger::log( $attachment_id, 'error', 'R2 not configured' );
			return;
		}

		$credentials = self::get_r2_credentials( $settings );

		// Check if secret key is available.
		if ( empty( $credentials['secret_access_key'] ) ) {
			BulkOperationLogger::log( $attachment_id, 'error', 'R2 secret key not configured' );
			return;
		}

		$r2      = new R2Client( $credentials );
		$offload = new OffloadService( $r2 );

		// Offload immediately.
		$result = $offload->offload( $attachment_id );

		// Log the result.
		if ( $result['success'] ) {
			$thumb_info = '';
			if ( ! empty( $result['thumbnails']['total'] ) ) {
				$thumb_info = sprintf(
					' (+%d/%d thumbnails)',
					$result['thumbnails']['success'],
					$result['thumbnails']['total']
				);
			}
			BulkOperationLogger::log( $attachment_id, 'success', 'Offloaded to R2' . $thumb_info );
		} else {
			BulkOperationLogger::log( $attachment_id, 'error', $result['message'] ?? 'Unknown error' );
		}
	}

	/**
	 * Handle attachment deleted.
	 *
	 * @param int $attachment_id Attachment ID.
	 */
	public function on_attachment_deleted( int $attachment_id ): void {
		$r2_key = get_post_meta( $attachment_id, '_cfr2_r2_key', true );
		if ( ! $r2_key ) {
			return;
		}

		global $wpdb;
		$wpdb->insert(
			$wpdb->prefix . 'cfr2_offload_queue',
			array(
				'attachment_id' => $attachment_id,
				'action'        => 'delete',
				'status'        => 'pending',
				'created_at'    => current_time( 'mysql' ),
			),
			array( '%d', '%s', '%s', '%s' )
		);

		$wpdb->delete(
			$wpdb->prefix . 'cfr2_offload_status',
			array( 'attachment_id' => $attachment_id ),
			array( '%d' )
		);
	}
}
