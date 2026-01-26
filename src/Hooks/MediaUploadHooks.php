<?php
/**
 * Media Upload Hooks class.
 *
 * @package CFR2OffLoad
 */

namespace ThachPN165\CFR2OffLoad\Hooks;

defined( 'ABSPATH' ) || exit;

use ThachPN165\CFR2OffLoad\Interfaces\HookableInterface;
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
		add_action( 'add_attachment', array( $this, 'on_attachment_added' ), 20 );
		add_action( 'delete_attachment', array( $this, 'on_attachment_deleted' ), 10 );
		add_action( 'cfr2_process_queue', array( QueueProcessor::class, 'process' ) );
	}

	/**
	 * Handle attachment added.
	 *
	 * @param int $attachment_id Attachment ID.
	 */
	public function on_attachment_added( int $attachment_id ): void {
		// Check if auto-offload enabled.
		$settings = get_option( 'cloudflare_r2_offload_cdn_settings', array() );
		if ( empty( $settings['auto_offload'] ) ) {
			return;
		}

		// Validate R2 configured.
		if ( empty( $settings['r2_account_id'] ) || empty( $settings['r2_bucket'] ) ) {
			return;
		}

		$credentials = self::get_r2_credentials( $settings );
		$r2          = new R2Client( $credentials );
		$offload     = new OffloadService( $r2 );
		$offload->queue_offload( $attachment_id );
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
